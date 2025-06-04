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
                hoursDisplaySpan.id = `hours-${dateStr}`;
                dayCell.appendChild(hoursDisplaySpan);

                const dayOfWeek = cellDate.getDay();

                if (cellDate < jobStartDate || dayOfWeek === 0 || dayOfWeek === 6) {
                    hoursDisplaySpan.classList.add('placeholder-dash'); // Will show "-" via CSS
                    dayCell.classList.add('disabled-day');
                    // No click listener for modal
                } else {
                    // Default for valid weekday, pre-fetch
                    hoursDisplaySpan.textContent = '7.50';
                    hoursDisplaySpan.classList.add('default-hours');
                    dayCell.addEventListener('click', () => openEditModal(dateStr, hoursDisplaySpan.textContent, hoursDisplaySpan.classList.contains('default-hours')));
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
                    if (data[dayVal]) { // If API returned hours for this day
                        hoursSpan.textContent = parseFloat(data[dayVal]).toFixed(2);
                        hoursSpan.classList.remove('default-hours', 'placeholder-dash');
                    } else {
                        // If it's a valid weekday (already set to 7.50 and 'default-hours' by renderCalendar)
                        // and not in API data, it remains 7.50 with 'default-hours' class.
                        // If it was a weekend or pre-job, it remains '-'
                        if (!hoursSpan.classList.contains('default-hours') && !hoursSpan.classList.contains('placeholder-dash')) {
                             // This case should ideally not be hit if renderCalendar sets initial state correctly.
                             // But as a fallback for a valid weekday not in API:
                             hoursSpan.textContent = '7.50';
                             hoursSpan.classList.add('default-hours');
                        }
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
            if (hoursSpan) {
                const newHours = parseFloat(hours);
                if (newHours === 0) {
                    hoursSpan.textContent = ''; // Show dash via CSS :empty pseudo
                    hoursSpan.classList.add('placeholder-dash');
                    hoursSpan.classList.remove('default-hours');
                } else if (newHours === 7.5 && /* logic to determine if it should be default style */ false) {
                    // This part is tricky: if user explicitly saves 7.5, should it look default or explicit?
                    // For now, any save makes it look explicit.
                    hoursSpan.textContent = newHours.toFixed(2);
                    hoursSpan.classList.remove('default-hours', 'placeholder-dash');
                }
                else {
                    hoursSpan.textContent = newHours.toFixed(2);
                    hoursSpan.classList.remove('default-hours', 'placeholder-dash');
                }
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
