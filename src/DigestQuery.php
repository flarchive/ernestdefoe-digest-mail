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
use Psr\Log\LoggerInterface;

/**
 * All database queries for digest content live here.
 */
class DigestQuery
{
    use \Resofire\DigestMail\Query\QueriesDiscussions;
    use \Resofire\DigestMail\Query\QueriesBadges;
    use \Resofire\DigestMail\Query\QueriesLeaderboard;
    use \Resofire\DigestMail\Query\QueriesPickem;
    use \Resofire\DigestMail\Query\QueriesPicks;
    use \Resofire\DigestMail\Query\QueriesGamepedia;
    use \Resofire\DigestMail\Query\QueriesFavorites;
    use \Resofire\DigestMail\Query\QueriesAwards;
    use \Resofire\DigestMail\Query\QueriesGiveaways;

    public function __construct(
        private SettingsRepositoryInterface $settings,
        // INTENTIONAL EXCEPTION: $db is reserved for THIRD-PARTY integration
        // tables only (leaderboard money, fof badges, picks/pickem, gamepedia,
        // giveaways, awards, reactions) — none of those extensions ship an
        // Eloquent model this extension could depend on. Every such call site
        // carries a "third-party table" comment. This extension's own digest_*
        // tables and all core tables go through Eloquent models exclusively.
        private ConnectionInterface         $db,
        private ExtensionManager            $extensions,
        private LoggerInterface             $log,
    ) {}

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

    public function getSectionOrder(): array
    {
        $default = ['discussions', 'members', 'stats', 'leaderboard', 'badges', 'pickem', 'picks', 'giveaways', 'gamepedia', 'resofireGamepedia', 'favorites', 'awards'];
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
        $limitGiveaways   = (int) $this->settings->get('ernestdefoe-digest-mail.limit_giveaways',    5) ?: 5;
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
            'giveaways'          => $this->getGiveaways($since, $limitGiveaways),
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
            giveaways:          $shared['giveaways'],
            gamepedia:          $shared['gamepedia'],
            resofireGamepedia:  $shared['resofireGamepedia'],
            favorites:          $shared['favorites'],
            awards:             $shared['awards'],
            theme:              $theme,
            sectionOrder:       $shared['sectionOrder'],
        );
    }
}
