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

    // Helper function to adjust a date to Friday if it's a weekend
    function adjustToGivenDayOfWeek(DateTime $date, int $targetDayOfWeek = 5): DateTime { // 5 for Friday
        $currentDayOfWeek = (int)$date->format('N');
        if ($targetDayOfWeek >= 1 && $targetDayOfWeek <=7) {
            if ($currentDayOfWeek > $targetDayOfWeek) { // e.g. Sat (6) > Fri (5) or Sun (7) > Fri (5)
                 $date->modify('-' . ($currentDayOfWeek - $targetDayOfWeek) . ' days');
            } elseif ($currentDayOfWeek < $targetDayOfWeek) { // e.g. Mon (1) < Fri (5)
                 // This case might not be what we want for "adjustToFriday" from weekend,
                 // but could be useful if we wanted to find "next occurring Friday".
                 // For now, this function is mostly for "previous or current Friday if weekend".
                 // The primary use is: if Sat, go to Fri. If Sun, go to Fri.
                 // Let's stick to the simpler logic for weekend adjustment to previous Friday.
            }
        }
        // Simplified for weekend adjustment to PRECEDING Friday
        $dayOfWeek = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
        if ($dayOfWeek === 6) { // Saturday
            $date->modify('-1 day');
        } elseif ($dayOfWeek === 7) { // Sunday
            $date->modify('-2 days');
        }
        return $date;
    }

    // Fetch all relevant app settings
    $settings_query_keys = [
        'pay_rate', 'federal_tax_rate', 'state_tax_rate',
        'pay_schedule_type', 'pay_schedule_detail1', 'pay_schedule_detail2'
    ];
    $placeholders = rtrim(str_repeat('?,', count($settings_query_keys)), ',');
    $stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
    $stmt_settings->execute($settings_query_keys);
    $app_settings = [];
    while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }

    $pay_rate = isset($app_settings['pay_rate']) ? floatval($app_settings['pay_rate']) : 0;
    $federal_tax_rate_value = isset($app_settings['federal_tax_rate']) ? floatval($app_settings['federal_tax_rate']) : 0;
    $state_tax_rate_value = isset($app_settings['state_tax_rate']) ? floatval($app_settings['state_tax_rate']) : 0;

    $pay_schedule_type = $app_settings['pay_schedule_type'] ?? 'semi-monthly'; // Default
    $ps_detail1_setting = $app_settings['pay_schedule_detail1'] ?? null;
    $ps_detail2_setting = $app_settings['pay_schedule_detail2'] ?? null;

    $current_date_time = new DateTimeImmutable(); // Use Immutable for safety
    $current_date_time = $current_date_time->setTime(0,0,0);

    $true_next_payday_obj = null;
    $all_potential_paydays_this_and_next_month = []; // Store DateTimeImmutable objects

    // --- Determine Next Payday ---
    if ($pay_schedule_type === 'bi-weekly') {
        if (empty($ps_detail1_setting) || !($referenceFriday = DateTimeImmutable::createFromFormat('Y-m-d', $ps_detail1_setting))) {
            // Handle error: missing or invalid reference Friday for bi-weekly
            throw new Exception("Bi-weekly schedule is set but reference Friday (pay_schedule_detail1) is missing or invalid.");
        }
        $referenceFriday = $referenceFriday->setTime(0,0,0);
        // Adjust reference to be an actual Friday if it's not (e.g. user error in settings)
        while((int)$referenceFriday->format('N') !== 5) {
            $referenceFriday = $referenceFriday->modify('+1 day');
        }

        $temp_payday = clone $referenceFriday;
        // Go back to find a cycle start before or equal to current date to ensure we find the *next* one correctly
        while ($temp_payday > $current_date_time) {
            $temp_payday = $temp_payday->modify('-14 days');
        }
        // Now find the first payday on or after current_date_time
        while ($temp_payday < $current_date_time) {
            $temp_payday = $temp_payday->modify('+14 days');
        }
        $true_next_payday_obj = clone $temp_payday; // This is the upcoming one
        // For bi-weekly, also add the one after that for safety in period calculation if needed
        $all_potential_paydays_this_and_next_month[] = $true_next_payday_obj;
        $all_potential_paydays_this_and_next_month[] = $true_next_payday_obj->modify('+14 days');


    } elseif ($pay_schedule_type === 'semi-monthly') {
        $day1 = ($ps_detail1_setting !== null && $ps_detail1_setting !== '') ? (int)$ps_detail1_setting : 15;
        $day2_val = ($ps_detail2_setting !== null && $ps_detail2_setting !== '') ? (int)$ps_detail2_setting : 0;

        $current_month_paydays = [];
        $next_month_paydays = [];

        $current_month_dt = new DateTime($current_date_time->format('Y-m-01'));
        $next_month_dt = (new DateTime($current_date_time->format('Y-m-01')))->modify('+1 month');

        // Paydays for current month
        $pd1_curr = adjustToGivenDayOfWeek((clone $current_month_dt)->setDate((int)$current_month_dt->format('Y'), (int)$current_month_dt->format('n'), $day1));
        if ($pd1_curr >= $current_date_time) $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable($pd1_curr);

        $pd2_curr_base = clone $current_month_dt;
        if ($day2_val === 0) $pd2_curr_base->modify('last day of this month');
        else $pd2_curr_base->setDate((int)$current_month_dt->format('Y'), (int)$current_month_dt->format('n'), $day2_val);
        $pd2_curr = adjustToGivenDayOfWeek($pd2_curr_base);
        if ($pd2_curr >= $current_date_time && $pd2_curr->format('Y-m-d') !== $pd1_curr->format('Y-m-d')) {
             $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable($pd2_curr);
        }

        // Paydays for next month (to ensure we always have a future one)
        $pd1_next = adjustToGivenDayOfWeek((clone $next_month_dt)->setDate((int)$next_month_dt->format('Y'), (int)$next_month_dt->format('n'), $day1));
        $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable($pd1_next);

        $pd2_next_base = clone $next_month_dt;
        if ($day2_val === 0) $pd2_next_base->modify('last day of this month');
        else $pd2_next_base->setDate((int)$next_month_dt->format('Y'), (int)$next_month_dt->format('n'), $day2_val);
        $pd2_next = adjustToGivenDayOfWeek($pd2_next_base);
         if ($pd2_next->format('Y-m-d') !== $pd1_next->format('Y-m-d')) {
            $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable($pd2_next);
        }

    } elseif ($pay_schedule_type === 'monthly') {
        $day_val = ($ps_detail1_setting !== null && $ps_detail1_setting !== '') ? (int)$ps_detail1_setting : 0;

        $current_month_dt = new DateTime($current_date_time->format('Y-m-01'));
        $next_month_dt = (new DateTime($current_date_time->format('Y-m-01')))->modify('+1 month');
        $month_after_next_dt = (new DateTime($current_date_time->format('Y-m-01')))->modify('+2 months');

        $pd_curr_base = clone $current_month_dt;
        if ($day_val === 0) $pd_curr_base->modify('last day of this month');
        else $pd_curr_base->setDate((int)$current_month_dt->format('Y'), (int)$current_month_dt->format('n'), $day_val);
        $pd_curr = adjustToGivenDayOfWeek($pd_curr_base);
        if ($pd_curr >= $current_date_time) $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable($pd_curr);

        $pd_next_base = clone $next_month_dt;
        if ($day_val === 0) $pd_next_base->modify('last day of this month');
        else $pd_next_base->setDate((int)$next_month_dt->format('Y'), (int)$next_month_dt->format('n'), $day_val);
        $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable(adjustToGivenDayOfWeek($pd_next_base));

        $pd_after_next_base = clone $month_after_next_dt; // Ensure we have one far enough out
        if ($day_val === 0) $pd_after_next_base->modify('last day of this month');
        else $pd_after_next_base->setDate((int)$month_after_next_dt->format('Y'), (int)$month_after_next_dt->format('n'), $day_val);
        $all_potential_paydays_this_and_next_month[] = DateTimeImmutable::createFromMutable(adjustToGivenDayOfWeek($pd_after_next_base));
    }

    // Sort all potential paydays and find the earliest one on or after today
    if (!empty($all_potential_paydays_this_and_next_month)) {
        // Remove duplicates that might occur due to adjustments
        $unique_paydays_str = [];
        foreach ($all_potential_paydays_this_and_next_month as $pd) {
            $unique_paydays_str[$pd->format('Y-m-d')] = $pd;
        }
        $all_potential_paydays_this_and_next_month = array_values($unique_paydays_str);

        usort($all_potential_paydays_this_and_next_month, function(DateTimeImmutable $a, DateTimeImmutable $b) {
            return $a <=> $b;
        });

        foreach ($all_potential_paydays_this_and_next_month as $pd_obj) {
            if ($pd_obj >= $current_date_time) {
                $true_next_payday_obj = $pd_obj;
                break;
            }
        }
    }

    if (!$true_next_payday_obj) {
        // This should not happen if logic is correct and settings are present
        throw new Exception("Could not determine the next payday. Check pay schedule settings and logic.");
    }
    $response["next_pay_date"] = $true_next_payday_obj->format('Y-m-d');

    // --- Determine Pay Period based on $true_next_payday_obj and user's definition ---
    // User: "imagine two weeks go by, then i get paid the next friday for those two weeks,
    // and the week leading up to pay day, goes into the next paycheck."
    // Payday (Friday, P)
    // Week of Pay (Mon P-4 to Fri P) -> This work goes to *next* paycheck.
    // Paid Period (Mon P-18 to Fri P-7) -> This is the 2 weeks of work paid on P.

    if ($pay_schedule_type === 'bi-weekly') {
        // Payday (P = $true_next_payday_obj, which is a Friday)
        // Paid Period: Monday (P-18 days) to Friday (P-7 days)
        $pay_period_start_date_obj = $true_next_payday_obj->modify('-18 days');
        $pay_period_end_date_obj = $true_next_payday_obj->modify('-7 days');
    } else { // semi-monthly or monthly - ADAPT THIS PART CAREFULLY
        // The old logic was:
        // $pay_period_end_date_obj = (clone $true_next_payday_obj)->modify('-5 days')->setTime(0, 0, 0);
        // $pay_period_start_date_obj = (clone $pay_period_end_date_obj)->modify('-13 days')->setTime(0, 0, 0);
        // This assumed a pay period ending 5 days before payday and lasting 14 days.
        // This needs to be re-evaluated for semi-monthly and monthly.
        // For now, let's make a simpler assumption for non-bi-weekly:
        // Pay period ends the day before the payday.
        // Pay period starts based on the *previous* payday.

        // To implement this correctly, we need the payday *before* $true_next_payday_obj
        $previous_payday_obj = null;
        $paydays_for_period_calc = []; // Re-fetch or re-calculate paydays for a broader range if needed

        // Simplified placeholder for semi-monthly/monthly pay period (NEEDS REVIEW AND USER FEEDBACK)
        // This will likely not match the user's expectation for bi-weekly if schedule type changes.
        // For now, let's assume pay period is from previous payday (or start of month) to day before current payday

        // Find payday immediately preceding $true_next_payday_obj to define the start of the pay period.
        // For this, we need a list of paydays that definitely includes dates before $true_next_payday_obj.
        // The $all_potential_paydays_this_and_next_month might only contain future dates.
        // Let's generate a broader list for period determination if needed.

        $paydays_for_period_determination = [];
        $num_months_to_check_around = 2; // Check current month, N previous, N next for robustness

        $temp_current_month_start = new DateTimeImmutable($current_date_time->format('Y-m-01'));

        for ($i = -$num_months_to_check_around; $i <= $num_months_to_check_around; $i++) {
            $iter_month_start = $temp_current_month_start->modify("$i month");
            $iter_year = (int)$iter_month_start->format('Y');
            $iter_month = (int)$iter_month_start->format('n');

            if ($pay_schedule_type === 'semi-monthly') {
                $day1 = ($ps_detail1_setting !== null && $ps_detail1_setting !== '') ? (int)$ps_detail1_setting : 15;
                $day2_val = ($ps_detail2_setting !== null && $ps_detail2_setting !== '') ? (int)$ps_detail2_setting : 0;

                $pd1_mut = (new DateTime())->setDate($iter_year, $iter_month, $day1)->setTime(0,0,0);
                $paydays_for_period_determination[] = DateTimeImmutable::createFromMutable(adjustToGivenDayOfWeek($pd1_mut));

                $pd2_mut_base = (new DateTime())->setDate($iter_year, $iter_month, 1)->setTime(0,0,0);
                if ($day2_val === 0) $pd2_mut_base->modify('last day of this month');
                else $pd2_mut_base->setDate($iter_year, $iter_month, $day2_val);
                $paydays_for_period_determination[] = DateTimeImmutable::createFromMutable(adjustToGivenDayOfWeek($pd2_mut_base));

            } elseif ($pay_schedule_type === 'monthly') {
                $day_val = ($ps_detail1_setting !== null && $ps_detail1_setting !== '') ? (int)$ps_detail1_setting : 0;
                $pd_mut_base = (new DateTime())->setDate($iter_year, $iter_month, 1)->setTime(0,0,0);
                if ($day_val === 0) $pd_mut_base->modify('last day of this month');
                else $pd_mut_base->setDate($iter_year, $iter_month, $day_val);
                $paydays_for_period_determination[] = DateTimeImmutable::createFromMutable(adjustToGivenDayOfWeek($pd_mut_base));
            }
        }

        // Consolidate and sort all paydays found for period determination
        $unique_paydays_str_period = [];
        foreach ($paydays_for_period_determination as $pd) {
            $unique_paydays_str_period[$pd->format('Y-m-d')] = $pd;
        }
        $paydays_for_period_determination = array_values($unique_paydays_str_period);
        usort($paydays_for_period_determination, function(DateTimeImmutable $a, DateTimeImmutable $b) {
            return $a <=> $b;
        });

        $previous_payday_obj_for_period_start = null;
        // Find the payday in this list that is immediately before $true_next_payday_obj
        for ($j = count($paydays_for_period_determination) - 1; $j >= 0; $j--) {
            if ($paydays_for_period_determination[$j] < $true_next_payday_obj) {
                $previous_payday_obj_for_period_start = $paydays_for_period_determination[$j];
                break;
            }
        }

        if ($previous_payday_obj_for_period_start) {
            // Pay period is from the day *after* the previous payday up to and including the day *before* the current payday.
            // Or, more commonly, from previous payday (inclusive) to day before current payday (inclusive)
            $pay_period_start_date_obj = $previous_payday_obj_for_period_start;
            $pay_period_end_date_obj = $true_next_payday_obj->modify('-1 day');
        } else {
            // Could not find a previous payday (e.g., very first pay period in the system for this schedule)
            // Default: start of the month of the $true_next_payday_obj up to day before $true_next_payday_obj
            $pay_period_start_date_obj = $true_next_payday_obj->modify('first day of this month');
            $pay_period_end_date_obj = $true_next_payday_obj->modify('-1 day');
        }
    }

    $response["debug_pay_period_start"] = $pay_period_start_date_obj->format('Y-m-d');
    $response["debug_pay_period_end"] = $pay_period_end_date_obj->format('Y-m-d');

    $jobStartDate = new DateTimeImmutable('2025-05-20'); // Assuming this is fixed, make immutable
    $jobStartDate = $jobStartDate->setTime(0,0,0);

    if ($current_date_time->format('Y-m-d') === $true_next_payday_obj->format('Y-m-d')) {
        $response["is_pay_day"] = true;
    }

    if ($response["is_pay_day"]) {
        // On payday, the "estimated_upcoming_pay" is for the *next* period, which we haven't calculated yet.
        // Or, it means the pay for the period just ended has been received.
        // For simplicity, let's assume on payday, the "upcoming" estimate is 0, as it's just been paid / is being paid.
        // The "future_net_worth" will then just be current_net_worth.
        // If we wanted to show the *next* paycheck amount on payday, the logic would need to look further ahead.
        $response["gross_estimated_pay"] = 0.00;
        $response["estimated_federal_tax"] = 0.00;
        $response["estimated_state_tax"] = 0.00;
        $response["estimated_upcoming_pay"] = 0.00;
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