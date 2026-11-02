<?php

namespace Resofire\DigestMail\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Resofire\DigestMail\Tests\integration\SeedsDigest;

/**
 * What happens to a member's digest when their account is activated — here
 * by an admin confirming the unconfirmed member's email.
 */
class OnboardingTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsDigest;

    private function activate(): object
    {
        $this->seedDigest();
        $this->prepareDatabase([
            'users' => [$this->member(7, 'newcomer', ['is_email_confirmed' => 0])],
        ]);

        $response = $this->send($this->request('PATCH', '/api/users/7', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['type' => 'users', 'id' => '7', 'attributes' => ['isEmailConfirmed' => true]]],
        ]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->database()->table('users')->where('id', 7)->first();
    }

    #[Test]
    public function by_default_nothing_changes()
    {
        $user = $this->activate();

        $this->assertNull($user->digest_frequency);
        $this->assertArrayNotHasKey('digest_onboarding_pending', json_decode($user->preferences ?? '[]', true) ?: []);
    }

    #[Test]
    public function auto_enroll_subscribes_them_at_the_chosen_frequency()
    {
        $this->setting('ernestdefoe-digest-mail.onboarding_mode', 'auto_enroll');
        $this->setting('ernestdefoe-digest-mail.onboarding_frequency', 'monthly');
        $this->setting('ernestdefoe-digest-mail.allow_monthly', '1');

        $this->assertSame('monthly', $this->activate()->digest_frequency);
    }

    #[Test]
    public function auto_enroll_does_nothing_if_that_frequency_is_switched_off()
    {
        $this->setting('ernestdefoe-digest-mail.onboarding_mode', 'auto_enroll');
        $this->setting('ernestdefoe-digest-mail.onboarding_frequency', 'daily');
        $this->setting('ernestdefoe-digest-mail.allow_daily', '0');

        $this->assertNull($this->activate()->digest_frequency);
    }

    #[Test]
    public function opt_in_flags_them_for_the_modal()
    {
        $this->setting('ernestdefoe-digest-mail.onboarding_mode', 'opt_in_modal');

        $user = $this->activate();

        $this->assertNull($user->digest_frequency);
        $this->assertTrue(json_decode($user->preferences, true)['digest_onboarding_pending']);
    }
}
