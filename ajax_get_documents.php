<?php
// ajax_get_documents.php
// Create this new file in your root directory

require_once 'includes/database.php';
require_once 'includes/auth.php';

// Check authentication
checkAuth();

header('Content-Type: application/json');

if (isset($_GET['entry_id'])) {
    $entry_id = intval($_GET['entry_id']);
    
    if ($entry_id <= 0) {
        echo json_encode([]);
        exit;
    }
    
    try {
        // Verify the entry belongs to the user
        $stmt = $pdo->prepare("SELECT id FROM cpd_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$entry_id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            echo json_encode([]);
            exit;
        }
        
        // Get documents
        $documents = getCPDDocuments($pdo, $entry_id);
        echo json_encode($documents);
        
    } catch (Exception $e) {
        error_log("Error fetching documents: " . $e->getMessage());
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>