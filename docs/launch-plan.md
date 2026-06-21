# WP DataBench — Launch Plan

Goal: ship the plugin as a distributable WordPress plugin (v1.0.0), ready for
self-hosted installs and WordPress.org submission.

The codebase is already feature-complete and passes `php -l` with zero syntax
errors. The remaining work is launch readiness: packaging, metadata, and i18n
plumbing.

---

## Step 1 — Version + plugin header metadata (`wp-databench.php`)

- Bump `Version:` and `WP_DATABENCH_VERSION` from `0.1.0` → `1.0.0`.
- Add a `Tested up to:` header (target `7.0`, the current stable as of June
  2026). **Must be verified by actually installing on WP 7.0 before publishing.**
- Add a `WP_DATABENCH_FILE` constant (`= __FILE__`) so the activation hook and
  text-domain loader can resolve the plugin basename reliably.

## Step 2 — Internationalisation plumbing

- Add `WP_DataBench_Settings::load_textdomain()` calling
  `load_plugin_textdomain( 'wp-databench', false, .../languages )`, hooked on
  `init`.
- Add `WP_DataBench_Settings::activate()` registered via
  `register_activation_hook()`:
  - Graceful bail if `PHP_VERSION < 7.4` (deactivates + `wp_die`).
  - Seeds default options with `add_option()` (never overwrites existing).
- Create `languages/` directory (with `.gitkeep`) to hold future `.pot`/`.mo`
  files. The `.pot` can be generated later with `wp i18n make-pot`.
- Re-enable the `WordPress.WP.I18n` PHPCS sniff (all translated PHP strings
  already carry the `wp-databench` text domain).

> Known limitation: hardcoded English strings in `templates/admin-page.php` and
> the JS SPA (`assets/js/app.js`) are not yet wrapped for translation. JS i18n
> would require `wp-i18n` + `wp_set_script_translations`. Tracked as post-launch
> work; does not block the 1.0.0 release.

## Step 3 — `readme.txt` (WordPress.org format)

- Create `readme.txt` with the standard .org headers:
  `=== Plugin Name ===`, `Contributors`, `Tags`, `Requires at least`,
  `Tested up to`, `Requires PHP`, `Stable tag`, `License`, and the
  `== Description ==`, `== Installation ==`, `== Frequently Asked Questions ==`,
  `== Screenshots ==`, `== Changelog ==` sections.
- `Stable tag: 1.0.0` must match the `Version:` header.
- This is the **hard requirement** for WordPress.org submission (the repo parser
  ignores `README.md`).

## Step 4 — Release workflow (`release.yml`)

- Add `readme.txt` to the list of files copied into the release zip so the
  packaged plugin is .org-compliant.
- Confirm tag convention: the workflow triggers on `v*.*.*` (e.g. `v1.0.0`),
  which matches the README's documented convention. The pre-existing `0.1.0` /
  `0.1.1` tags (no `v`) predate this and are intentionally not released.

## Step 5 — Verification

- `php -l` across all PHP files.
- `vendor/bin/phpcs` with the re-enabled I18n sniff.
- Manual: activate on a WP 7.0 + PHP 8.x test site, confirm the admin page
  loads, REST nonce flow works, and Settings persist.

## Step 6 — Release (manual, after verification)

- Commit all changes on `main`.
- Tag `v1.0.0` and push: `git tag v1.0.0 && git push origin v1.0.0`.
- The `release.yml` workflow builds `wp-databench.1.0.0.zip` (internal folder
  `wp-databench/`, matching the plugin slug; the `v` is stripped from the
  artifact name) and attaches it to a GitHub Release.
- **Self-hosted:** download the zip → Plugins → Add New → Upload.
- **WordPress.org:** request a slug at
  `https://wordpress.org/plugins/developers/add/`, then publish via SVN or the
  release zip. Update `Stable tag` + `Tested up to` as needed after real testing.
