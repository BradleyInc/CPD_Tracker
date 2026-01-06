<?php
// Goals Dashboard Widget
// Include this file in dashboard.php to show goals progress

// Get user's goals
$user_active_goals = getUserGoals($pdo, $_SESSION['user_id'], 'active');
$user_goal_stats = getGoalStatistics($pdo, $_SESSION['user_id']);

// Get templates for quick creation
$templates = getGoalTemplates($pdo);

// Only show widget if user has goals or templates exist
if (count($user_active_goals) > 0 || ($user_goal_stats['total_goals'] ?? 0) > 0 || count($templates) > 0):
?>

<div class="goals-widget">
    <div class="widget-header">
        <h2>🎯 My Goals</h2>
        <a href="user_goals.php" class="view-all-link">View All →</a>
    </div>

    <!-- Quick Goal Creation from Template (if templates exist) -->
    <?php if (count($templates) > 0): ?>
    <div class="quick-create-section">
        <h3>Quick Create from Template</h3>
        <form method="POST" action="user_goals.php" class="quick-template-form">
            <select name="template_id" onchange="this.form.submit()">
                <option value="">Select a template...</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?php echo $template['id']; ?>">
                        <?php echo htmlspecialchars($template['name']); ?> 
                        (<?php echo $template['target_hours']; ?> hours)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="create_from_template" value="1">
        </form>
        <small>Creates a personal goal with predefined settings</small>
    </div>
    <?php endif; ?>

    <!-- Goals Summary -->
    <div class="goals-summary">
        <div class="summary-item">
            <div class="summary-icon active">⚡</div>
            <div class="summary-content">
                <span class="summary-value"><?php echo $user_goal_stats['active_goals'] ?? 0; ?></span>
                <span class="summary-label">Active</span>
            </div>
        </div>
        
        <div class="summary-item">
            <div class="summary-icon completed">✅</div>
            <div class="summary-content">
                <span class="summary-value"><?php echo $user_goal_stats['completed_goals'] ?? 0; ?></span>
                <span class="summary-label">Completed</span>
            </div>
        </div>
        
        <?php if (($user_goal_stats['overdue_goals'] ?? 0) > 0): ?>
        <div class="summary-item">
            <div class="summary-icon overdue">⚠️</div>
            <div class="summary-content">
                <span class="summary-value"><?php echo $user_goal_stats['overdue_goals']; ?></span>
                <span class="summary-label">Overdue</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="summary-item">
            <div class="summary-icon progress">📊</div>
            <div class="summary-content">
                <span class="summary-value"><?php echo round($user_goal_stats['avg_progress'] ?? 0, 0); ?>%</span>
                <span class="summary-label">Avg Progress</span>
            </div>
        </div>
    </div>

    <!-- Active Goals List -->
    <?php if (count($user_active_goals) > 0): ?>
    <div class="active-goals-list">
        <h3>Active Goals</h3>
        
        <?php 
        // Show up to 3 most urgent goals
        $displayed_goals = array_slice($user_active_goals, 0, 3);
        foreach ($displayed_goals as $goal): 
            $progress = $goal['progress_percentage'] ?? 0;
            $is_urgent = $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
            $is_overdue = $goal['days_remaining'] < 0;
        ?>
        <div class="goal-item <?php echo $is_overdue ? 'overdue' : ($is_urgent ? 'urgent' : ''); ?>">
            <div class="goal-item-header">
                <div class="goal-item-title">
                    <span class="goal-type-icon">
                        <?php 
                        if ($goal['goal_type'] === 'individual') echo '👤';
                        elseif ($goal['goal_type'] === 'team') echo '👥';
                        else echo '🏢';
                        ?>
                    </span>
                    <strong><?php echo htmlspecialchars($goal['title']); ?></strong>
                </div>
                <div class="goal-item-deadline">
                    <?php if ($is_overdue): ?>
                        <span class="deadline-badge overdue">Overdue</span>
                    <?php elseif ($is_urgent): ?>
                        <span class="deadline-badge urgent"><?php echo $goal['days_remaining']; ?> days</span>
                    <?php else: ?>
                        <span class="deadline-badge normal"><?php echo $goal['days_remaining']; ?> days</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="goal-item-progress">
                <div class="progress-info">
                    <span class="progress-hours">
                        <?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours
                    </span>
                    <span class="progress-percentage">
                        <?php echo round($progress, 0); ?>%
                    </span>
                </div>
                <div class="progress-bar-widget">
                    <div class="progress-fill-widget <?php echo $is_overdue ? 'overdue' : ($is_urgent ? 'urgent' : 'normal'); ?>" 
                         style="width: <?php echo min($progress, 100); ?>%"></div>
                </div>
            </div>
            
            <?php if ($goal['goal_type'] === 'team' && isset($goal['group_name'])): ?>
            <div class="goal-item-team">
                Team: <?php echo htmlspecialchars($goal['group_name']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if (count($user_active_goals) > 3): ?>
        <div style="text-align: center; margin-top: 1rem;">
            <a href="user_goals.php" class="btn btn-small">
                View All <?php echo count($user_active_goals); ?> Goals
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="no-active-goals">
        <p>No active goals at the moment.</p>
        <small>Create a goal from a template above, or create a custom one in the Goals page.</small>
        <div style="margin-top: 1rem;">
            <a href="user_goals.php" class="btn btn-small">Create Your First Goal</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .goals-widget {
        background: #fff;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        border-left: 4px solid #667eea;
    }
    
    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .widget-header h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.5rem;
    }
    
    .view-all-link {
        color: #007cba;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: color 0.3s ease;
    }
    
    .view-all-link:hover {
        color: #005a87;
    }
    
    /* Quick Create Section */
    .quick-create-section {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px dashed #007cba;
    }
    
    .quick-create-section h3 {
        margin: 0 0 0.75rem 0;
        color: #2c3e50;
        font-size: 1rem;
    }
    
    .quick-template-form select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.95rem;
        background: white;
    }
    
    .quick-template-form select:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 2px rgba(0,124,186,0.2);
    }
    
    .quick-create-section small {
        display: block;
        margin-top: 0.5rem;
        color: #666;
        font-size: 0.85rem;
    }
    
    .goals-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    /* Rest of the existing styles remain the same... */
    .summary-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        transition: transform 0.2s ease;
    }
    
    .summary-item:hover {
        transform: translateY(-2px);
    }
    
    .summary-icon {
        font-size: 2rem;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    
    .summary-icon.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .summary-icon.completed {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .summary-icon.overdue {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    
    .summary-icon.progress {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .summary-content {
        display: flex;
        flex-direction: column;
    }
    
    .summary-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #2c3e50;
        line-height: 1;
    }
    
    .summary-label {
        font-size: 0.85rem;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .active-goals-list h3 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
        font-size: 1.1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f8f9fa;
    }
    
    .goal-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 4px solid #667eea;
        transition: all 0.3s ease;
    }
    
    .goal-item:hover {
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .goal-item.urgent {
        border-left-color: #ffc107;
        background: #fffef0;
    }
    
    .goal-item.overdue {
        border-left-color: #dc3545;
        background: #fff5f5;
    }
    
    .goal-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .goal-item-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex: 1;
    }
    
    .goal-type-icon {
        font-size: 1.25rem;
    }
    
    .goal-item-title strong {
        color: #2c3e50;
        font-size: 0.95rem;
    }
    
    .deadline-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        white-space: nowrap;
    }
    
    .deadline-badge.normal {
        background: #d4edda;
        color: #155724;
    }
    
    .deadline-badge.urgent {
        background: #fff3cd;
        color: #856404;
    }
    
    .deadline-badge.overdue {
        background: #f8d7da;
        color: #721c24;
        animation: pulse-widget 2s infinite;
    }
    
    @keyframes pulse-widget {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .goal-item-progress {
        margin-bottom: 0.5rem;
    }
    
    .progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .progress-hours {
        color: #666;
    }
    
    .progress-percentage {
        font-weight: bold;
        color: #007cba;
    }
    
    .progress-bar-widget {
        width: 100%;
        height: 20px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    
    .progress-fill-widget {
        height: 100%;
        transition: width 0.5s ease;
        border-radius: 10px;
    }
    
    .progress-fill-widget.normal {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .progress-fill-widget.urgent {
        background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
    }
    
    .progress-fill-widget.overdue {
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
    }
    
    .goal-item-team {
        font-size: 0.8rem;
        color: #666;
        margin-top: 0.5rem;
        font-style: italic;
    }
    
    .no-active-goals {
        text-align: center;
        padding: 2rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .no-active-goals p {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
        font-weight: 600;
    }
    
    .no-active-goals small {
        color: #666;
    }
    
    @media (max-width: 768px) {
        .goals-summary {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .summary-item {
            flex-direction: column;
            text-align: center;
        }
        
        .goal-item-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
</style>

<?php endif; ?>