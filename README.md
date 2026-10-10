# Finance Tracker

A small personal net-worth tracker. You record the balance of each account as a dated **snapshot**, and the dashboard shows your current net worth, a per-account breakdown, and how both have changed over time.

Built with plain PHP 8 (PDO + MySQL), vanilla JavaScript and Chart.js. There is no framework and no build step.

## Features

- **Dashboard:** current net worth with assets/liabilities totals, the latest balance of every active account, and a history chart that switches between total net worth and individual accounts, with 3M / 6M / 1Y / All ranges.
- **Add Snapshot:** enter today's balance for each active account. The form is pre-filled with the previous snapshot's values, so you only change what moved.
- **Manage Accounts:** add asset or liability accounts, set their display order, mark them active/inactive, or delete them. Inactive accounts are hidden from the dashboard, its totals and the snapshot form, but their history is kept.

## Requirements

- PHP 8.1+ with the PDO MySQL extension
- MySQL or MariaDB
- Composer
- Apache (or another web server)

## Setup

1. **Clone and install dependencies.** The app expects `vendor/` and `.env` to live in the directory *above* the web root, so files that aren't public stay out of it:

   ```bash
   git clone https://github.com/aaronperkel/finance-tracker.git public_html
   cd public_html
   COMPOSER_VENDOR_DIR=../vendor composer install
   ```

   Use whatever directory your web server serves in place of `public_html`.

2. **Create the database.**

   ```bash
   mysql -u USER -p DBNAME < finance_schema.sql
   mysql -u USER -p DBNAME < initial_data.sql
   ```

   `initial_data.sql` adds a starter set of accounts and the required `inactive_accounts` setting. Edit the account list before importing, or change it afterwards on the Manage Accounts page.

3. **Configure the connection.** Create `.env` next to `vendor/` (the web root's parent) using `.env.example` as a template:

   ```
   DBNAMEFINANCE="your_database"
   DBUSER="your_user"
   DBPASS="your_password"
   ```

   The database host, `webdb.uvm.edu`, is hardcoded in `db.php`. Change it there if you host the database elsewhere.

4. **Restrict access.** The included `.htaccess` requires UVM CAS login for a single user. Replace it with whatever authentication fits your server; the app itself has no login.

See [INSTRUCTIONS.md](INSTRUCTIONS.md) for a walkthrough of each page and how the numbers are calculated.

## License

MIT. See [LICENSE](LICENSE).
