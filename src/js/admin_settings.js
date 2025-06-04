document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('update-settings-form');
    const feedbackDiv = document.getElementById('settings-feedback');
    const payRateInput = document.getElementById('pay_rate');
    const payDay1Input = document.getElementById('pay_day_1');
    const payDay2Input = document.getElementById('pay_day_2');
    const federalTaxRateInput = document.getElementById('federal_tax_rate');
    const stateTaxRateInput = document.getElementById('state_tax_rate');

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
            payDay1Input.value = data.pay_day_1 || '';
            payDay2Input.value = data.pay_day_2 || '';
            federalTaxRateInput.value = data.federal_tax_rate ? (parseFloat(data.federal_tax_rate) * 100).toFixed(2) : '0.00';
            stateTaxRateInput.value = data.state_tax_rate ? (parseFloat(data.state_tax_rate) * 100).toFixed(2) : '0.00';
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
            pay_day_1: payDay1Input.value,
            pay_day_2: payDay2Input.value,
            federal_tax_rate: (parseFloat(federalTaxRateInput.value) / 100).toFixed(4), // Store as decimal
            state_tax_rate: (parseFloat(stateTaxRateInput.value) / 100).toFixed(4)   // Store as decimal
        };

        // Basic client-side validation (though server is primary)
        if (parseFloat(settingsData.pay_rate) < 0) {
            feedbackDiv.textContent = 'Error: Pay rate cannot be negative.';
            feedbackDiv.classList.add('error');
            return;
        }
        const pd1 = parseInt(settingsData.pay_day_1);
        const pd2 = parseInt(settingsData.pay_day_2);
        if (pd1 < 1 || pd1 > 31 || pd2 < 1 || pd2 > 31) { // Basic check, server will do more (e.g. valid days for month)
            feedbackDiv.textContent = 'Error: Pay days must be between 1 and 31.';
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
        // You could add more validation here, e.g. pd2 >= pd1,
        // but settings.php API should also handle this.

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
