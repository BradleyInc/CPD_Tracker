<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/goal_functions.php';

// Check authentication and manager role
checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'CPD Goals Management';
include 'includes/header.php';

// Handle goal creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_goal'])) {
    $goal_data = [
        'goal_type' => $_POST['goal_type'],
        'target_user_id' => $_POST['goal_type'] === 'individual' ? intval($_POST['target_user_id']) : null,
        'target_team_id' => $_POST['goal_type'] === 'team' ? intval($_POST['target_team_id']) : null,
        'target_department_id' => null,
        'set_by' => $_SESSION['user_id'],
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description']),
        'target_hours' => floatval($_POST['target_hours']),
        'target_entries' => !empty($_POST['target_entries']) ? intval($_POST['target_entries']) : null,
        'deadline' => $_POST['deadline']
    ];
    
    if (createGoal($pdo, $goal_data)) {
        $success_message = "Goal created successfully!";
    } else {
        $error_message = "Failed to create goal.";
    }
}

// Handle goal from template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_template'])) {
    $template_id = intval($_POST['template_id']);
    $target_data = [
        'goal_type' => $_POST['goal_type'],
        'target_user_id' => $_POST['goal_type'] === 'individual' ? intval($_POST['target_user_id']) : null,
        'target_team_id' => $_POST['goal_type'] === 'team' ? intval($_POST['target_team_id']) : null,
    ];
    
    if (createGoalFromTemplate($pdo, $template_id, $target_data, $_SESSION['user_id'])) {
        $success_message = "Goal created from template successfully!";
    } else {
        $error_message = "Failed to create goal from template.";
    }
}

// Get manager's teams and goals
$managed_teams = isManager() ? getManagerTeams($pdo, $_SESSION['user_id']) : getPartnerTeams($pdo, $_SESSION['user_id']);
$all_goals = isManager() ? getManagerGoals($pdo, $_SESSION['user_id']) : getPartnerGoals($pdo, $_SESSION['user_id']);
$overdue_goals = getOverdueGoals($pdo, $_SESSION['user_id']);
$approaching_goals = getApproachingDeadlineGoals($pdo, 7, $_SESSION['user_id']);
$templates = getGoalTemplates($pdo);

// Separate goals by status
$active_goals = array_filter($all_goals, function($g) { return $g['status'] === 'active'; });
$completed_goals = array_filter($all_goals, function($g) { return $g['status'] === 'completed'; });
?>

<div class="container">
    <div class="admin-header">
        <h1>🎯 CPD Goals Management</h1>
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

    <!-- Alerts Section -->
    <?php if (count($overdue_goals) > 0 || count($approaching_goals) > 0): ?>
    <div class="admin-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h2>⚠️ Goals Requiring Attention</h2>
        <div class="alerts-grid">
            <?php if (count($overdue_goals) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #dc3545;">
                <strong><?php echo count($overdue_goals); ?></strong> overdue goals
                <a href="#overdue-section" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($approaching_goals) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #ffc107;">
                <strong><?php echo count($approaching_goals); ?></strong> goals approaching deadline
                <a href="#approaching-section" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Goals Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Goals</h3>
            <p class="stat-number"><?php echo count($all_goals); ?></p>
        </div>
        <div class="stat-card" style="background: #d4edda; color: #155724;">
            <h3>Active Goals</h3>
            <p class="stat-number"><?php echo count($active_goals); ?></p>
        </div>
        <div class="stat-card" style="background: #cfe2ff; color: #084298;">
            <h3>Completed Goals</h3>
            <p class="stat-number"><?php echo count($completed_goals); ?></p>
        </div>
        <div class="stat-card" style="background: #f8d7da; color: #721c24;">
            <h3>Overdue Goals</h3>
            <p class="stat-number"><?php echo count($overdue_goals); ?></p>
        </div>
    </div>

    <!-- Quick Goal Creation -->
    <div class="admin-section">
        <h2>Quick Create Goal</h2>
        <div class="quick-create-tabs">
            <button class="tab-btn active" onclick="showTab('custom')">Custom Goal</button>
            <button class="tab-btn" onclick="showTab('template')">From Template</button>
        </div>

        <!-- Custom Goal Form -->
        <div id="custom-tab" class="tab-content active">
            <form method="POST" class="goal-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Goal Type:</label>
                        <select name="goal_type" id="goalType" required onchange="updateTargetOptions()">
                            <option value="individual">Individual Goal</option>
                            <option value="team">Team Goal</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="individualTarget">
                        <label>Team Member:</label>
                        <select name="target_user_id" id="targetUser">
                            <option value="">-- Select Member --</option>
                            <?php foreach ($managed_teams as $team): ?>
                                <?php 
                                $members = isManager() ? getTeamMembers($pdo, $team['id']) : getDepartmentMemberSummary($pdo, $team['id']);
                                foreach ($members as $member): 
                                ?>
                                    <option value="<?php echo $member['user_id'] ?? $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['username']); ?> 
                                        (<?php echo htmlspecialchars($team['name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="teamTarget" style="display: none;">
                        <label>Team:</label>
                        <select name="target_team_id" id="targetTeam">
                            <option value="">-- Select Team --</option>
                            <?php foreach ($managed_teams as $team): ?>
                                <?php if (isManager()): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php else: ?>
                                    <?php 
                                    $dept_teams = getDepartmentTeams($pdo, $team['id']);
                                    foreach ($dept_teams as $dept_team): 
                                    ?>
                                        <option value="<?php echo $dept_team['id']; ?>">
                                            <?php echo htmlspecialchars($dept_team['name']); ?> 
                                            (<?php echo htmlspecialchars($team['name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Goal Title:</label>
                    <input type="text" name="title" required maxlength="200" placeholder="e.g., Q1 2025 CPD Target">
                </div>

                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3" placeholder="Optional description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Target Hours:</label>
                        <input type="number" name="target_hours" step="0.5" min="1" required value="20">
                    </div>
                    
                    <div class="form-group">
                        <label>Target Entries (Optional):</label>
                        <input type="number" name="target_entries" min="1" placeholder="Leave blank for no target">
                    </div>
                    
                    <div class="form-group">
                        <label>Deadline:</label>
                        <input type="date" name="deadline" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" name="create_goal" class="btn">Create Goal</button>
            </form>
        </div>

        <!-- Template Form -->
        <div id="template-tab" class="tab-content">
            <form method="POST" class="goal-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Template:</label>
                        <select name="template_id" required onchange="showTemplateInfo(this)">
                            <option value="">-- Select Template --</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>"
                                        data-hours="<?php echo $template['target_hours']; ?>"
                                        data-days="<?php echo $template['duration_days']; ?>"
                                        data-desc="<?php echo htmlspecialchars($template['description']); ?>">
                                    <?php echo htmlspecialchars($template['name']); ?> 
                                    (<?php echo $template['target_hours']; ?> hours in <?php echo $template['duration_days']; ?> days)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="templateInfo" class="template-info" style="display: none;">
                    <p><strong>Target:</strong> <span id="tempHours"></span> hours</p>
                    <p><strong>Duration:</strong> <span id="tempDays"></span> days</p>
                    <p><strong>Description:</strong> <span id="tempDesc"></span></p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Goal Type:</label>
                        <select name="goal_type" id="goalType2" required onchange="updateTargetOptions2()">
                            <option value="individual">Individual Goal</option>
                            <option value="team">Team Goal</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="individualTarget2">
                        <label>Team Member:</label>
                        <select name="target_user_id" id="targetUser2">
                            <option value="">-- Select Member --</option>
                            <?php foreach ($managed_teams as $team): ?>
                                <?php 
                                $members = isManager() ? getTeamMembers($pdo, $team['id']) : getDepartmentMemberSummary($pdo, $team['id']);
                                foreach ($members as $member): 
                                ?>
                                    <option value="<?php echo $member['user_id'] ?? $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['username']); ?> 
                                        (<?php echo htmlspecialchars($team['name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="teamTarget2" style="display: none;">
                        <label>Team:</label>
                        <select name="target_team_id" id="targetTeam2">
                            <option value="">-- Select Team --</option>
                            <?php foreach ($managed_teams as $team): ?>
                                <?php if (isManager()): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php else: ?>
                                    <?php 
                                    $dept_teams = getDepartmentTeams($pdo, $team['id']);
                                    foreach ($dept_teams as $dept_team): 
                                    ?>
                                        <option value="<?php echo $dept_team['id']; ?>">
                                            <?php echo htmlspecialchars($dept_team['name']); ?> 
                                            (<?php echo htmlspecialchars($team['name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" name="create_from_template" class="btn">Create from Template</button>
            </form>
        </div>
    </div>

    <!-- Active Goals -->
    <div class="admin-section">
        <h2>Active Goals (<?php echo count($active_goals); ?>)</h2>
        
        <?php if (count($active_goals) > 0): ?>
            <div class="goals-grid">
                <?php foreach ($active_goals as $goal): ?>
                    <div class="goal-card <?php echo $goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0 ? 'warning' : ''; ?>">
                        <div class="goal-header">
                            <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                            <span class="goal-type-badge <?php echo $goal['goal_type']; ?>">
                                <?php echo ucfirst($goal['goal_type']); ?>
                            </span>
                        </div>
                        
                        <p class="goal-target"><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_name']); ?></p>
                        
                        <div class="goal-stats">
                            <div class="stat">
                                <span class="label">Target</span>
                                <span class="value"><?php echo $goal['target_hours']; ?> hrs</span>
                            </div>
                            <div class="stat">
                                <span class="label">Progress</span>
                                <span class="value"><?php echo round($goal['avg_progress'] ?? 0, 1); ?>%</span>
                            </div>
                            <div class="stat">
                                <span class="label">Deadline</span>
                                <span class="value"><?php echo date('M d', strtotime($goal['deadline'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo min($goal['avg_progress'] ?? 0, 100); ?>%"></div>
                        </div>
                        
                        <div class="goal-footer">
                            <?php if ($goal['days_remaining'] <= 7 && $goal['days_remaining'] > 0): ?>
                                <span class="days-warning">⚠️ <?php echo $goal['days_remaining']; ?> days left</span>
                            <?php elseif ($goal['days_remaining'] <= 0): ?>
                                <span class="days-overdue">🔴 Overdue</span>
                            <?php else: ?>
                                <span class="days-normal"><?php echo $goal['days_remaining']; ?> days remaining</span>
                            <?php endif; ?>
                            <a href="manager_goal_details.php?id=<?php echo $goal['id']; ?>" class="btn btn-small">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No active goals. Create one above!</p>
        <?php endif; ?>
    </div>

    <!-- Overdue Goals -->
    <?php if (count($overdue_goals) > 0): ?>
    <div id="overdue-section" class="admin-section" style="border-left: 4px solid #dc3545;">
        <h2>🔴 Overdue Goals (<?php echo count($overdue_goals); ?>)</h2>
        <div class="goals-grid">
            <?php foreach ($overdue_goals as $goal): ?>
                <div class="goal-card overdue">
                    <div class="goal-header">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <span class="goal-type-badge <?php echo $goal['goal_type']; ?>">
                            <?php echo ucfirst($goal['goal_type']); ?>
                        </span>
                    </div>
                    
                    <p class="goal-target"><strong>Target:</strong> <?php echo htmlspecialchars($goal['target_name']); ?></p>
                    
                    <div class="goal-stats">
                        <div class="stat">
                            <span class="label">Target</span>
                            <span class="value"><?php echo $goal['target_hours']; ?> hrs</span>
                        </div>
                        <div class="stat">
                            <span class="label">Progress</span>
                            <span class="value"><?php echo round($goal['avg_progress'] ?? 0, 1); ?>%</span>
                        </div>
                        <div class="stat">
                            <span class="label">Was Due</span>
                            <span class="value"><?php echo date('M d', strtotime($goal['deadline'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill overdue" style="width: <?php echo min($goal['avg_progress'] ?? 0, 100); ?>%"></div>
                    </div>
                    
                    <div class="goal-footer">
                        <span class="days-overdue">🔴 Overdue by <?php echo abs($goal['days_remaining']); ?> days</span>
                        <a href="manager_goal_details.php?id=<?php echo $goal['id']; ?>" class="btn btn-small">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .quick-create-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e1e5e9;
    }
    
    .tab-btn {
        padding: 0.75rem 1.5rem;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 1rem;
        color: #666;
        transition: all 0.3s ease;
    }
    
    .tab-btn.active {
        color: #007cba;
        border-bottom-color: #007cba;
        font-weight: bold;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .goal-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .goal-card {
        background: #fff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .goal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .goal-card.warning {
        border-left: 4px solid #ffc107;
    }
    
    .goal-card.overdue {
        border-left: 4px solid #dc3545;
        background: #fff5f5;
    }
    
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .goal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.1rem;
        flex: 1;
    }
    
    .goal-type-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
        margin-left: 0.5rem;
    }
    
    .goal-type-badge.individual {
        background: #cfe2ff;
        color: #084298;
    }
    
    .goal-type-badge.team {
        background: #d1e7dd;
        color: #0f5132;
    }
    
    .goal-type-badge.department {
        background: #f8d7da;
        color: #842029;
    }
    
    .goal-target {
        color: #666;
        margin-bottom: 1rem;
    }
    
    .goal-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .goal-stats .stat {
        text-align: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .goal-stats .label {
        display: block;
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    
    .goal-stats .value {
        display: block;
        font-size: 1rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 24px;
        background: #e9ecef;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1rem;
        position: relative;
    }
    
    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
        transition: width 0.5s ease;
        position: relative;
    }
    
    .progress-bar-fill.overdue {
        background: linear-gradient(90deg, #fa709a 0%, #fee140 100%);
    }
    
    .goal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .days-normal {
        color: #28a745;
        font-weight: 600;
    }
    
    .days-warning {
        color: #ffc107;
        font-weight: 600;
    }
    
    .days-overdue {
        color: #dc3545;
        font-weight: 600;
    }
    
    .template-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .template-info p {
        margin: 0.5rem 0;
    }
    
    .alerts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .alert-card {
        background: white;
        padding: 1rem;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function updateTargetOptions() {
    const goalType = document.getElementById('goalType').value;
    const individualTarget = document.getElementById('individualTarget');
    const teamTarget = document.getElementById('teamTarget');
    const targetUser = document.getElementById('targetUser');
    const targetTeam = document.getElementById('targetTeam');
    
    if (goalType === 'individual') {
        individualTarget.style.display = 'block';
        teamTarget.style.display = 'none';
        targetUser.required = true;
        targetTeam.required = false;
    } else {
        individualTarget.style.display = 'none';
        teamTarget.style.display = 'block';
        targetUser.required = false;
        targetTeam.required = true;
    }
}

function updateTargetOptions2() {
    const goalType = document.getElementById('goalType2').value;
    const individualTarget = document.getElementById('individualTarget2');
    const teamTarget = document.getElementById('teamTarget2');
    const targetUser = document.getElementById('targetUser2');
    const targetTeam = document.getElementById('targetTeam2');
    
    if (goalType === 'individual') {
        individualTarget.style.display = 'block';
        teamTarget.style.display = 'none';
        targetUser.required = true;
        targetTeam.required = false;
    } else {
        individualTarget.style.display = 'none';
        teamTarget.style.display = 'block';
        targetUser.required = false;
        targetTeam.required = true;
    }
}

function showTemplateInfo(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('templateInfo').style.display = 'block';
        document.getElementById('tempHours').textContent = option.dataset.hours;
        document.getElementById('tempDays').textContent = option.dataset.days;
        document.getElementById('tempDesc').textContent = option.dataset.desc;
    } else {
        document.getElementById('templateInfo').style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
