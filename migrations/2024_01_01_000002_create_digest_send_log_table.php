<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('digest_send_log')) {
            return;
        }

        $schema->create('digest_send_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            // daily | weekly | monthly
            $table->string('frequency', 16);

            // Number of jobs dispatched (i.e. emails queued) this run
            $table->unsignedInteger('sent_count');

            // Number of users skipped because their content was empty
            $table->unsignedInteger('skipped_count')->default(0);

            // UTC timestamp of when this batch was dispatched
            $table->timestamp('sent_at');

            $table->index(['frequency', 'sent_at']);
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('digest_send_log');
    },
];
