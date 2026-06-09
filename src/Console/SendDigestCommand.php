<?php

namespace Resofire\DigestMail\Console;

use Resofire\DigestMail\DigestContent;
use Resofire\DigestMail\DigestQuery;
use Resofire\DigestMail\DigestMailer;
use Resofire\DigestMail\DigestSendLog;
use Resofire\DigestMail\Job\SendDigestJob;
use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Queue;

/**
 * Console command that drives the entire digest send cycle.
 *
 * Scaling design:
 *   1. Shared data cache  — sections identical for every user (awards,
 *      leaderboard, etc.) are built once per frequency run and stored in
 *      Laravel's cache. Each user job reads from cache, not the DB.
 *
 *   2. Chunked user loading — users loaded in configurable chunks (default
 *      200) so memory stays flat regardless of forum size.
 *
 *   3. Delayed dispatch — jobs can be pushed with an available_at delay so
 *      the queue fills before workers start consuming. Combine with the
 *      digest:enqueue command for true two-phase operation.
 *
 *   4. Priority queue — digest jobs go onto a named queue ('digest' by
 *      default) so they don't block other forum notification jobs.
 *
 *   5. Retry + backoff — jobs carry tries and exponential backoff config
 *      so transient mail failures retry automatically.
 */
class SendDigestCommand extends Command
{
    use DigestPeriods;

    protected $signature = 'digest:send
        {--frequency=  : Run only this frequency (daily|weekly|monthly)}
        {--dry-run     : Print eligible recipients without dispatching}
        {--user=       : Restrict to a single user ID (testing)}
        {--delay=      : Delay each job N seconds (overrides admin setting)}
        {--queue=      : Queue name to push jobs onto (overrides admin setting)}';

    protected $description = 'Send digest emails to subscribed forum members.';

    private const FREQUENCIES      = ['daily', 'weekly', 'monthly'];
    private const SHARED_CACHE_TTL    = 7200; // 2 hours
    private const WINDOW_COMPLETE_TTL = 86400; // 24 hours — cleared at midnight

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private DigestQuery                 $query,
        private DigestMailer                $mailer,
        private Queue                       $queue,
        private Cache                       $cache,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun        = (bool) $this->option('dry-run');
        $forcedFrequency = $this->option('frequency');
        $singleUserId    = $this->option('user');

        if ($forcedFrequency !== null && !in_array($forcedFrequency, self::FREQUENCIES, true)) {
            $this->error('Invalid --frequency. Must be one of: ' . implode(', ', self::FREQUENCIES));
            return self::FAILURE;
        }

        $dueFrequencies = $forcedFrequency !== null
            ? [$forcedFrequency]
            : $this->dueFrequencies();

        if (empty($dueFrequencies)) {
            $this->info('No digest frequencies are due at this time. Exiting.');
            return self::SUCCESS;
        }

        $this->info('Due frequencies: ' . implode(', ', $dueFrequencies));

        $totalDispatched = 0;
        $totalSkipped    = 0;

        foreach ($dueFrequencies as $frequency) {
            [$dispatched, $skipped] = $this->processFrequency(
                $frequency,
                $isDryRun,
                $singleUserId !== null ? (int) $singleUserId : null
            );
            $totalDispatched += $dispatched;
            $totalSkipped    += $skipped;
        }

        $this->newLine();
        $this->info("Done. Dispatched: {$totalDispatched}. Skipped: {$totalSkipped}.");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Time-gate
    // -------------------------------------------------------------------------

    private function dueFrequencies(): array
    {
        $timezone    = $this->settings->get('ernestdefoe-digest-mail.timezone', 'UTC');
        $now         = Carbon::now($timezone);
        $weeklyDay   = (int) $this->settings->get('ernestdefoe-digest-mail.weekly_day',  1);
        $monthlyDay  = (int) $this->settings->get('ernestdefoe-digest-mail.monthly_day', 1);

        // Window mode: send_window_start and send_window_end define a range of
        // hours during which the scheduler fires repeatedly, dispatching one
        // chunk per minute until all subscribers are processed.
        //
        // Single-hour mode (legacy): if send_window_end is not set or equals
        // send_window_start, behave exactly as before — fire once at that hour.
        $windowStart = (int) $this->settings->get('ernestdefoe-digest-mail.send_window_start',
            $this->settings->get('ernestdefoe-digest-mail.send_hour', 8));
        $windowEnd   = (int) $this->settings->get('ernestdefoe-digest-mail.send_window_end', $windowStart);

        $inWindow = ($windowEnd > $windowStart)
            ? ($now->hour >= $windowStart && $now->hour < $windowEnd)
            : ($now->hour === $windowStart);

        if (!$inWindow) return [];

        $due = [];

        // Daily — due if within window and not yet fully dispatched today.
        if (!$this->isWindowComplete('daily', $now)) {
            $due[] = 'daily';
        }

        // Weekly — only on the configured day.
        if ($now->dayOfWeek === $weeklyDay && !$this->isWindowComplete('weekly', $now)) {
            $due[] = 'weekly';
        }

        // Monthly — only on the configured day-of-month.
        if ($now->day === $monthlyDay && !$this->isWindowComplete('monthly', $now)) {
            $due[] = 'monthly';
        }

        return $due;
    }

    /**
     * Returns true if this frequency has already been fully dispatched
     * during the current window for today's date in the forum timezone.
     */
    private function isWindowComplete(string $frequency, \Carbon\Carbon $now): bool
    {
        $key = $this->windowCompleteKey($frequency, $now);
        return $this->cache->has($key);
    }

    /**
     * Mark a frequency as fully dispatched for today's window.
     * TTL is 24 hours so it auto-clears at the next day's window.
     */
    private function markWindowComplete(string $frequency, \Carbon\Carbon $now): void
    {
        $key = $this->windowCompleteKey($frequency, $now);
        $this->cache->put($key, true, self::WINDOW_COMPLETE_TTL);
    }

    /**
     * Cache key for the window-complete flag.
     * Scoped to frequency + calendar date in the forum timezone.
     */
    private function windowCompleteKey(string $frequency, \Carbon\Carbon $now): string
    {
        return 'resofire_digest_window_complete_' . $frequency . '_' . $now->toDateString();
    }

    // -------------------------------------------------------------------------
    // Per-frequency processing
    // -------------------------------------------------------------------------

    private function processFrequency(string $frequency, bool $isDryRun, ?int $singleUserId): array
    {
        $since     = $this->periodStart($frequency);
        $cutoff    = $this->lastSentCutoff($frequency);
        $dispatched = 0;
        $skipped    = 0;

        // Resolve queue settings — CLI flags take priority over admin settings.
        $queueName = $this->option('queue')
            ?? $this->settings->get('ernestdefoe-digest-mail.queue_name', 'digest');

        $delaySecs = $this->option('delay') !== null
            ? (int) $this->option('delay')
            : (int) $this->settings->get('ernestdefoe-digest-mail.queue_delay', 0);

        $chunkSize = max(50, min(10000,
            (int) $this->settings->get('ernestdefoe-digest-mail.queue_chunk_size', 200)
        ));

        $this->info(
            "Processing '{$frequency}' " .
            "(since: {$since->toDateTimeString()}, " .
            "queue: {$queueName}, delay: {$delaySecs}s, chunk: {$chunkSize})"
        );

        // Build shared data once and cache it for this frequency run.
        // All per-user jobs will read from this cache instead of re-querying.
        $sharedData = null;
        $cacheKey   = "resofire_digest_shared_{$frequency}_{$since->timestamp}";
        if (!$isDryRun) {
            $sharedData = $this->cache->remember(
                $cacheKey,
                self::SHARED_CACHE_TTL,
                fn () => $this->query->buildSharedData($since)
            );
            $this->line("  [cache]    Shared data ready (key: {$cacheKey})");

            // Informational pre-flight: warn if shared sections are empty.
            // We still process users because their unread section may justify
            // sending individually — that cannot be checked without per-user queries.
            if ($this->sharedDataIsEmpty($sharedData)) {
                $this->line("  [preflight] Shared sections are empty. Users with unread discussions will still be processed.");
            }
        }

        // Window mode: fetch only one chunk of users per scheduler run.
        // Each minute the scheduler fires, dispatches $chunkSize users, and exits.
        // The window-complete check below detects when all users are done.
        // This spreads DB load across the window rather than processing
        // all subscribers in one large run.
        $userQuery = User::query()
            ->where('digest_frequency', $frequency)
            ->where('is_email_confirmed', true)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('digest_last_sent_at')
                  ->orWhere('digest_last_sent_at', '<', $cutoff);
            });

        if ($singleUserId !== null) {
            $userQuery->where('id', $singleUserId);
        }

        $users = $userQuery->limit($chunkSize)->get();

        foreach ($users as $user) {
            // Dry-run: build content to summarise what would be sent.
            if ($isDryRun) {
                $theme   = $this->mailer->resolveTheme($user);
                $content = $this->query->buildForUser(
                    $user, $since, $frequency, $theme, $sharedData
                );
                if ($content->isEmpty()) {
                    $this->line("  [skip]     {$user->username} (#{$user->id}) — no content");
                    $skipped++;
                } else {
                    $this->line("  [dry-run]  {$user->username} (#{$user->id}) — " . $this->contentSummary($content));
                    $dispatched++;
                }
                continue;
            }

            // Real send: push a lightweight job — no DigestContent, no token.
            // The worker builds content and generates the token at send time.
            $theme = $this->mailer->resolveTheme($user);

            $job = (new SendDigestJob($user, $frequency, $cacheKey, $since, $theme))
                ->onQueue($queueName);
            $job->tries = $this->jobTries();
            $job->backoff = [30, 60, 120];

            if ($delaySecs > 0) {
                $job = $job->delay($delaySecs);
            }

            $this->queue->push($job);

            // Stamp last sent so the user isn't double-dispatched if
            // the command re-runs within the same window.
            User::where('id', $user->id)->update([
                'digest_last_sent_at' => Carbon::now()->toDateTimeString(),
            ]);

            $this->line("  [queued]   {$user->username} (#{$user->id})");
            $dispatched++;
        }

                // Log the batch — aggregate into one row per frequency per day.
        // With window mode dispatching one chunk per minute, we update today's
        // row rather than inserting a new one for each chunk.
        if (!$isDryRun && $dispatched > 0) {
            $nowUtc     = Carbon::now('UTC');
            $startOfDay = $nowUtc->copy()->startOfDay();

            $existing = DigestSendLog::query()
                ->where('frequency', $frequency)
                ->where('sent_at', '>=', $startOfDay)
                ->where('sent_at', '<',  $startOfDay->copy()->addDay())
                ->first();

            if ($existing) {
                $existing->sent_count    += $dispatched;
                $existing->skipped_count += $skipped;
                $existing->sent_at        = $nowUtc;
                $existing->save();
            } else {
                DigestSendLog::create([
                    'frequency'     => $frequency,
                    'sent_count'    => $dispatched,
                    'skipped_count' => $skipped,
                    'sent_at'       => $nowUtc,
                ]);
            }

            // Purge old log entries beyond the retention limits.
            // Daily: 30 rows, Weekly: 52 rows, Monthly: 24 rows.
            $retention = match ($frequency) {
                'daily'   => 30,
                'weekly'  => 52,
                'monthly' => 24,
                default   => 30,
            };

            $keepIds = DigestSendLog::query()
                ->where('frequency', $frequency)
                ->orderByDesc('sent_at')
                ->limit($retention)
                ->pluck('id');

            if ($keepIds->isNotEmpty()) {
                DigestSendLog::query()
                    ->where('frequency', $frequency)
                    ->whereNotIn('id', $keepIds)
                    ->delete();
            }
        }

        // Window-complete check: if no eligible users remain for this frequency,
        // mark it done so dueFrequencies() skips it for the rest of the window.
        if (!$isDryRun) {
            $timezone = $this->settings->get('ernestdefoe-digest-mail.timezone', 'UTC');
            $now      = Carbon::now($timezone);
            $cutoff   = $this->lastSentCutoff($frequency);

            $remaining = User::query()
                ->where('digest_frequency', $frequency)
                ->where('is_email_confirmed', true)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('digest_last_sent_at')
                      ->orWhere('digest_last_sent_at', '<', $cutoff);
                })
                ->limit(1)
                ->count();

            if ($remaining === 0) {
                $this->markWindowComplete($frequency, $now);
                $this->line("  [window]   All '{$frequency}' subscribers dispatched. Window marked complete.");
            } else {
                $this->line("  [window]   {$remaining} '{$frequency}' subscriber(s) remaining — will continue next minute.");
            }
        }

        return [$dispatched, $skipped];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function jobTries(): int
    {
        return max(1, (int) $this->settings->get('ernestdefoe-digest-mail.queue_tries', 3));
    }

    /**
     * Check whether the shared data would cause every user to be skipped.
     * Mirrors the logic in DigestContent::isEmpty() for shared-only sections.
     * If this returns true, there is no point chunking through the user table.
     */
    private function sharedDataIsEmpty(array $shared): bool
    {
        // Core shared sections
        if (!empty($shared['newDiscussions']) && $shared['newDiscussions']->isNotEmpty()) return false;
        if (!empty($shared['hotDiscussions']) && $shared['hotDiscussions']->isNotEmpty()) return false;
        if (!empty($shared['newMembers'])     && $shared['newMembers']->isNotEmpty())     return false;

        // Force-send triggers
        if (!empty($shared['awards']['awards']))            return false;
        if (!empty($shared['leaderboard']['entries']))      return false;
        if (!empty($shared['pickem']['upcomingEvents']))    return false;
        if (!empty($shared['pickem']['recentResults']))     return false;

        // Note: unreadDiscussions is per-user, so we cannot check it here.
        // A user with unread content will still get sent even if we reach here,
        // but we cannot know that without checking each user individually.
        // The pre-flight is a best-effort early exit, not a guarantee.
        return true;
    }

    private function contentSummary(DigestContent $content): string
    {
        $parts = [];
        if ($content->newDiscussions->isNotEmpty())   $parts[] = $content->newDiscussions->count()   . ' new';
        if ($content->hotDiscussions->isNotEmpty())    $parts[] = $content->hotDiscussions->count()   . ' hot';
        if ($content->unreadDiscussions->isNotEmpty()) $parts[] = $content->unreadDiscussions->count(). ' unread';
        if ($content->newMembers->isNotEmpty())        $parts[] = $content->newMembers->count()       . ' members';
        if (!empty($content->awards['awards']))        $parts[] = count($content->awards['awards'])   . ' award(s)';
        return implode(', ', $parts) ?: 'extension sections only';
    }
}
