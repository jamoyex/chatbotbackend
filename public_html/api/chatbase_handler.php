<?php
require_once '../config.php';

class ChatbaseHandler {
    private $api_key;
    private $chatbot_id;
    private $base_url = 'https://www.chatbase.co/api/v1/chat';

    public function __construct() {
        $this->api_key = CHATBASE_API_KEY;
        $this->chatbot_id = CHATBASE_BOT_ID;
    }

    public function sendMessage($messages_history, $conversation_id) {
        $data = [
            'chatbotId' => $this->chatbot_id,
            'messages' => $messages_history,
            'conversationId' => $conversation_id
        ];

        error_log("Chatbase Request Data: " . json_encode($data));

        $ch = curl_init($this->base_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Chatbase Response Code: " . $http_code);
        error_log("Chatbase Raw Response: " . $response);
        
        if ($curl_error = curl_error($ch)) {
            error_log("Curl Error: " . $curl_error);
        }
        
        curl_close($ch);

        if ($http_code !== 200) {
            error_log("Chatbase API error: " . $response);
            return [
                'success' => false,
                'error' => 'Failed to send message to Chatbase'
            ];
        }

        $decoded_response = json_decode($response, true);
        error_log("Chatbase Decoded Response: " . json_encode($decoded_response));

        return [
            'success' => true,
            'response' => $decoded_response
        ];
    }
}
?> 