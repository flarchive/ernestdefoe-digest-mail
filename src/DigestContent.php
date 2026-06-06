<?php

namespace Resofire\DigestMail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Immutable data bag representing all content sections for one digest email.
 */
class DigestContent
{
    public function __construct(
        /** A single admin-pinned discussion to feature at the top of the digest, or null. */
        public readonly ?object $featuredDiscussion = null,

        /** New discussions started during the digest period. */
        public readonly Collection $newDiscussions,

        /** Most active discussions during the period, ranked by hot score. */
        public readonly Collection $hotDiscussions,

        /** Discussions started during the period that this user has not read. */
        public readonly Collection $unreadDiscussions,

        /** Email-confirmed users who joined during the digest period. */
        public readonly Collection $newMembers,

        /** Start of the period this digest covers. */
        public readonly Carbon $periodStart,

        /** 'daily' | 'weekly' | 'monthly' */
        public readonly string $frequency,

        /** Period stats: posts, discussions, newMembers, activeUsers. */
        public readonly array $stats = [],

        /**
         * Badges data:
         *   enabled      bool
         *   recentEarners array of [ user, badge, earnedAt ] — badges earned this period
         *   mostEarned    array|null — [ badge, count ] — badge earned by most users this period
         *   rarest        array|null — [ badge, earnedCount ] — lowest all-time earned_count among period badges
         */
        public readonly array $badges = [],

        /**
         * Leaderboard data:
         *   enabled      bool
         *   entries      array of [ user, rank, points, periodPoints, rankChange, isNew ]
         *   biggestMover array|null — entry with highest periodPoints
         */
        public readonly array $leaderboard = [],

        /**
         * Pick'em data:
         *   enabled        bool
         *   upcomingEvents array of [ homeTeam, awayTeam, matchDate, cutoff, allowDraw ]
         *   recentResults  array of [ homeTeam, awayTeam, matchDate, homeScore, awayScore, result ]
         *   leaderboard    array of [ rank, user, totalPoints, totalPicks, correctPicks, accuracy ]
         */
        public readonly array $pickem = [],

        /**
         * Picks (resofire/picks) section data.
         *   enabled            bool
         *   confidenceMode     bool — whether confidence ratings are active
         *   leaderboardScope   string — 'week' | 'season' | 'alltime'
         *   currentWeek        array|null — [ id, name, weekNumber, isOpen ] — open week, if any
         *   upcomingEvents     array of [ id, homeTeam, awayTeam, matchDate, cutoff, neutralSite, weekName ]
         *   recentResults      array of [ homeTeam, awayTeam, matchDate, homeScore, awayScore, result ]
         *   leaderboard        array of [ rank, previousRank, movement, user, totalPoints, totalPicks, correctPicks, accuracy ]
         *   leaderboardLabel   string — human-readable scope label for display (e.g. "Week 9" or "2024 Season")
         *   picksForumUrl      string — full URL to the picks page
         */
        public readonly array $picks = [],

        /**
         * Gamepedia section data (huseyinfiliz/gamepedia).
         *   enabled        bool
         *   mostDiscussed  array of [ game, postCount, discussionCount ]
         *   newGames       array of game objects added this period
         */
        public readonly array $gamepedia = [],

        /**
         * Resofire Gamepedia section data (resofire/gamepedia 2.x).
         *   enabled        bool
         *   mostDiscussed  array of [ game (stdClass with cover_image_url,
         *                             developer, publisher, first_release_date),
         *                             postCount, discussionCount,
         *                             genres (array of stdClass) ]
         *   newGames       array of stdClass — each has genres array attached
         *   topGenres      array of [ genre (stdClass), gameCount, postCount ]
         */
        public readonly array $resofireGamepedia = [],

        /**
         * Favorite Discussions section data.
         *   Array of entries, each:
         *     discussion  — Discussion model with user eager-loaded
         *     score       — total engagement count
         *     likeCount   — total likes (likes-only mode)
         *     reactions   — array of [ emoji, count ] sorted by count desc
         *     mode        — 'likes' | 'reactions'
         */
        public readonly array $favorites = [],

        /**
         * Awards section data.
         *   enabled  bool
         *   awards   array of entries, each:
         *     award           — stdClass (id, name, slug, year, description, starts_at, ends_at, status, show_live_votes, image_url)
         *     effectiveStatus — 'upcoming' | 'active' | 'ended' | 'published'
         *     categories      — array of stdClass rows with nominee_count, vote_count
         *     totalVotes      — int
         *     topNominees     — array of [ categoryName, nomineeName, nomineeImage, voteCount ]
         */
        public readonly array $awards = [],

        /**
         * Email theme: 'light' | 'dark' | 'auto'.
         */
        public readonly string $theme = 'auto',

        /** Ordered list of section keys, e.g. ["discussions","members","stats","badges"] */
        public readonly array $sectionOrder = [],
    ) {}

    public function isEmpty(): bool
    {
        // Core discussion/member activity
        $coreEmpty = $this->newDiscussions->isEmpty()
            && $this->hotDiscussions->isEmpty()
            && $this->unreadDiscussions->isEmpty()
            && $this->newMembers->isEmpty();

        if (!$coreEmpty) return false;

        // Awards: any active or upcoming award is time-sensitive enough to send alone
        if (!empty($this->awards['awards'])) return false;

        // Leaderboard: someone earned points this period
        if (!empty($this->leaderboard['entries'])) return false;

        // Pick'em: upcoming matches or recent results worth surfacing
        if (!empty($this->pickem['upcomingEvents'])
            || !empty($this->pickem['recentResults'])) return false;

        // Picks (resofire/picks): upcoming matches or recent results worth surfacing
        if (!empty($this->picks['upcomingEvents'])
            || !empty($this->picks['recentResults'])) return false;

        return true;
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily'   => 'Daily',
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
            default   => ucfirst($this->frequency),
        };
    }
}
