<?php
include 'db.php';
$feedback_message = '';
$feedback_type = '';

$inactive_ids = getInactiveAccountIds($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle active/inactive
    if (isset($_POST['toggle_active']) && isset($_POST['toggle_account_id'])) {
        $aid = filter_input(INPUT_POST, 'toggle_account_id', FILTER_VALIDATE_INT);
        if ($aid) {
            if (in_array($aid, $inactive_ids)) {
                $inactive_ids = array_diff($inactive_ids, [$aid]);
            } else {
                $inactive_ids[] = $aid;
            }
            setInactiveAccountIds($pdo, $inactive_ids);
            $feedback_message = "Account status updated.";
            $feedback_type = 'success';
        }
    }
    // Delete account
    elseif (isset($_POST['delete_account_action']) && isset($_POST['delete_account_id'])) {
        $aid = filter_input(INPUT_POST, 'delete_account_id', FILTER_VALIDATE_INT);
        if ($aid) {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM balances WHERE account_id = ?");
                $stmt_check->execute([$aid]);
                $has_history = $stmt_check->fetchColumn() > 0;

                if ($has_history) {
                    // Has history: mark inactive to preserve data
                    if (!in_array($aid, $inactive_ids)) {
                        $inactive_ids[] = $aid;
                        setInactiveAccountIds($pdo, $inactive_ids);
                    }
                    $feedback_message = "Account deactivated (historical data preserved).";
                    $feedback_type = 'success';
                } else {
                    // No history, safe to hard delete
                    $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
                    $stmt->execute([$aid]);
                    $inactive_ids = array_diff($inactive_ids, [$aid]);
                    setInactiveAccountIds($pdo, $inactive_ids);
                    $feedback_message = "Account deleted.";
                    $feedback_type = 'success';
                }
            } catch (Exception $e) {
                $feedback_message = "Error: " . $e->getMessage();
                $feedback_type = 'error';
            }
        }
    }
    // Add account
    elseif (isset($_POST['add_account'])) {
        $name_raw = filter_input(INPUT_POST, 'account_name');
        $type_raw = filter_input(INPUT_POST, 'account_type');
        $sort_order_raw = filter_input(INPUT_POST, 'sort_order', FILTER_SANITIZE_NUMBER_INT);
        $sort_order = ($sort_order_raw === '' || $sort_order_raw === null) ? null : (int) $sort_order_raw;

        if (empty($name_raw) || empty($type_raw)) {
            $feedback_message = "Account Name and Type are required.";
            $feedback_type = 'error';
        } elseif (!in_array($type_raw, ['Asset', 'Liability'])) {
            $feedback_message = "Invalid Account Type.";
            $feedback_type = 'error';
        } else {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE name = ?");
                $stmt_check->execute([$name_raw]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception("An account with this name already exists.");
                }
                $stmt = $pdo->prepare("INSERT INTO accounts (name, type, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$name_raw, $type_raw, $sort_order]);
                $feedback_message = "Account '" . htmlspecialchars($name_raw) . "' added.";
                $feedback_type = 'success';
            } catch (Exception $e) {
                $feedback_message = "Error: " . $e->getMessage();
                $feedback_type = 'error';
            }
        }
    }
    // Update sort orders
    elseif (isset($_POST['update_sort_orders'])) {
        if (isset($_POST['sort_orders_input']) && is_array($_POST['sort_orders_input'])) {
            $pdo->beginTransaction();
            try {
                $update_stmt = $pdo->prepare("UPDATE accounts SET sort_order = ? WHERE id = ?");
                $count = 0;
                foreach ($_POST['sort_orders_input'] as $account_id => $val) {
                    $id = filter_var($account_id, FILTER_VALIDATE_INT);
                    $order = ($val === '') ? null : filter_var($val, FILTER_VALIDATE_INT);
                    if ($id) {
                        $update_stmt->execute([$order === false ? null : $order, $id]);
                        if ($update_stmt->rowCount() > 0) $count++;
                    }
                }
                $pdo->commit();
                $feedback_message = $count > 0 ? "{$count} account(s) updated." : "No changes.";
                $feedback_type = $count > 0 ? 'success' : 'info';
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback_message = "Error: " . $e->getMessage();
                $feedback_type = 'error';
            }
        }
    }

    // Refresh inactive list after changes
    $inactive_ids = getInactiveAccountIds($pdo);
}

$accounts = $pdo->query("SELECT id, name, type, sort_order FROM accounts ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Sort: active first, then inactive
usort($accounts, function ($a, $b) use ($inactive_ids) {
    $a_inactive = in_array($a['id'], $inactive_ids) ? 1 : 0;
    $b_inactive = in_array($b['id'], $inactive_ids) ? 1 : 0;
    if ($a_inactive !== $b_inactive) return $a_inactive - $b_inactive;
    $a_sort = $a['sort_order'] ?? PHP_INT_MAX;
    $b_sort = $b['sort_order'] ?? PHP_INT_MAX;
    if ($a_sort !== $b_sort) return $a_sort - $b_sort;
    return strcmp($a['name'], $b['name']);
});

$page_title = 'Manage Accounts';
$active_page = 'manage_accounts';
$page_specific_css = 'manage_accounts.css';
include 'templates/header.php';
?>
<h1 class="page-title">Manage Accounts</h1>

<?php if (!empty($feedback_message)): ?>
    <div class="feedback-message <?= htmlspecialchars($feedback_type); ?>">
        <?= htmlspecialchars($feedback_message); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <h2 style="margin-bottom: 16px;">Add New Account</h2>
    <form action="manage_accounts.php" method="POST">
        <div class="form-group">
            <label for="account_name">Account Name</label>
            <input type="text" id="account_name" name="account_name" placeholder="e.g. Checking, Credit Card" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="account_type">Type</label>
                <select id="account_type" name="account_type" required>
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                </select>
            </div>
            <div class="form-group">
                <label for="sort_order">Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" placeholder="Optional">
            </div>
        </div>
        <button type="submit" name="add_account">Add Account</button>
    </form>
</div>

<?php if (!empty($accounts)): ?>
<form action="manage_accounts.php" method="POST">
    <div class="card">
        <h2 style="margin-bottom: 16px;">Accounts</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="width: 90px;">Order</th>
                    <th style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account):
                    $is_inactive = in_array($account['id'], $inactive_ids);
                ?>
                    <tr class="<?= $is_inactive ? 'inactive-row' : '' ?>">
                        <td data-label="Name"><?= htmlspecialchars($account['name']); ?></td>
                        <td data-label="Type">
                            <span class="type-badge <?= $account['type'] === 'Asset' ? 'badge-asset' : 'badge-liability' ?>">
                                <?= htmlspecialchars($account['type']); ?>
                            </span>
                        </td>
                        <td data-label="Status">
                            <form action="manage_accounts.php" method="POST" style="display:inline;">
                                <input type="hidden" name="toggle_account_id" value="<?= $account['id']; ?>">
                                <button type="submit" name="toggle_active" class="toggle-btn <?= $is_inactive ? 'toggle-inactive' : 'toggle-active' ?>">
                                    <?= $is_inactive ? 'Inactive' : 'Active' ?>
                                </button>
                            </form>
                        </td>
                        <td data-label="Order">
                            <input type="number" name="sort_orders_input[<?= $account['id']; ?>]"
                                value="<?= $account['sort_order'] !== null ? htmlspecialchars($account['sort_order']) : ''; ?>"
                                placeholder="--" class="sort-input">
                        </td>
                        <td class="actions-cell" data-label="Actions">
                            <form action="manage_accounts.php" method="POST" style="display:inline;">
                                <input type="hidden" name="delete_account_id" value="<?= $account['id']; ?>">
                                <button type="submit" name="delete_account_action" class="delete-button"
                                    onclick="return confirm('Delete this account? If it has history, it will be deactivated instead.');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align: right; margin-top: 16px;">
            <button type="submit" name="update_sort_orders" style="width: auto; padding: 8px 20px;">Save Order</button>
        </div>
    </div>
</form>
<?php else: ?>
    <p style="text-align:center; color: var(--text-muted);">No accounts yet.</p>
<?php endif; ?>
<?php include 'templates/footer.php'; ?>