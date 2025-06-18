<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php';

$upcomingExpenses = [];
$debug_info = []; // For debugging utilities part

// --- Rent --- (This part remains unchanged)
$rentAmount = 1100.99;
$rentEmoji = '🏡';
$currentMonthRentDate = date('Y-m-01');
$upcomingExpenses[] = [
    'date' => $currentMonthRentDate,
    'type' => 'Rent',
    'amount' => $rentAmount,
    'emoji' => $rentEmoji,
    'notes' => 'Monthly Rent'
];
$nextMonthRentDate = date('Y-m-01', strtotime('+1 month'));
$upcomingExpenses[] = [
    'date' => $nextMonthRentDate,
    'type' => 'Rent',
    'amount' => $rentAmount,
    'emoji' => $rentEmoji,
    'notes' => 'Monthly Rent'
];

// --- Utilities ---
try {
    $userPersonName = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;
    $debug_info['user_person_name'] = $userPersonName;

    if (!$userPersonName) {
        $debug_info['error_user_name'] = "UTILITIES_USER_PERSON_NAME is not set in .env";
    }

    // Modified SQL: Removed "AND u.fldStatus = 'Unpaid'" and added "u.fldStatus" to SELECT
    $sql = "
        SELECT
            u.pmkBillID,
            u.fldDate AS billIssueDate,
            u.fldItem,
            u.fldTotal,
            u.fldDue,
            u.fldStatus, -- Added fldStatus to SELECT
            (SELECT COUNT(*) FROM tblBillOwes WHERE billID = u.pmkBillID) AS totalSharers
        FROM tblUtilities u
        JOIN tblBillOwes bo ON u.pmkBillID = bo.billID
        JOIN tblPeople p ON bo.personID = p.personID
        WHERE p.personName = :personName
        -- Removed: AND u.fldStatus = 'Unpaid'
        -- No date filtering on fldDue for now (as per previous debugging state)
    ";

    $debug_info['sql_query'] = $sql;

    $stmt = $pdoUtilities->prepare($sql);
    $stmt->bindParam(':personName', $userPersonName, PDO::PARAM_STR);

    $params_for_log = ['personName' => $userPersonName];
    $debug_info['sql_params'] = $params_for_log;

    $stmt->execute();

    $utilityBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['raw_utility_bills_count'] = count($utilityBills);
    $debug_info['raw_utility_bills'] = $utilityBills;

    foreach ($utilityBills as $bill) {
        $userShare = 0;
        // User share calculation remains the same (1/3rd of total),
        // but financial impact is only for 'Unpaid' ones (handled by financial summary API).
        // This API is for calendar display, so share is calculated for all.
        if ($bill['fldTotal'] > 0 && $bill['totalSharers'] > 0) {
            $userShare = round(floatval($bill['fldTotal']) / 3, 2);
        }

        $emoji = '💰';
        if (stripos($bill['fldItem'], 'Gas') !== false) { $emoji = '🔥'; }
        elseif (stripos($bill['fldItem'], 'Electric') !== false) { $emoji = '💡'; }
        elseif (stripos($bill['fldItem'], 'Internet') !== false) { $emoji = '🌐'; }

        $upcomingExpenses[] = [
            'date' => $bill['fldDue'],
            'type' => $bill['fldItem'],
            'amount' => $userShare, // User's potential share
            'emoji' => $emoji,
            'status' => $bill['fldStatus'], // Include the status
            'notes' => 'Utility Bill - Your Share' // Removed (Debug) from note
        ];
    }

} catch (PDOException $e) {
    error_log("Utilities DB Error in api_get_upcoming_expenses.php: " . $e->getMessage());
    $debug_info['pdo_exception'] = $e->getMessage();
    $upcomingExpenses[] = ['error_message' => 'Utilities DB Error: ' . $e->getMessage(), 'debug_trace' => $e->getTraceAsString()];
}

if (!empty($debug_info)) {
    $upcomingExpenses[] = ['_debug_utility_api' => $debug_info];
}

echo json_encode($upcomingExpenses);
?>
