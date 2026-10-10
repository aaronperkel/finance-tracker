# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A small personal net-worth tracker: plain PHP 8 + PDO/MySQL, vanilla JS, Chart.js. No framework, no build step, no tests, no linter config.

**This working directory is the live production web root** (UVM silk server, `finance.aperkel.w3.uvm.edu`). Saving a file deploys it immediately. `.htaccess` gates the whole site behind UVM CAS (`uid=aperkel`), so it cannot be hit with `curl` without auth. PHP is not on the shell PATH here, so `php -l` is unavailable; verify changes by reasoning or by asking the user to load the page.

Git remote: `github.com/aaronperkel/finance-tracker`. In Oct 2026 the repo was flattened from `src/` to the root and stripped of the old hours/calendar/payday/rent/utilities features. Older commits and the screenshots in `img/` reflect that previous app.

## Runtime dependencies outside the repo

`db.php` loads `../vendor/autoload.php` and `../.env` (phpdotenv), i.e. from the directory *above* the web root (the user's home). Neither is in the repo. `composer.json` lives here but `vendor/` is installed one level up. Required env vars: `DBNAMEFINANCE`, `DBUSER`, `DBPASS`. The DB host `webdb.uvm.edu` is hardcoded in `db.php`.

## Architecture

Three pages, each a self-contained PHP file that handles its own POST at the top, then sets `$page_title`, `$active_page`, `$page_specific_css` and optionally `$page_specific_js` before `include 'templates/header.php'`. The header emits the nav, the CDN scripts (Chart.js 4, Luxon, the chartjs-adapter-luxon) and opens `<div class="container">`. `templates/footer.php` closes it and loads `js/$page_specific_js`.

- `index.php`: a static shell. All data comes from `js/dashboard.js` → `fetch('api_dashboard.php')`, a single JSON endpoint returning current balances, totals, net-worth history and per-account history. Range filtering (3M/6M/1Y/All) and the Net Worth vs By Account chart toggle happen client-side.
- `add_snapshot.php`: inserts one `snapshots` row plus one `balances` row per active account in a transaction. The form is pre-filled from the most recent snapshot.
- `manage_accounts.php`: add accounts, set sort order, toggle active/inactive, delete.

### Data model and invariants (see `finance_schema.sql`)

- `accounts` (type `Asset` | `Liability`, nullable `sort_order`) → `balances` ← `snapshots` (`snapshot_date`). Liabilities are stored as positive numbers; net worth = assets − liabilities.
- **Inactive accounts are not a column.** They are stored as a comma-separated ID list in `app_settings` row `setting_key='inactive_accounts'`, read and written via `getInactiveAccountIds()` / `setInactiveAccountIds()` in `db.php`. `setInactiveAccountIds` does an `UPDATE`, so that row must already exist (seeded by `initial_data.sql`). Every query that shows balances must exclude these IDs; follow the `$exclude_sql` pattern in `api_dashboard.php`.
- Multiple snapshots can share a date. History queries use only the latest snapshot per date (`MAX(id) GROUP BY snapshot_date`), and "current" means `ORDER BY snapshot_date DESC, id DESC LIMIT 1`.
- Deleting an account that has any balances only deactivates it, to preserve history. Hard delete happens only when it has no balances.
- The `logged_hours` and `rent_payments` tables (and the pay/tax keys mentioned in schema comments) are leftovers from the old app and are unused by the current code.

## Conventions

- Styling: dark theme driven by CSS custom properties in `css/common.css` `:root` (`--bg-*`, `--accent`, `--green`, …). Page CSS uses those vars; the chart palette is duplicated as `CHART_COLORS` in `js/dashboard.js`.
- Escape all output with `htmlspecialchars`; all SQL goes through PDO prepared statements (PDO is set to `ERRMODE_EXCEPTION`).
- Timezone is set to `America/New_York` in `db.php`.
