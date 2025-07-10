<?php
require_once 'db.php'; // 1. Include db.php

header('Content-Type: application/json'); // 4. Set Content-Type header

$allowed_keys = [
    'pay_rate',
    'federal_tax_rate',
    'state_tax_rate',
    'pay_schedule_type',
    'pay_schedule_detail1',
    'pay_schedule_detail2'
];
$pay_schedule_types = ['bi-weekly', 'semi-monthly', 'monthly'];

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
            case 'federal_tax_rate':
            case 'state_tax_rate':
                if (!is_numeric($value) || $value < 0 || $value > 1) {
                    $errors[] = "Invalid value for $key. Must be a number between 0.00 and 1.00 (e.g., 0.15 for 15%).";
                } else {
                    // Ensure it's stored with a consistent precision, e.g., 4 decimal places
                    $valid_settings[$key] = number_format((float)$value, 4, '.', '');
                }
                break;
            case 'pay_schedule_type':
                if (!in_array($value, $pay_schedule_types)) {
                    $errors[] = "Invalid value for pay_schedule_type. Must be one of: " . implode(', ', $pay_schedule_types) . ".";
                } else {
                    $valid_settings[$key] = $value;
                }
                break;
            case 'pay_schedule_detail1':
            case 'pay_schedule_detail2':
                // Basic validation: allow empty string (for detail2 when not used), or validate based on type.
                // More specific validation happens below, based on pay_schedule_type.
                // Here, just ensure it's a string that's not overly long.
                if (strlen((string)$value) > 20) { // Date 'YYYY-MM-DD' is 10 chars, day numbers are 1-2 chars.
                    $errors[] = "Invalid length for $key.";
                } else {
                    $valid_settings[$key] = (string)$value; // Store as string
                }
                break;
        }
    }

    // Cross-field validation for pay schedule settings
    if (isset($valid_settings['pay_schedule_type'])) {
        $schedule_type = $valid_settings['pay_schedule_type'];
        $detail1 = $valid_settings['pay_schedule_detail1'] ?? null;
        $detail2 = $valid_settings['pay_schedule_detail2'] ?? null;

        if ($schedule_type === 'bi-weekly') {
            if (empty($detail1)) {
                $errors[] = "pay_schedule_detail1 (Reference Friday) is required for bi-weekly schedule.";
            } else {
                $d = DateTime::createFromFormat('Y-m-d', $detail1);
                if (!($d && $d->format('Y-m-d') === $detail1)) {
                    $errors[] = "Invalid date format for pay_schedule_detail1 (Reference Friday). Must be YYYY-MM-DD.";
                }
                // Optionally, check if it's actually a Friday
                // elseif ($d->format('N') != 5) {
                //     $errors[] = "pay_schedule_detail1 (Reference Friday) must be a Friday.";
                // }
            }
            // Ensure detail2 is empty or null for bi-weekly, as it's not used.
            // $valid_settings['pay_schedule_detail2'] = ''; // Or null, depending on DB preference
        } elseif ($schedule_type === 'semi-monthly') {
            if ($detail1 === null || $detail1 === '' || $detail2 === null || $detail2 === '') {
                $errors[] = "pay_schedule_detail1 and pay_schedule_detail2 are required for semi-monthly schedule.";
            } else {
                $d1_val = filter_var($detail1, FILTER_VALIDATE_INT);
                $d2_val = filter_var($detail2, FILTER_VALIDATE_INT); // Allows '0'

                if ($d1_val === false || $d1_val < 1 || $d1_val > 31) {
                    $errors[] = "pay_schedule_detail1 (First Payday) must be an integer between 1 and 31.";
                }
                if ($d2_val === false || $d2_val < 0 || $d2_val > 31) { // 0 is for last day of month
                    $errors[] = "pay_schedule_detail2 (Second Payday) must be an integer between 0 and 31.";
                }
                if ($d1_val !== false && $d2_val !== false && $d1_val === $d2_val && $d1_val !== 0) {
                     $errors[] = "For semi-monthly, pay_schedule_detail1 and pay_schedule_detail2 cannot be the same day (unless it's 0, which is for 'last day' and less likely to be duplicated).";
                }
            }
        } elseif ($schedule_type === 'monthly') {
            if ($detail1 === null || $detail1 === '') {
                $errors[] = "pay_schedule_detail1 (Payday) is required for monthly schedule.";
            } else {
                $d1_val = filter_var($detail1, FILTER_VALIDATE_INT);
                 if ($d1_val === false || $d1_val < 0 || $d1_val > 31) { // 0 is for last day of month
                    $errors[] = "pay_schedule_detail1 (Payday) must be an integer between 0 and 31.";
                }
            }
            // Ensure detail2 is empty or null for monthly
            // $valid_settings['pay_schedule_detail2'] = '';
        }
        // Ensure pay_schedule_detail2 is set to empty string if not applicable, to avoid saving "null" string from JS
        if ($schedule_type === 'bi-weekly' || $schedule_type === 'monthly') {
            $valid_settings['pay_schedule_detail2'] = '';
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
