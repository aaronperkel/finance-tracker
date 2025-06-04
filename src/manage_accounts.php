<?php
include 'db.php'; // Include the database connection script
$feedback_message = ''; // For displaying success or error messages
$feedback_type = '';    // To style feedback messages (e.g., 'success' or 'error')

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Delete Account
    if (isset($_POST['delete_account_action']) && isset($_POST['delete_account_id'])) {
        $account_id_to_delete = filter_input(INPUT_POST, 'delete_account_id', FILTER_VALIDATE_INT);
        if ($account_id_to_delete) {
            try {
                // Check if account has associated balances
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM balances WHERE account_id = ?");
                $stmt_check->execute([$account_id_to_delete]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception("Cannot delete account as it has associated balance entries. Please remove those first.");
                }

                $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
                if ($stmt->execute([$account_id_to_delete])) {
                    $feedback_message = "Account deleted successfully.";
                    $feedback_type = 'success';
                } else {
                    throw new Exception("Failed to delete account. Database error.");
                }
            } catch (Exception $e) {
                $feedback_message = "Error deleting account: " . $e->getMessage();
                $feedback_type = 'error';
            }
        } else {
            $feedback_message = "Invalid account ID for deletion.";
            $feedback_type = 'error';
        }
    }
    // Handle Add Account
    elseif (isset($_POST['add_account'])) {
        $name_raw = filter_input(INPUT_POST, 'account_name');
        $name = $name_raw ? htmlspecialchars($name_raw, ENT_QUOTES, 'UTF-8') : '';

        $type_raw = filter_input(INPUT_POST, 'account_type');
        $type = $type_raw ? htmlspecialchars($type_raw, ENT_QUOTES, 'UTF-8') : '';

        $sort_order_raw = filter_input(INPUT_POST, 'sort_order', FILTER_SANITIZE_NUMBER_INT);
        $sort_order = ($sort_order_raw === '' || $sort_order_raw === null) ? null : (int)$sort_order_raw;

        // Note: $type is used in in_array check later. For that, the raw value might be more appropriate
        // if htmlspecialchars encoding interferes with the check. However, 'Asset' and 'Liability' don't contain special chars.
        // For now, we'll proceed with $type being the htmlspecialchars version.
        // If $type validation fails due to this, we might need to use $type_raw for validation and $type for DB/echoing.

        if (empty($name_raw) || empty($type_raw)) { // Validate based on raw inputs for emptiness
            $feedback_message = "Account Name and Type are required.";
            $feedback_type = 'error';
        } elseif (!in_array($type_raw, ['Asset', 'Liability'])) { // Validate $type_raw
            $feedback_message = "Invalid Account Type selected.";
            $feedback_type = 'error';
        } elseif ($sort_order !== null && !is_int($sort_order)) { // Allow NULL sort_order
            $feedback_message = "Sort Order must be a whole number or empty.";
            $feedback_type = 'error';
        } else {
            try {
                // Check if account name already exists
                // Use $name_raw for checking in DB to avoid issues with entities if names could have them
                $stmt_check_name = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE name = ?");
                $stmt_check_name->execute([$name_raw]); // Use $name_raw for DB check
                if ($stmt_check_name->fetchColumn() > 0) {
                    throw new Exception("An account with this name already exists.");
                }

                // When inserting into DB with prepared statements, raw values are fine for $name and $type
                // as PDO handles escaping. $name (htmlspecialchars version) is good for feedback.
                $stmt = $pdo->prepare("INSERT INTO accounts (name, type, sort_order) VALUES (?, ?, ?)");
                if ($stmt->execute([$name_raw, $type_raw, $sort_order])) { // Use $name_raw, $type_raw for DB
                    $feedback_message = "Account '" . htmlspecialchars($name_raw, ENT_QUOTES, 'UTF-8') . "' added successfully."; // Use htmlspecialchars for feedback
                    $feedback_type = 'success';
                } else {
                    throw new Exception("Database error occurred while adding the account.");
                }
            } catch (Exception $e) {
                $feedback_message = "Error adding account: " . $e->getMessage();
                $feedback_type = 'error';
            }
        }
    }
    // Handle Update Sort Orders
    elseif (isset($_POST['update_sort_orders'])) {
        if (isset($_POST['sort_orders_input']) && is_array($_POST['sort_orders_input'])) {
            $sort_orders_data = $_POST['sort_orders_input'];
            $pdo->beginTransaction();
            try {
                $update_stmt = $pdo->prepare("UPDATE accounts SET sort_order = ? WHERE id = ?");
                $updated_count = 0;
                // $error_encountered = false; // Not strictly needed with transaction throwing exception

                foreach ($sort_orders_data as $account_id => $sort_order_value) {
                    $account_id_sanitized = filter_var($account_id, FILTER_VALIDATE_INT);

                    $sort_order_sanitized = null;
                    if ($sort_order_value !== '') {
                        $sort_order_sanitized = filter_var($sort_order_value, FILTER_VALIDATE_INT);
                        if ($sort_order_sanitized === false && $sort_order_value !== '') {
                            // Allow empty string to become NULL, but non-empty non-integer is an error for this item
                            // Or, more strictly, throw an exception for the whole batch:
                            // throw new Exception("Invalid sort order value '".htmlspecialchars($sort_order_value)."' for account ID ".htmlspecialchars($account_id_sanitized));
                             $sort_order_sanitized = null; // Defaulting to NULL if invalid non-empty string.
                        }
                    }

                    if ($account_id_sanitized) {
                        if ($update_stmt->execute([$sort_order_sanitized, $account_id_sanitized])) {
                            if ($update_stmt->rowCount() > 0) {
                                $updated_count++;
                            }
                        } else {
                            throw new Exception("Database error updating sort order for account ID {$account_id_sanitized}.");
                        }
                    }
                }
                $pdo->commit();
                if ($updated_count > 0) {
                    $feedback_message = "{$updated_count} account(s) sort order updated successfully.";
                    $feedback_type = 'success';
                } else {
                    $feedback_message = "No sort orders were changed.";
                    $feedback_type = 'info';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback_message = "Error updating sort orders: " . $e->getMessage();
                $feedback_type = 'error';
            }
        } else {
            $feedback_message = "No sort order data submitted.";
            $feedback_type = 'error';
        }
    }
}


// Fetch existing accounts for display - always run after potential add/delete
$accounts_stmt = $pdo->query("SELECT id, name, type, sort_order FROM accounts ORDER BY sort_order ASC, name ASC");
$accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Finance App</title>
    <style>
        /* Basic Reset & Body Styling */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #eef1f5;
            color: #333;
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background-color: #2c3e50;
            color: #fff;
            padding: 1rem 2rem;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin-right: 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .navbar a:hover, .navbar a.active {
            background-color: #3498db;
        }
        .navbar .app-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: auto;
        }

        /* Main Content Container */
        .container {
            padding: 0 20px 20px 20px;
            max-width: 900px; /* Adjusted for potentially wider table */
            margin: 0 auto;
        }

        h1.page-title, h2.section-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        h2.section-title {
            margin-top: 30px;
        }

        /* Form Container Styling */
        .form-container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .form-container div.form-group {
             margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 10px; /* Slightly reduced padding */
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
            font-size: 0.95rem; /* Slightly reduced font size */
        }
        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        button[type="submit"] {
            background-color: #27ae60; /* Green */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
            /* width: 100%; */ /* No longer full width for all buttons */
        }
        button[type="submit"]:hover { background-color: #229954; }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f2f5; /* Lighter header */
            color: #333;
            font-weight: 600;
        }
        tr:hover {
             background-color: #f9f9f9;
        }
        td.actions-cell form {
            display: inline-block; /* Keep delete buttons on same line if space */
        }
        td.actions-cell button[type="submit"] {
            background-color: #e74c3c; /* Red for delete */
            padding: 6px 10px;
            font-size: 0.85rem;
        }
        td.actions-cell button[type="submit"]:hover {
            background-color: #c0392b;
        }


        /* Feedback Messages */
        .feedback-message-container {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .feedback-message-container.success {
            color: #1d6f42;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .feedback-message-container.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .feedback-message-container:empty { display: none; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .navbar a {
                margin-bottom: 5px;
                width: 100%;
                text-align: left;
            }
            .navbar .app-title { margin-bottom: 10px; }
            .container { padding: 0 15px 15px 15px; }

            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr { border: 1px solid #ccc; margin-bottom: 5px; border-radius: 0;}
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td:before {
                position: absolute;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: bold;
                content: attr(data-label);
            }
            td.actions-cell { padding-left: 6px; text-align: left; } /* Adjust action cell for mobile */
            td.actions-cell form { margin-right: 5px; margin-bottom: 5px;}
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <span class="app-title">Finance App</span>
            <a href="index.php">Dashboard</a>
            <a href="add_snapshot.php">Add Snapshot</a>
            <a href="calendar_hours.php">Hours Calendar</a>
            <a href="manage_accounts.php" class="active">Manage Accounts</a>
            <a href="admin_settings.php">Settings</a>
        </nav>
    </header>
    <main class="container">
        <h1 class="page-title">Manage Accounts</h1>

        <?php if (!empty($feedback_message)): ?>
            <div class="feedback-message-container <?= htmlspecialchars($feedback_type); ?>">
                <?= htmlspecialchars($feedback_message); ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Add New Account</h2>
        <div class="form-container">
            <form action="manage_accounts.php" method="POST">
                <div class="form-group">
                    <label for="account_name">Account Name:</label>
                    <input type="text" id="account_name" name="account_name" required>
                </div>
                <div class="form-group">
                    <label for="account_type">Account Type:</label>
                    <select id="account_type" name="account_type" required>
                        <option value="Asset">Asset</option>
                        <option value="Liability">Liability</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort_order">Sort Order (optional, lower numbers appear first):</label>
                    <input type="number" id="sort_order" name="sort_order" placeholder="e.g., 10, 20, 100">
                </div>
                <button type="submit" name="add_account">Add Account</button>
            </form>
        </div>

        <h2 class="section-title">Existing Accounts</h2>
        <?php if (empty($accounts)): ?>
            <p style="text-align:center;">No accounts found. Add one using the form above.</p>
        <?php else: ?>
            <form action="manage_accounts.php" method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th style="width: 120px;">Sort Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td data-label="Name"><?= htmlspecialchars($account['name']); ?></td>
                                <td data-label="Type"><?= htmlspecialchars($account['type']); ?></td>
                                <td data-label="Sort Order">
                                    <input type="number" name="sort_orders_input[<?= $account['id']; ?>]"
                                           value="<?= $account['sort_order'] !== null ? htmlspecialchars($account['sort_order']) : ''; ?>"
                                           placeholder="N/A" style="width: 80px; padding: 5px;">
                                </td>
                                <td class="actions-cell" data-label="Actions">
                                    <form action="manage_accounts.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="delete_account_id" value="<?= $account['id']; ?>">
                                        <button type="submit" name="delete_account_action" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px; margin-bottom: 20px;">
                    <button type="submit" name="update_sort_orders">Update All Sort Orders</button>
                </div>
            </form>
        <?php endif; ?>

    </main>
    <footer>
        <p style="text-align: center; margin-top: 30px; color: #777;">&copy; <?= date("Y"); ?> Finance Tracker</p>
    </footer>
</body>
</html>
