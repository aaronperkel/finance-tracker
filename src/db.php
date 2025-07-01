<?php
require __DIR__ . '/../vendor/autoload.php'; // Adjusted path for vendor

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // Adjusted path for .env
$dotenv->load();
date_default_timezone_set('America/New_York');

// Connection for Finance Database
$databaseNameFinance = $_ENV['DBNAMEFINANCE'];
$dsnFinance = 'mysql:host=webdb.uvm.edu;dbname=' . $databaseNameFinance;
$usernameFinance = $_ENV['DBUSER'];
$passwordFinance = $_ENV['DBPASS'];

$pdo = new PDO($dsnFinance, $usernameFinance, $passwordFinance);

// Connection for Utilities Database
$hostUtilities = $_ENV['DBHOSTUTILITIES'] ?? 'webdb.uvm.edu'; // Default host if not set
$databaseNameUtilities = $_ENV['DBNAMEUTILITIES'];
$dsnUtilities = 'mysql:host=' . $hostUtilities . ';dbname=' . $databaseNameUtilities;
$usernameUtilities = $_ENV['DBUSERUTILITIES'];
$passwordUtilities = $_ENV['DBPASSUTILITIES'];

$pdoUtilities = new PDO($dsnUtilities, $usernameUtilities, $passwordUtilities);

// It's good practice to ensure UTILITIES_USER_PERSON_NAME is loaded,
// though it might not be directly used in db.php itself.
// This can be retrieved directly via $_ENV['UTILITIES_USER_PERSON_NAME'] in other scripts.

/**
 * Calculates the adjusted payday, moving to Friday if it falls on a weekend or Monday.
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
    } elseif ($dayOfWeek === 1) { // Monday
        $date->modify('-3 days'); // Move to Friday
    }
    return $date;
}
?>