<?php
/**
 * CloudPRO - Панель управления VPS
 * Основной входной файл
 */

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
$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);

// Инициализация аутентификации
$auth = new Auth($db);

// Инициализация маршрутизатора
$router = new Router();

// Обработка аутентификации
if (isset($_POST['login']) && isset($_POST['password'])) {
    // Попытка входа
    $username = $_POST['login'];
    $password = $_POST['password'];
    
    if ($auth->login($username, $password)) {
        // Успешный вход
        redirect('dashboard');
    } else {
        // Ошибка входа
        $error = 'Неверный логин или пароль';
    }
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
    redirect('login');
}

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

// Маршруты по умолчанию
$router->get('/', function() use ($auth) {
    if ($auth->isLoggedIn()) {
        redirect('dashboard');
    } else {
        redirect('login');
    }
});

$router->get('/login', function() use ($auth) {
    if ($auth->isLoggedIn()) {
        redirect('dashboard');
    }
    
    $template = new Template('login');
    $template->render([
        'error' => isset($error) ? $error : null
    ]);
});

$router->get('/dashboard', function() use ($auth, $modules) {
    if (!$auth->isLoggedIn()) {
        redirect('login');
    }
    
    $user = $auth->getCurrentUser();
    $moduleStats = [];
    
    foreach ($modules as $moduleId => $module) {
        if (method_exists($module, 'getStats')) {
            $moduleStats[$moduleId] = $module->getStats();
        }
    }
    
    $template = new Template('dashboard');
    $template->render([
        'user' => $user,
        'stats' => $moduleStats,
        'modules' => $modules
    ]);
});

// Обработка текущего запроса
$router->dispatch(); 