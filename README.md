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
   - Create a MySQL database through your hosting control panel
   - Import the database schema:
     ```bash
     mysql -u your_username -p your_database_name < database.sql
     ```
   - Or use your hosting provider's database management tool (like phpMyAdmin) to import the `database.sql` file

4. Configure the application:
   - Copy `public_html/exampleconfig.php` to `public_html/config.php`
   - Update the following in `config.php`:
     ```php
     // Database configuration
     define('DB_HOST', 'your_database_host');
     define('DB_USER', 'your_database_username');
     define('DB_PASS', 'your_database_password');
     define('DB_NAME', 'your_database_name');

     // Chatbase configuration
     define('CHATBASE_API_KEY', 'your_chatbase_api_key');
     define('CHATBASE_BOT_ID', 'your_chatbase_bot_id');

     // Admin credentials
     define('ADMIN_CREDENTIALS', [
         'admin' => 'your_secure_password',
         // Add more admin users as needed
     ]);
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

## Configuration

The application requires a `config.php` file in the `public_html` directory. An example configuration file (`exampleconfig.php`) is provided. Make sure to:

1. Copy `exampleconfig.php` to `config.php`
2. Update all placeholder values with your actual credentials
3. Never commit your actual `config.php` to version control
4. Keep your `config.php` file secure and backed up

## License

This project is proprietary software. All rights reserved.

## Author

BotBuilders Team 