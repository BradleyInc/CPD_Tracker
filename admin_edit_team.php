<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/team_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_manage_teams.php');
    exit();
}

$team_id = intval($_GET['id']);
$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: admin_manage_teams.php');
    exit();
}

$pageTitle = 'Edit Team: ' . $team['name'];
include 'includes/header.php';

// Handle team update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_team'])) {
        $name = trim($_POST['team_name']);
        $description = trim($_POST['team_description']);
        
        if (!empty($name)) {
            if (updateTeam($pdo, $team_id, $name, $description)) {
                $success_message = "Team updated successfully!";
                // Refresh team data
                $team = getTeamById($pdo, $team_id);
            } else {
                $error_message = "Failed to update team.";
            }
        } else {
            $error_message = "Team name is required.";
        }
    }
}

// Get team statistics
$team_stats = getTeamCPDStats($pdo, $team_id);
$recent_entries = getTeamCPDEntries($pdo, $team_id, 10);
?>

<div class="container">
    <div class="admin-header">
        <h1>Team: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderTeamNav($team_id, 'details'); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="team-details-grid">
        <div class="team-info-card">
            <h2>Team Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Team Name:</label>
                    <input type="text" name="team_name" value="<?php echo htmlspecialchars($team['name']); ?>" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="team_description" rows="4" maxlength="500"><?php echo htmlspecialchars($team['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Created By:</label>
                    <p><?php echo htmlspecialchars($team['created_by_name']); ?></p>
                </div>
                <div class="form-group">
                    <label>Created:</label>
                    <p><?php echo date('F j, Y, g:i a', strtotime($team['created_at'])); ?></p>
                </div>
                <div class="form-group">
                    <label>Last Updated:</label>
                    <p><?php echo date('F j, Y, g:i a', strtotime($team['updated_at'])); ?></p>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_team" class="btn">Update Team</button>
                    <a href="admin_manage_teams.php" class="btn btn-secondary">Back to Teams</a>
                </div>
            </form>
        </div>

        <div class="team-stats-card">
            <h2>Team Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Active Members</div>
                    <div class="stat-value"><?php echo $team_stats['active_users'] ?? 0; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Entries</div>
                    <div class="stat-value"><?php echo $team_stats['total_entries'] ?? 0; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Hours</div>
                    <div class="stat-value"><?php echo $team_stats['total_hours'] ?? 0; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Avg Hours/User</div>
                    <div class="stat-value"><?php echo round($team_stats['avg_hours_per_user'] ?? 0, 1); ?></div>
                </div>
            </div>
            
            <div class="quick-links">
                <a href="admin_team_members.php?id=<?php echo $team_id; ?>" class="btn btn-block">Manage Members</a>
                <a href="admin_team_report.php?id=<?php echo $team_id; ?>" class="btn btn-block btn-secondary">View Full Report</a>
            </div>
        </div>
    </div>

    <div class="admin-section">
        <h2>Recent Team CPD Entries</h2>
        <?php if (count($recent_entries) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                        <td><?php echo htmlspecialchars($entry['username']); ?></td>
                        <td><?php echo htmlspecialchars($entry['title']); ?></td>
                        <td><?php echo htmlspecialchars($entry['category']); ?></td>
                        <td><?php echo htmlspecialchars($entry['hours']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="admin_team_report.php?id=<?php echo $team_id; ?>" class="btn btn-secondary">View All Entries</a>
            </div>
        <?php else: ?>
            <p>No CPD entries from team members yet.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .team-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .team-info-card, .team-stats-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .team-info-card h2, .team-stats-card h2 {
        margin-top: 0;
        color: #2c3e50;
        border-bottom: 2px solid #f8f9fa;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 6px;
        text-align: center;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #666;
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .quick-links {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    @media (max-width: 992px) {
        .team-details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>