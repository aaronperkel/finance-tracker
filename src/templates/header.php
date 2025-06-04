<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Finance App'; ?></title>
    <link rel="stylesheet" href="css/common.css">
    <?php if (isset($page_specific_css)): ?><link rel="stylesheet" href="css/<?php echo htmlspecialchars($page_specific_css); ?>"><?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/3.5.0/luxon.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-luxon/1.3.1/chartjs-adapter-luxon.umd.min.js" defer></script>
</head>
<body>
    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="index.php" class="<?php echo (isset($active_page) && $active_page === 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
        <a href="add_snapshot.php" class="<?php echo (isset($active_page) && $active_page === 'add_snapshot') ? 'active' : ''; ?>">Add Snapshot</a>
        <a href="calendar_hours.php" class="<?php echo (isset($active_page) && $active_page === 'calendar') ? 'active' : ''; ?>">Hours Calendar</a>
        <a href="manage_accounts.php" class="<?php echo (isset($active_page) && $active_page === 'manage_accounts') ? 'active' : ''; ?>">Manage Accounts</a>
        <a href="admin_settings.php" class="<?php echo (isset($active_page) && $active_page === 'settings') ? 'active' : ''; ?>">Settings</a>
    </nav>
    <div class="container">
