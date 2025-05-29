<!DOCTYPE html>
<html>

<head>
    <title>Net-Worth Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>

<body>
    <h1>Your Net-Worth Over Time</h1>
    <canvas id="nwChart" width="800" height="400"></canvas>
    <script>
        // in dashboard.php, replace your fetch block with:
        fetch('api_networth.php')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.text();                 // ← get raw text
            })
            .then(text => {
                console.log('Raw API response:', text);
                let data;
                try {
                    data = JSON.parse(text);       // ← try to parse
                } catch (e) {
                    return console.error('JSON parse failed:', e);
                }
                // if we get here, data is valid JSON
                const labels = data.map(r => r.date),
                    vals = data.map(r => parseFloat(r.networth));
                new Chart(
                    document.getElementById('nwChart'),
                    {
                        type: 'line',
                        data: {
                            labels, datasets: [{
                                label: 'Net Worth',
                                data: vals,
                                fill: false,
                                tension: 0.2
                            }]
                        },
                        options: { scales: { y: { beginAtZero: false } } }
                    }
                );
            })
            .catch(err => console.error('Fetch/API error:', err));
    </script>
</body>

</html>