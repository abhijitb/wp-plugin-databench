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

## Phase 3 — CI/CD ✅

Three GitHub Actions workflows living in `.github/workflows/`.

### `lint.yml` — triggered on push and pull_request to `main`

**PHP lint** (`php-lint` job)
- Runs on `ubuntu-latest`
- Installs PHP 8.2 via `shivammathur/setup-php`
- Installs Composer dev dependencies (`squizlabs/php_codesniffer` + `wp-coding-standards/wpcs`)
- Registers WPCS with PHPCS: `vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs`
- Runs: `vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=vendor/ .`

**JS lint** (`js-lint` job)
- Runs on `ubuntu-latest`
- Installs Node.js 20 via `actions/setup-node`
- Installs ESLint: `npm install --save-dev eslint`
- Config file: `.eslintrc.json` with `env: {browser: true, es5: true}`, `extends: eslint:recommended`, overrides for WP globals (`wpDataBench`, `wp`)
- Runs: `npx eslint assets/js/`

Both jobs are independent and run in parallel.

### `release.yml` — triggered on `push` with tag pattern `v*.*.*`

**`build-zip` job**
- Checks out the repo
- Creates a staging directory: `wp-plugin-databench/`
- Copies all plugin files except dev artifacts: excludes `.git`, `.github`, `node_modules`, `vendor`, `*.lock`, `composer.json`, `package.json`, `.eslintrc.json`, `docs/`
- Zips the staging directory: `zip -r wp-plugin-databench-{tag}.zip wp-plugin-databench/`
- Creates a GitHub Release via `softprops/action-gh-release` with the zip attached
- The release body is auto-populated from the tag annotation message

---

## Phase 4 — Settings & Access Controls ✅

**Server-side (`includes/`)**

- `class-wp-databench-settings.php` — Settings API integration
  - `wp_databench_enabled` — kill switch; admin page shows disabled notice when off
  - `wp_databench_read_only` — blocks all write REST endpoints and hides UI write controls
  - `wp_databench_ip_allowlist` — newline-separated IPs checked on every REST request
  - `wp_databench_unlock_password` — write token gate; stored as `wp_hash_password` hash
  - Sentinel value `**clear**` used by the JS "Remove password" checkbox to trigger hash deletion
- `class-wp-databench-access-guard.php` — rewritten with three concerns:
  - `permission_callback()` — checks enabled flag, IP allowlist, and `manage_options`
  - `write_permission_callback()` — extends above; also checks read-only flag and write token header
  - `unlock()` — POST handler; verifies password, issues a 32-char WP-generated token via transient (1 hr TTL, user-scoped)
- `class-wp-databench-rest-api.php` — write routes use `write_permission_callback`; `/unlock` route added
- `class-wp-databench-admin-page.php` — Settings submenu registered; `readOnly` and `writeLocked` passed to JS via `wp_localize_script`
- `uninstall.php` — deletes all 4 options on plugin removal

**Front-end (`assets/`)**

- `app.js`
  - `state.writeToken` — holds session write token after unlock
  - `state.currentRows` — holds last loaded rows for browse CSV export
  - `apiFetch()` — automatically injects `X-DataBench-Write-Token` header when token is held
  - `canWrite()` — returns true only when not read-only and either unlocked or no password required
  - `init()` — injects read-only badge (`.databench-readonly-badge`) or lock button (`.databench-lock-btn`) into header based on server flags
  - `openUnlockModal()` — password modal → `POST /unlock` → stores token → refreshes grid; lock button updates to unlocked state
  - `renderGrid()` — respects `canWrite()` for Edit/Delete/New Row visibility; adds CSV export button to toolbar
- `style.css`
  - `.databench-readonly-badge` — orange pill badge in header
  - `.databench-lock-btn` / `.databench-lock-btn.unlocked` — amber/green lock button states
  - `.databench-browse-export-btn` — CSV export button on the browse grid toolbar
  - `input[type="password"]` styles for the unlock modal
