<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/organisation_functions.php';
require_once 'includes/system_admin_functions.php';

// Check authentication and admin role
checkAuth();
if (!isSuperAdmin()) {
    // Redirect regular admins to their tenant dashboard
    header('Location: admin_dashboard.php');
    exit();
}

$pageTitle = 'System Admin Dashboard - SaaS Metrics';
include 'includes/header.php';

// Get all metrics
$system_metrics = getSystemMetrics($pdo);
$revenue_metrics = calculateMRR($pdo);
$growth_metrics = getGrowthMetrics($pdo);
$feature_usage = getFeatureUsageStats($pdo);
$issues = getOrganisationsRequiringAttention($pdo);
$recent_activity = getRecentSystemActivity($pdo, 15);

// Get filter parameters
$status_filter = $_GET['status'] ?? null;
$plan_filter = $_GET['plan'] ?? null;

// Get organisations with metrics
$organisations = getOrganisationsWithMetrics($pdo, $status_filter, $plan_filter);

// Get top organisations
$top_by_users = getTopOrganisations($pdo, 'users', 5);
$top_by_cpd = getTopOrganisations($pdo, 'cpd_hours', 5);

// Calculate total issue count
$total_issues = count($issues['expiring_trials']) + 
                count($issues['near_capacity']) + 
                count($issues['over_capacity']) + 
                count($issues['suspended']) + 
                count($issues['no_activity']);
?>

<div class="container">
    <div class="admin-header">
        <h1>🚀 System Admin Dashboard</h1>
        <p style="color: #666; margin: 0.5rem 0 0 0;">SaaS Platform Metrics & Tenant Management</p>
        <?php renderAdminNav('system'); ?>
    </div>

    <!-- Critical Alerts -->
    <?php if ($total_issues > 0): ?>
    <div class="admin-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h2>⚠️ Attention Required (<?php echo $total_issues; ?> Issues)</h2>
        <div class="alerts-grid">
            <?php if (count($issues['expiring_trials']) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #dc3545;">
                <strong><?php echo count($issues['expiring_trials']); ?></strong> trials expiring within 7 days
                <a href="#expiring-trials" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($issues['over_capacity']) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #dc3545;">
                <strong><?php echo count($issues['over_capacity']); ?></strong> organisations over capacity
                <a href="#capacity-issues" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($issues['near_capacity']) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #ffc107;">
                <strong><?php echo count($issues['near_capacity']); ?></strong> organisations near capacity (90%+)
                <a href="#capacity-issues" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($issues['suspended']) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #6c757d;">
                <strong><?php echo count($issues['suspended']); ?></strong> suspended organisations
                <a href="#suspended-orgs" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
            
            <?php if (count($issues['no_activity']) > 0): ?>
            <div class="alert-card" style="border-left: 3px solid #17a2b8;">
                <strong><?php echo count($issues['no_activity']); ?></strong> organisations with no recent activity
                <a href="#inactive-orgs" class="btn btn-small">View</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Key Metrics -->
    <div class="metrics-section">
        <h2>💰 Revenue Metrics</h2>
        <div class="stats-grid">
            <div class="stat-card revenue-card">
                <h3>Monthly Recurring Revenue</h3>
                <p class="stat-number">$<?php echo number_format($revenue_metrics['mrr'], 2); ?></p>
                <small>MRR from active subscriptions</small>
            </div>
            <div class="stat-card revenue-card">
                <h3>Annual Recurring Revenue</h3>
                <p class="stat-number">$<?php echo number_format($revenue_metrics['arr'], 2); ?></p>
                <small>ARR (MRR × 12)</small>
            </div>
            <div class="stat-card">
                <h3>Active Subscriptions</h3>
                <p class="stat-number"><?php echo $system_metrics['active_organisations']; ?></p>
                <small>Paying customers</small>
            </div>
            <div class="stat-card">
                <h3>Trial Organisations</h3>
                <p class="stat-number"><?php echo $system_metrics['trial_organisations']; ?></p>
                <small>Potential conversions</small>
            </div>
        </div>
    </div>

    <!-- Growth Metrics -->
    <div class="metrics-section">
        <h2>📈 Growth Metrics</h2>
        <div class="stats-grid">
            <div class="stat-card growth-card">
                <h3>New Organisations</h3>
                <p class="stat-number"><?php echo $growth_metrics['new_orgs_this_month']; ?></p>
                <small>This month
                    <?php if ($growth_metrics['new_orgs_last_month'] > 0): ?>
                        (<?php echo $growth_metrics['new_orgs_this_month'] > $growth_metrics['new_orgs_last_month'] ? '▲' : '▼'; ?>
                        <?php echo abs($growth_metrics['new_orgs_this_month'] - $growth_metrics['new_orgs_last_month']); ?> vs last month)
                    <?php endif; ?>
                </small>
            </div>
            <div class="stat-card growth-card">
                <h3>New Users</h3>
                <p class="stat-number"><?php echo $growth_metrics['new_users_this_month']; ?></p>
                <small>This month</small>
            </div>
            <div class="stat-card success-card">
                <h3>Trial Conversion Rate</h3>
                <p class="stat-number"><?php echo $growth_metrics['conversion_rate']; ?>%</p>
                <small><?php echo $growth_metrics['converted_trials']; ?> converted trials</small>
            </div>
            <div class="stat-card <?php echo $growth_metrics['churn_rate'] > 5 ? 'warning-card' : 'success-card'; ?>">
                <h3>Churn Rate</h3>
                <p class="stat-number"><?php echo $growth_metrics['churn_rate']; ?>%</p>
                <small>This month</small>
            </div>
        </div>
    </div>

    <!-- System Overview -->
    <div class="metrics-section">
        <h2>🌐 System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Organisations</h3>
                <p class="stat-number"><?php echo $system_metrics['total_organisations']; ?></p>
                <small>All tenants</small>
            </div>
            <div class="stat-card">
                <h3>Total Users</h3>
                <p class="stat-number"><?php echo number_format($system_metrics['total_users']); ?></p>
                <small>Active user accounts</small>
            </div>
            <div class="stat-card">
                <h3>Total CPD Entries</h3>
                <p class="stat-number"><?php echo number_format($system_metrics['total_cpd_entries']); ?></p>
                <small>All-time entries</small>
            </div>
            <div class="stat-card">
                <h3>Total CPD Hours</h3>
                <p class="stat-number"><?php echo number_format($system_metrics['total_cpd_hours'], 0); ?></p>
                <small>Hours logged</small>
            </div>
        </div>
    </div>

    <!-- Feature Usage -->
    <div class="metrics-section">
        <h2>⚙️ Feature Adoption</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Departments</h3>
                <p class="stat-number"><?php echo $feature_usage['orgs_using_departments']; ?></p>
                <small>Orgs using departments</small>
            </div>
            <div class="stat-card">
                <h3>Teams</h3>
                <p class="stat-number"><?php echo $feature_usage['orgs_using_teams']; ?></p>
                <small>Orgs using teams</small>
            </div>
            <div class="stat-card">
                <h3>Avg CPD Entries/User</h3>
                <p class="stat-number"><?php echo $feature_usage['avg_entries_per_user']; ?></p>
                <small>Platform average</small>
            </div>
            <div class="stat-card">
                <h3>Avg CPD Hours/User</h3>
                <p class="stat-number"><?php echo $feature_usage['avg_hours_per_user']; ?></p>
                <small>Platform average</small>
            </div>
        </div>
    </div>

    <!-- Top Organisations -->
    <div class="two-column-grid">
        <div class="admin-section">
            <h2>🏆 Top Organisations by Users</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Plan</th>
                        <th>Users</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_by_users as $org): ?>
                    <tr>
                        <td>
                            <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>">
                                <?php echo htmlspecialchars($org['name']); ?>
                            </a>
                        </td>
                        <td><span class="plan-badge plan-<?php echo $org['subscription_plan']; ?>"><?php echo ucfirst($org['subscription_plan']); ?></span></td>
                        <td><strong><?php echo $org['metric_value']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-section">
            <h2>🏆 Top Organisations by CPD Hours</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Plan</th>
                        <th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_by_cpd as $org): ?>
                    <tr>
                        <td>
                            <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>">
                                <?php echo htmlspecialchars($org['name']); ?>
                            </a>
                        </td>
                        <td><span class="plan-badge plan-<?php echo $org['subscription_plan']; ?>"><?php echo ucfirst($org['subscription_plan']); ?></span></td>
                        <td><strong><?php echo round($org['metric_value'], 1); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Issue Details -->
    <?php if (count($issues['expiring_trials']) > 0): ?>
    <div id="expiring-trials" class="admin-section">
        <h2>⏰ Expiring Trials (<?php echo count($issues['expiring_trials']); ?>)</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Organisation</th>
                    <th>Plan</th>
                    <th>Users</th>
                    <th>Expires</th>
                    <th>Days Left</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues['expiring_trials'] as $org): ?>
                <tr class="<?php echo $org['days_remaining'] <= 3 ? 'urgent-row' : ''; ?>">
                    <td><?php echo htmlspecialchars($org['name']); ?></td>
                    <td><span class="plan-badge plan-<?php echo $org['subscription_plan']; ?>"><?php echo ucfirst($org['subscription_plan']); ?></span></td>
                    <td><?php echo $org['user_count'] ?? 0; ?> / <?php echo $org['max_users']; ?></td>
                    <td><?php echo date('M d, Y', strtotime($org['trial_ends_at'])); ?></td>
                    <td>
                        <span class="days-badge <?php echo $org['days_remaining'] <= 3 ? 'urgent' : 'warning'; ?>">
                            <?php echo $org['days_remaining']; ?> days
                        </span>
                    </td>
                    <td>
                        <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>" class="btn btn-small">Manage</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (count($issues['over_capacity']) > 0 || count($issues['near_capacity']) > 0): ?>
    <div id="capacity-issues" class="admin-section">
        <h2>📊 Capacity Issues</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Organisation</th>
                    <th>Plan</th>
                    <th>Users</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_merge($issues['over_capacity'], $issues['near_capacity']) as $org): ?>
                <tr class="<?php echo $org['usage_percent'] >= 100 ? 'urgent-row' : ''; ?>">
                    <td><?php echo htmlspecialchars($org['name']); ?></td>
                    <td><span class="plan-badge plan-<?php echo $org['subscription_plan']; ?>"><?php echo ucfirst($org['subscription_plan']); ?></span></td>
                    <td><?php echo $org['user_count']; ?> / <?php echo $org['max_users']; ?></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $org['usage_percent'] >= 100 ? 'full' : ($org['usage_percent'] >= 90 ? 'warning' : ''); ?>" 
                                 style="width: <?php echo min($org['usage_percent'], 100); ?>%"></div>
                        </div>
                    </td>
                    <td><?php echo round($org['usage_percent'], 1); ?>%</td>
                    <td>
                        <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>" class="btn btn-small">Increase Limit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- All Organisations Table -->
    <div class="admin-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2>All Organisations (<?php echo count($organisations); ?>)</h2>
            <div class="filter-controls">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="trial" <?php echo $status_filter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <select name="plan" onchange="this.form.submit()">
                        <option value="">All Plans</option>
                        <option value="basic" <?php echo $plan_filter === 'basic' ? 'selected' : ''; ?>>Basic</option>
                        <option value="professional" <?php echo $plan_filter === 'professional' ? 'selected' : ''; ?>>Professional</option>
                        <option value="enterprise" <?php echo $plan_filter === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                    </select>
                    <?php if ($status_filter || $plan_filter): ?>
                        <a href="system_admin_dashboard.php" class="btn btn-small btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Organisation</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Users</th>
                    <th>Departments</th>
                    <th>CPD Hours</th>
                    <th>Capacity</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organisations as $org): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($org['name']); ?></strong></td>
                    <td><span class="status-badge status-<?php echo $org['subscription_status']; ?>"><?php echo ucfirst($org['subscription_status']); ?></span></td>
                    <td><span class="plan-badge plan-<?php echo $org['subscription_plan']; ?>"><?php echo ucfirst($org['subscription_plan']); ?></span></td>
                    <td><?php echo $org['user_count']; ?> / <?php echo $org['max_users']; ?></td>
                    <td><?php echo $org['department_count']; ?></td>
                    <td><?php echo round($org['total_cpd_hours'], 1); ?></td>
                    <td>
                        <span class="capacity-indicator <?php echo $org['capacity_usage'] >= 100 ? 'full' : ($org['capacity_usage'] >= 90 ? 'warning' : 'good'); ?>">
                            <?php echo round($org['capacity_usage'], 0); ?>%
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($org['created_at'])); ?></td>
                    <td>
                        <a href="admin_edit_organisation.php?id=<?php echo $org['id']; ?>" class="btn btn-small">Manage</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Activity -->
    <div class="admin-section">
        <h2>📋 Recent System Activity</h2>
        <div class="activity-timeline">
            <?php foreach ($recent_activity as $activity): ?>
            <div class="activity-item">
                <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                    <?php if ($activity['activity_type'] === 'organisation_created'): ?>
                        🏢
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </div>
                <div class="activity-content">
                    <strong><?php echo htmlspecialchars($activity['org_name']); ?></strong>
                    <?php if ($activity['activity_type'] === 'organisation_created'): ?>
                        - New organisation created (<?php echo ucfirst($activity['details']); ?> plan)
                    <?php else: ?>
                        - New user registered: <?php echo htmlspecialchars($activity['user_name']); ?>
                    <?php endif; ?>
                    <small><?php echo date('M d, Y g:i A', strtotime($activity['activity_date'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    .metrics-section {
        margin-bottom: 2rem;
    }
    
    .metrics-section h2 {
        margin-bottom: 1rem;
        color: #2c3e50;
    }
    
    .revenue-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .revenue-card h3,
    .revenue-card small {
        color: white;
    }
    
    .growth-card {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    .growth-card h3,
    .growth-card small {
        color: white;
    }
    
    .success-card {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }
    
    .success-card h3,
    .success-card small {
        color: white;
    }
    
    .warning-card {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: white;
    }
    
    .warning-card h3,
    .warning-card small {
        color: white;
    }
    
    .plan-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .plan-basic {
        background: #e9ecef;
        color: #495057;
    }
    
    .plan-professional {
        background: #cfe2ff;
        color: #084298;
    }
    
    .plan-enterprise {
        background: #d1e7dd;
        color: #0f5132;
    }
    
    .days-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.875rem;
    }
    
    .days-badge.urgent {
        background: #f8d7da;
        color: #721c24;
    }
    
    .days-badge.warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .urgent-row {
        background: #fff3cd;
    }
    
    .progress-bar {
        width: 100%;
        height: 20px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .progress-fill {
        background: #28a745;
    }
    
    .progress-fill.warning {
        background: #ffc107;
    }
    
    .progress-fill.full {
        background: #dc3545;
    }
    
    .capacity-indicator {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.875rem;
    }
    
    .capacity-indicator.good {
        background: #d4edda;
        color: #155724;
    }
    
    .capacity-indicator.warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .capacity-indicator.full {
        background: #f8d7da;
        color: #721c24;
    }
    
    .two-column-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .alerts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .alert-card {
        background: white;
        padding: 1rem;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .activity-timeline {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .activity-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .activity-icon {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 50%;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-content small {
        display: block;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    @media (max-width: 992px) {
        .two-column-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
