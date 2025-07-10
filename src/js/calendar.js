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

const jobStartDate = new Date('2025-05-20T00:00:00');
jobStartDate.setHours(0, 0, 0, 0);

function getPayPeriod(paymentDateStr) {
    const paymentDate = new Date(paymentDateStr + 'T00:00:00');
    paymentDate.setHours(0, 0, 0, 0);
    const payEndDate = new Date(paymentDate);
    payEndDate.setDate(paymentDate.getDate() - 5);
    payEndDate.setHours(0, 0, 0, 0);
    const payStartDate = new Date(payEndDate);
    payStartDate.setDate(payEndDate.getDate() - 13);
    payStartDate.setHours(0, 0, 0, 0);
    return { startDate: payStartDate, endDate: payEndDate };
}

const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];

function applyHourStyles(dayCell, hoursSpan, hoursValue) {
    dayCell.classList.remove('gold-hours', 'red-hours');
    dayCell.style.backgroundColor = '';
    dayCell.style.color = '';
    hoursSpan.style.color = '';
    hoursSpan.style.fontWeight = 'normal';
    hoursSpan.classList.remove('placeholder-dash', 'default-hours');

    if (hoursValue === null || typeof hoursValue === 'undefined') {
        hoursSpan.textContent = '';
        hoursSpan.classList.add('placeholder-dash');
    } else if (hoursValue === 0) { // Explicitly 0 hours, show as red
        dayCell.classList.add('red-hours');
        hoursSpan.textContent = hoursValue.toFixed(2);
    } else if (hoursValue > 0) { // Covers all cases > 0
        // For hours >= 7.5, percentage will be 1.0 (or more, capped by Math.min), resulting in full green (hue 120)
        // For hours < 7.5, it will be a gradient from red-ish (low positive values) to green.
        let percentage = Math.min(hoursValue / 7.5, 1.0); // Cap at 1.0 for 7.5+ hours
        // Adjust hue slightly so that very small positive hours are not pure red (hue 0)
        // Let's say hue starts from 10 (orangey-red) for >0 up to 120 (green)
        // If percentage is 0 (e.g. hoursValue is extremely small like 0.01), hue should not be 0 if 0 is red.
        // Let's map percentage (0 to 1) to hue (e.g., 0 to 120 for red to green)
        // A very small positive hour (e.g. 0.1) will have a small percentage.
        // If hue = 0 is red, and hue = 120 is green:
        let hue = percentage * 120;

        dayCell.style.backgroundColor = `hsl(${hue}, 70%, 88%)`;
        dayCell.style.color = `hsl(${hue}, 90%, 25%)`;
        hoursSpan.style.color = `hsl(${hue}, 90%, 25%)`;
        hoursSpan.textContent = hoursValue.toFixed(2);
        hoursSpan.style.fontWeight = 'bold';
    } else { // Fallback for any other case (e.g. negative, though validation should prevent)
        hoursSpan.textContent = '';
        hoursSpan.classList.add('placeholder-dash');
    }
}

function fetchAndDisplayExpenses(displayYear, displayMonth) {
    fetch('./api_get_upcoming_expenses.php') // Using absolute path
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    throw new Error(`HTTP error ${response.status}: ${errData.error || errData.error_message || 'Failed to fetch expenses'}`);
                }).catch(() => {
                    throw new Error(`HTTP error ${response.status}: Failed to fetch expenses and parse error response.`);
                });
            }
            return response.json();
        })
        .then(expenses => {
            if (!Array.isArray(expenses)) return;
            expenses.forEach(expense => {
                if (expense._debug_utility_api || expense.error_message || !expense || typeof expense.date === 'undefined' || typeof expense.emoji === 'undefined') return;
                const expenseDate = new Date(expense.date + 'T00:00:00');
                if (expenseDate.getFullYear() === displayYear && expenseDate.getMonth() === displayMonth) {
                    const dayCell = calendarBody.querySelector(`td[data-date="${expense.date}"]`);
                    if (dayCell && !dayCell.classList.contains('not-current-month')) {
                        let emojiContainer = dayCell.querySelector('.expense-emoji-container');
                        if (!emojiContainer) {
                            emojiContainer = document.createElement('div');
                            emojiContainer.className = 'expense-emoji-container';
                            emojiContainer.style.cssText = 'display:flex; justify-content:center; align-items:center; margin-top:3px;';
                            dayCell.appendChild(emojiContainer);
                        }
                        let individualEmojiSpan = document.createElement('span');
                        individualEmojiSpan.className = 'expense-emoji-item';
                        individualEmojiSpan.textContent = expense.emoji;
                        let tooltipText = `${expense.type}: $${parseFloat(expense.amount).toFixed(2)}`;
                        if (expense.status) tooltipText += ` (${expense.status})`;
                        individualEmojiSpan.title = tooltipText;
                        individualEmojiSpan.style.margin = '0 1px';
                        emojiContainer.appendChild(individualEmojiSpan);
                    }
                }
            });
        })
        .catch(error => console.error('[Calendar Expenses] Overall error:', error.message, error));
}

function renderCalendarInternal(month, year, paydays = []) {
    calendarBody.innerHTML = '';
    currentMonthYearEl.textContent = `${monthNames[month]} ${year}`;
    const payPeriods = paydays.map(pdStr => getPayPeriod(pdStr));
    let firstDayOfMonth = new Date(year, month, 1).getDay();
    let daysInMonth = new Date(year, month + 1, 0).getDate();
    let date = 1;
    for (let i = 0; i < 6; i++) {
        let weekRow = document.createElement('tr');
        for (let j = 0; j < 7; j++) {
            let dayCell = document.createElement('td');
            if (i === 0 && j < firstDayOfMonth || date > daysInMonth) {
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

                if (paydays.includes(dateStr)) dayCell.classList.add('is-payday');

                const currentCellDateNormalized = new Date(cellDate);
                currentCellDateNormalized.setHours(0, 0, 0, 0);
                const dayOfWeek = currentCellDateNormalized.getDay();
                let isDisabledDay = currentCellDateNormalized < jobStartDate || dayOfWeek === 0 || dayOfWeek === 6;

                if (isDisabledDay) {
                    applyHourStyles(dayCell, hoursDisplaySpan, null);
                    dayCell.classList.add('disabled-day');
                } else {
                    applyHourStyles(dayCell, hoursDisplaySpan, 7.50);
                    dayCell.addEventListener('click', () => openEditModal(dateStr, hoursDisplaySpan.textContent, false));
                }
                if (!isDisabledDay) {
                    for (const period of payPeriods) {
                        if (currentCellDateNormalized >= period.startDate && currentCellDateNormalized <= period.endDate) {
                            dayCell.classList.add('in-pay-period');
                            break;
                        }
                    }
                }
                if (date === today.getDate() && year === today.getFullYear() && month === today.getMonth()) {
                    dayCell.classList.add('current-day');
                }
                date++;
            }
            weekRow.appendChild(dayCell);
        }
        calendarBody.appendChild(weekRow);
        if (date > daysInMonth && i < 5) break;
    }
    fetchAndDisplayHours(month + 1, year);
    fetchAndDisplayExpenses(year, month);
}

function renderCalendar(month, year) {
    currentMonthYearEl.textContent = `${monthNames[month]} ${year}`;
    fetch(`./api_get_paydays.php?year=${year}&month=${month + 1}`) // Absolute path
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => { throw new Error(`HTTP ${response.status}: ${errData.error || 'Failed to fetch paydays'}`); })
                    .catch(() => { throw new Error(`HTTP ${response.status}: Failed to fetch paydays (cannot parse error response).`); });
            }
            return response.json();
        })
        .then(paydaysData => renderCalendarInternal(month, year, Array.isArray(paydaysData) ? paydaysData : []))
        .catch(error => {
            console.error('Error fetching paydays:', error.message);
            renderCalendarInternal(month, year, []);
        });
}

function fetchAndDisplayHours(apiMonth, apiYear) {
    fetch(`./api_logged_hours.php?month=${apiMonth}&year=${apiYear}`) // Absolute path
        .then(response => { if (!response.ok) throw new Error('Network error fetching hours.'); return response.json(); })
        .then(data => {
            for (let dayVal = 1; dayVal <= new Date(apiYear, apiMonth, 0).getDate(); dayVal++) {
                const dateStr = `${apiYear}-${String(apiMonth).padStart(2, '0')}-${String(dayVal).padStart(2, '0')}`;
                const hoursSpan = document.getElementById(`hours-${dateStr}`);
                const cell = hoursSpan ? hoursSpan.closest('td') : null;
                if (hoursSpan && cell && !cell.classList.contains('disabled-day')) {
                    if (data[dayVal] !== undefined && data[dayVal] !== null) {
                        applyHourStyles(cell, hoursSpan, parseFloat(data[dayVal]));
                    }
                }
            }
        })
        .catch(error => console.error('Error fetching logged hours:', error));
}

prevMonthBtn.addEventListener('click', () => {
    displayMonth--; if (displayMonth < 0) { displayMonth = 11; displayYear--; }
    renderCalendar(displayMonth, displayYear);
});

nextMonthBtn.addEventListener('click', () => {
    displayMonth++; if (displayMonth > 11) { displayMonth = 0; displayYear++; }
    renderCalendar(displayMonth, displayYear);
});

function openEditModal(dateStr, currentHoursText, isDefault) {
    const cell = document.querySelector(`td[data-date="${dateStr}"]`);
    if (cell && cell.classList.contains('disabled-day')) return;
    currentModalDate = dateStr;
    modalSelectedDateEl.textContent = dateStr;
    if (isDefault && currentHoursText === '7.50') {
        modalHoursInput.value = '7.50';
    } else if (currentHoursText && currentHoursText !== '-') {
        modalHoursInput.value = parseFloat(currentHoursText).toFixed(2);
    } else {
        modalHoursInput.value = '';
    }
    modalFeedbackEl.textContent = '';
    modalFeedbackEl.className = '';
    setupRentButton(modal.querySelector('.modal-content') || modal, dateStr);
    modal.style.display = 'block';
    modalHoursInput.focus();
}

function getRentMonthString(dateStr) {
    const dateParts = dateStr.split('-');
    return `${dateParts[0]}-${dateParts[1]}-01`;
}

function getTodaysDateString() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

async function setupRentButton(modalContentElement, clickedDateStr) {
    const dateObj = new Date(clickedDateStr + 'T00:00:00');
    const dayOfMonth = dateObj.getDate();
    const rentButtonContainerId = 'rent-button-container';
    let rentButtonContainer = modalContentElement.querySelector(`#${rentButtonContainerId}`);
    if (rentButtonContainer) rentButtonContainer.remove();

    if (dayOfMonth === 1) {
        rentButtonContainer = document.createElement('div');
        rentButtonContainer.id = rentButtonContainerId;
        rentButtonContainer.style.marginTop = '10px';
        rentButtonContainer.style.marginBottom = '10px';
        const modalButtonsDiv = modalContentElement.querySelector('.modal-buttons');
        if (modalButtonsDiv && modalButtonsDiv.parentNode) {
            modalButtonsDiv.parentNode.insertBefore(rentButtonContainer, modalButtonsDiv);
        } else {
            modalContentElement.appendChild(rentButtonContainer);
        }
        rentButtonContainer.innerHTML = '<button id="rentToggleButton" class="button">Loading Rent Status...</button>';
        const rentToggleButton = rentButtonContainer.querySelector('#rentToggleButton');
        const rentMonthForAPI = getRentMonthString(clickedDateStr);
        const fetchUrl = `./get_rent_status.php?rent_month=${rentMonthForAPI}`; // Corrected path

        // REMOVED ALL DEBUG ALERTS FROM THIS VERSION
        // console.log(`[CalendarJS] Rent Button: Fetching URL: ${fetchUrl}`); // Use console.log for debugging

        try {
            rentToggleButton.disabled = true;
            const response = await fetch(fetchUrl);
            rentToggleButton.disabled = false;

            if (!response.ok) {
                let errorText = `HTTP error ${response.status}`;
                try {
                    const errorDataText = await response.text();
                    console.error("Raw error response from get_rent_status (not ok):", errorDataText);
                    try {
                        const errJson = JSON.parse(errorDataText);
                        if (errJson && errJson.error) errorText += ` - ${errJson.error}`;
                        else errorText += ` - ${errorDataText.substring(0, 100)}`;
                    } catch (e_json) { errorText += ` - ${errorDataText.substring(0, 100)}`; }
                } catch (e_text) { /* ignore */ }
                throw new Error(errorText);
            }

            const responseText = await response.text();
            try {
                const data = JSON.parse(responseText);
                if (data.is_paid) {
                    rentToggleButton.textContent = `Mark Rent Unpaid (Paid ${data.details.amount} on ${data.details.paid_date})`;
                    rentToggleButton.dataset.rentStatus = 'paid';
                    rentToggleButton.dataset.rentAmount = data.details.amount;
                } else {
                    rentToggleButton.textContent = 'Mark Current Month Rent Paid';
                    rentToggleButton.dataset.rentStatus = 'unpaid';
                }
            } catch (e_parse) {
                console.error("JSON Parse Error in get_rent_status response:", e_parse, "Raw text:", responseText);
                throw new Error(`JSON Parse error: ${e_parse.message}. Response was: ${responseText.substring(0, 200)}`);
            }

            rentToggleButton.onclick = async () => {
                rentToggleButton.disabled = true;
                if (modalFeedbackEl) { // Ensure modalFeedbackEl exists
                    modalFeedbackEl.textContent = '';
                    modalFeedbackEl.className = '';
                }

                const currentStatus = rentToggleButton.dataset.rentStatus;
                const monthToActOn = getRentMonthString(currentModalDate);
                const RENT_AMOUNT_DEFAULT = "1100.99";

                if (currentStatus === 'unpaid') {
                    const rentAmountToPay = parseFloat(prompt("Enter rent amount:", rentToggleButton.dataset.rentAmount || RENT_AMOUNT_DEFAULT) || RENT_AMOUNT_DEFAULT);
                    if (isNaN(rentAmountToPay) || rentAmountToPay <= 0) {
                        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Invalid amount entered.'; else alert('Invalid amount entered.');
                        if (modalFeedbackEl) modalFeedbackEl.className = 'error';
                        rentToggleButton.disabled = false;
                        return;
                    }
                    const paidDate = getTodaysDateString();
                    try {
                        const markPaidResponse = await fetch('./mark_rent_paid.php', { // Absolute path
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ rent_month: monthToActOn, paid_date: paidDate, amount: rentAmountToPay })
                        });
                        const markPaidData = await markPaidResponse.json();
                        if (!markPaidResponse.ok || markPaidData.error) throw new Error(markPaidData.error || `HTTP error ${markPaidResponse.status}`);

                        rentToggleButton.textContent = `Mark Rent Unpaid (Paid ${rentAmountToPay.toFixed(2)} on ${paidDate})`;
                        rentToggleButton.dataset.rentStatus = 'paid';
                        rentToggleButton.dataset.rentAmount = rentAmountToPay;
                        if (window.refreshFinancialSummary) window.refreshFinancialSummary();
                        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Rent marked as paid.';
                        if (modalFeedbackEl) modalFeedbackEl.className = 'success';
                    } catch (err) {
                        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Error marking rent paid: ' + err.message; else alert('Error marking rent paid: ' + err.message);
                        if (modalFeedbackEl) modalFeedbackEl.className = 'error';
                    }
                } else { // currentStatus === 'paid'
                    try {
                        const deleteResponse = await fetch('./delete_rent_payment.php', { // Absolute path
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ rent_month: monthToActOn })
                        });
                        const deleteData = await deleteResponse.json();
                        if (!deleteResponse.ok || deleteData.error) throw new Error(deleteData.error || `HTTP error ${deleteResponse.status}`);

                        rentToggleButton.textContent = 'Mark Current Month Rent Paid';
                        rentToggleButton.dataset.rentStatus = 'unpaid';
                        if (window.refreshFinancialSummary) window.refreshFinancialSummary();
                        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Rent marked as unpaid.';
                        if (modalFeedbackEl) modalFeedbackEl.className = 'success';
                    } catch (err) {
                        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Error marking rent unpaid: ' + err.message; else alert('Error marking rent unpaid: ' + err.message);
                        if (modalFeedbackEl) modalFeedbackEl.className = 'error';
                    }
                }
                rentToggleButton.disabled = false;
                setTimeout(() => {
                    if (modalFeedbackEl && modalFeedbackEl.className !== 'error') modalFeedbackEl.textContent = '';
                }, 3000);
            };
        } catch (error) {
            console.error('Error in setupRentButton (outer catch):', error);
            if (modalFeedbackEl) {
                modalFeedbackEl.textContent = 'Could not load rent status: ' + error.message;
                modalFeedbackEl.className = 'error';
            } else {
                alert('Could not load rent status: ' + error.message);
            }
            if (rentToggleButton) {
                rentToggleButton.textContent = 'Error loading status';
                rentToggleButton.disabled = false;
            }
        }
    }
}

cancelHoursBtn.addEventListener('click', () => {
    modal.style.display = 'none';
});

window.addEventListener('click', (event) => {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

saveHoursBtn.addEventListener('click', () => {
    const hours = modalHoursInput.value;
    if (hours === '' || parseFloat(hours) < 0 || parseFloat(hours) > 24) {
        if (modalFeedbackEl) modalFeedbackEl.textContent = 'Please enter a valid number of hours (0-24).'; else alert('Please enter a valid number of hours (0-24).');
        if (modalFeedbackEl) modalFeedbackEl.className = 'error';
        return;
    }
    if (!currentModalDate) return;

    if (modalFeedbackEl) modalFeedbackEl.textContent = '';
    if (modalFeedbackEl) modalFeedbackEl.className = '';

    const formData = new FormData();
    formData.append('log_date', currentModalDate);
    formData.append('hours_worked', hours);

    fetch('./log_hours.php', { // Absolute path
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (modalFeedbackEl) modalFeedbackEl.textContent = result.success;
                if (modalFeedbackEl) modalFeedbackEl.className = 'success';
                const hoursSpan = document.getElementById(`hours-${currentModalDate}`);
                const cell = hoursSpan ? hoursSpan.closest('td') : null;
                if (cell && hoursSpan) {
                    const newHours = parseFloat(hours);
                    applyHourStyles(cell, hoursSpan, newHours);
                }
                setTimeout(() => { modal.style.display = 'none'; }, 1000);
            } else if (result.error) {
                if (modalFeedbackEl) modalFeedbackEl.textContent = 'Error: ' + result.error; else alert('Error: ' + result.error);
                if (modalFeedbackEl) modalFeedbackEl.className = 'error';
            } else {
                if (modalFeedbackEl) modalFeedbackEl.textContent = 'Error: Unexpected response.'; else alert('Error: Unexpected response.');
                if (modalFeedbackEl) modalFeedbackEl.className = 'error';
            }
        })
        .catch(error => {
            if (modalFeedbackEl) modalFeedbackEl.textContent = 'Request failed: ' + error.message; else alert('Request failed: ' + error.message);
            if (modalFeedbackEl) modalFeedbackEl.className = 'error';
        });
});

renderCalendar(displayMonth, displayYear);

