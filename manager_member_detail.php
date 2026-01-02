<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/team_functions.php';
require_once 'includes/manager_partner_functions.php';

// Check authentication and manager role
checkAuth();
if (!isManager() && !isPartner()) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['user_id'])) {
    header('Location: manager_dashboard.php');
    exit();
}

$team_id = intval($_GET['id']);
$user_id = intval($_GET['user_id']);

// Check if manager/partner has access to this team
if (isManager() && !isManagerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: manager_dashboard.php');
    exit();
}

if (isPartner() && !isPartnerOfTeam($pdo, $_SESSION['user_id'], $team_id)) {
    header('Location: partner_dashboard.php');
    exit();
}

$team = getTeamById($pdo, $team_id);
$member = getUserById($pdo, $user_id);

if (!$team || !$member) {
    header('Location: manager_dashboard.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get member's CPD entries
$cpd_entries = getTeamMemberCPDEntries($pdo, $user_id, $team_id, $start_date, $end_date);

// Calculate totals
$total_hours = 0;
foreach ($cpd_entries as $entry) {
    $total_hours += $entry['hours'];
}

$pageTitle = 'CPD Details: ' . $member['username'];
include 'includes/header.php';
?>

<div class="container">
    <div class="admin-header">
        <h1>CPD Details: <?php echo htmlspecialchars($member['username']); ?></h1>
        <?php if (isManager()): ?>
            <?php renderManagerNav($team_id, 'members'); ?>
        <?php else: ?>
            <?php renderPartnerNav(''); ?>
        <?php endif; ?>
    </div>

    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <h3>Team</h3>
            <p class="stat-number" style="font-size: 1.2rem;"><?php echo htmlspecialchars($team['name']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Entries</h3>
            <p class="stat-number"><?php echo count($cpd_entries); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Hours</h3>
            <p class="stat-number"><?php echo round($total_hours, 1); ?></p>
        </div>
        <div class="stat-card">
            <h3>Member Since</h3>
            <p class="stat-number" style="font-size: 1rem;"><?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
        </div>
    </div>

    <div class="admin-section">
        <h2>CPD Entries</h2>
        
        <?php if (count($cpd_entries) > 0): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Hours</th>
                        <th>Description</th>
                        <th>Supporting Document</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cpd_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['date_completed']); ?></td>
                        <td><?php echo htmlspecialchars($entry['title']); ?></td>
                        <td><?php echo htmlspecialchars($entry['category']); ?></td>
                        <td><?php echo htmlspecialchars($entry['hours']); ?> hours</td>
                        <td style="max-width: 300px;">
                            <?php 
                            $desc = htmlspecialchars($entry['description']);
                            echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($entry['supporting_docs'])): ?>
                                <?php
                                // Determine file type for icon
                                $file_ext = strtolower(pathinfo($entry['supporting_docs'], PATHINFO_EXTENSION));
                                $icon_class = 'fa-file';
                                $file_types = [
                                    'pdf' => 'fa-file-pdf',
                                    'jpg' => 'fa-file-image',
                                    'jpeg' => 'fa-file-image',
                                    'png' => 'fa-file-image',
                                    'doc' => 'fa-file-word',
                                    'docx' => 'fa-file-word',
                                    'csv' => 'fa-file-csv'
                                ];
                                if (isset($file_types[$file_ext])) {
                                    $icon_class = $file_types[$file_ext];
                                }
                                ?>
                                <a href="download.php?file=<?php echo urlencode($entry['supporting_docs']); ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   target="_blank" 
                                   title="View supporting document">
                                    <i class="fas <?php echo $icon_class; ?>"></i> View Document
                                </a>
                            <?php else: ?>
                                <span class="text-muted" title="No supporting document attached">
                                    <i class="fas fa-times-circle"></i> No Document
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No CPD entries found for this team member<?php echo ($start_date || $end_date) ? ' in the selected date range' : ''; ?>.</p>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="<?php echo isManager() ? 'manager_team_view.php' : 'partner_team_view.php'; ?>?id=<?php echo $team_id; ?><?php echo $start_date ? '&start_date=' . $start_date : ''; ?><?php echo $end_date ? '&end_date=' . $end_date : ''; ?>" 
           class="btn btn-secondary">Back to Team Overview</a>
    </div>
</div>

<style>
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.text-muted {
    color: #6c757d !important;
}

.fa-times-circle {
    color: #dc3545;
}

.fa-file-pdf {
    color: #dc3545;
}

.fa-file-image {
    color: #28a745;
}

.fa-file-word {
    color: #007bff;
}

.fa-file-csv {
    color: #17a2b8;
}
</style>

<?php include 'includes/footer.php'; ?>