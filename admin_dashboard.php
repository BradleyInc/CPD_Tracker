<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

// Get statistics
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$cpd_count = $pdo->query("SELECT COUNT(*) FROM cpd_entries")->fetchColumn();
$team_count = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$avg_team_size = $pdo->query("SELECT AVG(member_count) FROM (SELECT team_id, COUNT(*) as member_count FROM user_teams GROUP BY team_id) as team_sizes")->fetchColumn();
$recent_users = $pdo->query("SELECT username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_teams = $pdo->query("SELECT t.name, t.created_at, u.username as created_by FROM teams t LEFT JOIN users u ON t.created_by = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

$pageTitle = 'Admin Dashboard';
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>Administrator Dashboard</h1>
        <?php renderAdminNav('dashboard'); ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <p class="stat-number"><?php echo $user_count; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Entries</h3>
            <p class="stat-number"><?php echo $cpd_count; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Teams</h3>
            <p class="stat-number"><?php echo $team_count; ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg Team Size</h3>
            <p class="stat-number"><?php echo round($avg_team_size, 1); ?></p>
        </div>
    </div>

    <div class="dashboard-sections">
        <div class="admin-section">
            <h2>Recent User Registrations</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-section">
            <h2>Recent Team Creations</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Team Name</th>
                        <th>Created By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_teams as $team): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($team['name']); ?></td>
                        <td><?php echo htmlspecialchars($team['created_by']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($team['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .dashboard-sections {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    
    @media (max-width: 768px) {
        .dashboard-sections {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>