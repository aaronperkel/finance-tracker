<?php
// include 'db.php'; // db.php is included by settings.php, so not strictly needed here if this page only interacts via API.
// However, if there were direct DB ops on this page, it would be.
$page_title = 'Application Settings - Finance App';
$active_page = 'settings'; // Matches the nav link in header.php
$page_specific_css = 'admin_settings.css';
$page_specific_js = 'admin_settings.js';
include 'templates/header.php';
?>
<h1 class="page-title">Application Settings</h1>

<div class="settings-form-container">
    <form id="update-settings-form">
        <div>
            <label for="pay_rate">Pay Rate (per hour):</label>
            <input type="number" id="pay_rate" name="pay_rate" step="0.01" min="0" required>
        </div>
        <div>
            <label for="federal_tax_rate">Federal Tax Rate (%):</label>
            <input type="number" id="federal_tax_rate" name="federal_tax_rate" step="0.01" min="0" max="100" required>
        </div>
        <div>
            <label for="state_tax_rate">State Tax Rate (%):</label>
            <input type="number" id="state_tax_rate" name="state_tax_rate" step="0.01" min="0" max="100" required>
        </div>
        <!-- Pay schedule configuration removed as it's now hardcoded -->
        <button type="submit">Save Settings</button>
    </form>
    <div id="settings-feedback" class="feedback-message"></div>
</div>
<?php include 'templates/footer.php'; ?>