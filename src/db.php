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
?>