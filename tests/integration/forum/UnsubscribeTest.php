<?php

namespace Resofire\DigestMail\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Resofire\DigestMail\Tests\integration\SeedsDigest;

/**
 * The link at the foot of every digest. The signed token is the only
 * authentication — no session, no CSRF — so it must work for exactly the
 * member it was made for and only while it is valid.
 */
class UnsubscribeTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsDigest;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDigest();
        $this->token = str_repeat('c', 64);
        $this->prepareDatabase([
            'digest_unsubscribe_tokens' => [
                ['id' => 1, 'user_id' => 3, 'token' => $this->token, 'created_at' => Carbon::now()->subDays(10)],
                ['id' => 2, 'user_id' => 6, 'token' => str_repeat('d', 64), 'created_at' => Carbon::now()->subDays(91)],
            ],
        ]);
    }

    private function frequency(int $userId): ?string
    {
        return $this->database()->table('users')->where('id', $userId)->value('digest_frequency');
    }

    #[Test]
    public function the_link_shows_the_members_choices()
    {
        $response = $this->send($this->requestUrl('GET', '/digest/unsubscribe?token='.$this->token));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('token='.$this->token.'&amp;frequency=', (string) $response->getBody());
    }

    #[Test]
    public function choosing_off_unsubscribes_and_spends_the_token()
    {
        $response = $this->send($this->requestUrl('GET', '/digest/unsubscribe?token='.$this->token.'&frequency=off'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/digest/unsubscribe?saved=1', $response->getHeaderLine('Location'));
        $this->assertNull($this->frequency(3));
        $this->assertSame(0, $this->database()->table('digest_unsubscribe_tokens')->where('user_id', 3)->count());
    }

    #[Test]
    public function choosing_another_frequency_switches_to_it()
    {
        $this->send($this->requestUrl('GET', '/digest/unsubscribe?token='.$this->token.'&frequency=monthly'));

        $this->assertSame('monthly', $this->frequency(3));
    }

    #[Test]
    public function an_expired_or_unknown_token_changes_nothing()
    {
        foreach ([str_repeat('d', 64), str_repeat('e', 64)] as $token) {
            $response = $this->send($this->requestUrl('GET', '/digest/unsubscribe?token='.$token.'&frequency=off'));

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringNotContainsString('frequency=', (string) $response->getBody());
        }

        $this->assertSame('monthly', $this->frequency(6));
    }
}
