<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php';

$upcomingExpenses = [];
$debug_info = []; // For debugging utilities part

// --- Rent ---
$rentAmount = 1100.99;
$rentEmoji = '🏡';

// Determine the date range for recurring rent
$currentDate = new DateTime();
// Start from the first day of the month, 12 months ago
$startDate = (new DateTime('now', $currentDate->getTimezone()))->modify('first day of this month')->modify('-12 months');
// End on the first day of the month, 24 months from now
$endDate = (new DateTime('now', $currentDate->getTimezone()))->modify('first day of this month')->modify('+24 months');
// Include the end date in the period by adding one month to it for DatePeriod behavior
$endDateForPeriod = (clone $endDate)->modify('+1 day'); // Ensure the last month is included

$interval = new DateInterval('P1M'); // Period of 1 month
$period = new DatePeriod($startDate, $interval, $endDateForPeriod);

foreach ($period as $dt) {
    $upcomingExpenses[] = [
        'date' => $dt->format('Y-m-01'),
        'type' => 'Rent',
        'amount' => $rentAmount,
        'emoji' => $rentEmoji,
        'notes' => 'Monthly Rent'
    ];
}

// --- Utilities ---
try {
    // Removed user-specific logic for utilities
    // $userPersonName = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;
    // $debug_info['user_person_name'] = $userPersonName;
    // if (!$userPersonName) {
    //     $debug_info['error_user_name'] = "UTILITIES_USER_PERSON_NAME is not set in .env";
    // }

    // SQL Query Change: Select all records directly from tblUtilities
    $sql = "
        SELECT
            pmkBillID,
            fldItem,
            fldTotal,
            fldDue,
            fldStatus
        FROM tblUtilities
    ";

    $debug_info['sql_query'] = $sql;

    $stmt = $pdoUtilities->prepare($sql);
    // Removed: $stmt->bindParam(':personName', $userPersonName, PDO::PARAM_STR);
    // Removed: $params_for_log = ['personName' => $userPersonName];
    // Removed: $debug_info['sql_params'] = $params_for_log;

    $stmt->execute();

    $utilityBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['raw_utility_bills_count'] = count($utilityBills); // Reflects total count from tblUtilities
    // $debug_info['raw_utility_bills'] = $utilityBills; // Optional: can be verbose

    foreach ($utilityBills as $bill) {
        // Calculate amount as fldTotal / 3
        $amount = 0;
        if (isset($bill['fldTotal']) && is_numeric($bill['fldTotal'])) {
            $amount = round(floatval($bill['fldTotal']) / 3, 2);
        }

        $emoji = '💰'; // Default emoji
        if (isset($bill['fldItem'])) {
            if (stripos($bill['fldItem'], 'Gas') !== false) { $emoji = '🔥'; }
            elseif (stripos($bill['fldItem'], 'Electric') !== false) { $emoji = '💡'; }
            elseif (stripos($bill['fldItem'], 'Internet') !== false) { $emoji = '🌐'; }
        }

        $upcomingExpenses[] = [
            'date' => $bill['fldDue'],
            'type' => $bill['fldItem'],
            'amount' => $amount,
            'emoji' => $emoji,
            'status' => $bill['fldStatus'],
            'notes' => 'Utility Bill' // Generic notes
        ];
    }

} catch (PDOException $e) {
    error_log("Utilities DB Error in api_get_upcoming_expenses.php: " . $e->getMessage());
    // Keep $debug_info for general API debugging if necessary, but avoid user-specific details for utilities.
    $debug_info['pdo_exception'] = $e->getMessage();
    $upcomingExpenses[] = ['error_message' => 'Utilities DB Error: ' . $e->getMessage()];
}

// Debug info should be minimal if not needed, or not include user-specific utility details
if (!empty($debug_info)) {
    // Example: only include if there was an actual error, or keep it lean.
    if(isset($debug_info['pdo_exception']) || isset($debug_info['sql_query'])) {
         $upcomingExpenses[] = ['_debug_utility_api' => $debug_info];
    }
}

echo json_encode($upcomingExpenses);
?>
