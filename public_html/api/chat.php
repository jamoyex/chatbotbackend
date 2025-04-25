<?php
require_once '../config.php';
require_once 'chatbase_handler.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$session_id = $input['session_id'] ?? '';
$conversation_id_client = $input['conversation_id'] ?? ''; // Get client-side conversation ID

if (!$message || !$session_id || !$conversation_id_client) { // Check for client ID too
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation ID and status from session (using PHP session_id for DB lookup)
    $stmt = $conn->prepare("SELECT id, status FROM conversations WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $db_conversation_id = null;
    $conversation_status = 'active';

    if (!$conversation) {
        // Create new conversation if none exists
        $stmt = $conn->prepare("INSERT INTO conversations (session_id, status, created_at) VALUES (?, 'active', NOW())");
        $stmt->execute([$session_id]);
        $db_conversation_id = $conn->lastInsertId();
    } else {
        $db_conversation_id = $conversation['id'];
        $conversation_status = $conversation['status'];
    }
    
    // Store user message in DB
    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_type, message) VALUES (?, 'user', ?)");
    $stmt->execute([$db_conversation_id, $message]);
    $message_id = $conn->lastInsertId(); // Get the ID of the newly inserted user message
    
    // Only send to Chatbase if conversation is active (not handled)
    if ($conversation_status === 'active') {
        // Fetch message history for this conversation from DB
        $stmt_history = $conn->prepare("SELECT sender_type, message FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $stmt_history->execute([$db_conversation_id]);
        $db_messages = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

        // Format messages for Chatbase API
        $chatbase_messages = [];
        foreach ($db_messages as $msg) {
            $role = ($msg['sender_type'] === 'user') ? 'user' : 'assistant'; // Map DB sender_type to Chatbase role
            // Skip system messages if any
            if ($msg['sender_type'] !== 'system') { 
                $chatbase_messages[] = ['role' => $role, 'content' => $msg['message']];
            }
        }

        // Ensure the latest user message is included if somehow missed by fetch (unlikely but safe)
        // Note: The working example sends the current message within the history array. We replicate that.
        // No need to add separately as it was just inserted and fetched.

        try {
            $chatbase = new ChatbaseHandler();
            // Pass the formatted history and the CLIENT conversation ID
            $chatbase_response = $chatbase->sendMessage($chatbase_messages, $conversation_id_client);
            
            error_log("Chat.php received response: " . json_encode($chatbase_response));
            
            if ($chatbase_response['success']) {
                // Store Chatbase response as bot message in DB
                if (isset($chatbase_response['response']['text'])) {
                    $bot_response = $chatbase_response['response']['text'];
                    $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
                    $stmt->execute([$db_conversation_id, $bot_response]);
                } else {
                    error_log("Unexpected Chatbase response structure: " . json_encode($chatbase_response));
                    // Don't store a fallback message if the structure is wrong, just log
                }
            } else {
                // Log error but don't send fallback to user via DB
                error_log("Chatbase error: " . ($chatbase_response['error'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            error_log("Chatbase handler error: " . $e->getMessage());
        }
    }
    
    // Always return success after storing the user message, regardless of bot response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message_id' => $message_id // Return the ID of the stored user message
    ]);
    
} catch (PDOException $e) {
    error_log("Chat error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 