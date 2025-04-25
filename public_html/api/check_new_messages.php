<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$sessionId = $_GET['session_id'] ?? null;
$lastMessageId = $_GET['last_id'] ?? 0;

if (empty($sessionId)) {
    echo json_encode(['error' => 'Session ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation ID and status
    $stmt = $conn->prepare("SELECT id FROM conversations WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo json_encode(['messages' => []]);
        exit;
    }
    
    // Get new messages
    $stmt = $conn->prepare("
        SELECT id, message, sender_type, created_at 
        FROM messages 
        WHERE conversation_id = ? AND id > ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversation['id'], $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'messages' => $messages,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Check new messages error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?> 