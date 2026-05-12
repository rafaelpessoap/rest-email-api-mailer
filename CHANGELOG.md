# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-05-12

### Changed

- **Unified plugin-wide prefix to `restemap_` / `Restemap_` / `RESTEMAP_`.** In response to the WordPress.org Plugin Review Team feedback that the plugin was carrying two different prefixes (`cyberpanel_email_*` left over from the previous identity for backward compatibility, plus the new `REST_Email_API_Mailer` class name that started with the common word "rest"), every function, class, constant, option key, cron hook, action handler, nonce and admin-notice transient now uses the same single distinctive prefix.
- Main class renamed from `REST_Email_API_Mailer` to `Restemap_Plugin`.
- All seven persisted option keys renamed: `cyberpanel_email_api_key` → `restemap_api_key`, `cyberpanel_email_from_email` → `restemap_from_email`, `cyberpanel_email_from_name` → `restemap_from_name`, `cyberpanel_email_enabled` → `restemap_enabled`, `cyberpanel_email_pending_messages` → `restemap_pending_messages`, `cyberpanel_email_account_stats` → `restemap_account_stats`, `cyberpanel_email_migrated_from_legacy` → `restemap_migrated_from_legacy`.
- Cron hook renamed from `cyberpanel_email_check_delivery` to `restemap_check_delivery`. The one-time migration unschedules any pending event under the old hook and reschedules it under the new one so delivery tracking never pauses.
- `admin_post_*` action handlers, nonce action names and per-user admin-notice transients renamed to share the same prefix.
- Settings group renamed from `cyberpanel_email` to `restemap`, and the settings page menu slug changed from `cyberpanel-api-email` to `restemap`. Bookmarks targeting the old `options-general.php?page=cyberpanel-api-email` URL should be updated.
- Log directory moved from `wp-content/uploads/cyberpanel-email/` to `wp-content/uploads/restemap/`. The previous directory is not migrated automatically: a fresh log file is started in the new location on first activation, and the old directory (if any) can be left in place or removed manually.

### Removed

- **`CYBERPANEL_EMAIL_API_KEY` wp-config constant.** Replaced by `RESTEMAP_API_KEY`. Database-stored API keys migrate automatically; installs that defined the API key only in `wp-config.php` need to rename the constant once after upgrading (until then the plugin uses the value stored in the database, if any).

### Added

- The one-time migration in `maybe_migrate_legacy_options()` now handles two generations of legacy keys: it copies values from `cyberpanel_email_*` (v2.0.0 – v2.1.0) when present, and also from `cyberpersons_*` (the original pre-2.0.0 internal release) as a fallback. Both sets are deleted after migration so no orphan options are left behind.

## [2.1.0] - 2026-05-07

### Changed

- **Renamed plugin to "REST Email API Mailer"** (slug `rest-email-api-mailer`, GitHub repo `rest-email-api-mailer`) to comply with the WordPress.org Plugin Directory ownership guideline. The previous identity ("Email API Mailer for Cyberpanel") incorporated the trademark of a third-party service the plugin connects to but does not represent; the WP.org Plugin Review Team requires plugin identity to either come from the trademark holder's own WordPress.org account or to drop the trademark from name and slug. We chose the second path.
- Display name, slug, text domain, plugin file name (`rest-email-api-mailer.php`), translation file names (`rest-email-api-mailer.pot`, `rest-email-api-mailer-pt_BR.po/.mo`), main class name (`REST_Email_API_Mailer`) and `@package` annotation all updated to the new neutral identity.
- Plugin URI, GitHub repository URL and translation header URLs updated to `https://github.com/rafaelpessoap/rest-email-api-mailer`. The previous URL still redirects automatically thanks to GitHub's automatic redirect on rename.
- Plugin Description in the file header now states explicitly that the plugin is independent and not affiliated with Cyberpanel or CyberPersons LLC.
- A few admin-visible labels were rebranded to neutral wording: the settings page `<h1>` no longer carries the third-party brand, and the Account Dashboard heading was generalized.

### Kept (intentional, for backward compatibility)

- Internal database option key prefix (`cyberpanel_email_*`) and migrated-from-legacy marker — three production sites already store data under these keys and no migration was needed.
- Cron hook name `cyberpanel_email_check_delivery` — pre-existing scheduled events on production sites continue to run uninterrupted.
- `CYBERPANEL_EMAIL_API_KEY` constant in `wp-config.php` — site owners who set this on existing installs keep their configuration without intervention.
- Log directory path (`wp-content/uploads/cyberpanel-email/`) — same rationale.
- Functional descriptions in admin UI still reference the third-party API the plugin sends to (e.g. "Send emails through the Cyberpanel API"), since they describe what the plugin does, not who owns it.

## [2.0.5] - 2026-04-30

### Changed

- Boolean option sanitizer now uses `rest_sanitize_boolean()` instead of a plain PHP `(bool)` cast. Plain casting treats any non-empty string as `true`, so values like `"false"`, `"0"` or `"off"` would have been stored as `true`. `rest_sanitize_boolean()` normalizes those to boolean `false` as expected.
- Log path resolver no longer falls back to `WP_CONTENT_DIR . '/uploads'` when `wp_upload_dir()` does not return a `basedir`. The plugin now relies solely on `wp_upload_dir()` and gracefully skips logging if the uploads directory is unavailable, matching the WordPress.org guideline on determining content locations.
- Removed the legacy v1.x log file cleanup from `uninstall.php` (it referenced `WP_CONTENT_DIR` to find a file that only ever existed on internal pre-2.0.0 installations).

### Fixed

- `Contributors` slug in `readme.txt` now matches the WordPress.org account that owns the plugin (`rafaelzezao`), so the directory page lists the correct contributor.

## [2.0.4] - 2026-04-22

### Changed

- Removed the explicit `load_plugin_textdomain()` call. WordPress's just-in-time loader (WP 6.1+) already auto-loads translations from the plugin's own `languages/` directory when `Domain Path` is declared, so the call was redundant and triggered a Plugin Check warning for users running the official plugin-check tool.
- Bumped minimum WordPress version from 5.7 to 6.1 to match the just-in-time translation loader behavior the plugin now relies on. WP 6.1 was released November 2022 and covers the vast majority of installations.

### Fixed

- Plugin now returns **0 errors and 0 warnings** when run through the official WordPress Plugin Check tool.

## [2.0.3] - 2026-04-22

### Documentation

- `readme.txt` now points readers to the public GitHub repository, issue tracker, `SECURITY.md` and `CONTRIBUTING.md` through an explicit "Contributing and community" section. The main description also states that the plugin is fully open source and that every release is mirrored on GitHub Releases, so users can audit the code behind any installed version. No code changes.

## [2.0.2] - 2026-04-22

### Added

- "Settings" shortcut on the **Plugins** list page next to the Activate/Deactivate link, so users can open the plugin configuration without navigating the Settings submenu. Matches the UX of popular plugins like Contact Form 7.

### Fixed

- Brazilian Portuguese (`pt_BR`) translation of the plugin name was stale: after renaming the plugin in 2.0.0 the English source was updated but the `msgstr` in the `.po/.mo` still read "Cyberpanel API Email para WordPress". The plugin now correctly displays as "Email API Mailer para Cyberpanel" on pt_BR sites.

## [2.0.1] - 2026-04-22

### Changed

- Bumped the declared compatibility from "Tested up to: 6.7" to "Tested up to: 6.9" so the plugin is eligible for the current WordPress release.
- Uninstall routine now uses `WP_Filesystem()->delete()` instead of direct `rmdir()`/`unlink()` calls, matching WordPress Plugin Directory review guidelines.
- Moved the `pre_wp_mail` filter callback into the main plugin class and the translation loading into the `init` hook. No user-visible change.

### Fixed

- Inline control structures in the settings dashboard replaced with explicit multi-line blocks for readability.
- `handle_test_email()` sanitizes `$_POST['test_to']` at the point of access so every static analyzer can verify the path.

## [2.0.0] - 2026-04-22

### Added

- First public open-source release under the name **Email API Mailer for Cyberpanel**.
- English source strings with bundled Brazilian Portuguese (`pt_BR`) translation.
- `CYBERPANEL_EMAIL_API_KEY` constant support so the API key can live in `wp-config.php` instead of the database.
- `uninstall.php` that performs a full cleanup of options, cron events and log directory.
- Protected log directory under `wp-content/uploads/cyberpanel-email/` with `.htaccess`, `web.config` and `index.php` guards.
- One-time automatic migration from the legacy `cyberpersons_*` option names used in v1.x.
- GitHub Actions workflow running WordPress Coding Standards on every push and pull request.
- `SECURITY.md`, `CONTRIBUTING.md` and `CHANGELOG.md` governance files.

### Changed

- Renamed from "Cyberpersons Mailer" to "Email API Mailer for Cyberpanel" to reflect the brand end users actually interact with (Cyberpanel). Internal option names, class, text domain and file name follow the rename. The underlying REST endpoint remains `platform.cyberpersons.com`, which is the administrative domain exposed by the Cyberpanel email service.
- Moved the activity log from `wp-content/cyberpersons-mailer.log` to the protected directory under `wp-content/uploads/cyberpanel-email/`.
- Admin redirects now use `wp_safe_redirect()` and carry notices through sanitized query args instead of string concatenation.

### Security

- Capability check (`manage_options`) added to every admin entry point (settings page, test email handler, check-now handler).
- `wp_unslash()` added on every superglobal read before sanitization.
- `sanitize_callback` added to every registered option; the API key is validated against a strict character whitelist and surfaces errors through `add_settings_error()`.
- All admin output is escaped at the point of output.

## [1.2.0]

Internal release. First working integration with the Cyberpanel email API (delivery tracking, account stats dashboard, activity log). Portuguese-only strings, options prefixed with `cyberpersons_`. Not published publicly.
