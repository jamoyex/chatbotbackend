<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$conversationId = $_POST['conversation_id'] ?? 0;

if (!$conversationId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Verify conversation exists and is handled
    $stmt = $conn->prepare("SELECT status FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation || $conversation['status'] !== 'handled') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid conversation status']);
        exit;
    }
    
    // Update conversation status to active
    $stmt = $conn->prepare("UPDATE conversations SET status = 'active' WHERE id = ?");
    $stmt->execute([$conversationId]);
    
    // Add system message about bot takeover
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_type, message) 
        VALUES (?, 'bot', 'The AI assistant has taken over the conversation.')
    ");
    $stmt->execute([$conversationId]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Let bot handle error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 