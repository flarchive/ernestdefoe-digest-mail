<?php

namespace Resofire\DigestMail\Tests\integration;

use Carbon\Carbon;
use Flarum\Extend;
use Illuminate\Mail\Events\MessageSent;

/**
 * Members at every stage of subscribing, one public discussion from this week
 * and one hidden one, and a record of every email the forum sends.
 */
trait SeedsDigest
{
    /** @var list<array{to: string, subject: string, html: string}> */
    public static array $sent = [];

    protected function seedDigest(): void
    {
        self::$sent = [];

        $this->setting('mail_driver', 'log');
        $this->extension('ernestdefoe-digest-mail');
        $this->extend(
            (new Extend\Event)->listen(MessageSent::class, function (MessageSent $event) {
                self::$sent[] = [
                    'to' => $event->message->getTo()[0]->getAddress(),
                    'subject' => (string) $event->message->getSubject(),
                    'html' => (string) $event->message->getHtmlBody(),
                ];
            })
        );

        $recently = Carbon::now()->subDays(2)->toDateTimeString();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                $this->member(3, 'reader', ['is_email_confirmed' => 1, 'digest_frequency' => 'weekly']),
                $this->member(4, 'unconfirmed', ['is_email_confirmed' => 0, 'digest_frequency' => 'weekly']),
                $this->member(5, 'already', ['is_email_confirmed' => 1, 'digest_frequency' => 'weekly', 'digest_last_sent_at' => $recently]),
                $this->member(6, 'monthly', ['is_email_confirmed' => 1, 'digest_frequency' => 'monthly']),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Fresh this week', 'slug' => 'fresh', 'user_id' => 1, 'created_at' => Carbon::now()->subDay(), 'last_posted_at' => Carbon::now()->subDay(), 'first_post_id' => 1, 'last_post_id' => 1, 'comment_count' => 1, 'participant_count' => 1],
                ['id' => 2, 'title' => 'Hidden by a moderator', 'slug' => 'hidden', 'user_id' => 1, 'created_at' => Carbon::now()->subDay(), 'last_posted_at' => Carbon::now()->subDay(), 'first_post_id' => 2, 'last_post_id' => 2, 'comment_count' => 1, 'participant_count' => 1, 'hidden_at' => Carbon::now()],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Hello</p></t>', 'created_at' => Carbon::now()->subDay()],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Gone</p></t>', 'created_at' => Carbon::now()->subDay()],
            ],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    protected function member(int $id, string $username, array $attributes): array
    {
        return ['id' => $id, 'username' => $username, 'email' => "$username@machine.local"] + $attributes + $this->normalUser();
    }

    /**
     * A request for a URL with a query string: the test request builder takes
     * a path, so the query has to be set on it separately.
     */
    protected function requestUrl(string $method, string $url, array $options = []): \Psr\Http\Message\ServerRequestInterface
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $this->request($method, (string) parse_url($url, PHP_URL_PATH), $options)->withQueryParams($query);
    }

    /** @return list<string> */
    protected function recipients(): array
    {
        return array_column(self::$sent, 'to');
    }
}
