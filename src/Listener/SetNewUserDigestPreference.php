<?php

namespace Resofire\DigestMail\Listener;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\Activated;

class SetNewUserDigestPreference
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(Activated $event): void
    {
        $mode = $this->settings->get('ernestdefoe-digest-mail.onboarding_mode', 'none');

        if ($mode === 'none') {
            return;
        }

        $user = $event->user;

        if ($mode === 'auto_enroll') {
            $frequency = $this->settings->get('ernestdefoe-digest-mail.onboarding_frequency', 'weekly');

            // Only apply if the chosen frequency is still enabled.
            $allowed = $this->settings->get('ernestdefoe-digest-mail.allow_' . $frequency, null);
            if ($allowed !== '1') {
                return;
            }

            $user->digest_frequency = $frequency;
            $user->save();

            return;
        }

        if ($mode === 'opt_in_modal') {
            $user->setPreference('digest_onboarding_pending', true);
            $user->save();

            return;
        }
    }
}
