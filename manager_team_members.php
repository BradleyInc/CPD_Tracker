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

if (!isset($_GET['id'])) {
    header('Location: manager_dashboard.php');
    exit();
}

$team_id = intval($_GET['id']);

// Check if manager has access to this team
if (!isManagerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: manager_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: manager_dashboard.php');
    exit();
}

// Get team members
$team_members = getTeamMembers($pdo, $team_id);

$pageTitle = 'Team Members: ' . $team['name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>Team Members: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderManagerNav($team_id, 'members'); ?>
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
                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                        <td>
                            <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $member['id']; ?>" 
                               class="btn btn-small">View CPD</a>
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

<?php include 'includes/footer.php'; ?>
