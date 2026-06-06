<?php

namespace Resofire\DigestMail\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Resofire\DigestMail\Token\UnsubscribeToken;
use Carbon\Carbon;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/ernestdefoe/digest-mail/check-token?token=...
 *
 * Admin-only. Checks whether an unsubscribe token is valid and returns
 * the associated user and expiry information.
 *
 * Success response (200):
 *   { "valid": true, "username": "...", "created_at": "...", "expires_at": "..." }
 *
 * Error response (404):
 *   { "errors": [{ "detail": "Token not found or expired." }] }
 */
class CheckTokenController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $rawToken = $request->getQueryParams()['token'] ?? '';

        if ($rawToken === '') {
            return new JsonResponse(
                ['errors' => [['detail' => 'No token provided.']]],
                400
            );
        }

        $token = UnsubscribeToken::findValid($rawToken);

        if ($token === null) {
            return new JsonResponse(
                ['errors' => [['detail' => 'Token not found or expired.']]],
                404
            );
        }

        $expiresAt = Carbon::parse($token->created_at)
            ->addDays(UnsubscribeToken::EXPIRES_AFTER_DAYS)
            ->toDateTimeString();

        return new JsonResponse([
            'valid'      => true,
            'username'   => $token->user->username,
            'created_at' => $token->created_at->toDateTimeString(),
            'expires_at' => $expiresAt,
        ]);
    }
}
