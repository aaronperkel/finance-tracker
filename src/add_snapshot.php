<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO snapshots(snapshot_date) VALUES(?)")
        ->execute([$date]);
    $sid = $pdo->lastInsertId();
    foreach ($_POST['balance'] as $aid => $bal) {
        $pdo->prepare(
            "INSERT INTO balances(snapshot_id,account_id,balance)
       VALUES(?,?,?)"
        )->execute([$sid, $aid, $bal]);
    }
    $pdo->commit();
    echo "Saved!";
    exit;
}

$accounts = $pdo->query("SELECT * FROM accounts")->fetchAll();
?>
<!DOCTYPE html>
<html>

<body>
    <h1>New Snapshot</h1>
    <form method="post">
        Date: <input type="date" name="date" required><br><br>
        <table>
            <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td>
                        <input type="number" step="0.01" name="balance[<?= $a['id'] ?>]" placeholder="0.00" required>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table><br>
        <button>Save balances</button>
    </form>
</body>

</html>