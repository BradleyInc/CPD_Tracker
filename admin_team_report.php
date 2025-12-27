<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/team_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_manage_teams.php');
    exit();
}

$team_id = intval($_GET['id']);
$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: admin_manage_teams.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$category = $_GET['category'] ?? 'all';

// Get team members
$team_members = getTeamMembers($pdo, $team_id);

// Build query for team CPD entries
$query = "
    SELECT ce.*, u.username
    FROM cpd_entries ce
    JOIN user_teams ut ON ce.user_id = ut.user_id
    JOIN users u ON ce.user_id = u.id
    WHERE ut.team_id = ?
";

$params = [$team_id];

if ($start_date) {
    $query .= " AND ce.date_completed >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND ce.date_completed <= ?";
    $params[] = $end_date;
}

if ($category && $category !== 'all') {
    $query .= " AND ce.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY ce.date_completed DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$team_entries = $stmt->fetchAll();

// Calculate statistics
$total_hours = 0;
$entries_by_user = [];
$entries_by_category = [];

foreach ($team_entries as $entry) {
    $total_hours += $entry['hours'];
    
    // Count entries by user
    $username = $entry['username'];
    $entries_by_user[$username] = ($entries_by_user[$username] ?? 0) + 1;
    
    // Count entries by category
    $category_name = $entry['category'];
    $entries_by_category[$category_name] = ($entries_by_category[$category_name] ?? 0) + 1;
}

$pageTitle = 'Team Report: ' . $team['name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>Team Report: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderTeamNav($team_id, 'report'); ?>
    </div>

    <div class="admin-section">
        <h2>Filter Report</h2>
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
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category">
                        <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="Training" <?php echo $category === 'Training' ? 'selected' : ''; ?>>Training</option>
                        <option value="Conference" <?php echo $category === 'Conference' ? 'selected' : ''; ?>>Conference</option>
                        <option value="Reading" <?php echo $category === 'Reading' ? 'selected' : ''; ?>>Reading</option>
                        <option value="Online Course" <?php echo $category === 'Online Course' ? 'selected' : ''; ?>>Online Course</option>
                        <option value="Other" <?php echo $category === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="admin_team_report.php?id=<?php echo $team_id; ?>" class="btn btn-secondary">Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <h3>Team Members</h3>
            <p class="stat-number"><?php echo count($team_members); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Entries</h3>
            <p class="stat-number"><?php echo count($team_entries); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Hours</h3>
            <p class="stat-number"><?php echo $total_hours; ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg Hours/User</h3>
            <p class="stat-number">
                <?php echo count($team_members) > 0 ? round($total_hours / count($team_members), 1) : 0; ?>
            </p>
        </div>
    </div>

    <div class="admin-section">
        <h2>CPD Entries (<?php echo count($team_entries); ?>)</h2>
        
        <?php if (count($team_entries) > 0): ?>
            <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
                <a href="admin_team_export.php?id=<?php echo $team_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&category=<?php echo $category; ?>" 
                   class="btn btn-small" style="background: #28a745;">
                    📥 Export CSV
                </a>
            </div>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Hours</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                        <td>
                            <a href="admin_edit_user.php?id=<?php echo $entry['user_id']; ?>">
                                <?php echo htmlspecialchars($entry['username']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($entry['title']); ?></td>
                        <td><?php echo htmlspecialchars($entry['category']); ?></td>
                        <td><?php echo htmlspecialchars($entry['hours']); ?></td>
                        <td style="max-width: 300px;">
                            <?php 
                            $desc = htmlspecialchars($entry['description']);
                            echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No CPD entries found for this team.</p>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h2>Statistics</h2>
        <div class="charts-grid">
            <div class="chart-container">
                <h3>Entries by User</h3>
                <?php if (!empty($entries_by_user)): ?>
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Entries</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_entries = count($team_entries);
                            foreach ($entries_by_user as $user => $count): 
                                $percentage = $total_entries > 0 ? round(($count / $total_entries) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user); ?></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="percentage-bar">
                                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        <span><?php echo $percentage; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No data available.</p>
                <?php endif; ?>
            </div>
            
            <div class="chart-container">
                <h3>Entries by Category</h3>
                <?php if (!empty($entries_by_category)): ?>
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Entries</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_entries = count($team_entries);
                            foreach ($entries_by_category as $category_name => $count): 
                                $percentage = $total_entries > 0 ? round(($count / $total_entries) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category_name); ?></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="percentage-bar">
                                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        <span><?php echo $percentage; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .filter-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
    }
    
    .chart-container {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .chart-container h3 {
        margin-top: 0;
        color: #2c3e50;
        border-bottom: 2px solid #f8f9fa;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .simple-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .simple-table th,
    .simple-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .simple-table th {
        font-weight: 600;
        color: #495057;
        background: #f8f9fa;
    }
    
    .percentage-bar {
        background: #e9ecef;
        border-radius: 4px;
        height: 24px;
        position: relative;
        overflow: hidden;
    }
    
    .bar-fill {
        background: #007cba;
        height: 100%;
        position: absolute;
        left: 0;
        top: 0;
        transition: width 0.3s ease;
    }
    
    .percentage-bar span {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
    }
    
    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>