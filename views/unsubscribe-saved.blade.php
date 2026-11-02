@extends('flarum.forum::layouts.basic')
@php
    $forumTitle = $settings->get('forum_title', 'Forum');
@endphp

@section('title', $translator->trans('ernestdefoe-digest-mail.unsubscribe_saved.page_title'))

@section('content')

<style>
    .digest-card {
        background: var(--body-bg, #fff);
        border-radius: 12px;
        padding: 36px 28px;
        box-shadow: 0 2px 16px rgba(0,0,0,.08);
        max-width: 440px;
        margin: 0 auto;
        text-align: center;
        border: 1px solid var(--control-bg, #e5e7eb);
        font-family: var(--font-family, system-ui, -apple-system, sans-serif);
    }
    .digest-card .icon { font-size: 40px; margin-bottom: 16px; }
    .digest-card h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--heading-color, var(--text-color, #111827));
        margin: 0 0 10px;
    }
    .digest-card p {
        font-size: 14px;
        color: var(--muted-color, #6b7280);
        margin: 0 0 16px;
        line-height: 1.6;
    }
    .digest-card a {
        color: var(--primary-color, #4f46e5);
        text-decoration: none;
        font-weight: 500;
    }
    .digest-card a:hover { text-decoration: underline; }
</style>

<div class="digest-card">
    <div class="icon">✅</div>
    <h2>{{ $translator->trans('ernestdefoe-digest-mail.unsubscribe_saved.heading') }}</h2>
    <p>{{ $translator->trans('ernestdefoe-digest-mail.unsubscribe_saved.body') }}</p>
    <p><a href="{{ $forumUrl }}">{!! $translator->trans('ernestdefoe-digest-mail.unsubscribe_saved.return_link', ['{forum}' => e($settings->get('forum_title', 'Forum'))]) !!}</a></p>
</div>

@endsection
