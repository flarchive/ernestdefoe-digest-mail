<?php

namespace Resofire\DigestMail\Query;

use Carbon\Carbon;
use Flarum\User\User;

/**
 * QueriesPickem: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesPickem
{

    // -------------------------------------------------------------------------
    // Section 7 — Pick'em
    // -------------------------------------------------------------------------

    /**
     * Build the pick'em section data.
     *
     * Returns an array with:
     *   enabled          bool
     *   upcomingEvents   array of upcoming scheduled events with cutoff in future
     *   recentResults    array of finished events within the digest period
     *   leaderboard      top N users by total_points from pickem_user_scores
     */
    public function getPickem(Carbon $since, int $limit = 5): array
    {
        $extInstalled = $this->extensions->isEnabled('huseyinfiliz-pickem');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_pickem');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'upcomingEvents' => [], 'recentResults' => [], 'leaderboard' => []];
        }

        $now = Carbon::now('UTC');

        // --- Upcoming events: scheduled, cutoff in the future ---
        $upcomingRows = $this->db->table('pickem_events')
            ->where('status', 'scheduled')
            ->where('cutoff_date', '>', $now)
            ->orderBy('match_date')
            ->limit($limit)
            ->get(['id', 'week_id', 'home_team_id', 'away_team_id', 'match_date', 'cutoff_date', 'allow_draw']);

        // --- Recent results: finished events within the digest period ---
        $recentRows = $this->db->table('pickem_events')
            ->where('status', 'finished')
            ->where('match_date', '>=', $since)
            ->orderByDesc('match_date')
            ->limit($limit)
            ->get(['id', 'home_team_id', 'away_team_id', 'match_date', 'home_score', 'away_score', 'result']);

        // Batch-load all teams referenced
        $teamIds = collect($upcomingRows)->pluck('home_team_id')
            ->merge(collect($upcomingRows)->pluck('away_team_id'))
            ->merge(collect($recentRows)->pluck('home_team_id'))
            ->merge(collect($recentRows)->pluck('away_team_id'))
            ->unique()->filter()->values()->all();

        $teams = $this->db->table('pickem_teams')
            ->whereIn('id', $teamIds)
            ->get(['id', 'name', 'slug', 'logo_path'])
            ->keyBy('id');

        // --- Pick'em leaderboard: top N by total_points ---
        $lbRows = $this->db->table('pickem_user_scores')
            ->whereNull('season_id')
            ->where('total_picks', '>', 0)
            ->orderByDesc('total_points')
            ->orderByDesc('correct_picks')
            ->limit($limit)
            ->get(['user_id', 'total_points', 'total_picks', 'correct_picks']);

        $lbUserIds = $lbRows->pluck('user_id')->all();
        $lbUsers   = User::whereIn('id', $lbUserIds)->get()->keyBy('id');

        $leaderboard = [];
        foreach ($lbRows as $i => $row) {
            $user = $lbUsers->get($row->user_id);
            if (!$user) continue;
            $accuracy = $row->total_picks > 0
                ? round(($row->correct_picks / $row->total_picks) * 100)
                : 0;
            $leaderboard[] = [
                'rank'          => $i + 1,
                'user'          => $user,
                'totalPoints'   => (int) $row->total_points,
                'totalPicks'    => (int) $row->total_picks,
                'correctPicks'  => (int) $row->correct_picks,
                'accuracy'      => $accuracy,
            ];
        }

        // Build upcoming events array
        $upcoming = [];
        foreach ($upcomingRows as $ev) {
            $homeTeam = $teams->get($ev->home_team_id);
            $awayTeam = $teams->get($ev->away_team_id);
            if (!$homeTeam || !$awayTeam) continue;
            $upcoming[] = [
                'id'        => $ev->id,
                'homeTeam'  => $homeTeam,
                'awayTeam'  => $awayTeam,
                'matchDate' => Carbon::parse($ev->match_date),
                'cutoff'    => Carbon::parse($ev->cutoff_date),
                'allowDraw' => (bool) $ev->allow_draw,
            ];
        }

        // Build recent results array
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
                'result'    => $ev->result, // 'home' | 'away' | 'draw'
            ];
        }

        return [
            'enabled'        => true,
            'upcomingEvents' => $upcoming,
            'recentResults'  => $results,
            'leaderboard'    => $leaderboard,
        ];
    }
}
