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
 * QueriesAwards: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesAwards
{

    // -------------------------------------------------------------------------
    // Section — Awards (huseyinfiliz/awards integration)
    // -------------------------------------------------------------------------

    /**
     * Returns all non-draft awards relevant for digest promotion.
     * Each entry:
     *   award        — stdClass row from the awards table
     *   effectiveStatus — 'upcoming' | 'active' | 'ended' | 'published'
     *   categories   — array of stdClass rows with nominee_count and vote_count
     *   totalVotes   — int
     *   topNominees  — array of [ categoryName, nomineeName ] (only when show_live_votes)
     */
    public function getAwards(): array
    {
        $extInstalled = $this->extensions->isEnabled('huseyinfiliz-awards');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_awards');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'awards' => []];
        }

        // Note: db->table() auto-applies the connection prefix.
        $now = \Carbon\Carbon::now()->toDateTimeString();

        $awardRows = $this->db->table('awards')
            ->whereNotIn('status', ['draft'])
            ->orderByDesc('starts_at')
            ->get()
            ->all();

        if (empty($awardRows)) {
            return ['enabled' => true, 'awards' => []];
        }

        // Resolve effective status for every award up front (pure PHP, no queries).
        $statuses = [];
        foreach ($awardRows as $award) {
            if ($award->status === 'published') {
                $statuses[$award->id] = 'published';
            } elseif ($award->status === 'ended') {
                $statuses[$award->id] = 'ended';
            } elseif ($award->status === 'active' && $award->ends_at && $award->ends_at < $now) {
                $statuses[$award->id] = 'ended';
            } elseif ($award->status === 'active' && $award->starts_at && $award->starts_at > $now) {
                $statuses[$award->id] = 'upcoming';
            } else {
                $statuses[$award->id] = 'active';
            }
        }

        $awardIds = array_map(fn ($a) => (int) $a->id, $awardRows);
        $prefix   = $this->db->getTablePrefix();
        $in       = implode(',', array_fill(0, count($awardIds), '?'));

        // Category vote/nominee counts for ALL awards in ONE query (was one query
        // per award — an N+1), grouped by award_id below.
        $categoryRows = $this->db->select("
            SELECT
                ac.award_id,
                ac.id,
                ac.name,
                ac.slug,
                ac.description,
                ac.sort_order,
                COUNT(DISTINCT an.id)  AS nominee_count,
                COUNT(DISTINCT av.id)  AS vote_count
            FROM {$prefix}award_categories AS ac
            LEFT JOIN {$prefix}award_nominees   AS an ON an.category_id = ac.id
            LEFT JOIN {$prefix}award_votes       AS av ON av.category_id = ac.id
            WHERE ac.award_id IN ($in)
            GROUP BY ac.award_id, ac.id, ac.name, ac.slug, ac.description, ac.sort_order
            ORDER BY ac.sort_order ASC
        ", $awardIds);

        $categoriesByAward = [];
        foreach ($categoryRows as $row) {
            $categoriesByAward[(int) $row->award_id][] = $row;
        }

        // Which awards should show live top-nominees?
        $liveIds = [];
        foreach ($awardRows as $award) {
            if ((bool) $award->show_live_votes && in_array($statuses[$award->id], ['active', 'published'], true)) {
                $liveIds[] = (int) $award->id;
            }
        }

        // Top nominees for ALL live awards in ONE query (also was an N+1).
        $topByAward = [];
        if ($liveIds) {
            $inLive = implode(',', array_fill(0, count($liveIds), '?'));
            $topRows = $this->db->select("
                SELECT
                    ac.award_id,
                    ac.name  AS category_name,
                    an.name  AS nominee_name,
                    an.image_url AS nominee_image,
                    COUNT(av.id) + COALESCE(an.vote_adjustment, 0) AS vote_count
                FROM {$prefix}award_categories AS ac
                INNER JOIN {$prefix}award_nominees AS an ON an.category_id = ac.id
                LEFT JOIN  {$prefix}award_votes    AS av ON av.nominee_id  = an.id
                WHERE ac.award_id IN ($inLive)
                GROUP BY ac.award_id, ac.id, ac.name, an.id, an.name, an.image_url, an.vote_adjustment
                ORDER BY ac.sort_order ASC, vote_count DESC
            ", $liveIds);

            // Keep only the top nominee per (award, category).
            $seen = [];
            foreach ($topRows as $row) {
                $aid = (int) $row->award_id;
                $key = $aid . "\0" . $row->category_name;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $topByAward[$aid][] = [
                    'categoryName'  => $row->category_name,
                    'nomineeName'   => $row->nominee_name,
                    'nomineeImage'  => $row->nominee_image,
                    'voteCount'     => (int) $row->vote_count,
                ];
            }
        }

        $awards = [];
        foreach ($awardRows as $award) {
            $cats = $categoriesByAward[(int) $award->id] ?? [];
            $awards[] = [
                'award'          => $award,
                'effectiveStatus'=> $statuses[$award->id],
                'categories'     => $cats,
                'totalVotes'     => (int) array_sum(array_map(fn ($row) => $row->vote_count, $cats)),
                'topNominees'    => $topByAward[(int) $award->id] ?? [],
            ];
        }

        return ['enabled' => true, 'awards' => $awards];
    }
}
