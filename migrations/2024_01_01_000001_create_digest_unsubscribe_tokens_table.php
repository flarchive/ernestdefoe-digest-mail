<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('digest_unsubscribe_tokens')) {
            return;
        }

        $schema->create('digest_unsubscribe_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');

            // One token per user. UnsubscribeTokenGenerator uses updateOrInsert()
            // so this unique index enforces the one-per-user constraint.
            // No foreign key — avoids engine/collation mismatch issues across
            // different Flarum hosting environments.
            $table->unsignedBigInteger('user_id')->unique();

            // 64 hex characters = 32 random bytes.
            $table->string('token', 64)->unique();

            $table->timestamp('created_at');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('digest_unsubscribe_tokens');
    },
];
