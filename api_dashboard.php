<?php
include 'db.php';
header('Content-Type: application/json');

$inactive_ids = getInactiveAccountIds($pdo);

// Build a SQL snippet to exclude inactive accounts
$exclude_sql = '';
$exclude_params = [];
if (!empty($inactive_ids)) {
    $placeholders = implode(',', array_fill(0, count($inactive_ids), '?'));
    $exclude_sql = " AND a.id NOT IN ($placeholders)";
    $exclude_params = $inactive_ids;
}

// Subquery: only use the latest snapshot per date
$latest_per_date_sql = "(SELECT MAX(id) AS id FROM snapshots GROUP BY snapshot_date)";

// Get latest snapshot with account balances (active only)
$latest = $pdo->query("
    SELECT s.id, s.snapshot_date
    FROM snapshots s
    ORDER BY s.snapshot_date DESC, s.id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$current_balances = [];
$total_assets = 0;
$total_liabilities = 0;
$snapshot_date = null;

if ($latest) {
    $snapshot_date = $latest['snapshot_date'];
    $stmt = $pdo->prepare("
        SELECT a.name, a.type, b.balance
        FROM balances b
        JOIN accounts a ON a.id = b.account_id
        WHERE b.snapshot_id = ? $exclude_sql
        ORDER BY a.sort_order, a.name
    ");
    $stmt->execute(array_merge([$latest['id']], $exclude_params));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $bal = floatval($row['balance']);
        $current_balances[] = [
            'name' => $row['name'],
            'type' => $row['type'],
            'balance' => $bal
        ];
        if ($row['type'] === 'Asset') {
            $total_assets += $bal;
        } else {
            $total_liabilities += $bal;
        }
    }
}

// Net worth history (active accounts only, latest snapshot per date)
$stmt_history = $pdo->prepare("
    SELECT
        s.snapshot_date AS date,
        SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END)
          - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END)
        AS networth
    FROM snapshots s
    JOIN $latest_per_date_sql lpd ON lpd.id = s.id
    JOIN balances b ON b.snapshot_id = s.id
    JOIN accounts a ON a.id = b.account_id
    WHERE 1=1 $exclude_sql
    GROUP BY s.snapshot_date
    ORDER BY s.snapshot_date
");
$stmt_history->execute($exclude_params);
$history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// Per-account balance history (active accounts only, latest snapshot per date)
$stmt_acct = $pdo->prepare("
    SELECT
        s.snapshot_date AS date,
        a.name,
        a.type,
        b.balance
    FROM snapshots s
    JOIN $latest_per_date_sql lpd ON lpd.id = s.id
    JOIN balances b ON b.snapshot_id = s.id
    JOIN accounts a ON a.id = b.account_id
    WHERE 1=1 $exclude_sql
    ORDER BY s.snapshot_date, a.sort_order, a.name
");
$stmt_acct->execute($exclude_params);
$account_history = $stmt_acct->fetchAll(PDO::FETCH_ASSOC);

// Group account history by account name
$by_account = [];
foreach ($account_history as $row) {
    $name = $row['name'];
    if (!isset($by_account[$name])) {
        $by_account[$name] = [
            'type' => $row['type'],
            'data' => []
        ];
    }
    $by_account[$name]['data'][] = [
        'date' => $row['date'],
        'balance' => floatval($row['balance'])
    ];
}

echo json_encode([
    'snapshot_date' => $snapshot_date,
    'accounts' => $current_balances,
    'total_assets' => $total_assets,
    'total_liabilities' => $total_liabilities,
    'net_worth' => $total_assets - $total_liabilities,
    'net_worth_history' => $history,
    'account_history' => $by_account
]);