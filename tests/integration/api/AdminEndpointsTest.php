<?php

namespace Resofire\DigestMail\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Resofire\DigestMail\Tests\integration\SeedsDigest;

class AdminEndpointsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsDigest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDigest();
        $this->prepareDatabase([
            'digest_unsubscribe_tokens' => [
                ['id' => 1, 'user_id' => 3, 'token' => str_repeat('a', 64), 'created_at' => Carbon::now()->subDays(10)],
                ['id' => 2, 'user_id' => 6, 'token' => str_repeat('b', 64), 'created_at' => Carbon::now()->subDays(91)],
            ],
        ]);
    }

    public static function routes(): array
    {
        return [
            'test send' => ['POST', '/api/ernestdefoe/digest-mail/test-send'],
            'stats' => ['GET', '/api/ernestdefoe/digest-mail/stats'],
            'subscribers' => ['GET', '/api/ernestdefoe/digest-mail/subscribers?frequency=weekly'],
            'check token' => ['GET', '/api/ernestdefoe/digest-mail/check-token?token='.str_repeat('a', 64)],
        ];
    }

    #[Test]
    #[DataProvider('routes')]
    public function a_member_is_refused(string $method, string $path)
    {
        $request = $this->requestUrl($method, $path, ['authenticatedAs' => 2, 'json' => ['email' => 'someone@machine.local']]);

        $this->assertSame(403, $this->send($request)->getStatusCode());
        $this->assertSame([], $this->recipients());
    }

    private function getJson(string $path): array
    {
        $response = $this->send($this->requestUrl('GET', $path, ['authenticatedAs' => 1]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true);
    }

    #[Test]
    public function stats_count_confirmed_subscribers_by_frequency()
    {
        $stats = $this->getJson('/api/ernestdefoe/digest-mail/stats');

        $this->assertSame(['daily' => 0, 'weekly' => 2, 'monthly' => 1], $stats['subscriptions']['by_frequency']);
        $this->assertSame(3, $stats['subscriptions']['total_subscribed']);
        $this->assertNotNull($stats['last_sent']['weekly']);
    }

    #[Test]
    public function subscribers_lists_confirmed_members_of_one_frequency()
    {
        $list = $this->getJson('/api/ernestdefoe/digest-mail/subscribers?frequency=weekly');

        $this->assertSame(['already', 'reader'], array_column($list['data'], 'username'));
        $this->assertSame(2, $list['total']);
    }

    #[Test]
    public function a_token_is_checked_and_an_expired_one_is_not_found()
    {
        $this->assertSame('reader', $this->getJson('/api/ernestdefoe/digest-mail/check-token?token='.str_repeat('a', 64))['username']);

        $response = $this->send($this->requestUrl('GET', '/api/ernestdefoe/digest-mail/check-token?token='.str_repeat('b', 64), ['authenticatedAs' => 1]));
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function a_test_digest_goes_to_the_address_given()
    {
        $response = $this->send($this->request('POST', '/api/ernestdefoe/digest-mail/test-send', [
            'authenticatedAs' => 1,
            'json' => ['email' => 'inbox@machine.local', 'frequency' => 'weekly'],
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(['inbox@machine.local'], $this->recipients());
        $this->assertStringContainsString('Fresh this week', self::$sent[0]['html']);
    }
}
