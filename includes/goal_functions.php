<?php
// CPD Goal Management Functions

/**
 * Create a new CPD goal
 */
function createGoal($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT INTO cpd_goals 
        (goal_type, target_user_id, target_team_id, target_department_id, set_by, title, description, target_hours, target_entries, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $data['goal_type'],
        $data['target_user_id'] ?? null,
        $data['target_team_id'] ?? null,
        $data['target_department_id'] ?? null,
        $data['set_by'],
        $data['title'],
        $data['description'] ?? null,
        $data['target_hours'],
        $data['target_entries'] ?? null,
        $data['deadline']
    ]);
    
    if ($result) {
        $goal_id = $pdo->lastInsertId();
        // Initialize progress tracking
        updateGoalProgress($pdo, $goal_id);
        return $goal_id;
    }
    
    return false;
}

/**
 * Update goal progress by calling stored procedure
 */
function updateGoalProgress($pdo, $goal_id) {
    try {
        $stmt = $pdo->prepare("CALL update_goal_progress(?)");
        $stmt->execute([$goal_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating goal progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Get goals for a specific user
 */
function getUserGoals($pdo, $user_id, $status = null) {
    $where_status = $status ? "AND g.status = ?" : "";
    $params = [$user_id, $user_id, $user_id, $user_id];
    if ($status) {
        $params[] = $status;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            u.username as set_by_name,
            gp.current_hours,
            gp.current_entries,
            gp.progress_percentage,
            gp.last_entry_date,
            DATEDIFF(g.deadline, CURDATE()) as days_remaining,
            CASE 
                WHEN g.goal_type = 'team' THEN t.name
                WHEN g.goal_type = 'department' THEN d.name
                ELSE NULL
            END as group_name
        FROM cpd_goals g
        LEFT JOIN users u ON g.set_by = u.id
        LEFT JOIN goal_progress gp ON g.id = gp.goal_id AND gp.user_id = ?
        LEFT JOIN teams t ON g.target_team_id = t.id
        LEFT JOIN departments d ON g.target_department_id = d.id
        WHERE (
            g.target_user_id = ?
            OR g.target_team_id IN (SELECT team_id FROM user_teams WHERE user_id = ?)
            OR g.target_department_id IN (
                SELECT d2.id FROM departments d2
                JOIN teams t2 ON d2.id = t2.department_id
                JOIN user_teams ut2 ON t2.id = ut2.team_id
                WHERE ut2.user_id = ?
            )
        )
        $where_status
        ORDER BY g.deadline ASC, g.status ASC
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get goals set by a manager for their team(s)
 */
function getManagerGoals($pdo, $manager_id) {
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            CASE 
                WHEN g.goal_type = 'individual' THEN u2.username
                WHEN g.goal_type = 'team' THEN t.name
            END as target_name,
            COUNT(DISTINCT gp.user_id) as affected_users,
            AVG(gp.progress_percentage) as avg_progress,
            DATEDIFF(g.deadline, CURDATE()) as days_remaining
        FROM cpd_goals g
        LEFT JOIN users u2 ON g.target_user_id = u2.id
        LEFT JOIN teams t ON g.target_team_id = t.id
        LEFT JOIN goal_progress gp ON g.id = gp.goal_id
        WHERE g.set_by = ?
        AND g.target_team_id IN (
            SELECT team_id FROM team_managers WHERE manager_id = ?
        )
        GROUP BY g.id
        ORDER BY g.deadline ASC, g.status ASC
    ");
    
    $stmt->execute([$manager_id, $manager_id]);
    return $stmt->fetchAll();
}

/**
 * Get all goals that a partner can see/manager
 * This includes:
 * 1. Goals set by the partner (g.set_by = ?)
 * 2. Goals assigned to departments the partner manages
 * 3. Goals assigned to teams the partner manages (directly or via department)
 */
function getPartnerGoals($pdo, $partner_id) {
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            CASE 
                WHEN g.goal_type = 'individual' THEN u2.username
                WHEN g.goal_type = 'team' THEN t.name
                WHEN g.goal_type = 'department' THEN d.name
            END as target_name,
            COUNT(DISTINCT gp.user_id) as affected_users,
            AVG(gp.progress_percentage) as avg_progress,
            DATEDIFF(g.deadline, CURDATE()) as days_remaining
        FROM cpd_goals g
        LEFT JOIN users u2 ON g.target_user_id = u2.id
        LEFT JOIN teams t ON g.target_team_id = t.id
        LEFT JOIN departments d ON g.target_department_id = d.id
        LEFT JOIN goal_progress gp ON g.id = gp.goal_id
        WHERE (
            -- Goals set by this partner
            g.set_by = ?
            OR 
            -- Goals assigned to departments this partner manages
            g.target_department_id IN (
                SELECT department_id FROM department_partners WHERE partner_id = ?
            )
            OR 
            -- Goals assigned to teams this partner manages (directly or via department)
            g.target_team_id IN (
                SELECT team_id FROM team_partners WHERE partner_id = ?
                UNION
                SELECT t2.id FROM teams t2
                JOIN departments d2 ON t2.department_id = d2.id
                JOIN department_partners dp ON d2.id = dp.department_id
                WHERE dp.partner_id = ?
            )
        )
        GROUP BY g.id
        ORDER BY g.deadline ASC, g.status ASC
    ");
    
    $stmt->execute([$partner_id, $partner_id, $partner_id, $partner_id]);
    return $stmt->fetchAll();
}

/**
 * Get team goals with individual progress
 */
function getTeamGoalProgress($pdo, $goal_id) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            gp.current_hours,
            gp.current_entries,
            gp.progress_percentage,
            gp.last_entry_date
        FROM goal_progress gp
        JOIN users u ON gp.user_id = u.id
        WHERE gp.goal_id = ?
        ORDER BY gp.progress_percentage DESC, u.username
    ");
    
    $stmt->execute([$goal_id]);
    return $stmt->fetchAll();
}

/**
 * Get goal by ID
 */
function getGoalById($pdo, $goal_id) {
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            u.username as set_by_name,
            CASE 
                WHEN g.goal_type = 'individual' THEN u2.username
                WHEN g.goal_type = 'team' THEN t.name
                WHEN g.goal_type = 'department' THEN d.name
            END as target_name,
            DATEDIFF(g.deadline, CURDATE()) as days_remaining
        FROM cpd_goals g
        LEFT JOIN users u ON g.set_by = u.id
        LEFT JOIN users u2 ON g.target_user_id = u2.id
        LEFT JOIN teams t ON g.target_team_id = t.id
        LEFT JOIN departments d ON g.target_department_id = d.id
        WHERE g.id = ?
    ");
    
    $stmt->execute([$goal_id]);
    return $stmt->fetch();
}

/**
 * Update goal
 */
function updateGoal($pdo, $goal_id, $data) {
    $stmt = $pdo->prepare("
        UPDATE cpd_goals 
        SET title = ?, description = ?, target_hours = ?, target_entries = ?, deadline = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['target_hours'],
        $data['target_entries'] ?? null,
        $data['deadline'],
        $goal_id
    ]);
    
    if ($result) {
        updateGoalProgress($pdo, $goal_id);
    }
    
    return $result;
}

/**
 * Cancel goal
 */
function cancelGoal($pdo, $goal_id) {
    $stmt = $pdo->prepare("UPDATE cpd_goals SET status = 'cancelled' WHERE id = ?");
    return $stmt->execute([$goal_id]);
}

/**
 * Delete goal
 */
function deleteGoal($pdo, $goal_id) {
    $stmt = $pdo->prepare("DELETE FROM cpd_goals WHERE id = ?");
    return $stmt->execute([$goal_id]);
}

/**
 * Get overdue goals
 */
function getOverdueGoals($pdo, $set_by = null) {
    // First check if user is a partner
    $is_partner = false;
    if ($set_by) {
        $stmt = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$set_by]);
        $role = $stmt->fetchColumn();
        $is_partner = ($role === 'partner');
    }
    
    if ($set_by && $is_partner) {
        // Partner-specific logic
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                CASE 
                    WHEN g.goal_type = 'individual' THEN u2.username
                    WHEN g.goal_type = 'team' THEN t.name
                    WHEN g.goal_type = 'department' THEN d.name
                END as target_name,
                AVG(gp.progress_percentage) as avg_progress,
                COUNT(DISTINCT gp.user_id) as affected_users
            FROM cpd_goals g
            LEFT JOIN users u2 ON g.target_user_id = u2.id
            LEFT JOIN teams t ON g.target_team_id = t.id
            LEFT JOIN departments d ON g.target_department_id = d.id
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id
            WHERE g.status = 'overdue'
            AND (
                -- Goals set by this partner
                g.set_by = ?
                OR 
                -- Goals assigned to departments this partner manages
                g.target_department_id IN (
                    SELECT department_id FROM department_partners WHERE partner_id = ?
                )
                OR 
                -- Goals assigned to teams this partner manages (directly or via department)
                g.target_team_id IN (
                    SELECT team_id FROM team_partners WHERE partner_id = ?
                    UNION
                    SELECT t2.id FROM teams t2
                    JOIN departments d2 ON t2.department_id = d2.id
                    JOIN department_partners dp ON d2.id = dp.department_id
                    WHERE dp.partner_id = ?
                )
            )
            GROUP BY g.id
            ORDER BY g.deadline ASC
        ");
        $stmt->execute([$set_by, $set_by, $set_by, $set_by]);
    } else {
        // Original logic for non-partners
        $where_clause = $set_by ? "AND g.set_by = ?" : "";
        $params = $set_by ? [$set_by] : [];
        
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                CASE 
                    WHEN g.goal_type = 'individual' THEN u2.username
                    WHEN g.goal_type = 'team' THEN t.name
                    WHEN g.goal_type = 'department' THEN d.name
                END as target_name,
                AVG(gp.progress_percentage) as avg_progress,
                COUNT(DISTINCT gp.user_id) as affected_users
            FROM cpd_goals g
            LEFT JOIN users u2 ON g.target_user_id = u2.id
            LEFT JOIN teams t ON g.target_team_id = t.id
            LEFT JOIN departments d ON g.target_department_id = d.id
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id
            WHERE g.status = 'overdue'
            $where_clause
            GROUP BY g.id
            ORDER BY g.deadline ASC
        ");
        
        $stmt->execute($params);
    }
    
    return $stmt->fetchAll();
}

/**
 * Get goals approaching deadline
 */
function getApproachingDeadlineGoals($pdo, $days = 7, $set_by = null) {
    // First check if user is a partner
    $is_partner = false;
    if ($set_by) {
        $stmt = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([$set_by]);
        $role = $stmt->fetchColumn();
        $is_partner = ($role === 'partner');
    }
    
    if ($set_by && $is_partner) {
        // Partner-specific logic
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                CASE 
                    WHEN g.goal_type = 'individual' THEN u2.username
                    WHEN g.goal_type = 'team' THEN t.name
                    WHEN g.goal_type = 'department' THEN d.name
                END as target_name,
                AVG(gp.progress_percentage) as avg_progress,
                DATEDIFF(g.deadline, CURDATE()) as days_remaining
            FROM cpd_goals g
            LEFT JOIN users u2 ON g.target_user_id = u2.id
            LEFT JOIN teams t ON g.target_team_id = t.id
            LEFT JOIN departments d ON g.target_department_id = d.id
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id
            WHERE g.status = 'active'
            AND DATEDIFF(g.deadline, CURDATE()) <= ?
            AND DATEDIFF(g.deadline, CURDATE()) >= 0
            AND (
                -- Goals set by this partner
                g.set_by = ?
                OR 
                -- Goals assigned to departments this partner manages
                g.target_department_id IN (
                    SELECT department_id FROM department_partners WHERE partner_id = ?
                )
                OR 
                -- Goals assigned to teams this partner manages (directly or via department)
                g.target_team_id IN (
                    SELECT team_id FROM team_partners WHERE partner_id = ?
                    UNION
                    SELECT t2.id FROM teams t2
                    JOIN departments d2 ON t2.department_id = d2.id
                    JOIN department_partners dp ON d2.id = dp.department_id
                    WHERE dp.partner_id = ?
                )
            )
            GROUP BY g.id
            ORDER BY g.deadline ASC
        ");
        $stmt->execute([$days, $set_by, $set_by, $set_by, $set_by]);
    } else {
        // Original logic for non-partners
        $where_clause = $set_by ? "AND g.set_by = ?" : "";
        $params = [$days];
        if ($set_by) {
            $params[] = $set_by;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                g.*,
                CASE 
                    WHEN g.goal_type = 'individual' THEN u2.username
                    WHEN g.goal_type = 'team' THEN t.name
                    WHEN g.goal_type = 'department' THEN d.name
                END as target_name,
                AVG(gp.progress_percentage) as avg_progress,
                DATEDIFF(g.deadline, CURDATE()) as days_remaining
            FROM cpd_goals g
            LEFT JOIN users u2 ON g.target_user_id = u2.id
            LEFT JOIN teams t ON g.target_team_id = t.id
            LEFT JOIN departments d ON g.target_department_id = d.id
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id
            WHERE g.status = 'active'
            AND DATEDIFF(g.deadline, CURDATE()) <= ?
            AND DATEDIFF(g.deadline, CURDATE()) >= 0
            $where_clause
            GROUP BY g.id
            ORDER BY g.deadline ASC
        ");
        
        $stmt->execute($params);
    }
    
    return $stmt->fetchAll();
}

/**
 * Get all goal templates
 */
function getGoalTemplates($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM goal_templates 
        WHERE is_active = 1 
        ORDER BY name
    ");
    return $stmt->fetchAll();
}

/**
 * Get goal template by ID
 */
function getGoalTemplateById($pdo, $template_id) {
    $stmt = $pdo->prepare("SELECT * FROM goal_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    return $stmt->fetch();
}

/**
 * Create goal from template
 */
function createGoalFromTemplate($pdo, $template_id, $target_data, $set_by) {
    $template = getGoalTemplateById($pdo, $template_id);
    if (!$template) {
        return false;
    }
    
    $deadline = date('Y-m-d', strtotime("+{$template['duration_days']} days"));
    
    $goal_data = [
        'goal_type' => $target_data['goal_type'],
        'target_user_id' => $target_data['target_user_id'] ?? null,
        'target_team_id' => $target_data['target_team_id'] ?? null,
        'target_department_id' => $target_data['target_department_id'] ?? null,
        'set_by' => $set_by,
        'title' => $template['name'],
        'description' => $template['description'],
        'target_hours' => $template['target_hours'],
        'target_entries' => $template['target_entries'],
        'deadline' => $deadline
    ];
    
    return createGoal($pdo, $goal_data);
}

/**
 * Get goal statistics for dashboard
 */
function getGoalStatistics($pdo, $user_id) {
    // First check if user is a partner
    $stmt = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $role = $stmt->fetchColumn();
    
    if ($role === 'partner') {
        // For partners viewing THEIR OWN goals (not goals they manage for others)
        // Use the SAME logic as regular users
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_goals,
                SUM(CASE WHEN g.status = 'active' THEN 1 ELSE 0 END) as active_goals,
                SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
                SUM(CASE WHEN g.status = 'overdue' THEN 1 ELSE 0 END) as overdue_goals,
                AVG(gp.progress_percentage) as avg_progress
            FROM cpd_goals g
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id AND gp.user_id = ?
            WHERE (
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
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    } else {
        // Original logic for non-partners
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_goals,
                SUM(CASE WHEN g.status = 'active' THEN 1 ELSE 0 END) as active_goals,
                SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
                SUM(CASE WHEN g.status = 'overdue' THEN 1 ELSE 0 END) as overdue_goals,
                AVG(gp.progress_percentage) as avg_progress
            FROM cpd_goals g
            LEFT JOIN goal_progress gp ON g.id = gp.goal_id AND gp.user_id = ?
            WHERE (
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
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    }
    
    return $stmt->fetch();
}

/**
 * Check if user can manage goal (is the one who set it)
 */
function canManageGoal($pdo, $user_id, $goal_id) {
    $stmt = $pdo->prepare("SELECT id FROM cpd_goals WHERE id = ? AND set_by = ?");
    $stmt->execute([$goal_id, $user_id]);
    return $stmt->fetch() !== false;
}

/**
 * Bulk update goal progress for all active goals
 */
function updateAllGoalProgress($pdo) {
    $stmt = $pdo->query("SELECT id FROM cpd_goals WHERE status IN ('active', 'overdue')");
    $goals = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $updated = 0;
    foreach ($goals as $goal_id) {
        if (updateGoalProgress($pdo, $goal_id)) {
            $updated++;
        }
    }
    
    return $updated;
}
?>