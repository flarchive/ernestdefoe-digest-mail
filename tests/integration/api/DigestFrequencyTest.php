<?php

namespace Resofire\DigestMail\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Resofire\DigestMail\Tests\integration\SeedsDigest;

/**
 * The frequency a member picks on their settings page: theirs to change, an
 * admin's to change, nobody else's.
 */
class DigestFrequencyTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsDigest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDigest();
    }

    private function patch(int $as, int $userId, mixed $frequency): int
    {
        return $this->send($this->request('PATCH', "/api/users/$userId", [
            'authenticatedAs' => $as,
            'json' => ['data' => ['type' => 'users', 'id' => (string) $userId, 'attributes' => ['digestFrequency' => $frequency]]],
        ]))->getStatusCode();
    }

    private function frequency(int $userId): ?string
    {
        return $this->database()->table('users')->where('id', $userId)->value('digest_frequency');
    }

    #[Test]
    public function a_member_sets_their_own()
    {
        $this->assertSame(200, $this->patch(2, 2, 'monthly'));
        $this->assertSame('monthly', $this->frequency(2));

        $this->assertSame(200, $this->patch(2, 2, null));
        $this->assertNull($this->frequency(2));
    }

    #[Test]
    public function a_member_cannot_set_someone_elses()
    {
        $this->patch(2, 3, 'daily');

        $this->assertSame('weekly', $this->frequency(3));
    }

    #[Test]
    public function an_admin_can_set_anyones()
    {
        $this->assertSame(200, $this->patch(1, 3, 'monthly'));
        $this->assertSame('monthly', $this->frequency(3));
    }

    #[Test]
    public function only_the_three_frequencies_are_accepted()
    {
        $this->assertSame(422, $this->patch(2, 2, 'hourly'));
        $this->assertNull($this->frequency(2));
    }
}
