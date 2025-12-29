<?php
// Admin-specific functions

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get all users for admin view (excludes archived by default)
 */
function getAllUsers($pdo, $include_archived = false) {
    $where_clause = $include_archived ? "" : "WHERE u.archived = 0";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at, u.archived, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        $where_clause
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get all available roles
 */
function getAllRoles($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM roles ORDER BY id");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Update user role
 */
function updateUserRole($pdo, $user_id, $role_id) {
    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    return $stmt->execute([$role_id, $user_id]);
}

/**
 * Get user details by ID

function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
 */

/**
 * Render admin navigation
 */
function renderAdminNav($current_page = '') {
    ?>
    <nav class="admin-nav">
        <a href="system_admin_dashboard.php" <?php echo $current_page === 'system' ? 'class="active"' : ''; ?>>
            🚀 System Dashboard
        </a>
        <a href="admin_dashboard.php" <?php echo $current_page === 'dashboard' ? 'class="active"' : ''; ?>>
            Dashboard
        </a>
        <a href="admin_manage_organisations.php" <?php echo $current_page === 'organisations' ? 'class="active"' : ''; ?>>
            Organisations
        </a>
        <a href="admin_manage_users.php" <?php echo $current_page === 'users' ? 'class="active"' : ''; ?>>
            Manage Users
        </a>
        <a href="admin_manage_teams.php" <?php echo $current_page === 'teams' ? 'class="active"' : ''; ?>>
            Manage Teams
        </a>
        <a href="dashboard.php">Back to Main App</a>
    </nav>
    <?php
}

/**
 * Render team management navigation (for team-specific pages)
 */
function renderTeamNav($team_id, $current_page = '') {
    $team_id = intval($team_id);
    ?>
    <nav class="admin-nav">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_manage_teams.php">Manage Teams</a>
        <a href="admin_edit_team.php?id=<?php echo $team_id; ?>" <?php echo $current_page === 'details' ? 'class="active"' : ''; ?>>Team Details</a>
        <a href="admin_team_members.php?id=<?php echo $team_id; ?>" <?php echo $current_page === 'members' ? 'class="active"' : ''; ?>>Team Members</a>
        <a href="admin_team_leadership.php?id=<?php echo $team_id; ?>" <?php echo $current_page === 'leadership' ? 'class="active"' : ''; ?>>Team Leadership</a>
        <a href="admin_team_report.php?id=<?php echo $team_id; ?>" <?php echo $current_page === 'report' ? 'class="active"' : ''; ?>>Team Report</a>
        <a href="dashboard.php">Back to Main App</a>
    </nav>
    <?php
}
?>
