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

// Get team members with extended stats
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at, u.archived, ut.joined_at,
           r.name as role_name,
           COUNT(DISTINCT ce.id) as total_entries,
           COALESCE(SUM(ce.hours), 0) as total_hours,
           MAX(ce.date_completed) as last_entry_date,
           SUM(CASE WHEN ce.review_status = 'pending' THEN 1 ELSE 0 END) as pending_reviews
    FROM users u
    JOIN user_teams ut ON u.id = ut.user_id
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN cpd_entries ce ON u.id = ce.user_id
    WHERE ut.team_id = ?
    GROUP BY u.id, u.username, u.email, u.created_at, u.archived, ut.joined_at, r.name
    ORDER BY u.archived ASC, total_hours DESC, u.username
");
$stmt->execute([$team_id]);
$team_members = $stmt->fetchAll();

// Calculate stats
$active_members = array_filter($team_members, function($m) { return !$m['archived']; });
$total_pending = array_sum(array_column($team_members, 'pending_reviews'));
$members_with_pending = count(array_filter($team_members, function($m) { return $m['pending_reviews'] > 0; }));

$pageTitle = 'Team Members: ' . $team['name'];
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
    
    .stat-card.alert .stat-sublabel {
        color: #856404;
    }
    
    .members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    
    .member-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    
    .member-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        border-color: #667eea;
    }
    
    .member-card.has-pending {
        border-left: 4px solid #ffc107;
        background: linear-gradient(to right, #fffef5 0%, white 20%);
    }
    
    .member-card.archived {
        opacity: 0.6;
        background: #f8f9fa;
    }
    
    .member-card-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
    }
    
    .member-info-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }
    
    .member-avatar-large {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .member-details {
        flex: 1;
        min-width: 0;
    }
    
    .member-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .member-email {
        font-size: 0.85rem;
        color: #666;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .member-badges {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.6rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge.role {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .badge.pending {
        background: #fff3cd;
        color: #856404;
        animation: pulse-badge 2s infinite;
    }
    
    .badge.archived {
        background: #f8d7da;
        color: #721c24;
    }
    
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .member-card-body {
        padding: 1.5rem;
    }
    
    .member-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .member-stat {
        text-align: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .member-stat-value {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 0.25rem;
    }
    
    .member-stat-label {
        display: block;
        font-size: 0.75rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .member-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .member-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .member-actions .btn {
        flex: 1;
        text-align: center;
        justify-content: center;
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #999;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
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
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .members-grid {
            grid-template-columns: 1fr;
        }
        
        .member-stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
    }
</style>

<div class="container">
    <!-- Team Hero -->
    <div class="team-hero">
        <div class="team-hero-content">
            <div class="team-hero-info">
                <h1><?php echo htmlspecialchars($team['name']); ?> - Members</h1>
                <p><?php echo count($active_members); ?> active members</p>
            </div>
            <div class="team-quick-actions">
                <?php if ($total_pending > 0): ?>
                <a href="manager_reviews.php?team=<?php echo $team_id; ?>&status=pending" class="hero-btn primary">
                    ⏳ <?php echo $total_pending; ?> Pending Reviews
                </a>
                <?php endif; ?>
                <a href="manager_team_view.php?id=<?php echo $team_id; ?>" class="hero-btn">
                    📊 Team Overview
                </a>
                <a href="manager_dashboard.php" class="hero-btn">
                    ← Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Members</div>
            <div class="stat-value"><?php echo count($team_members); ?></div>
            <div class="stat-sublabel"><?php echo count($active_members); ?> active</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total CPD Entries</div>
            <div class="stat-value"><?php echo array_sum(array_column($team_members, 'total_entries')); ?></div>
            <div class="stat-sublabel">All team members</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-label">Total Hours</div>
            <div class="stat-value"><?php echo round(array_sum(array_column($team_members, 'total_hours')), 1); ?></div>
            <div class="stat-sublabel">
                Avg: <?php echo count($active_members) > 0 ? round(array_sum(array_column($team_members, 'total_hours')) / count($active_members), 1) : 0; ?> hrs/member
            </div>
        </div>
        
        <?php if ($total_pending > 0): ?>
        <div class="stat-card alert">
            <div class="stat-label">⏳ Pending Reviews</div>
            <div class="stat-value"><?php echo $total_pending; ?></div>
            <div class="stat-sublabel"><?php echo $members_with_pending; ?> member(s)</div>
        </div>
        <?php else: ?>
        <div class="stat-card" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745;">
            <div class="stat-label" style="color: #155724;">✓ All Reviewed</div>
            <div class="stat-value" style="color: #155724;">0</div>
            <div class="stat-sublabel" style="color: #155724;">No pending reviews</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Members Grid -->
    <?php if (count($team_members) > 0): ?>
        <div class="members-grid">
            <?php foreach ($team_members as $member): ?>
                <div class="member-card <?php echo $member['pending_reviews'] > 0 ? 'has-pending' : ''; ?> <?php echo $member['archived'] ? 'archived' : ''; ?>">
                    <div class="member-card-header">
                        <div class="member-info-header">
                            <div class="member-avatar-large">
                                <?php echo strtoupper(substr($member['username'], 0, 1)); ?>
                            </div>
                            <div class="member-details">
                                <div class="member-name"><?php echo htmlspecialchars($member['username']); ?></div>
                                <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                            </div>
                        </div>
                        <div class="member-badges">
                            <span class="badge role"><?php echo ucfirst($member['role_name']); ?></span>
                            <?php if ($member['pending_reviews'] > 0): ?>
                                <span class="badge pending">⏳ <?php echo $member['pending_reviews']; ?> pending</span>
                            <?php endif; ?>
                            <?php if ($member['archived']): ?>
                                <span class="badge archived">Archived</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="member-card-body">
                        <div class="member-stats-grid">
                            <div class="member-stat">
                                <span class="member-stat-value"><?php echo $member['total_entries']; ?></span>
                                <span class="member-stat-label">Entries</span>
                            </div>
                            <div class="member-stat">
                                <span class="member-stat-value"><?php echo round($member['total_hours'], 1); ?></span>
                                <span class="member-stat-label">Hours</span>
                            </div>
                            <div class="member-stat">
                                <span class="member-stat-value">
                                    <?php 
                                    if ($member['last_entry_date']) {
                                        $days_ago = (time() - strtotime($member['last_entry_date'])) / (60 * 60 * 24);
                                        echo round($days_ago);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </span>
                                <span class="member-stat-label">Days Ago</span>
                            </div>
                        </div>
                        
                        <div class="member-meta">
                            <div>
                                <strong>Joined:</strong> <?php echo date('M d, Y', strtotime($member['joined_at'])); ?>
                            </div>
                            <?php if ($member['last_entry_date']): ?>
                            <div>
                                <strong>Last Entry:</strong> <?php echo date('M d, Y', strtotime($member['last_entry_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="member-actions">
                            <a href="manager_member_detail.php?id=<?php echo $team_id; ?>&user_id=<?php echo $member['id']; ?>" 
                               class="btn btn-small">
                                <?php echo $member['pending_reviews'] > 0 ? '⏳ Review' : '📊 View'; ?> CPD
                            </a>
                            <?php if (isManager()): ?>
                            <a href="manager_team_manage_users.php?id=<?php echo $team_id; ?>#user-<?php echo $member['id']; ?>" 
                               class="btn btn-small btn-secondary">
                                Manage
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h3>No team members yet</h3>
            <p>Add members to get started</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>