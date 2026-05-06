# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
