<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

if (!isset($_SESSION)) {
    session_start();
}

// Only allow this if no admin exists yet (for initial setup)
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2");
$admin_count = $stmt->fetchColumn();

if ($admin_count > 0) {
    die("Admin user already exists. Use Method 1 or 3 instead.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Create admin user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, 2)");
        
        if ($stmt->execute([$username, $email, $password_hash])) {
            $success = "Admin user created successfully! <a href='login.php'>Login here</a>";
        } else {
            $error = "Failed to create admin user";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create First Admin User</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Create First Admin User</h2>
        
        <?php if (isset($error)): ?>
            <div class='alert alert-error'><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class='alert alert-success'><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-block">Create Admin User</button>
        </form>
        <p class="text-center"><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>