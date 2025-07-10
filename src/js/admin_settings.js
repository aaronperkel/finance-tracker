document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('update-settings-form');
    const feedbackDiv = document.getElementById('settings-feedback');
    const payRateInput = document.getElementById('pay_rate');
    const federalTaxRateInput = document.getElementById('federal_tax_rate');
    const stateTaxRateInput = document.getElementById('state_tax_rate');
    const payScheduleTypeSelect = document.getElementById('pay_schedule_type');
    const payScheduleDetail1Input = document.getElementById('pay_schedule_detail1');
    const payScheduleDetail1Container = document.getElementById('pay_schedule_detail1_container');
    const payScheduleDetail1Label = document.getElementById('pay_schedule_detail1_label');
    const payScheduleDetail2Input = document.getElementById('pay_schedule_detail2');
    const payScheduleDetail2Container = document.getElementById('pay_schedule_detail2_container');

    function updatePayScheduleUI(scheduleType) {
        payScheduleDetail1Container.style.display = 'block'; // Generally visible
        payScheduleDetail2Container.style.display = 'none'; // Hidden by default

        switch (scheduleType) {
            case 'bi-weekly':
                payScheduleDetail1Label.textContent = 'Reference Friday:';
                payScheduleDetail1Input.type = 'date';
                payScheduleDetail1Input.required = true;
                payScheduleDetail2Input.required = false;
                payScheduleDetail2Input.value = ''; // Clear if switching
                break;
            case 'semi-monthly':
                payScheduleDetail1Label.textContent = 'First Payday (Day of Month, 1-31):';
                payScheduleDetail1Input.type = 'number';
                payScheduleDetail1Input.min = '1';
                payScheduleDetail1Input.max = '31';
                payScheduleDetail1Input.required = true;
                payScheduleDetail2Container.style.display = 'block';
                document.getElementById('pay_schedule_detail2_label').textContent = 'Second Payday (Day of Month, 0 for last, 1-31):';
                payScheduleDetail2Input.type = 'number';
                payScheduleDetail2Input.min = '0';
                payScheduleDetail2Input.max = '31';
                payScheduleDetail2Input.required = true;
                break;
            case 'monthly':
                payScheduleDetail1Label.textContent = 'Payday (Day of Month, 0 for last, 1-31):';
                payScheduleDetail1Input.type = 'number';
                payScheduleDetail1Input.min = '0';
                payScheduleDetail1Input.max = '31';
                payScheduleDetail1Input.required = true;
                payScheduleDetail2Input.required = false;
                payScheduleDetail2Input.value = ''; // Clear if switching
                break;
            default:
                // Hide all detail fields if type is somehow unknown
                payScheduleDetail1Container.style.display = 'none';
                payScheduleDetail2Container.style.display = 'none';
                break;
        }
    }

    // Event listener for pay schedule type change
    payScheduleTypeSelect.addEventListener('change', function() {
        updatePayScheduleUI(this.value);
    });

    // Fetch current settings on page load
    fetch('settings.php') // This API endpoint needs to exist and return current settings
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                   throw new Error(`Failed to fetch settings: ${response.status} ${errData.error || response.statusText}`);
                }).catch(() => new Error(`Failed to fetch settings: ${response.status} ${response.statusText}`));
            }
            return response.json();
        })
        .then(data => {
            if (data.error) { // Handle application-level errors from API
                 throw new Error(`API Error: ${data.error}`);
            }
            payRateInput.value = data.pay_rate || '';
            federalTaxRateInput.value = data.federal_tax_rate ? (parseFloat(data.federal_tax_rate) * 100).toFixed(2) : '0.00';
            stateTaxRateInput.value = data.state_tax_rate ? (parseFloat(data.state_tax_rate) * 100).toFixed(2) : '0.00';

            // Populate pay schedule fields
            if (data.pay_schedule_type) {
                payScheduleTypeSelect.value = data.pay_schedule_type;
            }
            updatePayScheduleUI(payScheduleTypeSelect.value); // Update UI based on fetched or default type

            payScheduleDetail1Input.value = data.pay_schedule_detail1 || '';
            payScheduleDetail2Input.value = data.pay_schedule_detail2 || '';

        })
        .catch(error => {
            feedbackDiv.textContent = 'Error loading settings: ' + error.message;
            feedbackDiv.className = 'error feedback-message'; // Add feedback-message for styling
        });

    // Handle form submission
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        feedbackDiv.textContent = '';
        feedbackDiv.className = 'feedback-message'; // Clear previous status, keep base class

        const settingsData = {
            pay_rate: payRateInput.value,
            federal_tax_rate: (parseFloat(federalTaxRateInput.value) / 100).toFixed(4), // Store as decimal
            state_tax_rate: (parseFloat(stateTaxRateInput.value) / 100).toFixed(4),   // Store as decimal
            pay_schedule_type: payScheduleTypeSelect.value,
            pay_schedule_detail1: payScheduleDetail1Input.value,
            pay_schedule_detail2: payScheduleDetail2Input.value
        };

        // Basic client-side validation (though server is primary)
        if (parseFloat(settingsData.pay_rate) < 0) {
            feedbackDiv.textContent = 'Error: Pay rate cannot be negative.';
            feedbackDiv.classList.add('error');
            return;
        }
        const fedTax = parseFloat(federalTaxRateInput.value);
        const stateTax = parseFloat(stateTaxRateInput.value);
        if (fedTax < 0 || fedTax > 100 || stateTax < 0 || stateTax > 100) {
            feedbackDiv.textContent = 'Error: Tax rates must be between 0 and 100%.';
            feedbackDiv.classList.add('error');
            return;
        }

        // Pay Schedule Validation
        const scheduleType = settingsData.pay_schedule_type;
        const detail1 = settingsData.pay_schedule_detail1;
        const detail2 = settingsData.pay_schedule_detail2;

        if (scheduleType === 'bi-weekly') {
            if (!detail1) {
                feedbackDiv.textContent = 'Error: Reference Friday is required for bi-weekly schedule.';
                feedbackDiv.classList.add('error');
                return;
            }
            // Basic date format check (YYYY-MM-DD), though input type="date" helps.
            // Server-side will do more robust validation.
            if (!/^\d{4}-\d{2}-\d{2}$/.test(detail1)) {
                 feedbackDiv.textContent = 'Error: Invalid date format for Reference Friday. Use YYYY-MM-DD.';
                 feedbackDiv.classList.add('error');
                 return;
            }
        } else if (scheduleType === 'semi-monthly') {
            if (!detail1 || detail2 === '') { // detail2 can be '0'
                feedbackDiv.textContent = 'Error: Both payday details are required for semi-monthly schedule.';
                feedbackDiv.classList.add('error');
                return;
            }
            const d1 = parseInt(detail1, 10);
            const d2 = parseInt(detail2, 10);
            if (isNaN(d1) || d1 < 1 || d1 > 31) {
                feedbackDiv.textContent = 'Error: First payday must be a number between 1 and 31.';
                feedbackDiv.classList.add('error');
                return;
            }
            if (isNaN(d2) || d2 < 0 || d2 > 31) { // 0 is valid for last day
                feedbackDiv.textContent = 'Error: Second payday must be a number between 0 (for last day) and 31.';
                feedbackDiv.classList.add('error');
                return;
            }
            if (d1 === d2 && d1 !== 0) { // Allow 0 and 0 if that makes sense for "last day" and "last day" (though backend should clarify)
                feedbackDiv.textContent = 'Error: First and second payday cannot be the same (unless both are 0 for last day, which is unusual).';
                feedbackDiv.classList.add('error');
                return;
            }
        } else if (scheduleType === 'monthly') {
            if (!detail1) {
                feedbackDiv.textContent = 'Error: Payday detail is required for monthly schedule.';
                feedbackDiv.classList.add('error');
                return;
            }
            const d1 = parseInt(detail1, 10);
             if (isNaN(d1) || d1 < 0 || d1 > 31) { // 0 is valid for last day
                feedbackDiv.textContent = 'Error: Payday must be a number between 0 (for last day) and 31.';
                feedbackDiv.classList.add('error');
                return;
            }
        }

        fetch('settings.php', { // This API endpoint needs to handle POST to update settings
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(settingsData)
        })
        .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, body: data })))
        .then(result => {
            if (result.ok && result.body.success) {
                feedbackDiv.textContent = result.body.success || 'Settings updated successfully!';
                feedbackDiv.classList.add('success');
            } else {
                let errorMessage = 'Error: ' + (result.body.error || `Failed with status ${result.status}`);
                if (result.body.details && Array.isArray(result.body.details)) {
                     errorMessage += ' Details: ' + result.body.details.join(', ');
                } else if (typeof result.body.details === 'string') {
                     errorMessage += ' Details: ' + result.body.details;
                }
                feedbackDiv.textContent = errorMessage;
                feedbackDiv.classList.add('error');
            }
        })
        .catch(error => {
            feedbackDiv.textContent = 'Request failed: ' + error.message;
            feedbackDiv.classList.add('error');
        });
    });
});
