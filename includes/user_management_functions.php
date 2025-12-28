<?php
// User management functions for archive and delete

/**
 * Archive a user
 */
function archiveUser($pdo, $user_id, $archived_by) {
    $stmt = $pdo->prepare("UPDATE users SET archived = 1, archived_at = NOW(), archived_by = ? WHERE id = ?");
    return $stmt->execute([$archived_by, $user_id]);
}

/**
 * Unarchive a user
 */
function unarchiveUser($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE users SET archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Delete a user (admin only - cascades through foreign keys)
 */
function deleteUser($pdo, $user_id) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete user's CPD entries (and their files)
        $stmt = $pdo->prepare("SELECT supporting_docs FROM cpd_entries WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $entries = $stmt->fetchAll();
        
        foreach ($entries as $entry) {
            if ($entry['supporting_docs']) {
                $filepath = 'uploads/' . $entry['supporting_docs'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        }
        
        // Delete CPD entries
        $stmt = $pdo->prepare("DELETE FROM cpd_entries WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete team memberships
        $stmt = $pdo->prepare("DELETE FROM user_teams WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete manager assignments
        $stmt = $pdo->prepare("DELETE FROM team_managers WHERE manager_id = ?");
        $stmt->execute([$user_id]);
        
        // Delete partner assignments
        $stmt = $pdo->prepare("DELETE FROM team_partners WHERE partner_id = ?");
        $stmt->execute([$user_id]);
        
        // Finally delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if manager can archive a user
 * Manager can only archive regular users in their teams
 */
function canManagerArchiveUser($pdo, $manager_id, $target_user_id) {
    // Get target user's role
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$target_user_id]);
    $target_role = $stmt->fetchColumn();
    
    // Can only archive regular users
    if ($target_role !== 'user') {
        return false;
    }
    
    // Check if target user is in a team managed by this manager
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_teams ut
        JOIN team_managers tm ON ut.team_id = tm.team_id
        WHERE ut.user_id = ? AND tm.manager_id = ?
    ");
    $stmt->execute([$target_user_id, $manager_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if partner can archive a user
 * Partner can archive regular users and managers in their teams
 */
function canPartnerArchiveUser($pdo, $partner_id, $target_user_id) {
    // Get target user's role
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$target_user_id]);
    $target_role = $stmt->fetchColumn();
    
    // Can only archive users and managers (not other partners or admins)
    if (!in_array($target_role, ['user', 'manager'])) {
        return false;
    }
    
    // Check if target user is in a team managed by this partner
    // For regular users
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_teams ut
        JOIN team_partners tp ON ut.team_id = tp.team_id
        WHERE ut.user_id = ? AND tp.partner_id = ?
    ");
    $stmt->execute([$target_user_id, $partner_id]);
    if ($stmt->fetchColumn() > 0) {
        return true;
    }
    
    // For managers
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM team_managers tm
        JOIN team_partners tp ON tm.team_id = tp.team_id
        WHERE tm.manager_id = ? AND tp.partner_id = ?
    ");
    $stmt->execute([$target_user_id, $partner_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get all archived users (admin only)
 */
function getAllArchivedUsers($pdo) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.archived_at, 
               r.name as role_name,
               au.username as archived_by_username
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN users au ON u.archived_by = au.id
        WHERE u.archived = 1
        ORDER BY u.archived_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get archived users in manager's teams
 */
function getManagerArchivedUsers($pdo, $manager_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.email, u.archived_at,
               r.name as role_name,
               au.username as archived_by_username
        FROM users u
        JOIN user_teams ut ON u.id = ut.user_id
        JOIN team_managers tm ON ut.team_id = tm.team_id
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN users au ON u.archived_by = au.id
        WHERE u.archived = 1 AND tm.manager_id = ?
        ORDER BY u.archived_at DESC
    ");
    $stmt->execute([$manager_id]);
    return $stmt->fetchAll();
}

/**
 * Get archived users in partner's teams
 */
function getPartnerArchivedUsers($pdo, $partner_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.email, u.archived_at,
               r.name as role_name,
               au.username as archived_by_username
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN users au ON u.archived_by = au.id
        WHERE u.archived = 1 
        AND (
            u.id IN (
                SELECT ut.user_id 
                FROM user_teams ut
                JOIN team_partners tp ON ut.team_id = tp.team_id
                WHERE tp.partner_id = ?
            )
            OR u.id IN (
                SELECT tm.manager_id
                FROM team_managers tm
                JOIN team_partners tp ON tm.team_id = tp.team_id
                WHERE tp.partner_id = ?
            )
        )
        ORDER BY u.archived_at DESC
    ");
    $stmt->execute([$partner_id, $partner_id]);
    return $stmt->fetchAll();
}
?>
