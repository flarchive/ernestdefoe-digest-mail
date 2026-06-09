<?php

namespace Resofire\DigestMail\Query;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\ConnectionInterface;

/**
 * QueriesFavorites: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesFavorites
{

    /**
     * Returns top discussions ranked by engagement (likes and/or reactions)
     * during the period. Only discussions with at least 1 engagement shown.
     *
     * Each entry:
     *   discussion   — Discussion model with user eager-loaded
     *   score        — total engagement count (for ranking)
     *   likeCount    — total likes during period (0 if reactions-only)
     *   reactions    — array of [ emoji => count ] sorted by count desc (empty if likes-only)
     *   mode         — 'likes' | 'reactions' | 'both'
     */
    public function getFavoriteDiscussions(User $actor, Carbon $since, int $limit): array
    {
        if ($limit <= 0) return [];

        $prefix    = $this->db->getTablePrefix();
        $since_str = $since->toDateTimeString();

        $likesOn     = $this->extensions->isEnabled('flarum-likes');
        $reactionsOn = $this->extensions->isEnabled('fof-reactions') || $this->extensions->isEnabled('resofire-reactions');
        $rawEnable   = $this->settings->get('ernestdefoe-digest-mail.enable_reactions');
        $reactionsEnabled = $reactionsOn && ($rawEnable === null || $rawEnable === '' || $rawEnable === '1');

        // Neither extension active — nothing to show
        if (!$likesOn && !$reactionsOn) return [];

        // thumbsdown and confused exist in fof/reactions; neither exists in resofire/reactions.
        // The query safely returns an empty exclusion list if neither identifier is found.
        $excludedIdentifiers = ['thumbsdown', 'confused'];

        if ($reactionsEnabled) {
            // Get excluded reaction IDs (db->table() auto-applies the prefix)
            $excludedIds = $this->db->table('reactions')
                ->whereIn('identifier', $excludedIdentifiers)
                ->pluck('id')
                ->toArray();

            $excludedIdsSql = count($excludedIds)
                ? 'AND pr.reaction_id NOT IN (' . implode(',', array_map('intval', $excludedIds)) . ')'
                : '';

            // Use post_reactions only — fof/reactions also writes to post_likes for thumbsup,
            // so querying both would double-count.
            $rows = $this->db->select("
                SELECT
                    d.id,
                    d.title,
                    d.slug,
                    d.user_id,
                    r.identifier,
                    COUNT(*) AS reaction_count
                FROM {$prefix}post_reactions AS pr
                INNER JOIN {$prefix}reactions   AS r ON r.id  = pr.reaction_id
                INNER JOIN {$prefix}posts        AS p ON p.id  = pr.post_id
                INNER JOIN {$prefix}discussions  AS d ON d.id  = p.discussion_id
                WHERE pr.created_at >= ?
                  AND p.hidden_at   IS NULL
                  AND d.hidden_at   IS NULL
                  {$excludedIdsSql}
                GROUP BY d.id, d.title, d.slug, d.user_id, r.identifier
            ", [$since_str]);

            // Pivot rows into per-discussion reaction counts
            $pivot = [];
            foreach ($rows as $row) {
                if (!isset($pivot[$row->id])) {
                    $pivot[$row->id] = [
                        'title'     => $row->title,
                        'slug'      => $row->slug,
                        'user_id'   => $row->user_id,
                        'reactions' => [],
                        'likeCount' => 0,
                    ];
                }
                $pivot[$row->id]['reactions'][$row->identifier] = (int) $row->reaction_count;
            }

            // Score = sum of all non-excluded reactions, must be >= 1
            $scored = [];
            foreach ($pivot as $discId => $data) {
                $score = (int) array_sum($data['reactions']);
                if ($score < 1) continue;
                $scored[$discId] = ['score' => $score, 'data' => $data];
            }

            // Sort by score descending
            uasort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $scored = array_slice($scored, 0, $limit, true);
            $mode = 'reactions';

        } else {
            // Likes-only mode (flarum-likes is always present if we reach here)
            $rows = $this->db->select("
                SELECT
                    d.id,
                    d.title,
                    d.slug,
                    d.user_id,
                    COUNT(*) AS like_count
                FROM {$prefix}post_likes    AS pl
                INNER JOIN {$prefix}posts        AS p ON p.id = pl.post_id
                INNER JOIN {$prefix}discussions  AS d ON d.id = p.discussion_id
                WHERE pl.created_at >= ?
                  AND p.hidden_at   IS NULL
                  AND d.hidden_at   IS NULL
                GROUP BY d.id, d.title, d.slug, d.user_id
                HAVING like_count >= 1
                ORDER BY like_count DESC
                LIMIT {$limit}
            ", [$since_str]);

            $scored = [];
            foreach ($rows as $row) {
                $scored[(int) $row->id] = [
                    'score' => (int) $row->like_count,
                    'data'  => [
                        'title'     => $row->title,
                        'slug'      => $row->slug,
                        'user_id'   => $row->user_id,
                        'reactions' => [],
                        'likeCount' => (int) $row->like_count,
                    ],
                ];
            }
            $mode = 'likes';
        }

        if (empty($scored)) return [];

        // Eager-load discussion authors, filtered to what the actor can see
        $discIds     = array_keys($scored);
        $discussions = Discussion::whereIn('id', $discIds)
            ->whereVisibleTo($actor)
            ->with('user')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($scored as $discId => $entry) {
            if (!isset($discussions[$discId])) continue;

            $disc = $discussions[$discId];
            $data = $entry['data'];

            // Build sorted emoji breakdown (reactions mode only)
            $emojiBreakdown = [];
            if ($mode === 'reactions') {
                $reactionCounts = $data['reactions'];
                arsort($reactionCounts);
                foreach ($reactionCounts as $identifier => $count) {
                    if (isset(self::REACTION_EMOJI[$identifier]) && $count > 0) {
                        $emojiBreakdown[] = [
                            'emoji' => self::REACTION_EMOJI[$identifier],
                            'count' => $count,
                        ];
                    }
                }
            }

            $result[] = [
                'discussion' => $disc,
                'score'      => $entry['score'],
                'likeCount'  => $data['likeCount'],
                'reactions'  => $emojiBreakdown,
                'mode'       => $mode,
            ];
        }

        return $result;
    }
}
