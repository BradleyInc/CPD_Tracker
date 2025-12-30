<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/user_management_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdminOrSuper()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Manage Users';
include 'includes/header.php';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);
    if (updateUserRole($pdo, $user_id, $role_id)) {
        $message = '<div class="alert alert-success">User role updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-error">Failed to update user role.</div>';
    }
}

// Handle archive user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Prevent archiving self
    if ($user_id === $_SESSION['user_id']) {
        $message = '<div class="alert alert-error">You cannot archive yourself!</div>';
    } else {
        if (archiveUser($pdo, $user_id, $_SESSION['user_id'])) {
            $message = '<div class="alert alert-success">User archived successfully!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to archive user.</div>';
        }
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Prevent deleting self
    if ($user_id === $_SESSION['user_id']) {
        $message = '<div class="alert alert-error">You cannot delete yourself!</div>';
    } else {
        if (deleteUser($pdo, $user_id)) {
            $message = '<div class="alert alert-success">User deleted permanently!</div>';
        } else {
            $message = '<div class="alert alert-error">Failed to delete user.</div>';
        }
    }
}

// Get all active users
$users = getAllUsers($pdo, false);
$roles = getAllRoles($pdo);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Users</h1>
        <?php renderAdminNav('users'); ?>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="admin-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>Active Users (<?php echo count($users); ?>)</h2>
            <a href="admin_archived_users.php" class="btn btn-secondary">View Archived Users</a>
        </div>
        
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <select name="role_id" onchange="this.form.submit()">
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" 
                                    <?php echo ($user['role_name'] == $role['name']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($role['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_role" value="1">
                        </form>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-small">View</a>
                        
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Archive <?php echo htmlspecialchars($user['username']); ?>? They will not be able to log in.');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="archive_user" class="btn btn-small" style="background: #ffc107;">Archive</button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('PERMANENTLY DELETE <?php echo htmlspecialchars($user['username']); ?>? This cannot be undone and will delete all their CPD entries!');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
