<?php

namespace Resofire\DigestMail\Query;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * QueriesDiscussions: extracted from DigestQuery to keep each integration's digest queries
 * in its own cohesive unit. Composed into DigestQuery via `use`.
 */
trait QueriesDiscussions
{

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

        // The hot score blends reply volume with recency. The recency term needs
        // "hours since last post", which has no portable SQL spelling
        // (TIMESTAMPDIFF is MySQL-only; PG/SQLite differ), so we pull a bounded
        // candidate set for the digest window and rank in PHP. The window itself
        // (last_posted_at >= since) keeps the candidate set small.
        $candidates = Discussion::whereVisibleTo($actor)
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
            ->where('discussions.last_posted_at', '>=', $since)
            ->whereNull('discussions.hidden_at')
            ->with(['user', 'lastPostedUser'])
            ->orderByDesc('comment_count')
            ->orderByDesc('last_posted_at')
            ->limit(max($limit * 5, 100))
            ->get();

        $now = Carbon::now();

        return $candidates
            ->map(function (Discussion $d) use ($replyWeight, $recencyWeight, $now) {
                $hours = $d->last_posted_at ? max(0, $now->diffInHours($d->last_posted_at)) : PHP_INT_MAX;
                $d->hot_score = ((int) $d->comment_count * $replyWeight)
                    + (1.0 / (1.0 + $hours * $recencyWeight));
                return $d;
            })
            ->sortByDesc('hot_score')
            ->take($limit)
            ->values();
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
}
