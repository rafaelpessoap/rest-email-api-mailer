=== REST Email API Mailer ===
Contributors: rafaelzezao
Tags: email, smtp, transactional email, wp_mail, rest api
Requires at least: 6.1
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replaces wp_mail() with delivery via a transactional email REST API (the one used by Cyberpanel hosting), with delivery tracking and account stats.

== Description ==

**REST Email API Mailer** replaces the default `wp_mail()` function so every transactional email your WordPress site sends (WooCommerce notifications, password resets, contact-form messages, custom plugin emails) is delivered through a transactional email REST API hosted at `platform.cyberpersons.com` — the email service that ships with Cyberpanel hosting — instead of the server's default PHP mailer.

I built this plugin after looking for a simple way to connect WordPress to the email service that ships with Cyberpanel and finding no existing plugin that did the job. I needed it for my own sites and decided to share it as open source so anyone running on Cyberpanel can benefit from the same integration.

**This is an independent, community-maintained plugin. It is not affiliated with, endorsed by or sponsored by Cyberpanel or CyberPersons LLC.** "Cyberpanel" is referenced in this readme only to describe the third-party email service the plugin connects to.

**This plugin is fully open source.** The source code, issue tracker and release history are public on GitHub: [https://github.com/rafaelpessoap/rest-email-api-mailer](https://github.com/rafaelpessoap/rest-email-api-mailer). Pull requests, bug reports and translations are welcome.

= Main features =

* **wp_mail() override** — every email leaving WordPress (WooCommerce, forms, notifications, custom plugins) goes through the configured REST API.
* **Smart delivery tracking** — after each send, the plugin schedules a single WP-Cron event to check whether the message was actually delivered, rechecking every 3 minutes until confirmation, bounce, timeout (20 retries) or expiration (48 hours).
* **Account statistics dashboard** — displays your plan, monthly limit, emails sent/remaining, reputation score, rate limits, verified domains and monthly engagement (delivered, bounced, opened, clicked).
* **Colored activity log** — history of the last 30 events: SENT (blue), DELIVERED (green), ERROR/BOUNCE (red), EXPIRED/TIMEOUT (yellow).
* **Built-in test email** — one-click test from the settings screen to confirm the integration is working.
* **Safe fallback** — when the plugin toggle is off, WordPress keeps using its default mailer (PHPMailer) without any side effects.
* **Multiple recipients** — emails with several To/Cc/Bcc addresses are transparently split into one API call per recipient (current API limitation).
* **wp-config.php constant support** — define `RESTEMAP_API_KEY` in your `wp-config.php` to keep the API key out of the database.
* **Protected log location** — logs are stored under `wp-content/uploads/restemap/` in a `.log.php` file that begins with a PHP-exit guard, so even if the web server is configured to serve static files directly (Nginx, LiteSpeed, etc.) any direct HTTP request returns empty output. Additional `.htaccess`, `web.config` and `index.php` guards are created for defense in depth.

= Data sent to third parties =

When enabled, the plugin sends the following data to `platform.cyberpersons.com` (the Cyberpanel email platform) for every outgoing email: the sender, recipient, subject, message body, reply-to, a `source: wp_mail` metadata tag and the site URL in metadata. API responses (delivery status, open/click counts) are fetched back and stored locally. No data is sent to any other third party.

= Contributing and community =

The plugin lives on GitHub and is developed in the open:

* **Source code and releases**: [github.com/rafaelpessoap/rest-email-api-mailer](https://github.com/rafaelpessoap/rest-email-api-mailer)
* **Bug reports and feature requests**: [github.com/rafaelpessoap/rest-email-api-mailer/issues](https://github.com/rafaelpessoap/rest-email-api-mailer/issues)
* **Security disclosures**: see [SECURITY.md](https://github.com/rafaelpessoap/rest-email-api-mailer/blob/main/SECURITY.md) — please use GitHub's private vulnerability reporting rather than opening a public issue.
* **Contributing guide**: see [CONTRIBUTING.md](https://github.com/rafaelpessoap/rest-email-api-mailer/blob/main/CONTRIBUTING.md) for coding standards, how to add a translation, and how releases are cut.

Every release shipped to this directory is mirrored verbatim on the GitHub Releases page so you can always inspect the exact source behind any installed version.

= Disclaimer =

This plugin is an independent, open-source integration. It is **not affiliated with, endorsed by or sponsored by Cyberpanel, CyberPersons LLC** (cyberpanel.net / cyberpersons.com) or any of their operators. "Cyberpanel" is referenced here only to describe the third-party email service this plugin connects to. All trademarks remain the property of their respective owners.

== Installation ==

1. Upload the `rest-email-api-mailer` folder to `wp-content/plugins/`, or install the ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate the plugin from the **Plugins** screen.
3. Go to **Settings > Email API Mailer**.
4. Fill in:
   * **API Key** — the `sk_live_...` key generated inside your Cyberpanel account (or define `RESTEMAP_API_KEY` in `wp-config.php`).
   * **Sender Email** — an address on a domain you already verified inside Cyberpanel.
   * **Sender Name** — the name your recipients will see.
5. Enable the **Enable** toggle and save.
6. Use the **Send Test** button to confirm delivery.

== Frequently Asked Questions ==

= How does delivery tracking work? =

No long-running cron is required. When an email is sent the plugin records the returned `message_id` and schedules a **single** WP-Cron event for 3 minutes later. That event queries `GET /email/v1/messages/{id}` for every pending message and refreshes your account stats. If pending messages remain it reschedules itself; otherwise it stops. A message is abandoned after 20 checks or 48 hours without a final status.

= Can I keep my API key out of the database? =

Yes. Add the following line to your `wp-config.php`:

    define( 'RESTEMAP_API_KEY', 'sk_live_your_key_here' );

The plugin detects the constant and prefers it over the value stored in the database.

= Are attachments supported? =

Not at this time — the current Cyberpanel email API does not accept attachments. When `wp_mail()` is called with attachments the plugin transparently falls through to the default WordPress mailer via the `pre_wp_mail` filter, so the message still goes out.

= Does the plugin support multisite? =

It has not been explicitly tested on multisite yet. Each site in a network can be configured independently if you activate it per-site rather than network-wide.

= Does the plugin work when the toggle is turned off? =

Yes. When disabled, the plugin uses an internal drop-in that mirrors the standard WordPress `wp_mail()` behavior backed by PHPMailer, so disabling the integration is safe.

= Where are the logs stored? =

Under `wp-content/uploads/restemap/restemap.log.php`. The file starts with a `<?php exit; ?>` guard so any direct HTTP request returns empty output regardless of the web server in use. Additional `.htaccess`, `web.config` and `index.php` files block direct access as a defense-in-depth measure.

== Screenshots ==

1. Account dashboard with plan, usage, engagement and monthly quota bar.
2. Settings page with API key, sender email and enable toggle.
3. Delivery tracking panel and colored activity log.

== Changelog ==

= 2.2.0 =
* **Unique `restemap_` prefix throughout** — every plugin-defined function, class, option key, cron hook, action handler, nonce and wp-config constant now uses the same `restemap_` / `Restemap_` / `RESTEMAP_` prefix. Previous internal identifiers under different prefixes have been retired, satisfying the WordPress.org guideline that all plugin globals share a single distinctive prefix.
* **Automatic one-time migration** of existing installs: option values, the migration marker and any scheduled delivery-check cron event are copied over from the previous keys (`cyberpanel_email_*`) — and from the original internal release (`cyberpersons_*`) when present — so upgrading from v2.0.x or v2.1.0 keeps every saved setting and pending delivery check intact.
* **`CYBERPANEL_EMAIL_API_KEY` wp-config constant renamed to `RESTEMAP_API_KEY`.** If you previously kept the API key in `wp-config.php`, edit that file once after upgrading and rename the constant; otherwise the plugin will fall back to the value stored in the database.
* **Log directory moved from `wp-content/uploads/cyberpanel-email/` to `wp-content/uploads/restemap/`.** A new directory is created automatically; the previous one (if any) is left in place and can be deleted manually.
* **Settings page URL changed.** Bookmarks pointing to `options-general.php?page=cyberpanel-api-email` should be updated to `options-general.php?page=restemap`.

= 2.1.0 =
* **Renamed plugin to "REST Email API Mailer" (slug `rest-email-api-mailer`)** to comply with the WordPress.org ownership guideline that bars third-party plugins from carrying a trademarked vendor name in their identity. Existing installs keep all their settings — internal option keys, cron events and the wp-config constant are preserved as-is, so the upgrade is transparent.
* Plugin URI now points to the renamed GitHub repository at `https://github.com/rafaelpessoap/rest-email-api-mailer` (the previous URL still redirects automatically).
* Updated all admin-visible labels and screenshots references to the new plugin name. Functional descriptions still mention the third-party email service the plugin integrates with, with a clear "not affiliated with" disclaimer.

= 2.0.5 =
* Boolean option sanitizer now uses `rest_sanitize_boolean()` instead of a plain PHP cast, so submitted strings like `"false"` are stored as the boolean `false` rather than the truthy string.
* Removed the `WP_CONTENT_DIR` fallback in the log path resolver and dropped the legacy v1.x log cleanup that referenced `WP_CONTENT_DIR`. The plugin now relies solely on `wp_upload_dir()` to determine where to write its log directory, matching WordPress.org guidelines for determining content locations.
* Corrected the `Contributors` slug in `readme.txt` to match the WordPress.org account that owns this plugin.

= 2.0.4 =
* Removed the explicit `load_plugin_textdomain()` call — redundant since the WordPress just-in-time loader (6.1+) automatically loads translations from the plugin's own `languages/` directory when `Domain Path` is declared.
* Bumped minimum WordPress version from 5.7 to 6.1 to match the just-in-time translation loader that the plugin now relies on. Both current and legacy users targeted are comfortably above this floor.
* Plugin now passes the official WordPress Plugin Check with zero warnings and zero errors.

= 2.0.3 =
* Documentation: added an explicit "Contributing and community" section to the readme with pointers to the public GitHub repository, issue tracker, security policy and contribution guide. No code changes.

= 2.0.2 =
* Added a **Settings** shortcut on the Plugins list page, next to Activate/Deactivate, for faster access to the plugin configuration.
* Fixed a stale Brazilian Portuguese translation of the plugin display name (a leftover value from a previous rename was still being shown to pt_BR users).

= 2.0.1 =
* Bumped "Tested up to" from 6.7 to 6.9 for current WordPress eligibility.
* Uninstall routine now uses `WP_Filesystem()` to remove the log directory.
* Internal refactor: `pre_wp_mail` callback moved into the main class; translation loading moved to the `init` hook.
* Readability cleanup in the dashboard rendering.

= 2.0.0 =
* Renamed plugin and published as open source.
* English source strings with bundled Brazilian Portuguese translation (pt_BR).
* Security hardening: capability checks on every admin action, nonces, `wp_unslash()` on all superglobals, sanitize callbacks on every registered option, use of `wp_safe_redirect()` for post-action redirects, escaping on every output.
* Logs moved from `wp-content/cyberpersons-mailer.log` to a protected directory under `wp-content/uploads/cyberpanel-email/` with `.htaccess`, `web.config` and `index.php` guards.
* Added wp-config.php constant support so the API key can live outside the database.
* Added `uninstall.php` that performs a full cleanup (options, cron, logs).
* One-time automatic migration from the legacy `cyberpersons_*` option names used in v1.x.

= 1.2.0 =
* Internal release. First working integration with delivery tracking, account stats dashboard and activity log. Portuguese-only strings, options prefixed with `cyberpersons_`.

== Upgrade Notice ==

= 2.2.0 =
Plugin-wide prefix unification. Settings migrate automatically on the first admin page load. If you defined the API key in wp-config.php, rename `CYBERPANEL_EMAIL_API_KEY` to `RESTEMAP_API_KEY`.

= 2.0.0 =
Major security and i18n release. Your previous settings are migrated automatically on the first admin page load after upgrading.

== Privacy ==

This plugin transmits the content of your outgoing emails (sender, recipient, subject, message body) to the Cyberpanel email platform at `platform.cyberpersons.com` for the sole purpose of delivering those emails, and fetches back their delivery status for display in the admin panel. Refer to the Cyberpanel privacy policy at https://cyberpanel.net for details on how that service processes the data.

No data is sent to the plugin author, to GitHub or to any other third party. All source code can be audited at [github.com/rafaelpessoap/rest-email-api-mailer](https://github.com/rafaelpessoap/rest-email-api-mailer).
