<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/organisation_functions.php';
require_once 'includes/department_functions.php';
require_once 'includes/team_functions.php';

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

$dept_id = intval($_GET['id']);
$department = getDepartmentById($pdo, $dept_id);

if (!$department) {
    header('Location: admin_manage_organisations.php');
    exit();
}

// Check if admin can access this department's organisation
require_once 'includes/admin_functions.php';
if (!canAdminAccessOrganisation($pdo, $_SESSION['user_id'], $department['organisation_id'])) {
    header('Location: admin_manage_organisations.php');
    exit();
}

$pageTitle = 'Edit Department: ' . $department['name'];
include 'includes/header.php';

// Handle department update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_department'])) {
        $name = trim($_POST['dept_name']);
        $description = trim($_POST['dept_description']);
        
        if (!empty($name)) {
            if (updateDepartment($pdo, $dept_id, $name, $description)) {
                $success_message = "Department updated successfully!";
                // Refresh department data
                $department = getDepartmentById($pdo, $dept_id);
            } else {
                $error_message = "Failed to update department.";
            }
        } else {
            $error_message = "Department name is required.";
        }
    }
    
    // Handle partner assignment
    if (isset($_POST['assign_partner'])) {
        $partner_id = intval($_POST['partner_id']);
        if (assignPartnerToDepartment($pdo, $partner_id, $dept_id)) {
            $success_message = "Partner assigned to department successfully!";
        } else {
            $error_message = "Partner is already assigned to this department.";
        }
    }
    
    // Handle partner removal
    if (isset($_POST['remove_partner'])) {
        $partner_id = intval($_POST['partner_id']);
        if (removePartnerFromDepartment($pdo, $partner_id, $dept_id)) {
            $success_message = "Partner removed from department successfully!";
        } else {
            $error_message = "Failed to remove partner from department.";
        }
    }
}

// Get department statistics
$dept_stats = getDepartmentCPDStats($pdo, $dept_id);

// Get department partners
$dept_partners = getDepartmentPartners($pdo, $dept_id);

// Get available partners (not already assigned)
$available_partners = getPartnersNotInDepartment($pdo, $dept_id, $department['organisation_id']);

// Get department teams
$dept_teams = getDepartmentTeams($pdo, $dept_id);

// Get recent CPD entries
$recent_entries = getDepartmentCPDEntries($pdo, $dept_id, null, null, 10);
?>

<div class="container">
    <div class="admin-header">
        <h1>Department: <?php echo htmlspecialchars($department['name']); ?></h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">
            <?php echo htmlspecialchars($department['organisation_name']); ?>
        </p>
        <nav class="admin-nav">
            <a href="admin_manage_organisations.php">All Organisations</a>
            <a href="admin_organisation_departments.php?id=<?php echo $department['organisation_id']; ?>">Departments</a>
            <a href="admin_edit_department.php?id=<?php echo $dept_id; ?>" class="active">Department Details</a>
            <a href="admin_department_teams.php?id=<?php echo $dept_id; ?>">Teams</a>
        </nav>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Department Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Partners</h3>
            <p class="stat-number"><?php echo count($dept_partners); ?></p>
        </div>
        <div class="stat-card">
            <h3>Teams</h3>
            <p class="stat-number"><?php echo $department['team_count']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Members</h3>
            <p class="stat-number"><?php echo $department['member_count']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Hours</h3>
            <p class="stat-number"><?php echo round($dept_stats['total_hours'], 1); ?></p>
        </div>
    </div>

    <div class="dept-details-grid">
        <!-- Department Details Form -->
        <div class="admin-section">
            <h2>Department Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Department Name:</label>
                    <input type="text" name="dept_name" value="<?php echo htmlspecialchars($department['name']); ?>" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="dept_description" rows="4" maxlength="500"><?php echo htmlspecialchars($department['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Organisation:</label>
                    <p><strong><?php echo htmlspecialchars($department['organisation_name']); ?></strong></p>
                </div>
                
                <div class="form-group">
                    <label>Created By:</label>
                    <p><?php echo htmlspecialchars($department['created_by_name'] ?? 'Unknown'); ?></p>
                </div>
                
                <div class="form-group">
                    <label>Created:</label>
                    <p><?php echo date('F j, Y', strtotime($department['created_at'])); ?></p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_department" class="btn">Update Department</button>
                    <a href="admin_organisation_departments.php?id=<?php echo $department['organisation_id']; ?>" class="btn btn-secondary">Back to Departments</a>
                </div>
            </form>
        </div>

        <!-- Department Partners -->
        <div class="admin-section">
            <h2>Department Partners</h2>
            
            <?php if (count($available_partners) > 0): ?>
            <div class="assign-partner-form">
                <h3>Assign Partner</h3>
                <form method="POST">
                    <div class="form-group">
                        <select name="partner_id" required>
                            <option value="">-- Select a partner --</option>
                            <?php foreach ($available_partners as $partner): ?>
                                <option value="<?php echo $partner['id']; ?>">
                                    <?php echo htmlspecialchars($partner['username']); ?> (<?php echo htmlspecialchars($partner['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign_partner" class="btn btn-small">Assign Partner</button>
                </form>
            </div>
            <?php endif; ?>
            
            <h3>Current Partners (<?php echo count($dept_partners); ?>)</h3>
            <?php if (count($dept_partners) > 0): ?>
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
                        <?php foreach ($dept_partners as $partner): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($partner['username']); ?></td>
                            <td><?php echo htmlspecialchars($partner['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($partner['assigned_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?php echo htmlspecialchars($partner['username']); ?> as department partner?');">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <button type="submit" name="remove_partner" class="btn btn-small btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No partners assigned to this department yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Teams in Department -->
    <div class="admin-section">
        <h2>Teams in Department (<?php echo count($dept_teams); ?>)</h2>
        
        <?php if (count($dept_teams) > 0): ?>
            <div class="teams-summary-grid">
                <?php foreach ($dept_teams as $team): 
                    $team_stats = getTeamCPDStats($pdo, $team['id']);
                ?>
                    <div class="team-summary-card">
                        <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-desc"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        <div class="team-stats">
                            <div>
                                <strong><?php echo $team['member_count']; ?></strong>
                                <span>Members</span>
                            </div>
                            <div>
                                <strong><?php echo $team_stats['total_entries'] ?? 0; ?></strong>
                                <span>Entries</span>
                            </div>
                            <div>
                                <strong><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></strong>
                                <span>Hours</span>
                            </div>
                        </div>
                        <a href="admin_edit_team.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-block">Manage Team</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No teams in this department yet.</p>
        <?php endif; ?>
        
        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="admin_department_teams.php?id=<?php echo $dept_id; ?>" class="btn" style="background: #28a745;">
                Manage All Teams →
            </a>
        </div>
    </div>

    <!-- Recent CPD Entries -->
    <div class="admin-section">
        <h2>Recent CPD Entries</h2>
        
        <?php if (count($recent_entries) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Team</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                        <td><?php echo htmlspecialchars($entry['username']); ?></td>
                        <td><?php echo htmlspecialchars($entry['team_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['title']); ?></td>
                        <td><?php echo htmlspecialchars($entry['category']); ?></td>
                        <td><?php echo htmlspecialchars($entry['hours']); ?> hours</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No CPD entries yet in this department.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .dept-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .assign-partner-form {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
    }
    
    .assign-partner-form h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1rem;
        color: #666;
    }
    
    .assign-partner-form select {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .teams-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .team-summary-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .team-summary-card h3 {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
        font-size: 1.1rem;
    }
    
    .team-desc {
        color: #666;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .team-stats {
        display: flex;
        justify-content: space-around;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .team-stats strong {
        display: block;
        font-size: 1.25rem;
        color: #007cba;
    }
    
    .team-stats span {
        display: block;
        font-size: 0.75rem;
        color: #666;
        margin-top: 0.25rem;
    }
    
    @media (max-width: 992px) {
        .dept-details-grid {
            grid-template-columns: 1fr;
        }
        
        .teams-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
