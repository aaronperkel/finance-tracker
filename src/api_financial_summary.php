<?php
require_once 'db.php'; // 1. Include db.php
header('Content-Type: application/json'); // 2. Set Content-Type header

$response = [
    "current_net_worth" => 0,
    "total_cash_on_hand" => 0,
    "receivables_balance" => 0,
    "estimated_upcoming_pay" => 0,
    "next_pay_date" => null,
    "future_net_worth" => 0,
    "net_worth_history" => [],
    "debug_pay_period_start" => null,
    "debug_pay_period_end" => null,
];

try {
    // 3. Fetch Data and Perform Calculations

    // Get latest snapshot ID
    $latest_snapshot_id = $pdo->query("SELECT id FROM snapshots ORDER BY snapshot_date DESC LIMIT 1")->fetchColumn();

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
    }

    // Net Worth History
    $stmt = $pdo->query("
        SELECT
          s.snapshot_date AS date,
          SUM(CASE WHEN a.type='Asset' THEN b.balance ELSE 0 END)
            - SUM(CASE WHEN a.type='Liability' THEN b.balance ELSE 0 END)
          AS networth
        FROM snapshots s
        JOIN balances b ON b.snapshot_id = s.id
        JOIN accounts a  ON a.id = b.account_id
        GROUP BY s.snapshot_date
        ORDER BY s.snapshot_date
    ");
    $response["net_worth_history"] = $stmt->fetchAll(PDO::FETCH_ASSOC);
     foreach ($response["net_worth_history"] as &$item) {
        $item["networth"] = floatval($item["networth"]);
    }


    // Estimated Upcoming Pay and Next Pay Date
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('pay_rate', 'pay_day_1', 'pay_day_2')");
    $app_settings = [];
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $app_settings[$row['setting_key']] = $row['setting_value'];
    }

    if (isset($app_settings['pay_rate'], $app_settings['pay_day_1'], $app_settings['pay_day_2'])) {
        $pay_rate = floatval($app_settings['pay_rate']);
        $pay_day_1 = intval($app_settings['pay_day_1']);
        $pay_day_2 = intval($app_settings['pay_day_2']);

        $current_date = new DateTime();
        $current_day = intval($current_date->format('j'));
        $current_month = intval($current_date->format('n'));
        $current_year = intval($current_date->format('Y'));

        $next_pay_date_obj = new DateTime();
        $prev_pay_date_obj = new DateTime();

        // Adjust pay_day_2 if it's intended to be end of month
        // For simplicity, if pay_day_2 > 28, consider it last day of month.
        // A more robust solution would check actual last day of month.
        $actual_pay_day_2 = $pay_day_2;
        if ($pay_day_2 >= 28) {
             $temp_date_for_last_day = clone $current_date;
             $actual_pay_day_2 = intval($temp_date_for_last_day->format('t')); // last day of current month
        }


        if ($current_day < $pay_day_1) {
            $next_pay_date_obj->setDate($current_year, $current_month, $pay_day_1);
            // Previous pay date was pay_day_2 of last month
            $prev_pay_date_obj->setDate($current_year, $current_month, $actual_pay_day_2);
            $prev_pay_date_obj->modify('-1 month');
            $prev_actual_pay_day_2 = $pay_day_2;
             if ($pay_day_2 >=28) { // if original pay_day_2 was end-of-month style
                 $prev_actual_pay_day_2 = intval($prev_pay_date_obj->format('t'));
             }
            $prev_pay_date_obj->setDate($prev_pay_date_obj->format('Y'), $prev_pay_date_obj->format('n'), $prev_actual_pay_day_2);


        } elseif ($current_day < $actual_pay_day_2) {
            $next_pay_date_obj->setDate($current_year, $current_month, $actual_pay_day_2);
            // Previous pay date was pay_day_1 of current month
            $prev_pay_date_obj->setDate($current_year, $current_month, $pay_day_1);
        } else {
            // Next pay date is pay_day_1 of next month
            $next_pay_date_obj->setDate($current_year, $current_month, $pay_day_1);
            $next_pay_date_obj->modify('+1 month');
            // Previous pay date was pay_day_2 of current month
            $prev_pay_date_obj->setDate($current_year, $current_month, $actual_pay_day_2);
        }
        
        // Ensure pay_day_1 and pay_day_2 are correctly handled for specific month lengths
        // This logic for next_pay_date_obj needs to be careful if pay_day_1 or pay_day_2 > days in month
        // For example, if pay_day_1 = 30 and month is Feb.
        // The setDate might roll over. A safer way is to check.
        $next_month_test = clone $next_pay_date_obj;
        if ($next_pay_date_obj->format('j') != ($next_pay_date_obj->format('n') == $current_month ? ($current_day < $pay_day_1 ? $pay_day_1 : $actual_pay_day_2) : $pay_day_1) ) {
             // Day rolled over, so set to last day of target month
             if($next_pay_date_obj->format('n') != $current_month){ // next month case
                $next_pay_date_obj->setDate($next_pay_date_obj->format('Y'), $next_pay_date_obj->format('n'), $pay_day_1);
                if($next_pay_date_obj->format('j') != $pay_day_1) $next_pay_date_obj->setDate($next_pay_date_obj->format('Y'), $next_pay_date_obj->format('n'), 1)->modify('last day of this month');
             } else { // current month case
                $target_day = ($current_day < $pay_day_1 ? $pay_day_1 : $actual_pay_day_2);
                 $next_pay_date_obj->setDate($current_year, $current_month, $target_day);
                 if($next_pay_date_obj->format('j') != $target_day) $next_pay_date_obj->setDate($current_year, $current_month, 1)->modify('last day of this month');
             }
        }
        
        $prev_month_test_day = $prev_pay_date_obj->format('j');
        $target_prev_day = ($current_day < $pay_day_1 ? ($pay_day_2 >= 28 ? intval($prev_pay_date_obj->format('t')) : $pay_day_2) : $pay_day_1);
        if ($prev_month_test_day != $target_prev_day){
             $prev_pay_date_obj->setDate($prev_pay_date_obj->format('Y'), $prev_pay_date_obj->format('n'), $target_prev_day);
             if($prev_pay_date_obj->format('j') != $target_prev_day) $prev_pay_date_obj->setDate($prev_pay_date_obj->format('Y'), $prev_pay_date_obj->format('n'), 1)->modify('last day of this month');
        }


        $response["next_pay_date"] = $next_pay_date_obj->format('Y-m-d');
        $response["debug_pay_period_start"] = $prev_pay_date_obj->format('Y-m-d');
        $response["debug_pay_period_end"] = $next_pay_date_obj->format('Y-m-d'); // This is exclusive for query

        // Sum hours_worked
        $hours_stmt = $pdo->prepare("
            SELECT SUM(hours_worked) 
            FROM logged_hours 
            WHERE log_date >= ? AND log_date < ?
        ");
        $hours_stmt->execute([$response["debug_pay_period_start"], $response["debug_pay_period_end"]]);
        $total_hours = floatval($hours_stmt->fetchColumn() ?: 0);
        $response["estimated_upcoming_pay"] = round($total_hours * $pay_rate, 2);
    }

    // Future Net Worth
    $response["future_net_worth"] = $response["current_net_worth"] + $response["estimated_upcoming_pay"];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'General error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>
