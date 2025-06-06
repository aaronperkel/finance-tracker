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

const jobStartDate = new Date('2025-05-20T00:00:00'); // MODIFIED as per user request
// Ensure jobStartDate is set to the beginning of its day for accurate date-only comparisons
jobStartDate.setHours(0, 0, 0, 0);


const monthNames = ["January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"];

function applyHourStyles(dayCell, hoursSpan, hoursValue) {
    // Clear previous hour-styling classes and inline styles
    dayCell.classList.remove('gold-hours', 'red-hours');
    dayCell.style.backgroundColor = '';
    dayCell.style.color = '';
    hoursSpan.style.color = ''; // Reset span color specifically
    hoursSpan.classList.remove('placeholder-dash', 'default-hours'); // default-hours might be legacy

    if (hoursValue === null || typeof hoursValue === 'undefined') { // For disabled/non-work days
        hoursSpan.textContent = ''; // Will show '-' via CSS :empty selector rule
        hoursSpan.classList.add('placeholder-dash');
    } else if (hoursValue > 7.5) {
        dayCell.classList.add('gold-hours');
        hoursSpan.textContent = hoursValue.toFixed(2);
        // hoursSpan color will be handled by td.gold-hours .hours-display CSS
    } else if (hoursValue > 0 && hoursValue <= 7.5) {
        let percentage = hoursValue / 7.5;
        let hue = percentage * 120; // 0 (red-ish end, though we start at >0) to 120 (green)
        dayCell.style.backgroundColor = `hsl(${hue}, 70%, 88%)`; // Light background
        dayCell.style.color = `hsl(${hue}, 90%, 25%)`;    // Darker text for contrast
        hoursSpan.style.color = `hsl(${hue}, 90%, 25%)`; // Ensure span text also has this color
        hoursSpan.textContent = hoursValue.toFixed(2);
        hoursSpan.style.fontWeight = 'bold'; // Make numbers prominent
    } else if (hoursValue === 0) { // Explicitly 0 hours
        dayCell.classList.add('red-hours');
        hoursSpan.textContent = hoursValue.toFixed(2);
        // hoursSpan color will be handled by td.red-hours .hours-display CSS
    } else { // Should not happen if hoursValue is a number or null, but as a fallback
        hoursSpan.textContent = '';
        hoursSpan.classList.add('placeholder-dash');
    }
}

// This function will now handle the actual DOM manipulation for calendar cells
function renderCalendarInternal(month, year, paydays = []) {
    calendarBody.innerHTML = ''; // Clear previous calendar
    currentMonthYearEl.textContent = `${monthNames[month]} ${year}`; // Title is already set by renderCalendar, but good for standalone call if needed

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
                hoursDisplaySpan.id = `hours-${dateStr}`;
                dayCell.appendChild(hoursDisplaySpan);

                // Check if this date is a payday
                // 'paydays' is the parameter of renderCalendarInternal, defaults to []
                // dateStr is 'YYYY-MM-DD', api_get_paydays.php is expected to return this format.
                if (paydays.includes(dateStr)) {
                    dayCell.classList.add('is-payday');
                }

                const dayOfWeek = cellDate.getDay();

                if (cellDate < jobStartDate || dayOfWeek === 0 || dayOfWeek === 6) {
                    applyHourStyles(dayCell, hoursDisplaySpan, null); // Style for disabled/non-work days
                    dayCell.classList.add('disabled-day');
                    // No click listener for modal
                } else {
                    applyHourStyles(dayCell, hoursDisplaySpan, 7.50); // Default styling for workdays
                    dayCell.addEventListener('click', () => openEditModal(dateStr, hoursDisplaySpan.textContent, false)); // isDefault is now less relevant here
                }

                if (date === today.getDate() && year === today.getFullYear() && month === today.getMonth()) {
                    dayCell.classList.add('current-day');
                }
                date++;
            }
            weekRow.appendChild(dayCell);
        }
        calendarBody.appendChild(weekRow);
        if (date > daysInMonth && i < 5) break; // Optimization if all days fit in less than 6 weeks
    }
    fetchAndDisplayHours(month + 1, year); // API uses 1-12 for month
}

// New renderCalendar function that fetches paydays first
function renderCalendar(month, year) {
    currentMonthYearEl.textContent = `${monthNames[month]} ${year}`; // Set title immediately

    fetch(`api_get_paydays.php?year=${year}&month=${month + 1}`) // month + 1 for API (1-12)
        .then(response => {
            if (!response.ok) {
                // Try to parse error body, then throw
                return response.json().then(errData => {
                    throw new Error(`HTTP error ${response.status}: ${errData.error || 'Failed to fetch paydays'}`);
                }).catch(() => { // If error body parsing fails
                    throw new Error(`HTTP error ${response.status}: Failed to fetch paydays and parse error response.`);
                });
            }
            return response.json();
        })
        .then(paydaysData => {
            // Ensure paydaysData is an array, even if API returns null or something else on success.
            const validPaydays = Array.isArray(paydaysData) ? paydaysData : [];
            renderCalendarInternal(month, year, validPaydays);
        })
        .catch(error => {
            console.error('Error fetching paydays:', error.message);
            // Fallback: render calendar without payday info if fetch fails
            calendarBody.innerHTML = ''; // Clear calendar body
            renderCalendarInternal(month, year, []); // Pass empty array for paydays
            // Optionally, display a non-intrusive message about paydays failing to load
            // For example, by adding a small note below the calendar or near the title.
            // For now, console log is the primary feedback for this failure.
        });
}

function fetchAndDisplayHours(apiMonth, apiYear) {
    fetch(`api_logged_hours.php?month=${apiMonth}&year=${apiYear}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok for fetching hours.');
            return response.json();
        })
        .then(data => {
            // For each day in the current month being displayed
            for (let dayVal = 1; dayVal <= new Date(apiYear, apiMonth, 0).getDate(); dayVal++) {
                const dateStr = `${apiYear}-${String(apiMonth).padStart(2, '0')}-${String(dayVal).padStart(2, '0')}`;
                const hoursSpan = document.getElementById(`hours-${dateStr}`);
                const cell = hoursSpan ? hoursSpan.closest('td') : null;

                if (hoursSpan && cell && !cell.classList.contains('disabled-day')) {
                    if (data[dayVal] !== undefined && data[dayVal] !== null) { // If API returned hours for this day
                        applyHourStyles(cell, hoursSpan, parseFloat(data[dayVal]));
                    } else {
                        // If data[dayVal] is not present, it means it's a default 7.5 day.
                        // renderCalendarInternal has already applied the style for 7.5 hours.
                        // So, no explicit action is needed here to re-apply default styling.
                        // The existing default green for 7.50 will remain.
                    }
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
function openEditModal(dateStr, currentHoursText, isDefault) {
    const cell = document.querySelector(`td[data-date="${dateStr}"]`);
    if (cell && cell.classList.contains('disabled-day')) {
        return; // Do not open modal for disabled days
    }

    currentModalDate = dateStr;
    modalSelectedDateEl.textContent = dateStr;

    if (isDefault && currentHoursText === '7.50') {
         modalHoursInput.value = '7.50'; // Pre-fill with 7.5 if it was a default
    } else if (currentHoursText && currentHoursText !== '-') {
        modalHoursInput.value = parseFloat(currentHoursText).toFixed(2);
    } else {
        modalHoursInput.value = ''; // Empty if '-' or no hours
    }

    modalFeedbackEl.textContent = '';
    modalFeedbackEl.className = '';
    modal.style.display = 'block';
    modalHoursInput.focus();
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
            const hoursSpan = document.getElementById(`hours-${currentModalDate}`);
            const cell = hoursSpan ? hoursSpan.closest('td') : null;
            if (cell && hoursSpan) { // Ensure both cell and span exist
                const newHours = parseFloat(hours);
                applyHourStyles(cell, hoursSpan, newHours);
            }
            setTimeout(() => { modal.style.display = 'none'; }, 1000);
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
