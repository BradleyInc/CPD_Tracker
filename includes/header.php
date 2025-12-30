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
                    <a href="dashboard.php" class="btn btn-secondary">My CPD</a>
					<a href="user_goals.php" class="btn btn-secondary">Goals</a>
                    
                    <?php if (isset($_SESSION['user_role'])): ?>
                        <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                            <a href="system_admin_dashboard.php" class="btn btn-secondary">🚀 System Admin</a>
                        <?php endif; ?>
                        <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
                            <a href="admin_dashboard.php" class="btn btn-secondary">Admin Panel</a>
                        <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                            <a href="manager_dashboard.php" class="btn btn-secondary">Manager Dashboard</a>
                        <?php elseif ($_SESSION['user_role'] === 'partner'): ?>
                            <a href="partner_dashboard.php" class="btn btn-secondary">Partner Dashboard</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="container">
