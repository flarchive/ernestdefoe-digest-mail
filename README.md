# Digest Email

A [Flarum](https://flarum.org) extension that sends beautifully formatted digest emails to your forum members on a schedule they choose. Each email summarises what happened on your forum since the last digest — new discussions, active conversations, new members, leaderboard standings, badges earned, pick'em results, awards, and more — all in a clean, branded email that works in every major email client.

---

## What It Does

Instead of sending a notification every time something happens, Digest Mail sends one well-organised summary email per period. Members choose how often they want it — daily, weekly, or monthly — and can unsubscribe at any time with a single click. Admins control what goes in the email, when it sends, and how hard it works on the server.

---

## Email Content

Every digest email is made up of sections. Some are always included, others only appear when the relevant extension is installed and enabled by the admin.

### Always included

**Featured Discussion**
An admin-pinned discussion displayed prominently at the top of every digest with a highlighted card, author avatar, and a button linking directly to the discussion. Set this to your most important current thread — announcements, pinned rules, ongoing events. Leave it blank to disable it.

**New Discussions**
Discussions that were started during the digest period, showing the title, author, and reply count.

**Active Discussions**
The most engaged discussions during the period, ranked by a configurable activity score that balances reply count and recency. The admin can tune how much weight each factor carries.

**Unread Discussions**
Personalised to each recipient — shows discussions they have not read yet, based on their own read history. This is the only section that differs between subscribers.

**New Members**
Members who joined during the period, shown with avatars and join date.

**Community Stats**
A four-number summary bar showing total posts, discussions, new members, and active users during the period. This always appears near the top of every email regardless of section order.

**Favorite Discussions**
The most liked or reacted-to discussions during the period. If `fof/reactions` is enabled, shows a per-emoji reaction breakdown. If only `flarum/likes` is installed, shows like counts. Requires at least one of those extensions to be active.

### Optional — require additional extensions

**Leaderboard** *(requires `huseyinfiliz/leaderboard`)*
Shows the top point-earners for the period with a podium display for the top three, a full ranked table for positions four and beyond, movement indicators showing who moved up or down, and a "biggest mover" spotlight for whoever gained the most points during the period.

**Badges** *(requires `fof/badges`)*
Shows badges earned during the period — recent earners with their badges, the most-awarded badge of the period, and the rarest badge awarded.

**Pick'em** *(requires `huseyinfiliz/pickem`)*
Shows upcoming pick'em matches members can still vote on, recent match results, and the current pick'em leaderboard standings.

**Gamepedia** *(requires `huseyinfiliz/gamepedia`)*
Shows the most-discussed game pages from the period and newly added games.

**Awards** *(requires `huseyinfiliz/awards`)*
Shows active and upcoming awards — including name, description, voting deadline countdown, category list, vote totals, and current front-runners when live vote display is enabled on the award.

---

## Section Order

Every section listed above can be reordered from the **Digest Order** tab in the admin panel. Use the arrows to set the sequence sections appear in each email. Only enabled sections appear in the list. The three core sections (Discussions, New Members, and Stats) are always shown regardless of order position.

---

## Scheduling

### How timing works

You set the time you want digests to go out — for example 8 a.m. — and the extension fires at that time in whichever timezone you select. You choose your own timezone, not the server's. A forum run by an admin in Chicago should be set to Central Time and set to 8 a.m., even if the server itself is physically located somewhere else entirely.

### Single hour vs send window

**Single hour:** The extension dispatches one chunk of subscribers per scheduler run at the configured hour. For most small forums this means all subscribers are processed in one or two runs within the same minute.

**Send window:** You set a start time and an end time — for example 2 a.m. to 5 a.m. — and the extension dispatches one chunk of subscribers per minute throughout that window. Each minute the scheduler fires, the next batch is processed and queued. Workers drain each batch in parallel as it arrives. This keeps server load low and steady instead of hitting everything at once. Recommended for forums with more than a few thousand subscribers.

When the send window is active, the extension tracks its own progress automatically. Once all subscribers for a given frequency have been processed, it stops on its own — it does not keep running until the window closes.

### User frequency choices

Each user picks their own digest frequency from their account settings page:

- **Daily** — one email every day at the configured time
- **Weekly** — one email on the configured day of the week
- **Monthly** — one email on the configured day of the month
- **Off** — no digest emails

Admins can restrict which frequencies are available. A low-traffic forum might choose to offer only weekly and monthly and hide the daily option entirely. The unsubscribe preference page shown to users automatically reflects whichever options the admin has enabled.

---

## Design and Themes

Every email uses a table-based HTML layout that works reliably in all major email clients including Gmail, Outlook, Apple Mail, and mobile clients.

The email uses your forum's configured brand colours throughout — headings, buttons, and accent elements all pull from your forum's primary and secondary colour settings automatically.

**Light and dark mode:** If `fof/nightmode` is installed, each subscriber's email theme automatically matches their night mode preference — members who use dark mode receive a dark-themed email. Members who have not set a preference receive the light version. If night mode is not installed, all emails use the light theme.

---

## Unsubscribe

Every email includes a secure unsubscribe link in the footer. Clicking it takes the subscriber to a preference page where they can choose a different frequency or opt out entirely.

The link is unique to each subscriber and valid for 90 days. Unlike some implementations, the link remains valid across multiple digest sends — it is only replaced when it expires or the subscriber uses it to change their preference. This means a user who receives a digest on Monday and clicks the link on Friday will always get a working page.

---

## Admin Panel

The admin panel is organised into four tabs.

### Settings

**Content Limits** — set the maximum number of items shown in each section: new discussions, active discussions, unread discussions, new members, leaderboard entries, badges, pick'em entries, and gamepedia entries. Also includes the featured discussion ID and the weighting controls for the hot score algorithm.

**Schedule** — set your timezone, send time (start hour), optional send window end hour, weekly send day, and monthly send day. A live summary below the time dropdowns shows you exactly what mode you are in and when emails will go out.

**User Frequency Options** — choose which frequency options are available to users on their account settings page.

**Extension Integrations** — enable or disable each optional section individually. A toggle is only activatable when the required extension is installed and enabled in Flarum. Sections that are disabled here are removed from the digest entirely and do not appear in the Digest Order tab.

**Token Checker** — paste any unsubscribe token to verify it is valid, see which user it belongs to, and confirm its expiry date. Useful for diagnosing unsubscribe link issues without sending an email.

### Digest Order

Arrow-based ordering for all active sections. Changes save immediately and apply to all future digests. Sections that are not currently enabled are not shown here — enable them in the Settings tab first.

### Statistics

Live statistics pulled from your forum's database:

- **Subscription Overview** — total members, total digest subscribers, and the overall subscription rate as a percentage
- **Subscribers by Frequency** — how many subscribers are on each frequency, with a visual bar and a **View** button that slides open a paginated list of all subscribers for that frequency showing username, avatar, and last sent date
- **Last Sent** — the date and time each frequency was last sent
- **Send History** — one entry per frequency per day showing the total sent and skipped count. History is retained automatically: 30 daily entries, 52 weekly entries, and 24 monthly entries

### Server Settings

Everything related to how the extension uses your server, in one place. The page auto-detects your forum's installation path and generates ready-to-copy cron lines and config blocks using your actual server path.

- **Queue Settings** — queue name, chunk size (subscribers dispatched per scheduler run), job delay, and maximum retry attempts
- **Cron Setup** — tailored setup instructions for your chosen queue backend: Sync, Database Queue, or Redis/Valkey. Each tab shows only what is relevant to that setup, including retry and backoff behaviour, send window guidance, and optional two-phase pre-population for very large forums
- **Recommended Settings by Forum Size** — a reference table covering 100 members through 100,000+ with suggested chunk size, worker count, and send mode for each tier

---

## Test Send

From the Settings tab, any admin can send a live digest email to any address immediately — without affecting any subscriber records or timestamps. Choose a frequency and an optional theme (light or dark), enter any email address, and click Send. Use this to check your email layout, branding, and content before your first scheduled send goes out.

---

## Requirements

### Core requirements

- PHP 8.1 or higher
- Flarum 2.0 or higher
- A working outbound mail configuration in your Flarum admin panel (SMTP, Mailgun, Postmark, etc.)
- A server cron job running `schedule:run` every minute — this is the standard Flarum scheduler requirement shared by many other extensions

### Queue driver

By default Flarum uses the **sync** queue driver, which processes jobs during the web request rather than in the background. This is fine for very small forums but causes problems as your subscriber list grows:

| Subscribers | What happens on sync |
|---|---|
| Under ~50 | Fine — most servers handle it without issue |
| 50–200 | Slow page responses, occasional timeouts |
| 200+ | Regular timeouts, memory exhaustion on typical VPS hosting |
| 500+ | Effectively broken — posts fail or appear to hang |

**Flarum 2.x has a database queue driver built into core** — no extension install required. To enable it, add this to your `config.php`:

```php
'queue' => [
    'driver' => 'database',
],
```

For larger forums, Redis or Valkey with Laravel Horizon and Supervisor is the most scalable option. See the **Server Settings** page in the admin panel for full setup instructions tailored to your chosen approach.

### Optional integrations

| Extension | What it enables |
|---|---|
| `huseyinfiliz/leaderboard` | Leaderboard section |
| `fof/badges` | Badges section |
| `huseyinfiliz/pickem` | Pick'em section |
| `huseyinfiliz/gamepedia` | Gamepedia section |
| `huseyinfiliz/awards` | Awards section |
| `flarum/likes` | Favorite Discussions section |
| `fof/reactions` | Per-emoji reaction breakdown in Favorite Discussions |
| `fof/nightmode` | Per-user dark mode email theme |

---

## Installation

```bash
composer require ernestdefoe/digest-mail
php flarum migrate
```

Then enable the extension in your Flarum admin panel.

---

## Cron Setup

The only cron line required for all setups is the Flarum scheduler. Add it to your server's crontab by running `sudo crontab -u YOUR_WEB_USER -e`, replacing `YOUR_WEB_USER` with the user that owns your Flarum files. If you are unsure which user that is, run `ls -la /path/to/flarum` and check the owner column. Common values are:

- **Ubuntu/Debian** with Nginx or Apache → `www-data`
- **CentOS/RHEL** with Apache → `apache`
- **CentOS/RHEL** with Nginx → `nginx`
- **Shared hosting** → your hosting account username

```
* * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1
```

**If you are using the sync driver**, that is the only line you need.

**If you are using the database queue driver**, add a second line for the queue worker:

```
* * * * * cd /path/to/flarum && php flarum queue:work --queue=digest,default --max-time=55 --tries=3 --backoff=30 >> /dev/null 2>&1
```

`--queue=digest,default` processes digest jobs first, then other Flarum notifications. `--max-time=55` stops the worker cleanly before the next cron fires. Add one additional worker line per parallel worker for larger forums.

**If you are using Redis or Valkey with Horizon and Supervisor**, the scheduler cron line is the only line you need. Do not add a `queue:work` cron line — Horizon is your persistent worker and runs continuously under Supervisor. Adding a `queue:work` cron on top would create competing workers.

Adjust `user` to match the user that owns your Flarum files (`www-data`, `apache`, `nginx`, or your hosting account username depending on your setup).

The **Server Settings** page in the admin panel generates ready-to-copy cron lines and config blocks using your actual server path and current Queue Settings values.

### Two-phase pre-population (50,000+ subscribers only)

For very large forums, you can pre-populate the queue with jobs before the send window opens so workers have no construction overhead when they start. Add this cron line 10 minutes before your configured send window start. For example, if your window starts at 2 a.m., run this at 1:50 a.m.:

```
50 1 * * * cd /path/to/flarum && php flarum digest:enqueue --frequency=daily --delay=600 >> /dev/null 2>&1
```

The first number is the minute (50 = :50) and the second is the hour in 24-hour format (1 = 1 a.m.). Adjust both to match your setup.

---

## Console Commands

### `digest:send`

The main send command. Runs automatically via the scheduler every minute. Outside the configured send window it exits immediately. Inside the window it dispatches one chunk of subscribers and exits, leaving the rest for the next minute.

```bash
# Normal run — respects time gate and window
php flarum digest:send

# Force a specific frequency, bypassing the time gate (useful for testing)
php flarum digest:send --frequency=daily

# Preview mode — shows eligible recipients and content without sending anything
php flarum digest:send --frequency=weekly --dry-run

# Test a single user by ID
php flarum digest:send --frequency=daily --user=1

# Override the queue name
php flarum digest:send --queue=high-priority

# Override the job delay in seconds
php flarum digest:send --delay=300
```

### `digest:enqueue`

Pre-populates the queue with jobs but does not send them. Intended for very large forums that want to build all jobs ahead of time so workers have no construction overhead when the send window opens.

```bash
# Pre-build all daily digest jobs with a 10-minute delay before they become available
php flarum digest:enqueue --frequency=daily --delay=600

# Preview how many users would be enqueued without actually doing it
php flarum digest:enqueue --frequency=weekly --dry-run
```

---

## Performance and Scaling

The extension is built for efficiency at any subscriber count.

**Shared data caching:** Almost everything in a digest email is identical for every subscriber in the same frequency group — new discussions, hot discussions, new members, leaderboard, awards, badges, and all other sections are built once per send run and stored in cache for 2 hours. The only per-subscriber query is unread discussions. For a forum with 10,000 daily subscribers this means roughly 10,013 total database queries per send instead of 100,000+.

**Lightweight job storage:** Each background job stores only a user ID, frequency, and a reference to the cached shared data — typically under 500 bytes per job. The jobs table stays small and workers stay fast.

**Persistent unsubscribe tokens:** Each subscriber's unsubscribe token is generated once and reused across sends. It is only replaced when it expires (90 days) or the subscriber uses it. This ensures unsubscribe links in emails always work, regardless of how many sends have occurred since the email was received.

**Send window pacing:** With a send window configured, the extension dispatches one chunk of subscribers per minute throughout the window. Workers drain each chunk in parallel as it arrives. Server load stays low and steady across the entire window period.

**Recommended settings by forum size:**

| Forum Size | Chunk Size | Workers | Send Mode |
|---|---|---|---|
| 100–500 members | 200 | 1 | Single hour |
| 500–2,000 | 500 | 1 | Single hour |
| 2,000–5,000 | 1,000 | 2 | 1–2 hour window |
| 5,000–15,000 | 2,000 | 3 | 2–3 hour window |
| 15,000–50,000 | 5,000 | 5 | 2–4 hour window |
| 50,000–100,000 | 7,500 | 8 | 3–4 hour window |
| 100,000+ | 10,000 | 10+ | 4+ hour window |

For 50,000+ member forums, consider switching from the database queue driver to Redis or Valkey with Horizon and Supervisor for significantly higher throughput and real-time queue monitoring. The Server Settings page in the admin panel includes full step-by-step setup instructions for this configuration.

---

## Credits

Originally created by [Resofire](https://github.com/resofire). Rebranded and
maintained by [ernestdefoe](https://github.com/ernestdefoe), with full credit and
thanks to the original author.

## License

MIT — Copyright (c) 2026 Resofire
