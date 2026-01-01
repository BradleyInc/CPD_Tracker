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

// Check if admin can access this team's organisation (if it belongs to a department)
if ($team['department_id']) {
    $stmt = $pdo->prepare("SELECT organisation_id FROM departments WHERE id = ?");
    $stmt->execute([$team['department_id']]);
    $dept_org_id = $stmt->fetchColumn();
    
    if ($dept_org_id) {
        require_once 'includes/admin_functions.php';
        if (!canAdminAccessOrganisation($pdo, $_SESSION['user_id'], $dept_org_id)) {
            header('Location: admin_manage_teams.php');
            exit();
        }
    }
}

$pageTitle = 'Team Members: ' . $team['name'];
include 'includes/header.php';

// Handle adding/removing members
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $user_id = intval($_POST['user_id']);
        if (addUserToTeam($pdo, $user_id, $team_id)) {
            $success_message = "User added to team successfully!";
        } else {
            $error_message = "User is already in the team.";
        }
    }
    
    if (isset($_POST['remove_member'])) {
        $user_id = intval($_POST['user_id']);
        if (removeUserFromTeam($pdo, $user_id, $team_id)) {
            $success_message = "User removed from team successfully!";
        } else {
            $error_message = "Failed to remove user from team.";
        }
    }
}

// Get team members and available users
$team_members = getTeamMembers($pdo, $team_id);
$available_users = getUsersNotInTeam($pdo, $team_id);
?>

<div class="container">
    <div class="admin-header">
        <h1>Team Members: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderTeamNav($team_id, 'members'); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Add Member to Team</h2>
        <?php if (count($available_users) > 0): ?>
            <form method="POST" class="add-member-form">
                <div class="form-group">
                    <label>Select User:</label>
                    <select name="user_id" required>
                        <option value="">-- Select a user --</option>
                        <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_member" class="btn">Add to Team</button>
            </form>
        <?php else: ?>
            <p>All users are already in this team.</p>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h2>Team Members (<?php echo count($team_members); ?>)</h2>
        
        <?php if (count($team_members) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Joined Team</th>
                        <th>Member Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                    <tr>
                        <td>
                            <a href="admin_edit_user.php?id=<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['username']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?php echo htmlspecialchars($member['username']); ?> from team?');">
                                <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" name="remove_member" class="btn btn-small btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No members in this team yet.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .add-member-form {
        max-width: 500px;
    }
    
    .add-member-form select {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 4px;
        font-size: 1rem;
    }
</style>

<?php include 'includes/footer.php'; ?>