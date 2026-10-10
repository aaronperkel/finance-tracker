# Finance Tracker: Usage Guide

For installation and configuration, see the [README](README.md).

## How the data fits together

- An **account** is anything you hold a balance in. It is either an **Asset** (checking, savings, retirement, money owed to you) or a **Liability** (credit cards, loans).
- A **snapshot** is a dated record of every active account's balance at that moment.
- **Net worth** = sum of asset balances − sum of liability balances. Enter liability balances as positive numbers (what you owe), not negatives.

## 1. Manage Accounts (`manage_accounts.php`)

Set up your accounts here before adding your first snapshot.

- **Add an account:** give it a unique name, choose Asset or Liability, and optionally a sort order.
- **Sort order:** accounts are listed by this number (lowest first), then alphabetically. Accounts without a number go last. Edit the numbers in the table and click **Save Order**. Leaving gaps (10, 20, 30, …) makes it easy to insert accounts later.
- **Active / Inactive:** click the status button to toggle it. Inactive accounts are left out of the snapshot form, the dashboard balances, the totals and the charts. Their past balances stay in the database and reappear if you reactivate the account.
- **Delete:** if the account has never appeared in a snapshot, it is removed. If it has history, it is deactivated instead so that past snapshots are preserved.

## 2. Add Snapshot (`add_snapshot.php`)

1. Pick the snapshot date (defaults to today).
2. Enter the current balance for each active account, grouped into Assets and Liabilities. Each field is pre-filled with that account's balance from the most recent snapshot.
3. Click **Save Snapshot**. Every active account needs a value; the snapshot is saved all at once or not at all.

You can save more than one snapshot for the same date. The dashboard always uses the most recently saved one for that date, so re-saving is how you correct a mistake.

## 3. Dashboard (`index.php`)

- **Net Worth:** the total from the latest snapshot, with the snapshot date and the assets/liabilities totals below it.
- **Account Balances:** each active account's balance in the latest snapshot.
- **History chart:**
  - **Net Worth** plots total net worth for each snapshot date.
  - **By Account** plots one line per active account.
  - **3M / 6M / 1Y / All** limits the chart to that time range.

Totals and history only ever include accounts that are currently active, so deactivating an account also removes it from past net-worth figures on the chart.
