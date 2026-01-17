<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/goal_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/department_functions.php';

// Check authentication
checkAuth();

// Debug logging function
function debugLog($message) {
    error_log("CPD DEBUG: " . $message);
}

function updateUserGoalProgress($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id
        FROM cpd_goals g
        WHERE g.status IN ('active', 'overdue')
        AND (
            g.target_user_id = ?
            OR g.target_team_id IN (SELECT team_id FROM user_teams WHERE user_id = ?)
            OR g.target_department_id IN (
                SELECT d.id FROM departments d
                JOIN teams t ON d.id = t.department_id
                JOIN user_teams ut ON t.id = ut.team_id
                WHERE ut.user_id = ?
            )
        )
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $goal_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $updated_count = 0;
    foreach ($goal_ids as $goal_id) {
        if (updateGoalProgress($pdo, $goal_id)) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog("POST request received: " . print_r($_POST, true));
    
    // Handle bulk delete
    if (isset($_POST['delete_entries'])) {
        if (isset($_POST['selected_entries']) && is_array($_POST['selected_entries'])) {
            $deleted_count = 0;
            $error_count = 0;
            
            foreach ($_POST['selected_entries'] as $entry_id) {
                $entry_id = intval($entry_id);
                if ($entry_id > 0) {
                    try {
                        if (deleteCPDEntry($pdo, $entry_id, $_SESSION['user_id'])) {
                            $deleted_count++;
                        } else {
                            $error_count++;
                        }
                    } catch (Exception $e) {
                        error_log("Delete error for entry $entry_id: " . $e->getMessage());
                        $error_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                echo "<div class='alert alert-success' style='max-width: 1400px; margin: 1rem auto;'>Successfully deleted $deleted_count CPD entry(ies).</div>";
                $updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
                if ($updated_goals > 0) {
                    debugLog("Updated progress for $updated_goals goal(s) after deletion");
                }
            }
            
            if ($error_count > 0) {
                echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>Failed to delete $error_count entry(ies). Please try again.</div>";
            }
        } else {
            echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>No entries selected for deletion.</div>";
        }
    }
    
    // Handle update entry
    if (isset($_POST['update_entry'])) {
        debugLog("Update entry form submitted");
        
        $entry_id = intval($_POST['entry_id'] ?? 0);
        $title = trim(htmlspecialchars($_POST['edit_title'] ?? ''));
        $description = trim(htmlspecialchars($_POST['edit_description'] ?? ''));
        $date_completed = $_POST['edit_date_completed'] ?? '';
        $hours = floatval($_POST['edit_hours'] ?? 0);
        $category = htmlspecialchars($_POST['edit_category'] ?? '');
        $points = !empty($_POST['edit_points']) ? floatval($_POST['edit_points']) : null;
        
        $validation_data = [
            'title' => $title,
            'description' => $description,
            'date_completed' => $date_completed,
            'hours' => $hours,
            'points' => $points,
            'category' => $category
        ];
        
        $validation_errors = validateCPDEntry($validation_data);
        
        if (empty($validation_errors)) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE id = ? AND user_id = ?");
                $stmt->execute([$entry_id, $_SESSION['user_id']]);
                $entry = $stmt->fetch();
                
                if ($entry) {
                    $sql = "UPDATE cpd_entries SET title = ?, description = ?, date_completed = ?, hours = ?,  points = ?, category = ? WHERE id = ? AND user_id = ?";
                    $params = [$title, $description, $date_completed, $hours, $points, $category, $entry_id, $_SESSION['user_id']];
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    $new_files_count = 0;
                    if (isset($_FILES['edit_supporting_docs']) && !empty($_FILES['edit_supporting_docs']['name'][0])) {
                        $uploaded_files = handleMultipleFileUploads($_FILES['edit_supporting_docs'], $_SESSION['user_id']);
                        
                        if (!empty($uploaded_files)) {
                            saveCPDDocuments($pdo, $entry_id, $uploaded_files);
                            $new_files_count = count($uploaded_files);
                        }
                    }
                    
                    if ($result || $new_files_count > 0) {
                        $message = "CPD entry updated successfully!";
                        if ($new_files_count > 0) {
                            $message .= " Added $new_files_count new document(s).";
                        }
                        echo "<div class='alert alert-success' style='max-width: 1400px; margin: 1rem auto;'>$message</div>";
                        
                        $updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
                        if ($updated_goals > 0) {
                            debugLog("Updated progress for $updated_goals goal(s)");
                        }
                    } else {
                        echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>No changes were made to the entry.</div>";
                    }
                } else {
                    echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>Entry not found or access denied.</div>";
                }
            } catch (PDOException $e) {
                error_log("Update error: " . $e->getMessage());
                echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            foreach ($validation_errors as $error) {
                echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>" . htmlspecialchars($error) . "</div>";
            }
        }
    }
    
    // Handle add entry
    if (isset($_POST['add_entry'])) {
        $title = trim(htmlspecialchars($_POST['title']));
        $description = trim(htmlspecialchars($_POST['description']));
        $date_completed = $_POST['date_completed'];
        $hours = floatval($_POST['hours']);
        $category = htmlspecialchars($_POST['category']);
        $points = !empty($_POST['points']) ? floatval($_POST['points']) : null;
        
        $validation_errors = validateCPDEntry($_POST);
        
        if (empty($validation_errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO cpd_entries (user_id, title, description, date_completed, hours, points, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $title, 
                    $description, 
                    $date_completed, 
                    $hours,
                    $points,
                    $category
                ]);
                
                $entry_id = $pdo->lastInsertId();
                
                if (isset($_FILES['supporting_docs']) && !empty($_FILES['supporting_docs']['name'][0])) {
                    $uploaded_files = handleMultipleFileUploads($_FILES['supporting_docs'], $_SESSION['user_id']);
                    
                    if (!empty($uploaded_files)) {
                        saveCPDDocuments($pdo, $entry_id, $uploaded_files);
                        echo "<div class='alert alert-success' style='max-width: 1400px; margin: 1rem auto;'>CPD entry added successfully with " . count($uploaded_files) . " document(s)!</div>";
                    } else {
                        echo "<div class='alert alert-warning' style='max-width: 1400px; margin: 1rem auto;'>CPD entry added but no documents were uploaded.</div>";
                    }
                } else {
                    echo "<div class='alert alert-success' style='max-width: 1400px; margin: 1rem auto;'>CPD entry added successfully!</div>";
                }
                
                $updated_goals = updateUserGoalProgress($pdo, $_SESSION['user_id']);
                if ($updated_goals > 0) {
                    debugLog("Updated progress for $updated_goals goal(s)");
                }
            } catch (PDOException $e) {
                error_log("CPD entry error: " . $e->getMessage());
                echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>Error adding CPD entry: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            foreach ($validation_errors as $error) {
                echo "<div class='alert alert-error' style='max-width: 1400px; margin: 1rem auto;'>" . htmlspecialchars($error) . "</div>";
            }
        }
    }
}

// Get user's CPD entries
try {
    $stmt = $pdo->prepare("
        SELECT ce.*, 
               u.username as reviewed_by_username,
               r.name as reviewer_role
        FROM cpd_entries ce
        LEFT JOIN users u ON ce.reviewed_by = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE ce.user_id = ? 
        ORDER BY ce.date_completed DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $entries = $stmt->fetchAll();
    
    foreach ($entries as &$entry) {
        $entry['documents'] = getCPDDocuments($pdo, $entry['id']);
    }
    
    debugLog("Loaded " . count($entries) . " entries for user");
} catch (PDOException $e) {
    error_log("Fetch entries error: " . $e->getMessage());
    $entries = [];
}

// Get statistics
$total_hours = getTotalCPDHours($pdo, $_SESSION['user_id']);
debugLog("Total hours: $total_hours");

$current_month = date('Y-m');
try {
    $stmt = $pdo->prepare("
        SELECT SUM(hours) as month_hours 
        FROM cpd_entries 
        WHERE user_id = ? 
        AND DATE_FORMAT(date_completed, '%Y-%m') = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $current_month]);
    $month_result = $stmt->fetch();
    $month_hours = $month_result['month_hours'] ?? 0;
} catch (PDOException $e) {
    error_log("Month hours error: " . $e->getMessage());
    $month_hours = 0;
}

$total_points = 0;
foreach ($entries as $entry) {
    if (isset($entry['points']) && $entry['points'] !== null && $entry['points'] > 0) {
        $total_points += floatval($entry['points']);
    }
}
debugLog("Total points: $total_points");

// Get active goals using the same function as the goals widget
$active_goals = getUserGoals($pdo, $_SESSION['user_id'], 'active');

// Calculate average progress for the stats card
$total_progress = 0;
$goals_with_progress = 0;

foreach ($active_goals as &$goal) {
    // Get the user's specific progress for this goal
    $stmt = $pdo->prepare("
        SELECT current_hours as current_value, last_entry_date as last_updated
        FROM goal_progress 
        WHERE goal_id = ? AND user_id = ?
    ");
    $stmt->execute([$goal['id'], $_SESSION['user_id']]);
    $user_progress = $stmt->fetch();
    
    if ($user_progress) {
        $current_value = $user_progress['current_value'] ?? 0;
        // Determine target value based on what's available in the goal
        $target_value = $goal['target_hours'] ?? 1;
        $progress = ($current_value / $target_value) * 100;
        $total_progress += min($progress, 100);
        $goals_with_progress++;
        
        // Add progress data to the goal for later use
        $goal['current_value'] = $current_value;
        $goal['target_value'] = $target_value;
        $goal['progress_percentage'] = $progress;
    } else {
        // No progress yet, set defaults
        $goal['current_value'] = 0;
        $goal['target_value'] = $goal['target_hours'] ?? 1;
        $goal['progress_percentage'] = 0;
        $goals_with_progress++;
    }
}

$avg_progress = $goals_with_progress > 0 ? round($total_progress / $goals_with_progress) : 0;

// Keep only top 3 for preview
$active_goals_preview = array_slice($active_goals, 0, 3);

$recent_entries = array_slice($entries, 0, 5);
$user_teams = getUserTeams($pdo, $_SESSION['user_id']);

// Set page title and include header
$pageTitle = "Dashboard";
require_once 'includes/header.php';
?>

<style>
    .hero-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    }
    
    .hero-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }
    
    .hero-title h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    
    .hero-title p {
        opacity: 0.9;
        font-size: 1.1rem;
    }
    
    .quick-actions {
        display: flex;
        gap: 1rem;
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    
    .stat-card {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 1.5rem;
    }
    
    .stat-label {
        opacity: 0.9;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    
    .stat-sublabel {
        opacity: 0.8;
        font-size: 0.85rem;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .section-header h2 {
        font-size: 1.5rem;
        color: #2c3e50;
    }
    
    .view-all-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .view-all-link:hover {
        text-decoration: underline;
    }
    
    .goals-preview, .recent-entries, .all-entries {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    
    .goal-item {
        padding: 1.25rem;
        border: 1px solid #e1e8ed;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .goal-item:last-child {
        margin-bottom: 0;
    }
    
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .goal-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .goal-deadline {
        color: #f59e0b;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .progress-bar {
        height: 8px;
        background: #e1e8ed;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
    }
    
    .progress-text {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #6b7280;
    }
    
    .entries-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .entries-table th {
        text-align: left;
        font-weight: 600;
        color: #6b7280;
        font-size: 0.85rem;
        text-transform: uppercase;
        padding: 0.75rem 1rem;
        border-bottom: 2px solid #e1e8ed;
    }
    
    .entries-table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .entries-table tr:hover {
        background: #f9fafb;
    }
    
    .entry-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .entry-description {
        color: #6b7280;
        font-size: 0.9rem;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-size: 2rem;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    
    .fab:hover {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
    }
    
    .teams-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border-left: 4px solid #6c757d;
    }
    
    .teams-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .team-badge {
        background: #e9f7fe;
        border: 1px solid #b3d7ff;
        border-radius: 20px;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .team-name {
        font-weight: bold;
        color: #007cba;
    }
    
    .team-description {
        color: #666;
    }
    
    .document-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .document-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    
    .document-link {
        color: #667eea;
        text-decoration: none;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .document-link:hover {
        text-decoration: underline;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    
    @media (max-width: 768px) {
        .hero-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .quick-actions {
            width: 100%;
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Hero Stats -->
<div class="hero-stats">
    <div class="hero-header">
        <div class="hero-title">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
            <p>You're making great progress this year</p>
        </div>
        <div class="quick-actions">
            <a href="#all-entries" class="btn-secondary">📊 View All Entries</a>
            <a href="user_goals.php" class="btn-secondary">🎯 My Goals</a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Hours</div>
            <div class="stat-value"><?php echo number_format($total_hours, 1); ?></div>
            <div class="stat-sublabel">This year</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">CPD Entries</div>
            <div class="stat-value"><?php echo count($entries); ?></div>
            <div class="stat-sublabel">Across all categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Goals Progress</div>
            <div class="stat-value">
                <?php 
                if (count($active_goals) > 0) {
                    echo $avg_progress . '%';
                } else {
                    echo 'N/A';
                }
                ?>
            </div>
            <div class="stat-sublabel"><?php echo count($active_goals); ?> active goal(s)</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">This Month</div>
            <div class="stat-value"><?php echo number_format($month_hours, 1); ?></div>
            <div class="stat-sublabel">Hours logged</div>
        </div>
    </div>
</div>

<!-- Active Goals Preview -->
<?php if (count($active_goals_preview) > 0): ?>
<div class="goals-preview">
    <div class="section-header">
        <h2>🎯 Active Goals</h2>
        <a href="user_goals.php" class="view-all-link">View all <span>→</span></a>
    </div>
    
    <?php foreach ($active_goals_preview as $goal): 
        $progress = $goal['progress_percentage'] ?? 0;
        $days_remaining = ceil((strtotime($goal['deadline']) - time()) / (60 * 60 * 24));
    ?>
    <div class="goal-item">
        <div class="goal-header">
            <div>
                <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                <?php if ($days_remaining > 0): ?>
                <div class="goal-deadline">⏰ <?php echo $days_remaining; ?> days remaining</div>
                <?php else: ?>
                <div class="goal-deadline" style="color: #ef4444;">⏰ Overdue</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
        </div>
        <div class="progress-text">
            <span><?php echo number_format($goal['current_value'], 1); ?> / <?php echo number_format($goal['target_value'], 1); ?> <?php echo 'hours'; ?></span>
            <span><?php echo round($progress); ?>%</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Teams Section -->
<?php if (count($user_teams) > 0): ?>
<div class="teams-section">
    <h2>👥 My Teams</h2>
    <div class="teams-list">
        <?php foreach ($user_teams as $team): ?>
        <div class="team-badge">
            <span class="team-name"><?php echo htmlspecialchars($team['name']); ?></span>
            <?php if (!empty($team['description'])): ?>
            <span class="team-description"> - <?php echo htmlspecialchars($team['description']); ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Entries -->
<div class="recent-entries">
    <div class="section-header">
        <h2>📝 Recent Entries</h2>
        <a href="#all-entries" class="view-all-link">View all <span>→</span></a>
    </div>
    
    <?php if (count($recent_entries) > 0): ?>
    <table class="entries-table">
        <thead>
            <tr>
                <th>Activity</th>
                <th>Date</th>
                <th>Category</th>
                <th>Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent_entries as $entry): ?>
            <tr onclick="openEditModal(<?php echo $entry['id']; ?>)" style="cursor: pointer;">
                <td>
                    <div class="entry-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                    <div class="entry-description"><?php echo htmlspecialchars(substr($entry['description'], 0, 50)); ?>...</div>
                </td>
                <td><?php echo date('M d, Y', strtotime($entry['date_completed'])); ?></td>
                <td><?php echo htmlspecialchars($entry['category']); ?></td>
                <td><strong><?php echo $entry['hours']; ?> hrs</strong></td>
                <td>
                    <?php if ($entry['review_status'] === 'approved'): ?>
                    <span class="status-badge status-approved">✓ Approved</span>
                    <?php else: ?>
                    <span class="status-badge status-pending">⏳ Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 3rem; color: #6b7280;">
        <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">📝</div>
        <h3>No entries yet</h3>
        <p>Add your first CPD entry to get started!</p>
    </div>
    <?php endif; ?>
</div>

<!-- All Entries Section -->
<div id="all-entries" class="all-entries">
    <div class="section-header">
        <h2>📋 All CPD Entries</h2>
        <span><?php echo count($entries); ?> total entries</span>
    </div>
    
    <?php if (count($entries) > 0): ?>
    <form id="deleteForm" method="POST" enctype="multipart/form-data">
        <table class="entries-table">
            <thead>
                <tr>
                    <th style="width: 50px;">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                    </th>
                    <th>Activity</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Hours</th>
                    <th>Points</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="selected_entries[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox">
                    </td>
                    <td>
                        <div class="entry-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                        <?php if (!empty($entry['description'])): ?>
                        <div class="entry-description"><?php echo htmlspecialchars(substr($entry['description'], 0, 100)); ?>...</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($entry['documents'])): ?>
                        <div class="document-list" style="margin-top: 0.5rem;">
                            <?php foreach (array_slice($entry['documents'], 0, 2) as $doc): ?>
                            <div class="document-item">
                                <a href="download.php?file=<?php echo urlencode($doc['filename']); ?>" target="_blank" class="document-link">
                                    📄 <?php echo htmlspecialchars($doc['original_filename']); ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($entry['documents']) > 2): ?>
                            <div class="document-item">
                                <span style="color: #6b7280; font-size: 0.8rem;">
                                    +<?php echo count($entry['documents']) - 2; ?> more document(s)
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($entry['date_completed'])); ?></td>
                    <td><?php echo htmlspecialchars($entry['category']); ?></td>
                    <td><strong><?php echo $entry['hours']; ?> hrs</strong></td>
                    <td>
                        <?php if ($entry['points'] !== null && $entry['points'] > 0): ?>
                        <strong><?php echo number_format($entry['points'], 2); ?> pts</strong>
                        <?php else: ?>
                        <span style="color: #9ca3af;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry['review_status'] === 'approved'): ?>
                        <span class="status-badge status-approved">✓ Approved</span>
                        <?php else: ?>
                        <span class="status-badge status-pending">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" onclick="openEditModal(<?php echo $entry['id']; ?>)" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                            Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #f9fafb; font-weight: 600;">
                    <td colspan="4" style="text-align: right;">Total:</td>
                    <td><?php echo number_format($total_hours, 1); ?> hours</td>
                    <td><?php echo number_format($total_points, 2); ?> points</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        
        <div id="bulkActions" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <button type="button" onclick="selectAllEntries(true)" class="btn btn-outline">Select All</button>
            <button type="button" onclick="selectAllEntries(false)" class="btn btn-outline">Deselect All</button>
            <button type="submit" name="delete_entries" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected entries?')">
                🗑️ Delete Selected
            </button>
            <span id="selectedCount" style="margin-left: 1rem; color: #6b7280;"></span>
        </div>
    </form>
    <?php else: ?>
    <div style="text-align: center; padding: 3rem; color: #6b7280;">
        <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">📋</div>
        <h3>No CPD entries yet</h3>
        <p>Add your first entry using the button above!</p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/goals_widget.php'; ?>

<!-- Add Entry Modal -->
<div id="addEntryModal" class="form-modal">
    <div class="form-modal-content">
        <span class="close" onclick="document.getElementById('addEntryModal').style.display='none'" style="position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</span>
        <h2>Add New CPD Entry</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" required maxlength="255" placeholder="Enter activity title">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="description" rows="3" maxlength="2000" placeholder="Describe the activity..."></textarea>
            </div>
            <div class="form-group">
                <label>Date Completed:</label>
                <input type="date" name="date_completed" required>
            </div>
            <div class="form-group">
                <label>Hours:</label>
                <input type="number" name="hours" step="0.5" min="0.5" max="100" required placeholder="e.g., 2.5">
            </div>
            <div class="form-group">
                <label>Points (Optional):</label>
                <input type="number" name="points" step="0.01" min="0" max="9999.99" placeholder="e.g., 5.5">
                <small style="color: #6b7280; display: block; margin-top: 0.25rem;">Optional CPD points for this activity</small>
            </div>
            <div class="form-group">
                <label>Category:</label>
                <select name="category">
                    <option value="Training">Training</option>
                    <option value="Conference">Conference</option>
                    <option value="Reading">Reading</option>
                    <option value="Online Course">Online Course</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Supporting Documentation:</label>
                <input type="file" name="supporting_docs[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
                <small style="color: #6b7280; display: block; margin-top: 0.25rem;">Max 10 files, 10MB each - PDF, JPEG, PNG, or Word docs</small>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_entry" class="btn btn-success">Add Entry</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addEntryModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Entry Modal -->
<div id="editModal" class="form-modal">
    <div class="form-modal-content">
        <span class="close" onclick="closeEditModal()" style="position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</span>
        <h2>Edit CPD Entry</h2>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="edit_entry_id" name="entry_id">
            
            <div class="form-group">
                <label>Title:</label>
                <input type="text" id="edit_title" name="edit_title" required maxlength="255">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea id="edit_description" name="edit_description" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="form-group">
                <label>Date Completed:</label>
                <input type="date" id="edit_date_completed" name="edit_date_completed" required>
            </div>
            <div class="form-group">
                <label>Hours:</label>
                <input type="number" id="edit_hours" name="edit_hours" step="0.5" min="0.5" max="100" required>
            </div>
            <div class="form-group">
                <label>Points (Optional):</label>
                <input type="number" id="edit_points" name="edit_points" step="0.01" min="0" max="9999.99">
                <small style="color: #6b7280; display: block; margin-top: 0.25rem;">Optional CPD points for this activity</small>
            </div>
            <div class="form-group">
                <label>Category:</label>
                <select id="edit_category" name="edit_category">
                    <option value="Training">Training</option>
                    <option value="Conference">Conference</option>
                    <option value="Reading">Reading</option>
                    <option value="Online Course">Online Course</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Add More Documentation:</label>
                <input type="file" id="edit_supporting_docs" name="edit_supporting_docs[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple>
                <small style="color: #6b7280; display: block; margin-top: 0.25rem;">Max 10 files, 10MB each</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_entry" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" onclick="document.getElementById('addEntryModal').style.display='block'" title="Add CPD Entry">+</button>

<script>
    // Entry data for edit modal
    let entryData = <?php echo json_encode($entries); ?>;
    
    // Bulk selection functions
    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        const selectAll = document.getElementById('selectAll');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        
        updateBulkActions();
    }
    
    function selectAllEntries(select) {
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        const selectAll = document.getElementById('selectAll');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = select;
        });
        selectAll.checked = select;
        
        updateBulkActions();
    }
    
    function updateBulkActions() {
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        const selected = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        if (selected > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = selected + ' entry(ies) selected';
        } else {
            bulkActions.style.display = 'none';
        }
    }
    
    // Edit modal functionality
	function openEditModal(entryId) {
		const entry = entryData.find(e => e.id == entryId);
		if (!entry) {
			console.error('Entry not found:', entryId);
			return;
		}
		
		document.getElementById('edit_entry_id').value = entry.id;
		document.getElementById('edit_title').value = entry.title;
		document.getElementById('edit_description').value = entry.description || '';
		document.getElementById('edit_date_completed').value = entry.date_completed;
		document.getElementById('edit_hours').value = entry.hours;
		document.getElementById('edit_points').value = entry.points || '';
		document.getElementById('edit_category').value = entry.category;
		
		// Load existing documents if the function exists
		if (typeof loadExistingDocuments === 'function') {
			loadExistingDocuments(entryId);
		}
		
		document.getElementById('editModal').style.display = 'block';
	}
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.form-modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    };
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Bulk selection functionality
        const checkboxes = document.querySelectorAll('.entry-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });
        
        // Set today's date as default for new entries
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="date_completed"]').value = today;
    });
</script>

<?php require_once 'includes/footer.php'; ?>