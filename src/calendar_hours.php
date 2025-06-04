<?php
$page_title = 'Hours Calendar - Finance App';
$active_page = 'calendar';
$page_specific_css = 'calendar.css';
$page_specific_js = 'calendar.js';
include 'templates/header.php';
?>
        <h1 class="page-title">Hours Calendar</h1>

        <div class="calendar-nav">
            <button id="prev-month-btn">&lt; Previous Month</button>
            <span id="current-month-year"></span>
            <button id="next-month-btn">Next Month &gt;</button>
        </div>

        <table id="hours-calendar">
            <thead>
                <tr>
                    <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
            </thead>
            <tbody>
                <!-- Calendar days will be generated here by JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Edit Hours Modal -->
    <div id="edit-hours-modal" class="modal">
        <div class="modal-content">
            <h3>Edit Hours for <span id="modal-selected-date"></span></h3>
            <div>
                <label for="modal_hours_worked">Hours Worked:</label>
                <input type="number" id="modal_hours_worked" step="0.01" min="0" max="24">
            </div>
            <div class="modal-buttons">
                <button id="save-hours-btn">Save Hours</button>
                <button id="cancel-hours-btn">Cancel</button>
            </div>
            <div id="modal-feedback"></div>
        </div>
    </div>
<?php include 'templates/footer.php'; ?>
