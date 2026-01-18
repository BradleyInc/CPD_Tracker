<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/review_functions.php';

checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: manager_dashboard.php');
    exit();
}

$team_id = intval($_GET['id']);

// Check if manager/partner has access to this team
if (isManager() && !isManagerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: manager_dashboard.php');
    exit();
}

if (isPartner() && !isPartnerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: manager_dashboard.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get team CPD summary
$team_summary = getTeamCPDSummary($pdo, $team_id, $start_date, $end_date);

// Get pending reviews count for this team
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM cpd_entries ce
    JOIN user_teams ut ON ce.user_id = ut.user_id
    WHERE ut.team_id = ? AND ce.review_status = 'pending'
");
$stmt->execute([$team_id]);
$pending_reviews_count = $stmt->fetchColumn();

// Calculate totals and stats
$total_entries = 0;
$total_hours = 0;
$total_approved = 0;
$members_with_entries = 0;

foreach ($team_summary as $member) {
    $total_entries += $member['total_entries'];
    $total_hours += $member['total_hours'];
    if ($member['total_entries'] > 0) {
        $members_with_entries++;
    }
}

// Get recent team entries
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

$query .= " ORDER BY ce.date_completed DESC LIMIT 10";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$recent_entries = $stmt->fetchAll();

$pageTitle = 'Team: ' . $team['name'];
include 'includes/header.php';
?>

<style>
    .team-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    }
    
    .team-hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
    }
    
    .team-hero-info h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }
    
    .team-hero-info p {
        margin: 0;
        opacity: 0.9;
        font-size: 1.1rem;
    }
    
    .team-quick-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    
    .hero-btn {
        padding: 0.75rem 1.5rem;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
    }
    
    .hero-btn.primary {
        background: white;
        color: #667eea;
        border-color: white;
    }
    
    .hero-btn.primary:hover {
        background: #f8f9fa;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-card.alert {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-left: 4px solid #ffc107;
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .stat-card.alert .stat-label {
        color: #856404;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .stat-card.alert .stat-value {
        color: #856404;
    }
    
    .stat-sublabel {
        font-size: 0.85rem;
        color: #999;
    }
    
    .filter-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1.5rem;
    }
    
    .filter-card h3 {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .content-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    
    .content-card-header {
        padding: 1.5rem;
        border-bottom: 2px solid #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .content-card-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #2c3e50;
    }
    
    .content-card-body {
        padding: 1.5rem;
    }
    
    .members-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .members-table thead {
        background: #f8f9fa;
    }
    
    .members-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .members-table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .members-table tbody tr {
        transition: background 0.2s;
    }
    
    .members-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .member-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .member-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
    }
    
    .member-name {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .progress-bar-wrapper {
        width: 100%;
        max-width: 120px;
    }
    
    .progress-bar-mini {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.25rem;
    }
    
    .progress-fill-mini {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.5s ease;
    }
    
    .progress-text {
        font-size: 0.75rem;
        color: #666;
    }
    
    .activity-item {
        padding: 1rem;
        border-left: 3px solid #e1e8ed;
        margin-bottom: 0.75rem;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .activity-item:hover {
        border-left-color: #667eea;
        background: #f8f9fa;
    }
    
    .activity-item:last-child {
        margin-bottom: 0;
    }
    
    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
    
    .activity-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .activity-meta {
        font-size: 0.85rem;
        color: #666;
    }
    
    .activity-hours {
        font-weight: 600;
        color: #667eea;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    @media (max-width: 992px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
        
        .team-hero-content {
            flex-direction: column;
        }
        
        .team-quick-actions {
            width: 100%;
        }
        
        .hero-btn {
            flex: 1;
            justify-content: center;
        }
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <!-- Team Hero -->
    <div class="team-hero">
        <div class="team-hero-content">
            <div class="team-hero-info">
                <h1><?php echo htmlspecialchars($team['name']); ?></h1>
                <p><?php echo count($team_summary); ?> team members • <?php echo $total_entries; ?> total entries</p>
            </div>
            <div class="team-quick-actions">
                <?php if ($pending_reviews_count > 0): ?>
                <a href="manager_reviews.php?team=<?php echo $team_id; ?>&status=pending" class="hero-btn primary">
                    ⏳ <?php echo $pending_reviews_count; ?> Pending Reviews
                </a>
                <?php endif; ?>
                <a href="manager_team_members.php?id=<?php echo $team_id; ?>" class="hero-btn">
                    👥 View Members
                </a>
                <a href="manager_dashboard.php" class="hero-btn">
                    ← Back
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Team Members</div>
            <div class="stat-value"><?php echo count($team_summary); ?></div>
            <div class="stat-sublabel"><?php echo $members_with_entries; ?> with entries</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Entries</div>
            <div class="stat-value"><?php echo $total_entries; ?></div>
            <div class="stat-sublabel">All time</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Hours</div>
            <div class="stat-value"><?php echo round($total_hours, 1); ?></div>
            <div class="stat-sublabel">
                Avg: <?php echo count($team_summary) > 0 ? round($total_hours / count($team_summary), 1) : 0; ?> hrs/member
            </div>
        </div>
        
        <?php if ($pending_reviews_count > 0): ?>
        <div class="stat-card alert">
            <div class="stat-label">⏳ Pending Reviews</div>
            <div class="stat-value"><?php echo $pending_reviews_count; ?></div>
            <a href="manager_reviews.php?team=<?php echo $team_id; ?>&status=pending" class="stat-link" style="color: #856404;">
                Review now →
            </a>
        </div>
        <?php else: ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745;">
            <div class="stat-label" style="color: #155724;">✓ All Reviewed</div>
            <div class="stat-value" style="color: #155724;">0</div>
            <div class="stat-sublabel" style="color: #155724;">No pending reviews</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <h3>📅 Filter by Date Range</h3>
        <form method="GET">
            <input type="hidden" name="id" value="<?php echo $team_id; ?>">
            <div class="filter-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>End Date:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="manager_team_view.php?id=<?php echo $team_id; ?>" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Team Members Performance -->
        <div class="content-card">
            <div class="content-card-header">
                <h2>👥 Team Performance</h2>
                <a href="manager_team_members.php?id=<?php echo $team_id; ?>" class="stat-link">View all →</a>
            </div>
            <div class="content-card-body" style="padding: 0;">
                <?php if (count($team_summary) > 0): ?>
                    <table class="members-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Entries</th>
                                <th>Hours</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Sort by total hours descending
                            usort($team_summary, function($a, $b) {
                                return $b['total_hours'] <=> $a['total_hours'];
                            });
                            
                            $max_hours = count($team_summary) > 0 ? max(array_column($team_summary, 'total_hours')) : 1;
                            
                            foreach ($team_summary as $member): 
                                $progress_percent = $max_hours > 0 ? ($member['total_hours'] / $max_hours) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="member-info">
                                        <div class="member-avatar">
                                            <?php echo strtoupper(substr($member['username'], 0, 1)); ?>
                                        </div>
                                        <span class="member-name"><?php echo htmlspecialchars($member['username']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $member['total_entries']; ?></td>
                                <td><strong><?php echo round($member['total_hours'], 1); ?></strong></td>
                                <td>
                                    <div class="progress-bar-wrapper">
                                        <div class="progress-bar-mini">
                                            <div class="progress-fill-mini" style="width: <?php echo $progress_percent; ?>%"></div>
                                        </div>
                                        <div class="progress-text"><?php echo round($progress_percent); ?>% of top</div>
                                    </div>
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
                    <div class="empty-state">
                        <div class="empty-state-icon">👥</div>
                        <p>No team members found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-card">
            <div class="content-card-header">
                <h2>📊 Recent Activity</h2>
            </div>
            <div class="content-card-body">
                <?php if (count($recent_entries) > 0): ?>
                    <?php foreach ($recent_entries as $entry): ?>
                        <div class="activity-item">
                            <div class="activity-header">
                                <div>
                                    <div class="activity-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($entry['username']); ?> • 
                                        <?php echo date('M d, Y', strtotime($entry['date_completed'])); ?>
                                    </div>
                                </div>
                                <div class="activity-hours"><?php echo $entry['hours']; ?> hrs</div>
                            </div>
                            <?php if ($entry['review_status'] === 'pending'): ?>
                                <div style="margin-top: 0.5rem;">
                                    <span class="status-badge status-pending" style="font-size: 0.75rem;">⏳ Pending</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <p>No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>