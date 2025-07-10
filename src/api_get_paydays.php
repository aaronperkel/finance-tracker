<?php
require_once 'db.php'; // Now used for fetching app_settings
header('Content-Type: application/json');

/**
 * Adjusts a given DateTime object to ensure it's a Friday.
 * If it's a Saturday, moves to the preceding Friday.
 * If it's a Sunday, moves to the preceding Friday.
 *
 * @param DateTime $date The date to adjust.
 * @return DateTime The adjusted date (guaranteed to be a Friday or original date if already Friday).
 */
function adjustToFriday(DateTime $date): DateTime {
    $dayOfWeek = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
    if ($dayOfWeek === 6) { // Saturday
        $date->modify('-1 day');
    } elseif ($dayOfWeek === 7) { // Sunday
        $date->modify('-2 days');
    }
    return $date;
}

// Retrieve and validate month and year from GET parameters
$month_param = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_VALIDATE_INT) : null;
$year_param = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;

if ($month_param === null || $month_param === false || $month_param < 1 || $month_param > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing month parameter. Must be an integer between 1 and 12.']);
    exit;
}

if ($year_param === null || $year_param === false || $year_param < 1900 || $year_param > 2200) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing year parameter. Must be a valid year (e.g., 1900-2200).']);
    exit;
}

try {
    // Pay schedule is now hardcoded: Bi-weekly, with reference date 2025-05-30.
    // No need to fetch settings from DB for schedule type.
    // The adjustToFriday function is not strictly needed if the reference is a known Friday
    // and we only add multiples of 14 days. However, it can be kept for robustness
    // or if any other part of the system might use it. For this specific API,
    // direct calculation is simpler.

    $fixedReferenceFridayString = '2025-05-30';
    $referenceFriday = new DateTimeImmutable($fixedReferenceFridayString); // This is a known Friday
    $referenceFriday = $referenceFriday->setTime(0,0,0);

    $paydays = [];
    $requestedMonthStartDate = new DateTimeImmutable("$year_param-$month_param-01");
    $requestedMonthEndDate = $requestedMonthStartDate->modify('last day of this month');

    // Find the first payday in the cycle that is relevant to the requested month.
    // Start from the reference and move forwards or backwards by 14-day steps.
    $currentPayday = clone $referenceFriday;

    if ($currentPayday > $requestedMonthEndDate) { // Reference is after the requested month
        // Go backwards from reference to find a payday before or in the month
        while ($currentPayday > $requestedMonthEndDate) {
            $currentPayday = $currentPayday->modify('-14 days');
        }
        // At this point, $currentPayday is the first payday on or before the requested month's end.
        // If it's before the month's start, the next one might be in the month.
        if ($currentPayday < $requestedMonthStartDate) {
            $currentPayday = $currentPayday->modify('+14 days');
        }
    } else { // Reference is before or during the requested month
        // Go forwards from reference to find the first payday in or after the month's start
        while ($currentPayday < $requestedMonthStartDate) {
            $currentPayday = $currentPayday->modify('+14 days');
        }
    }

    // Now $currentPayday is the first potential payday in the cycle that is >= requestedMonthStartDate.
    // Add all paydays in that cycle that fall within the requested month.
    while ($currentPayday <= $requestedMonthEndDate) {
        if ($currentPayday >= $requestedMonthStartDate) { // Ensure it's within the current month
             $paydays[] = $currentPayday->format('Y-m-d');
        }
        $currentPayday = $currentPayday->modify('+14 days');
    }

    $response = array_values(array_unique($paydays)); // Ensure uniqueness, though direct cycle should be unique
    sort($response);

    echo json_encode($response);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in api_get_paydays.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred while fetching settings.']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in api_get_paydays.php: " . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
    exit;
}

?>
