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

// Generate template CSV
function generateCSVTemplate() {
    $template_data = [
        ['title', 'description', 'date_completed', 'hours', 'category'],
        ['Advanced JavaScript Workshop', '2-day workshop on modern JavaScript features', '2024-01-15', '16', 'Training'],
        ['Annual Tech Conference', 'Attended annual technology conference with various speakers', '2024-02-20', '8', 'Conference'],
        ['React Best Practices Book', 'Read book on React patterns and best practices', '2024-03-10', '10', 'Reading'],
        ['Data Science Online Course', 'Completed Coursera data science specialization', '2024-03-25', '30', 'Online Course'],
        ['Team Leadership Seminar', 'Internal seminar on team management skills', '2024-04-05', '4', 'Other']
    ];
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cpd_import_template_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 compatibility in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Write data rows
    foreach ($template_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

generateCSVTemplate();
?>
[file content end]