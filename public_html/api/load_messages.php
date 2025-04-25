<?php
require_once '../config.php';

header('Content-Type: application/json');

$sessionId = $_GET['session_id'] ?? null;

if (empty($sessionId)) {
    echo json_encode(['error' => 'Session ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation ID
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode(['messages' => []]);
        exit;
    }
    
    // Get messages
    $stmt = $conn->prepare("
        SELECT id, message, sender_type 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversation['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['messages' => $messages]);
    
} catch (PDOException $e) {
    error_log("Load messages error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?> 