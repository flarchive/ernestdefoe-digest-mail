<?php

namespace Resofire\DigestMail\Token;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * Eloquent model for the digest_unsubscribe_tokens table.
 *
 * Schema:
 *   id          BIGINT UNSIGNED AUTO_INCREMENT PK
 *   user_id     BIGINT UNSIGNED  UNIQUE  FK → users.id CASCADE DELETE
 *   token       VARCHAR(64)      UNIQUE
 *   created_at  TIMESTAMP
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $token
 * @property Carbon      $created_at
 * @property-read User   $user
 */
class UnsubscribeToken extends AbstractModel
{
    /**
     * Token lifetime in days.
     * DigestMailer upserts (regenerates) the token on every send, so in
     * practice tokens are refreshed every digest cycle and rarely expire.
     */
    public const EXPIRES_AFTER_DAYS = 90;

    /**
     * AbstractModel sets $timestamps = false by default, which suppresses the
     * Eloquent auto-managed updated_at column. We only have created_at, which
     * we manage manually.
     */
    public $timestamps = false;

    protected $table = 'digest_unsubscribe_tokens';

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retrieve a token record by its raw string value.
     * Returns null if the token does not exist or has expired.
     */
    public static function findValid(string $rawToken): ?static
    {
        $record = static::where('token', $rawToken)->first();

        if ($record === null) {
            return null;
        }

        if ($record->created_at->diffInDays(Carbon::now()) >= static::EXPIRES_AFTER_DAYS) {
            return null;
        }

        return $record;
    }
}
