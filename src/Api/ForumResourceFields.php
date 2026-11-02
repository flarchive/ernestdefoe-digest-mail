<?php

namespace Resofire\DigestMail\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Forum attributes the digest JS reads: which optional integrations are
 * installed (admin only, so toggles can be grayed out) and which digest
 * frequencies members may pick.
 */
class ForumResourceFields
{
    /**
     * Optional integrations: key => [extension id(s), title, icon, icon background].
     * A null title marks an entry the admin only needs the enabled flag for.
     */
    private const INTEGRATIONS = [
        'leaderboard' => [['huseyinfiliz-leaderboard'], 'Leaderboard', 'fas fa-trophy', '#3498db'],
        'badges' => [['fof-badges'], 'Badges', 'fas fa-award', '#8b5cf6'],
        'pickem' => [['huseyinfiliz-pickem'], "Pick'em", 'fas fa-football-ball', '#16a34a'],
        'picks' => [['ernestdefoe-picks'], 'CFB Picks', 'fas fa-football', '#69c6b9'],
        'giveaways' => [['ernestdefoe-giveaways'], 'Giveaways', 'fas fa-gift', '#69c6b9'],
        'nightmode' => [[], null, null, null],
        'cosmos-theme' => [[], null, null, null],
        'gamepedia' => [['huseyinfiliz-gamepedia'], 'Gamepedia', 'fas fa-gamepad', '#e85d04'],
        'resofireGamepedia' => [['resofire-gamepedia'], 'Resofire Gamepedia', 'fas fa-gamepad', '#1a1a2e'],
        'likes' => [['flarum-likes'], null, null, null],
        'reactions' => [['fof-reactions', 'resofire-reactions'], 'Reactions', 'fas fa-smile', '#f59e0b'],
        'awards' => [['huseyinfiliz-awards'], 'Awards', 'fas fa-star', '#f59e0b'],
    ];

    public function __construct(
        private ExtensionManager $extensions,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    public function __invoke(): array
    {
        return [
            // Reveals which integrations are installed; consumed solely by the
            // admin panel, so hidden from guests and regular members.
            Schema\Arr::make('digestExtensions')
                ->visible(fn ($model, Context $context) => $context->getActor()->isAdmin())
                ->get(fn () => $this->integrations()),
            Schema\Arr::make('digestAllowedFrequencies')
                ->get(fn () => [
                    'daily' => $this->setting('allow_daily', '0') === '1',
                    'weekly' => $this->setting('allow_weekly', '1') === '1',
                    'monthly' => $this->setting('allow_monthly', '1') === '1',
                ]),
        ];
    }

    private function integrations(): array
    {
        $out = [];

        foreach (self::INTEGRATIONS as $key => [$ids, $title, $icon, $bg]) {
            $enabled = false;
            foreach ($ids as $id) {
                $enabled = $enabled || $this->extensions->isEnabled($id);
            }

            $out[$key] = $title === null
                ? ['enabled' => $enabled]
                : ['enabled' => $enabled, 'title' => $title, 'iconName' => $icon, 'iconColor' => '#ffffff', 'iconBg' => $bg];
        }

        return $out;
    }

    private function setting(string $key, string $fallback): string
    {
        $value = $this->settings->get('ernestdefoe-digest-mail.'.$key);

        return $value === null || $value === '' ? $fallback : (string) $value;
    }
}
