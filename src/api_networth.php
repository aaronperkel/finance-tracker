<?php
include 'db.php';
$data = $pdo->query("
  SELECT
    s.snapshot_date AS date,
    SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END)
      - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END)
    AS networth
  FROM snapshots s
  JOIN balances b ON b.snapshot_id = s.id
  JOIN accounts a  ON a.id = b.account_id
  GROUP BY s.snapshot_date
  ORDER BY s.snapshot_date
")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);