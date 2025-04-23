<?php
/**
 * CloudPRO - Загрузчик приложения
 * Этот файл инициализирует основные компоненты системы
 */

// Настройка отображения ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверка наличия установленной конфигурации
if (!file_exists(__DIR__ . '/config/config.php')) {
    die('Ошибка: Файл конфигурации не найден. Пожалуйста, запустите установщик.');
}

// Загрузка конфигурации
require_once __DIR__ . '/config/config.php';

// Загрузка базовых классов и функций
require_once __DIR__ . '/core/autoload.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Module.php';
require_once __DIR__ . '/core/Template.php';

// Инициализация сессии
session_start();

// Создание экземпляра базы данных
try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
} catch (Exception $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Проверка наличия необходимых таблиц
$requiredTables = ['users', 'websites', 'db_list', 'api_keys'];
$missingTables = [];

foreach ($requiredTables as $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if (empty($result)) {
            $missingTables[] = $table;
        }
    } catch (Exception $e) {
        die('Ошибка при проверке таблиц: ' . $e->getMessage());
    }
}

if (!empty($missingTables)) {
    die('В базе данных отсутствуют необходимые таблицы: ' . implode(', ', $missingTables) . 
        '. Запустите скрипт ./install.sh с параметром "repair", чтобы восстановить таблицы.');
}

// Инициализация аутентификации
$auth = new Auth($db);

// Инициализация маршрутизатора
$router = new Router();

// Загрузка всех модулей
$modules = [];
$modulesPath = __DIR__ . '/modules/';
$moduleDirectories = scandir($modulesPath);

foreach ($moduleDirectories as $moduleDir) {
    if ($moduleDir != '.' && $moduleDir != '..' && is_dir($modulesPath . $moduleDir)) {
        $moduleFile = $modulesPath . $moduleDir . '/module.php';
        if (file_exists($moduleFile)) {
            require_once $moduleFile;
            $className = ucfirst($moduleDir) . 'Module';
            if (class_exists($className)) {
                $modules[$moduleDir] = new $className($db);
                // Регистрация маршрутов модуля
                $modules[$moduleDir]->registerRoutes($router);
            }
        }
    }
}

// Добавление базовых маршрутов
require_once __DIR__ . '/routes.php';