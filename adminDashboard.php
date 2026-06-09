<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminLogin.php");
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle pickup assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_pickup'])) {
        $pickup_id = $_POST['pickup_id'];
        $collector_id = $_POST['collector_id'];
        
        $stmt = $pdo->prepare("UPDATE pickup SET collector_id = ?, status = 'assigned' WHERE pickup_id = ?");
        $stmt->execute([$collector_id, $pickup_id]);
        $success = "Pickup assigned successfully!";
    }

    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $pickup_id = $_POST['pickup_id'];
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE pickup SET status = ? WHERE pickup_id = ?");
        $stmt->execute([$status, $pickup_id]);
        $success = "Status updated successfully!";
    }

    // Get all pickups
    $pickupsStmt = $pdo->query("
        SELECT p.*, u.full_name as user_name, c.full_name as collector_name
        FROM pickup p
        LEFT JOIN user u ON p.user_id = u.user_id
        LEFT JOIN user c ON p.collector_id = c.user_id
        ORDER BY p.created_at DESC
    ");
    $allPickups = $pickupsStmt->fetchAll();

    // Get pending pickups
    $pendingStmt = $pdo->query("
        SELECT p.*, u.full_name as user_name
        FROM pickup p
        JOIN user u ON p.user_id = u.user_id
        WHERE p.status = 'pending'
        ORDER BY p.created_at DESC
    ");
    $pendingPickups = $pendingStmt->fetchAll();

    // Get completed pickups
    $completedStmt = $pdo->query("
        SELECT p.*, u.full_name as user_name, c.full_name as collector_name
        FROM pickup p
        LEFT JOIN user u ON p.user_id = u.user_id
        LEFT JOIN user c ON p.collector_id = c.user_id
        WHERE p.status = 'completed'
        ORDER BY p.completed_at DESC
    ");
    $completedPickups = $completedStmt->fetchAll();

    // Get all collectors
    $collectorsStmt = $pdo->query("SELECT * FROM user WHERE role = 'collector' AND status = 'active'");
    $collectors = $collectorsStmt->fetchAll();

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
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Waste</title>
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
        <?php if (isset($success)): ?>
            <div class="alert success" id="success-alert">
                <?php echo $success; ?>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <section class="dashboard-section">
            <h2>Pending Pickup Requests</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Weight (kg)</th>
                            <th>Scheduled Date</th>
                            <th>Request Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPickups as $pickup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pickup['pickup_id']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['created_at']); ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="pickup_id" value="<?php echo $pickup['pickup_id']; ?>">
                                        <select name="collector_id" required>
                                            <option value="">Select Collector</option>
                                            <?php foreach ($collectors as $collector): ?>
                                                <option value="<?php echo $collector['user_id']; ?>">
                                                    <?php echo htmlspecialchars($collector['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_pickup" class="small-button">Assign</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>All Pickups</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Collector</th>
                            <th>Type</th>
                            <th>Weight (kg)</th>
                            <th>Status</th>
                            <th>Scheduled Date</th>
                            <th>Request Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPickups as $pickup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pickup['pickup_id']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['collector_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td class="status-<?php echo htmlspecialchars($pickup['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($pickup['status'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['created_at']); ?></td>
                                <td>
                                    <?php if ($pickup['status'] !== 'completed' && $pickup['status'] !== 'cancelled'): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="pickup_id" value="<?php echo $pickup['pickup_id']; ?>">
                                            <select name="status" required>
                                                <option value="">Update Status</option>
                                                <option value="assigned">Assigned</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="small-button">Update</button>
                                        </form>
                                    <?php endif; ?>
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
                            <th>Collector</th>
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
                                <td><?php echo htmlspecialchars($pickup['collector_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['completed_at'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- User Lookup Form and Results -->
        <section class="dashboard-section">
            <h2>Lookup User Details & History</h2>
            <form method="GET" style="margin-bottom: 1rem;">
                <div class="form-group" style="max-width: 400px;">
                    <label for="lookup_name">Enter User Name</label>
                    <input type="text" id="lookup_name" name="lookup_name" placeholder="Full or partial name" value="<?php echo isset($_GET['lookup_name']) ? htmlspecialchars($_GET['lookup_name']) : ''; ?>" required>
                </div>
                <button type="submit">Search</button>
            </form>
            <?php
            if (isset($_GET['lookup_name']) && trim($_GET['lookup_name']) !== '') {
                $searchName = '%' . trim($_GET['lookup_name']) . '%';
                try {
                    $userStmt = $pdo->prepare("SELECT * FROM user WHERE full_name LIKE ? AND role = 'user' LIMIT 1");
                    $userStmt->execute([$searchName]);
                    $foundUser = $userStmt->fetch();
                    if ($foundUser) {
                        echo '<div class="card" style="margin-bottom:1rem; padding:1rem; background:#f9f9f9; border-radius:8px;">';
                        echo '<strong>Name:</strong> ' . htmlspecialchars($foundUser['full_name']) . '<br>';
                        echo '<strong>Email:</strong> ' . htmlspecialchars($foundUser['email']) . '<br>';
                        echo '<strong>Address:</strong> ' . htmlspecialchars($foundUser['address']) . '<br>';
                        echo '<strong>Phone:</strong> ' . htmlspecialchars($foundUser['phone']) . '<br>';
                        echo '</div>';
                        // Pickup history
                        $pickupStmt = $pdo->prepare("SELECT * FROM pickup WHERE user_id = ? ORDER BY created_at DESC");
                        $pickupStmt->execute([$foundUser['user_id']]);
                        $userPickups = $pickupStmt->fetchAll();
                        if ($userPickups) {
                            echo '<h3>Pickup History</h3>';
                            echo '<div class="table-container"><table><thead><tr><th>Request Date</th><th>Type</th><th>Weight (kg)</th><th>Scheduled Date</th><th>Status</th></tr></thead><tbody>';
                            foreach ($userPickups as $pickup) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($pickup['created_at']) . '</td>';
                                echo '<td>' . htmlspecialchars(ucfirst($pickup['ewaste_type'])) . '</td>';
                                echo '<td>' . htmlspecialchars($pickup['weight_kg']) . '</td>';
                                echo '<td>' . htmlspecialchars($pickup['scheduled_date']) . '</td>';
                                echo '<td class="status-' . htmlspecialchars($pickup['status']) . '">' . htmlspecialchars(ucfirst($pickup['status'])) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table></div>';
                        } else {
                            echo '<div class="alert">No pickup history found for this user.</div>';
                        }
                    } else {
                        echo '<div class="alert error">No user found with that name.</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>
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
    </script>
    <script src="script.js"></script>
</body>
</html>