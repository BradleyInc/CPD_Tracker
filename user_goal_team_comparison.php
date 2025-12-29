<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/goal_functions.php';
require_once 'includes/team_functions.php';

// Check authentication
checkAuth();

if (!isset($_GET['id'])) {
    header('Location: user_goals.php');
    exit();
}

$goal_id = intval($_GET['id']);

// Get goal details
$goal = getGoalById($pdo, $goal_id);

if (!$goal) {
    header('Location: user_goals.php');
    exit();
}

// Verify this is a team goal and user has access to it
if ($goal['goal_type'] !== 'team') {
    header('Location: user_goals.php');
    exit();
}

// Verify user is in the team this goal is assigned to
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_teams 
    WHERE user_id = ? AND team_id = ?
");
$stmt->execute([$_SESSION['user_id'], $goal['target_team_id']]);
if ($stmt->fetchColumn() == 0) {
    header('Location: user_goals.php');
    exit();
}

// Get all team members' progress
$progress_details = getTeamGoalProgress($pdo, $goal_id);

// Get user's own progress
$user_progress = null;
foreach ($progress_details as $participant) {
    if ($participant['user_id'] == $_SESSION['user_id']) {
        $user_progress = $participant;
        break;
    }
}

// Calculate statistics
$total_participants = count($progress_details);
$team_total_hours = 0;
$team_total_entries = 0;
$completed_count = 0;

foreach ($progress_details as $participant) {
    $team_total_hours += $participant['current_hours'] ?? 0;
    $team_total_entries += $participant['current_entries'] ?? 0;
    if (($participant['progress_percentage'] ?? 0) >= 100) {
        $completed_count++;
    }
}

$team_avg_hours = $total_participants > 0 ? $team_total_hours / $total_participants : 0;
$team_avg_progress = $total_participants > 0 ? array_sum(array_column($progress_details, 'progress_percentage')) / $total_participants : 0;

// Sort by progress
usort($progress_details, function($a, $b) {
    return ($b['progress_percentage'] ?? 0) <=> ($a['progress_percentage'] ?? 0);
});

// Find user's rank
$user_rank = 0;
foreach ($progress_details as $index => $participant) {
    if ($participant['user_id'] == $_SESSION['user_id']) {
        $user_rank = $index + 1;
        break;
    }
}

$pageTitle = 'Team Goal Comparison';
include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>📊 Team Goal Comparison</h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">See how your progress compares with your teammates</p>
    </div>

    <!-- Goal Information -->
    <div class="goal-info-card">
        <div class="goal-header">
            <div>
                <h2><?php echo htmlspecialchars($goal['title']); ?></h2>
                <p class="goal-team"><strong>Team:</strong> <?php echo htmlspecialchars($goal['target_name']); ?></p>
            </div>
            <div class="goal-deadline-badge">
                <?php if ($goal['days_remaining'] <= 0): ?>
                    <span class="deadline overdue">⚠️ Overdue</span>
                <?php elseif ($goal['days_remaining'] <= 7): ?>
                    <span class="deadline urgent">⏰ <?php echo $goal['days_remaining']; ?> days left</span>
                <?php else: ?>
                    <span class="deadline normal">📅 <?php echo $goal['days_remaining']; ?> days remaining</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($goal['description']): ?>
            <p class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></p>
        <?php endif; ?>
        
        <div class="goal-targets">
            <div class="target-item">
                <span class="label">Target Hours:</span>
                <span class="value"><?php echo $goal['target_hours']; ?> hours</span>
            </div>
            <?php if ($goal['target_entries']): ?>
            <div class="target-item">
                <span class="label">Target Entries:</span>
                <span class="value"><?php echo $goal['target_entries']; ?> entries</span>
            </div>
            <?php endif; ?>
            <div class="target-item">
                <span class="label">Deadline:</span>
                <span class="value"><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Your Progress vs Team -->
    <div class="comparison-section">
        <h2>Your Progress vs Team Average</h2>
        
        <div class="comparison-grid">
            <!-- Your Progress -->
            <div class="comparison-card your-progress">
                <div class="card-header">
                    <h3>👤 Your Progress</h3>
                    <span class="rank-badge">Rank #<?php echo $user_rank; ?> of <?php echo $total_participants; ?></span>
                </div>
                
                <div class="progress-stats">
                    <div class="stat">
                        <div class="stat-value"><?php echo $user_progress['current_hours'] ?? 0; ?></div>
                        <div class="stat-label">Hours</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo round($user_progress['progress_percentage'] ?? 0, 1); ?>%</div>
                        <div class="stat-label">Progress</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $user_progress['current_entries'] ?? 0; ?></div>
                        <div class="stat-label">Entries</div>
                    </div>
                </div>
                
                <div class="progress-bar-large">
                    <div class="progress-fill-large your" style="width: <?php echo min($user_progress['progress_percentage'] ?? 0, 100); ?>%"></div>
                </div>
                
                <?php if ($user_progress['last_entry_date']): ?>
                    <p class="last-entry">Last entry: <?php echo date('M d, Y', strtotime($user_progress['last_entry_date'])); ?></p>
                <?php else: ?>
                    <p class="last-entry">No entries yet</p>
                <?php endif; ?>
            </div>

            <!-- Team Average -->
            <div class="comparison-card team-average">
                <div class="card-header">
                    <h3>👥 Team Average</h3>
                    <span class="completed-badge"><?php echo $completed_count; ?> completed</span>
                </div>
                
                <div class="progress-stats">
                    <div class="stat">
                        <div class="stat-value"><?php echo round($team_avg_hours, 1); ?></div>
                        <div class="stat-label">Avg Hours</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo round($team_avg_progress, 1); ?>%</div>
                        <div class="stat-label">Avg Progress</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $total_participants; ?></div>
                        <div class="stat-label">Team Members</div>
                    </div>
                </div>
                
                <div class="progress-bar-large">
                    <div class="progress-fill-large team" style="width: <?php echo min($team_avg_progress, 100); ?>%"></div>
                </div>
                
                <p class="team-total">Total team hours: <?php echo round($team_total_hours, 1); ?></p>
            </div>

            <!-- Comparison Insights -->
            <div class="comparison-card insights">
                <div class="card-header">
                    <h3>💡 Insights</h3>
                </div>
                
                <?php
                $user_prog = $user_progress['progress_percentage'] ?? 0;
                $diff_from_avg = $user_prog - $team_avg_progress;
                $diff_from_target = $goal['target_hours'] - ($user_progress['current_hours'] ?? 0);
                ?>
                
                <div class="insights-list">
                    <?php if ($user_prog >= 100): ?>
                        <div class="insight success">
                            <span class="icon">🎉</span>
                            <span>Congratulations! You've completed this goal!</span>
                        </div>
                    <?php elseif ($diff_from_avg >= 20): ?>
                        <div class="insight success">
                            <span class="icon">🌟</span>
                            <span>You're <?php echo round(abs($diff_from_avg), 1); ?>% ahead of team average!</span>
                        </div>
                    <?php elseif ($diff_from_avg >= 0): ?>
                        <div class="insight good">
                            <span class="icon">👍</span>
                            <span>You're <?php echo round(abs($diff_from_avg), 1); ?>% above team average</span>
                        </div>
                    <?php elseif ($diff_from_avg >= -20): ?>
                        <div class="insight warning">
                            <span class="icon">📈</span>
                            <span>You're <?php echo round(abs($diff_from_avg), 1); ?>% below team average</span>
                        </div>
                    <?php else: ?>
                        <div class="insight alert">
                            <span class="icon">⚠️</span>
                            <span>You're <?php echo round(abs($diff_from_avg), 1); ?>% behind team average</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($diff_from_target > 0): ?>
                        <div class="insight info">
                            <span class="icon">🎯</span>
                            <span><?php echo round($diff_from_target, 1); ?> hours to reach your target</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($goal['days_remaining'] > 0 && $diff_from_target > 0): ?>
                        <?php $hours_per_day = $diff_from_target / $goal['days_remaining']; ?>
                        <div class="insight info">
                            <span class="icon">📅</span>
                            <span>Average <?php echo round($hours_per_day, 1); ?> hours/day needed</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="insight info">
                        <span class="icon">🏅</span>
                        <span>You're ranked #<?php echo $user_rank; ?> out of <?php echo $total_participants; ?> teammates</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Leaderboard -->
    <div class="leaderboard-section">
        <h2>🏆 Team Leaderboard</h2>
        
        <div class="leaderboard-table-container">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Team Member</th>
                        <th>Hours</th>
                        <th>Entries</th>
                        <th>Progress</th>
                        <th>Last Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($progress_details as $index => $participant): ?>
                        <?php
                        $progress = $participant['progress_percentage'] ?? 0;
                        $is_user = $participant['user_id'] == $_SESSION['user_id'];
                        $status_class = '';
                        
                        if ($progress >= 100) {
                            $status_class = 'completed';
                        } elseif ($progress >= 70) {
                            $status_class = 'on-track';
                        } elseif ($progress >= 40) {
                            $status_class = 'moderate';
                        } else {
                            $status_class = 'behind';
                        }
                        ?>
                        <tr class="<?php echo $is_user ? 'user-row' : ''; ?> <?php echo $status_class; ?>">
                            <td>
                                <div class="rank-cell">
                                    <?php if ($index == 0): ?>
                                        <span class="rank-medal gold">🥇</span>
                                    <?php elseif ($index == 1): ?>
                                        <span class="rank-medal silver">🥈</span>
                                    <?php elseif ($index == 2): ?>
                                        <span class="rank-medal bronze">🥉</span>
                                    <?php else: ?>
                                        <span class="rank-number">#<?php echo $index + 1; ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($participant['username']); ?></strong>
                                <?php if ($is_user): ?>
                                    <span class="you-badge">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $participant['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?></td>
                            <td><?php echo $participant['current_entries'] ?? 0; ?></td>
                            <td>
                                <div class="progress-cell">
                                    <div class="mini-progress-bar">
                                        <div class="mini-progress-fill <?php echo $status_class; ?>" 
                                             style="width: <?php echo min($progress, 100); ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo round($progress, 1); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                echo $participant['last_entry_date'] 
                                    ? date('M d, Y', strtotime($participant['last_entry_date'])) 
                                    : 'No entries';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="user_goals.php" class="btn btn-secondary">← Back to My Goals</a>
        <a href="dashboard.php" class="btn">Add CPD Entry</a>
    </div>
</div>

<style>
    .page-header {
        margin-bottom: 2rem;
    }
    
    .page-header h1 {
        margin-bottom: 0.5rem;
    }
    
    .goal-info-card {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        border-left: 4px solid #667eea;
    }
    
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .goal-header h2 {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
    }
    
    .goal-team {
        color: #666;
        margin: 0;
    }
    
    .goal-deadline-badge .deadline {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .deadline.normal {
        background: #d4edda;
        color: #155724;
    }
    
    .deadline.urgent {
        background: #fff3cd;
        color: #856404;
    }
    
    .deadline.overdue {
        background: #f8d7da;
        color: #721c24;
    }
    
    .goal-description {
        color: #666;
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }
    
    .goal-targets {
        display: flex;
        gap: 2rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .target-item {
        display: flex;
        flex-direction: column;
    }
    
    .target-item .label {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .target-item .value {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .comparison-section {
        margin-bottom: 3rem;
    }
    
    .comparison-section h2 {
        margin-bottom: 1.5rem;
        color: #2c3e50;
    }
    
    .comparison-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    
    .comparison-card {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .comparison-card.your-progress {
        border-left: 4px solid #667eea;
    }
    
    .comparison-card.team-average {
        border-left: 4px solid #4facfe;
    }
    
    .comparison-card.insights {
        grid-column: 1 / -1;
        border-left: 4px solid #f093fb;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .card-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.25rem;
    }
    
    .rank-badge, .completed-badge {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .rank-badge {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .completed-badge {
        background: #d4edda;
        color: #155724;
    }
    
    .progress-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat {
        text-align: center;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #2c3e50;
        line-height: 1;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: #666;
    }
    
    .progress-bar-large {
        width: 100%;
        height: 40px;
        background: #e9ecef;
        border-radius: 20px;
        overflow: hidden;
        position: relative;
        margin-bottom: 1rem;
    }
    
    .progress-fill-large {
        height: 100%;
        transition: width 0.6s ease;
        border-radius: 20px;
    }
    
    .progress-fill-large.your {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .progress-fill-large.team {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .last-entry, .team-total {
        text-align: center;
        color: #666;
        font-size: 0.9rem;
        margin: 0;
    }
    
    .insights-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .insight {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: 8px;
    }
    
    .insight.success {
        background: #d4edda;
        color: #155724;
    }
    
    .insight.good {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .insight.warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .insight.alert {
        background: #f8d7da;
        color: #721c24;
    }
    
    .insight.info {
        background: #e7f3ff;
        color: #004085;
    }
    
    .insight .icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .leaderboard-section {
        margin-bottom: 3rem;
    }
    
    .leaderboard-section h2 {
        margin-bottom: 1.5rem;
        color: #2c3e50;
    }
    
    .leaderboard-table-container {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .leaderboard-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .leaderboard-table thead {
        background: #f8f9fa;
    }
    
    .leaderboard-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #e1e5e9;
    }
    
    .leaderboard-table td {
        padding: 1rem;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .leaderboard-table tr.user-row {
        background: #e8f4fd;
        font-weight: 600;
    }
    
    .leaderboard-table tr.completed {
        background: #f0fff0;
    }
    
    .leaderboard-table tr:hover {
        background: #f8f9fa;
    }
    
    .leaderboard-table tr.user-row:hover {
        background: #d9edf7;
    }
    
    .rank-cell {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .rank-medal {
        font-size: 1.5rem;
    }
    
    .rank-number {
        font-weight: 600;
        color: #666;
    }
    
    .you-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: #667eea;
        color: white;
        border-radius: 12px;
        font-size: 0.7rem;
        margin-left: 0.5rem;
    }
    
    .progress-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .mini-progress-bar {
        flex: 1;
        height: 20px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    
    .mini-progress-fill {
        height: 100%;
        transition: width 0.5s ease;
        border-radius: 10px;
    }
    
    .mini-progress-fill.completed {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .mini-progress-fill.on-track {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .mini-progress-fill.moderate {
        background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
    }
    
    .mini-progress-fill.behind {
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
    }
    
    .progress-text {
        font-weight: 600;
        color: #2c3e50;
        min-width: 60px;
        text-align: right;
    }
    
    @media (max-width: 992px) {
        .comparison-grid {
            grid-template-columns: 1fr;
        }
        
        .comparison-card.insights {
            grid-column: 1;
        }
        
        .goal-targets {
            flex-direction: column;
            gap: 1rem;
        }
    }
    
    @media (max-width: 768px) {
        .goal-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .progress-stats {
            grid-template-columns: 1fr;
        }
        
        .leaderboard-table-container {
            overflow-x: auto;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
