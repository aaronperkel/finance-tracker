<?php
require_once 'db.php'; // 1. Include db.php

echo "<!DOCTYPE html><html><head><title>Fill Default Hours</title>";
// Basic styling for readability
echo "<style>body { font-family: sans-serif; padding: 20px; } .message { margin-bottom: 10px; padding: 10px; border-radius: 5px; }";
echo ".success { background-color: #e6ffe6; border: 1px solid #b3ffb3; }";
echo ".error { background-color: #ffe6e6; border: 1px solid #ffb3b3; }";
echo ".info { background-color: #e6f7ff; border: 1px solid #b3e0ff; }</style>";
echo "</head><body><h1>Fill Default Hours Utility</h1>";

$days_checked = 0;
$default_entries_added = 0;

try {
    // 1. Define Job Start Date
    $jobStartDate = new DateTime('2025-05-20'); // MODIFIED as per user request
    $jobStartDate->setTime(0, 0, 0); // Normalize time

    // 2. Determine Date Range
    $endDate = new DateTime('yesterday');
    $endDate->setTime(0, 0, 0); // Normalize to start of day

    // Determine initial start date from DB
    $stmt = $pdo->query("SELECT MIN(log_date) as earliest_date FROM logged_hours");
    $dbEarliestLogDateStr = $stmt->fetchColumn();
    $dbEarliestLogDateObj = null;
    if ($dbEarliestLogDateStr) {
        $dbEarliestLogDateObj = new DateTime($dbEarliestLogDateStr);
        $dbEarliestLogDateObj->setTime(0, 0, 0);
    }

    $firstDayOfCurrentMonth = new DateTime('first day of this month');
    $firstDayOfCurrentMonth->setTime(0, 0, 0);

    $calculatedStartDate;

    if ($dbEarliestLogDateObj) {
        // Logs exist, start from the later of jobStartDate or actual earliest log
        $calculatedStartDate = ($dbEarliestLogDateObj > $jobStartDate) ? clone $dbEarliestLogDateObj : clone $jobStartDate;
    } else {
        // No logs exist, start from the later of jobStartDate or first day of current month
        $calculatedStartDate = ($firstDayOfCurrentMonth > $jobStartDate) ? clone $firstDayOfCurrentMonth : clone $jobStartDate;
    }

    $startDate = $calculatedStartDate; // This $startDate is then used for the loop


    if ($startDate > $endDate) {
        echo "<div class='message info'>No date range to process. Effective start date (" . $startDate->format('Y-m-d') . ") is after end date (" . $endDate->format('Y-m-d') . "). This might be due to the job start date or no recent log activity.</div>";
    } else {
        $currentDate = clone $startDate;

        // Prepare statements
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM logged_hours WHERE log_date = ?");
        $insert_stmt = $pdo->prepare("INSERT INTO logged_hours (log_date, hours_worked) VALUES (?, ?)");

        // 3. Iterate and Log Default Hours
        while ($currentDate <= $endDate) {
            $days_checked++;
            $date_str = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->format('N'); // 1 (Mon) to 7 (Sun)

            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // If weekday (Monday to Friday)
                $check_stmt->execute([$date_str]);
                $entry_exists = $check_stmt->fetchColumn();

                if (!$entry_exists) {
                    $insert_stmt->execute([$date_str, 7.5]);
                    $default_entries_added++;
                }
            }
            $currentDate->modify('+1 day');
        }
        echo "<div class='message success'>Default hours processing complete. <br>Range processed: " . $startDate->format('Y-m-d') . " to " . $endDate->format('Y-m-d') . ".<br>" . $days_checked . " days checked, " . $default_entries_added . " default entries added.</div>";
    }

} catch (PDOException $e) {
    echo "<div class='message error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
} catch (Exception $e) {
    echo "<div class='message error'>General error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>