<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/user_management_functions.php';

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

$pageTitle = 'Manage Users: ' . $team['name'];
include 'includes/header.php';

// Handle archive user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Check if partner can archive this user
    if (canPartnerArchiveUser($pdo, $_SESSION['user_id'], $user_id)) {
        if (archiveUser($pdo, $user_id, $_SESSION['user_id'])) {
            $message = '<div class="alert alert-success">User archived successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to archive user.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">You do not have permission to archive this user.</div>';
    }
}

// Handle unarchive user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Check if partner can unarchive this user
    if (canPartnerArchiveUser($pdo, $_SESSION['user_id'], $user_id)) {
        if (unarchiveUser($pdo, $user_id)) {
            $message = '<div class="alert alert-success">User unarchived successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to unarchive user.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">You do not have permission to unarchive this user.</div>';
    }
}

// Get team members and managers (active and archived)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at, u.archived,
           r.name as role_name,
           ut.joined_at as member_joined_at,
           tm.assigned_at as manager_assigned_at
    FROM users u
    LEFT JOIN user_teams ut ON u.id = ut.user_id AND ut.team_id = ?
    LEFT JOIN team_managers tm ON u.id = tm.manager_id AND tm.team_id = ?
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE (ut.user_id IS NOT NULL OR tm.manager_id IS NOT NULL)
    ORDER BY u.archived ASC, r.name DESC, u.username
");
$stmt->execute([$team_id, $team_id]);
$team_users = $stmt->fetchAll();
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Users: <?php echo htmlspecialchars($team['name']); ?></h1>
        <nav class="admin-nav">
            <a href="partner_dashboard.php">My Teams</a>
            <a href="partner_team_view.php?id=<?php echo $team_id; ?>">Team Overview</a>
            <a href="partner_team_members.php?id=<?php echo $team_id; ?>">View Members</a>
            <a href="partner_team_manage_users.php?id=<?php echo $team_id; ?>" class="active">Manage Users</a>
            <a href="dashboard.php">My CPD</a>
        </nav>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="admin-section">
        <h2>Team Users (<?php echo count($team_users); ?>)</h2>
        <p><em>As a partner, you can archive team members and managers (but not other partners or admins).</em></p>
        
        <?php if (count($team_users) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined/Assigned</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_users as $user): ?>
                    <tr <?php echo $user['archived'] ? 'style="background: #fff3cd;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <strong><?php echo ucfirst($user['role_name']); ?></strong>
                        </td>
                        <td>
                            <?php 
                            $date = $user['manager_assigned_at'] ?? $user['member_joined_at'];
                            echo $date ? date('M d, Y', strtotime($date)) : 'N/A';
                            ?>
                        </td>
                        <td>
                            <?php if ($user['archived']): ?>
                                <span style="color: #856404; font-weight: bold;">Archived</span>
                            <?php else: ?>
                                <span style="color: #28a745; font-weight: bold;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $user['id']; ?>" 
                               class="btn btn-small">View CPD</a>
                            
                            <?php if (in_array($user['role_name'], ['user', 'manager'])): ?>
                                <?php if (!$user['archived']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Archive <?php echo htmlspecialchars($user['username']); ?>? They will not be able to log in.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="archive_user" class="btn btn-small" style="background: #ffc107;">Archive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Unarchive <?php echo htmlspecialchars($user['username']); ?>? They will be able to log in again.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="unarchive_user" class="btn btn-small" style="background: #28a745;">Unarchive</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999; font-size: 0.875rem;">Cannot archive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No users in this team yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
