<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/user_management_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Archived Users';
include 'includes/header.php';

// Handle unarchive user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_user'])) {
    $user_id = intval($_POST['user_id']);
    
    if (unarchiveUser($pdo, $user_id)) {
        $message = '<div class="alert alert-success">User unarchived successfully!</div>';
    } else {
        $message = '<div class="alert alert-error">Failed to unarchive user.</div>';
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    if (deleteUser($pdo, $user_id)) {
        $message = '<div class="alert alert-success">User deleted permanently!</div>';
    } else {
        $message = '<div class="alert alert-error">Failed to delete user.</div>';
    }
}

// Get all archived users
$archived_users = getAllArchivedUsers($pdo);
?>

<div class="container">
    <div class="admin-header">
        <h1>Archived Users</h1>
        <?php renderAdminNav('users'); ?>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="admin-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>Archived Users (<?php echo count($archived_users); ?>)</h2>
            <a href="admin_manage_users.php" class="btn btn-secondary">Back to Active Users</a>
        </div>
        
        <?php if (count($archived_users) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Archived Date</th>
                        <th>Archived By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archived_users as $user): ?>
                    <tr style="background: #fff3cd;">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo ucfirst($user['role_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($user['archived_at'])); ?></td>
                        <td><?php echo htmlspecialchars($user['archived_by_username'] ?? 'Unknown'); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Unarchive <?php echo htmlspecialchars($user['username']); ?>? They will be able to log in again.');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="unarchive_user" class="btn btn-small" style="background: #28a745;">Unarchive</button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('PERMANENTLY DELETE <?php echo htmlspecialchars($user['username']); ?>? This cannot be undone and will delete all their CPD entries!');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No archived users.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
