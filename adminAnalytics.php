<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminLogin.php");
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get analytics data
    $analyticsStmt = $pdo->query("
        SELECT 
            ewaste_type,
            COUNT(*) as count,
            SUM(weight_kg) as total_weight,
            DATE_FORMAT(created_at, '%Y-%m') as month
        FROM pickup
        GROUP BY ewaste_type, month
        ORDER BY month, ewaste_type
    ");
    $analytics = $analyticsStmt->fetchAll();

    // Prepare data for charts
    $months = [];
    $types = ['electronics', 'batteries', 'appliances', 'other'];
    $chartData = [];

    foreach ($analytics as $record) {
        if (!in_array($record['month'], $months)) {
            $months[] = $record['month'];
        }
    }

    foreach ($types as $type) {
        $typeData = [];
        foreach ($months as $month) {
            $found = false;
            foreach ($analytics as $record) {
                if ($record['month'] === $month && $record['ewaste_type'] === $type) {
                    $typeData[] = $record['total_weight'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $typeData[] = 0;
            }
        }
        $chartData[$type] = $typeData;
    }

    // Get monthly pickups and weight for trends
    $monthlyTrendsStmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as pickups, SUM(weight_kg) as total_weight
        FROM pickup
        GROUP BY month
        ORDER BY month
    ");
    $monthlyTrends = $monthlyTrendsStmt->fetchAll();
    $trendMonths = array_column($monthlyTrends, 'month');
    $trendPickups = array_column($monthlyTrends, 'pickups');
    $trendWeights = array_column($monthlyTrends, 'total_weight');

    // CO2 savings estimate (assume 1kg e-waste = 1.5kg CO2 saved)
    $co2Stmt = $pdo->query("SELECT SUM(weight_kg) as total_weight FROM pickup");
    $totalWeight = $co2Stmt->fetchColumn();
    $co2Saved = $totalWeight ? round($totalWeight * 1.5, 2) : 0;
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics - E-Waste</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> (Admin)</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="logout.php" onclick="return confirmLogout()">Logout</a>
        </nav>
    </header>
    <main class="dashboard">
        <div style="display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem;">
            <a href="adminDashboard.php" class="big-dashboard-btn">Pickup Tables</a>
            <a href="adminAnalytics.php" class="big-dashboard-btn">Analytics Dashboard</a>
            <a href="adminReports.php" class="big-dashboard-btn">Reports</a>
        </div>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        <section class="dashboard-section">
            <h2>Analytics Dashboard</h2>
            <div class="chart-container">
                <canvas id="monthlyWasteChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="wasteTypeChart"></canvas>
            </div>
        </section>
        <section class="dashboard-section">
            <h2>Monthly Trends</h2>
            <div class="chart-container">
                <canvas id="monthlyTrendsChart"></canvas>
            </div>
        </section>
        <section class="dashboard-section">
            <h2>Estimated Environmental Impact</h2>
            <div style="font-size:1.3rem; color:#228B22; margin-bottom:1rem;">
                <strong>Total CO₂ Saved:</strong> <?php echo number_format($co2Saved, 2); ?> kg
            </div>
            <div style="color:#555;">(Based on 1kg e-waste = 1.5kg CO₂ saved)</div>
        </section>
    </main>
    <script>
        // Monthly Waste Chart
        const monthlyCtx = document.getElementById('monthlyWasteChart').getContext('2d');
        const monthlyWasteChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Electronics',
                        data: <?php echo json_encode($chartData['electronics']); ?>,
                        borderColor: 'rgba(34, 139, 34, 1)',
                        backgroundColor: 'rgba(34, 139, 34, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Batteries',
                        data: <?php echo json_encode($chartData['batteries']); ?>,
                        borderColor: 'rgba(0, 100, 0, 1)',
                        backgroundColor: 'rgba(0, 100, 0, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Appliances',
                        data: <?php echo json_encode($chartData['appliances']); ?>,
                        borderColor: 'rgba(50, 205, 50, 1)',
                        backgroundColor: 'rgba(50, 205, 50, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Other',
                        data: <?php echo json_encode($chartData['other']); ?>,
                        borderColor: 'rgba(152, 251, 152, 1)',
                        backgroundColor: 'rgba(152, 251, 152, 0.1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Waste Collection (kg)'
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Waste Type Pie Chart
        const typeCtx = document.getElementById('wasteTypeChart').getContext('2d');
        const wasteTypeChart = new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: ['Electronics', 'Batteries', 'Appliances', 'Other'],
                datasets: [{
                    data: [
                        <?php echo array_sum($chartData['electronics']); ?>,
                        <?php echo array_sum($chartData['batteries']); ?>,
                        <?php echo array_sum($chartData['appliances']); ?>,
                        <?php echo array_sum($chartData['other']); ?>
                    ],
                    backgroundColor: [
                        'rgba(34, 139, 34, 0.7)',
                        'rgba(0, 100, 0, 0.7)',
                        'rgba(50, 205, 50, 0.7)',
                        'rgba(152, 251, 152, 0.7)'
                    ],
                    borderColor: [
                        'rgba(34, 139, 34, 1)',
                        'rgba(0, 100, 0, 1)',
                        'rgba(50, 205, 50, 1)',
                        'rgba(152, 251, 152, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Total Waste by Type (kg)'
                    },
                }
            }
        });

        // Monthly Trends Chart
        const trendsCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        const monthlyTrendsChart = new Chart(trendsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($trendMonths); ?>,
                datasets: [
                    {
                        label: 'Total Pickups',
                        data: <?php echo json_encode($trendPickups); ?>,
                        backgroundColor: 'rgba(34, 139, 34, 0.7)',
                        borderColor: 'rgba(34, 139, 34, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Weight (kg)',
                        data: <?php echo json_encode($trendWeights); ?>,
                        backgroundColor: 'rgba(50, 205, 50, 0.7)',
                        borderColor: 'rgba(50, 205, 50, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Pickups and Weight'
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
    <script src="script.js"></script>
</body>
</html> 