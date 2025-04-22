<?php
/**
 * CloudPRO - Вспомогательные функции
 */

/**
 * Перенаправление на другую страницу
 * 
 * @param string $path Путь для перенаправления
 * @return void
 */
function redirect($path) {
    header('Location: /' . ltrim($path, '/'));
    exit;
}

/**
 * Безопасный вывод текста с экранированием HTML
 * 
 * @param string $text Текст для вывода
 * @return string Экранированный текст
 */
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Генерация случайной строки
 * 
 * @param int $length Длина строки
 * @return string Случайная строка
 */
function randomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $string;
}

/**
 * Проверка доступности порта
 * 
 * @param int $port Номер порта
 * @return bool True если порт свободен
 */
function isPortAvailable($port) {
    $connection = @fsockopen('127.0.0.1', $port);
    
    if (is_resource($connection)) {
        fclose($connection);
        return false;
    }
    
    return true;
}

/**
 * Получение размера файла в человекочитаемом формате
 * 
 * @param int $bytes Размер в байтах
 * @return string Форматированный размер
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' ГБ';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' МБ';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' КБ';
    } else {
        return $bytes . ' Б';
    }
}

/**
 * Запись в лог-файл
 * 
 * @param string $message Сообщение для записи
 * @param string $level Уровень сообщения (info, warning, error)
 * @return bool Успешность записи
 */
function logMessage($message, $level = 'info') {
    $logFile = LOG_PATH . '/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp][$level] $message" . PHP_EOL;
    
    // Создаем директорию для лога, если она не существует
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    return file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Выполнение системной команды с логированием
 * 
 * @param string $command Команда для выполнения
 * @return array Массив с результатом и статусом выполнения
 */
function executeCommand($command) {
    $output = [];
    $returnVar = 0;
    
    exec($command . ' 2>&1', $output, $returnVar);
    
    $result = [
        'success' => ($returnVar === 0),
        'output' => implode("\n", $output),
        'command' => $command
    ];
    
    // Логируем выполнение команды
    if ($result['success']) {
        logMessage("Команда выполнена успешно: $command", 'info');
    } else {
        logMessage("Ошибка выполнения команды: $command. Вывод: " . $result['output'], 'error');
    }
    
    return $result;
}

/**
 * Проверка валидности домена
 * 
 * @param string $domain Доменное имя
 * @return bool True если домен валидный
 */
function isValidDomain($domain) {
    return (preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain) === 1);
}

/**
 * Получение текущего URL
 * 
 * @return string Текущий URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Активен ли текущий пункт меню
 * 
 * @param string $path Путь для проверки
 * @return bool True если путь активен
 */
function isActiveMenu($path) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $currentPath === '/' . ltrim($path, '/');
}

/**
 * Проверка директории на возможность записи
 * 
 * @param string $path Путь к директории
 * @return bool True если директория доступна для записи
 */
function isWritableDirectory($path) {
    return is_dir($path) && is_writable($path);
} 