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

$pageTitle = 'Manage Organisations';
include 'includes/header.php';

// Handle organisation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_organisation'])) {
        $name = trim($_POST['org_name']);
        $description = trim($_POST['org_description']);
        $subscription_plan = $_POST['subscription_plan'];
        $billing_email = trim($_POST['billing_email']);
        $max_users = intval($_POST['max_users']);
        
        if (!empty($name) && !empty($billing_email)) {
            if (createOrganisation($pdo, $name, $description, $subscription_plan, $billing_email, $max_users)) {
                $success_message = "Organisation '$name' created successfully!";
            } else {
                $error_message = "Failed to create organisation.";
            }
        } else {
            $error_message = "Organisation name and billing email are required.";
        }
    }
    
    // Handle organisation update
    if (isset($_POST['update_organisation'])) {
        $org_id = intval($_POST['org_id']);
        $name = trim($_POST['org_name']);
        $description = trim($_POST['org_description']);
        $subscription_status = $_POST['subscription_status'];
        $subscription_plan = $_POST['subscription_plan'];
        $billing_email = trim($_POST['billing_email']);
        $max_users = intval($_POST['max_users']);
        
        if (updateOrganisation($pdo, $org_id, $name, $description, $subscription_status, $subscription_plan, $billing_email, $max_users)) {
            $success_message = "Organisation updated successfully!";
        } else {
            $error_message = "Failed to update organisation.";
        }
    }
    
    // Handle organisation deletion
    if (isset($_POST['delete_organisation'])) {
        $org_id = intval($_POST['org_id']);
        if (deleteOrganisation($pdo, $org_id)) {
            $success_message = "Organisation deleted successfully!";
        } else {
            $error_message = "Failed to delete organisation.";
        }
    }
}

// Get all organisations
$organisations = getAllOrganisations($pdo);

// Get organisations near user limit
$orgs_near_limit = getOrganisationsNearLimit($pdo, 80);

// Get expiring trials
$expiring_trials = getExpiringTrials($pdo, 14);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Organisations</h1>
        <?php renderAdminNav('organisations'); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Warnings Section -->
    <?php if (count($expiring_trials) > 0 || count($orgs_near_limit) > 0): ?>
    <div class="admin-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h2>⚠️ Attention Required</h2>
        
        <?php if (count($expiring_trials) > 0): ?>
        <div style="margin-bottom: 1rem;">
            <h3>Trials Expiring Soon (<?php echo count($expiring_trials); ?>)</h3>
            <ul>
                <?php foreach ($expiring_trials as $trial): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($trial['name']); ?></strong> - 
                        <?php echo $trial['days_remaining']; ?> days remaining
                        <a href="admin_edit_organisation.php?id=<?php echo $trial['id']; ?>" class="btn btn-small">Manage</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (count($orgs_near_limit) > 0): ?>
        <div>
            <h3>Organisations Near User Limit (<?php echo count($orgs_near_limit); ?>)</h3>
            <ul>
                <?php foreach ($orgs_near_limit as $org): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($org['name']); ?></strong> - 
                        <?php echo $org['current_users']; ?>/<?php echo $org['max_users']; ?> users 
                        (<?php echo $org['usage_percentage']; ?>%)
                        <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>" class="btn btn-small">Manage</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Create Organisation Form -->
    <div class="admin-section">
        <h2>Create New Organisation</h2>
        <form method="POST" class="org-form">
            <div class="form-row">
                <div class="form-group">
                    <label>Organisation Name:</label>
                    <input type="text" name="org_name" required maxlength="200">
                </div>
                <div class="form-group">
                    <label>Billing Email:</label>
                    <input type="email" name="billing_email" required maxlength="255">
                </div>
            </div>
            
            <div class="form-group">
                <label>Description:</label>
                <textarea name="org_description" rows="2" maxlength="500"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Subscription Plan:</label>
                    <select name="subscription_plan">
                        <option value="basic">Basic (10 users)</option>
                        <option value="professional">Professional (50 users)</option>
                        <option value="enterprise">Enterprise (Unlimited)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Max Users:</label>
                    <input type="number" name="max_users" value="10" min="1" max="9999">
                </div>
            </div>
            
            <button type="submit" name="create_organisation" class="btn">Create Organisation</button>
        </form>
    </div>

    <!-- Organisations List -->
    <div class="admin-section">
        <h2>All Organisations (<?php echo count($organisations); ?>)</h2>
        
        <?php if (count($organisations) > 0): ?>
            <div class="orgs-grid">
                <?php foreach ($organisations as $org): ?>
                    <div class="org-card">
                        <div class="org-card-header">
                            <h3><?php echo htmlspecialchars($org['name']); ?></h3>
                            <span class="status-badge status-<?php echo $org['subscription_status']; ?>">
                                <?php echo ucfirst($org['subscription_status']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($org['description'])): ?>
                            <p class="org-description"><?php echo htmlspecialchars($org['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="org-stats">
                            <div class="stat-item">
                                <span class="stat-label">Plan:</span>
                                <span class="stat-value"><?php echo ucfirst($org['subscription_plan']); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Users:</span>
                                <span class="stat-value"><?php echo $org['user_count']; ?> / <?php echo $org['max_users']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Departments:</span>
                                <span class="stat-value"><?php echo $org['department_count']; ?></span>
                            </div>
                        </div>
                        
                        <div class="org-meta">
                            <small>Created: <?php echo date('M d, Y', strtotime($org['created_at'])); ?></small>
                        </div>
                        
                        <div class="org-actions">
                            <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>" class="btn btn-small">Manage</a>
                            <a href="admin_organisations_departments.php?id=<?php echo $org['id']; ?>" class="btn btn-small btn-secondary">Departments</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($org['name']); ?>? This will delete all departments, teams, and users!');">
                                <input type="hidden" name="org_id" value="<?php echo $org['id']; ?>">
                                <button type="submit" name="delete_organisation" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No organisations have been created yet.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .orgs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .org-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .org-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .org-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .org-card h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.25rem;
    }
    
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .status-trial {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-suspended {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-cancelled {
        background: #e2e3e5;
        color: #383d41;
    }
    
    .org-description {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .org-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-label {
        display: block;
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        display: block;
        font-size: 1rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .org-meta {
        font-size: 0.875rem;
        color: #888;
        margin-bottom: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .org-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .org-form {
        max-width: 800px;
    }
    
    @media (max-width: 768px) {
        .orgs-grid {
            grid-template-columns: 1fr;
        }
        
        .org-stats {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
