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
 * QueriesBadges: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesBadges
{

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

        // Recent earners: a bounded, most-recent slice (over-fetch a little so
        // filtering out invisible badges / deleted users below still fills $limit).
        // Never load the full period's badge_user table into memory.
        $recentRows = $this->db->table('fof_badge_user')
            ->where('earned_at', '>=', $since)
            ->orderByDesc('earned_at')
            ->limit(max($limit * 4, 50))
            ->get(['user_id', 'badge_id', 'earned_at']);

        // Most-earned this period: aggregate in SQL, so the result set is bounded
        // by the number of distinct badges (small) rather than by earner volume.
        $periodCounts = $this->db->table('fof_badge_user')
            ->where('earned_at', '>=', $since)
            ->groupBy('badge_id')
            ->select('badge_id')
            ->selectRaw('COUNT(DISTINCT user_id) AS earners')
            ->orderByDesc('earners')
            ->get();

        if ($recentRows->isEmpty() && $periodCounts->isEmpty()) {
            return ['enabled' => true, 'recentEarners' => [], 'mostEarned' => null, 'rarest' => null];
        }

        // Collect unique IDs for batch loading
        $badgeIds = $recentRows->pluck('badge_id')
            ->merge($periodCounts->pluck('badge_id'))->unique()->values()->all();
        $userIds  = $recentRows->pluck('user_id')->unique()->values()->all();

        $badges = $this->db->table('fof_badges')
            ->whereIn('id', $badgeIds)
            ->where('is_visible', true)
            ->get()
            ->keyBy('id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // --- Recent earners (up to $limit) ---
        $recentEarners = [];
        foreach ($recentRows as $row) {
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

        // --- Most earned this period (first visible badge by earner count) ---
        $mostEarned = null;
        foreach ($periodCounts as $pc) {
            if ($badges->has($pc->badge_id)) {
                $mostEarned = [
                    'badge' => $badges->get($pc->badge_id),
                    'count' => (int) $pc->earners,
                ];
                break;
            }
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
}
