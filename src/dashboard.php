<!DOCTYPE html>
<html>
<head>
    <title>Financial Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f7f6; color: #333; }
        h1, h2 { color: #2c3e50; }
        #financial-summary, #hour-logging-section, #chart-container {
            background-color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #financial-summary div, #hour-logging-section div { margin-bottom: 10px; }
        #financial-summary span, #hour-logging-section span { font-weight: bold; color: #2980b9; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="date"], input[type="number"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #27ae60;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button[type="submit"]:hover { background-color: #229954; }
        #log-hours-feedback, #summary-error { margin-top: 10px; font-weight: bold; }
        #log-hours-feedback.success { color: #27ae60; }
        #log-hours-feedback.error, #summary-error.error { color: #c0392b; }
        .currency::before { content: "$"; }
    </style>
</head>
<body>
    <h1>Financial Dashboard</h1>

    <div id="summary-error"></div>

    <div id="financial-summary">
        <h2>Summary</h2>
        <div>Current Net Worth: <span id="current-net-worth" class="currency">N/A</span></div>
        <div>Total Cash on Hand: <span id="total-cash" class="currency">N/A</span></div>
        <div>Receivables: <span id="receivables-balance" class="currency">N/A</span></div>
        <div>Estimated Next Paycheck: <span id="next-paycheck-amount" class="currency">N/A</span> on Next Pay Date: <span id="next-paycheck-date">N/A</span></div>
        <div>Future Net Worth (after next paycheck): <span id="future-net-worth" class="currency">N/A</span></div>
        <div style="font-size: 0.8em; color: #7f8c8d;">
            Debug Pay Period: <span id="debug-pay-start">N/A</span> to <span id="debug-pay-end">N/A</span>
        </div>
    </div>

    <div id="hour-logging-section">
        <h2>Log Worked Hours</h2>
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

    <div id="chart-container">
        <h2>Net Worth Over Time</h2>
        <canvas id="nwChart" width="800" height="400"></canvas>
    </div>

    <script>
        const summaryErrorDiv = document.getElementById('summary-error');
        let nwChartInstance = null; // To store the chart instance

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
                        throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(`API Error: ${data.error}`);
                    }
                    document.getElementById('current-net-worth').textContent = formatCurrency(data.current_net_worth);
                    document.getElementById('total-cash').textContent = formatCurrency(data.total_cash_on_hand);
                    document.getElementById('receivables-balance').textContent = formatCurrency(data.receivables_balance);
                    document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                    document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'N/A';
                    document.getElementById('future-net-worth').textContent = formatCurrency(data.future_net_worth);
                    
                    document.getElementById('debug-pay-start').textContent = data.debug_pay_period_start || 'N/A';
                    document.getElementById('debug-pay-end').textContent = data.debug_pay_period_end || 'N/A';

                    const labels = data.net_worth_history.map(r => r.date);
                    const vals = data.net_worth_history.map(r => parseFloat(r.networth));

                    if (nwChartInstance) {
                        nwChartInstance.destroy(); // Destroy existing chart before creating a new one
                    }
                    nwChartInstance = new Chart(
                        document.getElementById('nwChart'),
                        {
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
                        }
                    );
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
            // Basic client-side validation
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
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    feedbackDiv.textContent = result.success;
                    feedbackDiv.className = 'success';
                    this.reset(); // Reset form fields
                    fetchFinancialData(); // Refresh financial summary
                } else if (result.error) {
                    feedbackDiv.textContent = 'Error: ' + result.error;
                    feedbackDiv.className = 'error';
                }
            })
            .catch(error => {
                console.error('Log hours request failed:', error);
                feedbackDiv.textContent = 'Request failed: ' + error;
                feedbackDiv.className = 'error';
            });
        });

        // Initial data load
        fetchFinancialData();
    </script>
</body>
</html>