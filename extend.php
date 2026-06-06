<?php

use Resofire\DigestMail\Api\Controller\CheckTokenController;
use Resofire\DigestMail\Api\Controller\DigestStatsController;
use Resofire\DigestMail\Api\Controller\DigestSubscribersController;
use Resofire\DigestMail\Api\Controller\SendTestDigestController;
use Resofire\DigestMail\Console\SendDigestCommand;
use Resofire\DigestMail\Console\EnqueueDigestCommand;
use Resofire\DigestMail\Controller\UnsubscribeController;
use Resofire\DigestMail\Listener\SetNewUserDigestPreference;
use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Extension\ExtensionManager;
use Flarum\User\Event\Activated;
use Flarum\User\User;

return [
    // -------------------------------------------------------------------------
    // NOTE: Migrations are auto-discovered from the /migrations directory by
    // Flarum's ExtensionManager when the extension is enabled. No registration
    // is needed or possible — there is no Extend\Migration extender in Flarum.
    // -------------------------------------------------------------------------
    // Blade view namespace
    //
    // Registers the /views directory under the 'ernestdefoe-digest-mail' namespace so that
    // views can be referenced as 'ernestdefoe-digest-mail::emails.digest' and
    // 'ernestdefoe-digest-mail::unsubscribe' anywhere in the extension.
    // -------------------------------------------------------------------------
    (new Extend\View)
        ->namespace('ernestdefoe-digest-mail', __DIR__ . '/views'),

    // -------------------------------------------------------------------------
    // New user onboarding — event listener
    //
    // Fires when a user's account is activated (email verification or admin
    // activation). Applies the configured onboarding mode: no-op, auto-enroll
    // at a chosen frequency, or set a flag for the opt-in modal flow.
    // -------------------------------------------------------------------------
    (new Extend\Event)
        ->listen(Activated::class, SetNewUserDigestPreference::class),

    // -------------------------------------------------------------------------
    // New user onboarding — preference key registration
    //
    // Registers digest_onboarding_pending in Flarum's User preferences system
    // so that it is included in the preferences JSON returned to the forum JS.
    // The boot hook reads this flag to decide whether to show the opt-in modal.
    // -------------------------------------------------------------------------
    (new Extend\User)
        ->registerPreference('digest_onboarding_pending', null, false),

    // -------------------------------------------------------------------------
    // Translations
    // -------------------------------------------------------------------------
    new Extend\Locales(__DIR__ . '/locale'),

    // -------------------------------------------------------------------------
    // Frontend assets
    //
    // Forum: adds the digest-frequency selector to the user settings page.
    // Admin: adds the digest settings panel under the extension page.
    // -------------------------------------------------------------------------
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    // -------------------------------------------------------------------------
    // Console
    //
    // .command() — registers `php flarum digest:send` for manual/test runs.
    //   Flags:
    //     --frequency=daily|weekly|monthly   override the time gate
    //     --dry-run                          print recipients, don't dispatch
    //     --user=ID                          restrict to one user (testing)
    //
    // .schedule() — registers the per-minute scheduler entry via Flarum's own
    // scheduling system (flarum.console.scheduled), which is the correct API.
    // Runs every minute — the command's own dueFrequencies() time gate handles
    // whether to actually do work based on the configured send window. Outside
    // the window it exits immediately with no DB queries beyond settings reads.
    // -------------------------------------------------------------------------
    (new Extend\Console)
        ->command(SendDigestCommand::class)
        ->command(EnqueueDigestCommand::class)
        ->schedule(SendDigestCommand::class, fn ($event) => $event->everyMinute()),

    // -------------------------------------------------------------------------
    // Forum routes
    //
    // GET /digest/unsubscribe?token=...
    //   Shows the preference picker. With &frequency=... also present, saves
    //   immediately (signed token authenticates — no POST/CSRF needed) and
    //   redirects to ?saved=1.
    // -------------------------------------------------------------------------
    (new Extend\Routes('forum'))
        ->get(
            '/digest/unsubscribe',
            'resofire.digest-mail.unsubscribe',
            UnsubscribeController::class
        ),

    // -------------------------------------------------------------------------
    // API route — test send
    //
    // POST /api/ernestdefoe/digest-mail/test-send
    //   Admin-only. Sends a live digest email to an arbitrary address.
    //   Does not update digest_last_sent_at or consume unsubscribe tokens.
    // -------------------------------------------------------------------------
    (new Extend\Routes('api'))
        ->post(
            '/ernestdefoe/digest-mail/test-send',
            'resofire.digest-mail.test-send',
            SendTestDigestController::class
        )
        ->get(
            '/ernestdefoe/digest-mail/stats',
            'resofire.digest-mail.stats',
            DigestStatsController::class
        )
        ->get(
            '/ernestdefoe/digest-mail/subscribers',
            'resofire.digest-mail.subscribers',
            DigestSubscribersController::class
        )
        ->get(
            '/ernestdefoe/digest-mail/check-token',
            'resofire.digest-mail.check-token',
            CheckTokenController::class
        ),

    // -------------------------------------------------------------------------
    // Expose digestFrequency on the user resource
    //
    // The forum JS reads this attribute when the settings page mounts so it
    // can pre-select the correct option in the frequency dropdown.
    //
    // The field is writable on update (PATCH /api/users/{id}) — persistence is
    // handled automatically by AbstractDatabaseResource::setValue(), which maps
    // the camelCase field name to the snake_case model attribute
    // (digestFrequency → digest_frequency) and calls $user->save().
    //
    // Permission: users may only write their own preference; admins may write
    // any user's preference. This mirrors the logic previously in
    // SaveDigestFrequency.
    // -------------------------------------------------------------------------
    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Str::make('digestFrequency')
                ->nullable()
                ->get(fn (User $user) => $user->digest_frequency)
                ->writable(fn (User $user, Context $context) =>
                    $context->getActor()->id === $user->id || $context->getActor()->isAdmin()
                )
                ->in(['daily', 'weekly', 'monthly']),
        ]),

    // Expose optional-integration availability to the admin JS so toggles
    // can be grayed out when an integration extension is not installed.
    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(function () {
            /** @var ExtensionManager $manager */
            $manager = resolve(ExtensionManager::class);

            /** @var \Flarum\Settings\SettingsRepositoryInterface $settings */
            $settings = resolve(\Flarum\Settings\SettingsRepositoryInterface::class);
            $raw = fn(string $key, string $fallback) => ($v = $settings->get($key)) === null || $v === '' ? $fallback : $v;

            return [
                Schema\Arr::make('digestExtensions')
                    ->get(function () use ($manager) {
                        return [
                            'leaderboard' => [
                                'enabled'         => $manager->isEnabled('huseyinfiliz-leaderboard'),
                                'title'           => 'Leaderboard',
                                'iconName'        => 'fas fa-trophy',
                                'iconColor'       => '#ffffff',
                                'iconBg'          => '#3498db',
                            ],
                            'badges' => [
                                'enabled'         => $manager->isEnabled('fof-badges'),
                                'title'           => 'Badges',
                                'iconName'        => 'fas fa-award',
                                'iconColor'       => '#ffffff',
                                'iconBg'          => '#8b5cf6',
                            ],
                            'pickem' => [
                                'enabled'         => $manager->isEnabled('huseyinfiliz-pickem'),
                                'title'           => "Pick'em",
                                'iconName'        => 'fas fa-football-ball',
                                'iconColor'       => '#ffffff',
                                'iconBg'          => '#16a34a',
                            ],
                            'picks' => [
                                'enabled'         => $manager->isEnabled('ernestdefoe-picks'),
                                'title'           => 'CFB Picks',
                                'iconName'        => 'fas fa-football',
                                'iconColor'       => '#ffffff',
                                'iconBg'          => '#69c6b9',
                            ],
                            'giveaways' => [
                                'enabled'         => $manager->isEnabled('ernestdefoe-giveaways'),
                                'title'           => 'Giveaways',
                                'iconName'        => 'fas fa-gift',
                                'iconColor'       => '#ffffff',
                                'iconBg'          => '#69c6b9',
                            ],
                            'nightmode' => [
                                'enabled' => false,
                            ],
                            'cosmos-theme' => [
                                'enabled' => false,
                            ],
                            'gamepedia' => [
                                'enabled'  => $manager->isEnabled('huseyinfiliz-gamepedia'),
                                'title'    => 'Gamepedia',
                                'iconName' => 'fas fa-gamepad',
                                'iconColor'=> '#ffffff',
                                'iconBg'   => '#e85d04',
                            ],
                            'resofireGamepedia' => [
                                'enabled'  => $manager->isEnabled('resofire-gamepedia'),
                                'title'    => 'Resofire Gamepedia',
                                'iconName' => 'fas fa-gamepad',
                                'iconColor'=> '#ffffff',
                                'iconBg'   => '#1a1a2e',
                            ],
                            'likes' => [
                                'enabled' => $manager->isEnabled('flarum-likes'),
                            ],
                            'reactions' => [
                                'enabled'  => $manager->isEnabled('fof-reactions') || $manager->isEnabled('resofire-reactions'),
                                'title'    => 'Reactions',
                                'iconName' => 'fas fa-smile',
                                'iconColor'=> '#ffffff',
                                'iconBg'   => '#f59e0b',
                            ],
                            'awards' => [
                                'enabled'  => $manager->isEnabled('huseyinfiliz-awards'),
                                'title'    => 'Awards',
                                'iconName' => 'fas fa-star',
                                'iconColor'=> '#ffffff',
                                'iconBg'   => '#f59e0b',
                            ],
                        ];
                    }),
                Schema\Arr::make('digestAllowedFrequencies')
                    ->get(function () use ($raw) {
                        return [
                            'daily'   => $raw('ernestdefoe-digest-mail.allow_daily',   '0') === '1',
                            'weekly'  => $raw('ernestdefoe-digest-mail.allow_weekly',  '1') === '1',
                            'monthly' => $raw('ernestdefoe-digest-mail.allow_monthly', '1') === '1',
                        ];
                    }),
            ];
        }),

    // Settings defaults — returned by Flarum before the admin saves for the
    // first time, so the extension behaves sensibly out of the box.
    (new Extend\Settings())
        ->default('ernestdefoe-digest-mail.enable_badges',      '1')
        ->default('ernestdefoe-digest-mail.enable_leaderboard', '1')
        ->default('ernestdefoe-digest-mail.enable_picks',       '1')
        ->default('ernestdefoe-digest-mail.limit_picks',        '5')
        ->default('ernestdefoe-digest-mail.picks_leaderboard_scope', 'alltime')
        ->default('ernestdefoe-digest-mail.enable_giveaways',   '1')
        ->default('ernestdefoe-digest-mail.limit_giveaways',    '5')
        ->default('ernestdefoe-digest-mail.enable_gamepedia',              '1')
        ->default('ernestdefoe-digest-mail.enable_resofire_gamepedia',     '1')
        ->default('ernestdefoe-digest-mail.limit_resofire_gamepedia',      '5')
        ->default('ernestdefoe-digest-mail.enable_reactions',   '1')
        ->default('ernestdefoe-digest-mail.enable_awards',      '1')
        ->default('ernestdefoe-digest-mail.limit_favorites',    '6')
        ->default('ernestdefoe-digest-mail.queue_name',         'digest')
        ->default('ernestdefoe-digest-mail.queue_chunk_size',   '200')
        ->default('ernestdefoe-digest-mail.queue_delay',        '0')
        ->default('ernestdefoe-digest-mail.queue_tries',        '3')
        ->default('ernestdefoe-digest-mail.section_order',      '')
        ->default('ernestdefoe-digest-mail.allow_daily',        '0')
        ->default('ernestdefoe-digest-mail.allow_weekly',       '1')
        ->default('ernestdefoe-digest-mail.allow_monthly',      '1')
        ->default('ernestdefoe-digest-mail.timezone',           'UTC')
        ->default('ernestdefoe-digest-mail.send_hour',          '8')
        ->default('ernestdefoe-digest-mail.send_window_start',  '8')
        ->default('ernestdefoe-digest-mail.send_window_end',    '8')
        ->default('ernestdefoe-digest-mail.weekly_day',         '1')
        ->default('ernestdefoe-digest-mail.monthly_day',        '1')
        ->default('ernestdefoe-digest-mail.onboarding_mode',    'none')
        ->default('ernestdefoe-digest-mail.onboarding_frequency', 'weekly'),

];
