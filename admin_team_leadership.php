<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_manage_teams.php');
    exit();
}

$team_id = intval($_GET['id']);
$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: admin_manage_teams.php');
    exit();
}

$pageTitle = 'Manage Team Leadership: ' . $team['name'];
include 'includes/header.php';

// Handle adding/removing managers and partners
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_manager'])) {
        $manager_id = intval($_POST['manager_id']);
        if (assignManagerToTeam($pdo, $manager_id, $team_id)) {
            $success_message = "Manager assigned to team successfully!";
        } else {
            $error_message = "Manager is already assigned to this team.";
        }
    }
    
    if (isset($_POST['remove_manager'])) {
        $manager_id = intval($_POST['manager_id']);
        if (removeManagerFromTeam($pdo, $manager_id, $team_id)) {
            $success_message = "Manager removed from team successfully!";
        } else {
            $error_message = "Failed to remove manager from team.";
        }
    }
    
    if (isset($_POST['add_partner'])) {
        $partner_id = intval($_POST['partner_id']);
        if (assignPartnerToTeam($pdo, $partner_id, $team_id)) {
            $success_message = "Partner assigned to team successfully!";
        } else {
            $error_message = "Partner is already assigned to this team.";
        }
    }
    
    if (isset($_POST['remove_partner'])) {
        $partner_id = intval($_POST['partner_id']);
        if (removePartnerFromTeam($pdo, $partner_id, $team_id)) {
            $success_message = "Partner removed from team successfully!";
        } else {
            $error_message = "Failed to remove partner from team.";
        }
    }
}

// Get current managers and partners
$team_managers = getTeamManagers($pdo, $team_id);
$team_partners = getTeamPartners($pdo, $team_id);

// Get available managers and partners
$available_managers = getManagersNotInTeam($pdo, $team_id);
$available_partners = getPartnersNotInTeam($pdo, $team_id);
?>

<div class="container">
    <div class="admin-header">
        <h1>Manage Leadership: <?php echo htmlspecialchars($team['name']); ?></h1>
        <?php renderTeamNav($team_id, ''); ?>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="leadership-grid">
        <!-- Managers Section -->
        <div class="leadership-section">
            <h2>Team Managers</h2>
            
            <?php if (count($available_managers) > 0): ?>
            <div class="add-leadership-form">
                <h3>Assign Manager</h3>
                <form method="POST">
                    <div class="form-group">
                        <select name="manager_id" required>
                            <option value="">-- Select a manager --</option>
                            <?php foreach ($available_managers as $manager): ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['username']); ?> (<?php echo htmlspecialchars($manager['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_manager" class="btn">Assign Manager</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (count($team_managers) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Email</th>
                            <th>Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_managers as $manager): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($manager['username']); ?></td>
                            <td><?php echo htmlspecialchars($manager['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($manager['assigned_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?php echo htmlspecialchars($manager['username']); ?> as manager?');">
                                    <input type="hidden" name="manager_id" value="<?php echo $manager['id']; ?>">
                                    <button type="submit" name="remove_manager" class="btn btn-small btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No managers assigned to this team.</p>
            <?php endif; ?>
        </div>

        <!-- Partners Section -->
        <div class="leadership-section">
            <h2>Team Partners</h2>
            
            <?php if (count($available_partners) > 0): ?>
            <div class="add-leadership-form">
                <h3>Assign Partner</h3>
                <form method="POST">
                    <div class="form-group">
                        <select name="partner_id" required>
                            <option value="">-- Select a partner --</option>
                            <?php foreach ($available_partners as $partner): ?>
                                <option value="<?php echo $partner['id']; ?>">
                                    <?php echo htmlspecialchars($partner['username']); ?> (<?php echo htmlspecialchars($partner['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_partner" class="btn">Assign Partner</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (count($team_partners) > 0): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Partner</th>
                            <th>Email</th>
                            <th>Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team_partners as $partner): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($partner['username']); ?></td>
                            <td><?php echo htmlspecialchars($partner['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($partner['assigned_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?php echo htmlspecialchars($partner['username']); ?> as partner?');">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <button type="submit" name="remove_partner" class="btn btn-small btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No partners assigned to this team.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .leadership-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    
    .leadership-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .leadership-section h2 {
        margin-top: 0;
        color: #2c3e50;
        border-bottom: 2px solid #f8f9fa;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .add-leadership-form {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
    }
    
    .add-leadership-form h3 {
        margin-top: 0;
        margin-bottom: 1rem;
        font-size: 1rem;
        color: #666;
    }
    
    .add-leadership-form select {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 4px;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    @media (max-width: 992px) {
        .leadership-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>
