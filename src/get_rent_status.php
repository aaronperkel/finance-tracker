<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET requests are accepted.']);
    exit;
}

$rent_month_str = $_GET['rent_month'] ?? null;

if (empty($rent_month_str)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'rent_month GET parameter is required.']);
    exit;
}

// Validate rent_month format
$rent_month_dt = DateTime::createFromFormat('Y-m-d', $rent_month_str);
if (!$rent_month_dt || $rent_month_dt->format('Y-m-d') !== $rent_month_str) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid rent_month format. Expected YYYY-MM-DD.']);
    exit;
}
// We could also check if $rent_month_dt->format('d') === '01' if we strictly enforce it.
// For flexibility, we'll just use the provided YYYY-MM-DD as is for querying.

try {
    $stmt = $pdo->prepare("SELECT paid_date, amount FROM rent_payments WHERE rent_month = ?");
    $stmt->execute([$rent_month_str]);
    $payment_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment_details) {
        echo json_encode([
            'is_paid' => true,
            'details' => [
                'paid_date' => $payment_details['paid_date'],
                'amount' => number_format(floatval($payment_details['amount']), 2, '.', '')
            ]
        ]);
    } else {
        echo json_encode(['is_paid' => false]);
    }

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("PDOException in get_rent_status.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred while fetching rent status.']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Exception in get_rent_status.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>
