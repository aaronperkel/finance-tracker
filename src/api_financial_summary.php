<?php
require_once 'db.php'; // Ensures getAdjustedPaydate is available
header('Content-Type: application/json');

$response = [
    "current_net_worth" => 0,
    "total_cash_on_hand" => 0,
    "receivables_balance" => 0,
    "total_liabilities" => 0,
    // "gross_estimated_pay" => 0, // Will be set for the single next check if needed
    // "estimated_federal_tax" => 0, // Will be set for the single next check if needed
    // "estimated_state_tax" => 0, // Will be set for the single next check if needed
    "estimated_upcoming_pay" => 0, // Net for the single very next check
    "next_pay_date" => null,
    "future_net_worth" => 0, // current_net_worth + estimated_upcoming_pay
    "projected_net_worth_after_next_rent" => 0,
    "projected_net_worth_after_next_utilities" => 0,
    "net_worth_history" => [],
    "debug_pay_period_start" => null, // For the single next check
    "debug_pay_period_end" => null,   // For the single next check
];

$rentAmount = 1100.99; // Standard rent amount from original user code
$today = new DateTime(); // Define $today early for broad scope if needed for rent logic.
$today->setTime(0,0,0);


try {
    // --- snapshots & current net worth (largely from user's new version) ---
    $latest_snapshot_id = $pdo
        ->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC, id DESC LIMIT 1")
        ->fetchColumn();

    if ($latest_snapshot_id) {
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN a.type = 'Asset' THEN b.balance ELSE -b.balance END) as net_worth
            FROM balances b JOIN accounts a ON a.id = b.account_id WHERE b.snapshot_id = ?
        ");
        $stmt->execute([$latest_snapshot_id]);
        $response["current_net_worth"] = round((float) $stmt->fetchColumn(), 2);

        $cash_accounts = ["Truist Checking", "Capital One Savings", "Apple Savings"];
        $ph = rtrim(str_repeat('?,', count($cash_accounts)), ',');
        $stmt_cash = $pdo->prepare("
            SELECT SUM(b.balance) FROM balances b JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.name IN ($ph)
        ");
        $stmt_cash->execute(array_merge([$latest_snapshot_id], $cash_accounts));
        $response["total_cash_on_hand"] = (float) $stmt_cash->fetchColumn();

        $stmt_rec = $pdo->prepare("
            SELECT b.balance FROM balances b JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.name = 'Receivables'
        ");
        $stmt_rec->execute([$latest_snapshot_id]);
        $response["receivables_balance"] = (float) $stmt_rec->fetchColumn();

        $stmt_lia = $pdo->prepare("
            SELECT SUM(b.balance) FROM balances b JOIN accounts a ON a.id = b.account_id
            WHERE b.snapshot_id = ? AND a.type = 'Liability'
        ");
        $stmt_lia->execute([$latest_snapshot_id]);
        $response["total_liabilities"] = (float) $stmt_lia->fetchColumn();
    }

    // --- net worth history (from user's new version) ---
    $stmt_history = $pdo->query("
        SELECT s.snapshot_date AS date,
               SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END) -
               SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END) AS networth
        FROM balances b JOIN accounts a ON a.id = b.account_id JOIN snapshots s ON b.snapshot_id = s.id
        WHERE s.id IN (SELECT MAX(id) FROM snapshots GROUP BY snapshot_date)
        GROUP BY s.snapshot_date ORDER BY s.snapshot_date
    ");
    $response["net_worth_history"] = array_map(function ($row) {
        $row['networth'] = (float) $row['networth'];
        return $row;
    }, $stmt_history->fetchAll(PDO::FETCH_ASSOC));

    // --- Pay Calculation Logic ---
    $settings_query = $pdo->query("
        SELECT setting_key, setting_value FROM app_settings
        WHERE setting_key IN ('pay_rate','federal_tax_rate','state_tax_rate')
    ");
    $app_settings = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);

    $upcoming_paydays_for_cycle = []; // To store DateTime objects of paydays in the current decision cycle
    $true_next_payday_obj = null;    // The very next DateTime object for payday

    if (isset($app_settings['pay_rate'])) {
        $pay_rate = (float) $app_settings['pay_rate'];
        $federal_tax_rate = (float) ($app_settings['federal_tax_rate'] ?? 0);
        $state_tax_rate = (float) ($app_settings['state_tax_rate'] ?? 0);

        // $today already defined globally
        $current_year = (int) $today->format('Y');
        $current_month_num = (int) $today->format('n');

        $current_month_payday1 = getAdjustedPaydate($current_year, $current_month_num, 15);
        $current_month_payday2 = getAdjustedPaydate($current_year, $current_month_num, 0); // 0 for last day

        if ($today <= $current_month_payday1) {
            $upcoming_paydays_for_cycle = [$current_month_payday1, $current_month_payday2];
        } elseif ($today <= $current_month_payday2) {
            $upcoming_paydays_for_cycle = [$current_month_payday2];
        } else {
            $next_month_obj = (clone $today)->modify('+1 month');
            $upcoming_paydays_for_cycle = [getAdjustedPaydate((int) $next_month_obj->format('Y'), (int) $next_month_obj->format('n'), 15)];
        }

        if (!empty($upcoming_paydays_for_cycle)) {
            $true_next_payday_obj = clone reset($upcoming_paydays_for_cycle);
        }

        // Calculate net pay for the single upcoming paycheck (for display: estimated_upcoming_pay)
        if ($true_next_payday_obj) {
            // MODIFICATION: Pay Period End Date: -8 days from payday
            $pay_period_end_date_obj = (clone $true_next_payday_obj)->modify('-8 days')->setTime(0,0,0);
            // MODIFICATION: Pay Period Start Date: -13 days from end date
            $pay_period_start_date_obj = (clone $pay_period_end_date_obj)->modify('-13 days')->setTime(0,0,0);

            $stmt_logged = $pdo->prepare("SELECT log_date, hours_worked FROM logged_hours WHERE log_date BETWEEN ? AND ?");
            $stmt_logged->execute([$pay_period_start_date_obj->format('Y-m-d'), $pay_period_end_date_obj->format('Y-m-d')]);

            $explicitly_logged_hours = [];
            foreach ($stmt_logged->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $explicitly_logged_hours[$row['log_date']] = (float) $row['hours_worked'];
            }

            $total_hours_for_next_check = 0;
            $jobStartDate = (new DateTime('2025-05-20'))->setTime(0,0,0);

            for ($loop_date = clone $pay_period_start_date_obj; $loop_date <= $pay_period_end_date_obj; $loop_date->modify('+1 day')) {
                $date_str = $loop_date->format('Y-m-d');
                $day_of_week = (int) $loop_date->format('N');
                if ($loop_date >= $jobStartDate) {
                    if (isset($explicitly_logged_hours[$date_str])) {
                        $total_hours_for_next_check += $explicitly_logged_hours[$date_str];
                    } elseif ($day_of_week <= 5) { // Weekday
                        // MODIFICATION: Reinstate 7.5 hour default
                        $total_hours_for_next_check += 7.5;
                    }
                }
            }

            $gross_for_next_check = $total_hours_for_next_check * $pay_rate;
            $net_for_next_check = $gross_for_next_check * (1 - $federal_tax_rate - $state_tax_rate);

            $response["estimated_upcoming_pay"] = round($net_for_next_check, 2);
            $response["next_pay_date"] = $true_next_payday_obj->format('Y-m-d');
            $response["debug_pay_period_start"] = $pay_period_start_date_obj->format('Y-m-d');
            $response["debug_pay_period_end"] = $pay_period_end_date_obj->format('Y-m-d');
        }

        $response['is_pay_day'] = (($current_month_payday1 && $today->format('Y-m-d') === $current_month_payday1->format('Y-m-d')) ||
                                   ($current_month_payday2 && $today->format('Y-m-d') === $current_month_payday2->format('Y-m-d')));

        if ($response['is_pay_day']) {
            $response['future_net_worth'] = $response['current_net_worth'];
             // On payday, estimated_upcoming_pay is already set to the *next* cycle's first check.
        } else {
            $response['future_net_worth'] = round($response['current_net_worth'] + $response['estimated_upcoming_pay'], 2);
        }
    }

    // --- Projected Net Worth After Next Rent (MODIFIED LOGIC to meet "12th vs 31st") ---
    $pay_earned_before_immediate_rent = 0;
    // Determine the immediate next rent month (e.g., July 1st if today is in June)
    $immediate_rent_month_obj = (clone $today)->modify('first day of next month');
    $immediate_rent_month_str = $immediate_rent_month_obj->format('Y-m-d');

    $last_day_of_current_calendar_month_obj = (clone $today)->modify('last day of this month');

    if (isset($app_settings['pay_rate']) && !empty($upcoming_paydays_for_cycle)) {
        $pay_rate_for_rent_calc = (float) $app_settings['pay_rate'];
        $federal_tax_rate_for_rent_calc = (float) ($app_settings['federal_tax_rate'] ?? 0);
        $state_tax_rate_for_rent_calc = (float) ($app_settings['state_tax_rate'] ?? 0);
        $jobStartDate_for_rent_calc = (new DateTime('2025-05-20'))->setTime(0,0,0);

        foreach ($upcoming_paydays_for_cycle as $payday_date_obj) {
            // Only consider paydays that fall within the current calendar month
            // and are on or before the last day of the current month.
            if ($payday_date_obj <= $last_day_of_current_calendar_month_obj) {
                $pay_period_end_obj = (clone $payday_date_obj)->modify('-8 days')->setTime(0,0,0);
                $pay_period_start_obj = (clone $pay_period_end_obj)->modify('-13 days')->setTime(0,0,0);

                $stmt_logged_hrs = $pdo->prepare("SELECT log_date, hours_worked FROM logged_hours WHERE log_date BETWEEN ? AND ?");
                $stmt_logged_hrs->execute([$pay_period_start_obj->format('Y-m-d'), $pay_period_end_obj->format('Y-m-d')]);

                $logged_hrs_map = [];
                foreach ($stmt_logged_hrs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $logged_hrs_map[$r['log_date']] = (float) $r['hours_worked'];
                }

                $total_hrs = 0;
                for ($loop_d = clone $pay_period_start_obj; $loop_d <= $pay_period_end_obj; $loop_d->modify('+1 day')) {
                    $d_str = $loop_d->format('Y-m-d');
                    $d_of_w = (int) $loop_d->format('N');
                    if ($loop_d >= $jobStartDate_for_rent_calc) {
                        if (isset($logged_hrs_map[$d_str])) {
                            $total_hrs += $logged_hrs_map[$d_str];
                        } elseif ($d_of_w <= 5) { // Weekday
                            $total_hrs += 7.5; // Default hours
                        }
                    }
                }
                $gross = $total_hrs * $pay_rate_for_rent_calc;
                $net = $gross * (1 - $federal_tax_rate_for_rent_calc - $state_tax_rate_for_rent_calc);
                $pay_earned_before_immediate_rent += $net;
            }
        }
    }

    $actual_rent_to_subtract = $rentAmount; // Default to full rent amount
    // MODIFICATION: Check prepaid rent for the immediate_rent_month_str
    $stmt_rent_check = $pdo->prepare("SELECT amount FROM rent_payments WHERE rent_month = ?");
    $stmt_rent_check->execute([$immediate_rent_month_str]);
    $prepaid_rent_details = $stmt_rent_check->fetch(PDO::FETCH_ASSOC);
    if ($prepaid_rent_details) {
        $actual_rent_to_subtract = 0; // Rent is prepaid
    }

    $response['projected_net_worth_after_next_rent'] = round(
        $response['current_net_worth'] + $pay_earned_before_immediate_rent - $actual_rent_to_subtract,
        2
    );

    // --- Projected Net Worth After Next Utilities ---
    // (Using $true_next_payday_obj which is the single very next payday)
    $nextPeriodUnpaidUtilitiesShare = 0;
    $userPersonNameForUtils = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;

    // Ensure $true_next_payday_obj is a DateTimeInterface (it might be null if no pay_rate setting)
    if ($userPersonNameForUtils && isset($pdoUtilities) && $true_next_payday_obj instanceof DateTimeInterface) {
        $utilityMonthRefDate = clone $true_next_payday_obj;

        if ((int) $utilityMonthRefDate->format('d') > 15) {
            $utilityMonthRefDate->modify('first day of next month');
        } else {
            $utilityMonthRefDate->modify('first day of this month');
        }

        $targetUtilityStart = $utilityMonthRefDate->format('Y-m-01');
        $targetUtilityEnd = $utilityMonthRefDate->format('Y-m-t');

        $stmtUtils = $pdoUtilities->prepare("
            SELECT u.fldTotal FROM tblUtilities u
            JOIN tblBillOwes bo ON u.pmkBillID = bo.billID
            JOIN tblPeople p ON bo.personID = p.personID
            WHERE p.personName = :personName AND u.fldStatus = 'Unpaid'
            AND u.fldDue BETWEEN :startDt AND :endDt
        ");
        $stmtUtils->execute([
            ':personName' => $userPersonNameForUtils,
            ':startDt' => $targetUtilityStart,
            ':endDt' => $targetUtilityEnd
        ]);
        foreach ($stmtUtils->fetchAll(PDO::FETCH_ASSOC) as $bill) {
            // Ensure fldTotal is treated as a number and division by zero is avoided if necessary, though /3 is specific.
            $billTotal = floatval($bill['fldTotal']);
            if ($billTotal > 0) { // Check if bill total is positive
                 $nextPeriodUnpaidUtilitiesShare += round($billTotal / 3, 2); // Assuming 3 people split
            }
        }
    }
    // Utilities are subtracted from the post-rent projection
    $response["projected_net_worth_after_next_utilities"] = round(
        $response["projected_net_worth_after_next_rent"] - $nextPeriodUnpaidUtilitiesShare,
        2
    );

    echo json_encode($response);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    error_log("PDOException in api_financial_summary.php: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Exception in api_financial_summary.php: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'General error: ' . $e->getMessage()]);
    exit;
}
?>