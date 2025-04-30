-- Create conversations table
CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(255) NOT NULL,
    `conversation_id` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_conversation_id` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create messages table
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` VARCHAR(255) NOT NULL,
    `session_id` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `sender_type` ENUM('user', 'bot', 'agent', 'system') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_conversation_id` (`conversation_id`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin_users table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO `admin_users` (`username`, `password_hash`, `email`) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@botbuilders.com')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Create chat_settings table
CREATE TABLE IF NOT EXISTS `chat_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `chat_settings` (`setting_key`, `setting_value`) VALUES
('bot_name', 'Sarla'),
('welcome_message', 'Hey there! I\'m Sarla 🌟'),
('chat_theme', 'light'),
('max_history', '100')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`; 