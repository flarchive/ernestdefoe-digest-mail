<?php

namespace Resofire\DigestMail;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * Eloquent model for the digest_send_log table.
 *
 * One row per (frequency, day) recording how many digest emails were
 * dispatched/skipped in that batch. Written by SendDigestCommand and read by
 * the admin DigestStatsController. Retention is enforced at write time
 * (daily: 30 rows, weekly: 52, monthly: 24).
 *
 * Schema:
 *   id             BIGINT UNSIGNED AUTO_INCREMENT PK
 *   frequency      VARCHAR(16)   — daily | weekly | monthly
 *   sent_count     INT UNSIGNED
 *   skipped_count  INT UNSIGNED  default 0
 *   sent_at        TIMESTAMP     — UTC time the batch was dispatched
 *
 * @property int    $id
 * @property string $frequency
 * @property int    $sent_count
 * @property int    $skipped_count
 * @property Carbon $sent_at
 */
class DigestSendLog extends AbstractModel
{
    /**
     * The table tracks its own sent_at column; there is no created_at /
     * updated_at pair, so disable Eloquent's automatic timestamps.
     */
    public $timestamps = false;

    protected $table = 'digest_send_log';

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];
}
