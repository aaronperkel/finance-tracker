<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are accepted.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$rent_month_str = $input['rent_month'] ?? null;

if (empty($rent_month_str)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'rent_month is required in JSON payload.']);
    exit;
}

// Validate rent_month format
$rent_month_dt = DateTime::createFromFormat('Y-m-d', $rent_month_str);
if (!$rent_month_dt || $rent_month_dt->format('Y-m-d') !== $rent_month_str) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid rent_month format. Expected YYYY-MM-DD.']);
    exit;
}
// Again, could enforce $rent_month_dt->format('d') === '01' if needed.

try {
    $stmt = $pdo->prepare("DELETE FROM rent_payments WHERE rent_month = ?");

    if ($stmt->execute([$rent_month_str])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => 'Rent payment record deleted successfully.']);
        } else {
            // Technically not an error, but good to inform if no record was found to delete
            echo json_encode(['success' => 'No rent payment record found for the specified month to delete.']);
        }
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to delete rent payment record.']);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    error_log("PDOException in delete_rent_payment.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred while deleting rent payment.']);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Exception in delete_rent_payment.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>