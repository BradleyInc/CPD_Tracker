<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/goal_functions.php';

// Check authentication and manager/partner role
checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: manager_goals.php');
    exit();
}

$goal_id = intval($_GET['id']);

// Check if user can manage this goal
if (!canManageGoal($pdo, $_SESSION['user_id'], $goal_id)) {
    header('Location: manager_goals.php');
    exit();
}

$pageTitle = 'Goal Details';
include 'includes/header.php';

// Handle goal update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_goal'])) {
        $goal_data = [
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'target_hours' => floatval($_POST['target_hours']),
            'target_entries' => !empty($_POST['target_entries']) ? intval($_POST['target_entries']) : null,
            'deadline' => $_POST['deadline']
        ];
        
        if (updateGoal($pdo, $goal_id, $goal_data)) {
            $success_message = "Goal updated successfully!";
        } else {
            $error_message = "Failed to update goal.";
        }
    }
    
    if (isset($_POST['cancel_goal'])) {
        if (cancelGoal($pdo, $goal_id)) {
            header('Location: manager_goals.php?cancelled=1');
            exit();
        } else {
            $error_message = "Failed to cancel goal.";
        }
    }
    
    if (isset($_POST['delete_goal'])) {
        if (deleteGoal($pdo, $goal_id)) {
            header('Location: manager_goals.php?deleted=1');
            exit();
        } else {
            $error_message = "Failed to delete goal.";
        }
    }
}

// Get goal details
$goal = getGoalById($pdo, $goal_id);

if (!$goal) {
    header('Location: manager_goals.php');
    exit();
}

// Get progress details based on goal type
$progress_details = [];
if ($goal['goal_type'] === 'team' || $goal['goal_type'] === 'department') {
    $progress_details = getTeamGoalProgress($pdo, $goal_id);
}

// Calculate statistics
$total_participants = count($progress_details);
$on_track = 0;
$behind = 0;
$completed = 0;

foreach ($progress_details as $participant) {
    $progress = $participant['progress_percentage'] ?? 0;
    if ($progress >= 100) {
        $completed++;
    } elseif ($progress >= 70) {
        $on_track++;
    } else {
        $behind++;
    }
}
?>

<div class="container">
    <div class="admin-header">
        <h1>Goal Details</h1>
        <?php if (isManager()): ?>
            <?php renderManagerNav(null, 'goals'); ?>
        <?php else: ?>
            <?php renderPartnerNav('goals'); ?>
        <?php endif; ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Goal Header -->
    <div class="goal-detail-header">
        <div class="goal-info">
            <h2><?php echo htmlspecialchars($goal['title']); ?></h2>
            <div class="goal-meta">
                <span class="goal-type-badge <?php echo $goal['goal_type']; ?>">
                    <?php echo ucfirst($goal['goal_type']); ?> Goal
                </span>
                <span class="status-badge status-<?php echo $goal['status']; ?>">
                    <?php echo ucfirst($goal['status']); ?>
                </span>
            </div>
            <p class="goal-target">
                <strong>Target:</strong> <?php echo htmlspecialchars($goal['target_name']); ?>
            </p>
            <?php if ($goal['description']): ?>
                <p class="goal-description"><?php echo htmlspecialchars($goal['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="goal-deadline">
            <?php if ($goal['status'] === 'active'): ?>
                <?php if ($goal['days_remaining'] <= 0): ?>
                    <div class="deadline-box overdue">
                        <div class="deadline-icon">⚠️</div>
                        <div class="deadline-text">
                            <strong>Overdue</strong>
                            <span><?php echo abs($goal['days_remaining']); ?> days past deadline</span>
                        </div>
                    </div>
                <?php elseif ($goal['days_remaining'] <= 7): ?>
                    <div class="deadline-box urgent">
                        <div class="deadline-icon">⏰</div>
                        <div class="deadline-text">
                            <strong><?php echo $goal['days_remaining']; ?> Days Left</strong>
                            <span>Due: <?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="deadline-box normal">
                        <div class="deadline-icon">📅</div>
                        <div class="deadline-text">
                            <strong><?php echo $goal['days_remaining']; ?> Days</strong>
                            <span>Due: <?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="deadline-box completed">
                    <div class="deadline-icon">✅</div>
                    <div class="deadline-text">
                        <strong>Completed</strong>
                        <span><?php echo date('M d, Y', strtotime($goal['deadline'])); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Goal Statistics -->
    <?php if ($goal['goal_type'] === 'team' || $goal['goal_type'] === 'department'): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Participants</h3>
            <p class="stat-number"><?php echo $total_participants; ?></p>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h3 style="color: white;">Completed</h3>
            <p class="stat-number"><?php echo $completed; ?></p>
            <small style="color: white;"><?php echo $total_participants > 0 ? round(($completed / $total_participants) * 100, 1) : 0; ?>% of team</small>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white;">On Track</h3>
            <p class="stat-number"><?php echo $on_track; ?></p>
            <small style="color: white;">70%+ progress</small>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <h3 style="color: white;">Behind</h3>
            <p class="stat-number"><?php echo $behind; ?></p>
            <small style="color: white;">Need attention</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Individual Progress -->
    <?php if ($goal['goal_type'] === 'team' || $goal['goal_type'] === 'department'): ?>
    <div class="admin-section">
        <h2>Individual Progress (<?php echo count($progress_details); ?> participants)</h2>
        
        <?php if (count($progress_details) > 0): ?>
            <table class="admin-table progress-table">
                <thead>
                    <tr>
                        <th>Team Member</th>
                        <th>Current Hours</th>
                        <th>Target Hours</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Last Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($progress_details as $participant): ?>
                        <?php
                        $progress = $participant['progress_percentage'] ?? 0;
                        $status_class = '';
                        $status_text = '';
                        
                        if ($progress >= 100) {
                            $status_class = 'completed';
                            $status_text = 'Completed';
                        } elseif ($progress >= 70) {
                            $status_class = 'on-track';
                            $status_text = 'On Track';
                        } elseif ($progress >= 40) {
                            $status_class = 'moderate';
                            $status_text = 'Moderate';
                        } else {
                            $status_class = 'behind';
                            $status_text = 'Behind';
                        }
                        ?>
                        <tr class="<?php echo $status_class; ?>-row">
                            <td><strong><?php echo htmlspecialchars($participant['username']); ?></strong></td>
                            <td><?php echo $participant['current_hours'] ?? 0; ?> hours</td>
                            <td><?php echo $goal['target_hours']; ?> hours</td>
                            <td>
                                <div class="progress-cell">
                                    <div class="progress-bar-mini">
                                        <div class="progress-fill-mini <?php echo $status_class; ?>" 
                                             style="width: <?php echo min($progress, 100); ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo round($progress, 1); ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="status-indicator <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
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
        <?php else: ?>
            <p>No participants in this goal yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Goal Management -->
    <div class="goal-management-grid">
        <!-- Edit Goal -->
        <div class="admin-section">
            <h2>Edit Goal</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Goal Title:</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($goal['title']); ?>" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3"><?php echo htmlspecialchars($goal['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Target Hours:</label>
                        <input type="number" name="target_hours" value="<?php echo $goal['target_hours']; ?>" step="0.5" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Target Entries (Optional):</label>
                        <input type="number" name="target_entries" value="<?php echo $goal['target_entries'] ?? ''; ?>" min="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Deadline:</label>
                    <input type="date" name="deadline" value="<?php echo $goal['deadline']; ?>" required>
                </div>
                
                <button type="submit" name="update_goal" class="btn">Update Goal</button>
            </form>
        </div>

        <!-- Goal Actions -->
        <div class="admin-section">
            <h2>Goal Actions</h2>
            
            <div class="action-card">
                <h3>Goal Information</h3>
                <p><strong>Created:</strong> <?php echo date('M d, Y', strtotime($goal['created_at'])); ?></p>
                <p><strong>Set by:</strong> <?php echo htmlspecialchars($goal['set_by_name']); ?></p>
                <p><strong>Type:</strong> <?php echo ucfirst($goal['goal_type']); ?></p>
                <p><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_name']); ?></p>
            </div>
            
            <div class="action-buttons">
                <?php if ($goal['status'] === 'active'): ?>
                    <form method="POST" onsubmit="return confirm('Cancel this goal? Participants will still see it but marked as cancelled.');">
                        <button type="submit" name="cancel_goal" class="btn btn-block" style="background: #ffc107;">
                            🚫 Cancel Goal
                        </button>
                    </form>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return confirm('PERMANENTLY DELETE this goal? This cannot be undone!');">
                    <button type="submit" name="delete_goal" class="btn btn-block btn-danger">
                        🗑️ Delete Goal
                    </button>
                </form>
                
                <a href="manager_goals.php" class="btn btn-block btn-secondary">
                    ← Back to Goals
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .goal-detail-header {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 2rem;
        align-items: start;
    }
    
    .goal-info h2 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
    }
    
    .goal-meta {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .goal-type-badge {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .goal-type-badge.individual {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .goal-type-badge.team {
        background: #e8f5e9;
        color: #388e3c;
    }
    
    .goal-type-badge.department {
        background: #fff3e0;
        color: #f57c00;
    }
    
    .goal-target {
        color: #666;
        margin-bottom: 0.75rem;
    }
    
    .goal-description {
        color: #666;
        line-height: 1.6;
        margin: 0;
    }
    
    .deadline-box {
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
        min-width: 200px;
    }
    
    .deadline-box.normal {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .deadline-box.urgent {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: white;
    }
    
    .deadline-box.overdue {
        background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
        color: white;
    }
    
    .deadline-box.completed {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }
    
    .deadline-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
    
    .deadline-text strong {
        display: block;
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
    }
    
    .deadline-text span {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .progress-table {
        margin-top: 1.5rem;
    }
    
    .progress-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .progress-bar-mini {
        flex: 1;
        height: 24px;
        background: #e9ecef;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    
    .progress-fill-mini {
        height: 100%;
        transition: width 0.5s ease;
        border-radius: 12px;
    }
    
    .progress-fill-mini.completed {
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .progress-fill-mini.on-track {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .progress-fill-mini.moderate {
        background: linear-gradient(90deg, #f093fb 0%, #f5576c 100%);
    }
    
    .progress-fill-mini.behind {
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
    }
    
    .progress-text {
        font-weight: 600;
        color: #2c3e50;
        min-width: 60px;
        text-align: right;
    }
    
    .status-indicator {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-indicator.completed {
        background: #d4edda;
        color: #155724;
    }
    
    .status-indicator.on-track {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .status-indicator.moderate {
        background: #fff3cd;
        color: #856404;
    }
    
    .status-indicator.behind {
        background: #f8d7da;
        color: #721c24;
    }
    
    .completed-row {
        background: #f0fff0;
    }
    
    .on-track-row {
        background: #f0f8ff;
    }
    
    .moderate-row {
        background: #fffef0;
    }
    
    .behind-row {
        background: #fff5f5;
    }
    
    .goal-management-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .action-card {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .action-card h3 {
        margin-top: 0;
        color: #2c3e50;
    }
    
    .action-card p {
        margin: 0.5rem 0;
        color: #666;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    @media (max-width: 992px) {
        .goal-detail-header {
            grid-template-columns: 1fr;
        }
        
        .goal-management-grid {
            grid-template-columns: 1fr;
        }
        
        .deadline-box {
            min-width: auto;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
