<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/goal_functions.php';

// Check authentication
checkAuth();

$pageTitle = 'My CPD Goals';
include 'includes/header.php';

// Handle personal goal creation, editing, and deletion
$success_message = '';
$error_messages = [];

// Handle personal goal update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_goal'])) {
    $goal_id = intval($_POST['goal_id']);
    
    // Verify this is the user's own personal goal
    $stmt = $pdo->prepare("SELECT * FROM cpd_goals WHERE id = ? AND set_by = ? AND target_user_id = ?");
    $stmt->execute([$goal_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $existing_goal = $stmt->fetch();
    
    if ($existing_goal) {
        $title = trim($_POST['goal_title'] ?? '');
        $description = trim($_POST['goal_description'] ?? '');
        $target_hours = floatval($_POST['target_hours'] ?? 0);
        $target_entries = !empty($_POST['target_entries']) ? intval($_POST['target_entries']) : null;
        $deadline = $_POST['deadline'] ?? '';
        
        // Validation
        if (empty($title)) {
            $error_messages[] = "Goal title is required";
        } elseif (strlen($title) > 255) {
            $error_messages[] = "Title cannot exceed 255 characters";
        }
        
        if (strlen($description) > 1000) {
            $error_messages[] = "Description cannot exceed 1000 characters";
        }
        
        if ($target_hours <= 0) {
            $error_messages[] = "Target hours must be greater than 0";
        } elseif ($target_hours > 500) {
            $error_messages[] = "Target hours cannot exceed 500";
        }
        
        if ($target_entries !== null && ($target_entries <= 0 || $target_entries > 100)) {
            $error_messages[] = "Target entries must be between 1 and 100";
        }
        
        if (empty($deadline)) {
            $error_messages[] = "Deadline is required";
        } else {
            $deadline_date = DateTime::createFromFormat('Y-m-d', $deadline);
            if (!$deadline_date || $deadline_date->format('Y-m-d') !== $deadline) {
                $error_messages[] = "Invalid deadline format";
            }
        }
        
        if (empty($error_messages)) {
            $goal_data = [
                'title' => htmlspecialchars($title),
                'description' => !empty($description) ? htmlspecialchars($description) : null,
                'target_hours' => $target_hours,
                'target_entries' => $target_entries,
                'deadline' => $deadline
            ];
            
            if (updateGoal($pdo, $goal_id, $goal_data)) {
                $success_message = "Personal goal updated successfully!";
                header("Location: user_goals.php?success=updated");
                exit();
            } else {
                $error_messages[] = "Failed to update goal. Please try again.";
            }
        }
    } else {
        $error_messages[] = "Goal not found or you don't have permission to edit it.";
    }
}

// Handle personal goal deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_personal_goal'])) {
    $goal_id = intval($_POST['goal_id']);
    
    // Verify this is the user's own personal goal
    $stmt = $pdo->prepare("SELECT * FROM cpd_goals WHERE id = ? AND set_by = ? AND target_user_id = ?");
    $stmt->execute([$goal_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $existing_goal = $stmt->fetch();
    
    if ($existing_goal) {
        if (deleteGoal($pdo, $goal_id)) {
            $success_message = "Personal goal deleted successfully!";
            header("Location: user_goals.php?success=deleted");
            exit();
        } else {
            $error_messages[] = "Failed to delete goal. Please try again.";
        }
    } else {
        $error_messages[] = "Goal not found or you don't have permission to delete it.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_personal_goal'])) {
    // Validate input
    $title = trim($_POST['goal_title'] ?? '');
    $description = trim($_POST['goal_description'] ?? '');
    $target_hours = floatval($_POST['target_hours'] ?? 0);
    $target_entries = !empty($_POST['target_entries']) ? intval($_POST['target_entries']) : null;
    $deadline = $_POST['deadline'] ?? '';
    
    // Validation
    if (empty($title)) {
        $error_messages[] = "Goal title is required";
    } elseif (strlen($title) > 255) {
        $error_messages[] = "Title cannot exceed 255 characters";
    }
    
    if (strlen($description) > 1000) {
        $error_messages[] = "Description cannot exceed 1000 characters";
    }
    
    if ($target_hours <= 0) {
        $error_messages[] = "Target hours must be greater than 0";
    } elseif ($target_hours > 500) {
        $error_messages[] = "Target hours cannot exceed 500";
    }
    
    if ($target_entries !== null && ($target_entries <= 0 || $target_entries > 100)) {
        $error_messages[] = "Target entries must be between 1 and 100";
    }
    
    if (empty($deadline)) {
        $error_messages[] = "Deadline is required";
    } else {
        $deadline_date = DateTime::createFromFormat('Y-m-d', $deadline);
        if (!$deadline_date || $deadline_date->format('Y-m-d') !== $deadline) {
            $error_messages[] = "Invalid deadline format";
        } elseif (strtotime($deadline) < strtotime('today')) {
            $error_messages[] = "Deadline cannot be in the past";
        } elseif (strtotime($deadline) > strtotime('+5 years')) {
            $error_messages[] = "Deadline cannot be more than 5 years in the future";
        }
    }
    
    // If validation passes, create the goal
    if (empty($error_messages)) {
        $goal_data = [
            'goal_type' => 'individual',
            'target_user_id' => $_SESSION['user_id'],
            'target_team_id' => null,
            'target_department_id' => null,
            'set_by' => $_SESSION['user_id'], // User sets their own goal
            'title' => htmlspecialchars($title),
            'description' => !empty($description) ? htmlspecialchars($description) : null,
            'target_hours' => $target_hours,
            'target_entries' => $target_entries,
            'deadline' => $deadline
        ];
        
        $goal_id = createGoal($pdo, $goal_data);
        
        if ($goal_id) {
            $success_message = "Personal goal created successfully!";
            // Redirect to refresh the page and show the new goal
            header("Location: user_goals.php?success=created");
            exit();
        } else {
            $error_messages[] = "Failed to create goal. Please try again.";
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success_message = "Personal goal created successfully!";
    } elseif ($_GET['success'] === 'updated') {
        $success_message = "Personal goal updated successfully!";
    } elseif ($_GET['success'] === 'deleted') {
        $success_message = "Personal goal deleted successfully!";
    }
}

// Get user's goals
$active_goals = getUserGoals($pdo, $_SESSION['user_id'], 'active');
$completed_goals = getUserGoals($pdo, $_SESSION['user_id'], 'completed');
$overdue_goals = getUserGoals($pdo, $_SESSION['user_id'], 'overdue');
$goal_stats = getGoalStatistics($pdo, $_SESSION['user_id']);

// Separate by type
$individual_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'individual'; });
$team_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'team'; });
$dept_goals = array_filter($active_goals, function($g) { return $g['goal_type'] === 'department'; });

// Further separate individual goals into self-set and manager-set
$personal_goals = array_filter($individual_goals, function($g) { 
    return $g['set_by'] == $_SESSION['user_id']; 
});
$manager_set_goals = array_filter($individual_goals, function($g) { 
    return $g['set_by'] != $_SESSION['user_id']; 
});
?>

<div class="container">
    <div class="page-header">
        <h1>🎯 My CPD Goals</h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">Track your professional development targets and progress</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;">
        <strong>Please correct the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
            <?php foreach ($error_messages as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Overdue Alert -->
    <?php if (count($overdue_goals) > 0): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;">
        <strong>⚠️ You have <?php echo count($overdue_goals); ?> overdue goal<?php echo count($overdue_goals) > 1 ? 's' : ''; ?>!</strong>
        Please review and take action to get back on track.
        <a href="#overdue-section" style="color: inherit; text-decoration: underline;">View overdue goals</a>
    </div>
    <?php endif; ?>

    <!-- Create Personal Goal Section -->
    <div class="create-goal-section">
        <div class="section-header">
            <h2>Create Personal Goal</h2>
            <button type="button" id="toggleGoalForm" class="btn btn-secondary">
                <span id="toggleText">Show Form</span>
            </button>
        </div>
        
        <div id="goalFormContainer" class="goal-form-container" style="display: none;">
            <form method="POST" action="" class="goal-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="goal_title">Goal Title: *</label>
                        <input type="text" id="goal_title" name="goal_title" required maxlength="255" 
                               placeholder="e.g., Complete Advanced Excel Training"
                               value="<?php echo isset($_POST['goal_title']) ? htmlspecialchars($_POST['goal_title']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="goal_description">Description (optional):</label>
                        <textarea id="goal_description" name="goal_description" rows="3" maxlength="1000"
                                  placeholder="Describe what you want to achieve and why..."><?php echo isset($_POST['goal_description']) ? htmlspecialchars($_POST['goal_description']) : ''; ?></textarea>
                        <small>Max 1000 characters</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="target_hours">Target Hours: *</label>
                        <input type="number" id="target_hours" name="target_hours" required 
                               min="0.5" max="500" step="0.5" placeholder="e.g., 20"
                               value="<?php echo isset($_POST['target_hours']) ? htmlspecialchars($_POST['target_hours']) : ''; ?>">
                        <small>Between 0.5 and 500 hours</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="target_entries">Target Entries (optional):</label>
                        <input type="number" id="target_entries" name="target_entries" 
                               min="1" max="100" placeholder="e.g., 5"
                               value="<?php echo isset($_POST['target_entries']) ? htmlspecialchars($_POST['target_entries']) : ''; ?>">
                        <small>Leave blank if not tracking entries</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline">Deadline: *</label>
                        <input type="date" id="deadline" name="deadline" required
                               min="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+5 years')); ?>"
                               value="<?php echo isset($_POST['deadline']) ? htmlspecialchars($_POST['deadline']) : ''; ?>">
                        <small>Must be in the future</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="create_personal_goal" class="btn">
                        🎯 Create Personal Goal
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('goalFormContainer').style.display='none'; document.getElementById('toggleText').textContent='Show Form';">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

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

    <!-- Personal Goals (Self-Set) -->
    <?php if (count($personal_goals) > 0): ?>
    <div class="goals-section">
        <h2>💪 My Personal Goals (<?php echo count($personal_goals); ?>)</h2>
        <p style="color: #666; margin-bottom: 1rem;">Goals you've set for yourself</p>
        <div class="goals-grid">
            <?php foreach ($personal_goals as $goal): 
                $progress = $goal['progress_percentage'] ?? 0;
                $is_warning = $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0;
                $is_danger = $goal['days_remaining'] <= 3 && $goal['days_remaining'] > 0;
            ?>
                <div class="goal-card personal-goal <?php echo $is_danger ? 'danger' : ($is_warning ? 'warning' : ''); ?>">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-badge personal">Personal</span>
                    </div>

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
                            <span class="progress-label">Progress</span>
                            <span class="progress-value"><?php echo round($progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill personal" style="width: <?php echo min($progress, 100); ?>%"></div>
                        </div>
                        <div class="progress-stats">
                            <span><?php echo $goal['current_hours'] ?? 0; ?> / <?php echo $goal['target_hours']; ?> hours</span>
                            <?php if ($goal['target_entries']): ?>
                                <span><?php echo $goal['current_entries'] ?? 0; ?> / <?php echo $goal['target_entries']; ?> entries</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="goal-footer">
                        <div class="goal-footer-left">
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
                                <small class="last-entry" style="display: block; margin-top: 0.25rem;">Last entry: <?php echo date('M d', strtotime($goal['last_entry_date'])); ?></small>
                            <?php else: ?>
                                <small class="last-entry" style="display: block; margin-top: 0.25rem;">No entries yet</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="goal-actions">
                            <button type="button" class="btn btn-small" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($goal)); ?>)">
                                ✏️ Edit
                            </button>
                            <button type="button" class="btn btn-small btn-danger" onclick="confirmDeleteGoal(<?php echo $goal['id']; ?>)">
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Manager-Set Individual Goals -->
    <?php if (count($manager_set_goals) > 0): ?>
    <div class="goals-section">
        <h2>👤 Individual Goals Set by Manager (<?php echo count($manager_set_goals); ?>)</h2>
        <p style="color: #666; margin-bottom: 1rem;">Goals assigned to you by your manager</p>
        <div class="goals-grid">
            <?php foreach ($manager_set_goals as $goal): 
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
                            <span class="progress-value"><?php echo round($progress, 100); ?>%</span>
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
            <?php foreach ($team_goals as $goal): 
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
                            <span class="progress-value"><?php echo round($progress, 100); ?>%</span>
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
            <?php foreach ($dept_goals as $goal): 
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
            <?php foreach ($overdue_goals as $goal): 
                $progress = $goal['progress_percentage'] ?? 0;
            ?>
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
            <p>Create your first personal CPD goal using the form above, or your manager may assign goals to help guide your professional development.</p>
            <button type="button" class="btn" onclick="document.getElementById('goalFormContainer').style.display='block'; document.getElementById('toggleText').textContent='Hide Form'; window.scrollTo(0,0);">
                Create Your First Goal
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Personal Goal Modal -->
<div id="editGoalModal" class="goal-modal" style="display: none;">
    <div class="goal-modal-content">
        <span class="goal-modal-close" onclick="closeEditModal()">&times;</span>
        <h2>Edit Personal Goal</h2>
        
        <form method="POST" action="" class="goal-form">
            <input type="hidden" id="edit_goal_id" name="goal_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_goal_title">Goal Title: *</label>
                    <input type="text" id="edit_goal_title" name="goal_title" required maxlength="255">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_goal_description">Description (optional):</label>
                    <textarea id="edit_goal_description" name="goal_description" rows="3" maxlength="1000"></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_target_hours">Target Hours: *</label>
                    <input type="number" id="edit_target_hours" name="target_hours" required 
                           min="0.5" max="500" step="0.5">
                </div>
                
                <div class="form-group">
                    <label for="edit_target_entries">Target Entries (optional):</label>
                    <input type="number" id="edit_target_entries" name="target_entries" 
                           min="1" max="100">
                </div>
                
                <div class="form-group">
                    <label for="edit_deadline">Deadline: *</label>
                    <input type="date" id="edit_deadline" name="deadline" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_personal_goal" class="btn">
                    💾 Update Goal
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteGoalModal" class="goal-modal" style="display: none;">
    <div class="goal-modal-content" style="max-width: 500px;">
        <span class="goal-modal-close" onclick="closeDeleteModal()">&times;</span>
        <h2>Delete Personal Goal?</h2>
        
        <p style="margin: 1.5rem 0; color: #721c24; background: #f8d7da; padding: 1rem; border-radius: 4px;">
            <strong>⚠️ Warning:</strong> This will permanently delete your goal and cannot be undone.
        </p>
        
        <form method="POST" action="">
            <input type="hidden" id="delete_goal_id" name="goal_id">
            
            <div class="form-actions">
                <button type="submit" name="delete_personal_goal" class="btn btn-danger" style="width: 100%;">
                    🗑️ Yes, Delete Goal
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" style="width: 100%;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .page-header {
        margin-bottom: 2rem;
    }
    
    .page-header h1 {
        margin-bottom: 0.5rem;
    }
    
    /* Create Goal Section */
    .create-goal-section {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        border-left: 4px solid #28a745;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .section-header h2 {
        margin: 0;
        color: #28a745;
    }
    
    .goal-form-container {
        margin-top: 1.5rem;
    }
    
    .goal-form {
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 8px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .form-row:has(.form-group:nth-child(3)) {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }
    
    .form-group input,
    .form-group textarea {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }
    
    .form-group small {
        margin-top: 0.25rem;
        color: #666;
        font-size: 0.85rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
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
    
    .goal-card.personal-goal {
        border-color: #28a745;
        background: linear-gradient(to bottom, #f0fff4 0%, #fff 50%);
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
    
    .goal-badge.personal {
        background: #d4edda;
        color: #155724;
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
    
    .progress-bar-fill.personal {
        background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
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
        align-items: flex-start;
        padding-top: 1rem;
        border-top: 1px solid #e1e5e9;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .goal-footer-left {
        flex: 1;
    }
    
    .goal-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
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
    
    /* Modal Styles */
    .goal-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow-y: auto;
    }
    
    .goal-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 2rem;
        border-radius: 12px;
        width: 90%;
        max-width: 700px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        position: relative;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .goal-modal-close {
        position: absolute;
        right: 1.5rem;
        top: 1.5rem;
        font-size: 2rem;
        cursor: pointer;
        color: #666;
        transition: color 0.3s ease;
    }
    
    .goal-modal-close:hover {
        color: #000;
    }
    
    .goal-modal h2 {
        margin: 0 0 1.5rem 0;
        color: #2c3e50;
    }
    
    @media (max-width: 768px) {
        .goals-grid {
            grid-template-columns: 1fr;
        }
        
        .goal-details {
            grid-template-columns: 1fr;
        }
        
        .form-row:has(.form-group:nth-child(3)) {
            grid-template-columns: 1fr;
        }
        
        .goal-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<script>
function openEditModal(goal) {
    document.getElementById('edit_goal_id').value = goal.id;
    document.getElementById('edit_goal_title').value = goal.title;
    document.getElementById('edit_goal_description').value = goal.description || '';
    document.getElementById('edit_target_hours').value = goal.target_hours;
    document.getElementById('edit_target_entries').value = goal.target_entries || '';
    document.getElementById('edit_deadline').value = goal.deadline;
    
    document.getElementById('editGoalModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('editGoalModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function confirmDeleteGoal(goalId) {
    document.getElementById('delete_goal_id').value = goalId;
    document.getElementById('deleteGoalModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteGoalModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editGoalModal');
    const deleteModal = document.getElementById('deleteGoalModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Toggle goal form
document.getElementById('toggleGoalForm').addEventListener('click', function() {
    const container = document.getElementById('goalFormContainer');
    const toggleText = document.getElementById('toggleText');
    
    if (container.style.display === 'none') {
        container.style.display = 'block';
        toggleText.textContent = 'Hide Form';
    } else {
        container.style.display = 'none';
        toggleText.textContent = 'Show Form';
    }
});

// If there are errors, show the form
<?php if (!empty($error_messages)): ?>
document.getElementById('goalFormContainer').style.display = 'block';
document.getElementById('toggleText').textContent = 'Hide Form';
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>