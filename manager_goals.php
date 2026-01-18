<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/goal_functions.php';
require_once 'includes/department_functions.php';

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
        'target_points' => !empty($_POST['target_points']) ? floatval($_POST['target_points']) : null,
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
if (isManager()) {
    $managed_teams = getManagerTeams($pdo, $_SESSION['user_id']);
    $all_goals = getManagerGoals($pdo, $_SESSION['user_id']);
} else {
    $managed_teams = getPartnerTeams($pdo, $_SESSION['user_id']);
    $all_goals = getPartnerGoals($pdo, $_SESSION['user_id']);
    $managed_departments = getPartnerDepartments($pdo, $_SESSION['user_id']);
}

$overdue_goals = getOverdueGoals($pdo, $_SESSION['user_id']);
$approaching_goals = getApproachingDeadlineGoals($pdo, 7, $_SESSION['user_id']);
$templates = getGoalTemplates($pdo);

// Separate goals by status
$active_goals = array_filter($all_goals, function($g) { return $g['status'] === 'active'; });
$completed_goals = array_filter($all_goals, function($g) { return $g['status'] === 'completed'; });

// Separate personal goals from manager-set goals
$personal_goals = array_filter($active_goals, function($g) { return $g['is_personal_goal'] == 1; });
$manager_set_goals = array_filter($active_goals, function($g) { return $g['is_personal_goal'] == 0; });
?>

<style>
    .goals-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    }
    
    .goals-hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
    }
    
    .goals-hero-info h1 {
        margin: 0 0 0.5rem 0;
        font-size: 2rem;
    }
    
    .goals-hero-info p {
        margin: 0;
        opacity: 0.9;
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
    }
    
    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card-mini {
        background: white;
        padding: 1.25rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.2s;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-2px);
    }
    
    .stat-card-mini.danger {
        background: linear-gradient(135deg, #fee 0%, #fdd 100%);
        border-left: 4px solid #dc3545;
    }
    
    .stat-card-mini.warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-left: 4px solid #ffc107;
    }
    
    .stat-card-mini.success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-left: 4px solid #28a745;
    }
    
    .stat-mini-label {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-mini.danger .stat-mini-label,
    .stat-card-mini.warning .stat-mini-label,
    .stat-card-mini.success .stat-mini-label {
        font-weight: 600;
    }
    
    .stat-mini-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .stat-card-mini.danger .stat-mini-value {
        color: #721c24;
    }
    
    .stat-card-mini.warning .stat-mini-value {
        color: #856404;
    }
    
    .stat-card-mini.success .stat-mini-value {
        color: #155724;
    }
    
    .create-goal-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 2rem;
        overflow: hidden;
    }
    
    .section-header {
        padding: 1.5rem;
        border-bottom: 2px solid #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .section-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #2c3e50;
    }
    
    .section-body {
        padding: 1.5rem;
    }
    
    .tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #e1e8ed;
        margin-bottom: 1.5rem;
    }
    
    .tab-btn {
        padding: 0.75rem 1.5rem;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 1rem;
        color: #666;
        transition: all 0.2s;
        font-weight: 500;
    }
    
    .tab-btn.active {
        color: #667eea;
        border-bottom-color: #667eea;
        font-weight: 600;
    }
    
    .tab-btn:hover:not(.active) {
        color: #2c3e50;
        background: #f8f9fa;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
    }
    
    .goal-card {
        background: white;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.3s;
        position: relative;
    }
    
    .goal-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        border-color: #667eea;
    }
    
    .goal-card.warning {
        border-left: 4px solid #ffc107;
        background: linear-gradient(to right, #fffef5 0%, white 20%);
    }
    
    .goal-card.danger {
        border-left: 4px solid #dc3545;
        background: linear-gradient(to right, #fff5f5 0%, white 20%);
        animation: pulse-border 2s infinite;
    }
    
    @keyframes pulse-border {
        0%, 100% { border-left-color: #dc3545; }
        50% { border-left-color: #ff6b6b; }
    }
    
    .goal-card.personal {
        border-left: 4px solid #28a745;
        background: linear-gradient(to right, #f0fff4 0%, white 20%);
    }
    
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 1rem;
    }
    
    .goal-title-container {
        flex: 1;
    }
    
    .goal-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .personal-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.6rem;
        background: #d4edda;
        color: #155724;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .goal-type-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }
    
    .goal-type-badge.individual {
        background: #e3f2fd;
        color: #1976d2;
    }
    
    .goal-type-badge.team {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .goal-target {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }
    
    .goal-stats-mini {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .stat-mini {
        text-align: center;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .stat-mini-label-small {
        font-size: 0.7rem;
        color: #666;
        margin-bottom: 0.25rem;
        display: block;
    }
    
    .stat-mini-value-small {
        font-size: 1rem;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .progress-container {
        margin-bottom: 1rem;
    }
    
    .progress-bar-large {
        width: 100%;
        height: 28px;
        background: #e9ecef;
        border-radius: 14px;
        overflow: hidden;
        position: relative;
        margin-bottom: 0.5rem;
    }
    
    .progress-fill-large {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 0.75rem;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .progress-fill-large.low {
        background: linear-gradient(90deg, #dc3545 0%, #ff6b6b 100%);
    }
    
    .progress-fill-large.medium {
        background: linear-gradient(90deg, #ffc107 0%, #ffb300 100%);
    }
    
    .progress-info {
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
        border-top: 1px solid #f3f4f6;
    }
    
    .deadline-badge {
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .deadline-badge.normal {
        color: #28a745;
    }
    
    .deadline-badge.warning {
        color: #ffc107;
    }
    
    .deadline-badge.danger {
        color: #dc3545;
        animation: pulse-text 2s infinite;
    }
    
    @keyframes pulse-text {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .section-divider {
        margin: 2rem 0;
        border-top: 2px solid #e1e8ed;
        position: relative;
    }
    
    .section-divider-label {
        position: absolute;
        top: -0.75rem;
        left: 50%;
        transform: translateX(-50%);
        background: #f5f7fa;
        padding: 0 1rem;
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .goals-hero-content {
            flex-direction: column;
        }
        
        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .goals-grid {
            grid-template-columns: 1fr;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <!-- Hero Section -->
    <div class="goals-hero">
        <div class="goals-hero-content">
            <div class="goals-hero-info">
                <h1>🎯 CPD Goals Management</h1>
                <p>Set and track goals for your team members</p>
            </div>
            <a href="manager_dashboard.php" class="hero-btn">← Back to Dashboard</a>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-card-mini">
            <div class="stat-mini-label">Total Goals</div>
            <div class="stat-mini-value"><?php echo count($all_goals); ?></div>
        </div>
        
        <div class="stat-card-mini success">
            <div class="stat-mini-label">✓ Active</div>
            <div class="stat-mini-value"><?php echo count($active_goals); ?></div>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-mini-label">Completed</div>
            <div class="stat-mini-value"><?php echo count($completed_goals); ?></div>
        </div>
        
        <?php if (count($overdue_goals) > 0): ?>
        <div class="stat-card-mini danger">
            <div class="stat-mini-label">🔴 Overdue</div>
            <div class="stat-mini-value"><?php echo count($overdue_goals); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (count($approaching_goals) > 0): ?>
        <div class="stat-card-mini warning">
            <div class="stat-mini-label">⚠️ Approaching</div>
            <div class="stat-mini-value"><?php echo count($approaching_goals); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create Goal Section -->
    <div class="create-goal-section">
        <div class="section-header">
            <h2>➕ Create New Goal</h2>
        </div>
        <div class="section-body">
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('custom')">Custom Goal</button>
                <button class="tab-btn" onclick="switchTab('template')">From Template</button>
            </div>

            <!-- Custom Goal Form -->
            <div id="custom-tab" class="tab-content active">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Goal Type:</label>
                            <select name="goal_type" id="goalType" required onchange="updateTargetOptions()">
                                <option value="individual">Individual Goal</option>
                                <option value="team">Team Goal</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="individualTarget" style="margin-bottom: 0;">
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
                        
                        <div class="form-group" id="teamTarget" style="display: none; margin-bottom: 0;">
                            <label>Team:</label>
                            <select name="target_team_id" id="targetTeam">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($managed_teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Goal Title:</label>
                        <input type="text" name="title" required maxlength="200" placeholder="e.g., Q1 2025 CPD Target">
                    </div>

                    <div class="form-group">
                        <label>Description (Optional):</label>
                        <textarea name="description" rows="2" placeholder="Add context or expectations..."></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Target Hours:</label>
                            <input type="number" name="target_hours" step="0.5" min="1" required value="20">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Target Entries (Optional):</label>
                            <input type="number" name="target_entries" min="1" placeholder="Leave blank for no target">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Deadline:</label>
                            <input type="date" name="deadline" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <button type="submit" name="create_goal" class="btn" style="margin-top: 1rem;">Create Goal</button>
                </form>
            </div>

            <!-- Template Form -->
            <div id="template-tab" class="tab-content">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
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

                    <div id="templateInfo" class="template-info" style="display: none; background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                        <p style="margin: 0.5rem 0;"><strong>Target:</strong> <span id="tempHours"></span> hours</p>
                        <p style="margin: 0.5rem 0;"><strong>Duration:</strong> <span id="tempDays"></span> days</p>
                        <p style="margin: 0.5rem 0;"><strong>Description:</strong> <span id="tempDesc"></span></p>
                    </div>

                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Goal Type:</label>
                            <select name="goal_type" id="goalType2" required onchange="updateTargetOptions2()">
                                <option value="individual">Individual Goal</option>
                                <option value="team">Team Goal</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="individualTarget2" style="margin-bottom: 0;">
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
                        
                        <div class="form-group" id="teamTarget2" style="display: none; margin-bottom: 0;">
                            <label>Team:</label>
                            <select name="target_team_id" id="targetTeam2">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($managed_teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="create_from_template" class="btn" style="margin-top: 1rem;">Create from Template</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Overdue Goals -->
    <?php if (count($overdue_goals) > 0): ?>
    <div class="create-goal-section" style="border-left: 4px solid #dc3545;">
        <div class="section-header">
            <h2>🔴 Overdue Goals (<?php echo count($overdue_goals); ?>)</h2>
        </div>
        <div class="section-body">
            <div class="goals-grid">
                <?php foreach ($overdue_goals as $goal): ?>
                    <?php include 'includes/goal_card_template.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approaching Deadline Goals -->
    <?php if (count($approaching_goals) > 0): ?>
    <div class="create-goal-section" style="border-left: 4px solid #ffc107;">
        <div class="section-header">
            <h2>⚠️ Approaching Deadline (<?php echo count($approaching_goals); ?>)</h2>
        </div>
        <div class="section-body">
            <div class="goals-grid">
                <?php foreach ($approaching_goals as $goal): ?>
                    <?php include 'includes/goal_card_template.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Goals -->
    <div class="create-goal-section">
        <div class="section-header">
            <h2>✓ Active Goals (<?php echo count($active_goals); ?>)</h2>
        </div>
        <div class="section-body">
            <?php if (count($active_goals) > 0): ?>
                <!-- Manager-Set Goals -->
                <?php if (count($manager_set_goals) > 0): ?>
                <h3 style="margin: 0 0 1rem 0; color: #666; font-size: 1rem;">Manager-Set Goals (<?php echo count($manager_set_goals); ?>)</h3>
                <div class="goals-grid">
                    <?php foreach ($manager_set_goals as $goal): ?>
                        <?php include 'includes/goal_card_template.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Personal Goals -->
                <?php if (count($personal_goals) > 0): ?>
                    <?php if (count($manager_set_goals) > 0): ?>
                    <div class="section-divider">
                        <span class="section-divider-label">Personal Goals Set by Team Members</span>
                    </div>
                    <?php endif; ?>
                    
                    <h3 style="margin: 0 0 1rem 0; color: #666; font-size: 1rem;">Personal Goals (<?php echo count($personal_goals); ?>)</h3>
                    <div class="goals-grid">
                        <?php foreach ($personal_goals as $goal): ?>
                            <?php include 'includes/goal_card_template.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🎯</div>
                    <h3>No active goals</h3>
                    <p>Create a goal using the form above</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Goals -->
    <?php if (count($completed_goals) > 0): ?>
    <div class="create-goal-section" style="border-left: 4px solid #28a745;">
        <div class="section-header">
            <h2>✅ Completed Goals (<?php echo count($completed_goals); ?>)</h2>
        </div>
        <div class="section-body">
            <div class="goals-grid">
                <?php foreach (array_slice($completed_goals, 0, 6) as $goal): ?>
                    <?php include 'includes/goal_card_template.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php if (count($completed_goals) > 6): ?>
                <p style="text-align: center; margin-top: 1rem; color: #666;">
                    Showing 6 of <?php echo count($completed_goals); ?> completed goals
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTab(tabName) {
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