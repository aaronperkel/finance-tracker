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

// If accounts appear duplicated on the page, the likely cause is duplicate entries
// in the `accounts` table in the database.
// Check with a query like: SELECT name, COUNT(*) FROM accounts GROUP BY name HAVING COUNT(*) > 1;
$accounts = $pdo->query("SELECT * FROM accounts ORDER BY type, name")->fetchAll(); // Order for consistency
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Snapshot - Finance App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Basic Reset & Body Styling - Consistent with dashboard.php */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #eef1f5;
            color: #333;
            line-height: 1.6;
        }

        /* Navigation Bar - Consistent with dashboard.php */
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
            padding: 0 20px 20px 20px; /* Added bottom padding */
            max-width: 800px;
            margin: 0 auto;
        }

        h1.page-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
        }

        /* Form Container Styling */
        .form-container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-container div.form-group { /* Grouping for label + input */
             margin-bottom: 15px;
        }


        /* Form elements - Consistent styling */
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="date"], input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
            font-size: 1rem;
        }
        input[type="date"]:focus, input[type="number"]:focus {
            border-color: #3498db;
            outline: none;
        }
        button[type="submit"] {
            background-color: #27ae60; /* Green */
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
            width: 100%;
            margin-top: 10px; /* Space above button */
        }
        button[type="submit"]:hover { background-color: #229954; }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px; /* Space below table */
        }
        th, td {
            text-align: left;
            padding: 12px; /* Increased padding */
            border-bottom: 1px solid #ddd; /* Lighter border */
        }
        th {
            background-color: #f9f9f9; /* Light background for headers */
            color: #333;
            font-weight: 600;
        }
        td:first-child { /* Account name column */
            width: 60%;
        }
        td:last-child { /* Balance input column */
            width: 40%;
        }
        tr:hover {
             background-color: #f5f5f5; /* Hover effect for rows */
        }

        /* Feedback Messages */
        .feedback-message {
            margin-top: 0; /* Reset margin as it's inside form-container */
            margin-bottom: 20px; /* Space below feedback if it's shown before form */
            padding: 12px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .feedback-message.success {
            color: #1d6f42;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .feedback-message.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .feedback-message:empty { display: none; }


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
            td:first-child, td:last-child { width: auto; display:block; text-align:center; }
            td:last-child input { text-align:center; }
            td:first-child::before { content: attr(data-label); font-weight:bold; display:block; margin-bottom:5px;}
            thead { display:none; } /* Hide table headers on small screens if using data-label */
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_snapshot.php" class="active">Add Snapshot</a>
        <a href="calendar_hours.php">Hours Calendar</a>
        <a href="admin_settings.php">Settings</a>
    </nav>

    <div class="container">
        <h1 class="page-title">Add New Snapshot</h1>

        <div class="form-container">
            <?php if (!empty($feedback_message)): ?>
                <div class="feedback-message <?= strpos(strtolower($feedback_message), 'error') !== false || strpos(strtolower($feedback_message), 'invalid') !== false ? 'error' : 'success' ?>">
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
                                <td data-label="Account:"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['type']) ?>)</td>
                                <td>
                                    <input type="number" step="0.01" name="balance[<?= $a['id'] ?>]" placeholder="0.00" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="2" style="text-align:center;">No accounts found. Please add accounts via initial_data.sql or other admin interface.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="submit">Save Balances</button>
            </form>
        </div>
    </div>
</body>
</html>