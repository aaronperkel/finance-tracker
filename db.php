<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
date_default_timezone_set('America/New_York');

$databaseNameFinance = $_ENV['DBNAMEFINANCE'];
$dsnFinance = 'mysql:host=webdb.uvm.edu;dbname=' . $databaseNameFinance;
$usernameFinance = $_ENV['DBUSER'];
$passwordFinance = $_ENV['DBPASS'];

$pdo = new PDO($dsnFinance, $usernameFinance, $passwordFinance);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Get array of inactive account IDs from app_settings.
 */
function getInactiveAccountIds(PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'inactive_accounts'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val === false || $val === '') return [];
    return array_map('intval', array_filter(explode(',', $val), fn($v) => $v !== ''));
}

/**
 * Save array of inactive account IDs to app_settings.
 */
function setInactiveAccountIds(PDO $pdo, array $ids): void {
    $val = implode(',', array_unique(array_map('intval', $ids)));
    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'inactive_accounts'");
    $stmt->execute([$val]);
}
?>