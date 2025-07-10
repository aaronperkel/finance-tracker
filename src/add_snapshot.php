<?php
include 'db.php'; // This should be at the very top

$feedback_message = ''; // For displaying "Saved!" or errors

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = $_POST['date'];
        // Basic validation for date
        if (empty($date)) {
            throw new Exception("Date is required.");
        }
        // Further date validation could be added here (e.g., format YYYY-MM-DD)

        $pdo->beginTransaction();
        $stmt_snapshot = $pdo->prepare("INSERT INTO snapshots(snapshot_date) VALUES(?)");
        $stmt_snapshot->execute([$date]);
        $sid = $pdo->lastInsertId();

        if (empty($_POST['balance']) || !is_array($_POST['balance'])) {
            throw new Exception("No balance data submitted.");
        }

        $stmt_balance = $pdo->prepare(
            "INSERT INTO balances(snapshot_id, account_id, balance) VALUES(?,?,?)"
        );
        foreach ($_POST['balance'] as $aid => $bal) {
            // Basic validation for balance: must be a number
            if (!is_numeric($bal)) {
                // Rollback and show error if any balance is invalid
                // $pdo->rollBack(); // Not strictly needed if exit happens before commit
                throw new Exception("Invalid balance submitted for account ID " . htmlspecialchars($aid) . ". Please enter numbers only.");
            }
            $stmt_balance->execute([$sid, $aid, $bal]);
        }
        $pdo->commit();
        $feedback_message = "Snapshot saved successfully for " . htmlspecialchars($date) . "!";
        // To prevent form resubmission issues and clear POST data, redirect or show a clean page.
        // For simplicity, we'll just set the message. A redirect is better in full apps.
        // header("Location: " . $_SERVER['PHP_SELF'] . "?status=success"); // Example redirect
        // exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $feedback_message = "Error: " . $e->getMessage();
    }
}

// Initialize an array to hold balances from the last snapshot
$last_snapshot_balances = [];

// Find the ID of the most recent snapshot
$latest_snapshot_stmt = $pdo->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC, id DESC LIMIT 1");
$latest_snapshot_id = $latest_snapshot_stmt->fetchColumn();

if ($latest_snapshot_id) {
    // If a snapshot is found, fetch its balances
    $balances_stmt = $pdo->prepare("SELECT account_id, balance FROM balances WHERE snapshot_id = ?");
    $balances_stmt->execute([$latest_snapshot_id]);
    $balances_raw = $balances_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($balances_raw as $b) {
        // Store balances in an associative array: account_id => balance
        $last_snapshot_balances[$b['account_id']] = $b['balance'];
    }
}
// This $last_snapshot_balances array will be used later in the HTML part to pre-fill values.

// If accounts appear duplicated on the page, the likely cause is duplicate entries
// in the `accounts` table in the database.
// Check with a query like: SELECT name, COUNT(*) FROM accounts GROUP BY name HAVING COUNT(*) > 1;
$accounts = $pdo->query("SELECT * FROM accounts ORDER BY sort_order, name")->fetchAll(); // Order by sort_order, then name

$page_title = 'Add Snapshot - Finance App';
$active_page = 'add_snapshot';
$page_specific_css = 'add_snapshot.css';
// $page_specific_js = 'add_snapshot.js'; // If it existed
include 'templates/header.php';
?>
<h1 class="page-title">Add New Snapshot</h1>

<div class="form-container">
    <?php if (!empty($feedback_message)): ?>
        <div
            class="feedback-message <?= strpos(strtolower($feedback_message), 'error') !== false || strpos(strtolower($feedback_message), 'invalid') !== false ? 'error' : 'success' ?>">
            <?= htmlspecialchars($feedback_message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="add_snapshot.php"> <!-- Ensure action points to self -->
        <div class="form-group">
            <label for="date">Snapshot Date:</label>
            <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Account Name</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td data-label="Account:"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['type']) ?>)
                        </td>
                        <td>
                            <input type="number" step="0.01" name="balance[<?= $a['id'] ?>]" placeholder="0.00"
                                value="<?= htmlspecialchars($last_snapshot_balances[$a['id']] ?? '') ?>" required>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="2" style="text-align:center;">No accounts found. Please add accounts via
                            initial_data.sql or other admin interface.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="submit">Save Balances</button>
    </form>
</div>
<?php include 'templates/footer.php'; ?>