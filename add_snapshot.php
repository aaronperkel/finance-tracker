<?php
include 'db.php';

$feedback_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = $_POST['date'];
        if (empty($date)) {
            throw new Exception("Date is required.");
        }

        $pdo->beginTransaction();
        $stmt_snapshot = $pdo->prepare("INSERT INTO snapshots(snapshot_date) VALUES(?)");
        $stmt_snapshot->execute([$date]);
        $sid = $pdo->lastInsertId();

        if (empty($_POST['balance']) || !is_array($_POST['balance'])) {
            throw new Exception("No balance data submitted.");
        }

        $stmt_balance = $pdo->prepare("INSERT INTO balances(snapshot_id, account_id, balance) VALUES(?,?,?)");
        foreach ($_POST['balance'] as $aid => $bal) {
            if (!is_numeric($bal)) {
                throw new Exception("Invalid balance for account ID " . htmlspecialchars($aid) . ".");
            }
            $stmt_balance->execute([$sid, $aid, $bal]);
        }
        $pdo->commit();
        $feedback_message = "Snapshot saved for " . htmlspecialchars($date) . "!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $feedback_message = "Error: " . $e->getMessage();
    }
}

// Get last snapshot balances to pre-fill
$last_snapshot_balances = [];
$latest_snapshot_id = $pdo->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC, id DESC LIMIT 1")->fetchColumn();

if ($latest_snapshot_id) {
    $balances_stmt = $pdo->prepare("SELECT account_id, balance FROM balances WHERE snapshot_id = ?");
    $balances_stmt->execute([$latest_snapshot_id]);
    foreach ($balances_stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $last_snapshot_balances[$b['account_id']] = $b['balance'];
    }
}

// Only show active accounts
$inactive_ids = getInactiveAccountIds($pdo);
$all_accounts = $pdo->query("SELECT * FROM accounts ORDER BY sort_order, name")->fetchAll();
$accounts = array_filter($all_accounts, fn($a) => !in_array($a['id'], $inactive_ids));

$page_title = 'Add Snapshot';
$active_page = 'add_snapshot';
$page_specific_css = 'add_snapshot.css';
include 'templates/header.php';
?>
<h1 class="page-title">Add New Snapshot</h1>

<div class="form-container">
    <?php if (!empty($feedback_message)): ?>
        <div class="feedback-message <?= strpos(strtolower($feedback_message), 'error') !== false ? 'error' : 'success' ?>">
            <?= htmlspecialchars($feedback_message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="add_snapshot.php">
        <div class="form-group">
            <label for="date">Snapshot Date</label>
            <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <?php if (!empty($accounts)): ?>
            <div class="accounts-list">
                <?php
                $assets = array_filter($accounts, fn($a) => $a['type'] === 'Asset');
                $liabilities = array_filter($accounts, fn($a) => $a['type'] === 'Liability');
                ?>

                <?php if (!empty($assets)): ?>
                    <div class="snapshot-section-label">Assets</div>
                    <?php foreach ($assets as $a): ?>
                        <div class="snapshot-row">
                            <label class="snapshot-account-name" for="bal_<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></label>
                            <div class="snapshot-input-wrap">
                                <span class="input-prefix">$</span>
                                <input type="number" step="0.01" id="bal_<?= $a['id'] ?>" name="balance[<?= $a['id'] ?>]"
                                    placeholder="0.00"
                                    value="<?= htmlspecialchars($last_snapshot_balances[$a['id']] ?? '') ?>" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($liabilities)): ?>
                    <div class="snapshot-section-label">Liabilities</div>
                    <?php foreach ($liabilities as $a): ?>
                        <div class="snapshot-row">
                            <label class="snapshot-account-name" for="bal_<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></label>
                            <div class="snapshot-input-wrap">
                                <span class="input-prefix">$</span>
                                <input type="number" step="0.01" id="bal_<?= $a['id'] ?>" name="balance[<?= $a['id'] ?>]"
                                    placeholder="0.00"
                                    value="<?= htmlspecialchars($last_snapshot_balances[$a['id']] ?? '') ?>" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center; color: var(--text-muted); padding: 20px 0;">
                No active accounts. Add accounts on the <a href="manage_accounts.php" style="color: var(--accent);">Manage Accounts</a> page.
            </p>
        <?php endif; ?>

        <button type="submit" style="margin-top: 16px;">Save Snapshot</button>
    </form>
</div>
<?php include 'templates/footer.php'; ?>