# WP DataBench

A WordPress admin plugin for browsing and editing your database — similar to [Adminer](https://www.adminer.org/) but living inside the WP admin area.

---

## Features

### Phase 1 — Browse & Edit
- Sidebar table list with row counts
- Paginated data grid with column sorting and full-text search
- Insert, edit, and delete rows via modal forms
- Tables without a primary key are shown read-only

### Phase 2 — Structure & SQL Runner
- **Structure view** — per-table column details: name, type, nullable, key (PRI/UNI/MUL), default, extra
- **SQL Runner** — execute raw SELECT queries with row count, execution time, and CSV export
- 1,000-row safety cap auto-applied when no `LIMIT` is present

### Coming in Phase 3
- Settings page: read-only mode, IP allowlist, extra unlock password, kill switch
- CSV export on the data grid
- Multi-site support

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

The SQL Runner accepts SELECT statements only. See `docs/implementation-plan.md` for the full security model.

---

## Project structure

```
wp-plugin-databench/
├── wp-databench.php        # Plugin bootstrap
├── uninstall.php
├── includes/
│   ├── class-access-guard.php
│   ├── class-db-explorer.php
│   ├── class-rest-api.php
│   └── class-admin-page.php
├── assets/
│   ├── js/app.js
│   └── css/style.css
├── templates/
│   └── admin-page.php
└── docs/
    └── implementation-plan.md
```

---

## License

GPL-2.0+
