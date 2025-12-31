<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/department_functions.php';

// Check authentication and partner role
checkAuth();
if (!isPartner()) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = 'Partner Dashboard';
include 'includes/header.php';

// Get teams managed by this partner
$managed_teams = getPartnerTeams($pdo, $_SESSION['user_id']);

// Get departments managed by this partner
$managed_departments = getPartnerDepartments($pdo, $_SESSION['user_id']);

// Calculate overall statistics for teams
$total_teams = count($managed_teams);
$total_team_members = 0;
$total_managers = 0;
$total_cpd_entries = 0;
$total_cpd_hours = 0;

foreach ($managed_teams as $team) {
    $total_team_members += $team['member_count'];
    $total_managers += $team['manager_count'];
    $stats = getTeamCPDStats($pdo, $team['id']);
    $total_cpd_entries += $stats['total_entries'] ?? 0;
    $total_cpd_hours += $stats['total_hours'] ?? 0;
}

// Calculate overall statistics for departments
$total_departments = count($managed_departments);
$total_dept_members = 0;
$total_dept_teams = 0;

foreach ($managed_departments as $dept) {
    $total_dept_members += $dept['member_count'];
    $total_dept_teams += $dept['team_count'];
}
?>

<div class="container">
    <div class="admin-header">
        <h1>Partner Dashboard</h1>
        <?php renderPartnerNav('dashboard'); ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Departments</h3>
            <p class="stat-number"><?php echo $total_departments; ?></p>
        </div>
        <div class="stat-card">
            <h3>Teams Managed</h3>
            <p class="stat-number"><?php echo $total_teams; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Members</h3>
            <p class="stat-number"><?php echo $total_team_members + $total_dept_members; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total CPD Hours</h3>
            <p class="stat-number"><?php echo round($total_cpd_hours, 1); ?></p>
        </div>
    </div>

    <!-- My Departments Section -->
    <?php if (count($managed_departments) > 0): ?>
    <div class="admin-section">
        <h2>🏢 My Departments (<?php echo count($managed_departments); ?>)</h2>
        <p style="color: #666; margin-bottom: 1.5rem;">Departments you oversee as a partner</p>
        
        <div class="departments-grid">
            <?php foreach ($managed_departments as $dept): 
                $dept_stats = getDepartmentCPDStats($pdo, $dept['id']);
            ?>
                <div class="department-card">
                    <div class="department-card-header">
                        <h3>🏢 <?php echo htmlspecialchars($dept['name']); ?></h3>
                        <span class="org-badge"><?php echo htmlspecialchars($dept['organisation_name']); ?></span>
                    </div>
                    
                    <?php if (!empty($dept['description'])): ?>
                        <p class="department-description"><?php echo htmlspecialchars($dept['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="department-stats-mini">
                        <div class="stat-mini">
                            <strong><?php echo $dept['team_count']; ?></strong>
                            <span>Teams</span>
                        </div>
                        <div class="stat-mini">
                            <strong><?php echo $dept['member_count']; ?></strong>
                            <span>Members</span>
                        </div>
                        <div class="stat-mini">
                            <strong><?php echo $dept_stats['total_entries'] ?? 0; ?></strong>
                            <span>Entries</span>
                        </div>
                        <div class="stat-mini">
                            <strong><?php echo round($dept_stats['total_hours'] ?? 0, 1); ?></strong>
                            <span>Hours</span>
                        </div>
                    </div>
                    
                    <div class="department-actions">
                        <a href="partner_department_view.php?id=<?php echo $dept['id']; ?>" class="btn btn-small btn-block">
                            📊 View Department
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Teams Section -->
    <div class="admin-section">
        <h2>👥 My Teams (<?php echo count($managed_teams); ?>)</h2>
        <p style="color: #666; margin-bottom: 1.5rem;">Teams you directly manage as a partner</p>
        
        <?php if (count($managed_teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($managed_teams as $team): 
                    $team_stats = getTeamCPDStats($pdo, $team['id']);
                    $team_managers = getTeamManagers($pdo, $team['id']);
                ?>
                    <div class="team-card">
                        <div class="team-card-header">
                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            <span class="team-members"><?php echo $team['member_count']; ?> members</span>
                        </div>
                        
                        <?php if (!empty($team['department_name'])): ?>
                            <p class="team-department">
                                <strong>Department:</strong> <?php echo htmlspecialchars($team['department_name']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($team['description'])): ?>
                            <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="team-managers-list">
                            <strong>Managers:</strong>
                            <?php if (count($team_managers) > 0): ?>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <?php foreach ($team_managers as $manager): ?>
                                        <li><?php echo htmlspecialchars($manager['username']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span style="color: #666; font-style: italic;">No managers assigned</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="team-stats-mini">
                            <div class="stat-mini">
                                <strong><?php echo $team_stats['total_entries'] ?? 0; ?></strong>
                                <span>CPD Entries</span>
                            </div>
                            <div class="stat-mini">
                                <strong><?php echo round($team_stats['total_hours'] ?? 0, 1); ?></strong>
                                <span>Total Hours</span>
                            </div>
                        </div>
                        
                        <div class="team-actions">
                            <a href="partner_team_view.php?id=<?php echo $team['id']; ?>" class="btn btn-small">View Team</a>
                            <a href="partner_team_members.php?id=<?php echo $team['id']; ?>" class="btn btn-small btn-secondary">View Members</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>You are not currently assigned to manage any teams directly.</p>
        <?php endif; ?>
    </div>
    
    <?php if (count($managed_departments) == 0 && count($managed_teams) == 0): ?>
    <div class="empty-state" style="text-align: center; padding: 3rem; background: #f8f9fa; border-radius: 8px; margin-top: 2rem;">
        <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
        <h2>No Assignments Yet</h2>
        <p style="color: #666; margin-bottom: 2rem;">You haven't been assigned to manage any departments or teams yet. Please contact your administrator.</p>
        <a href="dashboard.php" class="btn">Go to My CPD Dashboard</a>
    </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" class="btn btn-secondary">Back to My CPD</a>
    </div>
</div>

<style>
    /* Department cards styling */
    .departments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .department-card {
        background: #fff;
        border: 2px solid #e1e5e9;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border-left: 4px solid #f57c00;
    }
    
    .department-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    
    .department-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 1rem;
    }
    
    .department-card-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.15rem;
        flex: 1;
    }
    
    .org-badge {
        padding: 0.35rem 0.75rem;
        background: #fff3e0;
        color: #f57c00;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .department-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .department-stats-mini {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.75rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .department-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    /* Update team stats to match */
    .team-stats-mini {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .stat-mini {
        text-align: center;
    }
    
    .stat-mini strong {
        display: block;
        font-size: 1.5rem;
        color: #007cba;
        margin-bottom: 0.25rem;
    }
    
    .stat-mini span {
        display: block;
        font-size: 0.875rem;
        color: #666;
    }
    
    .team-managers-list {
        padding: 0.75rem;
        background: #e9f7fe;
        border-radius: 4px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }
    
    .team-managers-list strong {
        color: #007cba;
    }
    
    .team-managers-list ul {
        list-style-type: disc;
    }
    
    .team-managers-list li {
        color: #333;
    }
    
    .team-department {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
        padding: 0.5rem;
        background: #fff3e0;
        border-radius: 4px;
    }
    
    @media (max-width: 768px) {
        .departments-grid,
        .teams-grid {
            grid-template-columns: 1fr;
        }
        
        .department-stats-mini {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .department-actions {
            flex-direction: column;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
