<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ? AND role = 'collector'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Update last login and status
            $updateStmt = $pdo->prepare("UPDATE user SET last_login = NOW(), status = 'active' WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            header("Location: collectorDashboard.php");
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } catch (PDOException $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Login - E-Waste</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>E-Waste Management System</h1>
        <nav>
            <a href="index.html">Home</a>
        </nav>
    </header>
    <main class="auth-container">
        <h2>Collector Login</h2>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </main>
</body>
</html>