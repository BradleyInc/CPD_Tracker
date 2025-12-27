<?php
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function getUserCPDEntries($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM cpd_entries 
        WHERE user_id = ? 
        ORDER BY date_completed DESC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function getTotalCPDHours($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(hours), 0) as total_hours 
        FROM cpd_entries 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total_hours'];
}

function validateCPDEntry($data) {
    $errors = [];
    
    // Required fields with sanitization
    // Handle both POST array data and CSV row data
    $title = is_array($data) ? trim($data['title'] ?? '') : trim($data);
    if (empty($title)) {
        $errors[] = "Title is required";
    } elseif (strlen($title) > 255) {
        $errors[] = "Title cannot exceed 255 characters";
    }
    
    $description = trim($data['description'] ?? '');
    if (strlen($description) > 2000) {
        $errors[] = "Description cannot exceed 2000 characters";
    }
    
    if (empty($data['date_completed'])) {
        $errors[] = "Date completed is required";
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $data['date_completed']);
        if (!$date || $date->format('Y-m-d') !== $data['date_completed']) {
            $errors[] = "Invalid date format";
        } elseif (strtotime($data['date_completed']) > strtotime('today')) {
            $errors[] = "Date cannot be in the future";
        }
    }
    
    if (empty($data['hours']) || !is_numeric($data['hours'])) {
        $errors[] = "Valid hours are required";
    } elseif ($data['hours'] <= 0) {
        $errors[] = "Hours must be greater than 0";
    } elseif ($data['hours'] > 100) {
        $errors[] = "Hours cannot exceed 100";
    }
    
    // Validate category against allowed values
    $allowed_categories = ['Training', 'Conference', 'Reading', 'Online Course', 'Other'];
    $category = $data['category'] ?? '';
    if (!in_array($category, $allowed_categories)) {
        $errors[] = "Invalid category selected";
    }
    
    // File validation - UPDATED to include .ics files
    if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['supporting_doc'];
        
        // Check file type by extension and MIME type
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'ics'];
        $file_info = pathinfo($file['name']);
        $file_extension = strtolower($file_info['extension'] ?? '');
        
        $allowed_mime_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/calendar', // .ics files
            'application/octet-stream' // Some .ics files may have this MIME type
        ];
        
        if (!in_array($file_extension, $allowed_extensions) || 
            !in_array($file['type'], $allowed_mime_types)) {
            $errors[] = "Invalid file type. Please upload PDF, JPEG, PNG, Word documents, or .ics calendar files";
        }
        
        // Additional MIME type validation
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            // Also accept text/plain for .ics files (some servers may detect them as text/plain)
            if (!in_array($real_mime, $allowed_mime_types) && $real_mime !== 'text/plain') {
                $errors[] = "Invalid file type detected";
            }
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // Increased to 10MB for .ics files
            $errors[] = "File size must be less than 10MB";
        }
    }
    
    return $errors;
}

function handleFileUpload($file, $user_id) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Verify it's a valid uploaded file
    if (!is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    
    $upload_dir = 'uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate secure filename with user_id prefix
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return null;
    }
    
    // Create unique filename with user_id and timestamp to prevent collisions
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $filename = "user{$user_id}_{$timestamp}_{$random}.{$file_extension}";
    
    // Ensure the upload directory exists and get its real path
    $upload_dir_real = realpath($upload_dir);
    if ($upload_dir_real === false) {
        return null;
    }
    
    $filepath = $upload_dir_real . DIRECTORY_SEPARATOR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return null;
}

function deleteCPDEntry($pdo, $entry_id, $user_id) {
    // First get the file path to delete the physical file
    $stmt = $pdo->prepare("SELECT supporting_docs FROM cpd_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$entry_id, $user_id]);
    $entry = $stmt->fetch();
    
    if ($entry && $entry['supporting_docs']) {
        $filename = $entry['supporting_docs'];
        
        // Verify filename belongs to this user before deletion
        if (preg_match('/^user' . $user_id . '_/', $filename)) {
            $filepath = 'uploads/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
    
    // Delete the database entry
    $stmt = $pdo->prepare("DELETE FROM cpd_entries WHERE id = ? AND user_id = ?");
    return $stmt->execute([$entry_id, $user_id]);
}

// Function to get user role (add to auth.php)
function getUserRole($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}
?>