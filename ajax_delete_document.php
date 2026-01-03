<?php
// ajax_delete_document.php
// Create this new file in your root directory

require_once 'includes/database.php';
require_once 'includes/auth.php';

// Check authentication
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $document_id = intval($_POST['document_id'] ?? 0);
    
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit;
    }
    
    try {
        $result = deleteCPDDocument($pdo, $document_id, $_SESSION['user_id']);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
        }
    } catch (Exception $e) {
        error_log("Error deleting document: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting document']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>