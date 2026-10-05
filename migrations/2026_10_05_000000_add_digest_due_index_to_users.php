<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * The scheduled send looks for due subscribers every minute of the send window:
 * WHERE digest_frequency = ? AND (digest_last_sent_at IS NULL OR < ?). Neither
 * column was indexed, so each run scanned the whole users table. These are this
 * extension's own columns, so indexing them is ours to do.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasIndex('users', ['digest_frequency', 'digest_last_sent_at'])) {
            $schema->table('users', function (Blueprint $table) {
                $table->index(['digest_frequency', 'digest_last_sent_at'], 'users_digest_due_index');
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasIndex('users', ['digest_frequency', 'digest_last_sent_at'])) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropIndex('users_digest_due_index');
            });
        }
    },
];
