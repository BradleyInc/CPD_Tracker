<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
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

// Get all users and roles
$users = getAllUsers($pdo);
$roles = getAllRoles($pdo);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Users</h1>
        <?php renderAdminNav('users'); ?>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="admin-section">
        <h2>User List (<?php echo count($users); ?> users)</h2>
        
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
                        <a href="admin_edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-small">View Details</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>