<?php
require_once '../config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

try {
    $conn = getDBConnection();
    
    // Get page number from request, default to 1
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10; // Items per page
    $offset = ($page - 1) * $limit;
    
    // Get active conversations with read status
    $stmt = $conn->prepare("
        SELECT c.*, 
            (SELECT message 
             FROM messages 
             WHERE conversation_id = c.id 
             AND sender_type IN ('agent', 'bot')
             ORDER BY created_at DESC 
             LIMIT 1) as last_bot_message,
            (SELECT message 
             FROM messages 
             WHERE conversation_id = c.id 
             AND sender_type = 'user'
             ORDER BY created_at DESC 
             LIMIT 1) as last_user_message,
            (SELECT created_at 
             FROM messages 
             WHERE conversation_id = c.id 
             ORDER BY created_at DESC 
             LIMIT 1) as last_message_time,
            (SELECT COUNT(*) 
             FROM messages 
             WHERE conversation_id = c.id 
             AND is_read = 0
             AND sender_type = 'user') as unread_count
        FROM conversations c
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.status = 'handled' THEN 0 
                WHEN c.status = 'active' THEN 1
                ELSE 2 
            END,
            last_message_time DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countStmt = $conn->query("SELECT COUNT(*) FROM conversations");
    $totalConversations = $countStmt->fetchColumn();
    $hasMorePages = ($offset + $limit) < $totalConversations;
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred while loading conversations.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <audio id="notificationSound" preload="auto">
        <source src="notification.mp3" type="audio/mpeg">
    </audio>
    <style>
        /* Basic Reset & Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            overflow: hidden;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            overflow: hidden;
            position: relative;
        }
        .h-full {
            height: 100%;
        }
        .chat-preview {
            transition: all 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        .chat-preview:hover {
            background-color: #f8f9fa;
        }
        .chat-preview.active {
            background-color: #e9ecef;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            width: 70px;
            text-align: center;
            line-height: 1.5;
        }
        .status-active {
            background-color: #ecfdf5;
            color: #059669;
        }
        .status-handled {
            background-color: #fff7ed;
            color: #ea580c;
        }
        .status-pending {
            background-color: #f0f9ff;
            color: #0284c7;
        }
        #chatMessages {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
        }
        .message img {
            max-width: 300px;
            border-radius: 8px;
            margin: 4px 0;
        }
        .message.user {
            align-self: flex-start;
            background-color: #f0f0f0;
            color: #1a1a1a;
            border-bottom-left-radius: 4px;
            margin-right: auto;
        }
        .message.bot {
            align-self: flex-end;
            background-color: #0D6EFD;
            color: white;
            border-bottom-right-radius: 4px;
            margin-left: auto;
        }
        .message.agent {
            align-self: flex-end;
            background-color: #28a745;
            color: white;
            border-bottom-right-radius: 4px;
            margin-left: auto;
        }
        .message.agent img,
        .message.bot img {
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        /* Add styles for scrollable containers */
        .scroll-on-hover {
            overflow: hidden;
        }
        .scroll-on-hover:hover {
            overflow-y: auto;
        }
        /* Hide scrollbar for Chrome, Safari and Opera */
        .scroll-on-hover::-webkit-scrollbar {
            width: 6px;
        }
        .scroll-on-hover::-webkit-scrollbar-track {
            background: transparent;
        }
        .scroll-on-hover::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        /* Scrollbar styles */
        .custom-scrollbar {
            overflow-y: auto;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        /* Mobile sidebar styles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                bottom: 0;
                width: 80%;
                max-width: 300px;
                z-index: 50;
                transition: all 0.3s ease-in-out;
                background: white;
            }
            .sidebar.active {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
        .unread-message {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .unread-preview {
            background-color: rgba(59, 130, 246, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="bg-red-600">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-semibold text-white">Sarla Messages Dashboard</span>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-100 mr-4"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="logout.php" class="text-white hover:text-gray-200">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1">
        <div class="h-full">
            <div class="bg-white rounded-lg shadow h-full flex flex-col">
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <button id="sidebarToggle" class="md:hidden text-gray-600 hover:text-gray-800">
                                <i class="fas fa-bars text-xl"></i>
                            </button>
                            <h2 class="text-lg font-semibold text-gray-800">Chat Conversations</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="refreshBtn" class="flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-sync-alt"></i>
                                <span class="ml-1 hidden md:inline">Refresh</span>
                            </button>
                            <div class="relative hidden md:block">
                                <input type="text" id="searchInput" placeholder="Search conversations..." 
                                       class="w-56 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-search absolute right-3 top-2.5 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex flex-1 min-h-0">
                    <!-- Sidebar Overlay -->
                    <div id="sidebarOverlay" class="sidebar-overlay"></div>
                    
                    <!-- Conversations List - Sidebar -->
                    <div id="sidebar" class="sidebar md:relative md:left-0 w-full md:w-1/3 border-r border-gray-200 flex flex-col min-h-0 bg-white">
                        <div class="md:hidden p-3 border-b border-gray-200">
                            <div class="relative">
                                <input type="text" id="mobileSearchInput" placeholder="Search conversations..." 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-search absolute right-3 top-2.5 text-gray-400"></i>
                            </div>
                        </div>
                        <div class="flex-1 custom-scrollbar">
                            <?php if (isset($error)): ?>
                                <div class="p-3 text-red-700 bg-red-100"><?php echo htmlspecialchars($error); ?></div>
                            <?php else: ?>
                                <div id="conversations-list">
                                <?php foreach ($conversations as $conv): ?>
                                    <div class="chat-preview hover:bg-gray-50 cursor-pointer <?php 
                                        echo $conv['unread_count'] > 0 ? 'unread-preview' : ''; 
                                        ?>" 
                                         onclick="openConversation(<?php echo $conv['id']; ?>)"
                                         data-conversation-id="<?php echo $conv['id']; ?>">
                                        <div class="flex items-start gap-3 py-3 px-3">
                                            <span class="status-badge <?php echo 'status-' . $conv['status']; ?>">
                                                <?php echo ucfirst($conv['status']); ?>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm text-gray-900 truncate <?php 
                                                    echo $conv['unread_count'] > 0 ? 'unread-message' : ''; 
                                                    ?>">
                                                    <?php 
                                                        echo $conv['last_bot_message'] ? 
                                                            htmlspecialchars($conv['last_bot_message']) : 
                                                            '<span class="text-gray-400">No response yet</span>';
                                                    ?>
                                                </div>
                                                <div class="text-sm text-gray-500 truncate <?php 
                                                    echo $conv['unread_count'] > 0 ? 'unread-message' : ''; 
                                                    ?>">
                                                    <?php 
                                                        echo $conv['last_user_message'] ? 
                                                            htmlspecialchars($conv['last_user_message']) : 
                                                            '<span class="text-gray-400">No messages yet</span>';
                                                    ?>
                                                </div>
                                            </div>
                                            <span class="text-xs text-gray-500 shrink-0 mt-0.5">
                                                <?php 
                                                $time = strtotime($conv['last_message_time']);
                                                echo human_timing($time); 
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <?php if ($hasMorePages): ?>
                                    <div class="p-4 text-center">
                                        <button id="load-more-btn" 
                                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors"
                                                data-page="<?php echo $page + 1; ?>">
                                            Load More
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="w-full md:w-2/3 flex flex-col bg-gray-50 min-h-0">
                        <div id="chatHeader" class="p-4 bg-white border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h2 id="conversationTitle" class="text-lg font-medium text-gray-900">Select a conversation</h2>
                                <div class="flex items-center gap-2">
                                    <button id="takeOverBtn" 
                                            onclick="takeOver(event)" 
                                            title="Take Over Conversation"
                                            class="hidden px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                        Take Over
                                    </button>
                                    <button id="letBotHandleBtn" 
                                            onclick="letBotHandle(event)" 
                                            title="Let AI Respond"
                                            class="hidden px-3 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200">
                                        Let AI Respond
                                    </button>
                                    <button id="deleteConversationBtn" 
                                            onclick="deleteConversation()" 
                                            title="Delete Conversation"
                                            class="hidden p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="chatMessages" class="flex-1 p-4 custom-scrollbar">
                            <!-- Messages will be loaded here -->
                        </div>

                        <div id="messageFormContainer" class="p-4 bg-white border-t border-gray-200">
                            <div id="activeNotice" class="hidden text-center p-3 bg-blue-50 text-blue-700 rounded-lg">
                                This conversation is being handled by the AI assistant. Take over to respond.
                            </div>
                            <form id="messageForm" onsubmit="sendMessage(event)" class="flex gap-2">
                                <input type="text" name="message" 
                                       class="flex-1 rounded-full border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="Type your message...">
                                <button type="submit" 
                                        class="bg-blue-500 text-white px-4 py-2 rounded-full hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                                    Send
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialize variables for intervals
        let currentConversationId = null;
        let lastMessageId = 0;
        let checkMessagesInterval = null;
        let checkConversationsInterval = null;
        let lastConversationCount = 0;
        let notificationSound = null;
        let isPageVisible = true;

        // Handle page visibility
        document.addEventListener('visibilitychange', () => {
            isPageVisible = !document.hidden;
            console.log('Page visibility changed:', {
                isPageVisible,
                hidden: document.hidden,
                visibilityState: document.visibilityState
            });
        });

        // Initialize sound and start conversation updates when document is ready
        document.addEventListener('DOMContentLoaded', () => {
            notificationSound = document.getElementById('notificationSound');
            
            // Start conversation list updates
            startConversationUpdates();

            // Check for conversation ID in URL
            const conversationId = getUrlParameter('conversation');
            if (conversationId) {
                openConversation(conversationId);
            }
        });

        function startConversationUpdates() {
            // Clear existing interval if any
            if (checkConversationsInterval) {
                clearInterval(checkConversationsInterval);
            }
            
            // Update conversations list every 3 seconds
            checkConversationsInterval = setInterval(() => {
                // Refresh the conversation list by reloading the page content
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Get the conversations container
                        const currentContainer = document.querySelector('.custom-scrollbar');
                        const newContainer = doc.querySelector('.custom-scrollbar');
                        
                        if (currentContainer && newContainer) {
                            // Store current scroll position
                            const scrollTop = currentContainer.scrollTop;
                            
                            // Get all current and new previews
                            const currentPreviews = Array.from(currentContainer.querySelectorAll('.chat-preview'));
                            const newPreviews = Array.from(newContainer.querySelectorAll('.chat-preview'));
                            
                            // Track if we need to play a notification
                            let shouldPlayNotification = false;
                            
                            newPreviews.forEach(newPreview => {
                                const conversationId = newPreview.getAttribute('data-conversation-id');
                                const status = newPreview.querySelector('.status-badge').textContent.trim().toLowerCase();
                                const currentPreview = currentPreviews.find(p => 
                                    p.getAttribute('data-conversation-id') === conversationId
                                );
                                
                                if (currentPreview) {
                                    // Update existing preview content without affecting its classes
                                    const currentClasses = currentPreview.className;
                                    const isCurrentlyActive = currentPreview.classList.contains('bg-blue-50');
                                    
                                    // Check for new messages
                                    if (status === 'handled') {
                                        const currentUserMessage = currentPreview.querySelector('.text-sm.text-gray-500').textContent;
                                        const newUserMessage = newPreview.querySelector('.text-sm.text-gray-500').textContent;
                                        
                                        // If there's a new message and either:
                                        // 1. The browser tab is not active, or
                                        // 2. This is not the currently selected conversation
                                        if (currentUserMessage !== newUserMessage && 
                                            (!isPageVisible || conversationId !== currentConversationId)) {
                                            shouldPlayNotification = true;
                                        }
                                    }

                                    // Update only the inner content
                                    const currentInner = currentPreview.querySelector('.flex.items-start.gap-3');
                                    const newInner = newPreview.querySelector('.flex.items-start.gap-3');
                                    if (currentInner && newInner) {
                                        currentInner.innerHTML = newInner.innerHTML;
                                    }
                                    
                                    // Restore classes including active state
                                    currentPreview.className = currentClasses;
                                    if (isCurrentlyActive) {
                                        currentPreview.classList.add('bg-blue-50');
                                    }
                                } else {
                                    // This is a new conversation, prepend it to the container
                                    currentContainer.insertBefore(newPreview, currentContainer.firstChild);
                                    // Play notification for new handled conversations
                                    if (status === 'handled') {
                                        shouldPlayNotification = true;
                                    }
                                }
                            });
                            
                            // Play notification sound if needed
                            if (shouldPlayNotification) {
                                playNotificationSound();
                            }
                            
                            // Remove conversations that no longer exist
                            currentPreviews.forEach(currentPreview => {
                                const conversationId = currentPreview.getAttribute('data-conversation-id');
                                const stillExists = newPreviews.some(p => 
                                    p.getAttribute('data-conversation-id') === conversationId
                                );
                                if (!stillExists) {
                                    currentPreview.remove();
                                }
                            });

                            // Restore scroll position
                            currentContainer.scrollTop = scrollTop;
                            
                            // Update unread styling for non-active conversations
                            const previews = currentContainer.querySelectorAll('.chat-preview');
                            previews.forEach(preview => {
                                const previewId = preview.getAttribute('data-conversation-id');
                                const status = preview.querySelector('.status-badge').textContent.trim().toLowerCase();
                                if (previewId && previewId !== currentConversationId && status === 'handled') {
                                    const unreadCount = parseInt(preview.getAttribute('data-unread-count') || '0');
                                    if (unreadCount > 0) {
                                        preview.classList.add('unread-preview');
                                        preview.querySelectorAll('.message-preview').forEach(el => {
                                            el.classList.add('unread-message');
                                        });
                                    }
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error updating conversations:', error));
            }, 3000);
        }

        function playNotificationSound() {
            if (!notificationSound) {
                console.error('Notification sound element not found');
                return;
            }

            console.log('Attempting to play sound...', {
                soundElement: notificationSound,
                soundSource: notificationSound.src,
                muted: notificationSound.muted,
                volume: notificationSound.volume
            });
            
            notificationSound.currentTime = 0; // Reset sound to start
            notificationSound.volume = 1.0; // Ensure volume is up
            notificationSound.muted = false; // Ensure not muted
            
            const playPromise = notificationSound.play();
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        console.log('Sound played successfully');
                    })
                    .catch(error => {
                        console.error('Error playing sound:', error);
                    });
            }
        }

        // Function to get URL parameters
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            const results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        // Function to update URL without refreshing
        function updateUrlParameter(key, value) {
            const baseUrl = window.location.origin + window.location.pathname;
            const urlParams = new URLSearchParams(window.location.search);
            if (value) {
                urlParams.set(key, value);
            } else {
                urlParams.delete(key);
            }
            const newParams = urlParams.toString();
            const newUrl = baseUrl + (newParams ? '?' + newParams : '');
            window.history.pushState({ path: newUrl }, '', newUrl);
        }

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        function deleteConversation() {
            if (!currentConversationId) return;
            
            if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('conversation_id', currentConversationId);
            
            fetch('delete_conversation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear chat area
                    document.getElementById('chatMessages').innerHTML = '';
                    document.getElementById('conversationTitle').textContent = 'Select a conversation';
                    document.getElementById('deleteConversationBtn').classList.add('hidden');
                    
                    // Stop checking for new messages
                    if (checkMessagesInterval) {
                        clearInterval(checkMessagesInterval);
                        checkMessagesInterval = null;
                    }
                    
                    // Reset current conversation and URL
                    currentConversationId = null;
                    updateUrlParameter('conversation', null);
                    
                    // Refresh the conversation list
                    location.reload();
                } else {
                    alert('Error deleting conversation: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting conversation');
            });
        }

        async function openConversation(conversationId) {
            if (window.innerWidth < 768) {
                toggleSidebar();
            }

            // Remove active class from all previews
            document.querySelectorAll('.chat-preview').forEach(preview => {
                preview.classList.remove('bg-blue-50');
            });
            
            // Add active class to selected preview
            const selectedPreview = document.querySelector(`.chat-preview[onclick*="${conversationId}"]`);
            if (selectedPreview) {
                selectedPreview.classList.add('bg-blue-50');
                // Remove unread styling
                selectedPreview.classList.remove('unread-preview');
                selectedPreview.querySelectorAll('.unread-message').forEach(el => {
                    el.classList.remove('unread-message');
                });
            }

            // Update current conversation ID before loading
            currentConversationId = conversationId;
            
            // Update URL with conversation ID
            updateUrlParameter('conversation', conversationId);
            
            // Show action buttons
            document.getElementById('deleteConversationBtn').classList.remove('hidden');

            // Stop any existing message check interval
            if (checkMessagesInterval) {
                clearInterval(checkMessagesInterval);
                checkMessagesInterval = null;
            }

            // Mark messages as read
            try {
                await fetch('mark_messages_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ conversation_id: conversationId })
                });
            } catch (error) {
                console.error('Error marking messages as read:', error);
            }
            
            // Load initial conversation and start checking for new messages
            await loadConversation(conversationId);
            
            // Start checking for new messages in this conversation
            checkNewMessages(conversationId);
            checkMessagesInterval = setInterval(() => checkNewMessages(conversationId), 3000);
        }

        function loadConversation(conversationId) {
            return new Promise((resolve, reject) => {
                fetch(`get_conversation.php?id=${conversationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const messagesContainer = document.getElementById('chatMessages');
                            messagesContainer.innerHTML = ''; // Clear existing messages
                            
                            // Update conversation title
                            document.getElementById('conversationTitle').textContent = 
                                `Chat Session: ${data.conversation.session_id}`;
                            
                            // Update action buttons based on conversation status
                            const takeOverBtn = document.getElementById('takeOverBtn');
                            const letBotHandleBtn = document.getElementById('letBotHandleBtn');
                            const messageForm = document.getElementById('messageForm');
                            const activeNotice = document.getElementById('activeNotice');
                            
                            if (data.conversation.status === 'active') {
                                takeOverBtn.classList.remove('hidden');
                                letBotHandleBtn.classList.add('hidden');
                                messageForm.classList.add('hidden');
                                activeNotice.classList.remove('hidden');
                            } else if (data.conversation.status === 'handled') {
                                takeOverBtn.classList.add('hidden');
                                letBotHandleBtn.classList.remove('hidden');
                                messageForm.classList.remove('hidden');
                                activeNotice.classList.add('hidden');
                            } else {
                                takeOverBtn.classList.add('hidden');
                                letBotHandleBtn.classList.add('hidden');
                                messageForm.classList.add('hidden');
                                activeNotice.classList.add('hidden');
                            }
                            
                            // Display messages
                            data.messages.forEach(message => {
                                const messageDiv = document.createElement('div');
                                messageDiv.className = `message ${message.sender_type}`;
                                messageDiv.innerHTML = formatMessage(message.message);
                                messagesContainer.appendChild(messageDiv);
                            });
                            
                            // Scroll to bottom
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            
                            // Store the last message ID
                            if (data.messages.length > 0) {
                                lastMessageId = data.messages[data.messages.length - 1].id;
                                console.log('Updated lastMessageId to:', lastMessageId);
                            }
                            resolve();
                        } else {
                            console.error('Error loading conversation:', data.error);
                            alert('Error loading conversation: ' + data.error);
                            reject(data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading conversation');
                        reject(error);
                    });
            });
        }

        function sendMessage(event) {
            event.preventDefault();
            const form = event.target;
            const messageInput = form.querySelector('input[name="message"]');
            const message = messageInput.value.trim();
            
            if (!message || !currentConversationId) return;
            
            // Check if conversation is handled before sending
            fetch(`get_conversation.php?id=${currentConversationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.conversation.status === 'handled') {
                        const formData = new FormData();
                        formData.append('conversation_id', currentConversationId);
                        formData.append('message', message);
                        
                        fetch('send_message.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                messageInput.value = '';
                                loadConversation(currentConversationId);
                            } else {
                                alert('Error sending message: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error sending message');
                        });
                    } else {
                        alert('Cannot send message. The conversation is currently being handled by the AI assistant.');
                        messageInput.value = '';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error checking conversation status');
                });
        }

        function updateConversationPreview(conversationId, message) {
            const preview = document.querySelector(`.chat-preview[onclick*="${conversationId}"]`);
            if (preview && message) {
                const agentMessagePreview = preview.querySelector('.text-sm.text-gray-900');
                const userMessagePreview = preview.querySelector('.text-sm.text-gray-500');
                const timePreview = preview.querySelector('.text-xs.text-gray-500');
                
                if (message.sender_type === 'agent') {
                    if (agentMessagePreview) {
                        agentMessagePreview.textContent = message.message;
                    }
                } else if (message.sender_type === 'user') {
                    if (userMessagePreview) {
                        userMessagePreview.textContent = message.message;
                        // Add unread styling if this isn't the current conversation
                        if (currentConversationId !== conversationId) {
                            preview.classList.add('unread-preview');
                            if (agentMessagePreview) agentMessagePreview.classList.add('unread-message');
                            userMessagePreview.classList.add('unread-message');
                        }
                    }
                }
                
                if (timePreview) {
                    timePreview.textContent = 'just now';
                }
            }
        }

        function checkNewMessages(conversationId) {
            if (!lastMessageId || !conversationId || conversationId !== currentConversationId) {
                return;
            }
            
            fetch(`check_new_messages.php?conversation_id=${conversationId}&last_id=${lastMessageId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Unknown error');
                    }

                    if (data.messages && data.messages.length > 0) {
                        const messagesContainer = document.getElementById('chatMessages');
                        if (!messagesContainer) {
                            return;
                        }

                        const wasAtBottom = (messagesContainer.scrollHeight - messagesContainer.scrollTop) <= (messagesContainer.clientHeight + 100);
                        
                        data.messages.forEach(message => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = `message ${message.sender_type}`;
                            messageDiv.innerHTML = formatMessage(message.message);
                            messagesContainer.appendChild(messageDiv);
                            
                            // Update last message ID
                            lastMessageId = message.id;
                        });
                        
                        // Scroll to bottom if user was already near bottom
                        if (wasAtBottom) {
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking new messages:', error);
                    // If there's an error, stop the current interval and try to restart it after 5 seconds
                    if (checkMessagesInterval) {
                        clearInterval(checkMessagesInterval);
                        checkMessagesInterval = null;
                        setTimeout(() => {
                            if (currentConversationId === conversationId) {
                                checkMessagesInterval = setInterval(() => checkNewMessages(conversationId), 3000);
                            }
                        }, 5000);
                    }
                });
        }

        // Add this function to detect and format messages with images and Markdown
        function formatMessage(message) {
            if (typeof message !== 'string') {
                return message; // Return non-strings as is
            }

            // 1. Basic HTML escaping (prevent XSS)
            let formattedMessage = message.replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // 2. Convert Markdown Bold (**text**)
            formattedMessage = formattedMessage.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // 3. Convert Markdown Italics (_text_)
            formattedMessage = formattedMessage.replace(/_([^_]+)_/g, '<em>$1</em>');

            // 4. Convert Markdown Unordered Lists (lines starting with * or -)
            formattedMessage = formattedMessage.replace(/^\*\s+/gm, '&bull; ').replace(/^-\s+/gm, '&bull; ');

            // 5. Convert Markdown Ordered Lists (lines starting with 1., 2., etc.)
            formattedMessage = formattedMessage.replace(/^(\d+)\.\s+/gm, '$1. ');
            
            // 6. Convert Markdown Links [text](url)
            formattedMessage = formattedMessage.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" style="color: #007bff; text-decoration: underline;">$1</a>');

            // 7. Convert Newlines to <br> (important after list handling)
            formattedMessage = formattedMessage.replace(/\n/g, '<br>');

            // 8. Handle Image URLs (after other formatting, so alt text isn't formatted)
            const imageUrlRegex = /(https?:\/\/[^\s<>]+\.(?:jpg|jpeg|png|gif|webp))(<br>|\s|$)/gi;
            formattedMessage = formattedMessage.replace(imageUrlRegex, (match, url, separator) => {
                // Ensure we don't match URLs inside existing HTML tags (like href)
                // This check is basic; more robust parsing might be needed for complex cases
                if (match.includes('src=') || match.includes('href=')) return match; 
                
                return `
                    <div class="my-2">
                        <img src="${url}" alt="Shared image" 
                             class="max-w-[300px] rounded-lg shadow-sm hover:shadow-md transition-shadow" 
                             onclick="window.open('${url}', '_blank')"
                             style="cursor: pointer;"
                        />
                    </div>${separator || ''}`;
            });
            
            return formattedMessage;
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.chat-preview').forEach(preview => {
                const text = preview.textContent.toLowerCase();
                preview.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Refresh button functionality
        document.getElementById('refreshBtn').addEventListener('click', function() {
            location.reload();
        });

        // Mobile search input handler
        const mobileSearchInput = document.getElementById('mobileSearchInput');
        if (mobileSearchInput) {
            mobileSearchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                document.querySelectorAll('.chat-preview').forEach(preview => {
                    const text = preview.textContent.toLowerCase();
                    preview.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
            });
        }

        async function takeOver(event) {
            event.preventDefault();
            if (!currentConversationId) return;
            
            try {
                const response = await fetch('takeover.php?id=' + currentConversationId);
                const data = await response.json();
                
                if (data.success) {
                    // Refresh the conversation to update status and buttons
                    loadConversation(currentConversationId);
                    
                    // Refresh the conversation list to update order
                    const currentContainer = document.querySelector('.custom-scrollbar');
                    const scrollTop = currentContainer ? currentContainer.scrollTop : 0;
                    
                    // Fetch updated conversation list
                    const response = await fetch(window.location.href);
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Get the new conversations container
                    const newContainer = doc.querySelector('.custom-scrollbar');
                    if (currentContainer && newContainer) {
                        currentContainer.innerHTML = newContainer.innerHTML;
                        
                        // Reapply active state to current conversation
                        const activeConv = currentContainer.querySelector(`.chat-preview[onclick*="${currentConversationId}"]`);
                        if (activeConv) {
                            activeConv.classList.add('bg-blue-50');
                        }
                    }
                } else {
                    alert('Failed to take over conversation: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error taking over conversation:', error);
                alert('An error occurred while taking over the conversation');
            }
        }

        async function letBotHandle(event) {
            event.preventDefault();
            if (!currentConversationId) return;
            
            try {
                const formData = new FormData();
                formData.append('conversation_id', currentConversationId);
                
                const response = await fetch('let_bot_handle.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    // Refresh the conversation to update status and buttons
                    loadConversation(currentConversationId);
                    
                    // Refresh the conversation list to update order
                    const currentContainer = document.querySelector('.custom-scrollbar');
                    const scrollTop = currentContainer ? currentContainer.scrollTop : 0;
                    
                    // Fetch updated conversation list
                    const response = await fetch(window.location.href);
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Get the new conversations container
                    const newContainer = doc.querySelector('.custom-scrollbar');
                    if (currentContainer && newContainer) {
                        currentContainer.innerHTML = newContainer.innerHTML;
                        
                        // Reapply active state to current conversation
                        const activeConv = currentContainer.querySelector(`.chat-preview[onclick*="${currentConversationId}"]`);
                        if (activeConv) {
                            activeConv.classList.add('bg-blue-50');
                        }
                    }
                } else {
                    alert('Failed to let bot handle conversation: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error letting bot handle conversation:', error);
                alert('An error occurred while letting bot handle the conversation');
            }
        }

        // Load More functionality
        document.getElementById('load-more-btn')?.addEventListener('click', async function() {
            const button = this;
            const nextPage = parseInt(button.dataset.page);
            button.disabled = true;
            button.textContent = 'Loading...';
            
            try {
                const response = await fetch(`dashboard.php?page=${nextPage}`);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Get new conversations
                const newConversations = doc.getElementById('conversations-list');
                if (newConversations) {
                    document.getElementById('conversations-list').insertAdjacentHTML(
                        'beforeend', 
                        newConversations.innerHTML
                    );
                }
                
                // Check if there's a next "Load More" button
                const nextLoadMoreBtn = doc.getElementById('load-more-btn');
                if (nextLoadMoreBtn) {
                    button.dataset.page = nextPage + 1;
                    button.disabled = false;
                    button.textContent = 'Load More';
                } else {
                    // No more conversations to load
                    button.remove();
                }
            } catch (error) {
                console.error('Error loading more conversations:', error);
                button.textContent = 'Error Loading More';
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Load More';
                }, 2000);
            }
        });
    </script>
</body>
</html>
<?php
function human_timing($timestamp) {
    $now = time();
    $diff = $now - $timestamp;
    
    $periods = array(
        31536000 => 'year',
        2592000 => 'month',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );
    
    foreach ($periods as $seconds => $label) {
        $count = floor($diff / $seconds);
        if ($count > 0) {
            if ($count > 1) {
                $label .= 's';
            }
            return $count . ' ' . $label . ' ago';
        }
    }
    return 'just now';
}
?> 