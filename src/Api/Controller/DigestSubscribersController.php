<?php

namespace Resofire\DigestMail\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/ernestdefoe/digest-mail/subscribers
 *
 * Returns a paginated list of subscribers for a given frequency.
 *
 * Query parameters:
 *   frequency  — 'daily' | 'weekly' | 'monthly'  (required)
 *   page       — 1-based page number              (default: 1)
 *   per_page   — results per page                 (default: 15, max: 50)
 *
 * Response:
 *   {
 *     "data": [
 *       { "id": 1, "username": "...", "avatar_url": "...", "last_sent": "..." }
 *     ],
 *     "total": 42,
 *     "page": 1,
 *     "per_page": 15,
 *     "total_pages": 3
 *   }
 *
 * Admin only.
 */
class DigestSubscribersController implements RequestHandlerInterface
{
    private const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly'];
    private const DEFAULT_PER_PAGE  = 15;
    private const MAX_PER_PAGE      = 50;

    public function __construct(
        private Factory $filesystem,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $params    = $request->getQueryParams();
        $frequency = $params['frequency'] ?? '';
        $page      = max(1, (int) ($params['page'] ?? 1));
        $perPage   = min(self::MAX_PER_PAGE, max(1, (int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE)));

        if (! in_array($frequency, self::VALID_FREQUENCIES, true)) {
            return new JsonResponse(['error' => 'Invalid frequency.'], 400);
        }

        $base = User::query()
            ->where('digest_frequency', $frequency)
            ->where('is_email_confirmed', true);

        $total  = (clone $base)->count();
        $offset = ($page - 1) * $perPage;

        $rows = (clone $base)
            ->select(['id', 'username', 'avatar_url', 'digest_last_sent_at'])
            ->orderBy('username', 'asc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $avatarDisk = $this->filesystem->disk('flarum-avatars');

        $data = $rows->map(function ($row) use ($avatarDisk) {
            // Flarum stores avatar_url as a bare filename when uploaded via the
            // built-in uploader. Resolve it to a full URL the same way the
            // User model's getAvatarUrlAttribute accessor does.
            $avatarUrl = $row->avatar_url;
            if ($avatarUrl && strpos($avatarUrl, '://') === false) {
                $avatarUrl = $avatarDisk->url($avatarUrl);
            }

            return [
                'id'         => $row->id,
                'username'   => $row->username,
                'avatar_url' => $avatarUrl,
                'last_sent'  => $row->digest_last_sent_at,
            ];
        })->values()->all();

        return new JsonResponse([
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ]);
    }
}
