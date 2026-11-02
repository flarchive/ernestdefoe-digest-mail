<?php

namespace Resofire\DigestMail\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use PHPUnit\Framework\Attributes\Test;
use Resofire\DigestMail\DigestQuery;
use Resofire\DigestMail\Tests\integration\SeedsDigest;

/**
 * digest:send picks out who is due and mails each of them. The queue is the
 * sync driver here, so every queued digest is built and sent in-line.
 */
class SendDigestTest extends ConsoleTestCase
{
    use RetrievesAuthorizedUsers;
    use SeedsDigest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDigest();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function lastSent(int $userId): ?string
    {
        return $this->database()->table('users')->where('id', $userId)->value('digest_last_sent_at');
    }

    #[Test]
    public function only_confirmed_members_who_are_due_get_a_digest()
    {
        $before = $this->lastSent(5);

        $output = $this->runCommand(['command' => 'digest:send', '--frequency' => 'weekly']);

        $this->assertStringContainsString('Dispatched: 1.', $output);
        $this->assertSame(['reader@machine.local'], $this->recipients(), 'Not the unconfirmed address, not someone sent one two days ago, not a monthly subscriber');

        $html = self::$sent[0]['html'];
        $this->assertStringContainsString('Fresh this week', $html);
        $this->assertStringNotContainsString('Hidden by a moderator', $html);
        $this->assertStringContainsString('/digest/unsubscribe?token=', $html);

        $this->assertNotNull($this->lastSent(3), 'Stamped so the next minute does not send it again');
        $this->assertNull($this->lastSent(4));
        $this->assertSame($before, $this->lastSent(5));
        $this->assertNull($this->lastSent(6));

        $log = $this->database()->table('digest_send_log')->get();
        $this->assertCount(1, $log);
        $this->assertSame(['weekly', 1], [$log[0]->frequency, (int) $log[0]->sent_count]);
    }

    #[Test]
    public function the_digest_shows_the_logo_cores_emails_show()
    {
        if (! class_exists(\Flarum\Mail\EmailLogo::class)) {
            $this->markTestSkipped('Emails have had their own logo since Flarum 2.0.0.');
        }

        // A forum logo in WebP, and the PNG copy core made of it for email.
        $this->setting('logo_path', 'logo-forum.webp');
        $this->setting('logo_email_copy_path', 'logo-email-copy.png');

        $this->runCommand(['command' => 'digest:send', '--frequency' => 'weekly']);

        $html = self::$sent[0]['html'];
        $this->assertStringContainsString('/assets/logo-email-copy.png"', $html);
        $this->assertStringNotContainsString('logo-forum.webp', $html);
    }

    #[Test]
    public function a_dry_run_lists_recipients_and_sends_nothing()
    {
        $output = $this->runCommand(['command' => 'digest:send', '--frequency' => 'weekly', '--dry-run' => true]);

        $this->assertStringContainsString('[dry-run]  reader (#3)', $output);
        $this->assertSame([], $this->recipients());
        $this->assertNull($this->lastSent(3));
        $this->assertSame(0, $this->database()->table('digest_send_log')->count());
    }

    #[Test]
    public function an_unknown_frequency_is_refused()
    {
        $output = $this->runCommand(['command' => 'digest:send', '--frequency' => 'hourly']);

        $this->assertStringContainsString('Invalid --frequency', $output);
        $this->assertSame([], $this->recipients());
    }

    #[Test]
    public function enqueue_sends_to_the_same_members()
    {
        $output = $this->runCommand(['command' => 'digest:enqueue', '--frequency' => 'weekly']);

        $this->assertStringContainsString('Enqueued: 1.', $output);
        $this->assertSame(['reader@machine.local'], $this->recipients());
    }

    /**
     * A digest lists discussions; building one must not cost a query per
     * discussion listed. Two more discussions in the period, same query count.
     */
    #[Test]
    public function the_query_count_does_not_grow_with_the_discussions_listed()
    {
        // The shared sections are cached under the period's start, to the
        // second. Held still, every run here reads the same cache entry;
        // otherwise a run that starts in the next second rebuilds it, and the
        // count doubles.
        Carbon::setTestNow(Carbon::now());

        $count = function (): int {
            $db = $this->database();
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->runCommand(['command' => 'digest:send', '--frequency' => 'weekly', '--user' => 3]);
            $db->disableQueryLog();
            $db->table('users')->where('id', 3)->update(['digest_last_sent_at' => null]);
            $db->table('digest_send_log')->delete();

            return count($db->getQueryLog());
        };

        $count(); // warm-up: the first run creates the token and the caches
        $one = $count();

        $now = Carbon::now()->subHours(3);
        $this->database()->table('discussions')->insert([
            ['id' => 3, 'title' => 'Second', 'slug' => 'second', 'user_id' => 2, 'created_at' => $now, 'last_posted_at' => $now, 'last_posted_user_id' => 2, 'comment_count' => 1, 'participant_count' => 1],
            ['id' => 4, 'title' => 'Third', 'slug' => 'third', 'user_id' => 6, 'created_at' => $now, 'last_posted_at' => $now, 'last_posted_user_id' => 6, 'comment_count' => 1, 'participant_count' => 1],
        ]);
        $this->database()->table('posts')->insert([
            ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Two</p></t>', 'created_at' => $now],
            ['id' => 4, 'discussion_id' => 4, 'number' => 1, 'user_id' => 6, 'type' => 'comment', 'content' => '<t><p>Three</p></t>', 'created_at' => $now],
        ]);
        $this->app()->getContainer()->make('cache.store')->flush();
        $count();
        $three = $count();

        $this->assertStringContainsString('Third', end(self::$sent)['html']);
        $this->assertSame($one, $three);
    }

    /**
     * The community stats count on any forum, with or without flarum/approval
     * (whose is_approved column this test forum does not have).
     */
    #[Test]
    public function the_stats_count_without_flarum_approval()
    {
        $stats = $this->app()->getContainer()->make(DigestQuery::class)->getStats(Carbon::now()->subWeek());

        $this->assertSame(2, $stats['posts']);
        $this->assertSame(1, $stats['discussions'], 'Not the hidden discussion');
        $this->assertSame(1, $stats['activeUsers']);
    }
}
