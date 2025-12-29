<?php
// System Admin functions for SaaS provider internal team

/**
 * Get system-wide metrics for SaaS dashboard
 */
function getSystemMetrics($pdo) {
    // Total organisations by status
    $stmt = $pdo->query("
        SELECT 
            subscription_status,
            COUNT(*) as count
        FROM organisations
        GROUP BY subscription_status
    ");
    $status_breakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total organisations by plan
    $stmt = $pdo->query("
        SELECT 
            subscription_plan,
            COUNT(*) as count
        FROM organisations
        GROUP BY subscription_plan
    ");
    $plan_breakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total users across all organisations
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE archived = 0
    ");
    $total_users = $stmt->fetchColumn();
    
    // Total CPD entries
    $stmt = $pdo->query("SELECT COUNT(*) FROM cpd_entries");
    $total_cpd_entries = $stmt->fetchColumn();
    
    // Total CPD hours
    $stmt = $pdo->query("SELECT COALESCE(SUM(hours), 0) FROM cpd_entries");
    $total_cpd_hours = $stmt->fetchColumn();
    
    // Average users per organisation
    $stmt = $pdo->query("
        SELECT AVG(user_count) 
        FROM (
            SELECT COUNT(*) as user_count 
            FROM users 
            WHERE archived = 0 
            GROUP BY organisation_id
        ) as org_users
    ");
    $avg_users_per_org = $stmt->fetchColumn();
    
    return [
        'total_organisations' => array_sum($status_breakdown),
        'active_organisations' => $status_breakdown['active'] ?? 0,
        'trial_organisations' => $status_breakdown['trial'] ?? 0,
        'suspended_organisations' => $status_breakdown['suspended'] ?? 0,
        'cancelled_organisations' => $status_breakdown['cancelled'] ?? 0,
        'status_breakdown' => $status_breakdown,
        'plan_breakdown' => $plan_breakdown,
        'total_users' => $total_users,
        'total_cpd_entries' => $total_cpd_entries,
        'total_cpd_hours' => round($total_cpd_hours, 2),
        'avg_users_per_org' => round($avg_users_per_org, 2)
    ];
}

/**
 * Calculate Monthly Recurring Revenue (MRR)
 * Note: You'll need to update these prices based on your actual pricing
 */
function calculateMRR($pdo) {
    // Define pricing (monthly)
    $pricing = [
        'basic' => 29,
        'professional' => 99,
        'enterprise' => 299
    ];
    
    $stmt = $pdo->query("
        SELECT 
            subscription_plan,
            COUNT(*) as count
        FROM organisations
        WHERE subscription_status = 'active'
        GROUP BY subscription_plan
    ");
    $active_plans = $stmt->fetchAll();
    
    $mrr = 0;
    foreach ($active_plans as $plan) {
        $plan_name = $plan['subscription_plan'];
        $count = $plan['count'];
        $mrr += ($pricing[$plan_name] ?? 0) * $count;
    }
    
    return [
        'mrr' => $mrr,
        'arr' => $mrr * 12,
        'pricing' => $pricing
    ];
}

/**
 * Get growth metrics
 */
function getGrowthMetrics($pdo) {
    // New organisations this month
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $new_orgs_this_month = $stmt->fetchColumn();
    
    // New organisations last month
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
    ");
    $new_orgs_last_month = $stmt->fetchColumn();
    
    // New users this month
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $new_users_this_month = $stmt->fetchColumn();
    
    // Trial conversions (organisations that moved from trial to active)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE subscription_status = 'active' 
        AND trial_ends_at IS NOT NULL
    ");
    $converted_trials = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE subscription_status = 'trial'
    ");
    $total_trials = $stmt->fetchColumn();
    
    $conversion_rate = ($total_trials + $converted_trials) > 0 
        ? ($converted_trials / ($total_trials + $converted_trials)) * 100 
        : 0;
    
    // Churn rate (organisations that went from active to cancelled this month)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE subscription_status = 'cancelled'
        AND MONTH(updated_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(updated_at) = YEAR(CURRENT_DATE())
    ");
    $churned_this_month = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM organisations 
        WHERE subscription_status = 'active'
    ");
    $active_orgs = $stmt->fetchColumn();
    
    $churn_rate = $active_orgs > 0 ? ($churned_this_month / $active_orgs) * 100 : 0;
    
    return [
        'new_orgs_this_month' => $new_orgs_this_month,
        'new_orgs_last_month' => $new_orgs_last_month,
        'new_users_this_month' => $new_users_this_month,
        'conversion_rate' => round($conversion_rate, 2),
        'churn_rate' => round($churn_rate, 2),
        'converted_trials' => $converted_trials
    ];
}

/**
 * Get organisations with detailed metrics
 */
function getOrganisationsWithMetrics($pdo, $status_filter = null, $plan_filter = null) {
    $where_clauses = [];
    $params = [];
    
    if ($status_filter) {
        $where_clauses[] = "o.subscription_status = ?";
        $params[] = $status_filter;
    }
    
    if ($plan_filter) {
        $where_clauses[] = "o.subscription_plan = ?";
        $params[] = $plan_filter;
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT d.id) as department_count,
            COUNT(DISTINCT t.id) as team_count,
            COUNT(DISTINCT ce.id) as cpd_entry_count,
            COALESCE(SUM(ce.hours), 0) as total_cpd_hours,
            DATEDIFF(o.trial_ends_at, NOW()) as trial_days_remaining,
            ROUND((COUNT(DISTINCT u.id) / o.max_users) * 100, 2) as capacity_usage
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        LEFT JOIN departments d ON o.id = d.organisation_id
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN cpd_entries ce ON ut.user_id = ce.user_id
        $where_sql
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get recent activity across all organisations
 */
function getRecentSystemActivity($pdo, $limit = 20) {
    // Sanitize limit to prevent SQL injection
    $limit = intval($limit);
    
    $stmt = $pdo->query("
        SELECT 
            'organisation_created' as activity_type,
            o.name as org_name,
            o.created_at as activity_date,
            NULL as user_name,
            o.subscription_plan as details
        FROM organisations o
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'user_registered' as activity_type,
            o.name as org_name,
            u.created_at as activity_date,
            u.username as user_name,
            NULL as details
        FROM users u
        JOIN organisations o ON u.organisation_id = o.id
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY activity_date DESC
        LIMIT $limit
    ");
    
    return $stmt->fetchAll();
}

/**
 * Get organisations with issues requiring attention
 */
function getOrganisationsRequiringAttention($pdo) {
    $issues = [
        'expiring_trials' => [],
        'near_capacity' => [],
        'over_capacity' => [],
        'suspended' => [],
        'no_activity' => []
    ];
    
    // Expiring trials (within 7 days)
    $stmt = $pdo->query("
        SELECT *, DATEDIFF(trial_ends_at, NOW()) as days_remaining
        FROM organisations
        WHERE subscription_status = 'trial'
        AND trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ORDER BY trial_ends_at
    ");
    $issues['expiring_trials'] = $stmt->fetchAll();
    
    // Near capacity (>= 90%)
    $stmt = $pdo->query("
        SELECT o.*, 
               COUNT(u.id) as user_count,
               ROUND((COUNT(u.id) / o.max_users) * 100, 2) as usage_percent
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        WHERE o.subscription_status IN ('trial', 'active')
        GROUP BY o.id
        HAVING usage_percent >= 90 AND usage_percent < 100
        ORDER BY usage_percent DESC
    ");
    $issues['near_capacity'] = $stmt->fetchAll();
    
    // Over capacity
    $stmt = $pdo->query("
        SELECT o.*, 
               COUNT(u.id) as user_count,
               ROUND((COUNT(u.id) / o.max_users) * 100, 2) as usage_percent
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        WHERE o.subscription_status IN ('trial', 'active')
        GROUP BY o.id
        HAVING user_count >= o.max_users
        ORDER BY usage_percent DESC
    ");
    $issues['over_capacity'] = $stmt->fetchAll();
    
    // Suspended organisations
    $stmt = $pdo->query("
        SELECT *
        FROM organisations
        WHERE subscription_status = 'suspended'
        ORDER BY updated_at DESC
    ");
    $issues['suspended'] = $stmt->fetchAll();
    
    // No activity (no CPD entries in last 30 days)
    $stmt = $pdo->query("
        SELECT o.*, COUNT(u.id) as user_count
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        WHERE o.subscription_status IN ('trial', 'active')
        AND o.id NOT IN (
            SELECT DISTINCT ut.user_id
            FROM cpd_entries ce
            JOIN user_teams ut ON ce.user_id = ut.user_id
            JOIN teams t ON ut.team_id = t.id
            JOIN departments d ON t.department_id = d.id
            WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND d.organisation_id = o.id
        )
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $issues['no_activity'] = $stmt->fetchAll();
    
    return $issues;
}

/**
 * Get feature usage statistics
 */
function getFeatureUsageStats($pdo) {
    // Organisations using departments
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT organisation_id)
        FROM departments
    ");
    $orgs_using_departments = $stmt->fetchColumn();
    
    // Organisations using teams
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT d.organisation_id)
        FROM teams t
        JOIN departments d ON t.department_id = d.id
    ");
    $orgs_using_teams = $stmt->fetchColumn();
    
    // Average CPD entries per user
    $stmt = $pdo->query("
        SELECT AVG(entry_count)
        FROM (
            SELECT COUNT(*) as entry_count
            FROM cpd_entries
            GROUP BY user_id
        ) as user_entries
    ");
    $avg_entries_per_user = $stmt->fetchColumn();
    
    // Average CPD hours per user
    $stmt = $pdo->query("
        SELECT AVG(total_hours)
        FROM (
            SELECT SUM(hours) as total_hours
            FROM cpd_entries
            GROUP BY user_id
        ) as user_hours
    ");
    $avg_hours_per_user = $stmt->fetchColumn();
    
    return [
        'orgs_using_departments' => $orgs_using_departments,
        'orgs_using_teams' => $orgs_using_teams,
        'avg_entries_per_user' => round($avg_entries_per_user, 2),
        'avg_hours_per_user' => round($avg_hours_per_user, 2)
    ];
}

/**
 * Get top organisations by various metrics
 */
function getTopOrganisations($pdo, $metric = 'users', $limit = 10) {
    // Sanitize limit to prevent SQL injection
    $limit = intval($limit);
    
    $metrics = [
        'users' => "COUNT(DISTINCT u.id) as metric_value",
        'cpd_hours' => "COALESCE(SUM(ce.hours), 0) as metric_value",
        'cpd_entries' => "COUNT(DISTINCT ce.id) as metric_value",
        'departments' => "COUNT(DISTINCT d.id) as metric_value",
        'teams' => "COUNT(DISTINCT t.id) as metric_value"
    ];
    
    $metric_sql = $metrics[$metric] ?? $metrics['users'];
    
    $stmt = $pdo->query("
        SELECT 
            o.id,
            o.name,
            o.subscription_plan,
            o.subscription_status,
            $metric_sql
        FROM organisations o
        LEFT JOIN users u ON o.id = u.organisation_id AND u.archived = 0
        LEFT JOIN departments d ON o.id = d.organisation_id
        LEFT JOIN teams t ON d.id = t.department_id
        LEFT JOIN user_teams ut ON t.id = ut.team_id
        LEFT JOIN cpd_entries ce ON ut.user_id = ce.user_id
        WHERE o.subscription_status IN ('trial', 'active')
        GROUP BY o.id
        ORDER BY metric_value DESC
        LIMIT $limit
    ");
    
    return $stmt->fetchAll();
}
?>