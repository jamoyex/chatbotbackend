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

$conversationId = $_GET['id'] ?? 0;

if (!$conversationId) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid conversation ID'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation details
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Conversation not found'
        ]);
        exit;
    }
    
    // Get messages
    $stmt = $conn->prepare("
        SELECT * FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'conversation' => $conversation,
        'messages' => $messages
    ]);
    
} catch (PDOException $e) {
    error_log("Error getting conversation: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} 