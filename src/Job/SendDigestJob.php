<?php

namespace Resofire\DigestMail\Job;

use Resofire\DigestMail\DigestMailer;
use Resofire\DigestMail\DigestQuery;
use Resofire\DigestMail\Token\UnsubscribeTokenGenerator;
use Carbon\Carbon;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Queueable job that sends a single digest email to one user.
 *
 * Lightweight by design — stores only the minimum needed to reconstruct
 * the email at send time:
 *
 *   $user      — serialized as a model ID by SerializesModels, re-hydrated
 *                with a single SELECT when the worker picks up the job
 *   $frequency — 'daily' | 'weekly' | 'monthly'
 *   $cacheKey  — Laravel cache key where buildSharedData() result lives
 *   $since     — period start timestamp so workers don't recalculate it
 *   $theme     — 'light' | 'dark' | 'auto'
 *
 * What is NOT stored in the job:
 *   - DigestContent (was 20-50KB of serialized Eloquent models per row)
 *   - The unsubscribe token (generated fresh at send time)
 *   - Shared section data (read from cache at send time)
 *
 * This keeps brfjobs payload to ~500 bytes per row instead of 20-50KB,
 * eliminates SerializesModels re-hydration queries for shared content,
 * and ensures the unread section and token are as fresh as possible at
 * the moment of sending.
 */
class SendDigestJob extends AbstractJob
{
    /** Maximum attempts before moving to failed_jobs. */
    public int $tries = 3;

    /** Exponential backoff in seconds between retries. */
    public array $backoff = [30, 60, 120];

    /**
     * The command stamps digest_last_sent_at when it QUEUES this job, so a
     * send that fails every attempt would otherwise still read as delivered.
     * failed() puts the previous value back, but only if the stamp is still
     * the one this dispatch wrote. Declared with defaults, not promoted, so a
     * job queued by an older version still unserializes cleanly.
     */
    private ?string $stampedAt = null;
    private ?string $previousSentAt = null;

    public function __construct(
        private User   $user,
        private string $frequency,
        private string $cacheKey,
        private Carbon $since,
        private string $theme,
        ?string $stampedAt = null,
        ?string $previousSentAt = null,
    ) {
        $this->stampedAt      = $stampedAt;
        $this->previousSentAt = $previousSentAt;
    }

    /**
     * Worker entry point — called by the queue worker.
     *
     * All dependencies injected by Laravel's container.
     *
     * Execution order:
     *   1. Read shared data from cache (zero DB queries if cache is warm)
     *   2. Query unread discussions for this specific user (1 query)
     *   3. Build DigestContent from shared + unread
     *   4. Generate/refresh unsubscribe token (1-2 queries)
     *   5. Render blade template and send email
     */
    public function handle(
        DigestMailer             $mailer,
        DigestQuery              $query,
        Cache                    $cache,
        UnsubscribeTokenGenerator $tokenGenerator,
    ): void {
        // Step 1 — Read shared data from cache.
        // If the cache has expired (TTL 2h) or been flushed, rebuild inline.
        $sharedData = $cache->get($this->cacheKey);
        if ($sharedData === null) {
            $sharedData = $query->buildSharedData($this->since);
            // Re-warm the cache so subsequent jobs in this batch don't rebuild.
            $cache->put($this->cacheKey, $sharedData, 7200);
        }

        // Step 2+3 — Build DigestContent: shared data + this user's unread.
        $content = $query->buildForUser(
            $this->user,
            $this->since,
            $this->frequency,
            $this->theme,
            $sharedData,
        );

        // If the content is empty (e.g. this user has read everything and
        // shared sections are also empty), skip silently.
        if ($content->isEmpty()) {
            return;
        }

        // Step 4 — Generate/refresh the unsubscribe token at send time
        // so it reflects the actual send moment, not the dispatch moment.
        $token = $tokenGenerator->getOrCreate($this->user);

        // Step 5 — Render and send.
        $mailer->sendToUser($this->user, $content, $token);
    }

    /**
     * Called once every attempt has failed (e.g. the mail transport is down).
     *
     * Un-stamps the user so the subscriber list and stats stop claiming a
     * delivery that never happened, and the user stays eligible for the next
     * run. It deliberately does NOT re-open the send window: against a broken
     * transport that would re-dispatch every subscriber each minute.
     */
    public function failed(?Throwable $e): void
    {
        if ($this->stampedAt !== null) {
            User::where('id', $this->user->id)
                ->where('digest_last_sent_at', $this->stampedAt)
                ->update(['digest_last_sent_at' => $this->previousSentAt]);
        }

        resolve(LoggerInterface::class)->error(sprintf(
            '[digest-mail] %s digest to user #%d was NOT delivered after %d attempts: %s',
            $this->frequency,
            $this->user->id,
            $this->tries,
            $e?->getMessage() ?? 'unknown error'
        ));
    }
}
