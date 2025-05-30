<?php
require_once 'db.php'; // 1. Include db.php

header('Content-Type: application/json'); // 7. Set Content-Type header

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_date = $_POST['log_date'] ?? null;
    $hours_worked_input = $_POST['hours_worked'] ?? null; // Use a different var name for raw input

    // 3. Perform basic validation
    // Check if log_date is provided
    if (empty($log_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing log_date.']);
        exit;
    }

    // Check if hours_worked is provided (isset considers "0" as set)
    if (!isset($hours_worked_input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing hours_worked.']);
        exit;
    }

    // Validate date format (YYYY-MM-DD)
    $date_format = 'Y-m-d';
    $d = DateTime::createFromFormat($date_format, $log_date);
    if (!($d && $d->format($date_format) === $log_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Please use YYYY-MM-DD.']);
        exit;
    }

    // Validate hours_worked
    if (!is_numeric($hours_worked_input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid hours_worked: must be a numeric value.']);
        exit;
    }

    $hours_worked = (float)$hours_worked_input; // Cast to float for comparison

    if ($hours_worked < 0 || $hours_worked > 24) { // Allow 0, check range
        http_response_code(400);
        echo json_encode(['error' => 'Invalid hours_worked: must be between 0 and 24.']);
        exit;
    }

    // $hours_worked is now validated and can be 0.
    // The original $hours_worked variable will be used below, which is now a float.

    try {
        // 4. Insert or update data in the logged_hours table
        $sql = "INSERT INTO logged_hours (log_date, hours_worked) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE hours_worked = VALUES(hours_worked)";
        $stmt = $pdo->prepare($sql);
        
        // Use the validated and cast $hours_worked
        if ($stmt->execute([$log_date, $hours_worked])) {
            // 5. Output success message
            echo json_encode(['success' => 'Hours logged successfully for ' . $log_date]);
        } else {
            // This case might not be reached if PDO is set to throw exceptions for errors.
            // However, it's good practice to handle it.
            http_response_code(500);
            echo json_encode(['error' => 'Database error during execution.']);
        }
    } catch (PDOException $e) {
        // 6. Output database error message
        http_response_code(500);
        // In a production app, log this error instead of echoing it directly
        // error_log($e->getMessage()); 
        echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are accepted.']);
}
?>
