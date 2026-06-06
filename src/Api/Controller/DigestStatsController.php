<?php

namespace Resofire\DigestMail\Api\Controller;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DigestStatsController implements RequestHandlerInterface
{
    public function __construct(
        private ConnectionInterface $db,
        private Paths               $paths,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        // Subscription counts
        $totalMembers = $this->db->table('users')
            ->where('is_email_confirmed', true)
            ->count();

        $freqRows = $this->db->table('users')
            ->select('digest_frequency', $this->db->raw('COUNT(*) as cnt'))
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
        $lastSentRows = $this->db->table('users')
            ->select('digest_frequency', $this->db->raw('MAX(digest_last_sent_at) as last_sent'))
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
        $logExists = $this->db->getSchemaBuilder()->hasTable('digest_send_log');
        $sendLog   = [];

        if ($logExists) {
            $rows = $this->db->table('digest_send_log')
                ->orderBy('sent_at', 'desc')
                ->get();

            foreach ($rows as $r) {
                $sendLog[] = [
                    'frequency'     => $r->frequency,
                    'sent_count'    => (int) $r->sent_count,
                    'skipped_count' => (int) $r->skipped_count,
                    'sent_at'       => $r->sent_at,
                ];
            }
        }

        return new JsonResponse([
            'subscriptions' => [
                'total_members'     => $totalMembers,
                'total_subscribed'  => $totalSubscribed,
                'subscription_rate' => $subscriptionRate,
                'by_frequency'      => $byFrequency,
            ],
            'last_sent' => $lastSent,
            'send_log'  => $sendLog,
            // Filesystem path to the Flarum root — used by the admin panel to
            // generate copy-ready cron lines. Only returned to admins (enforced
            // above). Never exposed via the public ForumSerializer.
            'base_path' => $this->paths->base,
        ]);
    }
}
