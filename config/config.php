<?php
// CloudPRO Configuration

// Генерация случайного пароля от 8 до 15 символов
function generateRandomPassword($min = 8, $max = 15) {
    $length = rand($min, $max);
    return bin2hex(random_bytes(ceil($length/2))); // Преобразуем в hex для получения только букв и цифр
}

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'cloudpro');
define('DB_USER', 'cloudpro_user');
define('DB_PASS', generateRandomPassword()); // Генерируем пароль

// Application settings
define('APP_URL', 'http://' . ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
define('APP_PATH', dirname(__DIR__));
define('LOG_PATH', APP_PATH . '/logs');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SALT', bin2hex(random_bytes(16))); // Генерируем случайный SALT
define('APP_VERSION', '1.0.0');

// Выводим информацию о сгенерированных данных только при первом запуске
if (!file_exists(LOG_PATH . '/setup_completed.txt')) {
    echo "<h2>Конфигурация CloudPRO</h2>";
    echo "<p>Сгенерированы следующие параметры для подключения к БД:</p>";
    echo "<ul>";
    echo "<li>База данных: " . DB_NAME . "</li>";
    echo "<li>Пользователь: " . DB_USER . "</li>";
    echo "<li>Пароль: " . DB_PASS . "</li>";
    echo "</ul>";
    echo "<p>Пожалуйста, сохраните эти данные в надежном месте!</p>";
    
    // Создаем файл-метку о завершении установки
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    file_put_contents(LOG_PATH . '/setup_completed.txt', date('Y-m-d H:i:s'));
    
    echo "<p>Для продолжения <a href='/'>перезагрузите страницу</a>.</p>";
    exit;
} 