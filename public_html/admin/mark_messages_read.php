<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$conversationId = $input['conversation_id'] ?? 0;

if (!$conversationId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Mark all user messages as read in this conversation
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE conversation_id = ? 
        AND sender_type = 'user'
        AND is_read = 0
    ");
    $stmt->execute([$conversationId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Mark messages read error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 