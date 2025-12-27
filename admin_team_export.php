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
    header('Location: admin_manage_teams.php');
    exit();
}

$team_id = intval($_GET['id']);
$team = getTeamById($pdo, $team_id);

if (!$team) {
    header('Location: admin_manage_teams.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$category = $_GET['category'] ?? 'all';

// Build query for team CPD entries
$query = "
    SELECT ce.*, u.username
    FROM cpd_entries ce
    JOIN user_teams ut ON ce.user_id = ut.user_id
    JOIN users u ON ce.user_id = u.id
    WHERE ut.team_id = ?
";

$params = [$team_id];

if ($start_date) {
    $query .= " AND ce.date_completed >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND ce.date_completed <= ?";
    $params[] = $end_date;
}

if ($category && $category !== 'all') {
    $query .= " AND ce.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY ce.date_completed DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$team_entries = $stmt->fetchAll();

// Calculate total hours
$total_hours = 0;
foreach ($team_entries as $entry) {
    $total_hours += $entry['hours'];
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=team_' . $team_id . '_cpd_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility in Excel
fputs($output, "\xEF\xBB\xBF");

// Write headers
fputcsv($output, [
    'Date Completed',
    'Username',
    'Title', 
    'Description',
    'Category',
    'Hours',
    'Date Logged'
]);

// Write data rows
foreach ($team_entries as $entry) {
    fputcsv($output, [
        $entry['date_completed'],
        htmlspecialchars($entry['username']),
        htmlspecialchars($entry['title']),
        htmlspecialchars($entry['description']),
        htmlspecialchars($entry['category']),
        $entry['hours'],
        $entry['created_at']
    ]);
}

// Add summary rows
fputcsv($output, []); // Empty row
fputcsv($output, ['', '', '', '', 'TOTAL ENTRIES:', count($team_entries), '']);
fputcsv($output, ['', '', '', '', 'TOTAL HOURS:', $total_hours, '']);
fputcsv($output, ['', '', '', '', 'AVG HOURS PER ENTRY:', count($team_entries) > 0 ? round($total_hours / count($team_entries), 2) : 0, '']);

fclose($output);
exit();
?>
[file content end]