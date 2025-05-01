<?php
require_once 'config.php';

// No longer creating conversation record on initial page load
// The conversation will be created in api/chat.php on first user message

// Initialize session ID if needed (for subsequent requests)
try {
    if (!isset($_SESSION['chat_session_id'])) {
        $_SESSION['chat_session_id'] = 'session_' . uniqid();
    }
} catch (Exception $e) {
     // Minimal error logging if session start fails
     error_log("Session initialization error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Basic Reset & Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            background-color: #f8f9fa;
            overflow: hidden; /* Prevent body scroll */
            color: #333;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
        }

        /* Main Chat Layout */
        .chat-layout {
            display: flex;
            flex-direction: column;
            height: 100%;
            background-color: #ffffff;
            position: relative; /* Needed for absolute positioning of scroll button */
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }

        /* Chat Header */
        .chat-header {
            padding: 15px 30px;
            border-bottom: 1px solid #e9ecef;
            background-color:rgb(238, 238, 238);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            flex-shrink: 0; /* Prevent shrinking */
            height: 70px;
            display: flex; /* Make it a flex container */
            align-items: center; /* Vertically center content */
            justify-content: space-between; /* Push title and menu apart */
            position: relative; /* Needed for absolute positioning of menu */
        }
        .chat-header h1 {
            font-size: 1.25rem; /* Equivalent to text-xl */
            color:rgb(0, 0, 0);
            margin-left: 60px; /* gray-800 */
            /* font-family: 'Montserrat', sans-serif; /* Removed font */
        }

        /* Header Menu */
        .header-menu-button {
            background: none;
            border: none;
            color: #6c757d; /* gray-500 */
            font-size: 1.25rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }
        .header-menu-button:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .header-menu {
            display: none; /* Hidden by default */
            position: absolute;
            top: 60px; /* Position below the button */
            right: 20px;
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            min-width: 150px;
            padding: 8px 0;
        }
        .header-menu.menu-open {
            display: block; /* Show when open */
        }
        .header-menu-item {
            display: block;
            background: none;
            border: none;
            width: 100%;
            padding: 10px 15px;
            text-align: left;
            font-size: 0.9rem;
            color: #333;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .header-menu-item:hover {
            background-color: #f8f9fa;
        }

        /* Chat Messages Area */
        #chat-messages {
            flex-grow: 1; /* Take available space */
            overflow-y: auto; /* Enable vertical scrolling */
            padding: 20px;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
        }

        /* Message Container & Styles */
        .message-container {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .message {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.4;
            word-wrap: break-word;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .sender-name {
            font-size: 0.8rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .message-time {
            font-size: 0.7rem;
            color: #adb5bd;
            margin-top: 5px;
            text-align: right;
        }

        /* Specific Sender Styles */
        .user-message-container {
            align-items: flex-end;
        }
        .bot-message-container,
        .agent-message-container,
        .system-message-container {
            align-items: flex-start;
        }

        .user-message {
            background-color: #B31111;
            color: white;
            border-bottom-right-radius: 5px;
        }
        .user-message .sender-name,
        .user-message .message-time {
           color: rgba(255, 255, 255, 0.8);
        }

        .bot-message {
            background-color: #ffffff;
            color: #212529;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 5px;
        }

        .agent-message {
            background-color: #28a745;
            color: white;
            border-bottom-left-radius: 5px;
        }
        .agent-message .sender-name,
        .agent-message .message-time {
           color: rgba(255, 255, 255, 0.8);
        }

        /* Hide Sender Name specifically for Bot messages */
        .bot-message .sender-name {
            display: none;
        }

        /* Hide Sender Name specifically for Agent messages */
        .agent-message .sender-name {
            display: none;
        }

        .system-message {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            font-style: italic;
            text-align: center;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
            border-radius: 8px;
        }
        .system-message-container {
             width: 100%;
             align-items: center;
        }

        /* Chat Input Area */
        .chat-input-container {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            background-color:rgb(238, 238, 238);
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.05);
            flex-shrink: 0; /* Prevent shrinking */
        }
        #chat-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        #user-input {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 20px;
            font-size: 1rem;
            background-color: #f8f9fa;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        #user-input:focus {
            outline: none;
            border-color: #A91414;
            box-shadow: 0 0 0 0.2rem rgba(98, 3, 3, 0.25);
        }
        .send-button {
            background-color: #B31111;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
            flex-shrink: 0;
        }
        .send-button:hover {
            background-color: #900B0B;
        }
        .send-button i {
            font-size: 1rem;
        }

        /* Typing Indicator */
        .loading-indicator {
            opacity: 0.8;
        }
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 0;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background-color: #6c757d;
            border-radius: 50%;
            animation: typing 1.2s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-6px); }
        }

        /* Animation */
        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scroll Down Button */
        .scroll-down-button {
            position: absolute;
            bottom: 85px; /* Adjust as needed, above input area */
            right: 25px; /* Adjust as needed */
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
            z-index: 5; /* Ensure it's above messages */
        }
        .scroll-down-button:hover {
             background-color: #f8f9fa;
        }
        .scroll-down-button.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .scroll-down-button i {
            font-size: 0.9rem;
        }

        /* Quick Replies */
        .quick-replies-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 10px 20px;
            background-color:#f8f9fa;
            border-bottom: 1px solid #e9ecef;
            justify-content: flex-end;
            align-items: center;
            transition: opacity 0.3s ease, max-height 0.3s ease, padding 0.3s ease, border 0.3s ease;
            opacity: 1;
        }
        .quick-replies-container.hidden {
            opacity: 0;
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-bottom: none;
            overflow: hidden; /* Prevent interaction when hidden */
        }
        /* Hide scrollbar */
        .quick-replies-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .quick-replies-container {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .quick-reply-button {
            flex: 0 0 auto;
            padding: 4px 8px;
            border: 1px solid #ced4da;
            border-radius: 20px;
            background-color:rgb(255, 255, 255);
            color: #333;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            margin: 0;
        }
        .quick-reply-button:hover {
            background-color:rgb(181, 53, 53);
            border-color: #adb5bd;
            color: white;
        }
        .quick-reply-button:last-child {
             margin-right: 0;
        }
    </style>
</head>
<body>
    <audio id="notificationSound" preload="auto" style="display: none;">
        <source src="admin/notification.mp3" type="audio/mpeg"> 
        Your browser does not support the audio element.
    </audio>
    <div class="chat-layout">
        <div class="chat-header">
            <h1>Sarla the Bot</h1>
            <div class="header-menu-container">
                <button class="header-menu-button" id="menu-button">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="header-menu" id="header-menu">
                    <button class="header-menu-item" id="new-chat-button">Start New Chat</button>
                </div>
            </div>
        </div>
        
        <div id="chat-messages">
            <!-- Hardcoded Initial Messages -->
            <div class="message-container bot-message-container">
                <div class="message bot-message">
                    <div>Hello! I'm Sarla, your friendly bot assistant.</div>
                </div>
            </div>
            <div class="message-container bot-message-container">
                <div class="message bot-message">
                    <div>
                        <img src="sarlawaving.png" alt="Chat Image" style="max-width: 150px; max-height: 150px; border-radius: 10px; margin-top: 5px;">
                    </div>
                </div>
            </div>
            <div class="message-container bot-message-container">
                <div class="message bot-message">
                    <div>I'm here to help with any question you have!</div>
                </div>
            </div>
            <!-- Dynamically added messages will appear below -->
        </div>
        
        <!-- Quick Replies Container -->
        <div id="quick-replies" class="quick-replies-container">
            <button class="quick-reply-button">🎯 Get a Quote</button>
            <button class="quick-reply-button">✨ Backdrops & Banners</button>
            <button class="quick-reply-button">🎨 Artwork & Templates</button>
            <button class="quick-reply-button">🚚 Shipping & Installation</button>
            <button class="quick-reply-button">⚡ Rush Turnaround</button>
            <button class="quick-reply-button">📍 LA Rentals</button>
            <!-- Add more buttons as needed -->
        </div>
        
        <div class="chat-input-container">
            <form id="chat-form">
                <input type="text" id="user-input" placeholder="Ask Anything">
                <button type="submit" class="send-button">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>

        <!-- Scroll Down Button -->
        <button id="scroll-down-button" class="scroll-down-button" title="Scroll to bottom">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>

    <script>
        const chatForm = document.getElementById('chat-form');
        const userInput = document.getElementById('user-input');
        const chatMessages = document.getElementById('chat-messages');
        const menuButton = document.getElementById('menu-button');
        const headerMenu = document.getElementById('header-menu');
        const newChatButton = document.getElementById('new-chat-button');
        const notificationSound = document.getElementById('notificationSound');
        const scrollDownButton = document.getElementById('scroll-down-button');
        const quickRepliesContainer = document.getElementById('quick-replies');

        let lastMessageId = 0;
        let isTyping = false;
        let messageQueue = [];
        let pollInterval;
        
        // Initialize session ID with better persistence
        let sessionId = localStorage.getItem('chat_session_id');
        if (!sessionId) {
            sessionId = 'session_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('chat_session_id', sessionId);
        }
        
        // --- Menu Logic --- 
        menuButton.addEventListener('click', (event) => {
            event.stopPropagation(); // Prevent click from closing menu immediately
            headerMenu.classList.toggle('menu-open');
        });

        newChatButton.addEventListener('click', () => {
            const confirmation = window.confirm("Are you sure you want to start a new chat? All current conversation history will be lost.");
            if (confirmation) {
                // Clear session data (no history flag needed anymore)
                localStorage.removeItem('chat_session_id');
                localStorage.removeItem('chatbase_conversation_id');
                
                location.reload();
            }
            headerMenu.classList.remove('menu-open');
        });

        // Close menu if clicking outside
        document.addEventListener('click', (event) => {
            if (!headerMenu.contains(event.target) && !menuButton.contains(event.target)) {
                headerMenu.classList.remove('menu-open');
            }
        });

        // Add loading indicator
        function showLoading() {
            const existingLoaders = chatMessages.querySelectorAll('.bot-message-container.loading-indicator');
            existingLoaders.forEach(loader => loader.remove());

            const container = document.createElement('div');
            container.className = 'message-container bot-message-container loading-indicator';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message bot-message';
            
            const typingDiv = document.createElement('div');
            typingDiv.className = 'typing-indicator';
            typingDiv.innerHTML = '<span></span><span></span><span></span>';
            
            messageDiv.appendChild(typingDiv);
            container.appendChild(messageDiv);
            chatMessages.appendChild(container);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return container;
        }
        
        // Remove loading indicator
        function removeLoading(loadingDiv) {
            if (loadingDiv && loadingDiv.parentNode) {
                loadingDiv.parentNode.removeChild(loadingDiv);
            }
        }
        
        // Function to add the initial bot messages sequentially with delay
        async function displayInitialWelcome() {
            const welcomeMessages = [
                "Hello! I'm Sarla, your friendly bot assistant.",
                window.location.origin + "/sarlawaving.png",
                "I'm here to help with any question you have!"
            ];
            const delay = 1200; // Delay in milliseconds 

            // Only proceed if chat is currently empty (prevent overlap if history loads fast)
            if (chatMessages.children.length > 0) return;

            for (const msg of welcomeMessages) {
                let loadingDiv = showLoading();
                await new Promise(resolve => setTimeout(resolve, delay));
                removeLoading(loadingDiv);
                // Check again if history loaded while waiting
                if (chatMessages.children.length > 0 && !chatMessages.querySelector('.loading-indicator')) return; 
                addMessage(msg, 'bot', false); 
                await new Promise(resolve => setTimeout(resolve, 200)); 
            }
             // Scroll down after welcome messages if they were actually added
             if (chatMessages.children.length <= welcomeMessages.length) {
                 chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
             }
        }

        // Load *actual* history from DB, overwriting welcome if history exists
        async function loadAndDisplayHistory() {
            try {
                const response = await fetch('api/load_messages.php?session_id=' + sessionId);
                const data = await response.json();
                
                if (data.error) {
                     console.error('Error loading history:', data.error);
                     return false; 
                }
                
                if (data.messages && data.messages.length > 0) {
                    chatMessages.innerHTML = ''; 
                    data.messages.forEach(msg => {
                        addMessage(msg.message, msg.sender_type, false);
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    quickRepliesContainer.classList.add('hidden');
                    return true; 
                }
                return false;
            } catch (error) {
                console.error('Error fetching history:', error);
                return false; 
            }
        }
        
        // Start polling for new messages
        function startPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
            checkNewMessages();
            pollInterval = setInterval(checkNewMessages, 2000);
        }
        
        // Check for new messages (Simplified Notification)
        async function checkNewMessages() {
            if (isTyping) return;

            try {
                const response = await fetch(`api/check_new_messages.php?session_id=${sessionId}&last_id=${lastMessageId}`);
                if (!response.ok) {
                     console.error(`Error checking messages: ${response.status} ${response.statusText}`);
                     return;
                 }
                const data = await response.json();

                if (data.error) {
                     console.error('Error checking new messages:', data.error);
                     return;
                 }
                
                if (data.messages && data.messages.length > 0) {
                    const newLastMessageId = Math.max(...data.messages.map(msg => msg.id));
                    const newMessages = data.messages.filter(msg => msg.id > lastMessageId);
                    
                    if (newMessages.length > 0) {
                        const isScrolledNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 100;
                        let agentMessageReceived = false; // Still useful to play only once per batch

                        newMessages.forEach(msg => {
                            addMessage(msg.message, msg.sender_type);
                            if (msg.sender_type === 'agent') {
                                agentMessageReceived = true; 
                            }
                        });

                        lastMessageId = newLastMessageId;
                        if (isScrolledNearBottom) {
                            chatMessages.scrollTo({
                                top: chatMessages.scrollHeight,
                                behavior: 'smooth'
                            });
                        }
                        
                        // Play sound if an agent message was in the batch (no visibility check)
                        if (agentMessageReceived) {
                            playNotificationSound();
                        }
                    }
                }
            } catch (error) {
                console.error('Error processing new messages check:', error);
            }
        }
        
        // Helper function to convert simple Markdown to HTML
        function formatMessageContent(text) {
            if (typeof text !== 'string') {
                return text; // Return non-strings as is
            }

            // 1. Basic HTML escaping (prevent XSS)
            let escapedText = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // 2. Convert Markdown Bold (**text**)
            escapedText = escapedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // 3. Convert Markdown Italics (_text_)
            escapedText = escapedText.replace(/_([^_]+)_/g, '<em>$1</em>');

            // 4. Convert Markdown Unordered Lists (lines starting with * or -)
            // Note: This is a simplified conversion, doesn't create proper <ul>
            escapedText = escapedText.replace(/^\*\s+/gm, '&bull; ').replace(/^-\s+/gm, '&bull; ');

            // 5. Convert Markdown Ordered Lists (lines starting with 1., 2., etc.)
            // Note: This is a simplified conversion, doesn't create proper <ol>
            escapedText = escapedText.replace(/^(\d+)\.\s+/gm, '$1. ');

            // 6. Convert Markdown Links [text](url)
            escapedText = escapedText.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" style="color: #007bff; text-decoration: underline;">$1</a>');

            // 7. Convert Newlines to <br> (important after list handling)
            escapedText = escapedText.replace(/\n/g, '<br>');

            return escapedText;
        }
        
        // Enhanced message display function
        function addMessage(message, sender, animate = true) {
            const messageContainer = document.createElement('div');
            messageContainer.className = `message-container ${sender}-message-container`;
            if (animate) {
                messageContainer.classList.add('fade-in');
            }

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}-message`;

            // Only add sender name for agent/system messages (exclude user and bot)
            if (sender !== 'user' && sender !== 'bot') {
                const senderName = document.createElement('div');
                senderName.className = 'sender-name';
                senderName.textContent = sender === 'agent' ? 'Support Agent' : 'System'; 
                messageDiv.appendChild(senderName);
            }

            const messageContent = document.createElement('div');
            // Check if message is an image URL
            const imageUrlRegex = /.(jpeg|jpg|gif|png|webp)$/i;
            const urlRegex = /^(http|https):\/\//i; // General URL check

            if (typeof message === 'string' && urlRegex.test(message) && imageUrlRegex.test(message)) {
                // Handle image rendering
                const img = document.createElement('img');
                img.src = message;
                img.alt = "Chat Image";
                img.style.maxWidth = '250px'; 
                img.style.maxHeight = '250px';
                img.style.borderRadius = '10px';
                img.style.marginTop = '5px';
                img.onerror = () => { 
                    messageContent.innerHTML = formatMessageContent("(Image failed to load: " + message + ")");
                    img.remove();
                };
                messageContent.appendChild(img);
            } else {
                // Apply Markdown formatting and set innerHTML
                messageContent.innerHTML = formatMessageContent(message);
            }
            messageDiv.appendChild(messageContent);

            messageContainer.appendChild(messageDiv);
            chatMessages.appendChild(messageContainer);

            if (animate) {
                 chatMessages.scrollTo({
                    top: chatMessages.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }
        
        // Handle message sending with queue
        async function sendMessage(message) {
            // Hide quick replies as soon as user interacts
            quickRepliesContainer.classList.add('hidden');

            if (isTyping) {
                messageQueue.push(message);
                return;
            }
            
            isTyping = true;
            addMessage(message, 'user');
            userInput.value = ''; // Clear input only if it was used (quick reply won't fill it)
            userInput.focus();
            
            let loadingDiv = showLoading(); 
            
            let conversationId = localStorage.getItem('chatbase_conversation_id') || sessionId;
            if (!localStorage.getItem('chatbase_conversation_id')) {
                localStorage.setItem('chatbase_conversation_id', conversationId);
            }

            try {
                const response = await fetch('api/chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message: message,
                        session_id: sessionId, 
                        conversation_id: conversationId 
                    })
                });
                
                const data = await response.json();
                removeLoading(loadingDiv);
                
                if (data.success) {
                    if (data.message_id) {
                        lastMessageId = Math.max(lastMessageId, data.message_id);
                    }
                } else {
                    console.error('Server error:', data.error);
                    addMessage(`Sorry, there was an error: ${data.error || 'Unknown issue'}. Please try again.`, 'system');
                }
            } catch (error) {
                console.error('Network error:', error);
                removeLoading(loadingDiv);
                addMessage('Sorry, there was a network error. Please check your connection and try again.', 'system');
            } finally {
                isTyping = false;
                
                if (messageQueue.length > 0) {
                    const nextMessage = messageQueue.shift();
                    sendMessage(nextMessage);
                }
            }
        }
        
        // Form submission
        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const message = userInput.value.trim();
            if (message) {
                sendMessage(message);
            }
        });
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                startPolling();
            } else {
                if (pollInterval) {
                    clearInterval(pollInterval);
                }
            }
        });
        
        // --- Notification Sound --- 
        function playNotificationSound() {
            if (!notificationSound) {
                console.error('Notification sound element not found');
                return;
            }
            // Attempt to play sound
            notificationSound.play().catch(error => {
                console.warn('Notification sound playback prevented:', error);
            });
        }

        // --- Initial Chat Initialization --- 
        async function initializeChat() {
            const historyLoaded = await loadAndDisplayHistory();
            
            if (!historyLoaded) {
                await displayInitialWelcome();
            }
            
            startPolling(); 
        }

        // --- Scroll Down Button Logic ---
        chatMessages.addEventListener('scroll', () => {
            const threshold = 200; // How many pixels from bottom to hide button
            const isNearBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < threshold;
            
            if (isNearBottom) {
                scrollDownButton.classList.remove('visible');
            } else {
                // Show only if there's enough content to scroll
                if (chatMessages.scrollHeight > chatMessages.clientHeight + threshold) {
                    scrollDownButton.classList.add('visible');
                }
            }
        });

        scrollDownButton.addEventListener('click', () => {
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: 'smooth'
            });
        });
        
        // --- Quick Replies Logic ---
        quickRepliesContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('quick-reply-button')) {
                const messageText = event.target.textContent.trim();
                if (messageText) {
                    sendMessage(messageText);
                    // Optionally hide immediately on click, though sendMessage does it too
                    // quickRepliesContainer.classList.add('hidden'); 
                }
            }
        });

        // Start the initialization process
        initializeChat();
    </script>
</body>
</html> 