<?php

namespace Resofire\DigestMail\Console;

use Resofire\DigestMail\DigestQuery;
use Resofire\DigestMail\DigestMailer;
use Resofire\DigestMail\Job\SendDigestJob;
use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Two-phase digest enqueue command.
 *
 * This command ONLY populates the queue — it does not process or send.
 * Workers (queue:work) handle the actual sending separately.
 *
 * Usage:
 *   Phase 1 — run this command N minutes before the send window:
 *     php flarum digest:enqueue --frequency=daily --delay=600
 *
 *   Phase 2 — at the send time, workers drain the queue:
 *     php flarum queue:work --queue=digest --max-time=55 --tries=3
 *
 * By the time workers start, all jobs are pre-built and waiting in
 * jobs table with available_at set to the send time. Workers spend zero
 * time on job construction — they just pull and send.
 *
 * This approach also means the DB query load (building DigestContent per
 * user) happens before the send window, spreading it across time rather
 * than spiking at send time.
 *
 * Shared data is cached (same key as SendDigestCommand uses) so if both
 * commands run in the same window they share the cache.
 */
class EnqueueDigestCommand extends Command
{
    use DigestPeriods;

    protected $signature = 'digest:enqueue
        {--frequency=  : Frequency to enqueue (daily|weekly|monthly). Required.}
        {--delay=      : Seconds until jobs become available to workers (default: 0)}
        {--queue=      : Queue name (overrides admin setting)}
        {--dry-run     : Count eligible users without enqueuing}';

    protected $description = 'Pre-populate the digest queue without sending. Use for two-phase operation.';

    private const FREQUENCIES      = ['daily', 'weekly', 'monthly'];
    private const SHARED_CACHE_TTL = 7200;

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
        $frequency = $this->option('frequency');
        $isDryRun  = (bool) $this->option('dry-run');

        if (!$frequency || !in_array($frequency, self::FREQUENCIES, true)) {
            $this->error('--frequency is required. Must be one of: ' . implode(', ', self::FREQUENCIES));
            return self::FAILURE;
        }

        $queueName = $this->option('queue')
            ?? $this->settings->get('ernestdefoe-digest-mail.queue_name', 'digest');

        $delaySecs = $this->option('delay') !== null
            ? (int) $this->option('delay')
            : (int) $this->settings->get('ernestdefoe-digest-mail.queue_delay', 0);

        $chunkSize = max(50, min(10000,
            (int) $this->settings->get('ernestdefoe-digest-mail.queue_chunk_size', 200)
        ));

        $tries     = max(1, (int) $this->settings->get('ernestdefoe-digest-mail.queue_tries', 3));

        $since  = $this->periodStart($frequency);
        $cutoff = $this->lastSentCutoff($frequency);

        $this->info(
            "Enqueuing '{$frequency}' digests " .
            "(since: {$since->toDateTimeString()}, " .
            "queue: {$queueName}, delay: {$delaySecs}s, chunk: {$chunkSize})"
        );

        // Build and cache shared data now, before jobs are created.
        // Workers will re-use this cache rather than re-querying.
        $sharedData = null;
        $cacheKey   = "resofire_digest_shared_{$frequency}_{$since->timestamp}";
        if (!$isDryRun) {
            $sharedData = $this->cache->remember(
                $cacheKey,
                self::SHARED_CACHE_TTL,
                fn () => $this->query->buildSharedData($since)
            );
            $this->line("  [cache]    Shared data ready (key: {$cacheKey})");

            // Informational: warn if shared sections are empty. Users with
            // unread discussions may still be enqueued individually.
            $sharedEmpty = empty($sharedData['newDiscussions']?->count())
                && empty($sharedData['hotDiscussions']?->count())
                && empty($sharedData['newMembers']?->count())
                && empty($sharedData['awards']['awards'])
                && empty($sharedData['leaderboard']['entries'])
                && empty($sharedData['pickem']['upcomingEvents'])
                && empty($sharedData['pickem']['recentResults']);

            if ($sharedEmpty) {
                $this->line("  [preflight] Shared sections are empty. Users with unread discussions will still be enqueued.");
            }
        }

        $enqueued = 0;
        $skipped  = 0;

        $users = User::query()
            ->where('digest_frequency', $frequency)
            ->where('is_email_confirmed', true)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('digest_last_sent_at')
                  ->orWhere('digest_last_sent_at', '<', $cutoff);
            })
            ->limit($chunkSize)
            ->get();

        foreach ($users as $user) {
            // Dry-run: build content just to count eligible users.
            if ($isDryRun) {
                $theme   = $this->mailer->resolveTheme($user);
                $content = $this->query->buildForUser(
                    $user, $since, $frequency, $theme, $sharedData
                );
                if ($content->isEmpty()) { $skipped++; } else { $enqueued++; }
                continue;
            }

            // Real enqueue: push lightweight job — worker builds content at send time.
            $theme = $this->mailer->resolveTheme($user);

            $job = (new SendDigestJob($user, $frequency, $cacheKey, $since, $theme))
                ->onQueue($queueName);
            $job->tries = $tries;
            $job->backoff = [30, 60, 120];

            if ($delaySecs > 0) {
                $job = $job->delay($delaySecs);
            }

            $this->queue->push($job);

            // Stamp last sent so this user isn't double-dispatched.
            User::where('id', $user->id)->update([
                'digest_last_sent_at' => Carbon::now()->toDateTimeString(),
            ]);

            $enqueued++;
        }
        $verb = $isDryRun ? 'Would enqueue' : 'Enqueued';
        $this->info("{$verb}: {$enqueued}. Skipped (no content): {$skipped}.");

        return self::SUCCESS;
    }

}
