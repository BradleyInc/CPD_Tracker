<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD Tracker - <?php echo $pageTitle ?? 'Professional Development'; ?></title>
    <link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="dashboard.php">CPD Tracker</a>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                <nav class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </nav>
                <?php endif; ?>
				<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <a href="admin_dashboard.php" class="btn btn-secondary">Admin Panel</a>
<?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="container">