<?php
// Goal Card Template - used in manager_goals.php and partner_goals.php
// Expects $goal variable to be set

$is_urgent = isset($goal['days_remaining']) && $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
$is_overdue = isset($goal['days_remaining']) && $goal['days_remaining'] < 0;
$is_personal = isset($goal['is_personal_goal']) && $goal['is_personal_goal'] == 1;

$card_class = 'goal-card';
if ($is_overdue) $card_class .= ' danger';
elseif ($is_urgent) $card_class .= ' warning';
if ($is_personal) $card_class .= ' personal';

$progress = $goal['avg_progress'] ?? 0;
$progress_class = '';
if ($progress < 33) $progress_class = 'low';
elseif ($progress < 66) $progress_class = 'medium';
?>

<div class="<?php echo $card_class; ?>">
    <div class="goal-header">
        <div class="goal-title-container">
            <div class="goal-title">
                <?php if ($is_personal): ?>
                    <span class="personal-badge">🎯 Personal</span>
                <?php endif; ?>
                <?php echo htmlspecialchars($goal['title']); ?>
            </div>
            <p class="goal-target">
                <strong><?php echo htmlspecialchars($goal['target_name']); ?></strong>
            </p>
        </div>
        <span class="goal-type-badge <?php echo $goal['goal_type']; ?>">
            <?php echo ucfirst($goal['goal_type']); ?>
        </span>
    </div>
    
    <div class="goal-stats-mini">
        <div class="stat-mini">
            <span class="stat-mini-label-small">Target</span>
            <span class="stat-mini-value-small"><?php echo $goal['target_hours']; ?> hrs</span>
        </div>
        <div class="stat-mini">
            <span class="stat-mini-label-small">Progress</span>
            <span class="stat-mini-value-small"><?php echo round($progress, 0); ?>%</span>
        </div>
        <div class="stat-mini">
            <span class="stat-mini-label-small">
                <?php if (isset($goal['affected_users']) && $goal['affected_users'] > 1): ?>
                    People
                <?php else: ?>
                    Deadline
                <?php endif; ?>
            </span>
            <span class="stat-mini-value-small">
                <?php if (isset($goal['affected_users']) && $goal['affected_users'] > 1): ?>
                    <?php echo $goal['affected_users']; ?>
                <?php else: ?>
                    <?php echo date('M d', strtotime($goal['deadline'])); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <div class="progress-container">
        <div class="progress-bar-large">
            <div class="progress-fill-large <?php echo $progress_class; ?>" 
                 style="width: <?php echo min($progress, 100); ?>%">
                <?php if ($progress >= 15): ?>
                    <?php echo round($progress); ?>%
                <?php endif; ?>
            </div>
        </div>
        <div class="progress-info">
            <span><?php echo round($progress, 1); ?>% complete</span>
            <span>
                <?php if (isset($goal['affected_users']) && $goal['affected_users'] > 1): ?>
                    <?php echo $goal['affected_users']; ?> participants
                <?php else: ?>
                    Due <?php echo date('M d, Y', strtotime($goal['deadline'])); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <div class="goal-footer">
        <div class="deadline-badge <?php echo $is_overdue ? 'danger' : ($is_urgent ? 'warning' : 'normal'); ?>">
            <?php if ($is_overdue): ?>
                🔴 Overdue by <?php echo abs($goal['days_remaining']); ?> days
            <?php elseif ($is_urgent): ?>
                ⚠️ <?php echo $goal['days_remaining']; ?> days left
            <?php else: ?>
                ✓ <?php echo $goal['days_remaining']; ?> days remaining
            <?php endif; ?>
        </div>
        <a href="manager_goal_details.php?id=<?php echo $goal['id']; ?>" class="btn btn-small">
            View Details
        </a>
    </div>
</div>
