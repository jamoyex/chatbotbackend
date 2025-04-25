# BotBuilders Chatbot Backend

A PHP-based chatbot backend system with admin panel integration.

## Features

- Real-time chat interface
- Admin dashboard for chat management
- Secure authentication system
- Modern, responsive UI
- Session management
- Message history tracking

## Tech Stack

- PHP
- MySQL
- HTML/CSS
- JavaScript
- Font Awesome for icons
- Modern responsive design

## Installation

1. Clone the repository:
```bash
git clone https://github.com/jamoyex/chatbotbackend.git
```

2. Configure your web server to point to the `public_html` directory

3. Set up the database:
```bash
mysql -u your_username -p < database.sql
```

4. Update the database configuration in `config.php`:
```php
define('DB_HOST', 'your_host');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'botbuilders_db');
```

5. Ensure proper permissions are set for file uploads and logs

## Default Admin Credentials

After installation, you can log in to the admin panel with:
- Username: admin
- Password: admin123

**Important:** Please change these credentials immediately after first login.

## Security

- Implements secure session management
- Password hashing for admin authentication
- Input sanitization and validation
- XSS protection measures

## Database Schema

The application uses the following tables:
- `conversations`: Stores chat sessions
- `messages`: Stores all chat messages
- `admin_users`: Manages admin authentication
- `chat_settings`: Stores configurable chat settings

## License

This project is proprietary software. All rights reserved.

## Author

BotBuilders Team 