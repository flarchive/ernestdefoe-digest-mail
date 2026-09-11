@php
    $primaryColor = $settings->get('theme_primary_color', '#4f46e5');
    $logoPath     = $settings->get('logo_path');
    $logoUrl      = $logoPath ? $url->to('forum')->path('assets/' . $logoPath) : null;
    $year         = date('Y');
    $theme        = $content->theme;

    $dk = $darkColors ?? [
        'bg'        => '#111827', 'surface'   => '#1f2937',
        'surface2'  => '#263042', 'border'    => '#2d3748',
        'text'      => '#e5e7eb', 'textMuted' => '#9ca3af',
    ];
    $lt = [
        'bg'        => '#f4f4f5', 'surface'   => '#ffffff',
        'surface2'  => '#f9fafb', 'border'    => '#e5e7eb',
        'text'      => '#111827', 'textMuted' => '#6b7280',
    ];
    $c = ($theme === 'dark') ? $dk : $lt;

    // Deterministic avatar colour from username
    $avatarColor = function ($u) {
        $colors = ['#4f46e5','#7c3aed','#db2777','#dc2626','#d97706',
                   '#059669','#0284c7','#0891b2','#65a30d','#9333ea'];
        return $colors[abs(crc32($u->username)) % count($colors)];
    };

    // Avatar helper
    $renderAvatar = function ($u, int $size = 40, int $fsize = 15) use ($avatarColor) {
        $initial = strtoupper(mb_substr($u->display_name ?? $u->username, 0, 1));
        $color   = $avatarColor($u);
        if ($u->avatar_url) {
            return '<img src="' . e($u->avatar_url) . '" alt="" width="' . $size . '" height="' . $size . '"'
                 . ' style="border-radius:50%; display:block; width:' . $size . 'px; height:' . $size . 'px;" />';
        }
        return '<div style="width:' . $size . 'px; height:' . $size . 'px; border-radius:50%;'
             . ' background-color:' . $color . '; display:inline-block; text-align:center;'
             . ' line-height:' . $size . 'px; font-size:' . $fsize . 'px;'
             . ' font-weight:600; color:#fff;">' . $initial . '</div>';
    };

    // Badge circle — email-safe: renders the badge background colour with the
    // first letter of the badge name, since Font Awesome icons don't load in email clients.
    $badgeCircle = function ($badge, int $size = 44) {
        $initial = strtoupper(mb_substr($badge->name, 0, 1));
        $bg      = e($badge->background_color ?: '#6b7280');
        $color   = e($badge->icon_color       ?: '#ffffff');
        $fsize   = (int) round($size * 0.40);
        return '<div style="width:' . $size . 'px; height:' . $size . 'px; border-radius:50%;'
             . ' background-color:' . $bg . '; display:inline-block; text-align:center;'
             . ' line-height:' . $size . 'px; font-size:' . $fsize . 'px;'
             . ' font-weight:700; color:' . $color . ';">' . $initial . '</div>';
    };

    $periodEnd = $content->periodStart->copy()->add(
        $content->frequency === 'daily'  ? '1 day'  :
        ($content->frequency === 'weekly' ? '1 week' : '1 month')
    );

    $lb         = $content->leaderboard ?? [];
    $lbEnabled  = !empty($lb) && ($lb['enabled'] ?? false);
    $lbEntries  = $lbEnabled ? ($lb['entries'] ?? []) : [];
    $lbMover    = $lbEnabled ? ($lb['biggestMover'] ?? null) : null;
    $lbTop3     = array_slice($lbEntries, 0, 3);
    $lbRest     = array_slice($lbEntries, 3);

    $bdg           = $content->badges ?? [];
    $bdgEnabled    = !empty($bdg) && ($bdg['enabled'] ?? false);
    $bdgEarners    = $bdgEnabled ? ($bdg['recentEarners'] ?? []) : [];
    $bdgMostEarned = $bdgEnabled ? ($bdg['mostEarned'] ?? null) : null;
    $bdgRarest     = $bdgEnabled ? ($bdg['rarest'] ?? null) : null;

    $pk            = $content->pickem ?? [];
    $pkEnabled     = !empty($pk) && ($pk['enabled'] ?? false);
    $pkUpcoming    = $pkEnabled ? ($pk['upcomingEvents'] ?? []) : [];
    $pkResults     = $pkEnabled ? ($pk['recentResults']  ?? []) : [];
    $pkLeaderboard = $pkEnabled ? ($pk['leaderboard']    ?? []) : [];
    $pkForumUrl    = rtrim($forumUrl, '/') . '/pickem';

    $pks                 = $content->picks ?? [];
    $pksEnabled          = !empty($pks) && ($pks['enabled'] ?? false);
    $pksConfidenceMode   = $pksEnabled ? ($pks['confidenceMode']   ?? false) : false;
    $pksCurrentWeek      = $pksEnabled ? ($pks['currentWeek']      ?? null)  : null;
    $pksUpcoming         = $pksEnabled ? ($pks['upcomingEvents']   ?? [])    : [];
    $pksResults          = $pksEnabled ? ($pks['recentResults']    ?? [])    : [];
    $pksLeaderboard      = $pksEnabled ? ($pks['leaderboard']      ?? [])    : [];
    $pksLeaderboardLabel = $pksEnabled ? ($pks['leaderboardLabel'] ?? '')    : '';
    $pksForumUrl         = $pksEnabled ? ($pks['picksForumUrl']    ?? '')    : '';

    $gv              = $content->giveaways ?? [];
    $gvEnabled       = !empty($gv) && ($gv['enabled'] ?? false);
    $gvEndingSoon    = $gvEnabled ? ($gv['endingSoon']    ?? []) : [];
    $gvRecentWinners = $gvEnabled ? ($gv['recentWinners'] ?? []) : [];
    $gvForumUrl      = $gvEnabled ? ($gv['forumUrl']      ?? (rtrim($forumUrl, '/') . '/giveaways')) : '';

    $gp              = $content->gamepedia ?? [];
    $gpEnabled       = !empty($gp) && ($gp['enabled'] ?? false);
    $gpMostDiscussed = $gpEnabled ? ($gp['mostDiscussed'] ?? []) : [];
    $gpNewGames      = $gpEnabled ? ($gp['newGames']      ?? []) : [];
    $gpForumUrl      = rtrim($forumUrl, '/') . '/gamepedia';

    $rgp             = $content->resofireGamepedia ?? [];
    $rgpEnabled      = !empty($rgp) && ($rgp['enabled'] ?? false);
    $rgpMostDiscussed= $rgpEnabled ? ($rgp['mostDiscussed'] ?? []) : [];
    $rgpNewGames     = $rgpEnabled ? ($rgp['newGames']      ?? []) : [];
    $rgpTopGenres    = $rgpEnabled ? ($rgp['topGenres']     ?? []) : [];
    $rgpForumUrl     = rtrim($forumUrl, '/') . '/gamepedia';

    $favEntries      = $content->favorites ?? [];

    $aw              = $content->awards ?? [];
    $awEnabled       = !empty($aw) && ($aw['enabled'] ?? false);
    $awAwards        = $awEnabled ? ($aw['awards'] ?? []) : [];
    $awForumUrl      = rtrim($forumUrl, '/') . '/awards';

    // huseyinfiliz/gamepedia cover URL — built from IGDB cover_image_id
    $gpCoverUrl = function (?string $imageId) : ?string {
        if (!$imageId) return null;
        return 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $imageId . '.jpg';
    };

    // resofire/gamepedia cover URL — stored directly as a full URL on the game row
    $rgpCoverUrl = function (mixed $game) : ?string {
        return !empty($game->cover_image_url) ? $game->cover_image_url : null;
    };

    $periodWord = $translator->trans('ernestdefoe-digest-mail.email.period.' . $content->frequency);

    /*
     * The digest heading, per frequency.
     *
     * A daily digest used to read "What's been happening this day", because
     * the heading interpolated the same {period} word that strings like
     * "+{points} this {period}" need. Each frequency now gets its own
     * sentence.
     *
     * Falls back to the generic "this {period}" heading when a locale has not
     * translated the specific key — trans() hands back the key itself when it
     * has no translation, which would otherwise print the raw key to the
     * reader. Same guard the mailer uses for the frequency label.
     */
    $headingKey  = 'ernestdefoe-digest-mail.email.heading_' . $content->frequency;
    $headingText = $translator->trans($headingKey);
    if ($headingText === $headingKey) {
        $headingText = $translator->trans(
            'ernestdefoe-digest-mail.email.heading',
            ['{period}' => $periodWord]
        );
    }

    // Team logo + name helper
    $renderTeam = function ($team, string $align = 'left') use ($c) {
        $name = e($team->name);
        $logo = $team->logo_path ?? null;
        $nameSpan = '<span style="font-size:15px; font-weight:500; color:' . $c['text'] . '; display:block; margin-top:6px;">' . $name . '</span>';
        if (!$logo) return $nameSpan;
        $img = '<img src="' . e($logo) . '" alt="' . $name . '" width="72" height="72"'
             . ' style="width:72px; height:72px; object-fit:contain; display:block; border-radius:6px;" />';
        return $img . $nameSpan;
    };

    // Section header helper
    $sectionHeader = function (string $label) use ($primaryColor) {
        return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">'
             . '<tr><td style="text-align:center; font-size:18px; font-weight:700; letter-spacing:2px; text-transform:uppercase;'
             . ' color:' . $primaryColor . '; padding-bottom:12px;">'
             . $label . '</td></tr></table>';
    };

    // Section divider — option C: short centred bar in primary color
    $sectionDivider = function () use ($primaryColor) {
        return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:40px 0 32px;">'
             . '<tr><td style="text-align:center; font-size:0; line-height:0;">'
             . '<div style="display:inline-block; width:48px; height:2px; background-color:' . $primaryColor . '; border-radius:2px;">&nbsp;</div>'
             . '</td></tr></table>';
    };

    // Medal colours for podium
    $medals = [
        1 => ['emoji' => '🥇', 'bg' => '#fffbeb', 'border' => '#f5d96b'],
        2 => ['emoji' => '🥈', 'bg' => '#f8f8f8', 'border' => '#d8d8d8'],
        3 => ['emoji' => '🥉', 'bg' => '#fff6f2', 'border' => '#f0c4a8'],
    ];
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="{{ $theme === 'auto' ? 'light dark' : $theme }}" />
    <meta name="supported-color-schemes" content="{{ $theme === 'auto' ? 'light dark' : $theme }}" />
    <title>{{ $translator->trans('ernestdefoe-digest-mail.email.document_title', ['{forum}' => $forumTitle, '{frequency}' => $frequencyLabel]) }}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }
        a { text-decoration:none; }
        @media only screen and (max-width:620px) {
            .wrapper  { width:100% !important; }
            .pad      { padding-left:24px !important; padding-right:24px !important; }
            .podium-cell { display:block !important; width:100% !important; padding:0 0 10px 0 !important; }
        }
        @if ($theme === 'auto')
        @media (prefers-color-scheme: dark) {
            .body-bg    { background-color:{{ $dk['bg']      }} !important; }
            .card       { background-color:{{ $dk['surface']  }} !important; }
            .card-foot  { background-color:{{ $dk['surface2'] }} !important; border-top-color:{{ $dk['border'] }} !important; }
            .t-main     { color:{{ $dk['text']      }} !important; }
            .t-muted    { color:{{ $dk['textMuted'] }} !important; }
            .t-strong   { color:{{ $dk['text']      }} !important; }
            .row-border { border-bottom-color:{{ $dk['border'] }} !important; }
            .surface2   { background-color:{{ $dk['surface2'] }} !important; }
            .card-tint  { background-color:{{ $dk['surface2'] }} !important; border-color:{{ $dk['border'] }} !important; }
            .score-bg   { background-color:{{ $dk['surface2'] }} !important; border-color:{{ $dk['border'] }} !important; }
            .vs-bg      { background-color:{{ $dk['surface2'] }} !important; border-color:{{ $dk['border'] }} !important; }
        }
        @endif
    </style>
</head>
<body class="body-bg" style="margin:0; padding:0; background-color:{{ $c['bg'] }}; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:{{ $c['text'] }};">

<table class="body-bg" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['bg'] }};"><tr><td align="center" style="padding:36px 16px;">
<table class="wrapper card" width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface'] }}; border-radius:12px; overflow:hidden; box-shadow:0 1px 6px rgba(0,0,0,.10);">

{{-- ── HEADER ───────────────────────────────────────────────────────────── --}}
<tr>
    <td class="pad" style="padding:36px 48px 30px; text-align:center; border-bottom:3px solid {{ $primaryColor }};">
        @if ($logoUrl)
            <a href="{{ $forumUrl }}" style="text-decoration:none; display:block;">
                <img src="{{ $logoUrl }}" alt="{{ $forumTitle }}" height="52" style="max-height:52px; max-width:240px; display:block; margin:0 auto;" />
            </a>
        @else
            <a href="{{ $forumUrl }}" style="color:{{ $primaryColor }}; font-size:26px; font-weight:600; text-decoration:none; letter-spacing:-0.5px;">{{ $forumTitle }}</a>
        @endif
        <p style="margin:10px 0 0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{-- 🚨 Do NOT uppercase this in PHP.

         Flarum's mail pipeline replaces every translation PARAMETER with an
         opaque marker — flarumsafevalue<hex>endflarumsafevalue — and puts the
         real value back after the view has rendered (Flarum\Mail\MutateEmail,
         via SafeSubstitution::restore). That restore matches the marker
         case-sensitively, with no /i flag.

         mb_strtoupper() therefore turned the marker into
         FLARUMSAFEVALUE4461696C79ENDFLARUMSAFEVALUE, which no longer matched,
         so subscribers received the raw marker under the logo instead of
         "Daily Digest" — 4461696c79 being the hex for "Daily".

         The uppercasing is already handled by text-transform on this element,
         which is how every other badge in this template does it. --}}
      {{ $translator->trans('ernestdefoe-digest-mail.email.header_badge', ['{frequency}' => $frequencyLabel]) }}</p>
    </td>
</tr>

{{-- ── TITLE + DATE ──────────────────────────────────────────────────────── --}}
<tr>
    <td class="pad" style="padding:30px 48px 26px; text-align:center; border-bottom:0.5px solid {{ $c['border'] }};">
        <h1 class="t-main" style="margin:0 0 10px; font-size:26px; font-weight:600; color:{{ $c['text'] }}; letter-spacing:-0.5px; line-height:1.3;">{{ $headingText }}</h1>
        <p class="t-muted" style="margin:0; font-size:15px; color:{{ $c['textMuted'] }};">{{ $content->periodStart->format('F j') }} – {{ $periodEnd->format('F j, Y') }}</p>
    </td>
</tr>

{{-- ── STATS BAR ─────────────────────────────────────────────────────────── --}}
@if (!empty($content->stats))
<tr>
    <td style="background-color:{{ $primaryColor }}; padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
            <td width="25%" style="padding:18px 8px; text-align:center; border-right:1px solid rgba(255,255,255,.2);">
                <span style="display:block; font-size:26px; font-weight:600; color:#fff; line-height:1; margin-bottom:5px;">{{ number_format($content->stats['posts']) }}</span>
                <span style="display:block; font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.75);">{{ $translator->trans('ernestdefoe-digest-mail.email.stats.posts') }}</span>
            </td>
            <td width="25%" style="padding:18px 8px; text-align:center; border-right:1px solid rgba(255,255,255,.2);">
                <span style="display:block; font-size:26px; font-weight:600; color:#fff; line-height:1; margin-bottom:5px;">{{ number_format($content->stats['discussions']) }}</span>
                <span style="display:block; font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.75);">{{ $translator->trans('ernestdefoe-digest-mail.email.stats.discussions') }}</span>
            </td>
            <td width="25%" style="padding:18px 8px; text-align:center; border-right:1px solid rgba(255,255,255,.2);">
                <span style="display:block; font-size:26px; font-weight:600; color:#fff; line-height:1; margin-bottom:5px;">{{ number_format($content->stats['newMembers']) }}</span>
                <span style="display:block; font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.75);">{{ $translator->trans('ernestdefoe-digest-mail.email.stats.new_members') }}</span>
            </td>
            <td width="25%" style="padding:18px 8px; text-align:center;">
                <span style="display:block; font-size:26px; font-weight:600; color:#fff; line-height:1; margin-bottom:5px;">{{ number_format($content->stats['activeUsers']) }}</span>
                <span style="display:block; font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; color:rgba(255,255,255,.75);">{{ $translator->trans('ernestdefoe-digest-mail.email.stats.active_users') }}</span>
            </td>
        </tr></table>
    </td>
</tr>
@endif

{{-- ── BODY ──────────────────────────────────────────────────────────────── --}}
<tr><td class="pad" style="padding:36px 48px 40px;">

{{-- ── DISCUSSION ROW MACRO ─────────────────────────────────────────────── --}}
@php
$discRow = function ($disc, string $metaHtml) use ($url, $c, $renderAvatar) {
    $href       = $url->to('forum')->route('discussion', ['id' => $disc->id . '-' . $disc->slug]);
    $avatar     = $disc->user ? $renderAvatar($disc->user, 44, 17) : '';
    $avatarCell = $avatar
        ? '<td width="58" style="vertical-align:top; padding-right:16px; padding-top:2px;">' . $avatar . '</td>'
        : '';
    return
        '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>'
        . $avatarCell
        . '<td style="vertical-align:top;">'
        . '<a class="t-main" href="' . $href . '" style="font-size:16px; font-weight:500; color:' . $c['text'] . '; text-decoration:none; line-height:1.4; display:block; margin-bottom:5px;">' . e($disc->title) . '</a>'
        . '<span class="t-muted" style="font-size:14px; color:' . $c['textMuted'] . '; line-height:1.5;">' . $metaHtml . '</span>'
        . '</td></tr></table>';
};
@endphp

{{-- ── FEATURED DISCUSSION ──────────────────────────────────────────────── --}}
@if ($content->featuredDiscussion)
@php
    $fd     = $content->featuredDiscussion;
    $fdHref = $url->to('forum')->route('discussion', ['id' => $fd->id . '-' . $fd->slug]);
    $fdMeta = $fd->comment_count . ' ' . ($fd->comment_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.reply') : $translator->trans('ernestdefoe-digest-mail.email.units.replies'));
    if ($fd->last_posted_at) $fdMeta .= ' &middot; ' . $translator->trans('ernestdefoe-digest-mail.email.meta.last_activity', ['{time_ago}' => e($fd->last_posted_at->diffForHumans())]);
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td class="card-tint" style="background-color:{{ $c['surface2'] }}; border:1.5px solid {{ $primaryColor }}; border-radius:10px; padding:24px 28px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td style="padding-bottom:16px;">
                <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                    @if ($fd->user)
                    <td style="vertical-align:middle; padding-right:12px;">{!! $renderAvatar($fd->user, 44, 17) !!}</td>
                    <td style="vertical-align:middle;">
                        <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $fd->user->display_name }}</span>
                        <span class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.featured.author_label') }}</span>
                    </td>
                    @endif
                    <td style="vertical-align:middle; padding-left:12px;">
                        <span style="font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; background-color:{{ $primaryColor }}; color:#fff; padding:3px 10px; border-radius:20px;">{{ $translator->trans('ernestdefoe-digest-mail.email.featured.badge') }}</span>
                    </td>
                </tr></table>
            </td></tr>
            <tr><td>
                <a href="{{ $fdHref }}" class="t-main" style="font-size:19px; font-weight:600; color:{{ $c['text'] }}; text-decoration:none; line-height:1.4; display:block; margin-bottom:8px;">{{ $fd->title }}</a>
                <p class="t-muted" style="margin:0 0 18px; font-size:14px; color:{{ $c['textMuted'] }};">{!! $fdMeta !!}</p>
                <a href="{{ $fdHref }}" style="display:inline-block; padding:10px 22px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.featured.cta') }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
@endif

@php $sectionOrder = $content->sectionOrder ?: ['discussions','members','stats','leaderboard','badges','pickem','picks','giveaways','gamepedia','favorites','awards']; @endphp
@foreach ($sectionOrder as $__section)
@switch($__section)

@case('discussions')
{{-- ── NEW DISCUSSIONS ───────────────────────────────────────────────────── --}}
@if ($content->newDiscussions->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.new_discussions')) !!}</td></tr>
    @foreach ($content->newDiscussions as $disc)
    @php
        $meta = '';
        if ($disc->user) $meta .= $translator->trans('ernestdefoe-digest-mail.email.meta.started_by', ['{user}' => '<strong class="t-strong" style="font-weight:500; color:' . $c['text'] . ';">' . e($disc->user->display_name) . '</strong>']) . ' &middot; ';
        $meta .= $disc->comment_count . ' ' . ($disc->comment_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.reply') : $translator->trans('ernestdefoe-digest-mail.email.units.replies'));
    @endphp
    <tr><td class="row-border" style="padding:16px 0; border-bottom:0.5px solid {{ $c['border'] }};">{!! $discRow($disc, $meta) !!}</td></tr>
    @endforeach
</table>
@endif

{{-- ── HOT DISCUSSIONS ───────────────────────────────────────────────────── --}}
@if ($content->hotDiscussions->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.active_discussions')) !!}</td></tr>
    @foreach ($content->hotDiscussions as $disc)
    @php
        $meta = $disc->comment_count . ' ' . ($disc->comment_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.reply') : $translator->trans('ernestdefoe-digest-mail.email.units.replies'));
        if ($disc->lastPostedUser) $meta .= ' &middot; ' . $translator->trans('ernestdefoe-digest-mail.email.meta.last_reply_by', ['{user}' => '<strong class="t-strong" style="font-weight:500; color:' . $c['text'] . ';">' . e($disc->lastPostedUser->display_name) . '</strong>']);
        if ($disc->last_posted_at) $meta .= ' &middot; ' . e($disc->last_posted_at->diffForHumans());
    @endphp
    <tr><td class="row-border" style="padding:16px 0; border-bottom:0.5px solid {{ $c['border'] }};">{!! $discRow($disc, $meta) !!}</td></tr>
    @endforeach
</table>
@endif

{{-- ── UNREAD DISCUSSIONS ────────────────────────────────────────────────── --}}
@if ($content->unreadDiscussions->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.unread')) !!}</td></tr>
    @foreach ($content->unreadDiscussions as $disc)
    @php
        $meta = '';
        if ($disc->user) $meta .= $translator->trans('ernestdefoe-digest-mail.email.meta.by', ['{user}' => '<strong class="t-strong" style="font-weight:500; color:' . $c['text'] . ';">' . e($disc->user->display_name) . '</strong>']) . ' &middot; ';
        $meta .= $disc->comment_count . ' ' . ($disc->comment_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.reply') : $translator->trans('ernestdefoe-digest-mail.email.units.replies'));
        $meta .= ' &middot; ' . $translator->trans('ernestdefoe-digest-mail.email.meta.started', ['{time_ago}' => e($disc->created_at->diffForHumans())]);
    @endphp
    <tr><td class="row-border" style="padding:16px 0; border-bottom:0.5px solid {{ $c['border'] }};">{!! $discRow($disc, $meta) !!}</td></tr>
    @endforeach
</table>
@endif
@break

@case('members')
{{-- ── NEW MEMBERS ───────────────────────────────────────────────────────── --}}
@if ($content->newMembers->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.new_members')) !!}</td></tr>
    @foreach ($content->newMembers as $member)
    <tr><td style="padding-bottom:8px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td class="surface2" style="background-color:{{ $c['surface2'] }}; border-radius:8px; padding:14px 16px; vertical-align:middle;">
                <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                    <td style="vertical-align:middle; padding-right:14px;">{!! $renderAvatar($member, 44, 17) !!}</td>
                    <td style="vertical-align:middle;">
                        <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $member->display_name }}</span>
                        <span class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.members.joined', ['{time_ago}' => $member->joined_at->diffForHumans()]) }}</span>
                    </td>
                </tr></table>
            </td></tr>
        </table>
    </td></tr>
    @endforeach
</table>
@endif
@break

@case('stats')
{{-- Stats bar renders outside the switch above --}}
@break

@case('leaderboard')
{{-- ── LEADERBOARD ───────────────────────────────────────────────────────── --}}
@if ($lbEnabled && !empty($lbEntries))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.leaderboard')) !!}</td></tr>

    <tr><td style="padding-bottom:20px;">
        <p class="t-muted" style="margin:0; font-size:14px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.rankings_note') }}</p>
    </td></tr>

    {{-- Podium top 3 --}}
    @if (count($lbTop3) === 3)
    <tr><td style="padding-bottom:16px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
            @foreach ($lbTop3 as $entry)
            @php
                $m      = $medals[$entry['rank']];
                $u      = $entry['user'];
                $pts    = number_format($entry['points']);
                $pPts   = $entry['periodPoints'];
                $change = $entry['rankChange'];
                $isNew  = $entry['isNew'];
                if ($isNew) {
                    $changeHtml = '<span style="font-size:11px; font-weight:600; color:#2fa899;">&#9733; ' . $translator->trans('ernestdefoe-digest-mail.email.leaderboard.change_new') . '</span>';
                } elseif ($change > 0) {
                    $changeHtml = '<span style="font-size:11px; font-weight:600; color:#2e9e5b;">&#9650; ' . $translator->trans('ernestdefoe-digest-mail.email.leaderboard.change_up', ['{n}' => $change]) . '</span>';
                } elseif ($change < 0) {
                    $changeHtml = '<span style="font-size:11px; font-weight:600; color:#e05c3a;">&#9660; ' . $translator->trans('ernestdefoe-digest-mail.email.leaderboard.change_down', ['{n}' => abs($change)]) . '</span>';
                } else {
                    $changeHtml = '<span style="font-size:11px; font-weight:500; color:#9ca3af;">&#8212; ' . $translator->trans('ernestdefoe-digest-mail.email.leaderboard.change_held') . '</span>';
                }
            @endphp
            <td class="podium-cell" width="33%" style="padding:0 5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr><td style="background-color:{{ $m['bg'] }}; border:1px solid {{ $m['border'] }}; border-radius:10px; padding:20px 12px 16px; text-align:center;">
                        <div style="font-size:24px; line-height:1; margin-bottom:10px;">{{ $m['emoji'] }}</div>
                        <div style="margin:0 auto 10px; width:48px; height:48px;">{!! $renderAvatar($u, 48, 18) !!}</div>
                        <div style="font-size:14px; font-weight:500; color:#111; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $u->display_name }}</div>
                        <div style="font-size:15px; font-weight:600; color:{{ $primaryColor }}; margin-bottom:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.points', ['{points}' => $pts]) }}</div>
                        {!! $changeHtml !!}
                        @if ($pPts > 0)
                        <div style="font-size:12px; color:#9ca3af; margin-top:4px;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.points_this_period', ['{points}' => number_format($pPts), '{period}' => $periodWord]) }}</div>
                        @endif
                    </td></tr>
                </table>
            </td>
            @endforeach
        </tr></table>
    </td></tr>
    @endif

    {{-- Ranks 4+ --}}
    @if (!empty($lbRest))
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td width="28" style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.col_rank') }}</td>
                <td style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.col_member') }}</td>
                <td width="56" style="padding:0 0 10px; text-align:center; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.col_move') }}</td>
                <td width="80" style="padding:0 0 10px; text-align:right; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.col_score') }}</td>
            </tr>
            @foreach ($lbRest as $entry)
            @php
                $u      = $entry['user'];
                $change = $entry['rankChange'];
                $isNew  = $entry['isNew'];
                $pPts   = $entry['periodPoints'];
                if ($isNew) {
                    $moveColor = '#2fa899'; $moveLabel = '&#9733; New';
                } elseif ($change > 0) {
                    $moveColor = '#2e9e5b'; $moveLabel = '&#9650; +' . $change;
                } elseif ($change < 0) {
                    $moveColor = '#e05c3a'; $moveLabel = '&#9660; ' . $change;
                } else {
                    $moveColor = '#9ca3af'; $moveLabel = '&#8212;';
                }
            @endphp
            <tr>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle;">
                    <span class="t-muted" style="font-size:13px; font-weight:500; color:{{ $c['textMuted'] }};">{{ $entry['rank'] }}</span>
                </td>
                <td class="row-border" style="padding:14px 8px; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                        <td style="vertical-align:middle; padding-right:12px;">{!! $renderAvatar($u, 36, 14) !!}</td>
                        <td style="vertical-align:middle;">
                            <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $u->display_name }}</span>
                            @if ($isNew)<span style="font-size:10px; font-weight:600; background:#e9f8f5; color:#2fa899; padding:2px 6px; border-radius:4px; letter-spacing:.5px; text-transform:uppercase; margin-top:2px; display:inline-block;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.new_badge') }}</span>@endif
                        </td>
                    </tr></table>
                </td>
                <td class="row-border" style="padding:14px 6px; border-bottom:0.5px solid {{ $c['border'] }}; text-align:center; vertical-align:middle;">
                    <span style="font-size:12px; font-weight:600; color:{{ $moveColor }}; white-space:nowrap;">{!! $moveLabel !!}</span>
                </td>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; text-align:right; vertical-align:middle;">
                    <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ number_format($entry['points']) }}</span>
                    @if ($pPts > 0)<span class="t-muted" style="font-size:12px; color:{{ $c['textMuted'] }}; display:block;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.points_this_period', ['{points}' => number_format($pPts), '{period}' => $periodWord]) }}</span>@endif
                </td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    {{-- Biggest mover --}}
    @if ($lbMover)
    @php $mu = $lbMover['user']; $mPts = $lbMover['periodPoints']; $mMove = $lbMover['rankChange']; @endphp
    <tr><td style="padding-top:20px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td style="background:#e9f8f5; border:0.5px solid #b8eae2; border-radius:10px; padding:18px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                    <td width="40" style="vertical-align:middle; padding-right:14px; font-size:28px; line-height:1;">&#128640;</td>
                    <td style="vertical-align:middle;">
                        <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px; color:#2fa899; margin-bottom:4px;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.biggest_mover', ['{period}' => ucfirst($periodWord)]) }}</div>
                        <div style="font-size:16px; font-weight:500; color:#111;">{{ $mu->display_name }}</div>
                        <div style="font-size:13px; color:#777; margin-top:3px;">{{ $translator->trans('ernestdefoe-digest-mail.email.leaderboard.pts_this_period', ['{points}' => number_format($mPts), '{period}' => $periodWord]) }}@if ($mMove > 0) &nbsp;&middot;&nbsp; &#9650; {{ $mMove }} {{ $mMove === 1 ? $translator->trans('ernestdefoe-digest-mail.email.leaderboard.spot_singular') : $translator->trans('ernestdefoe-digest-mail.email.leaderboard.spot_plural') }}@endif</div>
                    </td>
                    @if ($mMove > 0)
                    <td width="52" style="text-align:right; vertical-align:middle;">
                        <div style="font-size:30px; font-weight:700; color:#2e9e5b; line-height:1;">&#9650;{{ $mMove }}</div>
                        <div style="font-size:10px; color:#999; text-transform:uppercase; letter-spacing:.5px;">{{ $mMove === 1 ? $translator->trans('ernestdefoe-digest-mail.email.leaderboard.spot_singular') : $translator->trans('ernestdefoe-digest-mail.email.leaderboard.spot_plural') }}</div>
                    </td>
                    @endif
                </tr></table>
            </td></tr>
        </table>
    </td></tr>
    @endif

</table>
@endif
{{-- /LEADERBOARD --}}
@break

@case('badges')
{{-- ── BADGES ────────────────────────────────────────────────────────────── --}}
@if ($bdgEnabled && !empty($bdgEarners))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.badges')) !!}</td></tr>

    <tr><td style="padding-bottom:16px;">
        <p class="t-muted" style="margin:0; font-size:14px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $translator->trans('ernestdefoe-digest-mail.email.badges.intro', ['{period}' => $periodWord]) }}</p>
    </td></tr>

    @foreach ($bdgEarners as $earner)
    @php
        $eu  = $earner['user'];
        $eb  = $earner['badge'];
        $eat = $earner['earnedAt'];
        $badgeIcon = $badgeCircle($eb, 44);
    @endphp
    <tr>
        <td class="row-border" style="padding:16px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                <td width="60" style="vertical-align:middle; padding-right:16px;">{!! $badgeIcon !!}</td>
                <td style="vertical-align:middle;">
                    <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block; margin-bottom:3px;">{{ $eb->name }}</span>
                    @if ($eb->description)
                    <span class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{!! \Illuminate\Support\Str::limit($eb->description, 80) !!}</span>
                    @endif
                </td>
                <td style="vertical-align:middle; text-align:right; padding-left:16px; white-space:nowrap;">
                    <table cellpadding="0" cellspacing="0" role="presentation" style="margin-left:auto;"><tr>
                        <td style="vertical-align:middle; text-align:right; padding-right:10px;">
                            <span class="t-main" style="font-size:14px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $eu->display_name }}</span>
                            <span class="t-muted" style="font-size:12px; color:{{ $c['textMuted'] }};">{{ $eat->diffForHumans() }}</span>
                        </td>
                        <td style="vertical-align:middle;">{!! $renderAvatar($eu, 36, 14) !!}</td>
                    </tr></table>
                </td>
            </tr></table>
        </td>
    </tr>
    @endforeach

    @if ($bdgMostEarned || $bdgRarest)
    <tr><td style="padding-top:20px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>

            @if ($bdgMostEarned)
            @php $mb = $bdgMostEarned['badge']; $mc = $bdgMostEarned['count']; @endphp
            <td style="width:{{ $bdgRarest ? '48%' : '100%' }}; vertical-align:top; @if ($bdgRarest) padding-right:8px; @endif">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr><td class="surface2 card-tint" style="background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:10px; padding:18px 20px;">
                        <p style="margin:0 0 12px; font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:{{ $primaryColor }};">&#128293; {{ $translator->trans('ernestdefoe-digest-mail.email.badges.most_earned', ['{period}' => ucfirst($periodWord)]) }}</p>
                        <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                            <td style="vertical-align:middle; padding-right:12px;">
                                {!! $badgeCircle($mb, 40) !!}
                            </td>
                            <td style="vertical-align:middle;">
                                <div class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; margin-bottom:3px;">{{ $mb->name }}</div>
                                <div class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $mc === 1 ? $translator->trans('ernestdefoe-digest-mail.email.badges.earned_count_singular', ['{count}' => $mc, '{period}' => $periodWord]) : $translator->trans('ernestdefoe-digest-mail.email.badges.earned_count_plural', ['{count}' => $mc, '{period}' => $periodWord]) }}</div>
                            </td>
                        </tr></table>
                    </td></tr>
                </table>
            </td>
            @endif

            @if ($bdgRarest)
            @php $rb = $bdgRarest['badge']; $rc = $bdgRarest['earnedCount']; @endphp
            <td style="width:{{ $bdgMostEarned ? '48%' : '100%' }}; vertical-align:top; @if ($bdgMostEarned) padding-left:8px; @endif">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr><td class="surface2 card-tint" style="background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:10px; padding:18px 20px;">
                        <p style="margin:0 0 12px; font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:{{ $primaryColor }};">&#128142; {{ $translator->trans('ernestdefoe-digest-mail.email.badges.rarest') }}</p>
                        <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                            <td style="vertical-align:middle; padding-right:12px;">
                                {!! $badgeCircle($rb, 40) !!}
                            </td>
                            <td style="vertical-align:middle;">
                                <div class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; margin-bottom:3px;">{{ $rb->name }}</div>
                                <div class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $rc === 1 ? $translator->trans('ernestdefoe-digest-mail.email.badges.rarest_count_singular', ['{count}' => $rc]) : $translator->trans('ernestdefoe-digest-mail.email.badges.rarest_count_plural', ['{count}' => $rc]) }}</div>
                            </td>
                        </tr></table>
                    </td></tr>
                </table>
            </td>
            @endif

        </tr></table>
    </td></tr>
    @endif

</table>
@endif
{{-- /BADGES --}}
@break

@case('pickem')
{{-- ── PICK'EM ────────────────────────────────────────────────────────────── --}}
@if ($pkEnabled && (!empty($pkUpcoming) || !empty($pkResults) || !empty($pkLeaderboard)))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.pickem')) !!}</td></tr>

    {{-- Upcoming matches --}}
    @if (!empty($pkUpcoming))
    <tr><td style="padding-bottom:12px;">
        <p style="margin:0 0 6px; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.upcoming_heading') }}</p>
        <p class="t-muted" style="margin:0; font-size:14px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.upcoming_note') }}</p>
    </td></tr>
    @foreach ($pkUpcoming as $ev)
    <tr>
        <td class="row-border" style="padding:20px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                <td style="vertical-align:middle; text-align:left; width:38%;">{!! $renderTeam($ev['homeTeam'], 'left') !!}</td>
                <td style="vertical-align:middle; text-align:center; width:24%;">
                    <span class="vs-bg" style="font-size:12px; font-weight:600; color:{{ $c['textMuted'] }}; background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:6px; padding:5px 14px;">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.vs') }}</span>
                </td>
                <td style="vertical-align:middle; text-align:right; width:38%;">{!! $renderTeam($ev['awayTeam'], 'right') !!}</td>
            </tr></table>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:10px;"><tr>
                <td class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">&#128197; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.match_time', ['{time}' => $ev['matchDate']->format('D, M j g:i A')]) }}</td>
                <td class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }}; text-align:right;">&#9200; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.picks_close', ['{when}' => $ev['cutoff']->diffForHumans()]) }}</td>
            </tr></table>
        </td>
    </tr>
    @endforeach
    <tr><td style="padding-top:22px; text-align:center;">
        <a href="{{ $pkForumUrl }}" style="display:inline-block; padding:11px 28px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.cta') }}</a>
    </td></tr>
    @endif

    {{-- Recent results --}}
    @if (!empty($pkResults))
    <tr><td style="padding:{{ !empty($pkUpcoming) ? '32px' : '0px' }} 0 12px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.results_heading') }}</p>
    </td></tr>
    @foreach ($pkResults as $res)
    @php
        $homeWon = $res['result'] === 'home';
        $awayWon = $res['result'] === 'away';
        $isDraw  = $res['result'] === 'draw';
    @endphp
    <tr>
        <td class="row-border" style="padding:20px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                <td style="vertical-align:middle; text-align:left; width:35%;">
                    {!! $renderTeam($res['homeTeam'], 'left') !!}
                    @if ($homeWon)<div style="font-size:13px; margin-top:5px;">&#127942; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.winner') }}</div>@endif
                </td>
                <td style="vertical-align:middle; text-align:center; width:30%;">
                    <span class="score-bg" style="font-size:20px; font-weight:600; color:{{ $c['text'] }}; background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:8px; padding:6px 14px; white-space:nowrap; display:inline-block;">{{ $res['homeScore'] }} – {{ $res['awayScore'] }}</span>
                    @if ($isDraw)<div class="t-muted" style="font-size:11px; font-weight:600; color:{{ $c['textMuted'] }}; text-transform:uppercase; letter-spacing:1px; margin-top:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.draw') }}</div>@endif
                </td>
                <td style="vertical-align:middle; text-align:right; width:35%;">
                    {!! $renderTeam($res['awayTeam'], 'right') !!}
                    @if ($awayWon)<div style="font-size:13px; margin-top:5px; text-align:right;">&#127942; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.winner') }}</div>@endif
                </td>
            </tr></table>
            <p class="t-muted" style="margin:8px 0 0; font-size:13px; color:{{ $c['textMuted'] }};">{{ $res['matchDate']->format('D, M j') }}</p>
        </td>
    </tr>
    @endforeach
    @endif

    {{-- Pick'em leaderboard --}}
    @if (!empty($pkLeaderboard))
    <tr><td style="padding:{{ (!empty($pkUpcoming) || !empty($pkResults)) ? '32px' : '0px' }} 0 12px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.leaderboard_heading') }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td width="36" style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.col_rank') }}</td>
                <td style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.col_player') }}</td>
                <td width="48" style="padding:0 0 10px; text-align:right; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.col_pts') }}</td>
                <td width="72" style="padding:0 0 10px; text-align:right; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.col_accuracy') }}</td>
            </tr>
            @foreach ($pkLeaderboard as $entry)
            @php $pkMedals = [1=>'🥇',2=>'🥈',3=>'🥉']; $rankEmoji = $pkMedals[$entry['rank']] ?? $entry['rank'] . '.'; @endphp
            <tr>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; font-size:18px;">{{ $rankEmoji }}</td>
                <td class="row-border" style="padding:14px 8px 14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                        <td style="vertical-align:middle; padding-right:12px;">{!! $renderAvatar($entry['user'], 36, 14) !!}</td>
                        <td style="vertical-align:middle;">
                            <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $entry['user']->display_name }}</span>
                            <span class="t-muted" style="font-size:12px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.correct', ['{correct}' => $entry['correctPicks'], '{total}' => $entry['totalPicks']]) }}</span>
                        </td>
                    </tr></table>
                </td>
                <td class="row-border" style="padding:14px 8px 14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; text-align:right;">
                    <span style="font-size:16px; font-weight:600; color:{{ $primaryColor }};">{{ $entry['totalPoints'] }}</span>
                </td>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; text-align:right;">
                    <span class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $entry['accuracy'] }}%</span>
                </td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

</table>
@endif
{{-- /PICK'EM --}}
@break

@case('picks')
{{-- ── CFB PICKS (ernestdefoe/picks) ────────────────────────────────────────── --}}
@if ($pksEnabled && (!empty($pksUpcoming) || !empty($pksResults) || !empty($pksLeaderboard)))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.picks')) !!}</td></tr>

    {{-- Confidence mode notice --}}
    @if ($pksConfidenceMode)
    <tr><td style="padding-bottom:18px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td style="background-color:#fefce8; border:0.5px solid #fef08a; border-radius:8px; padding:10px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                    <td width="20" style="vertical-align:middle; padding-right:10px; font-size:16px; line-height:1;">&#9733;</td>
                    <td style="vertical-align:middle; font-size:13px; color:#854d0e; line-height:1.5;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.confidence_note') }}</td>
                </tr></table>
            </td></tr>
        </table>
    </td></tr>
    @endif

    {{-- Upcoming games --}}
    @if (!empty($pksUpcoming))
    @php
        $pksWeekName = null;
        foreach ($pksUpcoming as $__ev) { if (!empty($__ev['weekName'])) { $pksWeekName = $__ev['weekName']; break; } }
        $pksWeekIsOpen = $pksCurrentWeek && $pksCurrentWeek['isOpen'];
    @endphp
    <tr><td style="padding-bottom:12px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
            <td style="vertical-align:middle;">
                <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.upcoming_heading') }}@if ($pksWeekName) &nbsp;&mdash;&nbsp; {{ e($pksWeekName) }}@endif</p>
            </td>
            @if ($pksWeekIsOpen)
            <td style="vertical-align:middle; text-align:right;">
                <span style="font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; background-color:#dcfce7; color:#166534; border-radius:4px; padding:3px 8px; white-space:nowrap;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.week_open') }}</span>
            </td>
            @endif
        </tr></table>
        <p class="t-muted" style="margin:6px 0 0; font-size:14px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.upcoming_note') }}</p>
    </td></tr>
    @foreach ($pksUpcoming as $ev)
    @php
        $pksHomeLogo = null;
        $pksAwayLogo = null;
        if (!empty($ev['homeTeam']->logo_path)) {
            $__p = $ev['homeTeam']->logo_path;
            $pksHomeLogo = (str_starts_with($__p,'http://')||str_starts_with($__p,'https://')) ? $__p : rtrim($forumUrl,'/').'/'.ltrim($__p,'/');
        }
        if (!empty($ev['awayTeam']->logo_path)) {
            $__p = $ev['awayTeam']->logo_path;
            $pksAwayLogo = (str_starts_with($__p,'http://')||str_starts_with($__p,'https://')) ? $__p : rtrim($forumUrl,'/').'/'.ltrim($__p,'/');
        }
        $pksHomeAbbr = e($ev['homeTeam']->abbreviation ?? $ev['homeTeam']->name);
        $pksAwayAbbr = e($ev['awayTeam']->abbreviation ?? $ev['awayTeam']->name);
        $pksHomeName = e($ev['homeTeam']->name);
        $pksAwayName = e($ev['awayTeam']->name);
    @endphp
    <tr>
        <td class="row-border" style="padding:20px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                {{-- Home team --}}
                <td style="vertical-align:middle; text-align:left; width:38%;">
                    @if ($pksHomeLogo)
                        <img src="{{ $pksHomeLogo }}" alt="{{ $pksHomeAbbr }}" width="64" height="64" style="width:64px; height:64px; object-fit:contain; display:block; border-radius:6px;" />
                    @endif
                    <span style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block; margin-top:6px;">{{ $pksHomeName }}</span>
                </td>
                {{-- vs chip --}}
                <td style="vertical-align:middle; text-align:center; width:24%;">
                    <span class="vs-bg" style="font-size:12px; font-weight:600; color:{{ $c['textMuted'] }}; background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:6px; padding:5px 14px;">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.vs') }}</span>
                    @if ($ev['neutralSite'])
                    <div style="margin-top:6px;">
                        <span style="font-size:10px; font-weight:600; letter-spacing:0.8px; text-transform:uppercase; background-color:{{ $c['surface2'] }}; color:{{ $c['textMuted'] }}; border-radius:4px; padding:2px 7px;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.neutral_site') }}</span>
                    </div>
                    @endif
                </td>
                {{-- Away team --}}
                <td style="vertical-align:middle; text-align:right; width:38%;">
                    @if ($pksAwayLogo)
                        <img src="{{ $pksAwayLogo }}" alt="{{ $pksAwayAbbr }}" width="64" height="64" style="width:64px; height:64px; object-fit:contain; display:block; border-radius:6px; margin-left:auto;" />
                    @endif
                    <span style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block; margin-top:6px; text-align:right;">{{ $pksAwayName }}</span>
                </td>
            </tr></table>
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:10px;"><tr>
                <td class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">&#128197; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.match_time', ['{time}' => $ev['matchDate']->format('D, M j g:i A')]) }}</td>
                <td class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }}; text-align:right;">&#9200; {{ $translator->trans('ernestdefoe-digest-mail.email.pickem.picks_close', ['{when}' => $ev['cutoff']->diffForHumans()]) }}</td>
            </tr></table>
        </td>
    </tr>
    @endforeach
    <tr><td style="padding-top:22px; text-align:center;">
        <a href="{{ $pksForumUrl }}" style="display:inline-block; padding:11px 28px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.cta') }}</a>
    </td></tr>
    @endif

    {{-- Recent results --}}
    @if (!empty($pksResults))
    <tr><td style="padding:{{ !empty($pksUpcoming) ? '32px' : '0px' }} 0 12px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.results_heading') }}</p>
    </td></tr>
    @foreach ($pksResults as $res)
    @php
        $pksHomeWon  = $res['result'] === 'home';
        $pksAwayWon  = $res['result'] === 'away';
        $pksResHomeLogo = null;
        $pksResAwayLogo = null;
        if (!empty($res['homeTeam']->logo_path)) {
            $__p = $res['homeTeam']->logo_path;
            $pksResHomeLogo = (str_starts_with($__p,'http://')||str_starts_with($__p,'https://')) ? $__p : rtrim($forumUrl,'/').'/'.ltrim($__p,'/');
        }
        if (!empty($res['awayTeam']->logo_path)) {
            $__p = $res['awayTeam']->logo_path;
            $pksResAwayLogo = (str_starts_with($__p,'http://')||str_starts_with($__p,'https://')) ? $__p : rtrim($forumUrl,'/').'/'.ltrim($__p,'/');
        }
        $pksResHomeAbbr = e($res['homeTeam']->abbreviation ?? $res['homeTeam']->name);
        $pksResAwayAbbr = e($res['awayTeam']->abbreviation ?? $res['awayTeam']->name);
    @endphp
    <tr>
        <td class="row-border" style="padding:20px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                {{-- Home team --}}
                <td style="vertical-align:middle; text-align:left; width:35%;">
                    @if ($pksResHomeLogo)
                        <img src="{{ $pksResHomeLogo }}" alt="{{ $pksResHomeAbbr }}" width="56" height="56" style="width:56px; height:56px; object-fit:contain; display:block; border-radius:6px;" />
                    @endif
                    <span style="font-size:14px; font-weight:500; color:{{ $c['text'] }}; display:block; margin-top:6px;">{{ e($res['homeTeam']->name) }}</span>
                    @if ($pksHomeWon)<div style="font-size:13px; margin-top:4px;">&#127942; <span style="color:#16a34a; font-weight:500;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.winner') }}</span></div>@endif
                </td>
                {{-- Score --}}
                <td style="vertical-align:middle; text-align:center; width:30%;">
                    <span class="score-bg" style="font-size:20px; font-weight:600; color:{{ $c['text'] }}; background-color:{{ $c['surface2'] }}; border:0.5px solid {{ $c['border'] }}; border-radius:8px; padding:6px 14px; white-space:nowrap; display:inline-block;">{{ $res['homeScore'] }} &ndash; {{ $res['awayScore'] }}</span>
                </td>
                {{-- Away team --}}
                <td style="vertical-align:middle; text-align:right; width:35%;">
                    @if ($pksResAwayLogo)
                        <img src="{{ $pksResAwayLogo }}" alt="{{ $pksResAwayAbbr }}" width="56" height="56" style="width:56px; height:56px; object-fit:contain; display:block; border-radius:6px; margin-left:auto;" />
                    @endif
                    <span style="font-size:14px; font-weight:500; color:{{ $c['text'] }}; display:block; margin-top:6px; text-align:right;">{{ e($res['awayTeam']->name) }}</span>
                    @if ($pksAwayWon)<div style="font-size:13px; margin-top:4px; text-align:right;">&#127942; <span style="color:#16a34a; font-weight:500;">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.winner') }}</span></div>@endif
                </td>
            </tr></table>
            <p class="t-muted" style="margin:8px 0 0; font-size:13px; color:{{ $c['textMuted'] }};">{{ $res['matchDate']->format('D, M j') }}</p>
        </td>
    </tr>
    @endforeach
    @endif

    {{-- Picks leaderboard --}}
    @if (!empty($pksLeaderboard))
    <tr><td style="padding:{{ (!empty($pksUpcoming) || !empty($pksResults)) ? '32px' : '0px' }} 0 12px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ e($pksLeaderboardLabel) }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td width="36" style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.col_rank') }}</td>
                <td style="padding:0 0 10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.col_player') }}</td>
                <td width="60" style="padding:0 8px 10px 0; text-align:right; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.col_pts') }}</td>
                <td width="68" style="padding:0 0 10px; text-align:right; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.picks.col_accuracy') }}</td>
            </tr>
            @foreach ($pksLeaderboard as $entry)
            @php
                $pksMedals    = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                $pksRankEmoji = $pksMedals[$entry['rank']] ?? ($entry['rank'] . '.');
                $pksMovement  = $entry['movement'];
                if ($pksMovement === null) {
                    $pksMoveHtml = '<span style="font-size:11px; font-weight:600; color:#2fa899;">&#9733; ' . $translator->trans('ernestdefoe-digest-mail.email.picks.move_new') . '</span>';
                } elseif ($pksMovement > 0) {
                    $pksMoveHtml = '<span style="font-size:11px; font-weight:600; color:#16a34a;">&#9650; ' . $translator->trans('ernestdefoe-digest-mail.email.picks.move_up', ['{n}' => $pksMovement]) . '</span>';
                } elseif ($pksMovement < 0) {
                    $pksMoveHtml = '<span style="font-size:11px; font-weight:600; color:#dc2626;">&#9660; ' . $translator->trans('ernestdefoe-digest-mail.email.picks.move_down', ['{n}' => abs($pksMovement)]) . '</span>';
                } else {
                    $pksMoveHtml = '<span style="font-size:11px; color:#9ca3af;">' . $translator->trans('ernestdefoe-digest-mail.email.picks.move_held') . '</span>';
                }
            @endphp
            <tr>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; font-size:18px;">{{ $pksRankEmoji }}</td>
                <td class="row-border" style="padding:14px 8px 14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0" role="presentation"><tr>
                        <td style="vertical-align:middle; padding-right:12px;">{!! $renderAvatar($entry['user'], 36, 14) !!}</td>
                        <td style="vertical-align:middle;">
                            <span class="t-main" style="font-size:15px; font-weight:500; color:{{ $c['text'] }}; display:block;">{{ $entry['user']->display_name }}</span>
                            <span class="t-muted" style="font-size:12px; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.pickem.correct', ['{correct}' => $entry['correctPicks'], '{total}' => $entry['totalPicks']]) }} &nbsp;&middot;&nbsp; {!! $pksMoveHtml !!}</span>
                        </td>
                    </tr></table>
                </td>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; text-align:right;">
                    <span style="font-size:16px; font-weight:600; color:{{ $primaryColor }};">{{ $entry['totalPoints'] }}</span>
                </td>
                <td class="row-border" style="padding:14px 0; border-bottom:0.5px solid {{ $c['border'] }}; vertical-align:middle; text-align:right;">
                    <span class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }};">{{ $entry['accuracy'] }}%</span>
                </td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

</table>
@endif
{{-- /CFB PICKS --}}
@break

@case('giveaways')
{{-- ── GIVEAWAYS (ernestdefoe/giveaways) ─────────────────────────────────── --}}
@if ($gvEnabled && (!empty($gvEndingSoon) || !empty($gvRecentWinners)))
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.giveaways')) !!}</td></tr>

    @if (!empty($gvEndingSoon))
    <tr><td style="padding:0 0 6px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.ending_heading') }}</p>
    </td></tr>
    @foreach ($gvEndingSoon as $g)
    <tr><td style="padding:0 0 10px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] ?? $c['bg'] }}; border-radius:8px;">
            <tr>
                <td style="padding:12px 14px;">
                    <a href="{{ $g['url'] }}" style="font-size:15px; font-weight:600; color:{{ $c['text'] }}; text-decoration:none;">&#127873; {{ e($g['prize']) }}</a>
                    <div class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }}; margin-top:3px;">{{ e($g['title']) }}</div>
                    <div class="t-muted" style="font-size:12px; color:{{ $c['textMuted'] }}; margin-top:6px;">
                        &#9203; {{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.ends', ['{when}' => $g['endsAt']->diffForHumans()]) }}
                        &nbsp;&middot;&nbsp; {{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.entrants', ['{count}' => $g['entrantCount']]) }}
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    @endforeach
    @endif

    @if (!empty($gvRecentWinners))
    <tr><td style="padding:8px 0 6px;">
        <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.winners_heading') }}</p>
    </td></tr>
    @foreach ($gvRecentWinners as $g)
    <tr><td style="padding:0 0 10px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] ?? $c['bg'] }}; border-radius:8px;">
            <tr>
                <td style="padding:12px 14px;">
                    <a href="{{ $g['url'] }}" style="font-size:15px; font-weight:600; color:{{ $c['text'] }}; text-decoration:none;">&#127942; {{ e($g['prize']) }}</a>
                    <div class="t-muted" style="font-size:13px; color:{{ $c['textMuted'] }}; margin-top:3px;">{{ e($g['title']) }}</div>
                    @if (!empty($g['winners']))
                    <div style="font-size:13px; color:{{ $c['text'] }}; margin-top:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.won_by', ['{winners}' => e(implode(', ', $g['winners']))]) }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </td></tr>
    @endforeach
    @endif

    <tr><td align="center" style="padding:6px 0 4px;">
        <a href="{{ $gvForumUrl }}" style="display:inline-block; padding:11px 28px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.giveaways.cta') }}</a>
    </td></tr>
</table>
@endif
{{-- /GIVEAWAYS --}}
@break

@case('gamepedia')
{{-- ── GAMEPEDIA (huseyinfiliz/gamepedia) ────────────────────────────────── --}}
@if ($gpEnabled && (!empty($gpMostDiscussed) || !empty($gpNewGames)))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.gamepedia')) !!}</td></tr>

    {{-- Most discussed --}}
    <tr><td style="padding-bottom:16px;">
        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.gamepedia.most_discussed', ['{period}' => ucfirst($periodWord)]) }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $gpMdCol = 0; @endphp
        @foreach ($gpMostDiscussed as $entry)
            @if ($gpMdCol % 3 === 0 && $gpMdCol > 0) </tr><tr> @endif
            @php
                $game     = $entry['game'];
                $coverUrl = $gpCoverUrl($game->cover_image_id ?? null);
                $gameUrl  = rtrim($forumUrl, '/') . '/gamepedia/' . e($game->slug);
            @endphp
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    <tr><td style="padding:0;">
                        <a href="{{ $gameUrl }}" style="display:block; text-decoration:none;">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ e($game->name) }}" style="width:100%; height:auto; display:block;" />
                            @else
                                <div style="width:100%; padding-top:140%; background-color:{{ $c['surface2'] }};"></div>
                            @endif
                        </a>
                    </td></tr>
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:15px; font-weight:700; color:{{ $c['text'] }}; line-height:1.35; margin-bottom:3px;">{{ $game->name }}</div>
                        <div style="font-size:11px; color:{{ $c['textMuted'] }};">{{ $entry['postCount'] }} {{ $entry['postCount'] === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.post') : $translator->trans('ernestdefoe-digest-mail.email.units.posts') }}</div>
                    </td></tr>
                </table>
            </td>
            @php $gpMdCol++; @endphp
        @endforeach
        </tr></table>
    </td></tr>

    {{-- New games --}}
    @if (!empty($gpNewGames))
    <tr><td style="padding:{{ !empty($gpMostDiscussed) ? '28px' : '0px' }} 0 16px;">
        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.gamepedia.newly_added') }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $gpNgCol = 0; @endphp
        @foreach ($gpNewGames as $game)
            @if ($gpNgCol % 3 === 0 && $gpNgCol > 0) </tr><tr> @endif
            @php
                $coverUrl = $gpCoverUrl($game->cover_image_id ?? null);
                $gameUrl  = rtrim($forumUrl, '/') . '/gamepedia/' . e($game->slug);
            @endphp
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    <tr><td style="padding:0;">
                        <a href="{{ $gameUrl }}" style="display:block; text-decoration:none;">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ e($game->name) }}" style="width:100%; height:auto; display:block;" />
                            @else
                                <div style="width:100%; padding-top:140%; background-color:{{ $c['surface2'] }};"></div>
                            @endif
                        </a>
                    </td></tr>
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:15px; font-weight:700; color:{{ $c['text'] }}; line-height:1.35;">{{ $game->name }}</div>
                    </td></tr>
                </table>
            </td>
            @php $gpNgCol++; @endphp
        @endforeach
        </tr></table>
    </td></tr>
    @endif

    {{-- CTA --}}
    <tr><td style="padding-top:24px; text-align:center;">
        <a href="{{ $gpForumUrl }}" style="display:inline-block; padding:11px 28px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.gamepedia.cta') }}</a>
    </td></tr>

</table>
@endif
{{-- /GAMEPEDIA --}}
@break

@case('resofireGamepedia')
{{-- ── RESOFIRE GAMEPEDIA (resofire/gamepedia) ─────────────────────────────── --}}
@if ($rgpEnabled && (!empty($rgpMostDiscussed) || !empty($rgpNewGames) || !empty($rgpTopGenres)))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.gamepedia')) !!}</td></tr>

    {{-- Most discussed --}}
    <tr><td style="padding-bottom:16px;">
        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.resofire_gamepedia.most_discussed', ['{period}' => ucfirst($periodWord)]) }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $rgpMdCol = 0; @endphp
        @foreach ($rgpMostDiscussed as $entry)
            @if ($rgpMdCol % 3 === 0 && $rgpMdCol > 0) </tr><tr> @endif
            @php
                $game       = $entry['game'];
                $coverUrl   = $rgpCoverUrl($game);
                $gameUrl    = rtrim($forumUrl, '/') . '/gamepedia/' . e($game->slug);
                $gameGenres = $entry['genres'] ?? [];
            @endphp
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    <tr><td style="padding:0;">
                        <a href="{{ $gameUrl }}" style="display:block; text-decoration:none;">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ e($game->name) }}" style="width:100%; height:auto; display:block;" />
                            @else
                                <div style="width:100%; padding-top:140%; background-color:{{ $c['surface2'] }};"></div>
                            @endif
                        </a>
                    </td></tr>
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:15px; font-weight:700; color:{{ $c['text'] }}; line-height:1.35; margin-bottom:3px;">{{ $game->name }}</div>
                        @if (!empty($gameGenres))
                        <div style="font-size:10px; color:{{ $c['textMuted'] }}; margin-bottom:4px;">{{ implode(' · ', array_map(fn($g) => e($g->name), array_slice($gameGenres, 0, 2))) }}</div>
                        @endif
                        <div style="font-size:11px; color:{{ $c['textMuted'] }};">{{ $entry['postCount'] }} {{ $entry['postCount'] === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.post') : $translator->trans('ernestdefoe-digest-mail.email.units.posts') }}</div>
                    </td></tr>
                </table>
            </td>
            @php $rgpMdCol++; @endphp
        @endforeach
        </tr></table>
    </td></tr>

    {{-- New games --}}
    @if (!empty($rgpNewGames))
    <tr><td style="padding:{{ !empty($rgpMostDiscussed) ? '28px' : '0px' }} 0 16px;">
        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.resofire_gamepedia.newly_added') }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $rgpNgCol = 0; @endphp
        @foreach ($rgpNewGames as $game)
            @if ($rgpNgCol % 3 === 0 && $rgpNgCol > 0) </tr><tr> @endif
            @php
                $coverUrl   = $rgpCoverUrl($game);
                $gameUrl    = rtrim($forumUrl, '/') . '/gamepedia/' . e($game->slug);
                $gameGenres = $game->genres ?? [];
            @endphp
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    <tr><td style="padding:0;">
                        <a href="{{ $gameUrl }}" style="display:block; text-decoration:none;">
                            @if ($coverUrl)
                                <img src="{{ $coverUrl }}" alt="{{ e($game->name) }}" style="width:100%; height:auto; display:block;" />
                            @else
                                <div style="width:100%; padding-top:140%; background-color:{{ $c['surface2'] }};"></div>
                            @endif
                        </a>
                    </td></tr>
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:15px; font-weight:700; color:{{ $c['text'] }}; line-height:1.35; margin-bottom:3px;">{{ $game->name }}</div>
                        @if (!empty($gameGenres))
                        <div style="font-size:10px; color:{{ $c['textMuted'] }};">{{ implode(' · ', array_map(fn($g) => e($g->name), array_slice($gameGenres, 0, 2))) }}</div>
                        @endif
                    </td></tr>
                </table>
            </td>
            @php $rgpNgCol++; @endphp
        @endforeach
        </tr></table>
    </td></tr>
    @endif

    {{-- Top genres --}}
    @if (!empty($rgpTopGenres))
    <tr><td style="padding:{{ (!empty($rgpMostDiscussed) || !empty($rgpNewGames)) ? '28px' : '0px' }} 0 16px;">
        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.resofire_gamepedia.top_genres', ['{period}' => ucfirst($periodWord)]) }}</p>
    </td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        @foreach ($rgpTopGenres as $i => $genreEntry)
            @php
                $genre    = $genreEntry['genre'];
                $genreUrl = rtrim($forumUrl, '/') . '/gamepedia?genre=' . e($genre->slug);
                $bg       = $i % 2 === 0 ? $c['surface'] : $c['surface2'];
            @endphp
            <tr style="background:{{ $bg }};">
                <td style="padding:10px 14px; font-size:14px; font-weight:600; color:{{ $c['text'] }};">
                    <a href="{{ $genreUrl }}" style="color:{{ $c['text'] }}; text-decoration:none;">{{ e($genre->name) }}</a>
                </td>
                <td style="padding:10px 14px; font-size:13px; color:{{ $c['textMuted'] }}; text-align:right; white-space:nowrap;">
                    {{ $genreEntry['gameCount'] }} {{ $genreEntry['gameCount'] === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.game') : $translator->trans('ernestdefoe-digest-mail.email.units.games') }}
                    &nbsp;·&nbsp;
                    {{ $genreEntry['postCount'] }} {{ $genreEntry['postCount'] === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.post') : $translator->trans('ernestdefoe-digest-mail.email.units.posts') }}
                </td>
            </tr>
        @endforeach
        </table>
    </td></tr>
    @endif

    {{-- CTA --}}
    <tr><td style="padding-top:24px; text-align:center;">
        <a href="{{ $rgpForumUrl }}" style="display:inline-block; padding:11px 28px; background-color:{{ $primaryColor }}; color:#fff; font-size:14px; font-weight:500; text-decoration:none; border-radius:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.resofire_gamepedia.cta') }}</a>
    </td></tr>

</table>
@endif
{{-- /RESOFIRE GAMEPEDIA --}}
@break

@case('favorites')
{{-- ── FAVORITE DISCUSSIONS ────────────────────────────────────────────────── --}}
@if (!empty($favEntries))
{!! $sectionDivider() !!}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:36px;">
    <tr><td>{!! $sectionHeader($translator->trans('ernestdefoe-digest-mail.email.sections.favorites')) !!}</td></tr>
    <tr><td>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $favCol = 0; @endphp
        @foreach ($favEntries as $fav)
            @if ($favCol % 3 === 0 && $favCol > 0) </tr><tr> @endif
            @php
                $favDisc = $fav['discussion'];
                $favUser = $favDisc->user;
                $favUrl  = rtrim($forumUrl, '/') . '/d/' . e($favDisc->slug);
                $favMode = $fav['mode'];
                $favName = $favUser ? e($favUser->username) : $translator->trans('ernestdefoe-digest-mail.email.favorites.unknown_author');
            @endphp
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                       style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden; height:100%;">
                    {{-- Title --}}
                    <tr><td style="padding:14px 14px 10px;">
                        <a href="{{ $favUrl }}"
                           style="font-size:14px; font-weight:700; color:{{ $c['text'] }}; text-decoration:none; line-height:1.4; display:block;">{{ e($favDisc->title) }}</a>
                    </td></tr>
                    {{-- Author --}}
                    <tr><td style="padding:0 14px 10px;">
                        <table cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td style="vertical-align:middle; padding-right:7px;">
                                    @if ($favUser) {!! $renderAvatar($favUser, 22, 10) !!} @endif
                                </td>
                                <td style="vertical-align:middle;">
                                    <span style="font-size:12px; color:{{ $c['textMuted'] }};">{{ $favName }}</span>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                    {{-- Engagement --}}
                    <tr><td style="padding:0 14px 14px;">
                        @if ($favMode === 'reactions' && !empty($fav['reactions']))
                            <span style="font-size:13px; color:{{ $c['textMuted'] }}; line-height:1.6;">
                                @foreach ($fav['reactions'] as $ri => $rxn)
                                    {{ $rxn['emoji'] }}&thinsp;{{ $rxn['count'] }}@if ($ri !== array_key_last($fav['reactions'])) &middot; @endif
                                @endforeach
                            </span>
                        @else
                            <span style="font-size:13px; color:{{ $c['textMuted'] }};">👍&thinsp;{{ $fav['likeCount'] }}</span>
                        @endif
                    </td></tr>
                </table>
            </td>
            @php $favCol++; @endphp
        @endforeach
        {{-- Pad to complete the last row if needed --}}
        @while ($favCol % 3 !== 0)
            <td width="33%" style="padding:5px;"></td>
            @php $favCol++; @endphp
        @endwhile
        </tr></table>
    </td></tr>
</table>
@endif
{{-- /FAVORITE DISCUSSIONS --}}
@break

@case('awards')
{{-- ── AWARDS ──────────────────────────────────────────────────────────────── --}}
@if ($awEnabled && !empty($awAwards))
{!! $sectionDivider() !!}
@foreach ($awAwards as $awEntry)
@php
    $aww        = $awEntry['award'];
    $awStatus   = $awEntry['effectiveStatus'];
    $awCats     = $awEntry['categories'];
    $awVotes    = $awEntry['totalVotes'];
    $awTop      = $awEntry['topNominees'];
    $awUrl      = rtrim($forumUrl, '/') . '/awards/' . (int)$aww->id . '-' . e($aww->slug);
    $awName      = e($aww->name);
    $awDesc      = $aww->description ? e($aww->description) : '';
    $awImage     = $aww->image_url ? e($aww->image_url) : null;
    $awShowVotes = (bool) $aww->show_live_votes;

    // Deadline countdown
    $awDeadline     = null;
    $awDaysLeft     = null;
    $awStartsIn     = null;
    if ($aww->ends_at) {
        $awEndsAt   = \Carbon\Carbon::parse($aww->ends_at);
        $awDeadline = $awEndsAt->format('F j, Y');
        $awDaysLeft = max(0, (int) \Carbon\Carbon::now()->diffInDays($awEndsAt, false));
    }
    if ($awStatus === 'upcoming' && $aww->starts_at) {
        $awStartsAt = \Carbon\Carbon::parse($aww->starts_at);
        $awStartsIn = max(0, (int) \Carbon\Carbon::now()->diffInDays($awStartsAt, false));
        $awDeadline = $awStartsAt->format('F j, Y');
    }
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:48px;">
    <tr><td>{!! $sectionHeader($awName) !!}</td></tr>

    {{-- Banner image --}}
    @if ($awImage)
    <tr><td style="padding-bottom:20px;">
        <a href="{{ $awUrl }}" style="display:block; text-decoration:none;">
            <img src="{{ $awImage }}" alt="{{ $awName }}" style="width:100%; max-height:220px; object-fit:cover; display:block; border-radius:10px;" />
        </a>
    </td></tr>
    @endif

    {{-- Description --}}
    @if ($awDesc)
    <tr><td style="padding-bottom:20px; text-align:center;">
        <p style="margin:0; font-size:14px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $awDesc }}</p>
    </td></tr>
    @endif

    {{-- Status-specific content --}}

    {{-- UPCOMING: voting not yet open --}}
    @if ($awStatus === 'upcoming')
    <tr><td style="padding-bottom:24px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:24px; text-align:center;">
                @if ($awStartsIn !== null)
                <div style="font-size:28px; font-weight:800; color:{{ $primaryColor }}; line-height:1;">
                    {{ $awStartsIn }} {{ $awStartsIn === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.day') : $translator->trans('ernestdefoe-digest-mail.email.units.days') }}
                </div>
                <div style="font-size:13px; color:{{ $c['textMuted'] }}; margin-top:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.until_voting_opens', ['{date}' => $awDeadline]) }}</div>
                @else
                <div style="font-size:16px; font-weight:700; color:{{ $primaryColor }}; line-height:1;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.coming_soon') }}</div>
                @endif
                @if (!empty($awCats))
                <div style="margin-top:16px; font-size:13px; color:{{ $c['text'] }}; font-weight:600;">
                    {{ count($awCats) }} {{ count($awCats) === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.category') : $translator->trans('ernestdefoe-digest-mail.email.units.categories') }} &mdash;
                    {!! implode(', ', array_map(fn($cat) => e($cat->name), $awCats)) !!}
                </div>
                @endif
            </td></tr>
        </table>
    </td></tr>

    {{-- ACTIVE: voting is open --}}
    @elseif ($awStatus === 'active')

    {{-- Deadline + vote count bar --}}
    <tr><td style="padding-bottom:20px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:18px 24px;">
                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                        <td style="vertical-align:middle;">
                            <div style="font-size:22px; font-weight:800; color:{{ $primaryColor }}; line-height:1;">
                                @if ($awDaysLeft !== null && $awDaysLeft === 0)
                                    {{ $translator->trans('ernestdefoe-digest-mail.email.awards.closes_today') }}
                                @elseif ($awDaysLeft !== null)
                                    {{ $translator->trans('ernestdefoe-digest-mail.email.awards.days_left', ['{days}' => $awDaysLeft, '{unit}' => ($awDaysLeft === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.day') : $translator->trans('ernestdefoe-digest-mail.email.units.days'))]) }}
                                @endif
                            </div>
                            @if ($awDeadline)
                            <div style="font-size:12px; color:{{ $c['textMuted'] }}; margin-top:4px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.voting_closes', ['{date}' => $awDeadline]) }}</div>
                            @endif
                        </td>
                        @if ($awShowVotes && $awVotes > 0)
                        <td style="vertical-align:middle; text-align:right; padding-left:16px;">
                            <div style="font-size:22px; font-weight:800; color:{{ $c['text'] }}; line-height:1;">{{ number_format($awVotes) }}</div>
                            <div style="font-size:12px; color:{{ $c['textMuted'] }}; margin-top:4px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.votes_cast') }}</div>
                        </td>
                        @endif
                    </tr></table>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Category list with nominee counts --}}
    @if (!empty($awCats))
    <tr><td style="padding-bottom:16px;">
        <p style="margin:0 0 12px; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.categories') }}</p>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        @foreach ($awCats as $cat)
        <tr><td style="padding:8px 0; border-bottom:0.5px solid {{ $c['border'] }};">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                <td style="font-size:14px; font-weight:500; color:{{ $c['text'] }};">{{ e($cat->name) }}</td>
                <td style="text-align:right; font-size:12px; color:{{ $c['textMuted'] }}; white-space:nowrap;">
                    {{ $cat->nominee_count }} {{ $cat->nominee_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.nominee') : $translator->trans('ernestdefoe-digest-mail.email.units.nominees') }}
                    @if ($awShowVotes && $cat->vote_count > 0)
                        &nbsp;&middot;&nbsp;{{ number_format($cat->vote_count) }} {{ $cat->vote_count === 1 ? $translator->trans('ernestdefoe-digest-mail.email.units.vote') : $translator->trans('ernestdefoe-digest-mail.email.units.votes') }}
                    @endif
                </td>
            </tr></table>
        </td></tr>
        @endforeach
        </table>
    </td></tr>
    @endif

    {{-- Front-runners (show_live_votes only) --}}
    @if ($awShowVotes && !empty($awTop))
    <tr><td style="padding-bottom:20px; padding-top:8px;">
        <p style="margin:0 0 12px; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.front_runners') }}</p>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $awTopCol = 0; @endphp
        @foreach ($awTop as $tn)
            @if ($awTopCol % 3 === 0 && $awTopCol > 0) </tr><tr> @endif
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                       style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    @if ($tn['nomineeImage'])
                    <tr><td style="padding:0;">
                        <img src="{{ e($tn['nomineeImage']) }}" alt="{{ e($tn['nomineeName']) }}"
                             style="width:100%; height:auto; display:block; max-height:140px; object-fit:cover;" />
                    </td></tr>
                    @endif
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:11px; font-weight:600; letter-spacing:0.8px; text-transform:uppercase; color:{{ $c['textMuted'] }}; margin-bottom:4px;">{{ e($tn['categoryName']) }}</div>
                        <div style="font-size:14px; font-weight:700; color:{{ $c['text'] }}; line-height:1.3;">{{ e($tn['nomineeName']) }}</div>
                    </td></tr>
                </table>
            </td>
            @php $awTopCol++; @endphp
        @endforeach
        @while ($awTopCol % 3 !== 0)
            <td width="33%" style="padding:5px;"></td>
            @php $awTopCol++; @endphp
        @endwhile
        </tr></table>
    </td></tr>
    @endif

    {{-- ENDED: results pending --}}
    @elseif ($awStatus === 'ended')
    <tr><td style="padding-bottom:24px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr><td style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:24px; text-align:center;">
                <div style="font-size:16px; font-weight:700; color:{{ $c['text'] }}; margin-bottom:6px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.voting_closed') }}</div>
                <div style="font-size:13px; color:{{ $c['textMuted'] }}; line-height:1.6;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.tallying') }}</div>
            </td></tr>
        </table>
    </td></tr>

    {{-- PUBLISHED: show winners --}}
    @elseif ($awStatus === 'published')
    @if (!empty($awTop))
    <tr><td style="padding-bottom:20px; padding-top:4px;">
        <p style="margin:0 0 12px; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; text-align:center; color:{{ $c['textMuted'] }};">&#127942; {{ $translator->trans('ernestdefoe-digest-mail.email.awards.winners_heading') }}</p>
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
        @php $awWinCol = 0; @endphp
        @foreach ($awTop as $tn)
            @if ($awWinCol % 3 === 0 && $awWinCol > 0) </tr><tr> @endif
            <td width="33%" style="padding:5px; vertical-align:top;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                       style="background-color:{{ $c['surface2'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; overflow:hidden;">
                    @if ($tn['nomineeImage'])
                    <tr><td style="padding:0;">
                        <img src="{{ e($tn['nomineeImage']) }}" alt="{{ e($tn['nomineeName']) }}"
                             style="width:100%; height:auto; display:block; max-height:140px; object-fit:cover;" />
                    </td></tr>
                    @endif
                    <tr><td style="padding:10px 10px 12px; text-align:center;">
                        <div style="font-size:11px; font-weight:600; letter-spacing:0.8px; text-transform:uppercase; color:{{ $c['textMuted'] }}; margin-bottom:4px;">{{ e($tn['categoryName']) }}</div>
                        <div style="font-size:14px; font-weight:700; color:{{ $c['text'] }}; line-height:1.3;">&#127942; {{ e($tn['nomineeName']) }}</div>
                    </td></tr>
                </table>
            </td>
            @php $awWinCol++; @endphp
        @endforeach
        @while ($awWinCol % 3 !== 0)
            <td width="33%" style="padding:5px;"></td>
            @php $awWinCol++; @endphp
        @endwhile
        </tr></table>
    </td></tr>
    @endif
    @endif

    {{-- CTA button --}}
    <tr><td style="padding-top:20px; text-align:center;">
        @if ($awStatus === 'active')
            <a href="{{ $awUrl }}" style="display:inline-block; padding:13px 32px; background-color:{{ $primaryColor }}; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px; letter-spacing:.3px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.cta_vote') }} &rarr;</a>
        @elseif ($awStatus === 'upcoming')
            <a href="{{ $awUrl }}" style="display:inline-block; padding:13px 32px; background-color:{{ $primaryColor }}; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px; letter-spacing:.3px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.cta_nominees') }} &rarr;</a>
        @elseif ($awStatus === 'ended')
            <a href="{{ $awUrl }}" style="display:inline-block; padding:13px 32px; background-color:{{ $primaryColor }}; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px; letter-spacing:.3px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.cta_view') }} &rarr;</a>
        @elseif ($awStatus === 'published')
            <a href="{{ $awUrl }}" style="display:inline-block; padding:13px 32px; background-color:{{ $primaryColor }}; color:#fff; font-size:15px; font-weight:600; text-decoration:none; border-radius:8px; letter-spacing:.3px;">{{ $translator->trans('ernestdefoe-digest-mail.email.awards.cta_results') }} &rarr;</a>
        @endif
    </td></tr>

</table>
@endforeach
@endif
{{-- /AWARDS --}}
@break

@endswitch
@endforeach


{{-- ── CTA ─────────────────────────────────────────────────────────────── --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:40px; border-top:0.5px solid {{ $c['border'] }}; padding-top:36px;">
    <tr><td align="center">
        <a href="{{ $forumUrl }}" style="display:inline-block; padding:14px 40px; background-color:{{ $primaryColor }}; color:#fff; font-size:16px; font-weight:500; text-decoration:none; border-radius:8px; letter-spacing:.2px;">{{ $translator->trans('ernestdefoe-digest-mail.email.button.visit', ['{forum}' => $forumTitle]) }}</a>
    </td></tr>
</table>

</td></tr>
{{-- /BODY --}}

{{-- ── FOOTER ───────────────────────────────────────────────────────────── --}}
<tr>
    <td class="pad card-foot" style="background-color:{{ $c['surface2'] }}; border-top:0.5px solid {{ $c['border'] }}; padding:24px 48px; text-align:center;">
        <p class="t-muted" style="margin:0 0 8px; font-size:13px; color:{{ $c['textMuted'] }}; line-height:1.7;">
            {!! $translator->trans('ernestdefoe-digest-mail.email.footer.notice', ['{forum}' => '<a href="' . e($forumUrl) . '" class="t-muted" style="color:' . $c['textMuted'] . '; text-decoration:underline;">' . e($forumTitle) . '</a>', '{frequency}' => '<strong>' . e($frequencyLabel) . '</strong>']) !!}
        </p>
        <p class="t-muted" style="margin:0; font-size:13px; color:{{ $c['textMuted'] }};">
            <a href="{{ $unsubscribeUrl }}" class="t-muted" style="color:{{ $c['textMuted'] }}; text-decoration:underline;">{{ $translator->trans('ernestdefoe-digest-mail.email.footer.unsubscribe') }}</a>
            &nbsp;&middot;&nbsp;
            <a href="{{ $forumUrl }}" class="t-muted" style="color:{{ $c['textMuted'] }}; text-decoration:underline;">{{ $forumTitle }}</a>
            &nbsp;&middot;&nbsp; &copy; {{ $year }}
        </p>
    </td>
</tr>

</table>{{-- /CARD --}}
</td></tr></table>{{-- /WRAPPER --}}
</body>
</html>
