document.addEventListener('DOMContentLoaded', function() {
    const summaryErrorDiv = document.getElementById('summary-error');
    let nwChartInstance = null;

    function formatCurrency(value) {
        const num = parseFloat(value);
        return isNaN(num) ? '0.00' : num.toFixed(2);
    }

    function fetchFinancialData() {
        summaryErrorDiv.textContent = '';
        summaryErrorDiv.className = '';

        fetch('api_financial_summary.php')
            .then(response => {
                if (!response.ok) {
                    // Try to get error message from response body if available
                    return response.json().then(errData => {
                        throw new Error(`HTTP error ${response.status}: ${errData.error || response.statusText}`);
                    }).catch(() => {
                        throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) { // Handle application-level errors from API
                    throw new Error(`API Error: ${data.error}`);
                }
                document.getElementById('current-net-worth').textContent = formatCurrency(data.current_net_worth);
                document.getElementById('total-cash').textContent = formatCurrency(data.total_cash_on_hand);
                document.getElementById('receivables-balance').textContent = formatCurrency(data.receivables_balance);
                document.getElementById('total-owed').textContent = formatCurrency(data.total_liabilities !== undefined ? data.total_liabilities : 0);

                const nextPaycheckLine = document.getElementById('next-paycheck-line');
                const paydayMessageContainer = document.getElementById('payday-message-container');
                const futureNetWorthLine = document.getElementById('future-net-worth-line');

                if (data.is_pay_day) {
                    nextPaycheckLine.style.display = 'none';
                    paydayMessageContainer.style.display = 'block'; // Show "Pay Day!"
                    futureNetWorthLine.style.display = 'none'; // Hide future net worth on payday
                    // Values for estimated_upcoming_pay will be 0 from API
                    document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                    document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'Today!';
                } else {
                    nextPaycheckLine.style.display = 'block'; // Or 'inline' or '' depending on original display
                    paydayMessageContainer.style.display = 'none'; // Hide "Pay Day!"
                    futureNetWorthLine.style.display = 'block'; // Show future net worth
                    document.getElementById('next-paycheck-amount').textContent = formatCurrency(data.estimated_upcoming_pay);
                    document.getElementById('next-paycheck-date').textContent = data.next_pay_date || 'N/A';
                }

                document.getElementById('future-net-worth').textContent = formatCurrency(data.future_net_worth);

                // document.getElementById('debug-pay-start').textContent = data.debug_pay_period_start || 'N/A';
                // document.getElementById('debug-pay-end').textContent = data.debug_pay_period_end || 'N/A';

                const labels = data.net_worth_history.map(r => r.date);
                const vals = data.net_worth_history.map(r => parseFloat(r.networth));

                if (nwChartInstance) {
                    nwChartInstance.destroy();
                }
                const chartCtx = document.getElementById('nwChart').getContext('2d');
                nwChartInstance = new Chart(chartCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Net Worth',
                            data: vals,
                            fill: false,
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    tooltipFormat: 'MMM d, yyyy' // Example: Jan 1, 2023
                                },
                                title: {
                                    display: true,
                                    text: 'Date'
                                }
                            },
                            y: {
                                beginAtZero: false,
                                title: {
                                    display: true,
                                    text: 'Net Worth'
                                }
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            })
            .catch(error => {
                console.error('Fetch/API error:', error);
                summaryErrorDiv.textContent = 'Failed to load financial summary: ' + error.message;
                summaryErrorDiv.className = 'error';
            });
    }

    // Hour logging form JavaScript REMOVED

    // Initial data load
    fetchFinancialData();
});
