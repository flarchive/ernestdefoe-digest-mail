<?php

namespace Resofire\DigestMail\Query;

use Carbon\Carbon;
use Flarum\User\User;

/**
 * QueriesPicks: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesPicks
{


    // -------------------------------------------------------------------------
    // Section 7b — Picks (ernestdefoe/picks)
    // -------------------------------------------------------------------------

    /**
     * Build the Picks section data for ernestdefoe/picks.
     *
     * Returns an array with:
     *   enabled            bool
     *   confidenceMode     bool — whether confidence ratings are active
     *   leaderboardScope   string — 'week' | 'season' | 'alltime'
     *   currentWeek        array|null — [ id, name, weekNumber, isOpen ]
     *   upcomingEvents     array of upcoming scheduled events with cutoff in future
     *   recentResults      array of finished events within the digest period
     *   leaderboard        array of top N users with rank movement data
     *   leaderboardLabel   string — human-readable label for the leaderboard scope
     *   picksForumUrl      string — full URL to /picks
     */
    public function getPicks(Carbon $since, int $limit = 5): array
    {
        $empty = [
            'enabled'          => false,
            'confidenceMode'   => false,
            'leaderboardScope' => 'alltime',
            'currentWeek'      => null,
            'upcomingEvents'   => [],
            'recentResults'    => [],
            'leaderboard'      => [],
            'leaderboardLabel' => '',
            'picksForumUrl'    => '',
        ];

        $extInstalled = $this->extensions->isEnabled('ernestdefoe-picks');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_picks');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return $empty;
        }

        $now            = Carbon::now('UTC');
        $baseUrl        = rtrim($this->settings->get('url', ''), '/');
        $confidenceMode = (bool) $this->settings->get('ernestdefoe-picks.confidence_mode', false);
        $lbScope        = $this->settings->get('ernestdefoe-digest-mail.picks_leaderboard_scope', 'alltime');
        if (!in_array($lbScope, ['week', 'season', 'alltime'], true)) {
            $lbScope = 'alltime';
        }

        // --- Find the current open week (if any) ---
        $currentWeek = null;
        $openWeekRow = $this->db->table('picks_weeks')
            ->where('is_open', true)
            ->orderByDesc('id')
            ->first(['id', 'name', 'week_number', 'season_id', 'is_open']);

        if ($openWeekRow) {
            $currentWeek = [
                'id'         => $openWeekRow->id,
                'name'       => $openWeekRow->name,
                'weekNumber' => $openWeekRow->week_number,
                'isOpen'     => (bool) $openWeekRow->is_open,
            ];
        }

        // --- Upcoming events: scheduled, cutoff in the future ---
        $upcomingRows = $this->db->table('picks_events')
            ->where('status', 'scheduled')
            ->where('cutoff_date', '>', $now)
            ->orderBy('match_date')
            ->limit($limit)
            ->get(['id', 'week_id', 'home_team_id', 'away_team_id', 'match_date', 'cutoff_date', 'neutral_site']);

        // --- Recent results: finished events within the digest period ---
        $recentRows = $this->db->table('picks_events')
            ->where('status', 'finished')
            ->where('match_date', '>=', $since)
            ->orderByDesc('match_date')
            ->limit($limit)
            ->get(['id', 'home_team_id', 'away_team_id', 'match_date', 'home_score', 'away_score', 'result']);

        // Batch-load all teams referenced across both sets
        $teamIds = collect($upcomingRows)->pluck('home_team_id')
            ->merge(collect($upcomingRows)->pluck('away_team_id'))
            ->merge(collect($recentRows)->pluck('home_team_id'))
            ->merge(collect($recentRows)->pluck('away_team_id'))
            ->unique()->filter()->values()->all();

        $teams = $this->db->table('picks_teams')
            ->whereIn('id', $teamIds)
            ->get(['id', 'name', 'abbreviation', 'logo_path', 'logo_dark_path'])
            ->keyBy('id');

        // Batch-load week names for upcoming events
        $weekIds = collect($upcomingRows)->pluck('week_id')->unique()->filter()->values()->all();
        $weeks   = count($weekIds)
            ? $this->db->table('picks_weeks')->whereIn('id', $weekIds)->get(['id', 'name'])->keyBy('id')
            : collect();

        // --- Build upcoming events ---
        $upcoming = [];
        foreach ($upcomingRows as $ev) {
            $homeTeam = $teams->get($ev->home_team_id);
            $awayTeam = $teams->get($ev->away_team_id);
            if (!$homeTeam || !$awayTeam) continue;
            $week = $ev->week_id ? $weeks->get($ev->week_id) : null;
            $upcoming[] = [
                'id'          => $ev->id,
                'homeTeam'    => $homeTeam,
                'awayTeam'    => $awayTeam,
                'matchDate'   => Carbon::parse($ev->match_date),
                'cutoff'      => Carbon::parse($ev->cutoff_date),
                'neutralSite' => (bool) $ev->neutral_site,
                'weekName'    => $week?->name ?? null,
            ];
        }

        // --- Build recent results ---
        $results = [];
        foreach ($recentRows as $ev) {
            $homeTeam = $teams->get($ev->home_team_id);
            $awayTeam = $teams->get($ev->away_team_id);
            if (!$homeTeam || !$awayTeam) continue;
            $results[] = [
                'homeTeam'  => $homeTeam,
                'awayTeam'  => $awayTeam,
                'matchDate' => Carbon::parse($ev->match_date),
                'homeScore' => $ev->home_score,
                'awayScore' => $ev->away_score,
                'result'    => $ev->result, // 'home' | 'away'
            ];
        }

        // --- Build leaderboard based on configured scope ---
        $lbLabel = '';
        $lbQuery = $this->db->table('picks_user_scores')
            ->where('total_picks', '>', 0)
            ->orderByDesc('total_points')
            ->orderByDesc('correct_picks')
            ->limit($limit);

        if ($lbScope === 'week') {
            // Use the most recently completed week that has scored picks
            $lastScoredWeekRow = $this->db->table('picks_user_scores')
                ->whereNotNull('week_id')
                ->orderByDesc('week_id')
                ->first(['week_id', 'season_id']);

            if ($lastScoredWeekRow) {
                $lbQuery->where('week_id', $lastScoredWeekRow->week_id)
                        ->where('season_id', $lastScoredWeekRow->season_id);
                $weekRow = $this->db->table('picks_weeks')
                    ->where('id', $lastScoredWeekRow->week_id)
                    ->first(['name']);
                $lbLabel = $weekRow ? $weekRow->name . ' — Picks Leaderboard' : 'Picks Leaderboard';
            } else {
                // No scored week data yet — fall back to alltime
                $lbScope = 'alltime';
            }
        }

        if ($lbScope === 'season') {
            // Use the most recent season that has scored picks
            $lastScoredSeasonRow = $this->db->table('picks_user_scores')
                ->whereNotNull('season_id')
                ->whereNull('week_id')
                ->orderByDesc('season_id')
                ->first(['season_id']);

            if ($lastScoredSeasonRow) {
                $lbQuery->whereNull('week_id')
                        ->where('season_id', $lastScoredSeasonRow->season_id);
                $seasonRow = $this->db->table('picks_seasons')
                    ->where('id', $lastScoredSeasonRow->season_id)
                    ->first(['name']);
                $lbLabel = $seasonRow ? $seasonRow->name . ' — Picks Leaderboard' : 'Picks Leaderboard';
            } else {
                // No scored season data yet — fall back to alltime
                $lbScope = 'alltime';
            }
        }

        if ($lbScope === 'alltime') {
            $lbQuery->whereNull('week_id')->whereNull('season_id');
            $lbLabel = 'All-Time Picks Leaderboard';
        }

        $lbRows    = $lbQuery->get(['user_id', 'total_points', 'total_picks', 'correct_picks', 'previous_rank']);
        $lbUserIds = $lbRows->pluck('user_id')->all();
        $lbUsers   = User::whereIn('id', $lbUserIds)->get()->keyBy('id');

        $leaderboard = [];
        foreach ($lbRows as $i => $row) {
            $user = $lbUsers->get($row->user_id);
            if (!$user) continue;
            $currentRank  = $i + 1;
            $previousRank = isset($row->previous_rank) ? (int) $row->previous_rank : null;
            $movement     = $previousRank !== null ? $previousRank - $currentRank : null;
            $accuracy     = $row->total_picks > 0
                ? round(($row->correct_picks / $row->total_picks) * 100)
                : 0;
            $leaderboard[] = [
                'rank'         => $currentRank,
                'previousRank' => $previousRank,
                'movement'     => $movement,
                'user'         => $user,
                'totalPoints'  => (int) $row->total_points,
                'totalPicks'   => (int) $row->total_picks,
                'correctPicks' => (int) $row->correct_picks,
                'accuracy'     => $accuracy,
            ];
        }

        return [
            'enabled'          => true,
            'confidenceMode'   => $confidenceMode,
            'leaderboardScope' => $lbScope,
            'currentWeek'      => $currentWeek,
            'upcomingEvents'   => $upcoming,
            'recentResults'    => $results,
            'leaderboard'      => $leaderboard,
            'leaderboardLabel' => $lbLabel,
            'picksForumUrl'    => $baseUrl . '/picks',
        ];
    }
}
