<?php
// Team-related functions

/**
 * Get all teams
 */
function getAllTeams($pdo) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(ut.user_id) as member_count,
               u.username as created_by_name
        FROM teams t
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN users u ON t.created_by = u.id
        GROUP BY t.id
        ORDER BY t.name
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get team by ID
 */
function getTeamById($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as created_by_name
        FROM teams t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetch();
}

/**
 * Create a new team
 */
function createTeam($pdo, $name, $description, $created_by) {
    $stmt = $pdo->prepare("INSERT INTO teams (name, description, created_by) VALUES (?, ?, ?)");
    return $stmt->execute([$name, $description, $created_by]);
}

/**
 * Update a team
 */
function updateTeam($pdo, $team_id, $name, $description) {
    $stmt = $pdo->prepare("UPDATE teams SET name = ?, description = ? WHERE id = ?");
    return $stmt->execute([$name, $description, $team_id]);
}

/**
 * Delete a team
 */
function deleteTeam($pdo, $team_id) {
    // First delete user-team relationships
    $stmt = $pdo->prepare("DELETE FROM user_teams WHERE team_id = ?");
    $stmt->execute([$team_id]);
    
    // Then delete the team
    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
    return $stmt->execute([$team_id]);
}

/**
 * Add user to a team
 */
function addUserToTeam($pdo, $user_id, $team_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_teams (user_id, team_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $team_id]);
    } catch (PDOException $e) {
        // If duplicate entry, return false
        if ($e->getCode() == 23000) { // Integrity constraint violation
            return false;
        }
        throw $e;
    }
}

/**
 * Remove user from a team
 */
function removeUserFromTeam($pdo, $user_id, $team_id) {
    $stmt = $pdo->prepare("DELETE FROM user_teams WHERE user_id = ? AND team_id = ?");
    return $stmt->execute([$user_id, $team_id]);
}

/**
 * Get teams for a specific user
 */
function getUserTeams($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT t.* 
        FROM teams t
        JOIN user_teams ut ON t.id = ut.team_id
        WHERE ut.user_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Get users in a specific team
 */
function getTeamMembers($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.created_at, ut.joined_at
        FROM users u
        JOIN user_teams ut ON u.id = ut.user_id
        WHERE ut.team_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Get users NOT in a specific team
 */
function getUsersNotInTeam($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email
        FROM users u
        WHERE u.id NOT IN (
            SELECT user_id FROM user_teams WHERE team_id = ?
        )
        ORDER BY u.username
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}

/**
 * Check if user is in a specific team
 */
function isUserInTeam($pdo, $user_id, $team_id) {
    $stmt = $pdo->prepare("SELECT id FROM user_teams WHERE user_id = ? AND team_id = ?");
    $stmt->execute([$user_id, $team_id]);
    return $stmt->fetch() !== false;
}

/**
 * Get team CPD statistics
 */
function getTeamCPDStats($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT ce.user_id) as active_users,
            COUNT(ce.id) as total_entries,
            COALESCE(SUM(ce.hours), 0) as total_hours,
            AVG(ce.hours) as avg_hours_per_user
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        WHERE ut.team_id = ?
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetch();
}

/**
 * Get all team members' CPD entries
 */
function getTeamCPDEntries($pdo, $team_id, $limit = null) {
    $limit_clause = $limit ? "LIMIT $limit" : "";
    
    $stmt = $pdo->prepare("
        SELECT ce.*, u.username
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        JOIN users u ON ce.user_id = u.id
        WHERE ut.team_id = ?
        ORDER BY ce.date_completed DESC, ce.created_at DESC
        $limit_clause
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetchAll();
}
?>
[file content end]