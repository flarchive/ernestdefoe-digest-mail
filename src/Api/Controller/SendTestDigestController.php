<?php

namespace Resofire\DigestMail\Api\Controller;

use Resofire\DigestMail\DigestContent;
use Resofire\DigestMail\DigestMailer;
use Resofire\DigestMail\DigestQuery;
use Resofire\DigestMail\Token\UnsubscribeTokenGenerator;
use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/ernestdefoe/digest-mail/test-send
 *
 * Renders and sends a digest email immediately to an arbitrary address,
 * without touching digest_last_sent_at or consuming any unsubscribe token.
 *
 * Admin only. Intended for verifying mail settings and template rendering.
 *
 * Request body (JSON):
 *   {
 *     "email":     "test@example.com",   // required — recipient address
 *     "frequency": "weekly"              // optional — daily|weekly|monthly, default "weekly"
 *   }
 *
 * Success response (200):
 *   { "sent": true, "to": "test@example.com", "frequency": "weekly" }
 *
 * Error responses:
 *   400  missing/invalid fields
 *   403  actor is not an admin
 *   500  mail sending failed (message included)
 */
class SendTestDigestController implements RequestHandlerInterface
{
    private const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function __construct(
        private DigestQuery                 $query,
        private DigestMailer                $mailer,
        private UnsubscribeTokenGenerator   $tokenGenerator,
        private SettingsRepositoryInterface $settings,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Admin only.
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $body      = $request->getParsedBody();
        $email     = trim((string) Arr::get($body, 'email', ''));
        $frequency = (string) Arr::get($body, 'frequency', 'weekly');
        $theme     = (string) Arr::get($body, 'theme', 'auto');
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            $theme = 'auto';
        }

        // Validate inputs.
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(
                ['error' => 'A valid email address is required.'],
                400
            );
        }

        if (! in_array($frequency, self::VALID_FREQUENCIES, true)) {
            return new JsonResponse(
                ['error' => 'frequency must be daily, weekly, or monthly.'],
                400
            );
        }

        // Build digest content using the actor's own visibility scope so the
        // preview reflects what a real subscriber would see. We look back one
        // full period so there is content to show even on a quiet forum.
        $since = match ($frequency) {
            'daily'   => Carbon::now('UTC')->subDay(),
            'weekly'  => Carbon::now('UTC')->subWeek(),
            'monthly' => Carbon::now('UTC')->subMonth(),
        };

        $content = $this->query->buildForUser($actor, $since, $frequency, $theme);

        // If the forum has no content at all in the period, build a minimal
        // stub so the admin still gets a rendered email to inspect the layout.
        // We extend the lookback window up to 90 days before giving up.
        if ($content->isEmpty()) {
            $extended = Carbon::now('UTC')->subDays(90);
            $content  = $this->query->buildForUser($actor, $extended, $frequency, $theme);
        }

        // Build a temporary User-like object pointed at the test address.
        // We use the real actor's account (for permissions/preferences) but
        // override the email so the mail goes to the test address.
        $testUser                = clone $actor;
        $testUser->email         = $email;
        $testUser->display_name  = $testUser->display_name ?? $testUser->username;

        // Generate (or reuse) a real unsubscribe token so the link in the
        // test email is fully clickable. The next real digest send will
        // overwrite it with a fresh token automatically.
        $token = $this->tokenGenerator->getOrCreate($actor);

        try {
            $this->mailer->sendToUser($testUser, $content, $token);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Mail sending failed: ' . $e->getMessage()],
                500
            );
        }

        $lb = $content->leaderboard;

        return new JsonResponse([
            'sent'      => true,
            'to'        => $email,
            'frequency' => $frequency,
            '_lb_enabled'  => $lb['enabled'] ?? null,
            '_lb_count'    => count($lb['entries'] ?? []),
        ]);
    }
}
