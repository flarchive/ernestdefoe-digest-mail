<?php

namespace Resofire\DigestMail;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\ConnectionInterface;

/**
 * All database queries for digest content live here.
 */
class DigestQuery
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ConnectionInterface         $db,
        private ExtensionManager            $extensions,
    ) {}

    // -------------------------------------------------------------------------
    // Section 0 — Featured discussion
    // -------------------------------------------------------------------------

    /**
     * Load the admin-pinned featured discussion, if one is configured and
     * the discussion is still visible (not hidden, not deleted).
     *
     * Returns the Discussion model or null.
     */
    public function getFeaturedDiscussion(User $actor): ?Discussion
    {
        $raw = $this->settings->get('ernestdefoe-digest-mail.featured_discussion_id');
        if (!$raw) return null;

        $id = (int) $raw;
        if ($id <= 0) return null;

        return Discussion::whereVisibleTo($actor)
            ->where('discussions.id', $id)
            ->whereNull('discussions.hidden_at')
            ->with(['user', 'lastPostedUser'])
            ->first();
    }

    // -------------------------------------------------------------------------
    // Section 1 — New discussions
    // -------------------------------------------------------------------------

    public function getNewDiscussions(User $actor, Carbon $since, int $limit): Collection
    {
        return Discussion::whereVisibleTo($actor)
            ->select([
                'discussions.id',
                'discussions.title',
                'discussions.slug',
                'discussions.comment_count',
                'discussions.created_at',
                'discussions.user_id',
                'discussions.last_posted_user_id',
            ])
            ->where('discussions.created_at', '>=', $since)
            ->whereNull('discussions.hidden_at')
            ->with(['user', 'lastPostedUser'])
            ->orderByDesc('discussions.created_at')
            ->limit($limit)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Section 2 — Hot discussions
    // -------------------------------------------------------------------------

    public function getHotDiscussions(User $actor, Carbon $since, int $limit): Collection
    {
        $replyWeight   = (float) $this->settings->get('ernestdefoe-digest-mail.hot_reply_weight',   1.0);
        $recencyWeight = (float) $this->settings->get('ernestdefoe-digest-mail.hot_recency_weight', 0.5);

        return Discussion::whereVisibleTo($actor)
            ->select([
                'discussions.id',
                'discussions.title',
                'discussions.slug',
                'discussions.comment_count',
                'discussions.created_at',
                'discussions.last_posted_at',
                'discussions.user_id',
                'discussions.last_posted_user_id',
            ])
            ->selectRaw(
                '(comment_count * ?) + (1.0 / (1.0 + TIMESTAMPDIFF(HOUR, last_posted_at, NOW()) * ?)) AS hot_score',
                [$replyWeight, $recencyWeight]
            )
            ->where('discussions.last_posted_at', '>=', $since)
            ->whereNull('discussions.hidden_at')
            ->with(['user', 'lastPostedUser'])
            ->orderByDesc('hot_score')
            ->limit($limit)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Section 3 — Unread discussions
    // -------------------------------------------------------------------------

    public function getUnreadDiscussions(User $actor, Carbon $since, int $limit): Collection
    {
        return Discussion::whereVisibleTo($actor)
            ->select([
                'discussions.id',
                'discussions.title',
                'discussions.slug',
                'discussions.comment_count',
                'discussions.created_at',
                'discussions.user_id',
            ])
            ->leftJoin('discussion_user as du', function ($join) use ($actor) {
                $join->on('du.discussion_id', '=', 'discussions.id')
                     ->where('du.user_id', '=', $actor->id);
            })
            ->whereNull('du.user_id')
            ->where('discussions.created_at', '>=', $since)
            ->whereNull('discussions.hidden_at')
            ->with(['user'])
            ->orderByDesc('discussions.created_at')
            ->limit($limit)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Section 4 — New members
    // -------------------------------------------------------------------------

    public function getNewMembers(Carbon $since, int $limit): Collection
    {
        return User::query()
            ->select(['id', 'username', 'avatar_url', 'joined_at'])
            ->where('is_email_confirmed', true)
            ->where('joined_at', '>=', $since)
            ->orderByDesc('joined_at')
            ->limit($limit)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Section 5 — Badges
    // -------------------------------------------------------------------------

    /**
     * Build the badges section data.
     *
     * Returns an array with:
     *   enabled       bool
     *   recentEarners array of [ user, badge, earnedAt ] — up to $limit rows
     *                 from fof_badge_user earned during the period, joined to
     *                 fof_badges and users. Ordered by earned_at desc.
     *   mostEarned    [ badge, count ] — badge awarded to the most distinct
     *                 users during the period, or null if none.
     *   rarest        [ badge, earnedCount ] — among badges awarded during
     *                 the period, the one with the lowest all-time earned_count
     *                 on fof_badges, or null if none.
     */
    public function getBadges(Carbon $since, int $limit = 10): array
    {
        $extInstalled = $this->extensions->isEnabled('fof-badges');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_badges');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;

        if (!$extInstalled || !$adminEnabled) {
            return ['enabled' => false, 'recentEarners' => [], 'mostEarned' => null, 'rarest' => null];
        }

        // fof_badge_user rows earned this period
        $rows = $this->db->table('fof_badge_user')
            ->where('earned_at', '>=', $since)
            ->orderByDesc('earned_at')
            ->get(['user_id', 'badge_id', 'earned_at']);

        if ($rows->isEmpty()) {
            return ['enabled' => true, 'recentEarners' => [], 'mostEarned' => null, 'rarest' => null];
        }

        // Collect unique IDs for batch loading
        $badgeIds = $rows->pluck('badge_id')->unique()->values()->all();
        $userIds  = $rows->pluck('user_id')->unique()->values()->all();

        $badges = $this->db->table('fof_badges')
            ->whereIn('id', $badgeIds)
            ->where('is_visible', true)
            ->get()
            ->keyBy('id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // --- Recent earners (up to $limit) ---
        $recentEarners = [];
        foreach ($rows as $row) {
            if (count($recentEarners) >= $limit) break;
            $badge = $badges->get($row->badge_id);
            $user  = $users->get($row->user_id);
            if (!$badge || !$user) continue;
            $recentEarners[] = [
                'user'     => $user,
                'badge'    => $badge,
                'earnedAt' => Carbon::parse($row->earned_at),
            ];
        }

        // --- Most earned this period ---
        $mostEarned = null;
        $periodCounts = $rows->groupBy('badge_id')->map(fn($g) => $g->pluck('user_id')->unique()->count());
        $topBadgeId   = $periodCounts->sortDesc()->keys()->first();
        if ($topBadgeId && $badges->has($topBadgeId)) {
            $mostEarned = [
                'badge' => $badges->get($topBadgeId),
                'count' => $periodCounts[$topBadgeId],
            ];
        }

        // --- Rarest this period (lowest all-time earned_count) ---
        $rarest = null;
        $rarestBadge = $badges->sortBy('earned_count')->first();
        if ($rarestBadge) {
            $rarest = [
                'badge'       => $rarestBadge,
                'earnedCount' => (int) $rarestBadge->earned_count,
            ];
        }

        return [
            'enabled'      => true,
            'recentEarners'=> $recentEarners,
            'mostEarned'   => $mostEarned,
            'rarest'       => $rarest,
        ];
    }

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

    // -------------------------------------------------------------------------
    // Period stats — counts for the stats bar
    // -------------------------------------------------------------------------

    public function getStats(Carbon $since): array
    {
        $posts = \Flarum\Post\Post::where('created_at', '>=', $since)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_approved', true)
            ->count();

        $discussions = Discussion::where('created_at', '>=', $since)
            ->whereNull('hidden_at')
            ->where('is_approved', true)
            ->count();

        $newMembers = User::where('joined_at', '>=', $since)
            ->where('is_email_confirmed', true)
            ->count();

        $activeUsers = \Flarum\Post\Post::where('created_at', '>=', $since)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->where('is_approved', true)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        return compact('posts', 'discussions', 'newMembers', 'activeUsers');
    }

    // -------------------------------------------------------------------------
    // Section — Favorite Discussions (likes + optional reactions)
    // -------------------------------------------------------------------------

    /**
     * Emoji map for fof/reactions and resofire/reactions identifiers.
     * Identifiers present in fof/reactions only: laughing.
     * Identifiers present in resofire/reactions only: joy, astonished, sob, fire, eyes.
     * Identifiers shared by both: thumbsup, heart, tada.
     * Negative identifiers (thumbsdown, confused) are excluded from scoring and display.
     */
    private const REACTION_EMOJI = [
        'thumbsup'   => '👍️',
        'heart'      => '❤️',
        'tada'       => '🎉',
        'laughing'   => '😆',
        'joy'        => '😂',
        'astonished' => '😲',
        'sob'        => '😭',
        'fire'       => '🔥',
        'eyes'       => '👀',
    ];

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

        $awards = [];

        foreach ($awardRows as $award) {
            // Resolve effective status
            if ($award->status === 'published') {
                $effectiveStatus = 'published';
            } elseif ($award->status === 'ended') {
                $effectiveStatus = 'ended';
            } elseif ($award->status === 'active' && $award->ends_at && $award->ends_at < $now) {
                $effectiveStatus = 'ended';
            } elseif ($award->status === 'active' && $award->starts_at && $award->starts_at > $now) {
                $effectiveStatus = 'upcoming';
            } else {
                $effectiveStatus = 'active';
            }

            // Load categories with per-category vote and nominee counts
            $prefix = $this->db->getTablePrefix();
            $categoryRows = $this->db->select("
                SELECT
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
                WHERE ac.award_id = ?
                GROUP BY ac.id, ac.name, ac.slug, ac.description, ac.sort_order
                ORDER BY ac.sort_order ASC
            ", [(int) $award->id]);

            $totalVotes = (int) array_sum(array_map(fn($row) => $row->vote_count, $categoryRows));

            // Top nominee per category (only when show_live_votes is on and voting is active or results published)
            $topNominees = [];
            if ((bool) $award->show_live_votes && in_array($effectiveStatus, ['active', 'published'])) {
                $topRows = $this->db->select("
                    SELECT
                        ac.name  AS category_name,
                        an.name  AS nominee_name,
                        an.image_url AS nominee_image,
                        COUNT(av.id) + COALESCE(an.vote_adjustment, 0) AS vote_count
                    FROM {$prefix}award_categories AS ac
                    INNER JOIN {$prefix}award_nominees AS an ON an.category_id = ac.id
                    LEFT JOIN  {$prefix}award_votes    AS av ON av.nominee_id  = an.id
                    WHERE ac.award_id = ?
                    GROUP BY ac.id, ac.name, an.id, an.name, an.image_url, an.vote_adjustment
                    ORDER BY ac.sort_order ASC, vote_count DESC
                ", [(int) $award->id]);

                // Keep only the top nominee per category
                $seen = [];
                foreach ($topRows as $row) {
                    if (!isset($seen[$row->category_name])) {
                        $seen[$row->category_name] = true;
                        $topNominees[] = [
                            'categoryName'  => $row->category_name,
                            'nomineeName'   => $row->nominee_name,
                            'nomineeImage'  => $row->nominee_image,
                            'voteCount'     => (int) $row->vote_count,
                        ];
                    }
                }
            }

            $awards[] = [
                'award'          => $award,
                'effectiveStatus'=> $effectiveStatus,
                'categories'     => $categoryRows,
                'totalVotes'     => (int) $totalVotes,
                'topNominees'    => $topNominees,
            ];
        }

        return ['enabled' => true, 'awards' => $awards];
    }

    // -------------------------------------------------------------------------
    // Section order
    // -------------------------------------------------------------------------

    /**
     * Returns the admin-configured section order as an array of keys.
     * Falls back to a sensible default if nothing is saved yet.
     */
    public function getSectionOrder(): array
    {
        $default = ['discussions', 'members', 'stats', 'leaderboard', 'badges', 'pickem', 'picks', 'gamepedia', 'resofireGamepedia', 'favorites', 'awards'];
        $raw = $this->settings->get('ernestdefoe-digest-mail.section_order', '');
        if (!$raw) return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) return $default;
        // Ensure all default keys are present (in case new sections were added)
        foreach ($default as $key) {
            if (!in_array($key, $decoded, true)) $decoded[] = $key;
        }
        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Shared data — sections identical for every user in a given frequency run
    // -------------------------------------------------------------------------

    /**
     * Build a lightweight member-level User actor for shared queries.
     *
     * whereVisibleTo() needs a User to check permissions. For sections that
     * are broadcast content (same for all subscribers), we use a synthetic
     * member-level actor rather than a real user:
     *
     *   - id=0 would be Guest (too restrictive for login-required forums)
     *   - Using a real user's id would cause unnecessary DB lookups
     *   - A synthetic User with Group::MEMBER_ID loaded satisfies the
     *     viewForum permission check and returns the same results that any
     *     confirmed member subscriber would see
     *
     * This is the correct semantic model: the digest shows what a standard
     * member sees, which is identical for all standard-member subscribers.
     */
    private function memberActor(): User
    {
        $actor = new User();
        $actor->id = -1; // Non-zero so isGuest() returns false

        // Load the member group directly — no DB query needed
        $memberGroup = Group::find(Group::MEMBER_ID);

        if ($memberGroup) {
            $actor->setRelation('groups', new Collection([$memberGroup]));
        } else {
            // Fallback: empty collection so visibility scope doesn't throw
            $actor->setRelation('groups', new Collection([]));
        }

        return $actor;
    }

    /**
     * Build all sections that are identical across every user for a given
     * frequency+period. This is called once per frequency run and cached,
     * so 12,000 users don't each re-query the same data.
     *
     * ALL sections except unreadDiscussions are shared:
     *   - featuredDiscussion, newDiscussions, hotDiscussions, newMembers,
     *     favorites — use a member-level actor (same result for all members)
     *   - stats, badges, leaderboard, pickem, gamepedia, awards, sectionOrder
     *     — no actor needed
     *
     * Per-user section (NOT included here):
     *   - unreadDiscussions — joins discussion_user for this specific user
     */
    public function buildSharedData(Carbon $since): array
    {
        $actor = $this->memberActor();

        $limitNew         = (int) $this->settings->get('ernestdefoe-digest-mail.limit_new',         5);
        $limitHot         = (int) $this->settings->get('ernestdefoe-digest-mail.limit_hot',         5);
        $limitMembers     = (int) $this->settings->get('ernestdefoe-digest-mail.limit_members',     5);
        $limitBadges      = (int) $this->settings->get('ernestdefoe-digest-mail.limit_badges',      5) ?: 5;
        $limitLeaderboard = (int) $this->settings->get('ernestdefoe-digest-mail.limit_leaderboard', 10) ?: 10;
        $limitPickem      = (int) $this->settings->get('ernestdefoe-digest-mail.limit_pickem',      5) ?: 5;
        $limitPicks       = (int) $this->settings->get('ernestdefoe-digest-mail.limit_picks',        5) ?: 5;
        $limitGamepedia          = (int) $this->settings->get('ernestdefoe-digest-mail.limit_gamepedia',          5) ?: 5;
        $limitResofireGamepedia  = (int) $this->settings->get('ernestdefoe-digest-mail.limit_resofire_gamepedia', 5) ?: 5;
        $limitFavorites   = (int) $this->settings->get('ernestdefoe-digest-mail.limit_favorites',   6);

        return [
            'featuredDiscussion' => $this->getFeaturedDiscussion($actor),
            'newDiscussions'     => $this->getNewDiscussions($actor, $since, $limitNew),
            'hotDiscussions'     => $this->getHotDiscussions($actor, $since, $limitHot),
            'newMembers'         => $this->getNewMembers($since, $limitMembers),
            'favorites'          => $this->getFavoriteDiscussions($actor, $since, $limitFavorites),
            'stats'              => $this->getStats($since),
            'badges'             => $this->getBadges($since, $limitBadges),
            'leaderboard'        => $this->getLeaderboard($since, $limitLeaderboard),
            'pickem'             => $this->getPickem($since, $limitPickem),
            'picks'              => $this->getPicks($since, $limitPicks),
            'gamepedia'          => $this->getGamepedia($since, $limitGamepedia),
            'resofireGamepedia'  => $this->getResofireGamepedia($since, $limitResofireGamepedia),
            'awards'             => $this->getAwards(),
            'sectionOrder'       => $this->getSectionOrder(),
        ];
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build a DigestContent for a single user.
     *
     * Only unreadDiscussions is per-user — it joins discussion_user on the
     * specific user's read state. Everything else comes from $sharedData.
     *
     * Pass pre-built $sharedData (from buildSharedData()) to avoid repeating
     * queries for every user. If omitted, shared data is built inline
     * (backwards-compatible with direct callers like SendTestDigestController).
     */
    public function buildForUser(
        User    $actor,
        Carbon  $since,
        string  $frequency,
        string  $theme = 'auto',
        ?array  $sharedData = null,
    ): DigestContent {
        $limitUnread = (int) $this->settings->get('ernestdefoe-digest-mail.limit_unread', 5);

        // Use pre-built shared data if provided, otherwise build inline.
        $shared = $sharedData ?? $this->buildSharedData($since);

        return new DigestContent(
            featuredDiscussion: $shared['featuredDiscussion'],
            newDiscussions:     $shared['newDiscussions'],
            hotDiscussions:     $shared['hotDiscussions'],
            unreadDiscussions:  $this->getUnreadDiscussions($actor, $since, $limitUnread),
            newMembers:         $shared['newMembers'],
            periodStart:        $since,
            frequency:          $frequency,
            stats:              $shared['stats'],
            badges:             $shared['badges'],
            leaderboard:        $shared['leaderboard'],
            pickem:             $shared['pickem'],
            picks:              $shared['picks'],
            gamepedia:          $shared['gamepedia'],
            resofireGamepedia:  $shared['resofireGamepedia'],
            favorites:          $shared['favorites'],
            awards:             $shared['awards'],
            theme:              $theme,
            sectionOrder:       $shared['sectionOrder'],
        );
    }
}
