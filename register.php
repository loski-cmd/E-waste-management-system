<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $vehicle_type = $_POST['vehicle_type'] ?? null;
    $license_number = $_POST['license_number'] ?? null;

    try {
        $pdo = new PDO('mysql:host=localhost;dbname=ewaste', 'root', 'loski');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Set status to 'active' for collectors, otherwise NULL
        $status = ($role === 'collector') ? 'active' : null;
        $stmt = $pdo->prepare("INSERT INTO user (email, password_hash, full_name, address, phone, role, vehicle_type, license_number, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $password_hash, $full_name, $address, $phone, $role, $vehicle_type, $license_number, $status]);
        
        $success = "Successfully registered!";
        $redirect = $role . "Login.php";
    } catch (PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - E-Waste</title>
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
        <h2>Register</h2>
        <?php if (isset($success)): ?>
            <div class="alert success">
                <?php echo $success; ?>
                <script>
                    setTimeout(() => {
                        window.location.href = '<?php echo $redirect; ?>';
                    }, 2000);
                </script>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" required></textarea>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="collector">Collector</option>
                </select>
            </div>
            <div id="collector-fields" style="display: none;">
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <input type="text" id="vehicle_type" name="vehicle_type">
                </div>
                <div class="form-group">
                    <label for="license_number">License Number</label>
                    <input type="text" id="license_number" name="license_number">
                </div>
            </div>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="index.html">Login</a></p>
    </main>
    <script>
        document.getElementById('role').addEventListener('change', function() {
            const collectorFields = document.getElementById('collector-fields');
            collectorFields.style.display = this.value === 'collector' ? 'block' : 'none';
        });
    </script>
</body>
</html>