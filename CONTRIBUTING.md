# Contributing

Thanks for your interest in improving **REST Email API Mailer**. Contributions of every size are welcome.

## Ground rules

- Be respectful. This project follows a standard open-source community code of conduct even when one is not shipped verbatim.
- Keep pull requests focused. Small, single-purpose PRs are reviewed and merged faster than sprawling ones.
- Open an issue first when the change is non-trivial so we can align on scope before code is written.

## Development setup

This plugin is intentionally single-file. You do not need Composer or npm to work on it.

```bash
git clone https://github.com/rafaelpessoap/rest-email-api-mailer.git
cd rest-email-api-mailer
```

For local testing, symlink or copy the folder into a WordPress instance's `wp-content/plugins/` directory and activate it from the admin panel.

## Coding standards

- Target **PHP 7.4+** and **WordPress 6.1+**.
- Follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). A `phpcs.xml.dist` is included for automated checks.
- Every user-facing string must be translatable via `__()`, `esc_html__()`, `esc_attr__()` or `_e()` with the text domain `rest-email-api-mailer`.
- Functional descriptions may reference the third-party service the plugin connects to (Cyberpanel / cyberpersons.com), but the plugin's own identity (display name, slug, branding) must stay neutral. We are not affiliated with the service.
- Every output must be escaped at the point of output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate).
- Every `$_GET` / `$_POST` / `$_REQUEST` access must pass through `wp_unslash()` and a sanitization function.
- Every admin action must be guarded by `current_user_can()` and a nonce.

## Running the linter

```bash
composer global require "squizlabs/php_codesniffer=*" "wp-coding-standards/wpcs=*"
phpcs --standard=phpcs.xml.dist rest-email-api-mailer.php uninstall.php
```

The same checks run automatically on every pull request via GitHub Actions.

## Translations

- Source strings live in `rest-email-api-mailer.php` (English).
- The translation template is `languages/rest-email-api-mailer.pot`.
- Per-locale files go in `languages/rest-email-api-mailer-{locale}.po` with the matching compiled `.mo`.

If you are adding a new locale, please submit the `.po` file; maintainers will compile the `.mo` during the release process.

## Commit messages

Use short, imperative commit messages. Include a body when the change needs context. Example:

```
Tighten API key sanitizer

Reject keys shorter than 8 chars and surface the error through
add_settings_error() so the admin sees why the value was not saved.
```

## Cutting a release

Releases are cut by pushing a `vX.Y.Z` git tag. A GitHub Actions workflow
then validates the version, builds the distribution ZIP and creates the
GitHub Release automatically.

1. Update the user-visible bits in **three places, to the same value**:
   - The `Version:` line in the main plugin file's header.
   - The `Stable tag:` line in `readme.txt`.
   - A new `## [X.Y.Z] - YYYY-MM-DD` section in `CHANGELOG.md` with the
     user-facing changes for the release.
2. Commit and push that change to `main`.
3. Tag and push:

   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

4. Watch the **Release** workflow in GitHub Actions. It will:
   - Verify that the tag, plugin header version and `Stable tag` match
     (the release fails if they do not).
   - Extract the matching section from `CHANGELOG.md` as release notes.
   - Package the plugin as a ZIP respecting `.distignore`
     (so `.git/`, `.github/`, `.wordpress-org/`, `CLAUDE.md` and other
     development-only files are excluded).
   - Create a GitHub Release with the ZIP attached.
   - Push trunk and the new tag to the WordPress.org SVN repository, and
     sync `.wordpress-org/` to the WP SVN `/assets/` directory — **only
     if** the repository has `WPORG_SVN_USERNAME` and `WPORG_SVN_PASSWORD`
     secrets configured. Before the plugin is approved on WordPress.org
     those steps are skipped automatically.

### Adding WordPress.org secrets (one-time)

Once the plugin is approved by the WordPress.org review team, you will
receive SVN credentials. Add them to the repository's GitHub secrets:

1. Repo → **Settings** → **Secrets and variables** → **Actions** → **New
   repository secret**.
2. Create `WPORG_SVN_USERNAME` with your wordpress.org username.
3. Create `WPORG_SVN_PASSWORD` with your wordpress.org password.

Future tag pushes will then deploy to WP.org in the same workflow run.

## Pull request checklist

- [ ] Changes follow the coding standards above.
- [ ] New or changed user-facing strings are translatable.
- [ ] New admin actions have capability check + nonce.
- [ ] `CHANGELOG.md` has an entry for user-visible changes.
- [ ] The plugin header `Version` and the `Stable tag` in `readme.txt` are bumped together when a release is being prepared.
- [ ] No personal, server or credential information has been added to the repository.
