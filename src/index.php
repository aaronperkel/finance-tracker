<!DOCTYPE html>
<html>

<head>
    <title>Financial Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/3.5.0/luxon.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-luxon/1.3.1/chartjs-adapter-luxon.umd.min.js" defer></script>
    <style>
        /* Basic Reset & Body Styling */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #eef1f5;
            /* Lighter gray background */
            color: #333;
            line-height: 1.6;
        }

        /* Navigation Bar */
        .navbar {
            background-color: #2c3e50;
            /* Dark blue */
            color: #fff;
            padding: 1rem 2rem;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .navbar a {
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin-right: 10px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .navbar a:hover,
        .navbar a.active {
            background-color: #3498db;
            /* Brighter blue for hover/active */
        }

        .navbar .app-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: auto;
            /* Pushes other links to the right if needed, or use justify-content */
        }

        /* Main Content Container */
        .container {
            padding: 0 20px 20px 20px;
            /* Added bottom padding */
            max-width: 1400px;
            /* Constrain overall width for desktop */
            margin-left: auto;
            margin-right: auto;
        }

        h1.page-title {
            /* Specific styling for the main H1 */
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        h2 {
            color: #34495e;
            /* Slightly lighter blue for section headers */
            border-bottom: 2px solid #bdc3c7;
            /* Light gray border */
            padding-bottom: 10px;
            margin-top: 10px;
            /* Ensure h2 has margin-top if it's the first child of a section */
            margin-bottom: 15px;
        }

        /* Layout for sections */
        .main-layout {
            /*display: flex; /* Can be re-enabled if a two-column layout is desired for summary/other */
            /*flex-wrap: wrap; */
            gap: 20px;
            margin-bottom: 20px;
        }

        .main-layout>div,
        #chart-container {
            /* Apply common styling to section containers */
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            /* Ensure spacing when stacked */
        }

        #financial-summary-container {
            /* flex: 1; /* Only if .main-layout is flex and has other items */
            min-width: 300px;
        }

        /* Individual Sections Styling */
        #financial-summary div {
            margin-bottom: 12px;
        }

        #financial-summary span {
            font-weight: bold;
            color: #2980b9;
        }

        .currency::before {
            content: "$";
        }

        .debug-info {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-top: 15px;
        }

        #payday-message-container {
            background-color: #e6ffed;
            /* Light minty green */
            color: #004d00;
            /* Darker green text */
            padding: 12px 15px;
            border: 1px solid #b2dfdb;
            /* Subtle teal/green border */
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
            /* Slightly larger font */
            margin-bottom: 12px;
            /* Consistent with other div spacing */
            /* display: none; /* Managed by JS */
            margin: auto;
            width: 75%;
        }

        /* Form elements */
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            /* Slightly bolder labels */
            color: #555;
        }

        input[type="date"],
        input[type="number"] {
            width: 100%;
            /* Full width of parent */
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            /* Important for width calculation */
            transition: border-color 0.3s ease;
        }

        input[type="date"]:focus,
        input[type="number"]:focus {
            border-color: #3498db;
            /* Highlight focus */
            outline: none;
        }

        button[type="submit"] {
            background-color: #27ae60;
            /* Green */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%;
            /* Make button full width */
        }

        button[type="submit"]:hover {
            background-color: #229954;
            /* Darker green */
        }

        /* Feedback Messages */
        #log-hours-feedback,
        #summary-error {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        #log-hours-feedback.success,
        #summary-error.success {
            color: #1d6f42;
            /* Darker green for text */
            background-color: #d4edda;
            /* Light green background */
            border: 1px solid #c3e6cb;
        }

        #log-hours-feedback.error,
        #summary-error.error {
            color: #721c24;
            /* Darker red for text */
            background-color: #f8d7da;
            /* Light red background */
            border: 1px solid #f5c6cb;
        }

        /* Chart Container */
        #chart-container {
            position: relative;
            min-height: 500px;
            max-height: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #nwChart {
            /* Canvas element itself should not have style for max-height if container controls it */
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body nav.navbar { /* Increased specificity */
                flex-direction: column;
                align-items: flex-start;
            }
            body nav.navbar a { /* Increased specificity */
                margin-bottom: 5px;
                width: 100%;
                text-align: left;
            }
            body nav.navbar .app-title { /* Increased specificity */
                margin-bottom: 10px;
            }

            /* Styles for other elements on mobile if needed, e.g., chart container */
            #chart-container {
                aspect-ratio: 1 / 1;
                /* More square on smaller screens */
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="index.php" class="active">Dashboard</a>
        <a href="add_snapshot.php">Add Snapshot</a>
        <a href="calendar_hours.php">Hours Calendar</a>
        <a href="manage_accounts.php">Manage Accounts</a>
        <a href="admin_settings.php">Settings</a>
    </nav>

    <div class="container">
        <h1 class="page-title">Financial Dashboard</h1>
        <div id="summary-error"></div>

        <div class="main-layout">
            <h2>Summary</h2>
            <div id="financial-summary-container">
                <div id="financial-summary">
                    <div id="payday-message-container" style="display: none;">Pay Day!</div>
                    <div>Current Net Worth: <span id="current-net-worth" class="currency">N/A</span></div>
                    <div>Total Cash on Hand: <span id="total-cash" class="currency">N/A</span></div>
                    <div>Receivables: <span id="receivables-balance" class="currency">N/A</span></div>
                    <div><strong>Total Owed:</strong> <span id="total-owed" class="currency">0.00</span></div>

                    <div id="next-paycheck-line">
                        Estimated Next Paycheck: <span id="next-paycheck-amount" class="currency">N/A</span>
                        on Next Pay Date: <span id="next-paycheck-date">N/A</span>
                    </div>

                    <div id="future-net-worth-line">Future Net Worth (after next paycheck): <span id="future-net-worth"
                            class="currency">N/A</span></div>
                    <!-- <div class="debug-info">
                        Debug Pay Period: <span id="debug-pay-start">N/A</span> to <span id="debug-pay-end">N/A</span>
                    </div> -->
                </div>
            </div>
            <!-- HOUR LOGGING CONTAINER REMOVED, financial-summary-container is now direct child of .container if .main-layout is not flex -->
        </div>

        <!-- Chart container is now a direct child of .container, styled by .main-layout > div selector -->
        <h2>Net Worth Over Time</h2>
        <div id="chart-container">
            <canvas id="nwChart"></canvas>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

                        const nextPaycheckLine = document.getElementById('next-paycheck-line');
                        const paydayMessageContainer = document.getElementById('payday-message-container');
                        const futureNetWorthLine = document.getElementById('future-net-worth-line');

                        if (data.is_pay_day) {
                            nextPaycheckLine.style.display = 'none';
                            paydayMessageContainer.style.display = 'block'; // Show "Pay Day!"
                            futureNetWorthLine.style.display = 'none'; // Hide future net worth on payday
                            // Values for estimated_upcoming_pay will be 0 from API
                            document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                            document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'Today!';
                        } else {
                            nextPaycheckLine.style.display = 'block'; // Or 'inline' or '' depending on original display
                            paydayMessageContainer.style.display = 'none'; // Hide "Pay Day!"
                            futureNetWorthLine.style.display = 'block'; // Show future net worth
                            document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                            document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'N/A';
                        }

                        document.getElementById('future-net-worth').textContent = formatCurrency(data.future_net_worth);

                        // document.getElementById('debug-pay-start').textContent = data.debug_pay_period_start || 'N/A';
                        // document.getElementById('debug-pay-end').textContent = data.debug_pay_period_end || 'N/A';

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
                                scales: {
                                    x: {
                                        type: 'time',
                                        time: {
                                            unit: 'day',
                                            tooltipFormat: 'MMM d, yyyy' // Example: Jan 1, 2023
                                        },
                                        title: {
                                            display: true,
                                            text: 'Date'
                                        }
                                    },
                                    y: {
                                        beginAtZero: false,
                                        title: {
                                            display: true,
                                            text: 'Net Worth'
                                        }
                                    }
                                },
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

            // Hour logging form JavaScript REMOVED

            // Initial data load
            fetchFinancialData();
        });
    </script>
</body>

</html>