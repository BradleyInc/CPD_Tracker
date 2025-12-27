<?php 
require_once 'includes/database.php';

if (!isset($_SESSION)) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD Tracker - Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="auth-container">
        <h2>Register for CPD Tracker</h2>
        
        <?php
        // Only process when form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Input validation
            $errors = [];
            
            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                $errors[] = "All fields are required";
            }
            
            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }
            
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            }
            
            // If no validation errors, proceed with registration
            if (empty($errors)) {
                try {
                    // Check if username or email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->fetch()) {
						echo "<div class='alert alert-error'>Username or email already exists</div>";
					} else {
						// Hash password and insert user
						$password_hash = password_hash($password, PASSWORD_DEFAULT);
						$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, 1)");
						
						if ($stmt->execute([$username, $email, $password_hash])) {
							echo "<div class='alert alert-success'>Registration successful! <a href='login.php'>Login here</a></div>";
							
							// Clear form fields
							$username = $email = '';
						} else {
							echo "<div class='alert alert-error'>Registration failed. Please try again.</div>";
						}
}
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    echo "<div class='alert alert-error'>Database error. Please try again later.</div>";
                }
            } else {
                // Display validation errors
                foreach ($errors as $error) {
                    echo "<div class='alert alert-error'>$error</div>";
                }
            }
        }
        ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required minlength="6">
                <small>Must be at least 6 characters</small>
            </div>
            <div class="form-group">
                <label>Confirm Password:</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" name="register" class="btn btn-block">Register</button>
        </form>
        <p class="text-center">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>