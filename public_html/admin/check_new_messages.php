<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$conversationId = $_GET['conversation_id'] ?? 0;
$lastMessageId = $_GET['last_id'] ?? 0;

if (empty($conversationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing conversation ID']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation status
    $stmt = $conn->prepare("SELECT status FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode([
            'success' => false,
            'error' => 'Conversation not found'
        ]);
        exit;
    }
    
    // Get new messages
    $stmt = $conn->prepare("
        SELECT id, message, sender_type, created_at 
        FROM messages 
        WHERE conversation_id = ? AND id > ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversationId, $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'conversation_status' => $conversation['status']
    ]);
    
} catch (PDOException $e) {
    error_log("Check new messages error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 