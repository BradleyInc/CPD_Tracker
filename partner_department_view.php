<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/department_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

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

$dept_id = intval($_GET['id']);

// Check if partner has access to this department
if (!isPartnerOfDepartment($pdo, $_SESSION['user_id'], $dept_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$department = getDepartmentById($pdo, $dept_id);

if (!$department) {
    header('Location: partner_dashboard.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get department statistics
$dept_stats = getDepartmentCPDStats($pdo, $dept_id);
$dept_teams = getDepartmentTeams($pdo, $dept_id);
$dept_members = getDepartmentMemberSummary($pdo, $dept_id, $start_date, $end_date);

$pageTitle = 'Department: ' . $department['name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($department['name']); ?></h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">
            <?php echo htmlspecialchars($department['organisation_name']); ?>
        </p>
        <?php renderDepartmentNav($dept_id, 'overview'); ?>
    </div>

    <?php if (!empty($department['description'])): ?>
    <div class="admin-section" style="background: #e9f7fe; border-left: 4px solid #007cba;">
        <p style="margin: 0; color: #333;"><?php echo htmlspecialchars($department['description']); ?></p>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Filter by Date Range</h2>
        <form method="GET" class="filter-form">
            <input type="hidden" name="id" value="<?php echo $dept_id; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label>End Date:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="partner_department_view.php?id=<?php echo $dept_id; ?>" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Teams</h3>
            <p class="stat-number"><?php echo count($dept_teams); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Members</h3>
            <p class="stat-number"><?php echo count($dept_members); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Entries</h3>
            <p class="stat-number"><?php echo $dept_stats['total_entries'] ?? 0; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Hours</h3>
            <p class="stat-number"><?php echo round($dept_stats['total_hours'] ?? 0, 1); ?></p>
        </div>
    </div>

    <!-- Teams Overview -->
    <div class="admin-section">
        <h2>Teams in Department (<?php echo count($dept_teams); ?>)</h2>
        
        <?php if (count($dept_teams) > 0): ?>
            <div class="teams-overview-grid">
                <?php foreach ($dept_teams as $team): 
                    $team_stats = getTeamCPDStats($pdo, $team['id']);
                ?>
                    <div class="team-overview-card">
                        <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-desc"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        <div class="team-quick-stats">
                            <div>
                                <strong><?php echo $team['member_count']; ?></strong> members
                            </div>
                            <div>
                                <strong><?php echo $team_stats['total_entries'] ?? 0; ?></strong> entries
                            </div>
                            <div>
                                <strong><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></strong> hours
                            </div>
                        </div>
                        <a href="partner_team_view.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-block">View Team</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No teams in this department yet.</p>
        <?php endif; ?>
    </div>

    <!-- Members Summary -->
    <div class="admin-section">
        <h2>Department Members Summary</h2>
        
        <?php if (count($dept_members) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Team</th>
                        <th>CPD Entries</th>
                        <th>CPD Hours</th>
                        <th>Last Entry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                        <td><?php echo htmlspecialchars($member['team_name']); ?></td>
                        <td><?php echo $member['total_entries']; ?></td>
                        <td><?php echo round($member['total_hours'], 1); ?> hours</td>
                        <td>
                            <?php 
                            echo $member['last_entry_date'] 
                                ? date('M d, Y', strtotime($member['last_entry_date'])) 
                                : 'No entries';
                            ?>
                        </td>
                        <td>
                            <a href="manager_member_detail.php?id=<?php echo $dept_id; ?>&user_id=<?php echo $member['user_id']; ?><?php echo $start_date ? '&start_date=' . $start_date : ''; ?><?php echo $end_date ? '&end_date=' . $end_date : ''; ?>" 
                               class="btn btn-small">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No members in this department yet.</p>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="partner_dashboard.php" class="btn btn-secondary">Back to My Departments</a>
    </div>
</div>

<style>
    .filter-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .teams-overview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .team-overview-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .team-overview-card h3 {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
        font-size: 1.1rem;
    }
    
    .team-desc {
        color: #666;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .team-quick-stats {
        display: flex;
        justify-content: space-around;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .team-quick-stats strong {
        display: block;
        font-size: 1.25rem;
        color: #007cba;
    }
</style>

<?php include 'includes/footer.php'; ?>
