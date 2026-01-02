<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/manager_partner_functions.php';
require_once 'includes/team_functions.php';
require_once 'includes/admin_functions.php';

// Check authentication
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the filename from query parameter
$filename = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$filename) {
    die("No file specified");
}

// Validate the filename format (should be user{id}_{timestamp}_{random}.{ext})
if (!preg_match('/^user(\d+)_\d+_[a-f0-9]{16}\.(pdf|jpg|jpeg|png|doc|docx|csv)$/i', $filename)) {
    die("Invalid filename format");
}

// Extract user_id from filename
preg_match('/^user(\d+)_/', $filename, $matches);
$file_owner_id = $matches[1] ?? null;

// File path
$filepath = 'uploads/' . $filename;

// Verify file exists on disk
if (!file_exists($filepath)) {
    die("File not found on server");
}

// Prevent directory traversal
$real_filepath = realpath($filepath);
$real_upload_dir = realpath('uploads/');
if (strpos($real_filepath, $real_upload_dir) !== 0) {
    die("Invalid file path");
}

// Get the CPD entry associated with this file
try {
    $stmt = $pdo->prepare("SELECT user_id FROM cpd_entries WHERE supporting_docs = ?");
    $stmt->execute([$filename]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        die("Entry not found");
    }
    
    // Verify the file belongs to the owner extracted from filename
    if ($file_owner_id != $entry['user_id']) {
        die("Filename does not match database record");
    }
} catch (PDOException $e) {
    error_log("File verification error: " . $e->getMessage());
    die("Error verifying file access");
}

$current_user_id = $_SESSION['user_id'];

// Check if user has permission to download this file
$has_permission = false;

// 1. User owns the file
if ($file_owner_id == $current_user_id) {
    $has_permission = true;
}

// 2. User is a manager of a team that the file owner is in
if (!$has_permission && function_exists('isManager') && isManager()) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_teams ut
        JOIN team_managers tm ON ut.team_id = tm.team_id
        WHERE ut.user_id = ? AND tm.manager_id = ?
    ");
    $stmt->execute([$file_owner_id, $current_user_id]);
    if ($stmt->fetchColumn() > 0) {
        $has_permission = true;
    }
}

// 3. User is a partner of a team that the file owner is in
if (!$has_permission && function_exists('isPartner') && isPartner()) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_teams ut
        WHERE ut.user_id = ?
        AND (
            ut.team_id IN (
                SELECT team_id FROM team_partners WHERE partner_id = ?
            )
            OR ut.team_id IN (
                SELECT t.id FROM teams t
                JOIN departments d ON t.department_id = d.id
                JOIN department_partners dp ON d.id = dp.department_id
                WHERE dp.partner_id = ?
            )
        )
    ");
    $stmt->execute([$file_owner_id, $current_user_id, $current_user_id]);
    if ($stmt->fetchColumn() > 0) {
        $has_permission = true;
    }
}

// 4. User is an admin (can view all files in their organization)
if (!$has_permission && isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin')) {
    // For regular admins, check they're in the same organization
    if ($_SESSION['user_role'] === 'admin') {
        $admin_org_id = getAdminOrganisationId($pdo, $current_user_id);
        $stmt = $pdo->prepare("SELECT organisation_id FROM users WHERE id = ?");
        $stmt->execute([$file_owner_id]);
        $file_owner_org = $stmt->fetchColumn();
        
        if ($admin_org_id == $file_owner_org) {
            $has_permission = true;
        }
    } else {
        // Super admin can view all files
        $has_permission = true;
    }
}

// If no permission, deny access
if (!$has_permission) {
    die("Access denied: You do not have permission to view this file");
}

// Get file info for headers
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

$file_size = filesize($filepath);

// Get original filename (strip the user prefix and timestamp)
$original_filename = preg_replace('/^user\d+_\d+_[a-f0-9]+\./', '', $filename);
if ($original_filename === $filename) {
    // Fallback if pattern doesn't match
    $original_filename = $filename;
}

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $file_size);

// For PDFs and images, display inline; for others, force download
$inline_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
if (in_array($mime_type, $inline_types)) {
    header('Content-Disposition: inline; filename="' . $original_filename . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $original_filename . '"');
}

// Prevent caching
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Clear output buffer
ob_clean();
flush();

// Output file
readfile($filepath);
exit();
?>