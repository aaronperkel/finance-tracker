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

// Function to calculate pay period start and end dates
function getPayPeriod(paymentDateStr) {
    // Ensure parsing as local midnight by explicitly providing T00:00:00
    // JavaScript Date constructor can be unreliable with YYYY-MM-DD format alone
    const paymentDate = new Date(paymentDateStr + 'T00:00:00');
    paymentDate.setHours(0, 0, 0, 0); // Normalize to start of day

    const payEndDate = new Date(paymentDate);
    payEndDate.setDate(paymentDate.getDate() - 5);
    payEndDate.setHours(0, 0, 0, 0); // Normalize

    const payStartDate = new Date(payEndDate);
    payStartDate.setDate(payEndDate.getDate() - 13);
    payStartDate.setHours(0, 0, 0, 0); // Normalize

    return { startDate: payStartDate, endDate: payEndDate };
}

const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];

function applyHourStyles(dayCell, hoursSpan, hoursValue) {
    // Clear previous specific hour styling (classes and inline styles for hours)
    // Do NOT remove 'in-pay-period' here, as it's a base style for the cell.
    dayCell.classList.remove('gold-hours', 'red-hours');
    dayCell.style.backgroundColor = ''; // Clear any inline background from previous HSL
    dayCell.style.color = '';           // Clear inline cell text color
    hoursSpan.style.color = '';         // Clear inline span text color
    hoursSpan.style.fontWeight = 'normal';// Reset font weight for span
    hoursSpan.classList.remove('placeholder-dash', 'default-hours'); // default-hours might be legacy

    if (hoursValue === null || typeof hoursValue === 'undefined') { // For disabled/non-work days (or cells not representing a workday)
        hoursSpan.textContent = '';
        hoursSpan.classList.add('placeholder-dash');
        // Background will be default, or 'in-pay-period' blue if applicable, or 'disabled-day' grey.
        // Text color for placeholder dash is handled by CSS for .placeholder-dash.
    } else if (hoursValue > 7.5) {
        dayCell.classList.add('gold-hours'); // CSS handles background & text color for .gold-hours
        hoursSpan.textContent = hoursValue.toFixed(2);
        // hoursSpan will inherit color from dayCell's .gold-hours style.
    } else if (hoursValue > 0 && hoursValue <= 7.5) {
        let percentage = hoursValue / 7.5;
        let hue = percentage * 120; // 0 (for >0 hours, so slightly red-ish green) to 120 (green)
        dayCell.style.backgroundColor = `hsl(${hue}, 70%, 88%)`; // Light background (inline style)
        dayCell.style.color = `hsl(${hue}, 90%, 25%)`;    // Darker text for contrast (inline style)
        hoursSpan.style.color = `hsl(${hue}, 90%, 25%)`; // Ensure span text also has this color (inline style)
        hoursSpan.textContent = hoursValue.toFixed(2);
        hoursSpan.style.fontWeight = 'bold';
    } else if (hoursValue === 0) { // Explicitly 0 hours
        dayCell.classList.add('red-hours'); // CSS handles background & text color for .red-hours
        hoursSpan.textContent = hoursValue.toFixed(2);
        // hoursSpan will inherit color from dayCell's .red-hours style.
    } else { // Fallback for unexpected hoursValue (e.g., negative, though input validation should prevent)
        hoursSpan.textContent = '';
        hoursSpan.classList.add('placeholder-dash');
    }
}

function fetchAndDisplayExpenses(displayYear, displayMonth) { // month is 0-11 for JS Date
    console.log('[Calendar Expenses] Fetching expenses for year:', displayYear, 'month:', displayMonth);
    fetch('api_get_upcoming_expenses.php')
        .then(response => {
            console.log('[Calendar Expenses] Raw response object:', response);
            if (!response.ok) {
                // Try to parse error body for more details
                return response.json().then(errData => {
                    console.error('[Calendar Expenses] API Error Response Data:', errData);
                    throw new Error(`HTTP error ${response.status}: ${errData.error || errData.error_message || 'Failed to fetch expenses'}`);
                }).catch((parsingError) => { // If error body parsing fails
                    console.error('[Calendar Expenses] API Error Response Parsing Error:', parsingError);
                    throw new Error(`HTTP error ${response.status}: Failed to fetch expenses and parse error response.`);
                });
            }
            return response.json();
        })
        .then(expenses => {
            console.log('[Calendar Expenses] Parsed expenses payload:', expenses); // Log the whole payload

            if (!Array.isArray(expenses)) {
                console.warn('[Calendar Expenses] Expenses data is not an array:', expenses);
                return;
            }

            expenses.forEach((expense, index) => {
                console.log(`[Calendar Expenses] Processing expense item ${index}:`, expense);

                // Check for debug or error messages from the API
                if (expense._debug_utility_api) {
                    console.log('[Calendar Expenses] API Debug Info:', expense._debug_utility_api);
                    return; // Don't try to render debug info as an expense
                }
                if (expense.error_message) {
                    console.error('[Calendar Expenses] API returned an error for an item:', expense.error_message);
                    // Optionally, display this error on the calendar page if appropriate
                    return; // Don't try to render an error as an expense
                }

                if (!expense || typeof expense.date === 'undefined' || typeof expense.emoji === 'undefined') {
                    console.warn('[Calendar Expenses] Skipping invalid expense object (missing date or emoji):', expense);
                    return;
                }

                const expenseDate = new Date(expense.date + 'T00:00:00');
                console.log(`[Calendar Expenses] Expense date: ${expense.date}, Parsed JS Date: ${expenseDate.toISOString()}`);

                if (expenseDate.getFullYear() === displayYear && expenseDate.getMonth() === displayMonth) {
                    console.log(`[Calendar Expenses] Expense ${expense.type} on ${expense.date} is in current display month.`);
                    const dayCell = calendarBody.querySelector(`td[data-date="${expense.date}"]`);

                    if (dayCell && !dayCell.classList.contains('not-current-month')) {
                        console.log(`[Calendar Expenses] Found dayCell for ${expense.date}:`, dayCell);
                        let emojiContainer = dayCell.querySelector('.expense-emoji-container');
                        if (!emojiContainer) {
                            emojiContainer = document.createElement('div');
                            emojiContainer.className = 'expense-emoji-container';
                            emojiContainer.style.display = 'flex';
                            emojiContainer.style.justifyContent = 'center';
                            emojiContainer.style.alignItems = 'center';
                            emojiContainer.style.marginTop = '3px';
                            dayCell.appendChild(emojiContainer);
                        }

                        let individualEmojiSpan = document.createElement('span');
                        individualEmojiSpan.className = 'expense-emoji-item';
                        individualEmojiSpan.textContent = expense.emoji;

                        // Original title:
                        // individualEmojiSpan.title = `${expense.type}: $${parseFloat(expense.amount).toFixed(2)}`;

                        // New title logic:
                        let tooltipText = `${expense.type}: $${parseFloat(expense.amount).toFixed(2)}`;
                        if (expense.status) { // Check if status field exists (it should for utilities from updated API)
                            tooltipText += ` (${expense.status})`; // Append status, e.g., (Paid) or (Unpaid)
                        }
                        individualEmojiSpan.title = tooltipText;

                        individualEmojiSpan.style.margin = '0 1px';

                        emojiContainer.appendChild(individualEmojiSpan);
                        console.log(`[Calendar Expenses] Appended emoji for ${expense.type} to cell ${expense.date}`);
                    } else {
                        if (!dayCell) {
                            console.warn(`[Calendar Expenses] dayCell NOT FOUND for date: ${expense.date}`);
                        } else {
                            console.warn(`[Calendar Expenses] dayCell for ${expense.date} is in 'not-current-month'.`);
                        }
                    }
                } else {
                    // console.log(`[Calendar Expenses] Expense ${expense.type} on ${expense.date} is NOT in current display month (Display: ${displayYear}-${displayMonth+1}).`);
                }
            });
        })
        .catch(error => {
            console.error('[Calendar Expenses] Overall error fetching or displaying expenses:', error.message, error);
        });
}

// This function will now handle the actual DOM manipulation for calendar cells
function renderCalendarInternal(month, year, paydays = []) {
    calendarBody.innerHTML = ''; // Clear previous calendar
    currentMonthYearEl.textContent = `${monthNames[month]} ${year}`; // Title is already set by renderCalendar, but good for standalone call if needed

    const payPeriods = [];
    if (Array.isArray(paydays)) {
        paydays.forEach(paydayString => {
            payPeriods.push(getPayPeriod(paydayString));
        });
    }

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
                if (Array.isArray(paydays) && paydays.includes(dateStr)) {
                    dayCell.classList.add('is-payday');
                }

                // Normalize cellDate to the start of the day for accurate comparisons
                const currentCellDateNormalized = new Date(cellDate);
                currentCellDateNormalized.setHours(0, 0, 0, 0);

                const dayOfWeek = currentCellDateNormalized.getDay();
                let isDisabledDay = currentCellDateNormalized < jobStartDate || dayOfWeek === 0 || dayOfWeek === 6;

                if (isDisabledDay) {
                    applyHourStyles(dayCell, hoursDisplaySpan, null); // Style for disabled/non-work days
                    dayCell.classList.add('disabled-day');
                    // No click listener for modal
                } else {
                    applyHourStyles(dayCell, hoursDisplaySpan, 7.50); // Default styling for workdays
                    dayCell.addEventListener('click', () => openEditModal(dateStr, hoursDisplaySpan.textContent, false)); // isDefault is now less relevant here
                }

                // Check if cellDate is within any pay period
                if (!isDisabledDay) { // Only apply if not a disabled day
                    for (const period of payPeriods) {
                        if (currentCellDateNormalized.getTime() >= period.startDate.getTime() && currentCellDateNormalized.getTime() <= period.endDate.getTime()) {
                            dayCell.classList.add('in-pay-period');
                            break; // Found a period, no need to check others
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
        if (date > daysInMonth && i < 5) break; // Optimization if all days fit in less than 6 weeks
    }
    fetchAndDisplayHours(month + 1, year); // API uses 1-12 for month

    // Add the new call for expenses
    fetchAndDisplayExpenses(year, month); // year (YYYY), month (0-11)
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

    // Rent Button Logic
    // The modal variable itself is the modal root, pass its content area if it has one, or modal itself.
    // Assuming 'modal' is the element whose content we modify.
    setupRentButton(modal.querySelector('.modal-content') || modal, dateStr); // Pass modal content part or modal itself

    modal.style.display = 'block';
    modalHoursInput.focus();
}

// Helper to format date as YYYY-MM-01 for rent month
function getRentMonthString(dateStr) {
    // dateStr is expected to be YYYY-MM-DD
    const dateParts = dateStr.split('-');
    // Ensure month is two digits for the first day of the month string
    return `${dateParts[0]}-${dateParts[1]}-01`;
}

// Helper to get today's date as YYYY-MM-DD
function getTodaysDateString() {
    const todayDate = new Date();
    const yyyy = todayDate.getFullYear();
    const mm = String(todayDate.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const dd = String(todayDate.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

async function setupRentButton(modalContentElement, clickedDateStr) {
    alert(`[Debug] setupRentButton called for: ${clickedDateStr}`); // FORCED DEBUG

    const dateObj = new Date(clickedDateStr + 'T00:00:00'); // Ensure local time interpretation
    const dayOfMonth = dateObj.getDate(); // Use getDate() which is 1-31

    const rentButtonContainerId = 'rent-button-container';
    let rentButtonContainer = modalContentElement.querySelector(`#${rentButtonContainerId}`);

    // Remove existing container if it exists, to ensure clean state
    if (rentButtonContainer) {
        rentButtonContainer.remove();
    }

    if (dayOfMonth === 1) {
        rentButtonContainer = document.createElement('div');
        rentButtonContainer.id = rentButtonContainerId;
        rentButtonContainer.style.marginTop = '10px';

        // New placement logic: Insert before the div with class 'modal-buttons'
        const modalButtonsDiv = modalContentElement.querySelector('.modal-buttons');
        if (modalButtonsDiv && modalButtonsDiv.parentNode) {
            modalButtonsDiv.parentNode.insertBefore(rentButtonContainer, modalButtonsDiv);
        } else {
            // Fallback if '.modal-buttons' isn't found directly under modalContentElement
            // or if modalContentElement is the direct parent we want to append to.
            // This might happen if querySelector is on modal itself and .modal-buttons is deeper.
            // For your provided HTML, modalContentElement is .modal-content, and .modal-buttons is its child.
            modalContentElement.appendChild(rentButtonContainer);
        }


        rentButtonContainer.innerHTML = '<button id="rentToggleButton" class="button">Loading Rent Status...</button>';
        const rentToggleButton = rentButtonContainer.querySelector('#rentToggleButton');

        alert('[Debug] Before getRentMonthString. clickedDateStr: ' + clickedDateStr);
        const rentMonthForAPI = getRentMonthString(clickedDateStr);
        alert('[Debug] After getRentMonthString. rentMonthForAPI: ' + rentMonthForAPI); // <<< THIS IS THE CRITICAL ALERT NOW

        try {
            rentToggleButton.disabled = true; // Disable while loading

            // ON-SCREEN DEBUG for rentMonthForAPI:
            if (modalFeedbackEl) { // Check if feedback element exists
                modalFeedbackEl.textContent = `Debug: rentMonthForAPI = ${rentMonthForAPI}. Attempting fetch...`;
                modalFeedbackEl.className = 'info'; // Use a neutral class for info
            } else {
                // If modalFeedbackEl is not found, this is an issue with modal structure assumptions
                alert(`Debug: rentMonthForAPI = ${rentMonthForAPI}. Modal feedback element not found.`);
            }
            // console.log('[CalendarJS] Rent Status Fetch: Attempting to fetch for rent_month =', rentMonthForAPI); // Original DEBUG LINE

            const response = await fetch(`src/get_rent_status.php?rent_month=${rentMonthForAPI}`);

            rentToggleButton.disabled = false; // Re-enable after fetch attempt

            if (!response.ok) {
                let errorText = `HTTP error ${response.status}`;
                try {
                    const errorData = await response.text(); // Get raw text for debugging
                    console.error("Raw error response from get_rent_status:", errorData);
                    errorText += ` - ${errorData.substring(0, 100)}`; // Show first 100 chars
                } catch (e) { /* ignore text parsing error */ }
                throw new Error(errorText);
            }

            const data = await response.json(); // Now try to parse as JSON

            if (data.is_paid) {
                rentToggleButton.textContent = `Mark Rent Unpaid (Paid ${data.details.amount} on ${data.details.paid_date})`;
                rentToggleButton.dataset.rentStatus = 'paid';
                rentToggleButton.dataset.rentAmount = data.details.amount;
            } else {
                rentToggleButton.textContent = 'Mark Current Month Rent Paid';
                rentToggleButton.dataset.rentStatus = 'unpaid';
            }

            rentToggleButton.onclick = async () => {
                rentToggleButton.disabled = true;
                const currentStatus = rentToggleButton.dataset.rentStatus;
                // currentModalDate should be set when modal opens, it's the YYYY-MM-DD of the clicked day
                const monthToActOn = getRentMonthString(currentModalDate);

                if (currentStatus === 'unpaid') {
                    const rentAmountToPay = parseFloat(document.getElementById('rent-amount-input-field')?.value || prompt("Enter rent amount:", "1100.99") || "1100.99");
                    const paidDate = getTodaysDateString();

                    try {
                        const markPaidResponse = await fetch('src/mark_rent_paid.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({rent_month: monthToActOn, paid_date: paidDate, amount: rentAmountToPay})
                        });
                        if (!markPaidResponse.ok) throw new Error(`HTTP error ${markPaidResponse.status}`);
                        const markPaidData = await markPaidResponse.json();

                        if (markPaidData.success) {
                            rentToggleButton.textContent = `Mark Rent Unpaid (Paid ${rentAmountToPay.toFixed(2)} on ${paidDate})`;
                            rentToggleButton.dataset.rentStatus = 'paid';
                            rentToggleButton.dataset.rentAmount = rentAmountToPay;
                            if (window.refreshFinancialSummary) window.refreshFinancialSummary();
                        } else { throw new Error(markPaidData.error || 'Unknown error'); }
                    } catch (err) {
                        modalFeedbackEl.textContent = 'Error marking rent paid: ' + err.message;
                        modalFeedbackEl.className = 'error';
                    }

                } else { // currentStatus === 'paid'
                    try {
                        const deleteResponse = await fetch('src/delete_rent_payment.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({rent_month: monthToActOn})
                        });
                        if (!deleteResponse.ok) throw new Error(`HTTP error ${deleteResponse.status}`);
                        const deleteData = await deleteResponse.json();

                        if (deleteData.success) {
                            rentToggleButton.textContent = 'Mark Current Month Rent Paid';
                            rentToggleButton.dataset.rentStatus = 'unpaid';
                            if (window.refreshFinancialSummary) window.refreshFinancialSummary();
                        } else { throw new Error(deleteData.error || 'Unknown error'); }
                    } catch (err) {
                         modalFeedbackEl.textContent = 'Error marking rent unpaid: ' + err.message;
                         modalFeedbackEl.className = 'error';
                    }
                }
                rentToggleButton.disabled = false;
                 // Clear feedback after a short delay if successful, or rely on new feedback
                setTimeout(() => {
                    if (modalFeedbackEl.className !== 'error') modalFeedbackEl.textContent = '';
                }, 2000);
            };

        } catch (error) {
            alert(`[Debug] In catch. Error message: ${error.message}`); // DEBUG: Alert the raw error message

            if (modalFeedbackEl) {
                modalFeedbackEl.textContent = 'Could not load rent status: ' + error.message;
                modalFeedbackEl.className = 'error';
            } else {
                alert('[Debug] modalFeedbackEl is NULL in catch block!');
            }

            console.error('Error in setupRentButton:', error); // This will still go to browser console if available

            if(rentToggleButton) {
                rentToggleButton.textContent = 'Error loading status';
                rentToggleButton.disabled = false;
            }
        }
    }
    // If not the 1st of the month, any existing rentButtonContainer (if not removed above) should be cleared.
    // The new logic of removing/re-creating container handles this.
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
