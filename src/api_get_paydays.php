<?php
require_once 'db.php'; // For consistency, though not directly used for DB ops here
header('Content-Type: application/json');

// getAdjustedPaydate function is now in db.php

// Retrieve and validate month and year from GET parameters
$month = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_VALIDATE_INT) : null;
$year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;

if ($month === null || $month === false || $month < 1 || $month > 12) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid or missing month parameter. Must be an integer between 1 and 12.']);
    exit;
}

if ($year === null || $year === false || $year < 1900 || $year > 2200) { // Reasonable year range
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid or missing year parameter. Must be a valid year (e.g., 1900-2200).']);
    exit;
}

try {
    $payday1 = getAdjustedPaydate($year, $month, 15);
    $payday2 = getAdjustedPaydate($year, $month, 0); // 0 for last day of month

    $response = [
        $payday1->format('Y-m-d'),
        $payday2->format('Y-m-d')
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Catch any unexpected errors during date calculation, though less likely with validation
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}

?>
