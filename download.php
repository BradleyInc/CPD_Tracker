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
$file_user_id = $matches[1] ?? null;

// Verify the file belongs to the current user
if ($file_user_id != $_SESSION['user_id']) {
    die("Access denied");
}

// Verify the file exists in the database for this user
try {
    $stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE user_id = ? AND supporting_docs = ?");
    $stmt->execute([$_SESSION['user_id'], $filename]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        die("File not found in your records");
    }
} catch (PDOException $e) {
    error_log("File verification error: " . $e->getMessage());
    die("Error verifying file access");
}

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

// Get file info for headers
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

$file_size = filesize($filepath);
$original_name = $filename; // Or you could store original name in database

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $original_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Output file
readfile($filepath);
exit();
?>