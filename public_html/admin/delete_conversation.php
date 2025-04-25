<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['conversation_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Conversation ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Delete messages first (due to foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM messages WHERE conversation_id = ?");
    $stmt->execute([$_POST['conversation_id']]);
    
    // Then delete the conversation
    $stmt = $conn->prepare("DELETE FROM conversations WHERE id = ?");
    $stmt->execute([$_POST['conversation_id']]);
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if ($conn) {
        $conn->rollBack();
    }
    error_log("Delete conversation error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} 