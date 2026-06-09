<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminLogin.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get analytics data
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $totalPickups = $pdo->query("SELECT COUNT(*) FROM pickup")->fetchColumn();
    $totalWeight = $pdo->query("SELECT SUM(weight_kg) FROM pickup WHERE status = 'completed'")->fetchColumn() ?? 0;

    // Get waste by type
    $wasteByType = $pdo->query("
        SELECT ewaste_type, SUM(weight_kg) as total_weight, COUNT(*) as count
        FROM pickup
        WHERE status = 'completed'
        GROUP BY ewaste_type
    ")->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - E-Waste Management</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>E-Waste Kenya</h1>
            <nav>
                <ul>
                    <li><a href="../index.html">Home</a></li>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>System Analytics</h2>
        
        <section class="dashboard-section">
            <h3>Key Metrics</h3>
            <div class="metrics-grid">
                <div class="metric-card">
                    <h4>Total Users</h4>
                    <p class="metric-value"><?php echo $totalUsers; ?></p>
                </div>
                <div class="metric-card">
                    <h4>Total Pickups</h4>
                    <p class="metric-value"><?php echo $totalPickups; ?></p>
                </div>
                <div class="metric-card">
                    <h4>Total Waste Collected</h4>
                    <p class="metric-value"><?php echo number_format($totalWeight, 2); ?> kg</p>
                </div>
            </div>
        </section>
        
        <section class="dashboard-section">
            <h3>Waste Composition</h3>
            <div class="chart-container">
                <div class="pie-chart">
                    <?php foreach ($wasteByType as $type): ?>
                        <div class="pie-segment" 
                             style="--percentage: <?php echo ($totalWeight > 0) ? ($type['total_weight']/$totalWeight)*100 : 0; ?>%; 
                                    --color: var(--<?php echo $type['ewaste_type']; ?>-color);">
                            <span><?php echo ucfirst($type['ewaste_type']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-legend">
                    <?php foreach ($wasteByType as $type): ?>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: var(--<?php echo $type['ewaste_type']; ?>-color);"></span>
                            <span>
                                <?php echo ucfirst($type['ewaste_type']); ?>: 
                                <?php echo number_format($type['total_weight'], 2); ?> kg
                                (<?php echo $type['count']; ?> pickups)
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 E-Waste Kenya. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>