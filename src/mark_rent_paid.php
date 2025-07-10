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
$paid_date_str = $input['paid_date'] ?? null;
$amount_str = $input['amount'] ?? null;

$errors = [];

// Validate rent_month
if (empty($rent_month_str)) {
    $errors[] = "rent_month is required.";
} else {
    $rent_month_dt = DateTime::createFromFormat('Y-m-d', $rent_month_str);
    if (!$rent_month_dt || $rent_month_dt->format('Y-m-d') !== $rent_month_str) {
        $errors[] = "Invalid rent_month format. Expected YYYY-MM-DD.";
    } elseif ($rent_month_dt->format('d') !== '01') {
        $errors[] = "rent_month must be the first day of the month (e.g., YYYY-MM-01).";
    }
}

// Validate paid_date
if (empty($paid_date_str)) {
    $errors[] = "paid_date is required.";
} else {
    $paid_date_dt = DateTime::createFromFormat('Y-m-d', $paid_date_str);
    if (!$paid_date_dt || $paid_date_dt->format('Y-m-d') !== $paid_date_str) {
        $errors[] = "Invalid paid_date format. Expected YYYY-MM-DD.";
    }
}

// Validate amount
if (empty($amount_str)) {
    $errors[] = "amount is required.";
} elseif (!is_numeric($amount_str) || floatval($amount_str) <= 0) {
    $errors[] = "Invalid amount. Must be a positive number.";
} else {
    // Sanitize to ensure it's a valid decimal
    $amount = round(floatval($amount_str), 2);
    if ($amount <= 0) { // Double check after rounding
        $errors[] = "Amount must be greater than zero after rounding.";
    }
}

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}

// Ensure paid_date is not in the future relative to rent_month if desired,
// or that paid_date is not significantly far from rent_month.
// For now, we'll allow flexibility but this could be a future enhancement.
// Example: if ($paid_date_dt > $rent_month_dt) {
//    $errors[] = "Paid date cannot be after the rent month start.";
// }

try {
    $sql = "INSERT INTO rent_payments (rent_month, paid_date, amount) VALUES (:rent_month, :paid_date, :amount)
            ON DUPLICATE KEY UPDATE paid_date = VALUES(paid_date), amount = VALUES(amount)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':rent_month', $rent_month_str);
    $stmt->bindParam(':paid_date', $paid_date_str);
    $stmt->bindParam(':amount', $amount); // Use the validated and rounded amount

    if ($stmt->execute()) {
        echo json_encode(['success' => 'Rent payment recorded successfully.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to record rent payment.']);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    // Check for unique constraint violation specifically if needed, though ON DUPLICATE KEY UPDATE handles it.
    // MySQL error code for unique constraint violation is 1062.
    // if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062') !== false) {
    //     http_response_code(409); // Conflict
    //     echo json_encode(['error' => 'A rent payment for this month has already been recorded. You can update it if necessary.']);
    // } else {
    //     echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    // }
    // Simpler error for now as ON DUPLICATE KEY should prevent crashes on unique violation.
    error_log("PDOException in mark_rent_paid.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred.']);
}
?>