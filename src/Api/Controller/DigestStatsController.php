<?php

namespace Resofire\DigestMail\Api\Controller;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Resofire\DigestMail\DigestSendLog;

class DigestStatsController implements RequestHandlerInterface
{
    public function __construct(
        private Paths                        $paths,
        private SettingsRepositoryInterface  $settings,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        // Subscription counts
        $totalMembers = User::query()
            ->where('is_email_confirmed', true)
            ->count();

        $freqRows = User::query()
            ->selectRaw('digest_frequency, COUNT(*) as cnt')
            ->where('is_email_confirmed', true)
            ->whereIn('digest_frequency', ['daily', 'weekly', 'monthly'])
            ->groupBy('digest_frequency')
            ->get();

        $byFrequency = ['daily' => 0, 'weekly' => 0, 'monthly' => 0];
        foreach ($freqRows as $row) {
            $byFrequency[$row->digest_frequency] = (int) $row->cnt;
        }

        $totalSubscribed  = array_sum($byFrequency);
        $subscriptionRate = $totalMembers > 0
            ? round($totalSubscribed / $totalMembers * 100, 1)
            : 0;

        // Last sent per frequency
        $lastSentRows = User::query()
            ->selectRaw('digest_frequency, MAX(digest_last_sent_at) as last_sent')
            ->whereIn('digest_frequency', ['daily', 'weekly', 'monthly'])
            ->whereNotNull('digest_last_sent_at')
            ->groupBy('digest_frequency')
            ->get();

        $lastSent = ['daily' => null, 'weekly' => null, 'monthly' => null];
        foreach ($lastSentRows as $row) {
            $lastSent[$row->digest_frequency] = $row->last_sent;
        }

        // Send log — retention limits are enforced at write time by SendDigestCommand:
        //   daily: 30 rows, weekly: 52 rows, monthly: 24 rows
        $sendLog = DigestSendLog::query()
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (DigestSendLog $r) => [
                'frequency'     => $r->frequency,
                'sent_count'    => (int) $r->sent_count,
                'skipped_count' => (int) $r->skipped_count,
                'sent_at'       => optional($r->sent_at)->toDateTimeString(),
            ])
            ->all();

        return new JsonResponse([
            'subscriptions' => [
                'total_members'     => $totalMembers,
                'total_subscribed'  => $totalSubscribed,
                'subscription_rate' => $subscriptionRate,
                'by_frequency'      => $byFrequency,
            ],
            'last_sent' => $lastSent,
            'send_log'  => $sendLog,
            // Ready-to-paste cron / process-manager lines, assembled server-side
            // so the admin panel never has to handle (or render) the raw server
            // filesystem path as a bare, standalone value. Admin-only (enforced
            // above).
            'cron'      => $this->cronLines(),
        ]);
    }

    /**
     * Build the copy-ready cron / Supervisor lines for the setup panel using
     * the Flarum root path + the admin's saved queue settings. Returning the
     * finished strings (rather than the raw base path) keeps the absolute path
     * out of the API surface as a standalone, reusable value.
     *
     * @return array<string, string>
     */
    private function cronLines(): array
    {
        $base  = $this->paths->base;
        $queue = (string) ($this->settings->get('ernestdefoe-digest-mail.queue_name') ?: 'digest');
        $tries = (string) ($this->settings->get('ernestdefoe-digest-mail.queue_tries') ?: '3');

        return [
            'scheduler'  => "* * * * * cd {$base} && php flarum schedule:run >> /dev/null 2>&1",
            'worker'     => "* * * * * cd {$base} && php flarum queue:work --queue={$queue},default --max-time=55 --tries={$tries} --backoff=30 >> /dev/null 2>&1",
            'enqueue'    => "50 12 * * * cd {$base} && php flarum digest:enqueue --frequency=daily --delay=600 >> /dev/null 2>&1",
            'supervisor' => "[program:flarum-worker]\ncommand=php {$base}/flarum queue:work --queue={$queue},default --tries={$tries} --backoff=30\ndirectory={$base}\nautostart=true\nautorestart=true\nnumprocs=2\nstopwaitsecs=60\nuser=www-data\nredirect_stderr=true\nstdout_logfile={$base}/storage/logs/worker.log",
            'horizon'    => "[program:horizon]\nprocess_name=%(program_name)s\ncommand=php {$base}/flarum horizon\nautostart=true\nautorestart=true\nuser=www-data\nredirect_stderr=true\nstdout_logfile={$base}/storage/logs/horizon.log\nstopwaitsecs=3600",
        ];
    }
}
