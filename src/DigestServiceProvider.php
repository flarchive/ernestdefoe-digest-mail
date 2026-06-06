<?php

namespace Resofire\DigestMail;

use Resofire\DigestMail\Console\SendDigestCommand;
use Illuminate\Console\Scheduling\Schedule;
use Flarum\Foundation\AbstractServiceProvider;

/**
 * Registers the hourly scheduler entry for the digest:send command.
 */
class DigestServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // Nothing to bind.
    }

    public function boot(): void
    {
        $this->container->resolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(SendDigestCommand::class)->hourly();
        });
    }
}
