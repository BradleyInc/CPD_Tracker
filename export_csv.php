<?php
require_once 'includes/database.php';

if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate and sanitize filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$category = isset($_GET['category']) ? $_GET['category'] : null;

// Validate date formats
if ($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    die("Invalid start date format");
}

if ($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    die("Invalid end date format");
}

// Validate category against allowed values
$allowed_categories = ['Training', 'Conference', 'Reading', 'Online Course', 'Other', 'all'];
if ($category && !in_array($category, $allowed_categories)) {
    die("Invalid category");
}

// Build query with filters using prepared statements
$query = "SELECT * FROM cpd_entries WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if ($start_date) {
    $query .= " AND date_completed >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND date_completed <= ?";
    $params[] = $end_date;
}

if ($category && $category !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category;
}

$query .= " ORDER BY date_completed DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Calculate total hours
$total_hours = 0;
foreach ($entries as $entry) {
    $total_hours += $entry['hours'];
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=cpd_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 compatibility in Excel
fputs($output, "\xEF\xBB\xBF");

// Write headers
fputcsv($output, [
    'Date Completed',
    'Title', 
    'Description',
    'Category',
    'Hours',
    'Supporting Document',
    'Date Logged'
]);

// Write data rows
foreach ($entries as $entry) {
    fputcsv($output, [
        $entry['date_completed'],
        htmlspecialchars($entry['title']),
        htmlspecialchars($entry['description']),
        htmlspecialchars($entry['category']),
        $entry['hours'],
        $entry['supporting_docs'] ? htmlspecialchars(basename($entry['supporting_docs'])) : 'None',
        $entry['created_at']
    ]);
}

// Add summary row
fputcsv($output, []); // Empty row
fputcsv($output, ['', '', '', 'TOTAL HOURS:', $total_hours, '', '']);

fclose($output);
exit();
?>