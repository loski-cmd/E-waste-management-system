<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'collector') {
    header("Location: collectorLogin.php");
    exit();
}

$collector_id = $_SESSION['user_id'];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle pickup completion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_pickup'])) {
        $pickup_id = $_POST['pickup_id'];
        $actual_weight = $_POST['actual_weight'];
        $notes = $_POST['notes'];
        
        $stmt = $pdo->prepare("
            UPDATE pickup 
            SET 
                weight_kg = ?,
                notes = CONCAT(IFNULL(notes, ''), ?),
                status = 'completed',
                completed_at = NOW()
            WHERE pickup_id = ?
        ");
        $stmt->execute([$actual_weight, "\nCollector Notes: " . $notes, $pickup_id]);
        $success = "Pickup marked as completed!";
    }

    // Get assigned pickups
    $assignedStmt = $pdo->prepare("
        SELECT p.*, u.full_name as user_name, u.address as user_address, u.phone as user_phone
        FROM pickup p
        JOIN user u ON p.user_id = u.user_id
        WHERE p.collector_id = ? AND p.status = 'assigned'
        ORDER BY p.scheduled_date ASC
    ");
    $assignedStmt->execute([$collector_id]);
    $assignedPickups = $assignedStmt->fetchAll();

    // Get completed pickups
    $completedStmt = $pdo->prepare("
        SELECT p.*, u.full_name as user_name
        FROM pickup p
        JOIN user u ON p.user_id = u.user_id
        WHERE p.collector_id = ? AND p.status = 'completed'
        ORDER BY p.completed_at DESC
    ");
    $completedStmt->execute([$collector_id]);
    $completedPickups = $completedStmt->fetchAll();

    // Get analytics data
    $analyticsStmt = $pdo->prepare("
        SELECT 
            ewaste_type,
            COUNT(*) as count,
            SUM(weight_kg) as total_weight,
            DATE_FORMAT(completed_at, '%Y-%m') as month
        FROM pickup
        WHERE collector_id = ?
        GROUP BY ewaste_type, month
        ORDER BY month, ewaste_type
    ");
    $analyticsStmt->execute([$collector_id]);
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
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Dashboard - E-Waste</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?> (Collector)</h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="logout.php" onclick="return confirmLogout()">Logout</a>
        </nav>
    </header>
    <main class="dashboard">
        <?php if (isset($success)): ?>
            <div class="alert success" id="success-alert">
                <?php echo $success; ?>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <section class="dashboard-section">
            <h2>Assigned Pickups</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Estimated Weight (kg)</th>
                            <th>Address</th>
                            <th>Phone</th>
                            <th>Scheduled Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignedPickups as $pickup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pickup['pickup_id']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_address']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_phone']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td>
                                    <button onclick="document.getElementById('complete-modal-<?php echo $pickup['pickup_id']; ?>').showModal()" class="small-button">
                                        Complete
                                    </button>
                                    
                                    <dialog id="complete-modal-<?php echo $pickup['pickup_id']; ?>" class="modal">
                                        <h3>Complete Pickup #<?php echo $pickup['pickup_id']; ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="pickup_id" value="<?php echo $pickup['pickup_id']; ?>">
                                            <div class="form-group">
                                                <label for="actual_weight">Actual Weight (kg)</label>
                                                <input type="number" id="actual_weight" name="actual_weight" step="0.01" min="0.1" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="notes">Notes</label>
                                                <textarea id="notes" name="notes"></textarea>
                                            </div>
                                            <div class="form-actions">
                                                <button type="button" onclick="this.closest('dialog').close()" class="small-button cancel">Cancel</button>
                                                <button type="submit" name="complete_pickup" class="small-button">Submit</button>
                                            </div>
                                        </form>
                                    </dialog>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>Completed Pickups</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Weight (kg)</th>
                            <th>Scheduled Date</th>
                            <th>Completed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedPickups as $pickup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pickup['pickup_id']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['completed_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>Your Collection Analytics</h2>
            <div class="chart-container">
                <canvas id="collectionChart"></canvas>
            </div>
        </section>
    </main>

    <script>
        // Collection Chart
        const collectionCtx = document.getElementById('collectionChart').getContext('2d');
        const collectionChart = new Chart(collectionCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Electronics',
                        data: <?php echo json_encode($chartData['electronics']); ?>,
                        backgroundColor: 'rgba(34, 139, 34, 0.7)',
                        borderColor: 'rgba(34, 139, 34, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Batteries',
                        data: <?php echo json_encode($chartData['batteries']); ?>,
                        backgroundColor: 'rgba(0, 100, 0, 0.7)',
                        borderColor: 'rgba(0, 100, 0, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Appliances',
                        data: <?php echo json_encode($chartData['appliances']); ?>,
                        backgroundColor: 'rgba(50, 205, 50, 0.7)',
                        borderColor: 'rgba(50, 205, 50, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Other',
                        data: <?php echo json_encode($chartData['other']); ?>,
                        backgroundColor: 'rgba(152, 251, 152, 0.7)',
                        borderColor: 'rgba(152, 251, 152, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Collection by Waste Type (kg)'
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