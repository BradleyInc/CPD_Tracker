<?php
// Organisation management functions

/**
 * Get organisation by ID
 */
function getOrganisationById($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT o.*,
               COUNT(DISTINCT u.id) as user_count,
               COUNT(DISTINCT d.id) as department_count
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id
        LEFT JOIN departments d ON o.id = d.organisation_id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetch();
}

/**
 * Get all organisations (super admin only)
 */
function getAllOrganisations($pdo) {
    $where_clause = "";
    $params = [];
    
    // Regular admins can only see their own organisation
    if (isAdmin() && !isSuperAdmin()) {
        require_once 'admin_functions.php';
        $admin_org_id = getAdminOrganisationId($pdo, $_SESSION['user_id']);
        if ($admin_org_id) {
            $where_clause = "WHERE o.id = ?";
            $params[] = $admin_org_id;
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT o.*,
               COUNT(DISTINCT u.id) as user_count,
               COUNT(DISTINCT d.id) as department_count
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id
        LEFT JOIN departments d ON o.id = d.organisation_id
        $where_clause
        GROUP BY o.id
        ORDER BY o.name
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Create a new organisation
 */
function createOrganisation($pdo, $name, $description, $subscription_plan, $billing_email, $max_users = 10) {
    $stmt = $pdo->prepare("
        INSERT INTO organisations (name, description, subscription_status, subscription_plan, billing_email, max_users, trial_ends_at)
        VALUES (?, ?, 'trial', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    return $stmt->execute([$name, $description, $subscription_plan, $billing_email, $max_users]);
}

/**
 * Update organisation
 */
function updateOrganisation($pdo, $org_id, $name, $description, $subscription_status, $subscription_plan, $billing_email, $max_users) {
    $stmt = $pdo->prepare("
        UPDATE organisations 
        SET name = ?, description = ?, subscription_status = ?, subscription_plan = ?, billing_email = ?, max_users = ?
        WHERE id = ?
    ");
    return $stmt->execute([$name, $description, $subscription_status, $subscription_plan, $billing_email, $max_users, $org_id]);
}

/**
 * Delete organisation (super admin only - cascades to all related data)
 */
function deleteOrganisation($pdo, $org_id) {
    $stmt = $pdo->prepare("DELETE FROM organisations WHERE id = ?");
    return $stmt->execute([$org_id]);
}

/**
 * Get organisation statistics
 */
function getOrganisationStats($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT d.id) as total_departments,
            COUNT(DISTINCT t.id) as total_teams,
            COUNT(DISTINCT ce.id) as total_cpd_entries,
            COALESCE(SUM(ce.hours), 0) as total_cpd_hours
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        LEFT JOIN departments d ON o.id = d.organisation_id
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN cpd_entries ce ON ut.user_id = ce.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetch();
}

/**
 * Check if user is organisation admin
 */
function isOrganisationAdmin($pdo, $user_id, $org_id = null) {
    // If org_id is provided, check specific organisation
    if ($org_id !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM organisation_admins 
            WHERE user_id = ? AND organisation_id = ?
        ");
        $stmt->execute([$user_id, $org_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Otherwise check if user is admin of any organisation
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM organisation_admins 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Assign user as organisation admin
 */
function assignOrganisationAdmin($pdo, $user_id, $org_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO organisation_admins (user_id, organisation_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $org_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return false; // Already assigned
        }
        throw $e;
    }
}

/**
 * Remove user as organisation admin
 */
function removeOrganisationAdmin($pdo, $user_id, $org_id) {
    $stmt = $pdo->prepare("DELETE FROM organisation_admins WHERE user_id = ? AND organisation_id = ?");
    return $stmt->execute([$user_id, $org_id]);
}

/**
 * Get organisation admins
 */
function getOrganisationAdmins($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, oa.assigned_at
        FROM organisation_admins oa
        JOIN users u ON oa.user_id = u.id
        WHERE oa.organisation_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetchAll();
}

/**
 * Check if organisation has reached user limit
 */
function hasReachedUserLimit($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT 
            o.max_users,
            COUNT(u.id) as current_users
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        WHERE o.id = ?
        GROUP BY o.id, o.max_users
    ");
    $stmt->execute([$org_id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return true; // Organisation not found
    }
    
    return $result['current_users'] >= $result['max_users'];
}

/**
 * Get user's organisation ID
 */
function getUserOrganisationId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT organisation_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Check if organisation subscription is active
 */
function isOrganisationActive($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT subscription_status 
        FROM organisations 
        WHERE id = ? AND subscription_status IN ('trial', 'active')
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Get organisations approaching user limit
 */
function getOrganisationsNearLimit($pdo, $threshold_percentage = 80) {
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.name,
            o.max_users,
            COUNT(u.id) as current_users,
            ROUND((COUNT(u.id) / o.max_users) * 100, 2) as usage_percentage
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        GROUP BY o.id, o.name, o.max_users
        HAVING usage_percentage >= ?
        ORDER BY usage_percentage DESC
    ");
    $stmt->execute([$threshold_percentage]);
    return $stmt->fetchAll();
}

/**
 * Get organisations with expiring trials
 */
function getExpiringTrials($pdo, $days_ahead = 7) {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               DATEDIFF(o.trial_ends_at, NOW()) as days_remaining
        FROM organisations o
        WHERE o.subscription_status = 'trial'
        AND o.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
        ORDER BY o.trial_ends_at
    ");
    $stmt->execute([$days_ahead]);
    return $stmt->fetchAll();
}
?>