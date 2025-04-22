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
if (isset($_POST['username']) && isset($_POST['password'])) {
    // Попытка входа
    $username = $_POST['username'];
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

$router->get('/login', function() use ($auth, $error = null) {
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

// Маршруты для API ключей (используются из модуля API)
$router->post('/api/create_key', function() use ($auth, $db) {
    if (!$auth->isLoggedIn()) {
        echo json_encode(['error' => 'Не авторизован']); 
        exit;
    }
    
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null;
    
    $user = $auth->getCurrentUser();
    $apiKey = bin2hex(random_bytes(16));
    
    $stmt = $db->prepare("
        INSERT INTO api_keys (user_id, api_key, description, created_at, expires_at, status) 
        VALUES (?, ?, ?, NOW(), ?, 'active')
    ");
    
    $result = $stmt->execute([
        $user['id'],
        $apiKey,
        $description,
        $expiresAt
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'api_key' => $apiKey]);
    } else {
        echo json_encode(['error' => 'Ошибка при создании API ключа']);
    }
    exit;
});

$router->post('/api/revoke', function() use ($auth, $db) {
    if (!$auth->isLoggedIn()) {
        echo json_encode(['error' => 'Не авторизован']); 
        exit;
    }
    
    $keyId = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if (!$keyId) {
        echo json_encode(['error' => 'Неверный ID ключа']); 
        exit;
    }
    
    $user = $auth->getCurrentUser();
    
    // Проверяем, принадлежит ли ключ пользователю или админ
    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM api_keys WHERE id = ? AND user_id = ?");
        $stmt->execute([$keyId, $user['id']]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['error' => 'Доступ запрещен']); 
            exit;
        }
    }
    
    $stmt = $db->prepare("UPDATE api_keys SET status = 'inactive' WHERE id = ?");
    $result = $stmt->execute([$keyId]);
    
    if ($result) {
        logMessage("API ключ #$keyId отозван пользователем {$user['username']}", 'info');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Ошибка при отзыве API ключа']);
    }
    exit;
});

$router->post('/api/activate', function() use ($auth, $db) {
    if (!$auth->isLoggedIn()) {
        echo json_encode(['error' => 'Не авторизован']); 
        exit;
    }
    
    $keyId = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if (!$keyId) {
        echo json_encode(['error' => 'Неверный ID ключа']); 
        exit;
    }
    
    $user = $auth->getCurrentUser();
    
    // Проверяем, принадлежит ли ключ пользователю или админ
    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM api_keys WHERE id = ? AND user_id = ?");
        $stmt->execute([$keyId, $user['id']]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['error' => 'Доступ запрещен']); 
            exit;
        }
    }
    
    $stmt = $db->prepare("UPDATE api_keys SET status = 'active' WHERE id = ?");
    $result = $stmt->execute([$keyId]);
    
    if ($result) {
        logMessage("API ключ #$keyId активирован пользователем {$user['username']}", 'info');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Ошибка при активации API ключа']);
    }
    exit;
});

// Обработка текущего запроса
$router->dispatch(); 