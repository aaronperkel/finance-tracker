<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hours Calendar - Finance App</title>
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
            padding: 0 20px 20px 20px;
            max-width: 1000px; /* Wider for calendar */
            margin: 0 auto; 
        }
        
        h1.page-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        /* Calendar Navigation */
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .calendar-nav button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .calendar-nav button:hover { background-color: #2980b9; }
        #current-month-year {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        /* Calendar Grid */
        #hours-calendar {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden; /* For border radius on table */
        }
        #hours-calendar th, #hours-calendar td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            vertical-align: top;
        }
        #hours-calendar th {
            background-color: #f7f7f7;
            color: #333;
            font-weight: 600;
            padding: 12px 8px;
        }
        #hours-calendar td {
            height: 100px; /* Min height for day cells */
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        #hours-calendar td:hover { background-color: #f0f8ff; }
        #hours-calendar td.not-current-month {
            background-color: #f9f9f9;
            color: #aaa;
            cursor: default;
        }
        #hours-calendar td.current-day {
            background-color: #e0f0ff;
            font-weight: bold;
        }
        .day-number { font-size: 0.9em; display: block; margin-bottom: 5px; text-align: right; }
        .hours-display { 
            font-size: 1.1em; 
            font-weight: bold; 
            color: #27ae60; /* Green for hours */
            display: block;
            margin-top: 5px;
        }
        .hours-display:empty::after {
            content: "-"; /* Show dash if no hours */
            color: #bbb;
            font-weight: normal;
        }

        /* Edit Hours Modal */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5); /* Dim background */
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 25px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .modal-content h3 { margin-bottom: 15px; color: #2c3e50; }
        .modal-content label { display: block; margin-bottom: 5px; font-weight: 600; }
        .modal-content input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .modal-buttons button {
            padding: 10px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }
        .modal-buttons button#save-hours-btn { background-color: #27ae60; color: white; }
        .modal-buttons button#cancel-hours-btn { background-color: #ccc; }
        #modal-feedback { margin-top: 10px; font-weight: bold; }
        #modal-feedback.success { color: #27ae60; }
        #modal-feedback.error { color: #c0392b; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; align-items: flex-start; }
            .navbar a { margin-bottom: 5px; width: 100%; text-align: left; }
            .navbar .app-title { margin-bottom: 10px; }
            .container { padding: 0 10px 10px 10px; }
            #hours-calendar td { height: 80px; padding: 5px; font-size: 0.9em;}
            .day-number { font-size: 0.8em;}
            .hours-display { font-size: 1em;}
            .calendar-nav { flex-direction: column; gap: 10px;}
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <span class="app-title">Finance App</span>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_snapshot.php">Add Snapshot</a>
        <a href="calendar_hours.php" class="active">Hours Calendar</a>
        <a href="admin_settings.php">Settings</a>
    </nav>

    <div class="container">
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

    <script>
        const calendarBody = document.getElementById('hours-calendar').getElementsByTagName('tbody')[0];
        const currentMonthYearEl = document.getElementById('current-month-year');
        const prevMonthBtn = document.getElementById('prev-month-btn');
        const nextMonthBtn = document.getElementById('next-month-btn');

        const modal = document.getElementById('edit-hours-modal');
        const modalSelectedDateEl = document.getElementById('modal-selected-date');
        const modalHoursInput = document.getElementById('modal_hours_worked');
        const saveHoursBtn = document.getElementById('save-hours-btn');
        const cancelHoursBtn = document.getElementById('cancel-hours-btn');
        const modalFeedbackEl = document.getElementById('modal-feedback');
        
        let currentModalDate = null; // To store YYYY-MM-DD for the modal

        let today = new Date();
        let displayMonth = today.getMonth(); // 0-11
        let displayYear = today.getFullYear();

        const monthNames = ["January", "February", "March", "April", "May", "June", 
                            "July", "August", "September", "October", "November", "December"];

        function renderCalendar(month, year) {
            calendarBody.innerHTML = ''; // Clear previous calendar
            currentMonthYearEl.textContent = `${monthNames[month]} ${year}`;

            let firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 (Sun) - 6 (Sat)
            let daysInMonth = new Date(year, month + 1, 0).getDate();

            let date = 1;
            for (let i = 0; i < 6; i++) { // Max 6 weeks
                let weekRow = document.createElement('tr');
                for (let j = 0; j < 7; j++) {
                    let dayCell = document.createElement('td');
                    if (i === 0 && j < firstDayOfMonth) {
                        dayCell.classList.add('not-current-month');
                    } else if (date > daysInMonth) {
                        dayCell.classList.add('not-current-month');
                    } else {
                        const cellDate = new Date(year, month, date);
                        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                        dayCell.dataset.date = dateStr;

                        let dayNumberSpan = document.createElement('span');
                        dayNumberSpan.className = 'day-number';
                        dayNumberSpan.textContent = date;
                        dayCell.appendChild(dayNumberSpan);

                        let hoursDisplaySpan = document.createElement('span');
                        hoursDisplaySpan.className = 'hours-display';
                        hoursDisplaySpan.id = `hours-${dateStr}`; // Unique ID for updating hours
                        dayCell.appendChild(hoursDisplaySpan);
                        
                        if (date === today.getDate() && year === today.getFullYear() && month === today.getMonth()) {
                            dayCell.classList.add('current-day');
                        }
                        
                        dayCell.addEventListener('click', () => openEditModal(dateStr, hoursDisplaySpan.textContent));
                        date++;
                    }
                    weekRow.appendChild(dayCell);
                }
                calendarBody.appendChild(weekRow);
                if (date > daysInMonth && i < 5) break; // Optimization if all days fit in less than 6 weeks
            }
            fetchAndDisplayHours(month + 1, year); // API uses 1-12 for month
        }

        function fetchAndDisplayHours(apiMonth, apiYear) {
            fetch(`api_logged_hours.php?month=${apiMonth}&year=${apiYear}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok for fetching hours.');
                    return response.json();
                })
                .then(data => {
                    // Clear existing hours first (optional, if cells might have stale data)
                    // document.querySelectorAll('.hours-display').forEach(span => span.textContent = '');
                    
                    for (const day in data) {
                        const dateStr = `${apiYear}-${String(apiMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        const hoursSpan = document.getElementById(`hours-${dateStr}`);
                        if (hoursSpan) {
                            hoursSpan.textContent = parseFloat(data[day]).toFixed(2);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching logged hours:', error);
                    // Optionally display an error message to the user on the page
                });
        }

        prevMonthBtn.addEventListener('click', () => {
            displayMonth--;
            if (displayMonth < 0) {
                displayMonth = 11;
                displayYear--;
            }
            renderCalendar(displayMonth, displayYear);
        });

        nextMonthBtn.addEventListener('click', () => {
            displayMonth++;
            if (displayMonth > 11) {
                displayMonth = 0;
                displayYear++;
            }
            renderCalendar(displayMonth, displayYear);
        });

        // Modal Logic
        function openEditModal(dateStr, currentHours) {
            currentModalDate = dateStr;
            modalSelectedDateEl.textContent = dateStr;
            modalHoursInput.value = (currentHours && currentHours !== '-') ? parseFloat(currentHours).toFixed(2) : '';
            modalFeedbackEl.textContent = '';
            modalFeedbackEl.className = '';
            modal.style.display = 'block';
        }

        cancelHoursBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (event) => { // Close modal if clicked outside
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        saveHoursBtn.addEventListener('click', () => {
            const hours = modalHoursInput.value;
            if (hours === '' || parseFloat(hours) < 0 || parseFloat(hours) > 24) {
                modalFeedbackEl.textContent = 'Please enter a valid number of hours (0-24).';
                modalFeedbackEl.className = 'error';
                return;
            }
            if (!currentModalDate) return;

            modalFeedbackEl.textContent = '';
            modalFeedbackEl.className = '';

            const formData = new FormData();
            formData.append('log_date', currentModalDate);
            formData.append('hours_worked', hours);

            fetch('log_hours.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    modalFeedbackEl.textContent = result.success;
                    modalFeedbackEl.className = 'success';
                    // Update calendar display immediately
                    const hoursSpan = document.getElementById(`hours-${currentModalDate}`);
                    if (hoursSpan) {
                        hoursSpan.textContent = parseFloat(hours).toFixed(2) == "0.00" ? "" : parseFloat(hours).toFixed(2);
                    }
                    setTimeout(() => { modal.style.display = 'none'; }, 1000); // Close modal after 1 sec
                } else if (result.error) {
                    modalFeedbackEl.textContent = 'Error: ' + result.error;
                    modalFeedbackEl.className = 'error';
                } else {
                    modalFeedbackEl.textContent = 'Error: Unexpected response.';
                    modalFeedbackEl.className = 'error';
                }
            })
            .catch(error => {
                modalFeedbackEl.textContent = 'Request failed: ' + error.message;
                modalFeedbackEl.className = 'error';
            });
        });

        // Initial Render
        renderCalendar(displayMonth, displayYear);
    </script>
</body>
</html>
