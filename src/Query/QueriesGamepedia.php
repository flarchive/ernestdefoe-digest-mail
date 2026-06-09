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
 * QueriesGamepedia: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesGamepedia
{

    // -------------------------------------------------------------------------
    // Section 8 — Gamepedia (huseyinfiliz/gamepedia)
    // -------------------------------------------------------------------------

    /**
     * Build the Gamepedia section data for huseyinfiliz/gamepedia.
     *
     * Returns an array with:
     *   enabled        bool
     *   mostDiscussed  array of [ game, postCount, discussionCount ] — games
     *                  with the most post activity (via game_discussions pivot)
     *                  during the period, up to $limit entries.
     *   newGames       array of game objects added during the period.
     */
    public function getGamepedia(Carbon $since, int $limit = 5): array
    {
        $extInstalled = $this->extensions->isEnabled('huseyinfiliz-gamepedia');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_gamepedia');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'mostDiscussed' => [], 'newGames' => []];
        }

        $prefix    = $this->db->getTablePrefix();
        $since_str = $since->toDateTimeString();

        $mostDiscussedRows = $this->db->select("
            SELECT
                g.id, g.name, g.slug, g.cover_image_id, g.genres, g.developer, g.release_date,
                COUNT(DISTINCT p.id)  AS post_count,
                COUNT(DISTINCT d.id)  AS discussion_count
            FROM {$prefix}gamepedia_game_discussions AS gd
            INNER JOIN {$prefix}gamepedia_games       AS g ON g.id = gd.game_id
            INNER JOIN {$prefix}discussions            AS d ON d.id = gd.discussion_id
            INNER JOIN {$prefix}posts                  AS p ON p.discussion_id = d.id
            WHERE p.created_at >= ?
              AND p.type       = 'comment'
              AND p.hidden_at  IS NULL
              AND d.hidden_at  IS NULL
            GROUP BY g.id, g.name, g.slug, g.cover_image_id, g.genres, g.developer, g.release_date
            ORDER BY post_count DESC
            LIMIT {$limit}
        ", [$since_str]);
        $mostDiscussedRows = collect($mostDiscussedRows);

        $mostDiscussed = [];
        foreach ($mostDiscussedRows as $row) {
            $mostDiscussed[] = [
                'game'            => $row,
                'postCount'       => (int) $row->post_count,
                'discussionCount' => (int) $row->discussion_count,
            ];
        }

        $newGames = $this->db->table('gamepedia_games')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'cover_image_id', 'genres', 'developer', 'release_date'])
            ->all();

        return [
            'enabled'       => true,
            'mostDiscussed' => $mostDiscussed,
            'newGames'      => $newGames,
        ];
    }


    // -------------------------------------------------------------------------
    // Section 8b — Resofire Gamepedia (resofire/gamepedia)
    // -------------------------------------------------------------------------

    /**
     * Build the Gamepedia section data for resofire/gamepedia 2.x.
     *
     * Schema:
     *   gamepedia_games            — id, name, slug, cover_image_url, developer,
     *                                publisher, first_release_date, created_at
     *   gamepedia_discussion_game  — pivot: discussion_id, game_id, created_at
     *   gamepedia_genres           — id, name, slug
     *   gamepedia_game_genre       — pivot: game_id, genre_id
     *
     * Returns an array with:
     *   enabled        bool
     *   mostDiscussed  array of [ game (stdClass), postCount, discussionCount,
     *                             genres (array of stdClass) ]
     *   newGames       array of stdClass — each with genres array attached
     *   topGenres      array of [ genre (stdClass), gameCount, postCount ]
     */
    public function getResofireGamepedia(Carbon $since, int $limit = 5): array
    {
        $extInstalled = $this->extensions->isEnabled('resofire-gamepedia');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_resofire_gamepedia');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'mostDiscussed' => [], 'newGames' => [], 'topGenres' => []];
        }

        $prefix    = $this->db->getTablePrefix();
        $since_str = $since->toDateTimeString();

        // ── Most discussed ────────────────────────────────────────────────────
        $mostDiscussedRows = $this->db->select("
            SELECT
                g.id, g.name, g.slug, g.cover_image_url, g.developer, g.publisher,
                g.first_release_date,
                COUNT(DISTINCT p.id) AS post_count,
                COUNT(DISTINCT d.id) AS discussion_count
            FROM {$prefix}gamepedia_discussion_game AS gd
            INNER JOIN {$prefix}gamepedia_games      AS g ON g.id = gd.game_id
            INNER JOIN {$prefix}discussions           AS d ON d.id = gd.discussion_id
            INNER JOIN {$prefix}posts                 AS p ON p.discussion_id = d.id
            WHERE p.created_at >= ?
              AND p.type        = 'comment'
              AND p.hidden_at   IS NULL
              AND d.hidden_at   IS NULL
            GROUP BY g.id, g.name, g.slug, g.cover_image_url, g.developer,
                     g.publisher, g.first_release_date
            ORDER BY post_count DESC
            LIMIT {$limit}
        ", [$since_str]);

        $mdIds      = collect($mostDiscussedRows)->pluck('id')->all();
        $mdGenreMap = $this->loadGenresForGames($mdIds);

        $mostDiscussed = [];
        foreach ($mostDiscussedRows as $row) {
            $mostDiscussed[] = [
                'game'            => $row,
                'postCount'       => (int) $row->post_count,
                'discussionCount' => (int) $row->discussion_count,
                'genres'          => $mdGenreMap[$row->id] ?? [],
            ];
        }

        // ── New games ─────────────────────────────────────────────────────────
        $newGameRows = $this->db->table('gamepedia_games')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'cover_image_url', 'developer',
                   'publisher', 'first_release_date', 'created_at'])
            ->all();

        $ngIds      = array_map(fn ($g) => $g->id, $newGameRows);
        $ngGenreMap = $this->loadGenresForGames($ngIds);

        $newGames = [];
        foreach ($newGameRows as $game) {
            $game->genres = $ngGenreMap[$game->id] ?? [];
            $newGames[]   = $game;
        }

        // ── Top genres ────────────────────────────────────────────────────────
        $topGenreRows = $this->db->select("
            SELECT
                gr.id, gr.name, gr.slug,
                COUNT(DISTINCT g.id) AS game_count,
                COUNT(DISTINCT p.id) AS post_count
            FROM {$prefix}gamepedia_genres              AS gr
            INNER JOIN {$prefix}gamepedia_game_genre    AS gg ON gg.genre_id = gr.id
            INNER JOIN {$prefix}gamepedia_games          AS g  ON g.id  = gg.game_id
            INNER JOIN {$prefix}gamepedia_discussion_game AS gd ON gd.game_id = g.id
            INNER JOIN {$prefix}discussions               AS d  ON d.id  = gd.discussion_id
            INNER JOIN {$prefix}posts                     AS p  ON p.discussion_id = d.id
            WHERE p.created_at >= ?
              AND p.type        = 'comment'
              AND p.hidden_at   IS NULL
              AND d.hidden_at   IS NULL
            GROUP BY gr.id, gr.name, gr.slug
            ORDER BY post_count DESC
            LIMIT 5
        ", [$since_str]);

        $topGenres = [];
        foreach ($topGenreRows as $row) {
            $topGenres[] = [
                'genre'     => $row,
                'gameCount' => (int) $row->game_count,
                'postCount' => (int) $row->post_count,
            ];
        }

        return [
            'enabled'       => true,
            'mostDiscussed' => $mostDiscussed,
            'newGames'      => $newGames,
            'topGenres'     => $topGenres,
        ];
    }


    /**
     * Load genres for a set of game IDs from resofire/gamepedia schema.
     * Returns a map of [ game_id => [ stdClass(id, name, slug), ... ] ].
     */
    private function loadGenresForGames(array $gameIds): array
    {
        if (empty($gameIds)) return [];

        $rows = $this->db->table('gamepedia_game_genre AS gg')
            ->join('gamepedia_genres AS gr', 'gr.id', '=', 'gg.genre_id')
            ->whereIn('gg.game_id', $gameIds)
            ->get(['gg.game_id', 'gr.id', 'gr.name', 'gr.slug']);

        $map = [];
        foreach ($rows as $row) {
            $genre       = new \stdClass();
            $genre->id   = $row->id;
            $genre->name = $row->name;
            $genre->slug = $row->slug;
            $map[$row->game_id][] = $genre;
        }

        return $map;
    }
}
