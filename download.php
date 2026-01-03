<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Check authentication
checkAuth();

if (!isset($_GET['file'])) {
    die('No file specified');
}

$filename = $_GET['file'];

// Sanitize filename - only allow alphanumeric, dots, underscores, hyphens
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
    die('Invalid filename');
}

// First, try to find the document in the cpd_documents table
$stmt = $pdo->prepare("
    SELECT d.filename, d.original_filename, e.user_id, e.id as entry_id
    FROM cpd_documents d
    JOIN cpd_entries e ON d.entry_id = e.id
    WHERE d.filename = ?
");
$stmt->execute([$filename]);
$document = $stmt->fetch();

// If found in cpd_documents table
if ($document) {
    // Check authorization - user must own the entry OR be authorized to view it
    $can_access = false;
    
    // Owner can access
    if ($document['user_id'] == $_SESSION['user_id']) {
        $can_access = true;
    }
    
    // Admins can access everything
    if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
        $can_access = true;
    }
    
    // Managers can access their team members' documents
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_managers tm ON ut.team_id = tm.team_id
            WHERE ut.user_id = ? AND tm.manager_id = ?
        ");
        $stmt->execute([$document['user_id'], $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $can_access = true;
        }
    }
    
    // Partners can access documents from their teams/departments
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'partner') {
        // Check if user is in partner's teams
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_partners tp ON ut.team_id = tp.team_id
            WHERE ut.user_id = ? AND tp.partner_id = ?
        ");
        $stmt->execute([$document['user_id'], $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $can_access = true;
        } else {
            // Check if user is in partner's departments
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM user_teams ut
                JOIN teams t ON ut.team_id = t.id
                JOIN department_partners dp ON t.department_id = dp.department_id
                WHERE ut.user_id = ? AND dp.partner_id = ?
            ");
            $stmt->execute([$document['user_id'], $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $can_access = true;
            }
        }
    }
    
    if (!$can_access) {
        die('Access denied');
    }
    
    $filepath = 'uploads/' . $document['filename'];
    $original_filename = $document['original_filename'];
    
} else {
    // Fallback: Check old supporting_docs column for backward compatibility
    $stmt = $pdo->prepare("
        SELECT supporting_docs, user_id 
        FROM cpd_entries 
        WHERE supporting_docs = ?
    ");
    $stmt->execute([$filename]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        die('Entry not found');
    }
    
    // Check authorization (same logic as above)
    $can_access = false;
    
    if ($entry['user_id'] == $_SESSION['user_id']) {
        $can_access = true;
    }
    
    if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
        $can_access = true;
    }
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_managers tm ON ut.team_id = tm.team_id
            WHERE ut.user_id = ? AND tm.manager_id = ?
        ");
        $stmt->execute([$entry['user_id'], $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $can_access = true;
        }
    }
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'partner') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_teams ut
            JOIN team_partners tp ON ut.team_id = tp.team_id
            WHERE ut.user_id = ? AND tp.partner_id = ?
        ");
        $stmt->execute([$entry['user_id'], $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $can_access = true;
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM user_teams ut
                JOIN teams t ON ut.team_id = t.id
                JOIN department_partners dp ON t.department_id = dp.department_id
                WHERE ut.user_id = ? AND dp.partner_id = ?
            ");
            $stmt->execute([$entry['user_id'], $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $can_access = true;
            }
        }
    }
    
    if (!$can_access) {
        die('Access denied');
    }
    
    $filepath = 'uploads/' . $entry['supporting_docs'];
    $original_filename = $entry['supporting_docs']; // Use actual filename as fallback
}

// Check if file exists
if (!file_exists($filepath)) {
    die('File not found on server');
}

// Get file extension and set appropriate MIME type
$file_extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

$mime_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ics' => 'text/calendar'
];

$mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Security headers
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($original_filename) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

// Prevent execution of scripts
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'');

// Output file
readfile($filepath);
exit;
?>