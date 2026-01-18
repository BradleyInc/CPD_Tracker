<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/review_functions.php';
require_once 'includes/goal_functions.php';

checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Manager Dashboard';
include 'includes/header.php';

// Get teams managed by this manager
$managed_teams = getManagerTeams($pdo, $_SESSION['user_id']);

// Get pending reviews with team context
$stmt = $pdo->prepare("
    SELECT ce.*, u.username, t.name as team_name, t.id as team_id
    FROM cpd_entries ce
    JOIN users u ON ce.user_id = u.id
    JOIN user_teams ut ON u.id = ut.user_id
    JOIN teams t ON ut.team_id = t.id
    JOIN team_managers tm ON t.id = tm.team_id
    WHERE tm.manager_id = ? AND ce.review_status = 'pending'
    ORDER BY ce.date_completed DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$pending_reviews = $stmt->fetchAll();

// Get recent team activity
$stmt = $pdo->prepare("
    SELECT ce.*, u.username, t.name as team_name, t.id as team_id
    FROM cpd_entries ce
    JOIN users u ON ce.user_id = u.id
    JOIN user_teams ut ON u.id = ut.user_id
    JOIN teams t ON ut.team_id = t.id
    JOIN team_managers tm ON t.id = tm.team_id
    WHERE tm.manager_id = ?
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll();

// Get ALL goals for this manager first (for debugging)
$all_manager_goals = isManager() ? getManagerGoals($pdo, $_SESSION['user_id']) : getPartnerGoals($pdo, $_SESSION['user_id']);

// Debug: See all goals
error_log("Manager ID: " . $_SESSION['user_id']);
error_log("Total goals from getManagerGoals: " . count($all_manager_goals));
foreach ($all_manager_goals as $g) {
    error_log("Goal: " . $g['title'] . " | Status: " . $g['status'] . " | Days remaining: " . ($g['days_remaining'] ?? 'NULL'));
}

// Get goals needing attention
$approaching_goals = getApproachingDeadlineGoals($pdo, 7, $_SESSION['user_id']);
$overdue_goals = getOverdueGoals($pdo, $_SESSION['user_id']);

error_log("Approaching goals (7 days): " . count($approaching_goals));
error_log("Overdue goals: " . count($overdue_goals));

// Calculate stats
$total_team_members = 0;
$total_cpd_entries = 0;
$total_cpd_hours = 0;
$pending_count = count($pending_reviews);

foreach ($managed_teams as $team) {
    $total_team_members += $team['member_count'];
    $stats = getTeamCPDStats($pdo, $team['id']);
    $total_cpd_entries += $stats['total_entries'] ?? 0;
    $total_cpd_hours += $stats['total_hours'] ?? 0;
}
?>

<style>
    /* Hero Section */
    .manager-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    }
    
    .manager-hero h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }
    
    .manager-hero p {
        margin: 0;
        opacity: 0.9;
    }
    
    /* Quick Stats */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card-compact {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    
    .stat-card-compact:hover {
        transform: translateY(-2px);
    }
    
    .stat-card-compact.urgent {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-left: 4px solid #ffc107;
    }
    
    .stat-card-compact.urgent .stat-value {
        color: #856404;
    }
    
    .stat-label-small {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .stat-link {
        font-size: 0.85rem;
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }
    
    .stat-link:hover {
        text-decoration: underline;
    }
    
    /* Action Cards Grid */
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .action-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    
    .action-card-header {
        padding: 1.5rem;
        border-bottom: 2px solid #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .action-card-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .badge-count {
        background: #ef4444;
        color: white;
        padding: 0.25rem 0.6rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    
    .action-card-body {
        padding: 1.5rem;
    }
    
    /* Pending Review Item */
    .review-item {
        padding: 1rem;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .review-item:hover {
        border-color: #667eea;
        background: #f8f9fa;
        transform: translateX(4px);
    }
    
    .review-item:last-child {
        margin-bottom: 0;
    }
    
    .review-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }
    
    .review-item-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .review-item-meta {
        font-size: 0.85rem;
        color: #666;
    }
    
    .review-item-team {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        background: #e3f2fd;
        color: #1976d2;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .review-item-hours {
        font-weight: 600;
        color: #667eea;
    }
    
    .quick-action-btn {
        padding: 0.4rem 0.8rem;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .quick-action-btn:hover {
        background: #5568d3;
    }
    
    /* Team Cards */
    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }
    
    .team-mini-card {
        background: white;
        padding: 1.25rem;
        border-radius: 8px;
        border: 1px solid #e1e8ed;
        transition: all 0.2s;
    }
    
    .team-mini-card:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    
    .team-mini-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .team-mini-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #2c3e50;
    }
    
    .team-member-count {
        background: #f8f9fa;
        padding: 0.25rem 0.6rem;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #666;
    }
    
    .team-quick-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .team-stat-mini {
        text-align: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .team-stat-mini strong {
        display: block;
        font-size: 1.25rem;
        color: #667eea;
    }
    
    .team-stat-mini span {
        font-size: 0.8rem;
        color: #666;
    }
    
    .team-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .team-actions a {
        flex: 1;
        text-align: center;
        padding: 0.5rem;
        background: #f8f9fa;
        color: #2c3e50;
        text-decoration: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .team-actions a:hover {
        background: #667eea;
        color: white;
    }
    
    /* Goal Alert */
    .goal-alert {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }
    
    .goal-alert-title {
        font-weight: 600;
        color: #856404;
        margin-bottom: 0.25rem;
    }
    
    .goal-alert-meta {
        font-size: 0.85rem;
        color: #856404;
        opacity: 0.8;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .action-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="container">
    <!-- Hero Section -->
    <div class="manager-hero">
        <h1>👋 Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <p>Manage your teams, review CPD entries, and track goals</p>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card-compact <?php echo $pending_count > 0 ? 'urgent' : ''; ?>">
            <div class="stat-label-small">Pending Reviews</div>
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <?php if ($pending_count > 0): ?>
                <a href="#pending-reviews" class="stat-link">Review now →</a>
            <?php endif; ?>
        </div>
        
        <div class="stat-card-compact">
            <div class="stat-label-small">Teams Managed</div>
            <div class="stat-value"><?php echo count($managed_teams); ?></div>
            <a href="#my-teams" class="stat-link">View teams →</a>
        </div>
        
        <div class="stat-card-compact">
            <div class="stat-label-small">Team Members</div>
            <div class="stat-value"><?php echo $total_team_members; ?></div>
        </div>
        
        <div class="stat-card-compact">
            <div class="stat-label-small">Total CPD Hours</div>
            <div class="stat-value"><?php echo round($total_cpd_hours, 1); ?></div>
        </div>
    </div>

    <!-- Main Action Grid -->
    <div class="action-grid">
        <!-- Pending Reviews -->
        <div class="action-card" id="pending-reviews">
            <div class="action-card-header">
                <h2>
                    ⏳ Pending Reviews
                    <?php if ($pending_count > 0): ?>
                        <span class="badge-count"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </h2>
                <?php if ($pending_count > 10): ?>
                    <a href="manager_reviews.php" class="stat-link">View all →</a>
                <?php endif; ?>
            </div>
            <div class="action-card-body">
                <?php if (count($pending_reviews) > 0): ?>
                    <?php foreach (array_slice($pending_reviews, 0, 5) as $entry): ?>
                        <div class="review-item" onclick="openQuickReview(<?php echo $entry['id']; ?>)">
                            <div class="review-item-header">
                                <div>
                                    <div class="review-item-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                                    <div class="review-item-meta">
                                        <?php echo htmlspecialchars($entry['username']); ?> • 
                                        <span class="review-item-team"><?php echo htmlspecialchars($entry['team_name']); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="review-item-hours"><?php echo $entry['hours']; ?> hrs</div>
                                    <div class="review-item-meta"><?php echo date('M d', strtotime($entry['date_completed'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($pending_count > 5): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="manager_reviews.php" class="quick-action-btn">View All <?php echo $pending_count; ?> Pending Reviews</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>All caught up! No pending reviews.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Goals Needing Attention -->
        <div class="action-card">
            <div class="action-card-header">
                <h2>🎯 Goals Status</h2>
                <a href="manager_goals.php" class="stat-link">Manage →</a>
            </div>
            <div class="action-card-body">
                <?php if (count($overdue_goals) > 0 || count($approaching_goals) > 0): ?>
                    <?php foreach (array_slice($overdue_goals, 0, 3) as $goal): ?>
                        <div class="goal-alert" style="background: #fee; border-left-color: #dc3545;">
                            <div class="goal-alert-title" style="color: #721c24;">
                                🔴 <?php echo htmlspecialchars($goal['title']); ?>
                            </div>
                            <div class="goal-alert-meta" style="color: #721c24;">
                                <?php echo htmlspecialchars($goal['target_name']); ?> • 
                                Overdue by <?php echo abs($goal['days_remaining']); ?> days
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach (array_slice($approaching_goals, 0, 2) as $goal): ?>
                        <div class="goal-alert">
                            <div class="goal-alert-title">
                                ⚠️ <?php echo htmlspecialchars($goal['title']); ?>
                            </div>
                            <div class="goal-alert-meta">
                                <?php echo htmlspecialchars($goal['target_name']); ?> • 
                                <?php echo $goal['days_remaining']; ?> days left
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($overdue_goals) > 3 || count($approaching_goals) > 2): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="manager_goals.php" class="quick-action-btn">View All Goals</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php 
                    // If no urgent goals, show active goals
                    $all_active_goals = isManager() ? getManagerGoals($pdo, $_SESSION['user_id']) : getPartnerGoals($pdo, $_SESSION['user_id']);
                    $active_only = array_filter($all_active_goals, function($g) { return $g['status'] === 'active'; });
                    ?>
                    
                    <?php if (count($active_only) > 0): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <p><strong>All goals on track!</strong></p>
                            <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                                You have <?php echo count($active_only); ?> active goal(s)
                            </p>
                            <a href="manager_goals.php" class="stat-link" style="margin-top: 0.5rem; display: inline-block;">View all goals →</a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🎯</div>
                            <p>No active goals</p>
                            <a href="manager_goals.php" class="stat-link">Create new goal →</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="action-card">
            <div class="action-card-header">
                <h2>📊 Recent Activity</h2>
            </div>
            <div class="action-card-body">
                <?php if (count($recent_activity) > 0): ?>
                    <?php foreach ($recent_activity as $entry): ?>
                        <div class="review-item">
                            <div class="review-item-header">
                                <div>
                                    <div class="review-item-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                                    <div class="review-item-meta">
                                        <?php echo htmlspecialchars($entry['username']); ?> • 
                                        <span class="review-item-team"><?php echo htmlspecialchars($entry['team_name']); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($entry['review_status'] === 'approved'): ?>
                                        <span style="color: #28a745; font-size: 1.2rem;">✓</span>
                                    <?php else: ?>
                                        <span style="color: #ffc107; font-size: 1.2rem;">⏳</span>
                                    <?php endif; ?>
                                </div>
                            </div>
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

    <!-- My Teams Section -->
    <div class="admin-section" id="my-teams">
        <h2>My Teams (<?php echo count($managed_teams); ?>)</h2>
        
        <div class="team-grid">
            <?php foreach ($managed_teams as $team): 
                $team_stats = getTeamCPDStats($pdo, $team['id']);
                $pending_team_reviews = getPendingReviewCount($pdo, $_SESSION['user_id']);
            ?>
                <div class="team-mini-card">
                    <div class="team-mini-header">
                        <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                        <span class="team-member-count"><?php echo $team['member_count']; ?> members</span>
                    </div>
                    
                    <div class="team-quick-stats">
                        <div class="team-stat-mini">
                            <strong><?php echo $team_stats['total_entries'] ?? 0; ?></strong>
                            <span>Entries</span>
                        </div>
                        <div class="team-stat-mini">
                            <strong><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></strong>
                            <span>Hours</span>
                        </div>
                    </div>
                    
                    <div class="team-actions">
                        <a href="manager_team_view.php?id=<?php echo $team['id']; ?>">Overview</a>
                        <a href="manager_team_members.php?id=<?php echo $team['id']; ?>">Members</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Review Modal -->
<div id="quickReviewModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeQuickReview()">&times;</span>
        <h2>Quick Review</h2>
        <div id="quickReviewContent"></div>
    </div>
</div>

<script>
function openQuickReview(entryId) {
    window.location.href = 'manager_quick_review.php?entry_id=' + entryId;
}

function closeQuickReview() {
    document.getElementById('quickReviewModal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>