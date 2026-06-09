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
 * QueriesGiveaways: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesGiveaways
{

    // -------------------------------------------------------------------------
    // Section order
    // -------------------------------------------------------------------------

    /**
     * Returns the admin-configured section order as an array of keys.
     * Falls back to a sensible default if nothing is saved yet.
     */
    // -------------------------------------------------------------------------
    // Section — Giveaways (ernestdefoe/giveaways)
    // -------------------------------------------------------------------------

    /**
     * Build the Giveaways section for ernestdefoe/giveaways.
     *   enabled        bool
     *   endingSoon     array of [ title, prize, url, endsAt(Carbon), entrantCount, winnerCount ]
     *   recentWinners  array of [ title, prize, url, drawnAt(Carbon), winners(string[]) ]
     *   forumUrl       string — full URL to /giveaways
     */
    public function getGiveaways(Carbon $since, int $limit = 5): array
    {
        $empty = [
            'enabled'       => false,
            'endingSoon'    => [],
            'recentWinners' => [],
            'forumUrl'      => '',
        ];

        $extInstalled = $this->extensions->isEnabled('ernestdefoe-giveaways');
        $raw          = $this->settings->get('ernestdefoe-digest-mail.enable_giveaways');
        $adminEnabled = $raw === null || $raw === '' ? true : (bool) $raw;
        if (!$extInstalled || !$adminEnabled) {
            return $empty;
        }

        try {
            $now      = Carbon::now('UTC');
            $baseUrl  = rtrim($this->settings->get('url', ''), '/');
            $forumUrl = $baseUrl . '/giveaways';

            // Ending soon: active, already started, ending in the future, soonest first.
            $endingRows = $this->db->table('giveaways')
                ->where('status', 'active')
                ->where('ends_at', '>', $now)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->orderBy('ends_at')
                ->limit($limit)
                ->get(['id', 'title', 'slug', 'prize', 'ends_at', 'winner_count']);

            $endingIds   = collect($endingRows)->pluck('id')->all();
            $entryCounts = count($endingIds)
                ? $this->db->table('giveaway_entries')->whereIn('giveaway_id', $endingIds)
                    ->selectRaw('giveaway_id, COUNT(*) as c')->groupBy('giveaway_id')->pluck('c', 'giveaway_id')
                : collect();

            $endingSoon = [];
            foreach ($endingRows as $r) {
                $endingSoon[] = [
                    'title'        => $r->title,
                    'prize'        => $r->prize,
                    'url'          => $forumUrl . '/' . $r->slug,
                    'endsAt'       => Carbon::parse($r->ends_at),
                    'entrantCount' => (int) ($entryCounts[$r->id] ?? 0),
                    'winnerCount'  => (int) $r->winner_count,
                ];
            }

            // Recent winners: giveaways drawn within the digest period.
            $drawnRows = $this->db->table('giveaways')
                ->where('status', 'drawn')
                ->where('drawn_at', '>=', $since)
                ->orderByDesc('drawn_at')
                ->limit($limit)
                ->get(['id', 'title', 'slug', 'prize', 'drawn_at']);

            $drawnIds          = collect($drawnRows)->pluck('id')->all();
            $winnersByGiveaway = [];
            if (count($drawnIds)) {
                $wRows = $this->db->table('giveaway_winners')
                    ->join('users', 'users.id', '=', 'giveaway_winners.user_id')
                    ->whereIn('giveaway_winners.giveaway_id', $drawnIds)
                    ->orderBy('giveaway_winners.position')
                    ->get(['giveaway_winners.giveaway_id as gid', 'users.username']);
                foreach ($wRows as $w) {
                    $winnersByGiveaway[$w->gid][] = $w->username;
                }
            }

            $recentWinners = [];
            foreach ($drawnRows as $r) {
                $recentWinners[] = [
                    'title'   => $r->title,
                    'prize'   => $r->prize,
                    'url'     => $forumUrl . '/' . $r->slug,
                    'drawnAt' => Carbon::parse($r->drawn_at),
                    'winners' => $winnersByGiveaway[$r->id] ?? [],
                ];
            }

            if (empty($endingSoon) && empty($recentWinners)) {
                return $empty;
            }

            return [
                'enabled'       => true,
                'endingSoon'    => $endingSoon,
                'recentWinners' => $recentWinners,
                'forumUrl'      => $forumUrl,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}
