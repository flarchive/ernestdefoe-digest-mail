<?php

namespace Resofire\DigestMail\Token;

use Carbon\Carbon;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Generates and persists one-per-user unsubscribe tokens.
 *
 * Strategy: true get-or-create. If a valid token already exists for this
 * user it is returned as-is. A new token is only generated when none exists
 * yet (first-ever digest for this user) or the existing token has expired.
 *
 * This means the link in a user's email remains valid until they click it,
 * regardless of how many test runs or re-sends happen in the meantime.
 *
 * Revocation: deleting the row in digest_unsubscribe_tokens invalidates the
 * link immediately. UnsubscribeController deletes the token on a confirmed
 * preference change, so a second click on the same emailed link will show
 * the "invalid token" error page.
 */
class UnsubscribeTokenGenerator
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * Return the existing token for $user if one exists and has not expired.
     * Otherwise generate a new token, persist it, and return it.
     */
    public function getOrCreate(User $user): string
    {
        // Check for an existing valid token first.
        $existing = $this->db->table('digest_unsubscribe_tokens')
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            $createdAt = Carbon::parse($existing->created_at);
            $notExpired = $createdAt->diffInDays(Carbon::now()) < UnsubscribeToken::EXPIRES_AFTER_DAYS;

            if ($notExpired) {
                return $existing->token;
            }
        }

        // No token or expired — generate a fresh one.
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $now   = Carbon::now()->toDateTimeString();

        $this->db->table('digest_unsubscribe_tokens')->updateOrInsert(
            ['user_id' => $user->id],
            ['token' => $token, 'created_at' => $now],
        );

        return $token;
    }
}
