<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/organisation_functions.php';

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

$org_id = intval($_GET['id']);
$organisation = getOrganisationById($pdo, $org_id);

if (!$organisation) {
    header('Location: admin_manage_organisations.php');
    exit();
}

// Check if admin can access this organisation
if (!canAdminAccessOrganisation($pdo, $_SESSION['user_id'], $org_id)) {
    header('Location: admin_manage_organisations.php');
    exit();
}

$pageTitle = 'Edit Organisation: ' . $organisation['name'];
include 'includes/header.php';

// Handle organisation update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_organisation'])) {
        $name = trim($_POST['org_name']);
        $description = trim($_POST['org_description']);
        $subscription_status = $_POST['subscription_status'];
        $subscription_plan = $_POST['subscription_plan'];
        $billing_email = trim($_POST['billing_email']);
        $max_users = intval($_POST['max_users']);
        
        if (!empty($name) && !empty($billing_email)) {
            if (updateOrganisation($pdo, $org_id, $name, $description, $subscription_status, $subscription_plan, $billing_email, $max_users)) {
                $success_message = "Organisation updated successfully!";
                // Refresh organisation data
                $organisation = getOrganisationById($pdo, $org_id);
            } else {
                $error_message = "Failed to update organisation.";
            }
        } else {
            $error_message = "Organisation name and billing email are required.";
        }
    }
    
    // Handle organisation admin assignment
    if (isset($_POST['assign_admin'])) {
        $user_id = intval($_POST['user_id']);
        if (assignOrganisationAdmin($pdo, $user_id, $org_id)) {
            $success_message = "Organisation admin assigned successfully!";
        } else {
            $error_message = "User is already an organisation admin or assignment failed.";
        }
    }
    
    // Handle organisation admin removal
    if (isset($_POST['remove_admin'])) {
        $user_id = intval($_POST['user_id']);
        if (removeOrganisationAdmin($pdo, $user_id, $org_id)) {
            $success_message = "Organisation admin removed successfully!";
        } else {
            $error_message = "Failed to remove organisation admin.";
        }
    }
}

// Get organisation statistics
$org_stats = getOrganisationStats($pdo, $org_id);

// Get organisation admins
$org_admins = getOrganisationAdmins($pdo, $org_id);

// Get users in organisation who are not admins
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email
    FROM users u
    WHERE u.organisation_id = ? 
    AND u.archived = 0
    AND u.id NOT IN (SELECT user_id FROM organisation_admins WHERE organisation_id = ?)
    ORDER BY u.username
");
$stmt->execute([$org_id, $org_id]);
$available_users = $stmt->fetchAll();

// Check if trial is expiring soon
$trial_warning = false;
if ($organisation['subscription_status'] === 'trial' && $organisation['trial_ends_at']) {
    $trial_date = new DateTime($organisation['trial_ends_at']);
    $now = new DateTime();
    $diff = $now->diff($trial_date);
    $days_remaining = $diff->days;
    if ($diff->invert == 0 && $days_remaining <= 14) {
        $trial_warning = true;
    }
}

// Check if near user limit
$usage_percentage = ($organisation['user_count'] / $organisation['max_users']) * 100;
$near_limit = $usage_percentage >= 80;
?>

<div class="container">
    <div class="admin-header">
        <h1>Edit Organisation: <?php echo htmlspecialchars($organisation['name']); ?></h1>
        <nav class="admin-nav">
            <a href="admin_manage_organisations.php">All Organisations</a>
            <a href="admin_edit_organisation.php?id=<?php echo $org_id; ?>" class="active">Organisation Details</a>
            <a href="admin_organisations_departments.php?id=<?php echo $org_id; ?>">Departments</a>
        </nav>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Warnings -->
    <?php if ($trial_warning || $near_limit): ?>
    <div class="admin-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h3 style="margin-top: 0;">⚠️ Attention Required</h3>
        <?php if ($trial_warning): ?>
            <p><strong>Trial Expiring:</strong> This organisation's trial expires on <?php echo date('F j, Y', strtotime($organisation['trial_ends_at'])); ?> (<?php echo $days_remaining; ?> days remaining)</p>
        <?php endif; ?>
        <?php if ($near_limit): ?>
            <p><strong>Near User Limit:</strong> Currently using <?php echo $organisation['user_count']; ?> of <?php echo $organisation['max_users']; ?> users (<?php echo round($usage_percentage, 1); ?>%)</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Organisation Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <p class="stat-number"><?php echo $org_stats['total_users']; ?> / <?php echo $organisation['max_users']; ?></p>
            <small><?php echo round($usage_percentage, 1); ?>% of limit</small>
        </div>
        <div class="stat-card">
            <h3>Departments</h3>
            <p class="stat-number"><?php echo $org_stats['total_departments']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Teams</h3>
            <p class="stat-number"><?php echo $org_stats['total_teams']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Hours</h3>
            <p class="stat-number"><?php echo round($org_stats['total_cpd_hours'], 1); ?></p>
        </div>
    </div>

    <div class="org-details-grid">
        <!-- Organisation Details Form -->
        <div class="admin-section">
            <h2>Organisation Details</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Organisation Name:</label>
                    <input type="text" name="org_name" value="<?php echo htmlspecialchars($organisation['name']); ?>" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="org_description" rows="3" maxlength="500"><?php echo htmlspecialchars($organisation['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Billing Email:</label>
                    <input type="email" name="billing_email" value="<?php echo htmlspecialchars($organisation['billing_email'] ?? ''); ?>" required maxlength="255">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Subscription Status:</label>
                        <select name="subscription_status">
                            <option value="trial" <?php echo $organisation['subscription_status'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="active" <?php echo $organisation['subscription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $organisation['subscription_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="cancelled" <?php echo $organisation['subscription_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subscription Plan:</label>
                        <select name="subscription_plan">
                            <option value="basic" <?php echo $organisation['subscription_plan'] === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="professional" <?php echo $organisation['subscription_plan'] === 'professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="enterprise" <?php echo $organisation['subscription_plan'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Maximum Users:</label>
                    <input type="number" name="max_users" value="<?php echo $organisation['max_users']; ?>" min="1" max="9999">
                </div>
                
                <div class="form-group">
                    <label>Trial Ends:</label>
                    <p><?php echo $organisation['trial_ends_at'] ? date('F j, Y', strtotime($organisation['trial_ends_at'])) : 'N/A'; ?></p>
                </div>
                
                <div class="form-group">
                    <label>Created:</label>
                    <p><?php echo date('F j, Y', strtotime($organisation['created_at'])); ?></p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_organisation" class="btn">Update Organisation</button>
                    <a href="admin_manage_organisations.php" class="btn btn-secondary">Back to Organisations</a>
                </div>
            </form>
        </div>

        <!-- Organisation Admins -->
        <div class="admin-section">
            <h2>Organisation Administrators</h2>
            
            <?php if (count($available_users) > 0): ?>
            <div class="assign-admin-form">
                <h3>Assign Administrator</h3>
                <form method="POST">
                    <div class="form-group">
                        <select name="user_id" required>
                            <option value="">-- Select a user --</option>
                            <?php foreach ($available_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign_admin" class="btn btn-small">Assign Admin</button>
                </form>
            </div>
            <?php endif; ?>
            
            <h3>Current Administrators (<?php echo count($org_admins); ?>)</h3>
            <?php if (count($org_admins) > 0): ?>
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
                        <?php foreach ($org_admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($admin['assigned_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?php echo htmlspecialchars($admin['username']); ?> as organisation admin?');">
                                    <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="remove_admin" class="btn btn-small btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No organisation administrators assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="admin_organisations_departments.php?id=<?php echo $org_id; ?>" class="btn" style="background: #28a745;">
            View Departments →
        </a>
    </div>
</div>

<style>
    .org-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .assign-admin-form {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
    }
    
    .assign-admin-form h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1rem;
        color: #666;
    }
    
    .assign-admin-form select {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    @media (max-width: 992px) {
        .org-details-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
