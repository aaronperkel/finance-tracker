<?php
require_once 'db.php'; // 1. Include db.php
header('Content-Type: application/json'); // 2. Set Content-Type header

$response = [
    "current_net_worth" => 0,
    "total_cash_on_hand" => 0,
    "receivables_balance" => 0,
    "total_liabilities" => 0, // Added for Total Owed
    "gross_estimated_pay" => 0, // Optional new field
    "estimated_federal_tax" => 0, // Optional new field
    "estimated_state_tax" => 0,   // Optional new field
    "estimated_upcoming_pay" => 0, // This will be NET pay
    "next_pay_date" => null,
    "future_net_worth" => 0,
    "net_worth_history" => [],
    "debug_pay_period_start" => null,
    "debug_pay_period_end" => null,
];

try {
    // 3. Fetch Data and Perform Calculations

    // Get latest snapshot ID (Mandated Query)
    $latest_snapshot_id_stmt = $pdo->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC, id DESC LIMIT 1");
    $latest_snapshot_id = $latest_snapshot_id_stmt->fetchColumn();
    $response['debug_latest_snapshot_id_used'] = $latest_snapshot_id; // Debug line

    if ($latest_snapshot_id) {
        // Current Net Worth
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE -b.balance END) as net_worth
            FROM balances b
            JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ?
        ");
        $stmt->execute([$latest_snapshot_id]);
        $response["current_net_worth"] = floatval($stmt->fetchColumn() ?: 0);

        // Total Cash on Hand
        $cash_accounts = ["Truist Checking", "Capital One Savings", "Apple Savings"];
        $placeholders = rtrim(str_repeat('?,', count($cash_accounts)), ',');
        $stmt = $pdo->prepare("
            SELECT SUM(b.balance) as total_cash
            FROM balances b
            JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.name IN ($placeholders)
        ");
        $params = array_merge([$latest_snapshot_id], $cash_accounts);
        $stmt->execute($params);
        $response["total_cash_on_hand"] = floatval($stmt->fetchColumn() ?: 0);

        // Receivables Balance
        $stmt = $pdo->prepare("
            SELECT b.balance
            FROM balances b
            JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.name = 'Receivables'
        ");
        $stmt->execute([$latest_snapshot_id]);
        $response["receivables_balance"] = floatval($stmt->fetchColumn() ?: 0);

        // Total Liabilities (Total Owed)
        $stmt = $pdo->prepare("
            SELECT SUM(b.balance) as total_liabilities
            FROM balances b
            JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.type = 'Liability'
        ");
        $stmt->execute([$latest_snapshot_id]);
        $response["total_liabilities"] = floatval($stmt->fetchColumn() ?: 0);
    }

    // Net Worth History
    $sql_history = "
        SELECT
            s.snapshot_date AS date,
            SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END)
              - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END)
            AS networth
        FROM balances b
        JOIN accounts a ON a.id = b.account_id
        JOIN snapshots s ON b.snapshot_id = s.id
        WHERE s.id IN (
            -- Subquery to select the MAX(id) for each snapshot_date
            -- This ensures that only balances from the latest snapshot of any given day are considered.
            SELECT MAX(id)
            FROM snapshots
            GROUP BY snapshot_date
        )
        GROUP BY s.snapshot_date
        ORDER BY s.snapshot_date ASC"; // Ensure ascending order for the chart
        
    $stmt_history = $pdo->query($sql_history);
    $response["net_worth_history"] = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    foreach ($response["net_worth_history"] as &$item) {
        $item["networth"] = floatval($item["networth"]);
    }


    // Estimated Upcoming Pay and Next Pay Date
    // Remove pay_day_1 and pay_day_2 from query as they are no longer needed
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings 
                                  WHERE setting_key IN ('pay_rate', 'federal_tax_rate', 'state_tax_rate')");
    $app_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }

    // Initialize pay-related fields in case settings are not fully available
    $response["gross_estimated_pay"] = 0.00;
    $response["estimated_federal_tax"] = 0.00;
    $response["estimated_state_tax"] = 0.00;
    $response["estimated_upcoming_pay"] = 0.00;
    $response["is_pay_day"] = false; // Initialize is_pay_day

    // Helper function for pay period cutoff
    function get_cutoff_sunday_before_payday(DateTime $payDateObj): DateTime {
        $cutoff = clone $payDateObj;
        $cutoff->setTime(0,0,0); // Normalize time
        $cutoff->modify('previous sunday');
        return $cutoff;
    }

    /**
     * Calculates the adjusted payday, moving to Friday if it falls on a weekend.
     *
     * @param int $year The year.
     * @param int $month The month.
     * @param int $day The target day of the month (e.g., 15 or last day).
     * @return DateTime The adjusted payday.
     */
    function getAdjustedPaydate(int $year, int $month, int $day): DateTime {
        $date = new DateTime();
        if ($day === 0) { // Special case for last day of the month
            $date->setDate($year, $month, 1)->modify('last day of this month');
        } else {
            $date->setDate($year, $month, $day);
        }
        $date->setTime(0,0,0);

        $dayOfWeek = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)

        if ($dayOfWeek === 6) { // Saturday
            $date->modify('-1 day'); // Move to Friday
        } elseif ($dayOfWeek === 7) { // Sunday
            $date->modify('-2 days'); // Move to Friday
        }
        return $date;
    }

    // Check if pay_rate is set, otherwise, skip pay calculations
    if (isset($app_settings['pay_rate'])) {
        $pay_rate = floatval($app_settings['pay_rate']);
        
        $federal_tax_rate_value = isset($app_settings['federal_tax_rate']) ? floatval($app_settings['federal_tax_rate']) : 0;
        $state_tax_rate_value = isset($app_settings['state_tax_rate']) ? floatval($app_settings['state_tax_rate']) : 0;

        $current_date_time = new DateTime();
        $current_date_time->setTime(0,0,0); // Normalize current date for comparisons
        
        $current_year = (int)$current_date_time->format('Y');
        $current_month = (int)$current_date_time->format('n');

        // --- Determine Payday Objects for Current Month using new logic ---
        $payday1_current_month = getAdjustedPaydate($current_year, $current_month, 15);
        $payday2_current_month = getAdjustedPaydate($current_year, $current_month, 0); // 0 for last day of month

        // --- Determine True Next Payday ---
        $true_next_payday_obj;
        if ($current_date_time <= $payday1_current_month) {
            $true_next_payday_obj = clone $payday1_current_month;
        } elseif ($current_date_time <= $payday2_current_month) {
            $true_next_payday_obj = clone $payday2_current_month;
        } else {
            // Both paydays of current month have passed, so next is payday 1 of next month
            $next_month_date = (clone $current_date_time)->modify('+1 month');
            // Use getAdjustedPaydate for next month's first payday
            $true_next_payday_obj = getAdjustedPaydate((int)$next_month_date->format('Y'), (int)$next_month_date->format('n'), 15);
        }
        $response["next_pay_date"] = $true_next_payday_obj->format('Y-m-d');

        // --- Determine True Previous Payday ---
        $true_prev_payday_obj;
        if ($true_next_payday_obj->format('Y-m-d') === $payday1_current_month->format('Y-m-d')) {
            // Next payday is the 1st of current month (adjusted), so previous was 2nd payday of previous month (adjusted)
            $prev_month_date = (clone $current_date_time)->modify('-1 month');
            $true_prev_payday_obj = getAdjustedPaydate((int)$prev_month_date->format('Y'), (int)$prev_month_date->format('n'), 0); // 0 for last day
        } elseif ($true_next_payday_obj->format('Y-m-d') === $payday2_current_month->format('Y-m-d')) {
            // Next payday is the 2nd of current month (adjusted), so previous was 1st payday of current month (adjusted)
            $true_prev_payday_obj = clone $payday1_current_month;
        } else {
            // Next payday is 1st of next month (adjusted), so previous was 2nd payday of current month (adjusted)
            $true_prev_payday_obj = clone $payday2_current_month;
        }
        
        // Calculate Pay Period Boundaries
        $current_pay_period_end_date_obj = get_cutoff_sunday_before_payday($true_next_payday_obj);
        $prev_pay_period_end_date_obj = get_cutoff_sunday_before_payday($true_prev_payday_obj);
        $current_pay_period_start_date_obj = (clone $prev_pay_period_end_date_obj)->modify('+1 day');
        
        $response["debug_pay_period_start"] = $current_pay_period_start_date_obj->format('Y-m-d');
        $response["debug_pay_period_end"] = $current_pay_period_end_date_obj->format('Y-m-d');
        $response["debug_true_next_payday"] = $true_next_payday_obj->format('Y-m-d');
        $response["debug_true_prev_payday"] = $true_prev_payday_obj->format('Y-m-d');

        // Define jobStartDate
        $jobStartDate = new DateTime('2025-05-20'); // Ensure this matches other scripts
        $jobStartDate->setTime(0,0,0);

        // Determine if today is a payday (comparing normalized DateTime objects)
        if ($current_date_time->format('Y-m-d') === $payday1_current_month->format('Y-m-d') ||
            $current_date_time->format('Y-m-d') === $payday2_current_month->format('Y-m-d')) {
            $response["is_pay_day"] = true;
        }

        if ($response["is_pay_day"]) {
            $response["gross_estimated_pay"] = 0.00;
            $response["estimated_federal_tax"] = 0.00;
            $response["estimated_state_tax"] = 0.00;
            $response["estimated_upcoming_pay"] = 0.00;
        } else {
            // Fetch explicitly logged hours for the period
            $stmt_logged = $pdo->prepare("SELECT log_date, hours_worked FROM logged_hours WHERE log_date BETWEEN ? AND ?");
            $stmt_logged->execute([
                $current_pay_period_start_date_obj->format('Y-m-d'),
                $current_pay_period_end_date_obj->format('Y-m-d')
            ]);
            $logged_hours_for_period_raw = $stmt_logged->fetchAll(PDO::FETCH_ASSOC);
            $explicitly_logged_hours = [];
            foreach ($logged_hours_for_period_raw as $row) {
                $explicitly_logged_hours[$row['log_date']] = (float)$row['hours_worked'];
            }

            $total_hours_for_period = 0.0;
            $loop_date = clone $current_pay_period_start_date_obj;
            while ($loop_date <= $current_pay_period_end_date_obj) {
                $date_str = $loop_date->format('Y-m-d');
                $day_of_week = (int)$loop_date->format('N'); // 1 (Mon) to 7 (Sun)

                if ($loop_date >= $jobStartDate && $day_of_week >= 1 && $day_of_week <= 5) { // Is a relevant workday
                    if (isset($explicitly_logged_hours[$date_str])) {
                        $total_hours_for_period += $explicitly_logged_hours[$date_str];
                    } else {
                        $total_hours_for_period += 7.5; // Default hours
                    }
                }
                $loop_date->modify('+1 day');
            }
            
            $gross_estimated_pay = $total_hours_for_period * $pay_rate;
            $estimated_federal_tax = $gross_estimated_pay * $federal_tax_rate_value;
            $estimated_state_tax = $gross_estimated_pay * $state_tax_rate_value;
            $net_estimated_pay = $gross_estimated_pay - $estimated_federal_tax - $estimated_state_tax;

            $response["gross_estimated_pay"] = round($gross_estimated_pay, 2);
            $response["estimated_federal_tax"] = round($estimated_federal_tax, 2);
            $response["estimated_state_tax"] = round($estimated_state_tax, 2);
            $response["estimated_upcoming_pay"] = round($net_estimated_pay, 2);
        }
    }
    
    // Future Net Worth - always use the (potentially net) estimated_upcoming_pay
    $response["future_net_worth"] = round($response["current_net_worth"] + $response["estimated_upcoming_pay"], 2);

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'General error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>
