<!DOCTYPE html>
<html>
<head>
    <title>Financial Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        /* Basic Reset & Body Styling */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #eef1f5; /* Lighter gray background */
            color: #333; 
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background-color: #2c3e50; /* Dark blue */
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
            background-color: #3498db; /* Brighter blue for hover/active */
        }
        .navbar .app-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: auto; /* Pushes other links to the right if needed, or use justify-content */
        }

        /* Main Content Container */
        .container {
            padding: 0 20px; /* Add some horizontal padding to the main content area */
        }
        
        h1.page-title { /* Specific styling for the main H1 */
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        h2 { 
            color: #34495e; /* Slightly lighter blue for section headers */
            border-bottom: 2px solid #bdc3c7; /* Light gray border */
            padding-bottom: 10px;
            margin-top: 10px; /* Ensure h2 has margin-top if it's the first child of a section */
            margin-bottom: 15px;
        }

        /* Layout for sections - Flexbox for side-by-side summary and logging */
        .main-layout {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 20px; /* Space between flex items */
            margin-bottom: 20px;
        }
        .main-layout > div { /* Direct children of main-layout */
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        #financial-summary-container { flex: 2; min-width: 300px; } /* Takes more space */
        #hour-logging-container { flex: 1; min-width: 280px; }    /* Takes less space */
        
        /* Individual Sections Styling */
        #financial-summary div, #hour-logging-section form div { margin-bottom: 12px; }
        #financial-summary span, #hour-logging-section span { font-weight: bold; color: #2980b9; } /* Blue for data values */
        .currency::before { content: "$"; } /* Keep the dollar sign */
        .debug-info { font-size: 0.85em; color: #7f8c8d; margin-top: 15px; } /* Styling for debug info */

        /* Form elements */
        label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; /* Slightly bolder labels */
            color: #555;
        }
        input[type="date"], input[type="number"] {
            width: 100%; /* Full width of parent */
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Important for width calculation */
            transition: border-color 0.3s ease;
        }
        input[type="date"]:focus, input[type="number"]:focus {
            border-color: #3498db; /* Highlight focus */
            outline: none;
        }
        button[type="submit"] {
            background-color: #27ae60; /* Green */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%; /* Make button full width */
        }
        button[type="submit"]:hover { background-color: #229954; /* Darker green */ }

        /* Feedback Messages */
        #log-hours-feedback, #summary-error { 
            margin-top: 10px; 
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        #log-hours-feedback.success, #summary-error.success { 
            color: #1d6f42; /* Darker green for text */
            background-color: #d4edda; /* Light green background */
            border: 1px solid #c3e6cb; 
        }
        #log-hours-feedback.error, #summary-error.error { 
            color: #721c24; /* Darker red for text */
            background-color: #f8d7da; /* Light red background */
            border: 1px solid #f5c6cb;
        }
        
        /* Chart Container */
        #chart-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: 450px; /* Ensure enough height for the chart */
        }
        #nwChart { max-height: 400px; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-layout {
                flex-direction: column; /* Stack summary and logging on smaller screens */
            }
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
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="add_snapshot.php">Add Snapshot</a>
        <a href="calendar_hours.php">Hours Calendar</a>
        <a href="admin_settings.php">Settings</a>
    </nav>

    <div class="container">
        <h1 class="page-title">Financial Dashboard</h1>
        <div id="summary-error"></div>

        <div class="main-layout">
            <div id="financial-summary-container">
                <h2>Summary</h2>
                <div id="financial-summary">
                    <div>Current Net Worth: <span id="current-net-worth" class="currency">N/A</span></div>
                    <div>Total Cash on Hand: <span id="total-cash" class="currency">N/A</span></div>
                    <div>Receivables: <span id="receivables-balance" class="currency">N/A</span></div>
                    <div><strong>Total Owed:</strong> <span id="total-owed" class="currency">0.00</span></div>
                    <div>Estimated Next Paycheck: <span id="next-paycheck-amount" class="currency">N/A</span> on Next Pay Date: <span id="next-paycheck-date">N/A</span></div>
                    <div>Future Net Worth (after next paycheck): <span id="future-net-worth" class="currency">N/A</span></div>
                    <div class="debug-info">
                        Debug Pay Period: <span id="debug-pay-start">N/A</span> to <span id="debug-pay-end">N/A</span>
                    </div>
                </div>
            </div>

            <div id="hour-logging-container">
                <h2>Log Worked Hours</h2>
                <div id="hour-logging-section">
                    <form id="log-hours-form">
                        <div>
                            <label for="log_date">Date:</label>
                            <input type="date" id="log_date" name="log_date" required>
                        </div>
                        <div>
                            <label for="hours_worked">Hours Worked:</label>
                            <input type="number" id="hours_worked" name="hours_worked" step="0.01" min="0.01" max="24" required>
                        </div>
                        <button type="submit">Log Hours</button>
                    </form>
                    <div id="log-hours-feedback"></div>
                </div>
            </div>
        </div>

        <div id="chart-container">
            <h2>Net Worth Over Time</h2>
            <canvas id="nwChart"></canvas> <!-- Removed fixed width/height, rely on CSS/options -->
        </div>
    </div>

    <script>
        const summaryErrorDiv = document.getElementById('summary-error');
        let nwChartInstance = null; 

        function formatCurrency(value) {
            const num = parseFloat(value);
            return isNaN(num) ? '0.00' : num.toFixed(2);
        }

        function fetchFinancialData() {
            summaryErrorDiv.textContent = '';
            summaryErrorDiv.className = '';

            fetch('api_financial_summary.php')
                .then(response => {
                    if (!response.ok) {
                        // Try to get error message from response body if available
                        return response.json().then(errData => {
                            throw new Error(`HTTP error ${response.status}: ${errData.error || response.statusText}`);
                        }).catch(() => {
                             throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) { // Handle application-level errors from API
                        throw new Error(`API Error: ${data.error}`);
                    }
                    document.getElementById('current-net-worth').textContent = formatCurrency(data.current_net_worth);
                    document.getElementById('total-cash').textContent = formatCurrency(data.total_cash_on_hand);
                    document.getElementById('receivables-balance').textContent = formatCurrency(data.receivables_balance);
                    document.getElementById('total-owed').textContent = formatCurrency(data.total_liabilities !== undefined ? data.total_liabilities : 0);
                    document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                    document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'N/A';
                    document.getElementById('future-net-worth').textContent = formatCurrency(data.future_net_worth);
                    
                    document.getElementById('debug-pay-start').textContent = data.debug_pay_period_start || 'N/A';
                    document.getElementById('debug-pay-end').textContent = data.debug_pay_period_end || 'N/A';

                    const labels = data.net_worth_history.map(r => r.date);
                    const vals = data.net_worth_history.map(r => parseFloat(r.networth));

                    if (nwChartInstance) {
                        nwChartInstance.destroy(); 
                    }
                    const chartCtx = document.getElementById('nwChart').getContext('2d');
                    nwChartInstance = new Chart(chartCtx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Net Worth',
                                data: vals,
                                fill: false,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }]
                        },
                        options: { 
                            scales: { y: { beginAtZero: false } },
                            responsive: true,
                            maintainAspectRatio: false 
                        }
                    });
                })
                .catch(error => {
                    console.error('Fetch/API error:', error);
                    summaryErrorDiv.textContent = 'Failed to load financial summary: ' + error.message;
                    summaryErrorDiv.className = 'error';
                });
        }

        document.getElementById('log-hours-form').addEventListener('submit', function(event) {
            event.preventDefault();
            const feedbackDiv = document.getElementById('log-hours-feedback');
            feedbackDiv.textContent = '';
            feedbackDiv.className = '';

            const formData = new FormData(this);
            const logDate = formData.get('log_date');
            const hoursWorked = formData.get('hours_worked');

            if (!logDate || !hoursWorked) {
                feedbackDiv.textContent = 'Error: Both date and hours worked are required.';
                feedbackDiv.className = 'error';
                return;
            }
            if (parseFloat(hoursWorked) <= 0 || parseFloat(hoursWorked) > 24) {
                feedbackDiv.textContent = 'Error: Hours worked must be between 0.01 and 24.';
                feedbackDiv.className = 'error';
                return;
            }

            fetch('log_hours.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Assuming log_hours.php always returns JSON
            .then(result => {
                if (result.success) {
                    feedbackDiv.textContent = result.success;
                    feedbackDiv.className = 'success';
                    this.reset(); 
                    fetchFinancialData(); 
                } else if (result.error) {
                    feedbackDiv.textContent = 'Error: ' + result.error;
                    feedbackDiv.className = 'error';
                } else {
                    // Fallback for unexpected response structure
                    feedbackDiv.textContent = 'Error: Unexpected response from server.';
                    feedbackDiv.className = 'error';
                }
            })
            .catch(error => {
                console.error('Log hours request failed:', error);
                feedbackDiv.textContent = 'Request failed: ' + error.message; // Show network error message
                feedbackDiv.className = 'error';
            });
        });

        // Initial data load
        fetchFinancialData();
    </script>
</body>
</html>