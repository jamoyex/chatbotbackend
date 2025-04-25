<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$conversationId = $_GET['id'] ?? 0;

if (!$conversationId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verify conversation exists and is active
    $stmt = $conn->prepare("SELECT status FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation || $conversation['status'] !== 'active') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid conversation status']);
        exit;
    }
    
    // Update conversation status to handled
    $stmt = $conn->prepare("UPDATE conversations SET status = 'handled' WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    // Add system message about agent takeover
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_type, message) 
        VALUES (?, 'bot', 'An agent has joined the conversation. You are now chatting with a human representative.')
    ");
    $stmt->execute([$conversationId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Takeover error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 