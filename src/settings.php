<?php
require_once 'db.php'; // 1. Include db.php

header('Content-Type: application/json'); // 4. Set Content-Type header

$allowed_keys = ['pay_rate', 'pay_day_1', 'pay_day_2', 'federal_tax_rate', 'state_tax_rate'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 2. Handle GET requests
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode($settings);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred while fetching settings.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3. Handle POST requests
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload.']);
        exit;
    }

    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'No settings provided in payload.']);
        exit;
    }

    $errors = [];
    $valid_settings = [];

    foreach ($input as $key => $value) {
        if (!in_array($key, $allowed_keys)) {
            $errors[] = "Invalid setting key: " . htmlspecialchars($key);
            continue;
        }

        // Validate values
        switch ($key) {
            case 'pay_rate':
                if (!is_numeric($value) || $value < 0) {
                    $errors[] = "Invalid value for pay_rate. Must be a non-negative number.";
                } else {
                    $valid_settings[$key] = $value;
                }
                break;
            case 'pay_day_1':
            case 'pay_day_2':
                if (!is_numeric($value) || intval($value) != $value || $value < 1 || $value > 31) {
                    $errors[] = "Invalid value for $key. Must be an integer between 1 and 31.";
                } else {
                    $valid_settings[$key] = intval($value);
                }
                break;
            case 'federal_tax_rate':
            case 'state_tax_rate':
                if (!is_numeric($value) || $value < 0 || $value > 1) {
                    $errors[] = "Invalid value for $key. Must be a number between 0.00 and 1.00 (e.g., 0.15 for 15%).";
                } else {
                    // Ensure it's stored with a consistent precision, e.g., 4 decimal places
                    $valid_settings[$key] = number_format((float)$value, 4, '.', ''); 
                }
                break;
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
        exit;
    }

    if (empty($valid_settings)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid settings provided for update after validation.']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);

        foreach ($valid_settings as $key => $value) {
            if (!$stmt->execute([$key, $value])) {
                // This specific execution failed
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Database error occurred while updating setting: ' . htmlspecialchars($key)]);
                exit;
            }
        }
        $pdo->commit();
        echo json_encode(['success' => 'Settings updated successfully.']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
    }

} else {
    // 5. Error Handling for other methods
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only GET and POST requests are accepted.']);
}
?>
