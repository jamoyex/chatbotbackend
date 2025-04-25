<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Get POST parameters
$conversationId = $_POST['conversation_id'] ?? null;
$message = $_POST['message'] ?? null;

// Validate required parameters
if (!$conversationId || !$message) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // First check if conversation exists
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Conversation not found'
        ]);
        exit;
    }
    
    // Insert the message
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, message, sender_type, created_at) 
        VALUES (?, ?, 'agent', NOW())
    ");
    
    $stmt->execute([$conversationId, $message]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Error sending message: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?> 