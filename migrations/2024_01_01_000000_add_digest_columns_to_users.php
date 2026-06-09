<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) use ($schema) {
            // NULL  = opted out
            // 'daily' | 'weekly' | 'monthly' = opted in at that cadence
            if (! $schema->hasColumn('users', 'digest_frequency')) {
                $table->string('digest_frequency', 10)->nullable()->default(null)->after('preferences');
            }

            // Updated only when a non-empty digest is actually dispatched.
            // NULL means the user has never received one; treated as "always due".
            if (! $schema->hasColumn('users', 'digest_last_sent_at')) {
                $table->timestamp('digest_last_sent_at')->nullable()->default(null)->after('digest_frequency');
            }
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) use ($schema) {
            $columns = array_filter(
                ['digest_frequency', 'digest_last_sent_at'],
                fn (string $col) => $schema->hasColumn('users', $col)
            );

            if (! empty($columns)) {
                $table->dropColumn(array_values($columns));
            }
        });
    },
];
