<?php
$page_title = 'Financial Dashboard';
$active_page = 'dashboard';
$page_specific_css = 'dashboard.css';
$page_specific_js = 'dashboard.js';
include 'db.php'; // Ensure db connection is included if needed by page logic, or move to header if universally needed. For now, keep here.
include 'templates/header.php';
?>
        <h1 class="page-title">Financial Dashboard</h1>
        <div id="summary-error" class="feedback-message"></div>

        <div class="main-layout">
            <h2>Summary</h2>
            <div id="financial-summary-container">
                <div id="financial-summary">
                    <div id="payday-message-container" style="display: none;">Pay Day!</div>
                    <div>Current Net Worth: <span id="current-net-worth" class="currency">N/A</span></div>
                    <!-- New line to be added below -->
                    <div>Effective Current Net Worth (after current month expenses): <span id="effective-current-net-worth" class="currency">N/A</span></div>
                    <!-- End of new line -->
                    <div>Total Cash on Hand: <span id="total-cash" class="currency">N/A</span></div>
                    <div>Receivables: <span id="receivables-balance" class="currency">N/A</span></div>
                    <div><strong>Total Owed:</strong> <span id="total-owed" class="currency">0.00</span></div>

                    <div id="next-paycheck-line">
                        Estimated Next Paycheck: <span id="next-paycheck-amount" class="currency">N/A</span>
                        on Next Pay Date: <span id="next-paycheck-date">N/A</span>
                    </div>

                    <div id="future-net-worth-line">Future Net Worth (after next paycheck): <span id="future-net-worth"
                            class="currency">N/A</span></div>
                    <!-- New lines to be added below -->
                    <div id="projected-next-rent-line">Projected NW (after Next Rent): <span id="projected-nw-next-rent" class="currency">N/A</span></div>
                    <div id="projected-next-utils-line">Projected NW (after Next Utilities): <span id="projected-nw-next-utils" class="currency">N/A</span></div>
                    <!-- End of new lines -->
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
<?php include 'templates/footer.php'; ?>