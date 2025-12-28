<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/user_management_functions.php';

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

$pageTitle = 'Manage Users: ' . $team['name'];
include 'includes/header.php';

// Handle archive user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Check if manager can archive this user
    if (canManagerArchiveUser($pdo, $_SESSION['user_id'], $user_id)) {
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
    
    // Check if manager can unarchive this user (same rules as archive)
    if (canManagerArchiveUser($pdo, $_SESSION['user_id'], $user_id)) {
        if (unarchiveUser($pdo, $user_id)) {
            $message = '<div class="alert alert-success">User unarchived successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to unarchive user.</div>';
        }
    } else {
        $message = '<div class="alert alert-error">You do not have permission to unarchive this user.</div>';
    }
}

// Get team members (active and archived)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at, u.archived, ut.joined_at,
           r.name as role_name
    FROM users u
    JOIN user_teams ut ON u.id = ut.user_id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE ut.team_id = ?
    ORDER BY u.archived ASC, u.username
");
$stmt->execute([$team_id]);
$team_members = $stmt->fetchAll();
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Users: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderManagerNav($team_id, 'members'); ?>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="admin-section">
        <h2>Team Members (<?php echo count($team_members); ?>)</h2>
        <p><em>As a manager, you can only archive regular team members (not other managers, partners, or admins).</em></p>
        
        <?php if (count($team_members) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined Team</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                    <tr <?php echo $member['archived'] ? 'style="background: #fff3cd;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo ucfirst($member['role_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($member['joined_at'])); ?></td>
                        <td>
                            <?php if ($member['archived']): ?>
                                <span style="color: #856404; font-weight: bold;">Archived</span>
                            <?php else: ?>
                                <span style="color: #28a745; font-weight: bold;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $member['id']; ?>" 
                               class="btn btn-small">View CPD</a>
                            
                            <?php if ($member['role_name'] === 'user'): ?>
                                <?php if (!$member['archived']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Archive <?php echo htmlspecialchars($member['username']); ?>? They will not be able to log in.');">
                                        <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" name="archive_user" class="btn btn-small" style="background: #ffc107;">Archive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Unarchive <?php echo htmlspecialchars($member['username']); ?>? They will be able to log in again.');">
                                        <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
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
            <p>No members in this team yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
