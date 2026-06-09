import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Modal from 'flarum/common/components/Modal';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';

type Frequency = 'daily' | 'weekly' | 'monthly';

const FREQ_LABELS: Record<Frequency, string> = {
  daily: 'ernestdefoe-digest-mail.forum.settings.frequency_daily',
  weekly: 'ernestdefoe-digest-mail.forum.settings.frequency_weekly',
  monthly: 'ernestdefoe-digest-mail.forum.settings.frequency_monthly',
};

const trans = (key: string): Mithril.Children => app.translator.trans(key);

/**
 * One-time onboarding modal that lets a new user pick a digest frequency.
 * The pending flag is cleared on any interaction (including the X button) so
 * the modal never shows more than once.
 */
class DigestOptInModal extends Modal {
  className(): string {
    return 'DigestOptInModal Modal--small';
  }

  title(): Mithril.Children {
    return trans('ernestdefoe-digest-mail.forum.onboarding.modal_title');
  }

  // Override hide() so closing via the X button also clears the flag. Without
  // this the X calls hide() directly, the flag stays set, and the modal
  // reappears on every page load.
  hide(): void {
    const user = app.session.user;
    if (user) {
      const prefs: Record<string, unknown> = user.preferences() || {};
      if (prefs.digest_onboarding_pending) {
        prefs.digest_onboarding_pending = null;
        user.save({ preferences: prefs });
      }
    }
    super.hide();
  }

  content(): Mithril.Children {
    const user = app.session.user;
    if (!user) return null;

    const allowed: Partial<Record<Frequency, boolean>> =
      app.forum.attribute('digestAllowedFrequencies') || {};

    // Picking a frequency (or dismissing with `null`) clears the pending flag
    // and persists the choice, then closes the modal regardless of outcome.
    const choose = (frequency: Frequency | null): void => {
      const prefs: Record<string, unknown> = user.preferences() || {};
      prefs.digest_onboarding_pending = null;
      user
        .save({ digestFrequency: frequency, preferences: prefs })
        .then(() => this.hide(), () => this.hide());
    };

    const buttons = (['daily', 'weekly', 'monthly'] as Frequency[])
      .filter((f) => !!allowed[f])
      .map((f) =>
        m(
          'button',
          {
            className: 'Button Button--primary',
            onclick: (e: Event) => {
              e.preventDefault();
              choose(f);
            },
          },
          trans(FREQ_LABELS[f])
        )
      );

    return m(
      'div',
      { className: 'Modal-body', style: 'padding:20px;' },
      m('p', { style: 'margin-bottom:16px;' }, trans('ernestdefoe-digest-mail.forum.onboarding.modal_body')),
      m('div', { style: 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;justify-content:center;' }, buttons),
      m(
        'button',
        {
          className: 'Button Button--text',
          style: 'display:block;width:100%;margin-top:8px;',
          onclick: (e: Event) => {
            e.preventDefault();
            choose(null);
          },
        },
        trans('ernestdefoe-digest-mail.forum.onboarding.modal_dismiss')
      )
    );
  }
}

interface DigestFrequencyAttrs {
  user: User;
}

/**
 * Inline "Email digest" frequency selector injected into the user's settings
 * page. Saves immediately on change and shows a transient ✓ / error state.
 */
class DigestFrequencySetting extends Component<DigestFrequencyAttrs> {
  saving = false;
  saved = false;
  error: string | null = null;

  view(): Mithril.Children {
    const user = this.attrs.user;
    const value = (user.attribute('digestFrequency') as string) || 'off';
    const allowed: Record<string, boolean> =
      app.forum.attribute('digestAllowedFrequencies') || { daily: false, weekly: true, monthly: true };
    const effectiveValue = value !== 'off' && !allowed[value] ? 'off' : value;

    const options: Mithril.Children[] = [
      m('option', { value: 'off' }, trans('ernestdefoe-digest-mail.forum.settings.frequency_off')),
    ];
    if (allowed.daily) options.push(m('option', { value: 'daily' }, trans('ernestdefoe-digest-mail.forum.settings.frequency_daily')));
    if (allowed.weekly) options.push(m('option', { value: 'weekly' }, trans('ernestdefoe-digest-mail.forum.settings.frequency_weekly')));
    if (allowed.monthly) options.push(m('option', { value: 'monthly' }, trans('ernestdefoe-digest-mail.forum.settings.frequency_monthly')));

    return m(
      'div',
      { class: 'Form-group' },
      m('label', { class: 'label', for: 'ernestdefoe-digest-mail-frequency' }, trans('ernestdefoe-digest-mail.forum.settings.digest_label')),
      m('div', { class: 'helpText' }, trans('ernestdefoe-digest-mail.forum.settings.digest_help')),
      m(
        'div',
        { style: 'display:flex;align-items:center;gap:10px;margin-top:8px;' },
        m(
          'select',
          {
            id: 'ernestdefoe-digest-mail-frequency',
            class: 'FormControl',
            style: 'padding-top:6px;padding-bottom:8px;height:auto;line-height:1.5;',
            disabled: this.saving,
            value: effectiveValue,
            onchange: (e: Event) => this.save(user, (e.target as HTMLSelectElement).value),
          },
          options
        ),
        this.saving ? m('span', { class: 'LoadingIndicator', 'aria-hidden': 'true' }) : null,
        this.saved && !this.saving
          ? m('span', { style: 'color:var(--control-success-color,#3d8b3d);font-size:13px;' }, '✓ ' + trans('ernestdefoe-digest-mail.forum.settings.saved'))
          : null
      ),
      this.error ? m('div', { class: 'Alert Alert--error', style: 'margin-top:8px;padding:8px 12px;font-size:13px;' }, this.error) : null
    );
  }

  save(user: User, value: string): void {
    const frequency = value === 'off' ? null : value;
    this.saving = true;
    this.saved = false;
    this.error = null;
    m.redraw();

    user
      .save({ digestFrequency: frequency })
      .then(() => {
        this.saving = false;
        this.saved = true;
        m.redraw();
        setTimeout(() => {
          this.saved = false;
          m.redraw();
        }, 3000);
      })
      .catch((e: { message?: string }) => {
        this.saving = false;
        this.error = (e && e.message) || (trans('ernestdefoe-digest-mail.forum.settings.save_error') as string);
        m.redraw();
      });
  }
}

app.initializers.add('ernestdefoe-digest-mail', () => {
  // NOTE: the first argument is intentionally the component's module-path
  // string rather than its prototype. This is preserved verbatim from the
  // original shipped behaviour — do not "fix" it to a prototype reference
  // without re-validating the settings-page injection end to end.
  extend(
    'flarum/forum/components/SettingsPage' as unknown as Record<string, unknown>,
    'notificationsItems',
    function (this: { user?: User }, items: { add: (key: string, content: Mithril.Children, priority?: number) => void }) {
      const user = this.user;
      if (!user || !app.session.user || user.id() !== app.session.user.id()) return;
      items.add('digestFrequency', m(DigestFrequencySetting, { user }), 50);
    }
  );

  // Opt-in modal boot hook: on page load, if the current user still has the
  // digest_onboarding_pending preference set, open the opt-in modal once.
  setTimeout(() => {
    const user = app.session.user;
    if (!user) return;
    const prefs = user.preferences() as Record<string, unknown> | null;
    if (!prefs || prefs.digest_onboarding_pending !== true) return;
    app.modal.show(DigestOptInModal);
  }, 0);
});
