<?php
// Admin-specific functions

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if current user is super admin (internal SaaS staff)
 */
function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

/**
 * Check if current user is admin OR super admin
 */
function isAdminOrSuper() {
    return isAdmin() || isSuperAdmin();
}

/**
 * Get all users for admin view (excludes archived by default)
 * Regular admins only see users in their organisation
 */
function getAllUsers($pdo, $include_archived = false) {
    $where_clauses = [];
    $params = [];
    
    if (!$include_archived) {
        $where_clauses[] = "u.archived = 0";
    }
    
    // Regular admins can only see users in their organisation
    if (isAdmin() && !isSuperAdmin()) {
        $admin_org_id = getAdminOrganisationId($pdo, $_SESSION['user_id']);
        if ($admin_org_id) {
            $where_clauses[] = "u.organisation_id = ?";
            $params[] = $admin_org_id;
        }
    }
    
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at, u.archived, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        $where_clause
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
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
        <?php if (isSuperAdmin()): ?>
        <a href="system_admin_dashboard.php" <?php echo $current_page === 'system' ? 'class="active"' : ''; ?>>
            🚀 System Dashboard
        </a>
        <?php endif; ?>
        <a href="admin_dashboard.php" <?php echo $current_page === 'dashboard' ? 'class="active"' : ''; ?>>
            Dashboard
        </a>
        <a href="admin_manage_organisations.php" <?php echo $current_page === 'organisations' ? 'class="active"' : ''; ?>>
            <?php echo isSuperAdmin() ? 'Organisations' : 'My Organisation'; ?>
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

/**
 * Get admin's organization ID
 */
function getAdminOrganisationId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT organisation_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Check if admin can access organisation
 */
function canAdminAccessOrganisation($pdo, $user_id, $org_id) {
    // Super admins can access everything
    if (isSuperAdmin()) {
        return true;
    }
    
    // Regular admins can only access their own organisation
    if (isAdmin()) {
        $admin_org_id = getAdminOrganisationId($pdo, $user_id);
        return $admin_org_id == $org_id;
    }
    
    return false;
}
?>
