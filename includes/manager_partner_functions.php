<?php
// Manager and Partner specific functions

/**
 * Check if current user is a manager
 */
function isManager() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager';
}

/**
 * Check if current user is a partner
 */
function isPartner() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'partner';
}

/**
 * Assign a manager to a team
 */
function assignManagerToTeam($pdo, $manager_id, $team_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO team_managers (team_id, manager_id) VALUES (?, ?)");
        return $stmt->execute([$team_id, $manager_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            return false;
        }
        throw $e;
    }
}

/**
 * Remove a manager from a team
 */
function removeManagerFromTeam($pdo, $manager_id, $team_id) {
    $stmt = $pdo->prepare("DELETE FROM team_managers WHERE manager_id = ? AND team_id = ?");
    return $stmt->execute([$manager_id, $team_id]);
}

/**
 * Assign a partner to a team
 */
function assignPartnerToTeam($pdo, $partner_id, $team_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO team_partners (team_id, partner_id) VALUES (?, ?)");
        return $stmt->execute([$team_id, $partner_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            return false;
        }
        throw $e;
    }
}

/**
 * Remove a partner from a team
 */
function removePartnerFromTeam($pdo, $partner_id, $team_id) {
    $stmt = $pdo->prepare("DELETE FROM team_partners WHERE partner_id = ? AND team_id = ?");
    return $stmt->execute([$partner_id, $team_id]);
}

/**
 * Get teams managed by a specific manager
 */
function getManagerTeams($pdo, $manager_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(DISTINCT ut.user_id) as member_count,
               u.username as created_by_name
        FROM teams t
        JOIN team_managers tm ON t.id = tm.team_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE tm.manager_id = ?
        GROUP BY t.id
        ORDER BY t.name
    ");
    $stmt->execute([$manager_id]);
    return $stmt->fetchAll();
}

/**
 * Get teams managed by a specific partner
 */
function getPartnerTeams($pdo, $partner_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(DISTINCT ut.user_id) as member_count,
               COUNT(DISTINCT tm.manager_id) as manager_count,
               u.username as created_by_name
        FROM teams t
        JOIN team_partners tp ON t.id = tp.team_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN team_managers tm ON t.id = tm.team_id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE tp.partner_id = ?
        GROUP BY t.id
        ORDER BY t.name
    ");
    $stmt->execute([$partner_id]);
    return $stmt->fetchAll();
}

/**
 * Get all managers assigned to a team (excludes archived by default)
 */
function getTeamManagers($pdo, $team_id, $include_archived = false) {
    $archived_clause = $include_archived ? "" : "AND u.archived = 0";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.archived, tm.assigned_at
        FROM users u
        JOIN team_managers tm ON u.id = tm.manager_id
        WHERE tm.team_id = ? $archived_clause
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Get all partners assigned to a team (excludes archived by default)
 */
function getTeamPartners($pdo, $team_id, $include_archived = false) {
    $archived_clause = $include_archived ? "" : "AND u.archived = 0";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.archived, tp.assigned_at
        FROM users u
        JOIN team_partners tp ON u.id = tp.partner_id
        WHERE tp.team_id = ? $archived_clause
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Check if user is a manager of a specific team
 */
function isManagerOfTeam($pdo, $manager_id, $team_id) {
    $stmt = $pdo->prepare("SELECT id FROM team_managers WHERE manager_id = ? AND team_id = ?");
    $stmt->execute([$manager_id, $team_id]);
    return $stmt->fetch() !== false;
}

/**
 * Check if user is a partner of a specific team
 */
function isPartnerOfTeam($pdo, $partner_id, $team_id) {
    $stmt = $pdo->prepare("SELECT id FROM team_partners WHERE partner_id = ? AND team_id = ?");
    $stmt->execute([$partner_id, $team_id]);
    return $stmt->fetch() !== false;
}

/**
 * Get team CPD summary for managers/partners
 */
function getTeamCPDSummary($pdo, $team_id, $start_date = null, $end_date = null, $include_archived = false) {
    $archived_clause = $include_archived ? "" : "AND u.archived = 0";
    
    $query = "
        SELECT 
            u.id as user_id,
            u.username,
            u.archived,
            COUNT(ce.id) as total_entries,
            COALESCE(SUM(ce.hours), 0) as total_hours,
            MAX(ce.date_completed) as last_entry_date
        FROM users u
        JOIN user_teams ut ON u.id = ut.user_id
        LEFT JOIN cpd_entries ce ON u.id = ce.user_id
        WHERE ut.team_id = ? $archived_clause
    ";
    
    $params = [$team_id];
    
    if ($start_date) {
        $query .= " AND ce.date_completed >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND ce.date_completed <= ?";
        $params[] = $end_date;
    }
    
    $query .= " GROUP BY u.id, u.username, u.archived ORDER BY u.username";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get CPD entries for a team member (for managers/partners to view)
 */
function getTeamMemberCPDEntries($pdo, $user_id, $team_id, $start_date = null, $end_date = null) {
    $query = "
        SELECT ce.*
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        WHERE ce.user_id = ? AND ut.team_id = ?
    ";
    
    $params = [$user_id, $team_id];
    
    if ($start_date) {
        $query .= " AND ce.date_completed >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND ce.date_completed <= ?";
        $params[] = $end_date;
    }
    
    $query .= " ORDER BY ce.date_completed DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get all users who are managers
 */
function getAllManagers($pdo) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'manager'
        ORDER BY u.username
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get all users who are partners
 */
function getAllPartners($pdo) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'partner'
        ORDER BY u.username
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get managers not assigned to a specific team
 */
function getManagersNotInTeam($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'manager'
        AND u.id NOT IN (
            SELECT manager_id FROM team_managers WHERE team_id = ?
        )
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Get partners not assigned to a specific team
 */
function getPartnersNotInTeam($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'partner'
        AND u.id NOT IN (
            SELECT partner_id FROM team_partners WHERE team_id = ?
        )
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Check if user can access team data (manager, partner, or admin)
 */
function canUserAccessTeamData($pdo, $user_id, $team_id) {
    // Admin users can access all teams
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    // Partners can access their teams
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'partner') {
        return isPartnerOfTeam($pdo, $user_id, $team_id);
    }
    
    // Managers can access their teams
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager') {
        return isManagerOfTeam($pdo, $user_id, $team_id);
    }
    
    return false;
}

/**
 * Render manager navigation
 */
function renderManagerNav($team_id, $current_page = '') {
    ?>
    <nav class="admin-nav">
        <a href="manager_goal_details.php">Team Goal Management</a>
    </nav>
    <?php
}

/**
 * Render partner navigation
 */
function renderPartnerNav($current_page = '') {
    ?>
    <nav class="admin-nav">
        <a href="partner_dashboard.php" <?php echo $current_page === 'dashboard' ? 'class="active"' : ''; ?>>My Teams</a>
		<a href="manager_goal_details.php">Team Goal Management</a>
        <a href="dashboard.php">My CPD</a>
    </nav>
    <?php
}
?>
