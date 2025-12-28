<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

// Check authentication and partner role
checkAuth();
if (!isPartner()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: partner_dashboard.php');
    exit();
}

$team_id = intval($_GET['id']);

// Check if partner has access to this team
if (!isPartnerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: partner_dashboard.php');
    exit();
}

// Get team members and managers
$team_members = getTeamMembers($pdo, $team_id);
$team_managers = getTeamManagers($pdo, $team_id);

$pageTitle = 'Team Members: ' . $team['name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>Team Members: <?php echo htmlspecialchars($team['name']); ?></h1>
        <nav class="admin-nav">
            <a href="partner_dashboard.php">My Teams</a>
            <a href="partner_team_view.php?id=<?php echo $team_id; ?>">Team Overview</a>
            <a href="partner_team_members.php?id=<?php echo $team_id; ?>" class="active">Team Members</a>
            <a href="dashboard.php">My CPD</a>
        </nav>
    </div>

    <?php if (count($team_managers) > 0): ?>
    <div class="admin-section">
        <h2>Team Managers (<?php echo count($team_managers); ?>)</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Assigned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team_managers as $manager): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($manager['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($manager['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($manager['assigned_at'])); ?></td>
                    <td>
                        <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $manager['id']; ?>" 
                           class="btn btn-small">View CPD</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

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
