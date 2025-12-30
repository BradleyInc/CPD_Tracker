<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';

// Only super admins can access this page
checkAuth();
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Manage Super Admins';
include 'includes/header.php';

// Handle adding new super admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_super_admin'])) {
    $user_id = intval($_POST['user_id']);
    
    // Update user role to super_admin
    $stmt = $pdo->prepare("UPDATE users SET role_id = 5 WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $success_message = "User promoted to Super Admin successfully!";
    } else {
        $error_message = "Failed to promote user.";
    }
}

// Handle removing super admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_super_admin'])) {
    $user_id = intval($_POST['user_id']);
    
    // Prevent removing yourself
    if ($user_id === $_SESSION['user_id']) {
        $error_message = "You cannot remove yourself as Super Admin!";
    } else {
        // Demote to regular admin (role_id = 2)
        $stmt = $pdo->prepare("UPDATE users SET role_id = 2 WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success_message = "Super Admin demoted to regular Admin successfully!";
        } else {
            $error_message = "Failed to demote user.";
        }
    }
}

// Get all super admins
$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, u.created_at, o.name as organisation_name
    FROM users u
    LEFT JOIN organisations o ON u.organisation_id = o.id
    WHERE u.role_id = 5
    ORDER BY u.username
");
$super_admins = $stmt->fetchAll();

// Get all regular admins (candidates for promotion)
$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, o.name as organisation_name
    FROM users u
    LEFT JOIN organisations o ON u.organisation_id = o.id
    WHERE u.role_id = 2
    ORDER BY u.username
");
$admin_candidates = $stmt->fetchAll();
?>

<div class="container">
    <div class="admin-header">
        <h1>🚀 Manage Super Administrators</h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">Internal SaaS staff with full system access</p>
        <?php renderAdminNav('system'); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Current Super Admins -->
    <div class="admin-section">
        <h2>Current Super Administrators (<?php echo count($super_admins); ?>)</h2>
        
        <?php if (count($super_admins) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Organisation</th>
                        <th>Member Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($super_admins as $admin): ?>
                    <tr style="background: #e8f4f8;">
                        <td><strong><?php echo htmlspecialchars($admin['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td><?php echo $admin['organisation_name'] ? htmlspecialchars($admin['organisation_name']) : '<em>No organisation</em>'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                        <td>
                            <?php if ($admin['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Demote <?php echo htmlspecialchars($admin['username']); ?> to regular Admin? They will lose system-wide access.');">
                                    <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="remove_super_admin" class="btn btn-small" style="background: #ffc107;">
                                        Demote to Admin
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: #666; font-style: italic;">You (current user)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No super administrators found.</p>
        <?php endif; ?>
    </div>

    <!-- Promote Admin to Super Admin -->
    <div class="admin-section">
        <h2>Promote Admin to Super Administrator</h2>
        <p style="color: #666; margin-bottom: 1rem;">
            Super Admins are internal SaaS staff with access to all organisations and system metrics.
            They can view the System Admin Dashboard with SaaS KPIs, tenant management, and more.
        </p>
        
        <?php if (count($admin_candidates) > 0): ?>
            <form method="POST" class="promote-form">
                <div class="form-group">
                    <label>Select Admin to Promote:</label>
                    <select name="user_id" required>
                        <option value="">-- Select an admin --</option>
                        <?php foreach ($admin_candidates as $candidate): ?>
                            <option value="<?php echo $candidate['id']; ?>">
                                <?php echo htmlspecialchars($candidate['username']); ?> 
                                (<?php echo htmlspecialchars($candidate['email']); ?>)
                                <?php if ($candidate['organisation_name']): ?>
                                    - <?php echo htmlspecialchars($candidate['organisation_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_super_admin" class="btn">
                    ⬆️ Promote to Super Admin
                </button>
            </form>
        <?php else: ?>
            <p>All current admins are already Super Administrators.</p>
        <?php endif; ?>
    </div>

    <!-- Information Box -->
    <div class="admin-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h3 style="margin-top: 0;">ℹ️ About Super Administrators</h3>
        <ul style="margin: 0; padding-left: 1.5rem;">
            <li><strong>System Dashboard Access:</strong> View SaaS metrics, revenue, growth, and all tenant data</li>
            <li><strong>Cross-Tenant Access:</strong> Manage all organisations, departments, teams, and users</li>
            <li><strong>Internal Staff:</strong> Typically assigned to your SaaS company employees</li>
            <li><strong>Regular Admins:</strong> Tenant administrators who manage only their organisation</li>
        </ul>
        <p style="margin-top: 1rem; margin-bottom: 0;">
            <strong>Note:</strong> Super Admins retain all regular admin capabilities but with system-wide visibility.
        </p>
    </div>
</div>

<style>
    .promote-form {
        max-width: 600px;
    }
    
    .promote-form select {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 4px;
        font-size: 1rem;
    }
</style>

<?php include 'includes/footer.php'; ?>
