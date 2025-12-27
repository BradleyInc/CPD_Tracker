<?php
// Session security settings - MUST be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Only enable if using HTTPS - comment out for local development
ini_set('session.cookie_samesite', 'Strict');

session_start();

function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        // MySQL connection settings for XAMPP
        $host = "localhost";
        $dbname = "cpd_tracker";
        $username = "root";       // XAMPP default user
        $password = "";           // XAMPP default password (empty)
        
        // If you created a dedicated user, use:
        // $username = "cpd_user";
        // $password = "a_strong_password_here";
        
        try {
            // Change from "pgsql:" to "mysql:"
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

$pdo = getDatabaseConnection();

// Session timeout (30 minutes)
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > $session_timeout) {
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    // Update login time on activity
    $_SESSION['login_time'] = time();
}
?>