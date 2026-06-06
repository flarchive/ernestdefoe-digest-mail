<?php

namespace Resofire\DigestMail\Listener;

use Flarum\User\Event\Saving;
use Illuminate\Support\Arr;

class SaveDigestFrequency
{
    /**
     * Valid values for digest_frequency.
     * NULL (omitted or explicitly null) means opted out.
     */
    private const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /**
     * Handle the User\Event\Saving event.
     *
     * EditUserHandler fires this event after applying standard attribute
     * changes but before calling $user->save(). We read 'digestFrequency'
     * from the raw JSON:API attributes payload and write it onto the User
     * model so it is persisted in the same save() call.
     *
     * Permission: users may only change their own digest preference.
     * Admins may change any user's preference (e.g. bulk opt-out tooling).
     */
    public function handle(Saving $event): void
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        // Only act if the attribute was explicitly included in the request.
        // array_key_exists distinguishes "key present with null value" (opt-out)
        // from "key absent" (no change requested).
        if (! array_key_exists('digestFrequency', $attributes)) {
            return;
        }

        $actor = $event->actor;
        $user  = $event->user;

        // Users may only set their own preference; admins may set any user's.
        $actor->assertPermission($actor->id === $user->id || $actor->isAdmin());

        $frequency = $attributes['digestFrequency'];

        // Null means opt-out. A non-null value must be one of the three valid
        // cadences.
        if ($frequency !== null && ! in_array($frequency, self::VALID_FREQUENCIES, true)) {
            throw new \InvalidArgumentException(
                'digestFrequency must be null, "daily", "weekly", or "monthly".'
            );
        }

        $user->digest_frequency = $frequency;
    }
}
