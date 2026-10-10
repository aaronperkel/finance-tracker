<?php
$page_title = 'Dashboard';
$active_page = 'dashboard';
$page_specific_css = 'dashboard.css';
$page_specific_js = 'dashboard.js';
include 'db.php';
include 'templates/header.php';
?>
<div id="summary-error" class="feedback-message"></div>

<!-- Net Worth Hero -->
<div class="hero-card">
    <div class="hero-label">Net Worth</div>
    <div id="net-worth" class="hero-value">--</div>
    <div class="hero-date">as of <span id="snapshot-date">--</span></div>
    <div class="hero-breakdown">
        <div class="hero-stat">
            <span class="hero-stat-label">Assets</span>
            <span id="total-assets" class="hero-stat-value assets">$0.00</span>
        </div>
        <div class="hero-divider"></div>
        <div class="hero-stat">
            <span class="hero-stat-label">Liabilities</span>
            <span id="total-liabilities" class="hero-stat-value liabilities">$0.00</span>
        </div>
    </div>
</div>

<!-- Account Balances -->
<div class="card">
    <h2>Account Balances</h2>
    <div id="balances-list"></div>
</div>

<!-- Chart -->
<div class="card">
    <div class="chart-header">
        <h2>History</h2>
        <div class="chart-controls">
            <button id="btn-net-worth" class="chart-btn active">Net Worth</button>
            <button id="btn-accounts" class="chart-btn">By Account</button>
        </div>
    </div>
    <div class="time-range-controls">
        <button class="range-btn" data-range="3m">3M</button>
        <button class="range-btn" data-range="6m">6M</button>
        <button class="range-btn" data-range="1y">1Y</button>
        <button class="range-btn active" data-range="all">All</button>
    </div>
    <div id="chart-container">
        <canvas id="mainChart"></canvas>
    </div>
</div>
</div>
<?php include 'templates/footer.php'; ?>