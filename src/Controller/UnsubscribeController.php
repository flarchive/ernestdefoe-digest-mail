<?php

namespace Resofire\DigestMail\Controller;

use Resofire\DigestMail\Token\UnsubscribeToken;
use Flarum\Http\Controller\AbstractHtmlController;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles GET /digest/unsubscribe?token=...
 *
 * Without &frequency=...: renders the preference picker.
 * With    &frequency=...: saves the preference and redirects to ?saved=1.
 *
 * GET-only, no form POST — sidesteps CSRF entirely. The signed token
 * authenticates the action.
 */
class UnsubscribeController extends AbstractHtmlController
{
    private const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function __construct(
        private ViewFactory                 $view,
        private UrlGenerator                $url,
        private SettingsRepositoryInterface $settings,
        private Translator                  $translator,
    ) {}

    /**
     * Override handle() so we can return a RedirectResponse when a frequency
     * is submitted, rather than being forced into an HtmlResponse.
     */
    public function handle(Request $request): ResponseInterface
    {
        $params    = $request->getQueryParams();
        $rawToken  = Arr::get($params, 'token', '');
        $saved     = (bool) Arr::get($params, 'saved', false);
        $frequency = Arr::get($params, 'frequency');

        // Show saved confirmation — no token needed at this point.
        if ($saved) {
            return parent::handle($request);
        }

        // If a frequency param is present, save and redirect.
        if ($frequency !== null) {
            $token = UnsubscribeToken::findValid($rawToken);

            if ($token === null) {
                return parent::handle($request); // will render invalid-token view
            }

            $normalized = ($frequency === 'off' || $frequency === '')
                ? null
                : (in_array($frequency, self::VALID_FREQUENCIES, true) ? $frequency : null);

            User::where('id', $token->user_id)->update([
                'digest_frequency' => $normalized,
            ]);

            $token->delete();

            $savedUrl = $this->url->to('forum')->route('resofire.digest-mail.unsubscribe')
                . '?saved=1';

            return new RedirectResponse($savedUrl);
        }

        // No frequency — show the picker.
        return parent::handle($request);
    }

    protected function render(Request $request): Renderable|string
    {
        $params   = $request->getQueryParams();
        $rawToken = Arr::get($params, 'token', '');
        $saved    = (bool) Arr::get($params, 'saved', false);

        if ($saved) {
            return $this->view->make('ernestdefoe-digest-mail::unsubscribe-saved')
                ->with('forumUrl',    $this->url->to('forum')->base())
                ->with('settings',    $this->settings)
                ->with('translator',  $this->translator);
        }

        $token = UnsubscribeToken::findValid($rawToken);

        if ($token === null) {
            return $this->view->make('ernestdefoe-digest-mail::unsubscribe-invalid')
                ->with('forumUrl',    $this->url->to('forum')->base())
                ->with('settings',    $this->settings)
                ->with('translator',  $this->translator);
        }

        $user    = $token->user;
        $baseUrl = $this->url->to('forum')->route('resofire.digest-mail.unsubscribe')
            . '?token=' . urlencode($rawToken) . '&frequency=';

        return $this->view->make('ernestdefoe-digest-mail::unsubscribe')
            ->with('user',             $user)
            ->with('currentFrequency', $user->digest_frequency)
            ->with('token',            $rawToken)
            ->with('postUrl',          $baseUrl)
            ->with('settings',         $this->settings)
            ->with('translator',       $this->translator);
    }
}
