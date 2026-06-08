# WP DataBench — Implementation Plan

## Overview

WP DataBench is a WordPress admin plugin for browsing and editing the site database directly, similar to [Adminer](https://www.adminer.org/). It is built entirely with native WordPress APIs — no external libraries, no build step.

---

## Architecture

```
wp-plugin-databench/
├── wp-databench.php              # Bootstrap — plugin header, constants, hook registration
├── uninstall.php                 # Cleanup on plugin delete
├── includes/
│   ├── class-access-guard.php   # Permission callback (manage_options gate)
│   ├── class-db-explorer.php    # All DB operations via $wpdb
│   ├── class-rest-api.php       # REST route registration
│   └── class-admin-page.php     # Admin menu page + asset enqueue
├── assets/
│   ├── js/app.js                # Vanilla JS SPA (no build step)
│   └── css/style.css
├── templates/
│   └── admin-page.php           # HTML shell / SPA mount point
└── docs/
    └── implementation-plan.md
```

### Stack

| Layer | Technology |
|---|---|
| Server | PHP 7.4+, WordPress 6.0+ REST API |
| Database | `$wpdb` (no external DB library) |
| Frontend | Vanilla JS ES5 (no build step, no framework) |
| Styles | Custom CSS (WP admin colour palette) |

### Data flow

1. Admin page registers a shell HTML template and enqueues `app.js` + `style.css`
2. `wp_localize_script` passes `restUrl`, `nonce`, and `dbName` to the JS app
3. The JS app calls REST endpoints under `wp-databench/v1/`
4. Every request carries `X-WP-Nonce: <wp_rest nonce>` — WP authenticates via cookie
5. Every endpoint runs the `manage_options` permission callback before executing

---

## Security Model

- **Authentication**: WP REST API cookie auth + `wp_rest` nonce on every request
- **Authorisation**: `manage_options` capability required on all 8 REST endpoints and the admin page render
- **SQL injection — identifiers**: Table and column names validated against `SHOW TABLES` / `DESCRIBE` whitelist, then backtick-quoted via `qi()`
- **SQL injection — values**: All values go through `$wpdb->prepare()`, `$wpdb->insert()`, `$wpdb->update()`, or `$wpdb->delete()`
- **Column injection on write**: `array_intersect_key` against validated column list prevents extra fields
- **SQL Runner**: SELECT-only enforced server-side via regex; hard row cap of 1,000 rows auto-appended when no `LIMIT` is present

---

## REST API

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/wp-databench/v1/tables` | List all tables with row counts |
| `GET` | `/wp-databench/v1/tables/{table}/structure` | Column definitions (name, type, nullable, key, default, extra) |
| `GET` | `/wp-databench/v1/tables/{table}/rows` | Paginated rows (`?page=&search=&orderby=&order=`) |
| `POST` | `/wp-databench/v1/tables/{table}/rows` | Insert a row |
| `GET` | `/wp-databench/v1/tables/{table}/rows/{pk}` | Fetch a single row by primary key |
| `PUT` | `/wp-databench/v1/tables/{table}/rows/{pk}` | Update a row |
| `DELETE` | `/wp-databench/v1/tables/{table}/rows/{pk}` | Delete a row |
| `POST` | `/wp-databench/v1/query` | Execute a raw SELECT query |

---

## Phase 1 — Browse & Edit ✅

**Goal**: Working data browser with full CRUD.

- Dark sidebar listing all tables with approximate row counts (↺ refresh)
- Paginated data grid (25 rows/page), sortable columns, full-text search (Enter)
- Edit modal — pre-filled form, readonly primary key field
- New Row modal — empty form, insert on submit
- Delete with confirmation dialog
- Tables without a primary key rendered read-only (no edit/delete buttons)
- Full-viewport layout with dynamic height calculation
- Toast notifications for success and error states
- DB name displayed in header

---

## Phase 2 — Structure & SQL Runner ✅

**Goal**: Read-only introspection and raw SQL execution.

### Structure view

- Browse / Structure tab switcher in the toolbar
- Shows each column: name (monospace), type (blue monospace), nullable, key badge (PRI / UNI / MUL), default, extra (`auto_increment` etc.)
- Sticky header row

### SQL Runner

- Accessible via `⌨ SQL Runner` button in the header bar (global mode — not per-table)
- Monospace textarea, resizable
- `Ctrl+Enter` or Run button to execute
- SELECT-only enforced server-side (403 for anything else)
- Hard 1,000-row cap auto-applied when no `LIMIT` present; cap warning shown in result meta bar
- Results rendered in the same grid style with sticky row-count / timing bar
- **CSV export**: client-side, RFC-4180 compliant (handles commas, quotes, newlines), timestamped filename

---

## Phase 3 — Settings & Access Controls (planned)

- Settings page (Settings API):
  - `wp_databench_enabled` — kill switch
  - `wp_databench_read_only` — disable all write operations globally
  - `wp_databench_ip_allowlist` — comma-separated IP allowlist
  - `wp_databench_unlock_password` — extra password gate for write operations
- Read-only mode: hides New Row / Edit / Delete buttons and blocks write REST endpoints
- IP allowlist enforced on admin page render and REST permission callback
- Multi-site awareness (`$wpdb->tables('all')`)
- CSV export on the Browse grid (not just SQL Runner)
- SQL Runner: allow `WITH ... SELECT` (CTEs), export results to CSV
