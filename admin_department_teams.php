<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/organisation_functions.php';
require_once 'includes/department_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_manage_organisations.php');
    exit();
}

$dept_id = intval($_GET['id']);
$department = getDepartmentById($pdo, $dept_id);

if (!$department) {
    header('Location: admin_manage_organisations.php');
    exit();
}

$pageTitle = 'Manage Teams: ' . $department['name'];
include 'includes/header.php';

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_team'])) {
        $name = trim($_POST['team_name']);
        $description = trim($_POST['team_description']);
        
        if (!empty($name)) {
            // Create team with department_id
            $stmt = $pdo->prepare("INSERT INTO teams (name, description, created_by, department_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $_SESSION['user_id'], $dept_id])) {
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
    
    // Handle moving team to different department
    if (isset($_POST['move_team'])) {
        $team_id = intval($_POST['team_id']);
        $new_dept_id = intval($_POST['new_department_id']);
        
        $stmt = $pdo->prepare("UPDATE teams SET department_id = ? WHERE id = ?");
        if ($stmt->execute([$new_dept_id, $team_id])) {
            $success_message = "Team moved successfully!";
        } else {
            $error_message = "Failed to move team.";
        }
    }
}

// Get teams in this department
$dept_teams = getDepartmentTeams($pdo, $dept_id);

// Get all departments in same organisation (for moving teams)
$all_departments = getOrganisationDepartments($pdo, $department['organisation_id']);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Teams: <?php echo htmlspecialchars($department['name']); ?></h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">
            <?php echo htmlspecialchars($department['organisation_name']); ?>
        </p>
        <nav class="admin-nav">
            <a href="admin_manage_organisations.php">All Organisations</a>
            <a href="admin_organisation_departments.php?id=<?php echo $department['organisation_id']; ?>">Departments</a>
            <a href="admin_edit_department.php?id=<?php echo $dept_id; ?>">Department Details</a>
            <a href="admin_department_teams.php?id=<?php echo $dept_id; ?>" class="active">Teams</a>
        </nav>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Create Team Form -->
    <div class="admin-section">
        <h2>Create New Team</h2>
        <form method="POST" class="team-form">
            <div class="form-group">
                <label>Team Name:</label>
                <input type="text" name="team_name" required maxlength="100" placeholder="e.g., Tax Team A, Audit Squad">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="team_description" rows="3" maxlength="500" placeholder="Optional description of the team"></textarea>
            </div>
            <button type="submit" name="create_team" class="btn">Create Team</button>
        </form>
    </div>

    <!-- Teams List -->
    <div class="admin-section">
        <h2>Teams in <?php echo htmlspecialchars($department['name']); ?> (<?php echo count($dept_teams); ?>)</h2>
        
        <?php if (count($dept_teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($dept_teams as $team): 
                    $team_stats = getTeamCPDStats($pdo, $team['id']);
                    $team_managers = getTeamManagers($pdo, $team['id']);
                ?>
                    <div class="team-card">
                        <div class="team-card-header">
                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            <span class="team-members"><?php echo $team['member_count']; ?> members</span>
                        </div>
                        
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (count($team_managers) > 0): ?>
                        <div class="team-managers">
                            <strong>Managers:</strong>
                            <ul>
                                <?php foreach ($team_managers as $manager): ?>
                                    <li><?php echo htmlspecialchars($manager['username']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="team-stats">
                            <div class="stat-item">
                                <span class="stat-label">CPD Entries</span>
                                <span class="stat-value"><?php echo $team_stats['total_entries'] ?? 0; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total Hours</span>
                                <span class="stat-value"><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></span>
                            </div>
                        </div>
                        
                        <div class="team-meta">
                            <small>Created by: <?php echo htmlspecialchars($team['created_by_name'] ?? 'Unknown'); ?></small><br>
                            <small>Created: <?php echo date('M d, Y', strtotime($team['created_at'])); ?></small>
                        </div>
                        
                        <div class="team-actions">
                            <a href="admin_edit_team.php?id=<?php echo $team['id']; ?>" class="btn btn-small">Manage</a>
                            <a href="admin_team_members.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-secondary">Members</a>
                            
                            <!-- Move Team Button -->
                            <?php if (count($all_departments) > 1): ?>
                            <button type="button" class="btn btn-small" style="background: #17a2b8;" onclick="showMoveModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name'], ENT_QUOTES); ?>')">Move</button>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($team['name']); ?>? This will remove all team members!');">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" name="delete_team" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No teams have been created in this department yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Move Team Modal -->
<div id="moveTeamModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeMoveModal()">&times;</span>
        <h2>Move Team to Different Department</h2>
        <p id="moveTeamName" style="margin-bottom: 1.5rem;"></p>
        
        <form method="POST">
            <input type="hidden" id="move_team_id" name="team_id">
            
            <div class="form-group">
                <label>Move to Department:</label>
                <select name="new_department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach ($all_departments as $dept): ?>
                        <?php if ($dept['id'] != $dept_id): ?>
                            <option value="<?php echo $dept['id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" name="move_team" class="btn">Move Team</button>
                <button type="button" class="btn btn-secondary" onclick="closeMoveModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    .team-form {
        max-width: 600px;
    }
    
    .teams-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .team-description {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .team-managers {
        background: #fff3cd;
        padding: 0.75rem;
        border-radius: 4px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .team-managers strong {
        color: #856404;
    }
    
    .team-managers ul {
        margin: 0.5rem 0 0 0;
        padding-left: 1.5rem;
    }
    
    .team-managers li {
        color: #333;
    }
    
    .team-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-label {
        display: block;
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        display: block;
        font-size: 1.25rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .team-meta {
        font-size: 0.875rem;
        color: #888;
        margin-bottom: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .team-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 2rem;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        position: relative;
    }
    
    .close {
        position: absolute;
        right: 1.5rem;
        top: 1rem;
        font-size: 1.8rem;
        cursor: pointer;
        color: #666;
        line-height: 1;
    }
    
    .close:hover {
        color: #000;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 1.5rem;
    }
    
    @media (max-width: 768px) {
        .teams-grid {
            grid-template-columns: 1fr;
        }
        
        .team-actions {
            flex-direction: column;
        }
        
        .team-actions button,
        .team-actions a,
        .team-actions form {
            width: 100%;
        }
    }
</style>

<script>
function showMoveModal(teamId, teamName) {
    document.getElementById('move_team_id').value = teamId;
    document.getElementById('moveTeamName').textContent = 'Moving: ' + teamName;
    document.getElementById('moveTeamModal').style.display = 'block';
}

function closeMoveModal() {
    document.getElementById('moveTeamModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('moveTeamModal');
    if (event.target == modal) {
        closeMoveModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
