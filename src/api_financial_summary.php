<?php
require_once 'db.php';
header('Content-Type: application/json');

$response = [
    "current_net_worth" => 0,
    "total_cash_on_hand" => 0,
    "receivables_balance" => 0,
    "total_liabilities" => 0,
    "gross_estimated_pay" => 0,
    "estimated_federal_tax" => 0,
    "estimated_state_tax" => 0,
    "estimated_upcoming_pay" => 0,
    "next_pay_date" => null,
    "future_net_worth" => 0,
    "projected_net_worth_after_next_rent" => 0,
    "projected_net_worth_after_next_utilities" => 0,
    "net_worth_history" => [],
    "debug_pay_period_start" => null,
    "debug_pay_period_end" => null,
];

$rentAmount = 1100.99;

try {
    // Get latest snapshot ID and calculate raw_snapshot_net_worth
    $latest_snapshot_id_stmt = $pdo->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC, id DESC LIMIT 1");
    $latest_snapshot_id = $latest_snapshot_id_stmt->fetchColumn();

    $raw_snapshot_net_worth = 0;
    if ($latest_snapshot_id) {
        $stmt = $pdo->prepare("SELECT SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE -b.balance END) as net_worth FROM balances b JOIN accounts a ON a.id = b.account_id WHERE b.snapshot_id = ?");
        $stmt->execute([$latest_snapshot_id]);
        $raw_snapshot_net_worth = floatval($stmt->fetchColumn() ?: 0);
        $response["current_net_worth"] = round($raw_snapshot_net_worth, 2);

        // ... (Existing code for total_cash_on_hand, receivables_balance, total_liabilities - unchanged) ...
        $cash_accounts = ["Truist Checking", "Capital One Savings", "Apple Savings"];
        $placeholders = rtrim(str_repeat('?,', count($cash_accounts)), ',');
        $stmt_cash = $pdo->prepare("SELECT SUM(b.balance) as total_cash FROM balances b JOIN accounts a ON a.id = b.account_id WHERE b.snapshot_id = ? AND a.name IN ($placeholders)");
        $params_cash = array_merge([$latest_snapshot_id], $cash_accounts);
        $stmt_cash->execute($params_cash);
        $response["total_cash_on_hand"] = floatval($stmt_cash->fetchColumn() ?: 0);
        $stmt_rec = $pdo->prepare("SELECT b.balance FROM balances b JOIN accounts a ON a.id = b.account_id WHERE b.snapshot_id = ? AND a.name = 'Receivables'");
        $stmt_rec->execute([$latest_snapshot_id]);
        $response["receivables_balance"] = floatval($stmt_rec->fetchColumn() ?: 0);
        $stmt_lia = $pdo->prepare("SELECT SUM(b.balance) as total_liabilities FROM balances b JOIN accounts a ON a.id = b.account_id WHERE b.snapshot_id = ? AND a.type = 'Liability'");
        $stmt_lia->execute([$latest_snapshot_id]);
        $response["total_liabilities"] = floatval($stmt_lia->fetchColumn() ?: 0);
    }

    // ... (Existing code for net_worth_history - unchanged) ...
    $sql_history = "SELECT s.snapshot_date AS date, SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END) - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END) AS networth FROM balances b JOIN accounts a ON a.id = b.account_id JOIN snapshots s ON b.snapshot_id = s.id WHERE s.id IN (SELECT MAX(id) FROM snapshots GROUP BY snapshot_date) GROUP BY s.snapshot_date ORDER BY s.snapshot_date ASC";
    $stmt_history = $pdo->query($sql_history);
    $response["net_worth_history"] = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    foreach ($response["net_worth_history"] as &$item) {
        $item["networth"] = floatval($item["networth"]);
    }

    // ... (Full existing pay calculation logic - populates $response["estimated_upcoming_pay"], $response["is_pay_day"], $response["next_pay_date"], and $true_next_payday_obj - unchanged) ...
    $true_next_payday_obj = null; // Will be populated by pay calc logic
    // --- Start: Pay calculation logic ---
    $response["gross_estimated_pay"] = 0.00;
    $response["estimated_federal_tax"] = 0.00;
    $response["estimated_state_tax"] = 0.00;
    $response["estimated_upcoming_pay"] = 0.00;
    $response["is_pay_day"] = false;
    function getAdjustedPaydate(int $year, int $month, int $day): DateTime
    {
        $date = new DateTime();
        if ($day === 0) {
            $date->setDate($year, $month, 1)->modify('last day of this month');
        } else {
            $date->setDate($year, $month, $day);
        }
        $date->setTime(0, 0, 0);
        $dayOfWeek = (int) $date->format('N');
        if ($dayOfWeek === 6) {
            $date->modify('-1 day');
        } elseif ($dayOfWeek === 7) {
            $date->modify('-2 days');
        }
        return $date;
    }
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('pay_rate', 'federal_tax_rate', 'state_tax_rate')");
    $app_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }
    if (isset($app_settings['pay_rate'])) {
        $pay_rate = floatval($app_settings['pay_rate']);
        $federal_tax_rate_value = isset($app_settings['federal_tax_rate']) ? floatval($app_settings['federal_tax_rate']) : 0;
        $state_tax_rate_value = isset($app_settings['state_tax_rate']) ? floatval($app_settings['state_tax_rate']) : 0;
        $current_date_time = new DateTime();
        $current_date_time->setTime(0, 0, 0);
        $current_year = (int) $current_date_time->format('Y');
        $current_month = (int) $current_date_time->format('n');
        $payday1_current_month = getAdjustedPaydate($current_year, $current_month, 15);
        $payday2_current_month = getAdjustedPaydate($current_year, $current_month, 0);
        // $true_next_payday_obj defined here for later use
        if ($current_date_time <= $payday1_current_month) {
            $true_next_payday_obj = clone $payday1_current_month;
        } elseif ($current_date_time <= $payday2_current_month) {
            $true_next_payday_obj = clone $payday2_current_month;
        } else {
            $next_month_date_for_payday = (clone $current_date_time)->modify('+1 month');
            $true_next_payday_obj = getAdjustedPaydate((int) $next_month_date_for_payday->format('Y'), (int) $next_month_date_for_payday->format('n'), 15);
        }
        $response["next_pay_date"] = $true_next_payday_obj->format('Y-m-d');
        $pay_period_end_date_obj = (clone $true_next_payday_obj)->modify('-5 days')->setTime(0, 0, 0);
        $pay_period_start_date_obj = (clone $pay_period_end_date_obj)->modify('-13 days')->setTime(0, 0, 0);
        $response["debug_pay_period_start"] = $pay_period_start_date_obj->format('Y-m-d');
        $response["debug_pay_period_end"] = $pay_period_end_date_obj->format('Y-m-d');
        $jobStartDate = new DateTime('2025-05-20');
        $jobStartDate->setTime(0, 0, 0);
        if ($current_date_time->format('Y-m-d') === $payday1_current_month->format('Y-m-d') || $current_date_time->format('Y-m-d') === $payday2_current_month->format('Y-m-d')) {
            $response["is_pay_day"] = true;
        }
        if ($response["is_pay_day"]) { /* pay is 0 */
        } else {
            $stmt_logged = $pdo->prepare("SELECT log_date, hours_worked FROM logged_hours WHERE log_date BETWEEN ? AND ?");
            $stmt_logged->execute([$pay_period_start_date_obj->format('Y-m-d'), $pay_period_end_date_obj->format('Y-m-d')]);
            $logged_hours_for_period_raw = $stmt_logged->fetchAll(PDO::FETCH_ASSOC);
            $explicitly_logged_hours = [];
            foreach ($logged_hours_for_period_raw as $row) {
                $explicitly_logged_hours[$row['log_date']] = (float) $row['hours_worked'];
            }
            $total_hours_for_period = 0.0;
            $loop_date = clone $pay_period_start_date_obj;
            while ($loop_date <= $pay_period_end_date_obj) {
                $date_str = $loop_date->format('Y-m-d');
                $day_of_week = (int) $loop_date->format('N');
                if ($loop_date >= $jobStartDate && $day_of_week >= 1 && $day_of_week <= 5) {
                    if (isset($explicitly_logged_hours[$date_str])) {
                        $total_hours_for_period += $explicitly_logged_hours[$date_str];
                    } else {
                        $total_hours_for_period += 7.5;
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
    // --- End: Pay calculation logic ---

    // Calculate future_net_worth (Raw Snapshot Current Net Worth + Upcoming Pay)
    if ($response["is_pay_day"]) {
        $response["future_net_worth"] = $response["current_net_worth"];
    } else {
        $response["future_net_worth"] = round($response["current_net_worth"] + $response["estimated_upcoming_pay"], 2);
    }

    // Calculate projected_net_worth_after_next_rent (future_net_worth - Rent for the month after paycheck)
    $response["projected_net_worth_after_next_rent"] = round($response["future_net_worth"] - $rentAmount, 2);

    // Calculate projected_net_worth_after_next_utilities
    $nextPeriodUnpaidUtilitiesShare = 0;
    $userPersonName = $_ENV['UTILITIES_USER_PERSON_NAME'] ?? null;

    if ($userPersonName && isset($pdoUtilities) && isset($true_next_payday_obj)) {
        try {
            // Determine the month for "next rent" and "next utilities".
            // This is the month in which the next rent (after the upcoming paycheck) is due.
            // If next payday is June 30th, next rent is July 1st, so utilities are for July.
            // If next payday is July 15th, next rent is Aug 1st (assuming rent paid from that check is for Aug), so utilities for Aug.
            // A common scenario: rent is due on 1st. Paycheck on 15th and 30th.
            // If current date is June 10th: next payday June 15th. future_net_worth includes this. Rent for July 1st is after this.
            // If current date is June 20th: next payday June 30th. future_net_worth includes this. Rent for July 1st is after this.

            // The month of $true_next_payday_obj. Rent is due on the 1st of a month.
            // If $true_next_payday_obj is June 15th, the rent from this is for June (or already paid). The *next* rent is July 1st.
            // If $true_next_payday_obj is June 30th, the rent from this is for July 1st.
            // So, the month of the "next rent" that this projection considers is the month of $true_next_payday_obj if its day is >=16 (approx), or month after if day is <16.
            // More simply, the rent payment that would typically follow the receipt of $true_next_payday_obj.
            // Let's assume the "next rent" is for the month *of* or *immediately following* the $true_next_payday_obj.
            // For utilities, we'll target the month of $true_next_payday_obj. If $true_next_payday_obj is end of June, utilities for June.
            // If user meant "rent for month M+1, utilities for month M+1", then:

            $utilityMonthDate = clone $true_next_payday_obj;
            // If the next payday is late in the month (e.g. after 15th), assume rent paid from it is for next month.
            if ((int) $true_next_payday_obj->format('d') > 15) {
                $utilityMonthDate->modify('first day of next month');
            } else {
                // If payday is early/mid month, assume rent paid is for current month of payday.
                $utilityMonthDate->modify('first day of this month');
            }

            $targetUtilityMonthStart = $utilityMonthDate->format('Y-m-01');
            $targetUtilityMonthEnd = $utilityMonthDate->format('Y-m-t');

            $sqlUtilsNext = "
                SELECT u.fldTotal FROM tblUtilities u
                JOIN tblBillOwes bo ON u.pmkBillID = bo.billID
                JOIN tblPeople p ON bo.personID = p.personID
                WHERE p.personName = :personName AND u.fldStatus = 'Unpaid'
                AND u.fldDue BETWEEN :targetMonthStart AND :targetMonthEnd
            ";
            $stmtUtilsNext = $pdoUtilities->prepare($sqlUtilsNext);
            $stmtUtilsNext->bindParam(':personName', $userPersonName, PDO::PARAM_STR);
            $stmtUtilsNext->bindParam(':targetMonthStart', $targetUtilityMonthStart, PDO::PARAM_STR);
            $stmtUtilsNext->bindParam(':targetMonthEnd', $targetUtilityMonthEnd, PDO::PARAM_STR);
            $stmtUtilsNext->execute();
            $utilityBillsNextPeriod = $stmtUtilsNext->fetchAll(PDO::FETCH_ASSOC);

            foreach ($utilityBillsNextPeriod as $bill) {
                if ($bill['fldTotal'] > 0) {
                    $nextPeriodUnpaidUtilitiesShare += round(floatval($bill['fldTotal']) / 3, 2);
                }
            }
        } catch (PDOException $e) {
            error_log("PDOException in api_financial_summary.php (Next Period UNPAID Utilities): " . $e->getMessage());
        }
    }
    $response["projected_net_worth_after_next_utilities"] = round($response["projected_net_worth_after_next_rent"] - $nextPeriodUnpaidUtilitiesShare, 2);


    echo json_encode($response);
    exit; // <-- Add this line

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Main PDOException: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit; // <-- Add this line
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Exception: " . $e->getMessage());
    echo json_encode(['error' => 'General error: ' . $e->getMessage()]);
    exit; // <-- Add this line
}