<?php 
require_once 'includes/database.php';
require_once 'includes/auth.php';

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
    <title>CPD Tracker - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="auth-container">
        <h2>CPD Tracker Login</h2>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            $username = trim(htmlspecialchars($_POST['username']));
            $password = $_POST['password'];
            
            if (empty($username) || empty($password)) {
                echo "<div class='alert alert-error'>Please fill in all fields</div>";
            } else {
                try {
                    // Use prepared statement to prevent SQL injection
                    $stmt = $pdo->prepare("SELECT id, username, password_hash, archived FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        // Check if user is archived
                        if ($user['archived'] == 1) {
                            echo "<div class='alert alert-error'>This account has been archived. Please contact your administrator.</div>";
                        } else {
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_role'] = getUserRole($pdo, $user['id']);
                            $_SESSION['username'] = htmlspecialchars($user['username']);
                            $_SESSION['login_time'] = time();
                            
                            header("Location: dashboard.php");
                            exit();
                        }
                    } else {
                        echo "<div class='alert alert-error'>Invalid username or password</div>";
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    echo "<div class='alert alert-error'>Database error. Please try again later.</div>";
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
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn btn-block">Login</button>
        </form>
        <p class="text-center">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
