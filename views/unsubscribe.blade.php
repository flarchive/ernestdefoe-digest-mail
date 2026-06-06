@extends('flarum.forum::layouts.basic')
@php
    $forumTitle   = $settings->get('forum_title', 'Forum');

    $weekDayNames   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    $weeklyDayLabel = $weekDayNames[(int) $settings->get('ernestdefoe-digest-mail.weekly_day', 1)] ?? 'Monday';

    $monthlyDayInt = (int) $settings->get('ernestdefoe-digest-mail.monthly_day', 1);
    $suffix = match (true) {
        ($monthlyDayInt % 100 >= 11 && $monthlyDayInt % 100 <= 13) => 'th',
        ($monthlyDayInt % 10 === 1) => 'st',
        ($monthlyDayInt % 10 === 2) => 'nd',
        ($monthlyDayInt % 10 === 3) => 'rd',
        default => 'th',
    };
    $monthlyDayLabel = $monthlyDayInt . $suffix;

    // Only show frequencies the admin has enabled
    $allowDaily   = $settings->get('ernestdefoe-digest-mail.allow_daily',   '0') === '1';
    $allowWeekly  = $settings->get('ernestdefoe-digest-mail.allow_weekly',  '1') === '1';
    $allowMonthly = $settings->get('ernestdefoe-digest-mail.allow_monthly', '1') === '1';

    $options = [];
    if ($allowDaily)   $options[] = ['value' => 'daily',   'emoji' => '☀️', 'title' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.daily_title'),   'desc' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.daily_desc')];
    if ($allowWeekly)  $options[] = ['value' => 'weekly',  'emoji' => '📅', 'title' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.weekly_title'),  'desc' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.weekly_desc', ['{day}' => $weeklyDayLabel])];
    if ($allowMonthly) $options[] = ['value' => 'monthly', 'emoji' => '📆', 'title' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.monthly_title'), 'desc' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.monthly_desc', ['{day}' => $monthlyDayLabel])];
    $options[] =                    ['value' => 'off',     'emoji' => '🔕', 'title' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.off_title'),     'desc' => $translator->trans('ernestdefoe-digest-mail.unsubscribe.off_desc'), 'off' => true];
@endphp

@section('title', $translator->trans('ernestdefoe-digest-mail.unsubscribe.page_title'))

@section('content')

<style>
    .digest-card {
        background: var(--body-bg, #fff);
        border-radius: 12px;
        padding: 32px 28px;
        box-shadow: 0 2px 16px rgba(0,0,0,.08);
        text-align: left;
        max-width: 440px;
        margin: 0 auto;
        border: 1px solid var(--control-bg, #e5e7eb);
        font-family: var(--font-family, system-ui, -apple-system, sans-serif);
    }
    .digest-card h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--heading-color, var(--text-color, #111827));
        margin: 0 0 8px;
        letter-spacing: -0.2px;
    }
    .digest-card .subtitle {
        font-size: 14px;
        color: var(--muted-color, #6b7280);
        margin: 0 0 6px;
        line-height: 1.5;
    }
    .click-hint {
        font-size: 13px;
        color: var(--muted-color, #9ca3af);
        margin: 0 0 20px;
        font-style: italic;
    }
    .freq-option {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 13px 15px;
        background: var(--control-bg, #f3f4f6);
        border: 2px solid var(--control-bg, #e5e7eb);
        border-radius: 8px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        text-decoration: none;
        color: var(--text-color, #111827);
    }
    .freq-option:hover {
        border-color: var(--primary-color, #4f46e5);
        text-decoration: none;
        color: var(--text-color, #111827);
    }
    .freq-option.is-selected {
        border-color: var(--primary-color, #4f46e5);
        background: var(--body-bg, #fff);
    }
    .freq-label { flex: 1; min-width: 0; }
    .freq-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--heading-color, var(--text-color, #111827));
        display: block;
        margin-bottom: 2px;
    }
    .freq-option.is-selected .freq-title {
        color: var(--primary-color, #4f46e5);
    }
    .freq-desc {
        font-size: 13px;
        color: var(--muted-color, #6b7280);
        line-height: 1.4;
    }
    .freq-option.is-off {
        border-style: dashed;
    }
    .check-icon {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
        margin-top: 2px;
        color: var(--primary-color, #4f46e5);
        visibility: hidden;
    }
    .freq-option.is-selected .check-icon {
        visibility: visible;
    }
</style>

<div class="digest-card">

    <h2>📧 {{ $translator->trans('ernestdefoe-digest-mail.unsubscribe.heading') }}</h2>
    <p class="subtitle">
        {!! $translator->trans('ernestdefoe-digest-mail.unsubscribe.greeting', ['{name}' => '<strong>' . e($user->display_name) . '</strong>', '{forum}' => '<strong>' . e($forumTitle) . '</strong>']) !!}
    </p>
    <p class="click-hint">👆 {{ $translator->trans('ernestdefoe-digest-mail.unsubscribe.click_hint') }}</p>

    @foreach ($options as $option)
    <a href="{{ $postUrl }}{{ $option['value'] }}"
       class="freq-option{{ $currentFrequency === $option['value'] || ($option['value'] === 'off' && $currentFrequency === null) ? ' is-selected' : '' }}{{ !empty($option['off']) ? ' is-off' : '' }}">
        <span class="freq-label">
            <span class="freq-title">{{ $option['emoji'] }} {{ $option['title'] }}</span>
            <span class="freq-desc">{{ $option['desc'] }}</span>
        </span>
        <svg class="check-icon" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd"/>
        </svg>
    </a>
    @endforeach

</div>

@endsection
