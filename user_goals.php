<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/goal_functions.php';

// Check authentication
checkAuth();

$pageTitle = 'My CPD Goals';
include 'includes/header.php';

// Get user's goals
$active_goals = getUserGoals($pdo, $_SESSION['user_id'], 'active');
$completed_goals = getUserGoals($pdo, $_SESSION['user_id'], 'completed');
$overdue_goals = getUserGoals($pdo, $_SESSION['user_id'], 'overdue');
$goal_stats = getGoalStatistics($pdo, $_SESSION['user_id']);

// Separate by type
$individual_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'individual'; });
$team_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'team'; });
$dept_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'department'; });
?>

<div class="container">
    <div class="page-header">
        <h1>🎯 My CPD Goals</h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">Track your professional development targets and progress</p>
    </div>

    <!-- Alerts -->
    <?php if (count($overdue_goals) > 0): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;">
        <strong>⚠️ You have <?php echo count($overdue_goals); ?> overdue goal<?php echo count($overdue_goals) > 1 ? 's' : ''; ?>!</strong>
        Please review and take action to get back on track.
        <a href="#overdue-section" style="color: inherit; text-decoration: underline;">View overdue goals</a>
    </div>
    <?php endif; ?>

    <!-- Goals Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Goals</h3>
            <p class="stat-number"><?php echo $goal_stats['total_goals'] ?? 0; ?></p>
            <small>All time</small>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white;">Active Goals</h3>
            <p class="stat-number"><?php echo $goal_stats['active_goals'] ?? 0; ?></p>
            <small style="color: white;">Currently working on</small>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h3 style="color: white;">Completed</h3>
            <p class="stat-number"><?php echo $goal_stats['completed_goals'] ?? 0; ?></p>
            <small style="color: white;">Successfully achieved</small>
        </div>
        <div class="stat-card">
            <h3>Average Progress</h3>
            <p class="stat-number"><?php echo round($goal_stats['avg_progress'] ?? 0, 1); ?>%</p>
            <small>Across active goals</small>
        </div>
    </div>

    <!-- Individual Goals -->
    <?php if (count($individual_goals) > 0): ?>
    <div class="goals-section">
        <h2>👤 My Individual Goals (<?php echo count($individual_goals); ?>)</h2>
        <div class="goals-grid">
            <?php foreach ($individual_goals as $goal): ?>
                <?php
                $progress = $goal['progress_percentage'] ?? 0;
                $is_warning = $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
                $is_danger = $goal['days_remaining'] <= 3 && $goal['days_remaining'] > 0;
                ?>
                <div class="goal-card <?php echo $is_danger ? 'danger' : ($is_warning ? 'warning' : ''); ?>">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-badge individual">Individual</span>
                    </div>

                    <?php if ($goal['description']): ?>
                    <p class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></p>
                    <?php endif; ?>

                    <div class="goal-details">
                        <div class="detail-item">
                            <span class="label">Set by:</span>
                            <span class="value"><?php echo htmlspecialchars($goal['set_by_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Deadline:</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-value"><?php echo round($progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours</span>
                            <?php if ($goal['target_entries']): ?>
                                <span><?php echo $goal['current_entries'] ?? 0; ?> / <?php echo $goal['target_entries']; ?> entries</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="goal-footer">
                        <?php if ($goal['days_remaining'] < 0): ?>
                            <span class="days-badge overdue">Overdue</span>
                        <?php elseif ($goal['days_remaining'] == 0): ?>
                            <span class="days-badge danger">Due today!</span>
                        <?php elseif ($goal['days_remaining'] <= 3): ?>
                            <span class="days-badge danger"><?php echo $goal['days_remaining']; ?> days left</span>
                        <?php elseif ($goal['days_remaining'] <= 7): ?>
                            <span class="days-badge warning"><?php echo $goal['days_remaining']; ?> days left</span>
                        <?php else: ?>
                            <span class="days-badge normal"><?php echo $goal['days_remaining']; ?> days remaining</span>
                        <?php endif; ?>
                        
                        <?php if ($goal['last_entry_date']): ?>
                            <small class="last-entry">Last entry: <?php echo date('M d', strtotime($goal['last_entry_date'])); ?></small>
                        <?php else: ?>
                            <small class="last-entry">No entries yet</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Team Goals -->
    <?php if (count($team_goals) > 0): ?>
    <div class="goals-section">
        <h2>👥 My Team Goals (<?php echo count($team_goals); ?>)</h2>
        <div class="goals-grid">
            <?php foreach ($team_goals as $goal): ?>
                <?php
                $progress = $goal['progress_percentage'] ?? 0;
                $is_warning = $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
                $is_danger = $goal['days_remaining'] <= 3 && $goal['days_remaining'] > 0;
                ?>
                <div class="goal-card <?php echo $is_danger ? 'danger' : ($is_warning ? 'warning' : ''); ?>">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-badge team">Team</span>
                    </div>

                    <p class="goal-team"><strong>Team:</strong> <?php echo htmlspecialchars($goal['group_name']); ?></p>

                    <?php if ($goal['description']): ?>
                    <p class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></p>
                    <?php endif; ?>

                    <div class="goal-details">
                        <div class="detail-item">
                            <span class="label">Set by:</span>
                            <span class="value"><?php echo htmlspecialchars($goal['set_by_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Deadline:</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">My Progress</span>
                            <span class="progress-value"><?php echo round($progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill team" style="width: <?php echo min($progress, 100); ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours</span>
                            <?php if ($goal['target_entries']): ?>
                                <span><?php echo $goal['current_entries'] ?? 0; ?> / <?php echo $goal['target_entries']; ?> entries</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="goal-footer">
                        <?php if ($goal['days_remaining'] < 0): ?>
                            <span class="days-badge overdue">Overdue</span>
                        <?php elseif ($goal['days_remaining'] == 0): ?>
                            <span class="days-badge danger">Due today!</span>
                        <?php elseif ($goal['days_remaining'] <= 3): ?>
                            <span class="days-badge danger"><?php echo $goal['days_remaining']; ?> days left</span>
                        <?php elseif ($goal['days_remaining'] <= 7): ?>
                            <span class="days-badge warning"><?php echo $goal['days_remaining']; ?> days left</span>
                        <?php else: ?>
                            <span class="days-badge normal"><?php echo $goal['days_remaining']; ?> days remaining</span>
                        <?php endif; ?>
                        
                        <a href="user_goal_team_comparison.php?id=<?php echo $goal['id']; ?>" class="btn btn-small">Compare with Team</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Department Goals -->
    <?php if (count($dept_goals) > 0): ?>
    <div class="goals-section">
        <h2>🏢 My Department Goals (<?php echo count($dept_goals); ?>)</h2>
        <div class="goals-grid">
            <?php foreach ($dept_goals as $goal): ?>
                <?php
                $progress = $goal['progress_percentage'] ?? 0;
                $is_warning = $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
                $is_danger = $goal['days_remaining'] <= 3 && $goal['days_remaining'] > 0;
                ?>
                <div class="goal-card <?php echo $is_danger ? 'danger' : ($is_warning ? 'warning' : ''); ?>">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-badge department">Department</span>
                    </div>

                    <p class="goal-team"><strong>Department:</strong> <?php echo htmlspecialchars($goal['group_name']); ?></p>

                    <?php if ($goal['description']): ?>
                    <p class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></p>
                    <?php endif; ?>

                    <div class="goal-details">
                        <div class="detail-item">
                            <span class="label">Deadline:</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">My Progress</span>
                            <span class="progress-value"><?php echo round($progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill department" style="width: <?php echo min($progress, 100); ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours</span>
                        </div>
                    </div>

                    <div class="goal-footer">
                        <?php if ($goal['days_remaining'] < 0): ?>
                            <span class="days-badge overdue">Overdue</span>
                        <?php elseif ($goal['days_remaining'] <= 7): ?>
                            <span class="days-badge warning"><?php echo $goal['days_remaining']; ?> days left</span>
                        <?php else: ?>
                            <span class="days-badge normal"><?php echo $goal['days_remaining']; ?> days remaining</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Overdue Goals -->
    <?php if (count($overdue_goals) > 0): ?>
    <div id="overdue-section" class="goals-section" style="border: 2px solid #dc3545; border-radius: 8px; padding: 1.5rem;">
        <h2 style="color: #dc3545;">🔴 Overdue Goals (<?php echo count($overdue_goals); ?>)</h2>
        <p style="color: #721c24; margin-bottom: 1rem;">These goals have passed their deadline. Please catch up on your CPD entries!</p>
        <div class="goals-grid">
            <?php foreach ($overdue_goals as $goal): ?>
                <?php $progress = $goal['progress_percentage'] ?? 0; ?>
                <div class="goal-card overdue">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-badge <?php echo $goal['goal_type']; ?>"><?php echo ucfirst($goal['goal_type']); ?></span>
                    </div>

                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-value"><?php echo round($progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill overdue" style="width: <?php echo min($progress, 100); ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours</span>
                        </div>
                    </div>

                    <div class="goal-footer">
                        <span class="days-badge overdue">Overdue by <?php echo abs($goal['days_remaining']); ?> days</span>
                        <a href="dashboard.php" class="btn btn-small" style="background: #dc3545;">Add CPD Entry</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Completed Goals -->
    <?php if (count($completed_goals) > 0): ?>
    <div class="goals-section">
        <h2>✅ Completed Goals (<?php echo count($completed_goals); ?>)</h2>
        <div class="completed-goals-list">
            <?php foreach ($completed_goals as $goal): ?>
                <div class="completed-goal-item">
                    <div class="completed-icon">✅</div>
                    <div class="completed-content">
                        <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                        <small>Completed on <?php echo date('M d, Y', strtotime($goal['completed_at'])); ?></small>
                    </div>
                    <span class="goal-badge <?php echo $goal['goal_type']; ?>"><?php echo ucfirst($goal['goal_type']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- No Goals Message -->
    <?php if (count($active_goals) == 0 && count($overdue_goals) == 0 && count($completed_goals) == 0): ?>
    <div class="no-goals-message">
        <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <h2>No Goals Yet</h2>
            <p>You don't have any CPD goals assigned yet. Your manager or partner will set goals to help guide your professional development.</p>
            <a href="dashboard.php" class="btn">Go to Dashboard</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .page-header {
        margin-bottom: 2rem;
    }
    
    .page-header h1 {
        margin-bottom: 0.5rem;
    }
    
    .goals-section {
        margin-bottom: 3rem;
    }
    
    .goals-section h2 {
        margin-bottom: 1.5rem;
        color: #2c3e50;
    }
    
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    
    .goal-card {
        background: #fff;
        border: 2px solid #e1e5e9;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    
    .goal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    
    .goal-card.warning {
        border-color: #ffc107;
        background: #fffbf0;
    }
    
    .goal-card.danger {
        border-color: #ff6b6b;
        background: #fff5f5;
    }
    
    .goal-card.overdue {
        border-color: #dc3545;
        background: #fff0f0;
    }
    
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 1rem;
    }
    
    .goal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.15rem;
        flex: 1;
    }
    
    .goal-badge {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: bold;
        text-transform: uppercase;
        white-space: nowrap;
    }
    
    .goal-badge.individual {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .goal-badge.team {
        background: #e8f5e9;
        color: #388e3c;
    }
    
    .goal-badge.department {
        background: #fff3e0;
        color: #f57c00;
    }
    
    .goal-team {
        color: #666;
        margin-bottom: 0.75rem;
    }
    
    .goal-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .goal-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .detail-item .label {
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .detail-item .value {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .progress-section {
        margin-bottom: 1rem;
    }
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    
    .progress-label {
        font-weight: 600;
        color: #555;
    }
    
    .progress-value {
        font-weight: bold;
        color: #007cba;
        font-size: 1.1rem;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 28px;
        background: #e9ecef;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }
    
    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.6s ease;
        border-radius: 14px;
    }
    
    .progress-bar-fill.team {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .progress-bar-fill.department {
        background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
    }
    
    .progress-bar-fill.overdue {
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
    }
    
    .progress-stats {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #666;
    }
    
    .goal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
    }
    
    .days-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .days-badge.normal {
        background: #d4edda;
        color: #155724;
    }
    
    .days-badge.warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .days-badge.danger {
        background: #f8d7da;
        color: #721c24;
        animation: pulse 2s infinite;
    }
    
    .days-badge.overdue {
        background: #dc3545;
        color: white;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .last-entry {
        color: #888;
        font-size: 0.8rem;
    }
    
    .completed-goals-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .completed-goal-item {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .completed-icon {
        font-size: 2rem;
    }
    
    .completed-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .completed-content strong {
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .completed-content small {
        color: #666;
    }
    
    .no-goals-message {
        padding: 4rem 2rem;
    }
    
    .empty-state {
        text-align: center;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .empty-icon {
        font-size: 5rem;
        margin-bottom: 1rem;
    }
    
    .empty-state h2 {
        color: #2c3e50;
        margin-bottom: 1rem;
    }
    
    .empty-state p {
        color: #666;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .goals-grid {
            grid-template-columns: 1fr;
        }
        
        .goal-details {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
