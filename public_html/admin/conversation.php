<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$conversationId = $_GET['id'] ?? 0;

if (!$conversationId) {
    header('Location: dashboard.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get conversation details
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        header('Location: dashboard.php');
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
    
} catch (PDOException $e) {
    error_log("Conversation view error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - Chatbot Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container {
            height: calc(100vh - 300px);
            padding: 20px;
            background-color: #f8f9fa;
        }
        .message {
            max-width: 70%;
            margin: 10px 0;
            padding: 12px 16px;
            border-radius: 15px;
            position: relative;
            word-wrap: break-word;
            clear: both;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 4px;
        }
        .user-message {
            background-color: #e9ecef;
            color: #212529;
            float: left;
            border-bottom-left-radius: 5px;
        }
        .bot-message {
            background-color: #e9ecef;
            color: #212529;
            float: left;
            border-bottom-left-radius: 5px;
        }
        .agent-message {
            background-color: #28a745;
            color: white;
            float: right;
            border-bottom-right-radius: 5px;
        }
        .message-container {
            overflow: hidden;
            margin-bottom: 10px;
        }
        .sender-name {
            font-size: 0.8rem;
            margin-bottom: 4px;
            color: #6c757d;
        }
        .message-status {
            font-size: 0.7rem;
            text-align: right;
            margin-top: 2px;
            color: #6c757d;
        }
        /* Clear float after each message group */
        .message-container::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="dashboard.php" class="text-gray-700 hover:text-gray-900">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-700 mr-4"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                        <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Conversation Details
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            Session ID: <?php echo htmlspecialchars($conversation['session_id']); ?>
                        </p>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            Status: 
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $conversation['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                    ($conversation['status'] === 'handled' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <?php echo ucfirst($conversation['status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="chat-container overflow-y-auto border-t border-gray-200" id="chat-messages">
                        <?php foreach ($messages as $message): ?>
                        <div class="message-container">
                            <div class="message <?php echo $message['sender_type'] . '-message'; ?>">
                                <div class="sender-name">
                                    <?php 
                                    echo $message['sender_type'] === 'user' ? 'Visitor' : 
                                        ($message['sender_type'] === 'agent' ? 'You' : 'Bot');
                                    ?>
                                </div>
                                <?php echo htmlspecialchars($message['message']); ?>
                                <div class="message-time">
                                    <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($conversation['status'] === 'handled'): ?>
                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <form id="agent-message-form" class="flex gap-2">
                            <input type="hidden" name="conversation_id" value="<?php echo $conversationId; ?>">
                            <input type="text" name="message" 
                                   class="flex-1 p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Type your message...">
                            <button type="submit" 
                                    class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                                Send
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const agentMessageForm = document.getElementById('agent-message-form');
        const chatContainer = document.querySelector('.chat-container');
        let lastMessageId = <?php echo count($messages) > 0 ? end($messages)['id'] : 0; ?>;
        let isSubmitting = false;
        
        // Function to check for new messages
        async function checkNewMessages() {
            if (isSubmitting) return; // Skip checking while submitting
            
            try {
                const response = await fetch('check_new_messages.php?conversation_id=<?php echo $conversationId; ?>&last_id=' + lastMessageId);
                const data = await response.json();
                
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        const messageContainer = document.createElement('div');
                        messageContainer.className = 'message-container';
                        
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${msg.sender_type}-message`;
                        
                        const senderName = document.createElement('div');
                        senderName.className = 'sender-name';
                        senderName.textContent = msg.sender_type === 'user' ? 'Visitor' : 
                            (msg.sender_type === 'agent' ? 'You' : 'Bot');
                        
                        const messageContent = document.createElement('div');
                        messageContent.textContent = msg.message;
                        
                        const messageTime = document.createElement('div');
                        messageTime.className = 'message-time';
                        messageTime.textContent = new Date(msg.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        
                        messageDiv.appendChild(senderName);
                        messageDiv.appendChild(messageContent);
                        messageDiv.appendChild(messageTime);
                        messageContainer.appendChild(messageDiv);
                        chatContainer.appendChild(messageContainer);
                        
                        lastMessageId = msg.id;
                    });
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Error checking new messages:', error);
            }
        }
        
        // Check for new messages every 2 seconds
        const pollInterval = setInterval(checkNewMessages, 2000);
        
        if (agentMessageForm) {
            agentMessageForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const message = e.target.message.value.trim();
                const conversationId = e.target.conversation_id.value;
                
                if (message) {
                    try {
                        isSubmitting = true;
                        const response = await fetch('send_message.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                conversation_id: conversationId,
                                message: message
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            // Clear input
                            e.target.message.value = '';
                            // Check for new messages immediately
                            await checkNewMessages();
                        } else {
                            console.error('Failed to send message:', data.error);
                            alert('Failed to send message. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while sending the message. Please try again.');
                    } finally {
                        isSubmitting = false;
                    }
                }
            });
        }
        
        // Handle page visibility
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                checkNewMessages();
            }
        });
    </script>
</body>
</html> 