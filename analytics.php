<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sidebar.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? 0;
$uname = htmlspecialchars($_SESSION['user_name'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Energy Analytics | MiGrid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #020c16;
            color: #e8ecf1;
        }
        :root {
            --bg: #020c16;
            --accent: #10b981;
            --accent-dark: rgba(4,30,33,0.6);
            --border: rgba(16,185,129,0.3);
            --card-bg: linear-gradient(135deg, rgba(4,30,33,0.6), rgba(1,11,12,0.6));
            --text-muted: #8ba8b5;
        }
        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            transition: margin-left 0.2s;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(2px);
        }
        .card-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.8rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.2rem;
        }
        .insight-card {
            background: rgba(16,185,129,0.1);
            border-radius: 0.75rem;
            padding: 0.8rem;
            text-align: center;
        }
        .insight-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent);
        }
        .insight-label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        canvas {
            max-height: 300px;
            width: 100%;
        }
        .session-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        @media (max-width: 640px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        .alert-success { background: #059669; }
        .alert-warning { background: #f59e0b; }
        .alert-error   { background: #dc2626; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="card">
        <div class="session-info">
            <div><strong>👤 <?= $uname ?></strong></div>
            <div>🤖 AI Analytics Dashboard</div>
        </div>
    </div>

    <!-- Chart Cards -->
    <div class="card">
        <div class="card-title">📈 Energy Consumption (Last 7 Days)</div>
        <canvas id="consumptionChart" width="400" height="200"></canvas>
    </div>

    <div class="card">
        <div class="card-title">🔮 AI Prediction (Next 7 Days)</div>
        <canvas id="predictionChart" width="400" height="200"></canvas>
    </div>

    <!-- Insights Grid -->
    <div class="card">
        <div class="card-title">🧠 AI Insights</div>
        <div class="grid-2">
            <div class="insight-card">
                <div class="insight-value" id="peakUsage">--</div>
                <div class="insight-label">Peak Daily Usage (kWh)</div>
            </div>
            <div class="insight-card">
                <div class="insight-value" id="peakDay">--</div>
                <div class="insight-label">Peak Day</div>
            </div>
            <div class="insight-card">
                <div class="insight-value" id="avgDaily">--</div>
                <div class="insight-label">Average Daily (kWh)</div>
            </div>
            <div class="insight-card">
                <div class="insight-value" id="estCost">--</div>
                <div class="insight-label">Est. Monthly Cost ($)</div>
            </div>
        </div>
        <div id="aiRecommendation" style="margin-top: 1rem; padding: 0.5rem; background: rgba(16,185,129,0.1); border-radius: 0.5rem; text-align: center;">
            🤖 Loading insights...
        </div>
    </div>
</div>

<script>
    // Unified alert system (for fetch errors)
    function showAlert(message, type = "success") {
        let container = document.getElementById("alertContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "alertContainer";
            container.style.cssText = "position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:10px;";
            document.body.appendChild(container);
        }
        const alertDiv = document.createElement("div");
        alertDiv.className = `alert alert-${type}`;
        alertDiv.innerText = message;
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.style.opacity = "0";
            alertDiv.style.transform = "translateX(100%)";
            setTimeout(() => alertDiv.remove(), 300);
        }, 4000);
    }

    let consumptionChart, predictionChart;

    async function fetchAnalytics() {
        try {
            const response = await fetch('api/analytics_data.php');
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            // Update charts
            const actualDates = data.actual.map(d => d.date);
            const actualKwh = data.actual.map(d => d.kwh);
            const predDates = data.predictions.map(d => d.date);
            const predKwh = data.predictions.map(d => d.kwh);

            if (consumptionChart) consumptionChart.destroy();
            if (predictionChart) predictionChart.destroy();

            consumptionChart = new Chart(document.getElementById('consumptionChart'), {
                type: 'line',
                data: {
                    labels: actualDates,
                    datasets: [{
                        label: 'kWh consumed',
                        data: actualKwh,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { labels: { color: '#8ba8b5' } } },
                    scales: { y: { ticks: { color: '#8ba8b5' } }, x: { ticks: { color: '#8ba8b5' } } }
                }
            });

            predictionChart = new Chart(document.getElementById('predictionChart'), {
                type: 'line',
                data: {
                    labels: predDates,
                    datasets: [{
                        label: 'Predicted kWh (AI)',
                        data: predKwh,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#f59e0b'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { labels: { color: '#8ba8b5' } } },
                    scales: { y: { ticks: { color: '#8ba8b5' } }, x: { ticks: { color: '#8ba8b5' } } }
                }
            });

            // Update insights with explicit IDs
            document.getElementById('peakUsage').innerText = data.insights.peak_usage;
            document.getElementById('peakDay').innerText = data.insights.peak_day;
            document.getElementById('avgDaily').innerText = data.insights.avg_daily;
            document.getElementById('estCost').innerText = '$' + data.insights.estimated_monthly_cost;
            document.getElementById('aiRecommendation').innerHTML = `🤖 ${data.insights.recommendation}`;

        } catch (err) {
            console.error(err);
            showAlert('Failed to load analytics data: ' + err.message, 'error');
        }
    }

    fetchAnalytics();
    // Refresh every 60 seconds (optional)
    setInterval(fetchAnalytics, 60000);
</script>
</body>
</html>