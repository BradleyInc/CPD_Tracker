<?php
// Department management functions

/**
 * Get department by ID
 */
function getDepartmentById($pdo, $dept_id) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               o.name as organisation_name,
               u.username as created_by_name,
               COUNT(DISTINCT t.id) as team_count,
               COUNT(DISTINCT ut.user_id) as member_count
        FROM departments d
        JOIN organisations o ON d.organisation_id = o.id
        LEFT JOIN users u ON d.created_by = u.id
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        WHERE d.id = ?
        GROUP BY d.id
    ");
    $stmt->execute([$dept_id]);
    return $stmt->fetch();
}

/**
 * Get all departments for an organisation
 */
function getOrganisationDepartments($pdo, $org_id) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COUNT(DISTINCT t.id) as team_count,
               COUNT(DISTINCT ut.user_id) as member_count,
               COUNT(DISTINCT dp.partner_id) as partner_count
        FROM departments d
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN department_partners dp ON d.id = dp.department_id
        WHERE d.organisation_id = ?
        GROUP BY d.id
        ORDER BY d.name
    ");
    $stmt->execute([$org_id]);
    return $stmt->fetchAll();
}

/**
 * Create a new department
 */
function createDepartment($pdo, $org_id, $name, $description, $created_by) {
    $stmt = $pdo->prepare("
        INSERT INTO departments (organisation_id, name, description, created_by) 
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$org_id, $name, $description, $created_by]);
}

/**
 * Update a department
 */
function updateDepartment($pdo, $dept_id, $name, $description) {
    $stmt = $pdo->prepare("
        UPDATE departments 
        SET name = ?, description = ? 
        WHERE id = ?
    ");
    return $stmt->execute([$name, $description, $dept_id]);
}

/**
 * Delete a department
 */
function deleteDepartment($pdo, $dept_id) {
    // This will cascade and remove teams and their associations
    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
    return $stmt->execute([$dept_id]);
}

/**
 * Assign partner to department
 */
function assignPartnerToDepartment($pdo, $partner_id, $dept_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO department_partners (department_id, partner_id) VALUES (?, ?)");
        return $stmt->execute([$dept_id, $partner_id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return false; // Already assigned
        }
        throw $e;
    }
}

/**
 * Remove partner from department
 */
function removePartnerFromDepartment($pdo, $partner_id, $dept_id) {
    $stmt = $pdo->prepare("DELETE FROM department_partners WHERE partner_id = ? AND department_id = ?");
    return $stmt->execute([$partner_id, $dept_id]);
}

/**
 * Get partners assigned to a department
 */
function getDepartmentPartners($pdo, $dept_id, $include_archived = false) {
    $archived_clause = $include_archived ? "" : "AND u.archived = 0";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.archived, dp.assigned_at
        FROM department_partners dp
        JOIN users u ON dp.partner_id = u.id
        WHERE dp.department_id = ? $archived_clause
        ORDER BY u.username
    ");
    $stmt->execute([$dept_id]);
    return $stmt->fetchAll();
}

/**
 * Get partners NOT assigned to a department
 */
function getPartnersNotInDepartment($pdo, $dept_id, $org_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'partner' 
        AND u.organisation_id = ?
        AND u.archived = 0
        AND u.id NOT IN (
            SELECT partner_id FROM department_partners WHERE department_id = ?
        )
        ORDER BY u.username
    ");
    $stmt->execute([$org_id, $dept_id]);
    return $stmt->fetchAll();
}

/**
 * Get departments for a specific partner
 */
function getPartnerDepartments($pdo, $partner_id) {
    $stmt = $pdo->prepare("
        SELECT d.*,
               o.name as organisation_name,
               COUNT(DISTINCT t.id) as team_count,
               COUNT(DISTINCT ut.user_id) as member_count
        FROM departments d
        JOIN department_partners dp ON d.id = dp.department_id
        JOIN organisations o ON d.organisation_id = o.id
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        WHERE dp.partner_id = ?
        GROUP BY d.id
        ORDER BY d.name
    ");
    $stmt->execute([$partner_id]);
    return $stmt->fetchAll();
}

/**
 * Check if partner is assigned to a specific department
 */
function isPartnerOfDepartment($pdo, $partner_id, $dept_id) {
    $stmt = $pdo->prepare("SELECT id FROM department_partners WHERE partner_id = ? AND department_id = ?");
    $stmt->execute([$partner_id, $dept_id]);
    return $stmt->fetch() !== false;
}

/**
 * Get teams in a department

function getDepartmentTeams($pdo, $dept_id) {
    $stmt = $pdo->prepare("
        SELECT t.*,
               COUNT(DISTINCT ut.user_id) as member_count,
               u.username as created_by_name
        FROM teams t
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.department_id = ?
        GROUP BY t.id
        ORDER BY t.name
    ");
    $stmt->execute([$dept_id]);
    return $stmt->fetchAll();
}
 */

/**
 * Get department CPD statistics
 */
function getDepartmentCPDStats($pdo, $dept_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT ce.user_id) as active_users,
            COUNT(ce.id) as total_entries,
            COALESCE(SUM(ce.hours), 0) as total_hours,
            AVG(ce.hours) as avg_hours_per_entry
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        JOIN teams t ON ut.team_id = t.id
        WHERE t.department_id = ?
    ");
    $stmt->execute([$dept_id]);
    return $stmt->fetch();
}

/**
 * Get all department members' CPD entries
 */
function getDepartmentCPDEntries($pdo, $dept_id, $start_date = null, $end_date = null, $limit = null) {
    $query = "
        SELECT ce.*, u.username, t.name as team_name
        FROM cpd_entries ce
        JOIN user_teams ut ON ce.user_id = ut.user_id
        JOIN teams t ON ut.team_id = t.id
        JOIN users u ON ce.user_id = u.id
        WHERE t.department_id = ?
    ";
    
    $params = [$dept_id];
    
    if ($start_date) {
        $query .= " AND ce.date_completed >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND ce.date_completed <= ?";
        $params[] = $end_date;
    }
    
    $query .= " ORDER BY ce.date_completed DESC, ce.created_at DESC";
    
    if ($limit) {
        $query .= " LIMIT " . intval($limit);
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get department member summary

function getDepartmentMemberSummary($pdo, $dept_id, $start_date = null, $end_date = null) {
    $query = "
        SELECT 
            u.id as user_id,
            u.username,
            t.name as team_name,
            COUNT(ce.id) as total_entries,
            COALESCE(SUM(ce.hours), 0) as total_hours,
            MAX(ce.date_completed) as last_entry_date
        FROM users u
        JOIN user_teams ut ON u.id = ut.user_id
        JOIN teams t ON ut.team_id = t.id
        LEFT JOIN cpd_entries ce ON u.id = ce.user_id
        WHERE t.department_id = ? AND u.archived = 0
    ";
    
    $params = [$dept_id];
    
    if ($start_date) {
        $query .= " AND (ce.date_completed >= ? OR ce.date_completed IS NULL)";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND (ce.date_completed <= ? OR ce.date_completed IS NULL)";
        $params[] = $end_date;
    }
    
    $query .= " GROUP BY u.id, u.username, t.name ORDER BY u.username";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
 */

/**
 * Check if user can access department (is partner, manager of team in dept, or admin)
 */
function canUserAccessDepartment($pdo, $user_id, $dept_id) {
    // Check if user is system admin or organisation admin
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    // Check if user is organisation admin for this department's organisation
    $stmt = $pdo->prepare("
        SELECT d.organisation_id 
        FROM departments d 
        WHERE d.id = ?
    ");
    $stmt->execute([$dept_id]);
    $org_id = $stmt->fetchColumn();
    
    if ($org_id && isOrganisationAdmin($pdo, $user_id, $org_id)) {
        return true;
    }
    
    // Check if partner of department
    if (isPartnerOfDepartment($pdo, $user_id, $dept_id)) {
        return true;
    }
    
    // Check if manager of any team in department
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM team_managers tm
        JOIN teams t ON tm.team_id = t.id
        WHERE t.department_id = ? AND tm.manager_id = ?
    ");
    $stmt->execute([$dept_id, $user_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Render department navigation
 */
function renderDepartmentNav($dept_id, $current_page = '') {
    $dept_id = intval($dept_id);
    ?>
    <nav class="admin-nav">
        <a href="partner_dashboard.php">Dashboard</a>
        <a href="partner_department_view.php?id=<?php echo $dept_id; ?>" <?php echo $current_page === 'overview' ? 'class="active"' : ''; ?>>Department Overview</a>
        <a href="partner_department_teams.php?id=<?php echo $dept_id; ?>" <?php echo $current_page === 'teams' ? 'class="active"' : ''; ?>>Teams</a>
        <a href="partner_department_members.php?id=<?php echo $dept_id; ?>" <?php echo $current_page === 'members' ? 'class="active"' : ''; ?>>Members</a>
        <a href="partner_department_report.php?id=<?php echo $dept_id; ?>" <?php echo $current_page === 'report' ? 'class="active"' : ''; ?>>Reports</a>
        <a href="dashboard.php">My CPD</a>
    </nav>
    <?php
}
?>