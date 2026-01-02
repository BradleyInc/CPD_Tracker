<?php
// CPD Entry Review Functions

/**
 * Add or update review for a CPD entry
 */
function reviewCPDEntry($pdo, $entry_id, $reviewer_id, $status, $comments = null) {
    try {
        $stmt = $pdo->prepare("
            UPDATE cpd_entries 
            SET review_status = ?,
                review_comments = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $comments, $reviewer_id, $entry_id]);
    } catch (PDOException $e) {
        error_log("Review CPD entry error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get review information for a CPD entry
 */
function getCPDEntryReview($pdo, $entry_id) {
    $stmt = $pdo->prepare("
        SELECT ce.review_status, ce.review_comments, ce.reviewed_at,
               u.username as reviewed_by_username,
               r.name as reviewer_role
        FROM cpd_entries ce
        LEFT JOIN users u ON ce.reviewed_by = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE ce.id = ?
    ");
    $stmt->execute([$entry_id]);
    return $stmt->fetch();
}

/**
 * Check if user can review a CPD entry
 * Managers can review entries of their team members
 * Partners can review entries of members in their teams/departments
 */
function canUserReviewEntry($pdo, $user_id, $entry_id) {
    // Get the entry's owner
    $stmt = $pdo->prepare("SELECT user_id FROM cpd_entries WHERE id = ?");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        return false;
    }
    
    $entry_owner_id = $entry['user_id'];
    
    // Check user role
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_role = $stmt->fetchColumn();
    
    // Admin can review any entry
    if ($user_role === 'admin') {
        return true;
    }
    
    // Manager can review entries of users in their teams
    if ($user_role === 'manager') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_managers tm ON ut.team_id = tm.team_id
            WHERE ut.user_id = ? AND tm.manager_id = ?
        ");
        $stmt->execute([$entry_owner_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Partner can review entries of users in their teams/departments
    if ($user_role === 'partner') {
        // Check if user is in partner's teams
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_partners tp ON ut.team_id = tp.team_id
            WHERE ut.user_id = ? AND tp.partner_id = ?
        ");
        $stmt->execute([$entry_owner_id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // Check if user is in partner's departments
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN teams t ON ut.team_id = t.id
            JOIN department_partners dp ON t.department_id = dp.department_id
            WHERE ut.user_id = ? AND dp.partner_id = ?
        ");
        $stmt->execute([$entry_owner_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    return false;
}

/**
 * Get count of pending reviews for a user's teams
 */
function getPendingReviewCount($pdo, $reviewer_id) {
    $stmt = $pdo->prepare("
        SELECT r.name FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$reviewer_id]);
    $role = $stmt->fetchColumn();
    
    if ($role === 'admin') {
        // Admin sees all pending reviews
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM cpd_entries 
            WHERE review_status = 'pending'
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    } elseif ($role === 'manager') {
        // Manager sees pending reviews from their team members
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.id)
            FROM cpd_entries ce
            JOIN user_teams ut ON ce.user_id = ut.user_id
            JOIN team_managers tm ON ut.team_id = tm.team_id
            WHERE tm.manager_id = ? AND ce.review_status = 'pending'
        ");
        $stmt->execute([$reviewer_id]);
        return $stmt->fetchColumn();
    } elseif ($role === 'partner') {
        // Partner sees pending reviews from their teams/departments
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.id)
            FROM cpd_entries ce
            JOIN user_teams ut ON ce.user_id = ut.user_id
            JOIN teams t ON ut.team_id = t.id
            WHERE ce.review_status = 'pending'
            AND (
                t.id IN (SELECT team_id FROM team_partners WHERE partner_id = ?)
                OR t.department_id IN (SELECT department_id FROM department_partners WHERE partner_id = ?)
            )
        ");
        $stmt->execute([$reviewer_id, $reviewer_id]);
        return $stmt->fetchColumn();
    }
    
    return 0;
}

/**
 * Get statistics about reviews for a team
 */
function getTeamReviewStats($pdo, $team_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_entries,
            SUM(CASE WHEN review_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN review_status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        WHERE ut.team_id = ?
    ");
    $stmt->execute([$team_id]);
    return $stmt->fetch();
}

/**
 * Bulk approve entries
 */
function bulkApproveEntries($pdo, $entry_ids, $reviewer_id) {
    try {
        $pdo->beginTransaction();
        
        foreach ($entry_ids as $entry_id) {
            if (canUserReviewEntry($pdo, $reviewer_id, $entry_id)) {
                reviewCPDEntry($pdo, $entry_id, $reviewer_id, 'approved');
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk approve error: " . $e->getMessage());
        return false;
    }
}
?>
