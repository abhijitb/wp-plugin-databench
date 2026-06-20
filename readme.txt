=== WP DataBench ===
Contributors: @abhijitbhatnagar
Tags: database, admin, browse, edit, sql, adminer, dbexplorer
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse and edit your WordPress database from the admin area — an Adminer-style tool that lives inside wp-admin.

== Description ==

WP DataBench is a WordPress admin plugin for browsing and editing your site database directly. Think of it as [Adminer](https://www.adminer.org/) built into the WP admin area — no external tools, no build step, no dependencies.

It is built entirely with native WordPress APIs (REST API, Settings API, `$wpdb`) and a small vanilla-JS single-page app. Access is restricted to administrators (`manage_options`).

= Browse & Edit =

* Sidebar table list with exact row counts
* Paginated data grid (25 rows/page) with column sorting and full-text search
* Insert, edit, and delete rows via modal forms
* Tables without a primary key are shown read-only
* CSV export directly from the data grid

= Structure & SQL Runner =

* Structure view — per-table column details: name, type, nullable, key (PRI/UNI/MUL), default, extra
* SQL Runner — execute raw SELECT queries with row count, execution time, and CSV export
* 1,000-row safety cap auto-applied when no LIMIT is present

= Settings & Access Controls =

* Kill switch — disable the plugin without deactivating it
* Read-only mode — block all write operations globally (UI + server-side)
* IP allowlist — restrict access to specific IP addresses
* Write unlock password — require a password before write operations; issues a session-scoped token valid for 1 hour

= Security =

Every REST request is authenticated via the WP REST nonce and verified server-side. All database identifiers are validated against a live whitelist, and values are passed through `$wpdb->prepare()` or the `$wpdb` write helpers. The SQL Runner accepts SELECT statements only.

== Installation ==

1. Upload the `wp-plugin-databench` folder to `wp-content/plugins/` (or install via Plugins → Add New → Upload Plugin).
2. Activate the plugin on the Plugins screen.
3. Navigate to **DataBench** in the admin sidebar (database icon, near the bottom).

No build step required. No external dependencies.

== Frequently Asked Questions ==

= Who can access DataBench? =

Only users with the `manage_options` capability (Administrators). Every REST endpoint and the admin page enforce this server-side.

= Can I restrict which IPs can use it? =

Yes. Add one IP address per line under **DataBench → Settings → IP allowlist**. Leave blank to allow all IPs.

= How do I protect write operations? =

Enable **Read-only mode** to block all writes, or set a **Write unlock password**. With a password set, users must unlock DataBench before insert/update/delete; the unlock token expires after one hour.

= Is the SQL Runner safe? =

The SQL Runner accepts SELECT statements only (enforced server-side). A hard 1,000-row cap is appended automatically when no LIMIT is present.

== Screenshots ==

1. Table browser with sidebar, data grid, sorting and search
2. Edit/New Row modal forms
3. Structure view showing column metadata
4. SQL Runner with results and CSV export
5. Settings page (kill switch, read-only, IP allowlist, write password)

== Changelog ==

= 1.0.0 =
* Initial public release.
* Browse, search, sort, and edit any database table.
* Structure view with full column metadata.
* SELECT-only SQL Runner with 1,000-row safety cap and CSV export.
* Settings: kill switch, read-only mode, IP allowlist, write-unlock password.
* REST API under `wp-databench/v1` with nonce + capability checks.
