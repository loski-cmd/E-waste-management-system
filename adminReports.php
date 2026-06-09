<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminLogin.php");
    exit();
}

function downloadCSV($filename, $header, $rows) {
    // Suppress errors and prevent unwanted output
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $header, ',', '"');
    foreach ($rows as $row) {
        fputcsv($output, $row, ',', '"');
    }
    fclose($output);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Download Users Report
    if (isset($_GET['download']) && $_GET['download'] === 'users') {
        $stmt = $pdo->query("SELECT full_name, email, phone, address FROM user WHERE role = 'user'");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        downloadCSV('users_report.csv', ['Name', 'Email', 'Phone', 'Address'], $rows);
    }
    // Download Collectors Report
    if (isset($_GET['download']) && $_GET['download'] === 'collectors') {
        $stmt = $pdo->query("SELECT full_name, email, phone, address FROM user WHERE role = 'collector'");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        downloadCSV('collectors_report.csv', ['Name', 'Email', 'Phone', 'Address'], $rows);
    }
    // Download Waste Summary Report
    if (isset($_GET['download']) && $_GET['download'] === 'waste') {
        $stmt = $pdo->query("SELECT ewaste_type, SUM(weight_kg) as total_weight FROM pickup GROUP BY ewaste_type");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        downloadCSV('waste_summary_report.csv', ['E-Waste Type', 'Total Weight (kg)'], $rows);
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - E-Waste</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .report-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
            margin: 2rem 0 2rem 0;
        }
        .report-btn {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 1.2rem 2.5rem;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(34,139,34,0.08);
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.2s;
            text-decoration: none;
        }
        .report-btn:hover {
            background: #ffdb4d;
            color: #222;
            transform: translateY(-2px) scale(1.04);
        }
    </style>
</head>
<body>
    <header>
        <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.5rem; color: #228B22;">E-Waste Management System</h1>
        <nav style="display: flex; gap: 1rem;">
            <a href="adminDashboard.php" class="cta-btn">Dashboard</a>
            <a href="adminAnalytics.php" class="cta-btn">Analytics</a>
            <a href="adminReports.php" class="cta-btn">Reports</a>
            <a href="index.html" class="cta-btn">Home</a>
            <a href="logout.php" class="cta-btn" style="background:#dc3545; color:#fff;">Logout</a>
        </nav>
    </header>
    <main class="dashboard">
        <section class="dashboard-section">
            <h2>Download Reports (CSV)</h2>
            <div class="report-btns">
                <a href="?download=users" class="report-btn">Users Report</a>
                <a href="?download=collectors" class="report-btn">Collectors Report</a>
                <a href="?download=waste" class="report-btn">Waste Summary Report</a>
            </div>
            <ul style="max-width:600px;margin:2rem auto 0 auto;line-height:2;">
                <li><strong>Users Report:</strong> List of all users with their phone numbers and addresses.</li>
                <li><strong>Collectors Report:</strong> List of all collectors with their phone numbers and addresses.</li>
                <li><strong>Waste Summary Report:</strong> Total weight of waste collected, grouped by type.</li>
            </ul>
            <?php if (isset($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <p>&copy; 2025 E-Waste Management System. All rights reserved.</p>
    </footer>
</body>
</html> 