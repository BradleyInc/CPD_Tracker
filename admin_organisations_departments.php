<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/organisation_functions.php';
require_once 'includes/department_functions.php';

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
require_once 'includes/admin_functions.php';
if (!canAdminAccessOrganisation($pdo, $_SESSION['user_id'], $org_id)) {
    header('Location: admin_manage_organisations.php');
    exit();
}

$pageTitle = 'Departments: ' . $organisation['name'];
include 'includes/header.php';

// Handle department creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_department'])) {
        $name = trim($_POST['dept_name']);
        $description = trim($_POST['dept_description']);
        
        if (!empty($name)) {
            if (createDepartment($pdo, $org_id, $name, $description, $_SESSION['user_id'])) {
                $success_message = "Department '$name' created successfully!";
            } else {
                $error_message = "Failed to create department.";
            }
        } else {
            $error_message = "Department name is required.";
        }
    }
    
    // Handle department deletion
    if (isset($_POST['delete_department'])) {
        $dept_id = intval($_POST['dept_id']);
        if (deleteDepartment($pdo, $dept_id)) {
            $success_message = "Department deleted successfully!";
        } else {
            $error_message = "Failed to delete department.";
        }
    }
}

// Get departments for this organisation
$departments = getOrganisationDepartments($pdo, $org_id);
?>

<div class="container">
    <div class="admin-header">
        <h1>Departments: <?php echo htmlspecialchars($organisation['name']); ?></h1>
        <nav class="admin-nav">
            <a href="admin_manage_organisations.php">All Organisations</a>
            <a href="admin_edit_organisation.php?id=<?php echo $org_id; ?>">Organisation Details</a>
            <a href="admin_organisation_departments.php?id=<?php echo $org_id; ?>" class="active">Departments</a>
        </nav>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Organisation Summary -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <h3>Subscription Plan</h3>
            <p class="stat-number" style="font-size: 1.2rem;"><?php echo ucfirst($organisation['subscription_plan']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Users</h3>
            <p class="stat-number"><?php echo $organisation['user_count']; ?> / <?php echo $organisation['max_users']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Departments</h3>
            <p class="stat-number"><?php echo count($departments); ?></p>
        </div>
        <div class="stat-card">
            <h3>Status</h3>
            <p class="stat-number" style="font-size: 1.2rem;"><?php echo ucfirst($organisation['subscription_status']); ?></p>
        </div>
    </div>

    <!-- Create Department Form -->
    <div class="admin-section">
        <h2>Create New Department</h2>
        <form method="POST" class="dept-form">
            <div class="form-group">
                <label>Department Name:</label>
                <input type="text" name="dept_name" required maxlength="200" placeholder="e.g., Tax, Assurance, Consulting">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="dept_description" rows="3" maxlength="500" placeholder="Optional description of the department"></textarea>
            </div>
            <button type="submit" name="create_department" class="btn">Create Department</button>
        </form>
    </div>

    <!-- Departments List -->
    <div class="admin-section">
        <h2>Departments (<?php echo count($departments); ?>)</h2>
        
        <?php if (count($departments) > 0): ?>
            <div class="depts-grid">
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card">
                        <div class="dept-card-header">
                            <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                            <div class="dept-badges">
                                <?php if ($dept['partner_count'] > 0): ?>
                                    <span class="badge badge-partners"><?php echo $dept['partner_count']; ?> Partners</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($dept['description'])): ?>
                            <p class="dept-description"><?php echo htmlspecialchars($dept['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="dept-stats">
                            <div class="stat-item">
                                <span class="stat-label">Teams</span>
                                <span class="stat-value"><?php echo $dept['team_count']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Members</span>
                                <span class="stat-value"><?php echo $dept['member_count']; ?></span>
                            </div>
                        </div>
                        
                        <div class="dept-meta">
                            <small>Created: <?php echo date('M d, Y', strtotime($dept['created_at'])); ?></small>
                        </div>
                        
                        <div class="dept-actions">
                            <a href="admin_edit_department.php?id=<?php echo $dept['id']; ?>" class="btn btn-small">Manage</a>
                            <a href="admin_department_teams.php?id=<?php echo $dept['id']; ?>" class="btn btn-small btn-secondary">Teams</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($dept['name']); ?>? This will delete all teams within this department!');">
                                <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                <button type="submit" name="delete_department" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No departments have been created yet for this organisation.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .depts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .dept-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .dept-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .dept-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .dept-card h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.25rem;
    }
    
    .dept-badges {
        display: flex;
        gap: 0.5rem;
    }
    
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .badge-partners {
        background: #e9f7fe;
        color: #007cba;
    }
    
    .dept-description {
        color: #666;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .dept-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .dept-meta {
        font-size: 0.875rem;
        color: #888;
        margin-bottom: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .dept-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .dept-form {
        max-width: 600px;
    }
    
    @media (max-width: 768px) {
        .depts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
