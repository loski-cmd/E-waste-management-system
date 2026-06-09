<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: userLogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle pickup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_pickup'])) {
    $ewaste_type = $_POST['ewaste_type'];
    $weight_kg = $_POST['weight_kg'];
    $pickup_address = $_POST['pickup_address'];
    $notes = $_POST['notes'];
    $scheduled_date = $_POST['scheduled_date'];

    // Validate scheduled_date is at least one day after now
    $minDate = strtotime('+1 day');
    $scheduledTimestamp = strtotime($scheduled_date);
    if ($scheduledTimestamp < $minDate) {
        $error = "Scheduled date must be at least one day after today.";
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("INSERT INTO pickup (user_id, scheduled_date, ewaste_type, weight_kg, pickup_address, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $scheduled_date, $ewaste_type, $weight_kg, $pickup_address, $notes]);
            
            $success = "Pickup requested successfully!";
        } catch (PDOException $e) {
            $error = "Failed to request pickup: " . $e->getMessage();
        }
    }
}

// Get user's pickup history
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM pickup WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $pickups = $stmt->fetchAll();

    // Get analytics data
    $analyticsStmt = $pdo->prepare("
        SELECT 
            ewaste_type,
            COUNT(*) as count,
            SUM(weight_kg) as total_weight
        FROM pickup
        WHERE user_id = ?
        GROUP BY ewaste_type
    ");
    $analyticsStmt->execute([$user_id]);
    $analytics = $analyticsStmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - E-Waste</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
        <nav>
            <a href="index.html">Home</a>
            <a href="logout.php">Logout</a>
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
            <h2>Request New Pickup</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="ewaste_type">E-Waste Type</label>
                    <select id="ewaste_type" name="ewaste_type" required>
                        <option value="electronics">Electronics</option>
                        <option value="batteries">Batteries</option>
                        <option value="appliances">Appliances</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="weight_kg">Weight (kg)</label>
                    <input type="number" id="weight_kg" name="weight_kg" step="0.01" min="0.1" required>
                </div>
                <div class="form-group">
                    <label for="pickup_address">Pickup Address</label>
                    <textarea id="pickup_address" name="pickup_address" required><?php echo htmlspecialchars($_SESSION['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="scheduled_date">Scheduled Date</label>
                    <input type="datetime-local" id="scheduled_date" name="scheduled_date" required min="<?php echo date('Y-m-d\TH:i', strtotime('+1 day')); ?>">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
                <button type="submit" name="request_pickup">Request Pickup</button>
            </form>
        </section>

        <section class="dashboard-section">
            <h2>Your Pickup History</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Request Date</th>
                            <th>Type</th>
                            <th>Weight (kg)</th>
                            <th>Scheduled Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pickups as $pickup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pickup['created_at']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($pickup['ewaste_type'])); ?></td>
                                <td><?php echo htmlspecialchars($pickup['weight_kg']); ?></td>
                                <td><?php echo htmlspecialchars($pickup['scheduled_date']); ?></td>
                                <td class="status-<?php echo htmlspecialchars($pickup['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($pickup['status'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <h2>Your Waste Analytics</h2>
            <div class="chart-container">
                <canvas id="wasteChart"></canvas>
            </div>
        </section>
    </main>

    <script>
        // Hide success message after 5 seconds
        if (document.getElementById('success-alert')) {
            setTimeout(() => {
                document.getElementById('success-alert').style.display = 'none';
            }, 5000);
        }

        // Chart.js implementation
        const ctx = document.getElementById('wasteChart').getContext('2d');
        const wasteChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($analytics, 'ewaste_type')); ?>,
                datasets: [{
                    label: 'Total Weight (kg)',
                    data: <?php echo json_encode(array_column($analytics, 'total_weight')); ?>,
                    backgroundColor: [
                        '#1f77b4', // Electronics - blue
                        '#ff7f0e', // Batteries - orange
                        '#2ca02c', // Appliances - green
                        '#d62728'  // Other - red
                    ],
                    borderColor: [
                        '#1f77b4',
                        '#ff7f0e',
                        '#2ca02c',
                        '#d62728'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>