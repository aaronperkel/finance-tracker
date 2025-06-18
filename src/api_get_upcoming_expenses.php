<?php
header('Content-Type: application/json');
require __DIR__ . '/db.php'; // Assuming db.php is in the same directory (src)

$upcomingExpenses = [];

// --- Rent ---
$rentAmount = 1100.99;
$rentEmoji = '🏡';

// Rent for current month
$currentMonthRentDate = date('Y-m-01');
$upcomingExpenses[] = [
    'date' => $currentMonthRentDate,
    'type' => 'Rent',
    'amount' => $rentAmount,
    'emoji' => $rentEmoji,
    'notes' => 'Monthly Rent'
];

// Rent for next month
$nextMonthRentDate = date('Y-m-01', strtotime('+1 month'));
$upcomingExpenses[] = [
    'date' => $nextMonthRentDate,
    'type' => 'Rent',
    'amount' => $rentAmount,
    'emoji' => $rentEmoji,
    'notes' => 'Monthly Rent'
];

// --- Utilities ---
$debug_info = []; // Array to hold debugging messages

try {
    $userPersonName = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;
    $debug_info['user_person_name'] = $userPersonName;

    if (!$userPersonName) {
        $debug_info['error_user_name'] = "UTILITIES_USER_PERSON_NAME is not set in .env";
        // For now, just proceed, and the query won't match any utilities
        // error_log("UTILITIES_USER_PERSON_NAME is not set in .env");
    }

    // SQL to get unpaid bills for the specific user
    // We also count how many people are associated with each bill to calculate the user's share.
    $sql = "
        SELECT
            u.pmkBillID,
            u.fldDate AS billIssueDate,
            u.fldItem,
            u.fldTotal,
            u.fldDue,
            u.fldStatus,
            (SELECT COUNT(*) FROM tblBillOwes WHERE billID = u.pmkBillID) AS totalSharers
        FROM tblUtilities u
        JOIN tblBillOwes bo ON u.pmkBillID = bo.billID
        JOIN tblPeople p ON bo.personID = p.personID
        WHERE p.personName = :personName AND u.fldStatus = 'Unpaid'
    ";
    // No date filtering for fldDue temporarily for debugging

    $debug_info['sql_query'] = $sql; // Log the query

    $stmt = $pdoUtilities->prepare($sql);
    $stmt->bindParam(':personName', $userPersonName, PDO::PARAM_STR);

    $params_for_log = ['personName' => $userPersonName];
    $debug_info['sql_params'] = $params_for_log; // Log parameters

    $stmt->execute();

    $utilityBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug_info['raw_utility_bills_count'] = count($utilityBills);
    $debug_info['raw_utility_bills'] = $utilityBills; // Log raw bills fetched

    foreach ($utilityBills as $bill) {
        $userShare = 0;
        if ($bill['fldTotal'] > 0 && $bill['totalSharers'] > 0) {
            // As per instruction: "my part (a third of the bill price)"
            // The original request implies a fixed 1/3rd share.
            // However, using totalSharers from tblBillOwes is more dynamic and accurate
            // if the number of people sharing can vary per bill.
            // Forcing 1/3rd as per original explicit request:
            $userShare = round(floatval($bill['fldTotal']) / 3, 2);
        }

        $emoji = '💰'; // Default emoji
        if (stripos($bill['fldItem'], 'Gas') !== false) {
            $emoji = '🔥';
        } elseif (stripos($bill['fldItem'], 'Electric') !== false) {
            $emoji = '💡';
        } elseif (stripos($bill['fldItem'], 'Internet') !== false) {
            $emoji = '🌐';
        }

        $upcomingExpenses[] = [
            'date' => $bill['fldDue'],
            'type' => $bill['fldItem'],
            'amount' => $userShare,
            'emoji' => $emoji,
            'notes' => 'Utility Bill - Your Share (Debug)' // Added Debug note
        ];
    }

} catch (PDOException $e) {
    error_log("Utilities DB Error in api_get_upcoming_expenses.php: " . $e->getMessage());
    $debug_info['pdo_exception'] = $e->getMessage();
    // Add error to expenses array to make it visible on client side during debug
    $upcomingExpenses[] = ['error_message' => 'Utilities DB Error: ' . $e->getMessage(), 'debug_trace' => $e->getTraceAsString()];
}

// Add debug info to the main response
if (!empty($debug_info)) {
    // Find a way to add this to the response.
    // If $upcomingExpenses is always an array of objects, add debug_info as a special object.
    // Or, wrap the response: echo json_encode(['data' => $upcomingExpenses, 'debug' => $debug_info]);
    // For now, let's add it as a separate item in the expenses list for easy spotting.
    $upcomingExpenses[] = ['_debug_utility_api' => $debug_info];
}

echo json_encode($upcomingExpenses);

?>
