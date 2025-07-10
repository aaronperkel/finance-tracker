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

    // ... (Existing code for net_worth_history - unchanged) ...
    $sql_history = "SELECT s.snapshot_date AS date, SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END) - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END) AS networth FROM balances b JOIN accounts a ON a.id = b.account_id JOIN snapshots s ON b.snapshot_id = s.id WHERE s.id IN (SELECT MAX(id) FROM snapshots GROUP BY snapshot_date) GROUP BY s.snapshot_date ORDER BY s.snapshot_date ASC";
    $stmt_history = $pdo->query($sql_history);
    $response["net_worth_history"] = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    foreach ($response["net_worth_history"] as &$item) {
        $item["networth"] = floatval($item["networth"]);
    }

    // ... (Full existing pay calculation logic - populates $response["estimated_upcoming_pay"], $response["is_pay_day"], $response["next_pay_date"], and $true_next_payday_obj - unchanged) ...
    // --- Start: Hardcoded Bi-weekly Pay calculation logic ---
    $response["gross_estimated_pay"] = 0.00; $response["estimated_federal_tax"] = 0.00;
    $response["estimated_state_tax"] = 0.00; $response["estimated_upcoming_pay"] = 0.00;
    $response["is_pay_day"] = false; $true_next_payday_obj = null;

    // settings_keys no longer needs pay schedule types
    $settings_keys = ['pay_rate', 'federal_tax_rate', 'state_tax_rate'];
    $placeholders = rtrim(str_repeat('?,', count($settings_keys)), ',');
    $stmt_settings = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
    $stmt_settings->execute($settings_keys);
    $app_settings = []; while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) { $app_settings[$row['setting_key']] = $row['setting_value']; }

    $pay_rate = isset($app_settings['pay_rate']) ? floatval($app_settings['pay_rate']) : 0;

    if ($pay_rate > 0) {
        $current_date_time = (new DateTimeImmutable())->setTime(0,0,0);

        $fixedReferenceFridayString = '2025-05-30'; // Hardcoded reference Friday
        $refFriday = (new DateTimeImmutable($fixedReferenceFridayString))->setTime(0,0,0);

        // Determine true_next_payday_obj for hardcoded bi-weekly
        $temp_pd = clone $refFriday;
        while ($temp_pd > $current_date_time) { $temp_pd = $temp_pd->modify('-14 days'); }
        while ($temp_pd < $current_date_time) { $temp_pd = $temp_pd->modify('+14 days'); }
        $true_next_payday_obj = clone $temp_pd;

        if (!$true_next_payday_obj) { throw new Exception("Could not determine next payday with fixed reference."); }
        $response["next_pay_date"] = $true_next_payday_obj->format('Y-m-d');

        // Determine Pay Period for bi-weekly (P-18 to P-7)
        $pay_period_start_date_obj = $true_next_payday_obj->modify('-18 days');
        $pay_period_end_date_obj = $true_next_payday_obj->modify('-7 days');

        $response["debug_pay_period_start"] = $pay_period_start_date_obj->format('Y-m-d');
        $response["debug_pay_period_end"] = $pay_period_end_date_obj->format('Y-m-d');

        if ($current_date_time == $true_next_payday_obj) { $response["is_pay_day"] = true; }

        if ($response["is_pay_day"]) {
            // Values remain 0.00 as set initially
        } else {
            $jobStartDate = (new DateTimeImmutable('2025-05-20'))->setTime(0,0,0);
            $stmt_logged = $pdo->prepare("SELECT log_date, hours_worked FROM logged_hours WHERE log_date BETWEEN ? AND ?");
            $stmt_logged->execute([$pay_period_start_date_obj->format('Y-m-d'), $pay_period_end_date_obj->format('Y-m-d')]);
            $logged_hours_list = $stmt_logged->fetchAll(PDO::FETCH_ASSOC);
            $hours_map = []; foreach($logged_hours_list as $l) { $hours_map[$l['log_date']] = (float)$l['hours_worked']; }

            $total_hours = 0.0;
            $loop_date = DateTime::createFromImmutable($pay_period_start_date_obj);
            $end_loop_check = ($pay_period_end_date_obj instanceof DateTimeImmutable) ? $pay_period_end_date_obj : DateTimeImmutable::createFromMutable($pay_period_end_date_obj);

            while($loop_date <= $end_loop_check) {
                $ds = $loop_date->format('Y-m-d'); $dow = (int)$loop_date->format('N');
                if ($loop_date >= $jobStartDate && $dow >= 1 && $dow <= 5) {
                    $total_hours += $hours_map[$ds] ?? 7.5;
                }
                $loop_date->modify('+1 day');
            }
            $response["gross_estimated_pay"] = round($total_hours * $pay_rate, 2);
            $fed_tax_rate = isset($app_settings['federal_tax_rate']) ? floatval($app_settings['federal_tax_rate']) : 0;
            $state_tax_rate = isset($app_settings['state_tax_rate']) ? floatval($app_settings['state_tax_rate']) : 0;
            $fed_tax = $response["gross_estimated_pay"] * $fed_tax_rate;
            $state_tax = $response["gross_estimated_pay"] * $state_tax_rate;
            $response["estimated_federal_tax"] = round($fed_tax, 2);
            $response["estimated_state_tax"] = round($state_tax, 2);
            $response["estimated_upcoming_pay"] = round($response["gross_estimated_pay"] - $fed_tax - $state_tax, 2);
        }
    }
    // --- End: Hardcoded Bi-weekly Pay calculation logic ---

    // Calculate future_net_worth (Raw Snapshot Current Net Worth + Upcoming Pay)
    if ($response["is_pay_day"]) {
        $response["future_net_worth"] = $response["current_net_worth"];
    } else {
        $response["future_net_worth"] = round($response["current_net_worth"] + $response["estimated_upcoming_pay"], 2);
    }

    // Calculate projected_net_worth_after_next_rent (future_net_worth - Rent for the month after paycheck)
    $response["projected_net_worth_after_next_rent"] = round($response["future_net_worth"] - $rentAmount, 2);

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