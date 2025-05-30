<!DOCTYPE html>
<html>
<head>
    <title>Application Settings - Finance App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Basic Reset & Body Styling - Consistent with dashboard.php */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #eef1f5;
            color: #333;
            line-height: 1.6;
        }

        /* Navigation Bar - Consistent with dashboard.php */
        .navbar {
            background-color: #2c3e50;
            color: #fff;
            padding: 1rem 2rem;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin-right: 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .navbar a:hover, .navbar a.active {
            background-color: #3498db;
        }
        .navbar .app-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: auto;
        }

        /* Main Content Container */
        .container {
            padding: 0 20px;
            max-width: 800px; /* Max width for settings page */
            margin: 0 auto; /* Center container */
        }

        h1.page-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
        }

        /* Settings Form Container */
        .settings-form-container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* Form elements - Consistent with dashboard.php */
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="number"], input[type="text"] /* Added text for future use */ {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
            font-size: 1rem;
        }
        input[type="number"]:focus, input[type="text"]:focus {
            border-color: #3498db;
            outline: none;
        }
        button[type="submit"] {
            background-color: #27ae60; /* Green */
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
            width: 100%;
        }
        button[type="submit"]:hover { background-color: #229954; }

        /* Feedback Messages - Consistent with dashboard.php */
        #settings-feedback {
            margin-top: 20px;
            padding: 12px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        #settings-feedback.success {
            color: #1d6f42;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        #settings-feedback.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        #settings-feedback:empty { /* Hide if empty */
            display: none;
        }

        /* Responsive adjustments - Consistent with dashboard.php */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .navbar a {
                margin-bottom: 5px;
                width: 100%;
                text-align: left;
            }
            .navbar .app-title { margin-bottom: 10px; }
            .container { padding: 0 15px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_snapshot.php">Add Snapshot</a>
        <a href="calendar_hours.php">Hours Calendar</a>
        <a href="admin_settings.php" class="active">Settings</a>
    </nav>

    <div class="container">
        <h1 class="page-title">Application Settings</h1>

        <div class="settings-form-container">
            <form id="update-settings-form">
                <div>
                    <label for="pay_rate">Pay Rate (per hour):</label>
                    <input type="number" id="pay_rate" name="pay_rate" step="0.01" min="0" required>
                </div>
                <div>
                    <label for="pay_day_1">First Pay Day of Month (1-31):</label>
                    <input type="number" id="pay_day_1" name="pay_day_1" min="1" max="31" step="1" required>
                </div>
                <div>
                    <label for="pay_day_2">Second Pay Day of Month (1-31):</label>
                    <input type="number" id="pay_day_2" name="pay_day_2" min="1" max="31" step="1" required>
                </div>
                <div>
                    <label for="federal_tax_rate">Federal Tax Rate (%):</label>
                    <input type="number" id="federal_tax_rate" name="federal_tax_rate" step="0.01" min="0" max="100" required>
                </div>
                <div>
                    <label for="state_tax_rate">State Tax Rate (%):</label>
                    <input type="number" id="state_tax_rate" name="state_tax_rate" step="0.01" min="0" max="100" required>
                </div>
                <button type="submit">Save Settings</button>
            </form>
            <div id="settings-feedback"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('update-settings-form');
            const feedbackDiv = document.getElementById('settings-feedback');
            const payRateInput = document.getElementById('pay_rate');
            const payDay1Input = document.getElementById('pay_day_1');
            const payDay2Input = document.getElementById('pay_day_2');
            const federalTaxRateInput = document.getElementById('federal_tax_rate');
            const stateTaxRateInput = document.getElementById('state_tax_rate');

            // Fetch current settings on page load
            fetch('settings.php')
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                           throw new Error(`Failed to fetch settings: ${response.status} ${errData.error || response.statusText}`);
                        }).catch(() => new Error(`Failed to fetch settings: ${response.status} ${response.statusText}`));
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) { // Handle application-level errors from API
                         throw new Error(`API Error: ${data.error}`);
                    }
                    payRateInput.value = data.pay_rate || '';
                    payDay1Input.value = data.pay_day_1 || '';
                    payDay2Input.value = data.pay_day_2 || '';
                    federalTaxRateInput.value = data.federal_tax_rate ? (parseFloat(data.federal_tax_rate) * 100).toFixed(2) : '0.00';
                    stateTaxRateInput.value = data.state_tax_rate ? (parseFloat(data.state_tax_rate) * 100).toFixed(2) : '0.00';
                })
                .catch(error => {
                    feedbackDiv.textContent = 'Error loading settings: ' + error.message;
                    feedbackDiv.className = 'error'; // Use just 'error' or 'success' for class
                });

            // Handle form submission
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                feedbackDiv.textContent = '';
                feedbackDiv.className = ''; // Clear class

                const settingsData = {
                    pay_rate: payRateInput.value,
                    pay_day_1: payDay1Input.value,
                    pay_day_2: payDay2Input.value,
                    federal_tax_rate: (parseFloat(federalTaxRateInput.value) / 100).toFixed(4), // Store as decimal
                    state_tax_rate: (parseFloat(stateTaxRateInput.value) / 100).toFixed(4)   // Store as decimal
                };

                // Basic client-side validation (though server is primary)
                if (parseFloat(settingsData.pay_rate) < 0) {
                    feedbackDiv.textContent = 'Error: Pay rate cannot be negative.';
                    feedbackDiv.className = 'error';
                    return;
                }
                const pd1 = parseInt(settingsData.pay_day_1);
                const pd2 = parseInt(settingsData.pay_day_2);
                if (pd1 < 1 || pd1 > 31 || pd2 < 1 || pd2 > 31) {
                    feedbackDiv.textContent = 'Error: Pay days must be between 1 and 31.';
                    feedbackDiv.className = 'error';
                    return;
                }
                const fedTax = parseFloat(federalTaxRateInput.value);
                const stateTax = parseFloat(stateTaxRateInput.value);
                if (fedTax < 0 || fedTax > 100 || stateTax < 0 || stateTax > 100) {
                    feedbackDiv.textContent = 'Error: Tax rates must be between 0 and 100%.';
                    feedbackDiv.className = 'error';
                    return;
                }
                // You could add more validation here, e.g. pd2 >= pd1,
                // but settings.php API should also handle this.

                fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settingsData)
                })
                .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, body: data })))
                .then(result => {
                    if (result.ok && result.body.success) {
                        feedbackDiv.textContent = result.body.success || 'Settings updated successfully!';
                        feedbackDiv.className = 'success';
                    } else {
                        let errorMessage = 'Error: ' + (result.body.error || `Failed with status ${result.status}`);
                        if (result.body.details && Array.isArray(result.body.details)) {
                             errorMessage += ' Details: ' + result.body.details.join(', ');
                        } else if (typeof result.body.details === 'string') {
                             errorMessage += ' Details: ' + result.body.details;
                        }
                        feedbackDiv.textContent = errorMessage;
                        feedbackDiv.className = 'error';
                    }
                })
                .catch(error => {
                    feedbackDiv.textContent = 'Request failed: ' + error.message;
                    feedbackDiv.className = 'error';
                });
            });
        });
    </script>
</body>
</html>
