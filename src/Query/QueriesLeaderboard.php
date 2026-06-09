<?php

namespace Resofire\DigestMail\Query;

use Carbon\Carbon;
use Flarum\User\User;

/**
 * QueriesLeaderboard: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesLeaderboard
{

    // -------------------------------------------------------------------------
    // Section 6 — Leaderboard
    // -------------------------------------------------------------------------

    /**
     * Build the leaderboard section data.
     *
     * Returns an array with:
     *   entries      — top $limit users with rank, points, period_points,
     *                  rank_change, is_new, and user model
     *   biggestMover — the entry with the highest period_points (or null)
     *   enabled      — false if huseyinfiliz-leaderboard is not installed
     *
     * Rank change is computed by:
     *   1. Fetching all-time totals for the full board
     *   2. Summing each user's period points from leaderboard_points
     *   3. Subtracting period points from totals → "points at period start"
     *   4. Re-ranking by that reconstructed value
     *   5. Comparing to current rank
     */
    public function getLeaderboard(Carbon $since, int $limit = 10): array
    {
        $extInstalled = $this->extensions->isEnabled('huseyinfiliz-leaderboard');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_leaderboard');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'entries' => [], 'biggestMover' => null];
        }

        // Use unprefixed names — ->table() applies the DB prefix automatically.
        $totalsTable  = 'leaderboard_user_totals';
        $pointsTable  = 'leaderboard_points';

        // --- All-time totals (current ranking) ---
        $totals = $this->db->table($totalsTable)
            ->where('points_total', '>', 0)
            ->orderByDesc('points_total')
            ->orderBy('user_id')
            ->get(['user_id', 'points_total']);

        if ($totals->isEmpty()) {
            return ['enabled' => true, 'entries' => [], 'biggestMover' => null];
        }

        // Assign current ranks
        $currentRanks = [];
        foreach ($totals as $i => $row) {
            $currentRanks[$row->user_id] = $i + 1;
        }

        // --- Period points per user ---
        $periodRows = $this->db->table($pointsTable)
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as period_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // --- Reconstruct "pre-period" totals and re-rank ---
        // We need point values per reason to compute weighted period points.
        // Use the same defaults as PointService.
        $pointValues = [
            'discussion_started' => (int) $this->settings->get('huseyinfiliz-leaderboard.points_discussion_started', 1),
            'post_created'       => (int) $this->settings->get('huseyinfiliz-leaderboard.points_post_created',       1),
            'daily_login'        => (int) $this->settings->get('huseyinfiliz-leaderboard.points_daily_login',        1),
            'like_received'      => (int) $this->settings->get('huseyinfiliz-leaderboard.points_like_received',      1),
            'like_given'         => (int) $this->settings->get('huseyinfiliz-leaderboard.points_like_given',         0),
            'reaction_received'  => (int) $this->settings->get('huseyinfiliz-leaderboard.points_reaction_received',  1),
            'reaction_given'     => (int) $this->settings->get('huseyinfiliz-leaderboard.points_reaction_given',     0),
            'best_answer'        => (int) $this->settings->get('huseyinfiliz-leaderboard.points_best_answer',        2),
            'badge_earned'       => (int) $this->settings->get('huseyinfiliz-leaderboard.points_badge_earned',       3),
            'upvote_received'    => (int) $this->settings->get('huseyinfiliz-leaderboard.points_upvote_received',    1),
            'downvote_received'  => (int) $this->settings->get('huseyinfiliz-leaderboard.points_downvote_received', -1),
        ];

        // Build CASE SQL for period point values
        $case = 'CASE reason';
        $bindings = [];
        foreach ($pointValues as $reason => $pts) {
            $case .= ' WHEN ? THEN ?';
            $bindings[] = $reason;
            $bindings[] = $pts;
        }
        $case .= ' ELSE 0 END';

        $periodPointsRows = $this->db->table($pointsTable)
            ->where('created_at', '>=', $since)
            ->selectRaw("user_id, SUM({$case}) as period_points", $bindings)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Build pre-period totals map and re-rank
        $prePeriodTotals = [];
        foreach ($totals as $row) {
            $periodPts = isset($periodPointsRows[$row->user_id])
                ? (int) $periodPointsRows[$row->user_id]->period_points
                : 0;
            $prePeriodTotals[$row->user_id] = $row->points_total - $periodPts;
        }
        arsort($prePeriodTotals);
        $previousRanks = [];
        $r = 1;
        foreach ($prePeriodTotals as $uid => $pts) {
            $previousRanks[$uid] = $r++;
        }

        // --- Detect first-ever point within period (NEW badge) ---
        // A user is "new" if their earliest point entry is >= $since
        $allUserIds = $totals->pluck('user_id')->all();
        $firstPointDates = $this->db->table($pointsTable)
            ->whereIn('user_id', $allUserIds)
            ->selectRaw('user_id, MIN(created_at) as first_point_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // --- Load top $limit users ---
        $topRows = $totals->take($limit);
        $topUserIds = $topRows->pluck('user_id')->all();
        $users = User::whereIn('id', $topUserIds)->get()->keyBy('id');

        $entries = [];
        foreach ($topRows as $i => $row) {
            $uid          = $row->user_id;
            $user         = $users->get($uid);
            if (!$user) continue;

            $currentRank  = $i + 1;
            $previousRank = $previousRanks[$uid] ?? $currentRank;
            $rankChange   = $previousRank - $currentRank; // positive = moved up

            $periodPts    = isset($periodPointsRows[$uid])
                ? (int) $periodPointsRows[$uid]->period_points
                : 0;

            $firstAt      = isset($firstPointDates[$uid])
                ? Carbon::parse($firstPointDates[$uid]->first_point_at)
                : null;
            $isNew        = $firstAt !== null && $firstAt->gte($since);

            $entries[] = [
                'user'         => $user,
                'rank'         => $currentRank,
                'points'       => (int) $row->points_total,
                'periodPoints' => $periodPts,
                'rankChange'   => $rankChange,
                'isNew'        => $isNew,
            ];
        }

        // --- Biggest mover — highest period_points among top $limit ---
        $biggestMover = null;
        if (!empty($entries)) {
            $mover = collect($entries)->sortByDesc('periodPoints')->first();
            if ($mover && $mover['periodPoints'] > 0) {
                $biggestMover = $mover;
            }
        }

        return [
            'enabled'      => true,
            'entries'      => $entries,
            'biggestMover' => $biggestMover,
        ];
    }
}
