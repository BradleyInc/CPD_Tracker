<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
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

$team_id = intval($_GET['id']);

// Check if partner has access to this team
if (!isPartnerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: partner_dashboard.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get team CPD summary
$team_summary = getTeamCPDSummary($pdo, $team_id, $start_date, $end_date);
$team_managers = getTeamManagers($pdo, $team_id);

// Calculate totals
$total_entries = 0;
$total_hours = 0;
foreach ($team_summary as $member) {
    $total_entries += $member['total_entries'];
    $total_hours += $member['total_hours'];
}

$pageTitle = 'Team Overview: ' . $team['name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($team['name']); ?></h1>
        <nav class="admin-nav">
            <a href="partner_dashboard.php">My Teams</a>
            <a href="partner_team_view.php?id=<?php echo $team_id; ?>" class="active">Team Overview</a>
            <a href="partner_team_members.php?id=<?php echo $team_id; ?>">Team Members</a>
            <a href="dashboard.php">My CPD</a>
        </nav>
    </div>

    <?php if (count($team_managers) > 0): ?>
    <div class="admin-section" style="background: #e9f7fe;">
        <h3 style="margin-top: 0; color: #007cba;">Team Managers</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
            <?php foreach ($team_managers as $manager): ?>
                <div class="manager-badge">
                    <strong><?php echo htmlspecialchars($manager['username']); ?></strong>
                    <small style="display: block; color: #666;">
                        Assigned: <?php echo date('M d, Y', strtotime($manager['assigned_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-section">
        <h2>Filter by Date Range</h2>
        <form method="GET" class="filter-form">
            <input type="hidden" name="id" value="<?php echo $team_id; ?>">
            
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
                <a href="partner_team_view.php?id=<?php echo $team_id; ?>" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Team Members</h3>
            <p class="stat-number"><?php echo count($team_summary); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Entries</h3>
            <p class="stat-number"><?php echo $total_entries; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Hours</h3>
            <p class="stat-number"><?php echo round($total_hours, 1); ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg Hours/Member</h3>
            <p class="stat-number">
                <?php echo count($team_summary) > 0 ? round($total_hours / count($team_summary), 1) : 0; ?>
            </p>
        </div>
    </div>

    <div class="admin-section">
        <h2>Team Member CPD Summary</h2>
        
        <?php if (count($team_summary) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Team Member</th>
                        <th>Total Entries</th>
                        <th>Total Hours</th>
                        <th>Last Entry Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_summary as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['username']); ?></td>
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
                            <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $member['user_id']; ?><?php echo $start_date ? '&start_date=' . $start_date : ''; ?><?php echo $end_date ? '&end_date=' . $end_date : ''; ?>" 
                               class="btn btn-small">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No team members found.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    .filter-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .manager-badge {
        background: white;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        border: 2px solid #b3d7ff;
    }
</style>

<?php include 'includes/footer.php'; ?>
