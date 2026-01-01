<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/admin_functions.php';
require_once 'includes/team_functions.php';

// Check authentication and admin role
checkAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_manage_users.php');
    exit();
}

$user_id = intval($_GET['id']);
$user = getUserById($pdo, $user_id);

if (!$user) {
    header('Location: admin_manage_users.php');
    exit();
}

// Check if admin can access this user's organisation
if ($user['organisation_id']) {
    require_once 'includes/admin_functions.php';
    if (!canAdminAccessOrganisation($pdo, $_SESSION['user_id'], $user['organisation_id'])) {
        header('Location: admin_manage_users.php');
        exit();
    }
}

// Get user's CPD entries
$stmt = $pdo->prepare("SELECT * FROM cpd_entries WHERE user_id = ? ORDER BY date_completed DESC");
$stmt->execute([$user_id]);
$cpd_entries = $stmt->fetchAll();

// Get user's teams
$user_teams = getUserTeams($pdo, $user_id);

$pageTitle = 'User Details: ' . $user['username'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>User Details: <?php echo htmlspecialchars($user['username']); ?></h1>
        <?php renderAdminNav('users'); ?>
    </div>

    <div class="user-details-grid">
        <div class="detail-card">
            <h3>Basic Information</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Role:</strong> <?php echo ucfirst($user['role_name']); ?></p>
            <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
        </div>

        <div class="detail-card">
            <h3>CPD Summary</h3>
            <p><strong>Total Entries:</strong> <?php echo count($cpd_entries); ?></p>
            <?php
            $total_hours = 0;
            foreach ($cpd_entries as $entry) {
                $total_hours += $entry['hours'];
            }
            ?>
            <p><strong>Total Hours:</strong> <?php echo $total_hours; ?></p>
        </div>

        <div class="detail-card">
            <h3>Team Memberships</h3>
            <?php if (count($user_teams) > 0): ?>
                <ul class="team-list">
                    <?php foreach ($user_teams as $team): ?>
                        <li>
                            <a href="admin_edit_team.php?id=<?php echo $team['id']; ?>">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </a>
                            <?php if (!empty($team['description'])): ?>
                                <small> - <?php echo htmlspecialchars($team['description']); ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>This user is not a member of any teams.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($cpd_entries) > 0): ?>
    <div class="admin-section">
        <h2>CPD Entries</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Hours</th>
                    <th>Document</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cpd_entries as $entry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                    <td><?php echo htmlspecialchars($entry['title']); ?></td>
                    <td><?php echo htmlspecialchars($entry['category']); ?></td>
                    <td><?php echo htmlspecialchars($entry['hours']); ?></td>
                    <td>
                        <?php if ($entry['supporting_docs']): ?>
                            <a href="download.php?file=<?php echo urlencode($entry['supporting_docs']); ?>" target="_blank">View</a>
                        <?php else: ?>
                            None
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="form-actions">
        <a href="admin_manage_users.php" class="btn btn-secondary">Back to User List</a>
    </div>
</div>

<style>
    .user-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .team-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .team-list li {
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .team-list li a {
        font-weight: bold;
        color: #007cba;
    }
    
    .team-list li small {
        color: #666;
    }
</style>

<?php include 'includes/footer.php'; ?>