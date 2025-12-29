<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

// Check authentication and manager role
checkAuth();
if (!isManager()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Manager Dashboard';
include 'includes/header.php';

// Get teams managed by this manager
$managed_teams = getManagerTeams($pdo, $_SESSION['user_id']);

// Calculate overall statistics
$total_team_members = 0;
$total_cpd_entries = 0;
$total_cpd_hours = 0;

foreach ($managed_teams as $team) {
    $total_team_members += $team['member_count'];
    $stats = getTeamCPDStats($pdo, $team['id']);
    $total_cpd_entries += $stats['total_entries'] ?? 0;
    $total_cpd_hours += $stats['total_hours'] ?? 0;
}
?>

<div class="container">
    <div class="admin-header">
        <h1>Manager Dashboard</h1>
        <?php renderManagerNav($team['id'],'dashboard'); ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Teams Managed</h3>
            <p class="stat-number"><?php echo count($managed_teams); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Team Members</h3>
            <p class="stat-number"><?php echo $total_team_members; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Entries</h3>
            <p class="stat-number"><?php echo $total_cpd_entries; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Hours</h3>
            <p class="stat-number"><?php echo round($total_cpd_hours, 1); ?></p>
        </div>
    </div>

    <div class="admin-section">
        <h2>My Teams</h2>
        
        <?php if (count($managed_teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($managed_teams as $team): 
                    $team_stats = getTeamCPDStats($pdo, $team['id']);
                ?>
                    <div class="team-card">
                        <div class="team-card-header">
                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            <span class="team-members"><?php echo $team['member_count']; ?> members</span>
                        </div>
                        
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="team-stats-mini">
                            <div class="stat-mini">
                                <strong><?php echo $team_stats['total_entries'] ?? 0; ?></strong>
                                <span>CPD Entries</span>
                            </div>
                            <div class="stat-mini">
                                <strong><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></strong>
                                <span>Total Hours</span>
                            </div>
                        </div>
                        
                        <div class="team-actions">
                            <a href="manager_team_view.php?id=<?php echo $team['id']; ?>" class="btn btn-small">View Team</a>
                            <a href="manager_team_members.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-secondary">View Members</a>
							<a href="manager_team_manage_users.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-secondary">Manage Users</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>You are not currently assigned to manage any teams. Please contact your administrator.</p>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" class="btn btn-secondary">Back to My CPD</a>
    </div>
</div>

<style>
    .team-stats-mini {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .stat-mini {
        text-align: center;
    }
    
    .stat-mini strong {
        display: block;
        font-size: 1.5rem;
        color: #007cba;
        margin-bottom: 0.25rem;
    }
    
    .stat-mini span {
        display: block;
        font-size: 0.875rem;
        color: #666;
    }
</style>

<?php include 'includes/footer.php'; ?>
