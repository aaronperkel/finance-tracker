document.addEventListener('DOMContentLoaded', function () {
    const errorDiv = document.getElementById('summary-error');
    let chartInstance = null;
    let dashboardData = null;
    let activeChartMode = 'networth'; // 'networth' or 'accounts'
    let activeRange = 'all';

    const CHART_COLORS = [
        '#8b5cf6', '#34d399', '#f87171', '#60a5fa',
        '#fbbf24', '#f472b6', '#a78bfa', '#2dd4bf',
    ];

    function formatCurrency(value) {
        const num = parseFloat(value);
        if (isNaN(num)) return '$0.00';
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getCutoffDate(range) {
        if (range === 'all') return null;
        const now = new Date();
        if (range === '3m') now.setMonth(now.getMonth() - 3);
        else if (range === '6m') now.setMonth(now.getMonth() - 6);
        else if (range === '1y') now.setFullYear(now.getFullYear() - 1);
        return now.toISOString().split('T')[0];
    }

    function filterByRange(items, dateKey) {
        const cutoff = getCutoffDate(activeRange);
        if (!cutoff) return items;
        return items.filter(item => item[dateKey] >= cutoff);
    }

    function getTimeUnit(count) {
        if (count > 90) return 'month';
        if (count > 30) return 'week';
        return 'day';
    }

    function renderBalances(data) {
        const list = document.getElementById('balances-list');
        list.innerHTML = '';

        if (!data.accounts || data.accounts.length === 0) {
            list.innerHTML = '<p class="no-data">No accounts found. Add accounts and record a snapshot to get started.</p>';
            return;
        }

        const assets = data.accounts.filter(a => a.type === 'Asset');
        const liabilities = data.accounts.filter(a => a.type === 'Liability');

        function buildSection(title, items, cssClass) {
            if (items.length === 0) return;
            const section = document.createElement('div');
            section.className = 'balance-section';
            section.innerHTML = '<h3>' + title + '</h3>';
            items.forEach(a => {
                const row = document.createElement('div');
                row.className = 'balance-row';
                row.innerHTML =
                    '<span class="account-name">' + a.name + '</span>' +
                    '<span class="account-balance ' + cssClass + '">' + formatCurrency(a.balance) + '</span>';
                section.appendChild(row);
            });
            list.appendChild(section);
        }

        buildSection('Assets', assets, 'assets');
        buildSection('Liabilities', liabilities, 'liabilities');

        const dateStr = data.snapshot_date;
        if (dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            document.getElementById('snapshot-date').textContent = d.toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
            });
        }

        document.getElementById('total-assets').textContent = formatCurrency(data.total_assets);
        document.getElementById('total-liabilities').textContent = formatCurrency(data.total_liabilities);
        document.getElementById('net-worth').textContent = formatCurrency(data.net_worth);
    }

    function chartTheme() {
        return {
            gridColor: 'rgba(139, 92, 246, 0.08)',
            tickColor: '#6b6484',
            tooltipBg: '#1e1b2e',
            tooltipBorder: '#2e2a42',
            tooltipText: '#e8e4f0',
        };
    }

    function renderNetWorthChart(data) {
        if (chartInstance) chartInstance.destroy();
        const ctx = document.getElementById('mainChart').getContext('2d');
        const c = chartTheme();

        const filtered = filterByRange(data.net_worth_history, 'date');
        const labels = filtered.map(r => r.date);
        const values = filtered.map(r => parseFloat(r.networth));

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net Worth',
                    data: values,
                    fill: true,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.08)',
                    tension: 0.3,
                    pointRadius: labels.length > 40 ? 2 : 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#1e1b2e',
                    pointBorderWidth: 2,
                    borderWidth: 2.5,
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: getTimeUnit(labels.length),
                            tooltipFormat: 'MMM d, yyyy',
                            displayFormats: { day: 'MMM d', week: 'MMM d', month: 'MMM yyyy' }
                        },
                        title: { display: false },
                        grid: { color: c.gridColor },
                        ticks: { color: c.tickColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { size: 11 } },
                        border: { color: 'transparent' }
                    },
                    y: {
                        beginAtZero: false,
                        title: { display: false },
                        grid: { color: c.gridColor },
                        ticks: { color: c.tickColor, callback: v => '$' + v.toLocaleString(), font: { size: 11 } },
                        border: { color: 'transparent' }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    tooltip: {
                        backgroundColor: c.tooltipBg, borderColor: c.tooltipBorder, borderWidth: 1,
                        titleColor: c.tooltipText, bodyColor: c.tooltipText,
                        padding: 12, cornerRadius: 8,
                        callbacks: { label: ctx => ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y) }
                    },
                    legend: { display: false }
                }
            }
        });
    }

    function renderAccountsChart(data) {
        if (chartInstance) chartInstance.destroy();
        const ctx = document.getElementById('mainChart').getContext('2d');
        const c = chartTheme();
        const cutoff = getCutoffDate(activeRange);

        const datasets = [];
        let colorIdx = 0;
        let maxPoints = 0;

        for (const [name, info] of Object.entries(data.account_history)) {
            const color = CHART_COLORS[colorIdx % CHART_COLORS.length];
            let points = info.data.map(d => ({ x: d.date, y: d.balance }));
            if (cutoff) points = points.filter(p => p.x >= cutoff);
            if (points.length > maxPoints) maxPoints = points.length;
            datasets.push({
                label: name,
                data: points,
                fill: false,
                borderColor: color,
                backgroundColor: color,
                tension: 0.3,
                pointRadius: maxPoints > 40 ? 2 : 4,
                pointHoverRadius: 6,
                pointBackgroundColor: color,
                pointBorderColor: '#1e1b2e',
                pointBorderWidth: 2,
                borderWidth: 2,
                borderDash: info.type === 'Liability' ? [6, 4] : []
            });
            colorIdx++;
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { datasets: datasets },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: getTimeUnit(maxPoints),
                            tooltipFormat: 'MMM d, yyyy',
                            displayFormats: { day: 'MMM d', week: 'MMM d', month: 'MMM yyyy' }
                        },
                        title: { display: false },
                        grid: { color: c.gridColor },
                        ticks: { color: c.tickColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { size: 11 } },
                        border: { color: 'transparent' }
                    },
                    y: {
                        beginAtZero: false,
                        title: { display: false },
                        grid: { color: c.gridColor },
                        ticks: { color: c.tickColor, callback: v => '$' + v.toLocaleString(), font: { size: 11 } },
                        border: { color: 'transparent' }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    tooltip: {
                        backgroundColor: c.tooltipBg, borderColor: c.tooltipBorder, borderWidth: 1,
                        titleColor: c.tooltipText, bodyColor: c.tooltipText,
                        padding: 12, cornerRadius: 8,
                        callbacks: { label: ctx => ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y) }
                    },
                    legend: {
                        position: 'bottom',
                        labels: { color: '#9a93b0', usePointStyle: true, pointStyle: 'circle', padding: 20, font: { size: 11 }, boxWidth: 8 }
                    }
                }
            }
        });
    }

    function renderActiveChart() {
        if (!dashboardData) return;
        if (activeChartMode === 'networth') renderNetWorthChart(dashboardData);
        else renderAccountsChart(dashboardData);
    }

    // Chart type toggle
    document.getElementById('btn-net-worth').addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('btn-accounts').classList.remove('active');
        activeChartMode = 'networth';
        renderActiveChart();
    });

    document.getElementById('btn-accounts').addEventListener('click', function () {
        this.classList.add('active');
        document.getElementById('btn-net-worth').classList.remove('active');
        activeChartMode = 'accounts';
        renderActiveChart();
    });

    // Time range buttons
    document.querySelectorAll('.range-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.range-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeRange = this.dataset.range;
            renderActiveChart();
        });
    });

    // Fetch data
    fetch('api_dashboard.php')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load data');
            return response.json();
        })
        .then(data => {
            dashboardData = data;
            renderBalances(data);
            renderActiveChart();
        })
        .catch(error => {
            console.error('Error:', error);
            errorDiv.textContent = 'Failed to load dashboard data: ' + error.message;
            errorDiv.className = 'feedback-message error';
        });
});