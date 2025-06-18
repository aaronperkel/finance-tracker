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
try {
    $userPersonName = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;

    if (!$userPersonName) {
        // Optionally, return an error or empty array if user person name is not set
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

    // For simplicity, focusing on bills due this month and next month.
    // More sophisticated date filtering could be added (e.g., bills due in the next X days)
    $currentMonthStart = date('Y-m-01');
    $nextMonthEnd = date('Y-m-t', strtotime('+1 month')); // 't' gives last day of month

    // $sql .= " AND u.fldDue >= :currentMonthStart AND u.fldDue <= :nextMonthEnd";
    // Commenting out date filtering for now to ensure we catch all unpaid, can be added if too many results

    $stmt = $pdoUtilities->prepare($sql);
    $stmt->bindParam(':personName', $userPersonName, PDO::PARAM_STR);
    // $stmt->bindParam(':currentMonthStart', $currentMonthStart, PDO::PARAM_STR);
    // $stmt->bindParam(':nextMonthEnd', $nextMonthEnd, PDO::PARAM_STR);
    $stmt->execute();

    $utilityBills = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            'notes' => 'Utility Bill - Your Share'
        ];
    }

} catch (PDOException $e) {
    // Log error and/or return an error message
    error_log("Utilities DB Error: " . $e->getMessage());
    // To keep the API functional even if utilities fail,
    // we don't echo an error here, but could add an 'error' field in JSON.
    $upcomingExpenses[] = ['error' => 'Could not retrieve utility data: ' . $e->getMessage()];
}


echo json_encode($upcomingExpenses);

?>
