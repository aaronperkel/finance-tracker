<?php
require_once 'db.php'; // For consistency, though not directly used for DB ops here
header('Content-Type: application/json');

/**
 * Calculates the adjusted payday, moving to Friday if it falls on a weekend.
 *
 * @param int $year The year.
 * @param int $month The month.
 * @param int $day The target day of the month (e.g., 15 or 0 for last day).
 * @return DateTime The adjusted payday.
 */
function getAdjustedPaydate(int $year, int $month, int $day): DateTime {
    $date = new DateTime();
    if ($day === 0) { // Special case for last day of the month
        $date->setDate($year, $month, 1)->modify('last day of this month');
    } else {
        $date->setDate($year, $month, $day);
    }
    $date->setTime(0,0,0); // Normalize time

    $dayOfWeek = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)

    if ($dayOfWeek === 6) { // Saturday
        $date->modify('-1 day'); // Move to Friday
    } elseif ($dayOfWeek === 7) { // Sunday
        $date->modify('-2 days'); // Move to Friday
    }
    return $date;
}

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
