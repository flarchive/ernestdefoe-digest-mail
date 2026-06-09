<?php

namespace Resofire\DigestMail;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Flarum\Locale\Translator;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

class DigestMailer
{
    public function __construct(
        private Mailer                      $mailer,
        private UrlGenerator                $url,
        private SettingsRepositoryInterface $settings,
        private Translator                  $translator,
    ) {}

    /**
     * Resolve the effective email theme for a user.
     *
     * Uses Flarum 2.x's built-in color scheme system:
     *
     *   Forum setting 'color_scheme' (set by admin in Appearance):
     *     'light'    → force light for all emails
     *     'dark'     → force dark for all emails
     *     'light-hc' → treat as light
     *     'dark-hc'  → treat as dark
     *     'auto'     → defer to the user's own preference
     *
     *   When the forum is set to 'auto', the user preference 'colorScheme'
     *   is checked with the same value mapping. If the user preference is
     *   also 'auto' or unset, we return 'auto' (the Blade template renders
     *   a self-contained light email in that case).
     *
     * A $themeOverride of 'light' or 'dark' bypasses all of the above
     * (used by the admin test-send panel).
     */
    public function resolveTheme(User $user, ?string $themeOverride = null): string
    {
        if ($themeOverride === 'light' || $themeOverride === 'dark') {
            return $themeOverride;
        }

        $forumScheme = $this->settings->get('color_scheme', 'auto');

        // If the admin has locked the forum to a specific scheme, honour it.
        if ($forumScheme !== 'auto') {
            return $this->schemeToTheme($forumScheme);
        }

        // Forum is set to auto — use the individual user's preference.
        $userScheme = $user->getPreference('colorScheme', 'auto');

        return $this->schemeToTheme($userScheme);
    }

    /**
     * Map a Flarum 2.x color scheme value to the email theme string.
     *
     * 'light' and 'light-hc' → 'light'
     * 'dark'  and 'dark-hc'  → 'dark'
     * anything else (including 'auto') → 'auto'
     */
    private function schemeToTheme(mixed $scheme): string
    {
        return match ((string) $scheme) {
            'light', 'light-hc' => 'light',
            'dark',  'dark-hc'  => 'dark',
            default             => 'auto',
        };
    }

    /**
     * Derive dark-mode background and surface colors from the forum's
     * secondary color, mirroring what Night Mode's LESS compilation does.
     *
     * Flarum's dark theme desaturates the secondary color heavily and
     * uses it as the base for background hues. We approximate this with
     * a simple HSL calculation rather than running the LESS compiler.
     */
    public static function darkColorsFromSecondary(string $hex): array
    {
        // Parse hex → RGB
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        // RGB → HSL
        $max  = max($r, $g, $b);
        $min  = min($r, $g, $b);
        $l    = ($max + $min) / 2;
        $s    = 0;
        $h    = 0;
        $diff = $max - $min;

        if ($diff > 0) {
            $s = $diff / (1 - abs(2 * $l - 1));
            if ($max === $r) $h = 60 * fmod(($g - $b) / $diff, 6);
            elseif ($max === $g) $h = 60 * (($b - $r) / $diff + 2);
            else                  $h = 60 * (($r - $g) / $diff + 4);
            if ($h < 0) $h += 360;
        }

        // Build dark palette — desaturate and darken significantly
        $hslToHex = function (float $h, float $s, float $l): string {
            $s = max(0, min(1, $s));
            $l = max(0, min(1, $l));
            $c = (1 - abs(2 * $l - 1)) * $s;
            $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
            $m = $l - $c / 2;
            if ($h < 60)       [$r,$g,$b] = [$c,$x,0];
            elseif ($h < 120)  [$r,$g,$b] = [$x,$c,0];
            elseif ($h < 180)  [$r,$g,$b] = [0,$c,$x];
            elseif ($h < 240)  [$r,$g,$b] = [0,$x,$c];
            elseif ($h < 300)  [$r,$g,$b] = [$x,0,$c];
            else               [$r,$g,$b] = [$c,0,$x];
            return sprintf('#%02x%02x%02x',
                round(($r+$m)*255), round(($g+$m)*255), round(($b+$m)*255));
        };

        return [
            // Page background — very dark, slight hue tint
            'bg'      => $hslToHex($h, min($s * 0.3, 0.15), 0.10),
            // Card/surface background
            'surface' => $hslToHex($h, min($s * 0.25, 0.12), 0.15),
            // Slightly lighter surface for alternating rows
            'surface2'=> $hslToHex($h, min($s * 0.2,  0.10), 0.19),
            // Border color
            'border'  => $hslToHex($h, min($s * 0.2,  0.10), 0.22),
            // Primary text
            'text'    => '#e5e7eb',
            // Secondary text
            'textMuted' => '#9ca3af',
        ];
    }

    public function sendToUser(User $user, DigestContent $content, string $unsubscribeToken): void
    {
        $originalLocale = $this->translator->getLocale();
        $userLocale     = $user->getPreference('locale') ?? $this->settings->get('default_locale');
        $this->translator->setLocale($userLocale);

        try {
            $unsubscribeUrl = $this->url->to('forum')->route('resofire.digest-mail.unsubscribe')
                . '?token=' . urlencode($unsubscribeToken);

            $forumTitle  = $this->settings->get('forum_title', 'Forum');
            $subject     = $this->translator->trans(
                'ernestdefoe-digest-mail.email.subject',
                ['{forum}' => $forumTitle, '{frequency}' => $content->frequencyLabel()]
            );

            $fromAddress = $this->settings->get('mail_from', 'noreply@' . parse_url($this->url->to('forum')->base(), PHP_URL_HOST));
            $fromName    = $this->settings->get('mail_from_name', $forumTitle);

            $secondaryHex = $this->settings->get('theme_secondary_color', '#4f46e5');
            $darkColors   = self::darkColorsFromSecondary($secondaryHex);

            $viewData = [
                'content'        => $content,
                'user'           => $user,
                'forumTitle'     => $forumTitle,
                'forumUrl'       => $this->url->to('forum')->base(),
                'unsubscribeUrl' => $unsubscribeUrl,
                'url'            => $this->url,
                'darkColors'     => $darkColors,
                'translator'     => $this->translator,
            ];

            $this->mailer->send(
                'ernestdefoe-digest-mail::emails.digest',
                $viewData,
                function (Message $message) use ($user, $subject, $fromAddress, $fromName) {
                    $message
                        ->from($fromAddress, $fromName)
                        ->to($user->email, $user->display_name)
                        ->subject($subject);
                }
            );
        } finally {
            $this->translator->setLocale($originalLocale);
        }
    }
}
