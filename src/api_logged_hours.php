<?php
require_once 'db.php'; // 1. Include db.php
header('Content-Type: application/json'); // 2. Set Content-Type header

// 3. Input Parameters (with defaults)
$currentDate = new DateTime();
$month = $_GET['month'] ?? $currentDate->format('n');
$year = $_GET['year'] ?? $currentDate->format('Y');

// 6. Validation
$month = filter_var($month, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 12]]);
$year = filter_var($year, FILTER_VALIDATE_INT, ["options" => ["min_range" => 2000, "max_range" => 2100]]); // Adjust range as needed

if ($month === false || $year === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid month or year provided. Month must be 1-12, year e.g., 2000-2100.']);
    exit;
}

$response_data = [];

try {
    // Determine the number of days in the given month and year
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    // Create the first and last day of the month for the SQL query
    $firstDayOfMonth = sprintf("%04d-%02d-01", $year, $month);
    $lastDayOfMonth = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);

    // 4. Fetch Logged Hours
    $stmt = $pdo->prepare("
        SELECT log_date, hours_worked 
        FROM logged_hours 
        WHERE log_date >= :first_day AND log_date <= :last_day
        ORDER BY log_date ASC
    ");
    
    $stmt->bindParam(':first_day', $firstDayOfMonth, PDO::PARAM_STR);
    $stmt->bindParam(':last_day', $lastDayOfMonth, PDO::PARAM_STR);
    
    $stmt->execute();
    $logged_hours_for_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Format Response
    if ($logged_hours_for_month) {
        foreach ($logged_hours_for_month as $row) {
            $dateObj = new DateTime($row['log_date']);
            $dayOfMonth = $dateObj->format('j'); // Day of the month without leading zeros
            $response_data[$dayOfMonth] = number_format((float)$row['hours_worked'], 2, '.', '');
        }
    }
    
    echo json_encode($response_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
} catch (Exception $e) { // Catch other potential errors (e.g., DateTime issues)
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>
