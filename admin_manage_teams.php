<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/team_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdminOrSuper()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Manage Teams';
include 'includes/header.php';

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_team'])) {
        $name = trim($_POST['team_name']);
        $description = trim($_POST['team_description']);
        
        if (!empty($name)) {
            if (createTeam($pdo, $name, $description, $_SESSION['user_id'])) {
                $success_message = "Team '$name' created successfully!";
            } else {
                $error_message = "Failed to create team.";
            }
        } else {
            $error_message = "Team name is required.";
        }
    }
    
    // Handle team deletion
    if (isset($_POST['delete_team'])) {
        $team_id = intval($_POST['team_id']);
        if (deleteTeam($pdo, $team_id)) {
            $success_message = "Team deleted successfully!";
        } else {
            $error_message = "Failed to delete team.";
        }
    }
}

// Get all teams
$teams = getAllTeams($pdo);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Teams</h1>
        <?php renderAdminNav('teams'); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Create New Team</h2>
        <form method="POST" class="team-form">
            <div class="form-group">
                <label>Team Name:</label>
                <input type="text" name="team_name" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="team_description" rows="3" maxlength="500"></textarea>
            </div>
            <button type="submit" name="create_team" class="btn">Create Team</button>
        </form>
    </div>

    <div class="admin-section">
        <h2>All Teams (<?php echo count($teams); ?>)</h2>
        
        <?php if (count($teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($teams as $team): ?>
                    <div class="team-card">
                        <div class="team-card-header">
                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            <span class="team-members"><?php echo $team['member_count']; ?> members</span>
                        </div>
                        
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="team-meta">
                            <small>Created by: <?php echo htmlspecialchars($team['created_by_name']); ?></small>
                            <small>Created: <?php echo date('M d, Y', strtotime($team['created_at'])); ?></small>
                        </div>
                        
                        <div class="team-actions">
                            <a href="admin_edit_team.php?id=<?php echo $team['id']; ?>" class="btn btn-small">Manage</a>
                            <a href="admin_team_members.php?id=<?php echo $team['id']; ?>" class="btn btn-small">Members</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team?');">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" name="delete_team" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No teams have been created yet.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .teams-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .team-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .team-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .team-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .team-card h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.25rem;
    }
    
    .team-members {
        background: #e9f7fe;
        color: #007cba;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: bold;
    }
    
    .team-description {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .team-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.875rem;
        color: #888;
        margin-bottom: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .team-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .team-form {
        max-width: 500px;
    }
    
    @media (max-width: 768px) {
        .teams-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>