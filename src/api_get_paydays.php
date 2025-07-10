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
    // Fetch pay schedule settings from the database
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings
                                  WHERE setting_key IN ('pay_schedule_type', 'pay_schedule_detail1', 'pay_schedule_detail2')");
    $app_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }

    $pay_schedule_type = $app_settings['pay_schedule_type'] ?? 'semi-monthly'; // Default if not set
    $detail1 = $app_settings['pay_schedule_detail1'] ?? null;
    $detail2 = $app_settings['pay_schedule_detail2'] ?? null;

    $paydays = [];
    $startDate = new DateTimeImmutable("$year_param-$month_param-01");
    $endDate = (clone $startDate)->modify('last day of this month');

    if ($pay_schedule_type === 'bi-weekly') {
        if (empty($detail1) || !($referenceFriday = DateTimeImmutable::createFromFormat('Y-m-d', $detail1))) {
            http_response_code(500);
            echo json_encode(['error' => 'Bi-weekly schedule selected, but Reference Friday (pay_schedule_detail1) is missing or invalid in settings.']);
            exit;
        }
        $referenceFriday = $referenceFriday->setTime(0,0,0);

        // Ensure the reference Friday itself is a Friday.
        if ((int)$referenceFriday->format('N') !== 5) {
             // This should ideally be caught during settings validation, but good to double check.
             // Or, adjust it to the nearest Friday. For now, error out if not set correctly.
            error_log("Reference Friday setting (pay_schedule_detail1: {$detail1}) is not actually a Friday.");
            // Let's try to find the first valid Friday on or after this reference for calculation start
            while((int)$referenceFriday->format('N') !== 5) {
                $referenceFriday = $referenceFriday->modify('+1 day');
            }
        }

        // Find the first payday on or after the start of the requested month that matches the bi-weekly cycle.
        $currentPayday = clone $referenceFriday;
        while ($currentPayday < $startDate) {
            $currentPayday = $currentPayday->modify('+14 days');
        }

        // Add all paydays in that cycle that fall within the requested month
        while ($currentPayday <= $endDate) {
            // All bi-weekly paydays should inherently be Fridays based on the reference.
            // No need for adjustToFriday() unless reference was not a Friday.
            $paydays[] = $currentPayday->format('Y-m-d');
            $currentPayday = $currentPayday->modify('+14 days');
        }

    } elseif ($pay_schedule_type === 'semi-monthly') {
        $day1 = ($detail1 !== null && $detail1 !== '') ? (int)$detail1 : 15; // Default to 15
        $day2_setting = ($detail2 !== null && $detail2 !== '') ? (int)$detail2 : 0; // Default to last day (0)

        $paydate1 = new DateTime();
        $paydate1->setDate($year_param, $month_param, $day1)->setTime(0,0,0);
        $paydays[] = adjustToFriday(clone $paydate1)->format('Y-m-d');

        $paydate2 = new DateTime();
        if ($day2_setting === 0) { // Last day of month
            $paydate2->setDate($year_param, $month_param, 1)->modify('last day of this month')->setTime(0,0,0);
        } else {
            $paydate2->setDate($year_param, $month_param, $day2_setting)->setTime(0,0,0);
        }
        // Avoid duplicate if day1 and day2 result in same adjusted Friday (e.g. 15th is Sat, 16th is Sun)
        $adjusted_paydate2_str = adjustToFriday(clone $paydate2)->format('Y-m-d');
        if (!in_array($adjusted_paydate2_str, $paydays)) {
            $paydays[] = $adjusted_paydate2_str;
        }
        sort($paydays); // Ensure they are in chronological order

    } elseif ($pay_schedule_type === 'monthly') {
        $day_setting = ($detail1 !== null && $detail1 !== '') ? (int)$detail1 : 0; // Default to last day

        $paydate = new DateTime();
        if ($day_setting === 0) { // Last day of month
            $paydate->setDate($year_param, $month_param, 1)->modify('last day of this month')->setTime(0,0,0);
        } else {
            $paydate->setDate($year_param, $month_param, $day_setting)->setTime(0,0,0);
        }
        $paydays[] = adjustToFriday(clone $paydate)->format('Y-m-d');
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Unknown pay_schedule_type in settings.']);
        exit;
    }

    // Remove duplicate dates just in case, and sort
    $response = array_values(array_unique($paydays));
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
