# WP DataBench

A WordPress admin plugin for browsing and editing your database — similar to [Adminer](https://www.adminer.org/) but living inside the WP admin area.

---

## Features

### Browse & Edit
- Sidebar table list with row counts
- Paginated data grid with column sorting and full-text search
- Insert, edit, and delete rows via modal forms
- Tables without a primary key are shown read-only
- CSV export directly from the data grid

### Structure & SQL Runner
- **Structure view** — per-table column details: name, type, nullable, key (PRI/UNI/MUL), default, extra
- **SQL Runner** — execute raw SELECT queries with row count, execution time, and CSV export
- 1,000-row safety cap auto-applied when no `LIMIT` is present

### Settings & Access Controls
- **Kill switch** — disable the plugin without deactivating it
- **Read-only mode** — block all write operations globally (hides UI controls and enforces server-side)
- **IP allowlist** — restrict access to specific IP addresses
- **Write unlock password** — require a password before write operations are permitted; issues a session-scoped token valid for 1 hour
- Settings live under **DataBench → Settings** in the admin menu

### CI/CD
- PHP lint via PHPCS + WordPress Coding Standards on push/PR
- JS lint via ESLint on push/PR
- Automated release zip built and attached to GitHub Release on `v*.*.*` tag

---

## Installation

1. Copy or symlink the `wp-plugin-databench` folder into `wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Navigate to **DataBench** in the admin sidebar (database icon, near the bottom)

No build step required. No external dependencies.

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| MySQL | 5.7 / MariaDB 10.3 |

---

## Security

Access is restricted to users with the `manage_options` capability (Administrators only).

Every REST request is authenticated via the WP REST nonce (`wp_rest`) and verified server-side. All database identifiers (table and column names) are validated against a live whitelist before use. Values are always passed through `$wpdb->prepare()` or the `$wpdb` write helpers.

Write operations have an additional optional layer: an IP allowlist and/or an unlock password can be configured in Settings. Write tokens are stored as user-scoped WordPress transients and expire after one hour.

The SQL Runner accepts SELECT statements only. See `docs/implementation-plan.md` for the full security model.

---

## Project structure

```
wp-plugin-databench/
├── wp-databench.php        # Plugin bootstrap
├── uninstall.php
├── includes/
│   ├── class-wp-databench-settings.php
│   ├── class-wp-databench-access-guard.php
│   ├── class-wp-databench-db-explorer.php
│   ├── class-wp-databench-rest-api.php
│   └── class-wp-databench-admin-page.php
├── assets/
│   ├── js/app.js
│   └── css/style.css
├── templates/
│   └── admin-page.php
├── .github/workflows/
│   ├── lint.yml
│   └── release.yml
└── docs/
    └── implementation-plan.md
```

---

## License

GPL-2.0+
