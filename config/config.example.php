<?php
// CloudPRO Configuration Example
// Переименуйте этот файл в config.php и настройте параметры

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'cloudpro');
define('DB_USER', 'cloudpro_user');
define('DB_PASS', 'YourSecurePassword'); // Измените на ваш пароль

// Application settings
define('APP_URL', 'http://localhost');
define('APP_PATH', dirname(__DIR__));
define('LOG_PATH', APP_PATH . '/logs');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SALT', 'YourRandomSaltString'); // Замените на случайную строку
define('APP_VERSION', '1.0.0'); 