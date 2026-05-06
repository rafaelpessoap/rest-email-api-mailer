# REST Email API Mailer

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.1%2B-0073aa.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net)

A WordPress plugin that replaces `wp_mail()` with delivery via the transactional email REST API hosted at `platform.cyberpersons.com` — the email service that ships with [Cyberpanel](https://cyberpanel.net) hosting. Includes smart delivery tracking, an account statistics dashboard and a graceful fallback to the default WordPress mailer.

> **Disclaimer**: this is an independent, community-maintained project. It is **not affiliated with, endorsed by or sponsored by Cyberpanel, CyberPersons LLC** (cyberpanel.net / cyberpersons.com) or any of their operators. "Cyberpanel" is referenced here only to describe the third-party email service the plugin connects to. All trademarks remain the property of their respective owners.

## Why this plugin exists

I run several WordPress sites on servers managed with Cyberpanel and wanted to use the transactional email service that ships with the Cyberpanel account. After searching around I could not find an existing plugin that handled the API integration with WordPress, so I wrote one for my own sites and am now releasing it as open source so anyone running on Cyberpanel can benefit from the same integration.

## Features

- **Drop-in `wp_mail()` replacement** — every transactional email leaving WordPress (WooCommerce, contact forms, password resets, custom plugins) is routed through the Cyberpanel API.
- **Smart delivery tracking** — the plugin schedules a single WP-Cron event after each send, rechecks every 3 minutes until a final status is reached, and stops on its own (no permanent cron).
- **Account dashboard** — plan, monthly limit, emails sent/remaining, reputation score, rate limits, verified domains, monthly delivered/bounced/opened/clicked.
- **Activity log** — last 30 events with colored status (SENT / DELIVERED / ERROR / BOUNCE / EXPIRED / TIMEOUT).
- **Safe fallback** — when the plugin toggle is off, WordPress keeps using its default PHPMailer-backed mailer.
- **API key via constant** — define `CYBERPANEL_EMAIL_API_KEY` in `wp-config.php` to keep the secret out of the database.
- **Protected log file** — logs live under `wp-content/uploads/cyberpanel-email/cyberpanel-email.log.php` starting with a `<?php exit; ?>` guard (safe on Apache, Nginx and LiteSpeed), plus redundant `.htaccess`, `web.config` and `index.php` rules for defense in depth.
- **Internationalization** — English source strings with bundled Brazilian Portuguese (`pt_BR`) translation; ready for more locales.

## Requirements

- WordPress 6.1 or newer (uses the `pre_wp_mail` filter + just-in-time translation loader introduced in 6.1)
- PHP 7.4 or newer
- A Cyberpanel account with an API key and at least one verified sending domain

## Installation

**From GitHub release:**

1. Download the latest ZIP from the [Releases page](https://github.com/rafaelpessoap/rest-email-api-mailer/releases).
2. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the ZIP.
3. Activate the plugin.
4. Open **Settings > Email API Mailer** and fill in your API key, sender email and sender name.
5. Tick **Enable** and save. Use the **Send Test** button to confirm.

**From source:**

```bash
git clone https://github.com/rafaelpessoap/rest-email-api-mailer.git
cp -r rest-email-api-mailer /path/to/wp-content/plugins/
```

## Configuration via `wp-config.php`

To keep the API key out of the database, define it in `wp-config.php` above the `/* That's all, stop editing! */` line:

```php
define( 'CYBERPANEL_EMAIL_API_KEY', 'sk_live_your_key_here' );
```

The plugin prefers the constant over the option value whenever both are present.

## How delivery tracking works

1. Email is sent via `POST /email/v1/send` → the API returns a `message_id`.
2. The plugin records the `message_id` and schedules a single WP-Cron event 3 minutes later.
3. The cron runs, calls `GET /email/v1/messages/{id}` for every pending message and refreshes account stats via `GET /email/v1/account/stats`.
4. If messages are still pending it reschedules itself; otherwise it stops.
5. Messages without a final status after 20 checks or 48 hours are marked `TIMEOUT` / `EXPIRED`.

## API endpoints used

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/email/v1/send` | Send an email |
| `GET`  | `/email/v1/messages/{id}` | Fetch delivery status |
| `GET`  | `/email/v1/account/stats` | Fetch account stats |

Base URL: `https://platform.cyberpersons.com` — this is the administrative domain the Cyberpanel email platform exposes its REST API on.

## Known limitations

- Only **one recipient per API request** — emails addressed to multiple recipients are split into multiple API calls transparently.
- The API `from` field accepts plain emails only — the "Name \<email\>" format is parsed out client-side.
- **Attachments are not supported** by the API at the moment; emails carrying attachments fall through to the default WordPress mailer.
- Open/click tracking depends on how your Cyberpanel account is configured.

## Security

This plugin has been written with WordPress security guidelines in mind: every admin action is gated by capability checks and nonces, all `$_POST`/`$_GET` access goes through `wp_unslash()` plus explicit sanitization, every registered option has a `sanitize_callback`, and all admin output is escaped. See [SECURITY.md](SECURITY.md) for how to report a vulnerability responsibly.

## Contributing

Pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) before sending a patch.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

[GPL v2 or later](LICENSE).
