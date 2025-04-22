<?php
/**
 * CloudPRO - Модуль API для внешних интеграций
 */

class ApiModule extends Module {
    protected $name = 'API';
    protected $icon = 'fa-plug';
    protected $menuPosition = 60;
    
    /**
     * Конструктор модуля
     * 
     * @param Database $db Экземпляр базы данных
     */
    public function __construct(Database $db) {
        parent::__construct($db);
    }
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/api', [$this, 'actionIndex']);
        $router->get('/api/docs', [$this, 'actionDocs']);
        
        // API Routes
        $router->get('/api/v1/status', [$this, 'apiStatus']);
        $router->get('/api/v1/sites', [$this, 'apiSites']);
        $router->get('/api/v1/sites/{id}', [$this, 'apiSiteDetails']);
        $router->get('/api/v1/stats', [$this, 'apiStats']);
        $router->get('/api/v1/logs', [$this, 'apiLogs']);
        
        // API с авторизацией
        $router->post('/api/v1/auth', [$this, 'apiAuth']);
        $router->post('/api/v1/sites/create', [$this, 'apiCreateSite']);
        $router->post('/api/v1/sites/delete', [$this, 'apiDeleteSite']);
        $router->post('/api/v1/databases/create', [$this, 'apiCreateDatabase']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        // Получение количества API запросов
        $logsPath = LOG_PATH . '/api.log';
        $requestsCount = 0;
        
        if (file_exists($logsPath)) {
            $requestsCount = count(file($logsPath));
        }
        
        // Получение количества API ключей
        $apiKeys = $this->db->query("SELECT COUNT(*) as count FROM api_keys");
        $keysCount = $apiKeys[0]['count'] ?? 0;
        
        return [
            'requests_count' => $requestsCount,
            'keys_count' => $keysCount
        ];
    }
    
    /**
     * Действие: главная страница API
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Получение API ключей
        $apiKeys = $this->db->query("
            SELECT ak.*, u.username 
            FROM api_keys ak
            LEFT JOIN users u ON ak.user_id = u.id
            ORDER BY ak.created_at DESC
        ");
        
        $this->render('index', [
            'apiKeys' => $apiKeys,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: документация API
     * 
     * @return void
     */
    public function actionDocs() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $this->render('docs', [
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * API: Статус сервера
     * 
     * @return void
     */
    public function apiStatus() {
        // Базовая информация о системе
        $systemInfo = [
            'status' => 'ok',
            'version' => APP_VERSION,
            'uptime' => trim(shell_exec('uptime -p')),
            'datetime' => date('Y-m-d H:i:s'),
            'hostname' => gethostname()
        ];
        
        $this->sendJsonResponse($systemInfo);
    }
    
    /**
     * API: Список сайтов
     * 
     * @return void
     */
    public function apiSites() {
        if (!$this->checkApiAuth()) {
            return;
        }
        
        $sites = $this->db->query("SELECT id, domain, path, status FROM websites ORDER BY domain");
        $this->sendJsonResponse(['sites' => $sites]);
    }
    
    /**
     * API: Детальная информация о сайте
     * 
     * @return void
     */
    public function apiSiteDetails() {
        if (!$this->checkApiAuth()) {
            return;
        }
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$id) {
            $this->sendJsonResponse(['error' => 'Не указан ID сайта'], 400);
            return;
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            $this->sendJsonResponse(['error' => 'Сайт не найден'], 404);
            return;
        }
        
        // Дополнительные данные о сайте
        $siteStats = [
            'disk_usage' => formatFileSize(executeCommand("du -sb /var/www{$site['path']} | cut -f1")['output']),
            'files_count' => trim(executeCommand("find /var/www{$site['path']} -type f | wc -l")['output'])
        ];
        
        $site = array_merge($site, $siteStats);
        $this->sendJsonResponse(['site' => $site]);
    }
    
    /**
     * API: Статистика сервера
     * 
     * @return void
     */
    public function apiStats() {
        if (!$this->checkApiAuth()) {
            return;
        }
        
        // Базовая статистика всех модулей
        $stats = [
            'cpu' => trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'")),
            'memory' => [
                'total' => formatFileSize(intval(shell_exec("free -b | grep 'Mem:' | awk '{print $2}'"))),
                'used' => formatFileSize(intval(shell_exec("free -b | grep 'Mem:' | awk '{print $3}'"))),
                'free' => formatFileSize(intval(shell_exec("free -b | grep 'Mem:' | awk '{print $4}'"))),
            ],
            'disk' => [
                'total' => formatFileSize(disk_total_space('/')),
                'free' => formatFileSize(disk_free_space('/')),
                'used' => formatFileSize(disk_total_space('/') - disk_free_space('/'))
            ],
            'websites' => $this->db->queryScalar("SELECT COUNT(*) FROM websites"),
            'databases' => $this->db->queryScalar("SELECT COUNT(*) FROM databases"),
            'users' => $this->db->queryScalar("SELECT COUNT(*) FROM users")
        ];
        
        $this->sendJsonResponse(['stats' => $stats]);
    }
    
    /**
     * API: Лог файлы сервера
     * 
     * @return void
     */
    public function apiLogs() {
        if (!$this->checkApiAuth()) {
            return;
        }
        
        $type = isset($_GET['type']) ? $_GET['type'] : 'app';
        $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 50;
        
        // Проверка типа лога и безопасности
        $logFile = '';
        switch ($type) {
            case 'app':
                $logFile = LOG_PATH . '/app.log';
                break;
            case 'nginx':
                $logFile = '/var/log/nginx/error.log';
                break;
            case 'mysql':
                $logFile = '/var/log/mysql/error.log';
                break;
            case 'system':
                $logFile = '/var/log/syslog';
                break;
            default:
                $this->sendJsonResponse(['error' => 'Неизвестный тип лога'], 400);
                return;
        }
        
        // Проверка существования файла
        if (!file_exists($logFile) || !is_readable($logFile)) {
            $this->sendJsonResponse(['error' => 'Лог файл недоступен'], 404);
            return;
        }
        
        // Чтение последних строк лога
        $content = $this->getTailContent($logFile, $lines);
        $this->sendJsonResponse(['log' => ['type' => $type, 'lines' => $lines, 'content' => $content]]);
    }
    
    /**
     * API: Авторизация и получение токена
     * 
     * @return void
     */
    public function apiAuth() {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($username) || empty($password)) {
            $this->sendJsonResponse(['error' => 'Не указаны данные для авторизации'], 400);
            return;
        }
        
        // Авторизация
        global $auth;
        if ($auth->login($username, $password)) {
            $user = $auth->getCurrentUser();
            
            // Генерация или получение API ключа
            $apiKey = $this->getOrCreateApiKey($user['id']);
            
            $this->sendJsonResponse([
                'success' => true,
                'api_key' => $apiKey,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            $this->sendJsonResponse(['error' => 'Неверное имя пользователя или пароль'], 401);
        }
    }
    
    /**
     * API: Создание нового сайта
     * 
     * @return void
     */
    public function apiCreateSite() {
        if (!$this->checkApiAuth(true)) {
            return;
        }
        
        $domain = isset($_POST['domain']) ? $_POST['domain'] : '';
        $type = isset($_POST['type']) ? $_POST['type'] : 'php';
        
        if (empty($domain)) {
            $this->sendJsonResponse(['error' => 'Не указан домен'], 400);
            return;
        }
        
        if (!isValidDomain($domain)) {
            $this->sendJsonResponse(['error' => 'Неверный формат домена'], 400);
            return;
        }
        
        // Проверка существования домена
        $exists = $this->db->queryOne("SELECT id FROM websites WHERE domain = ?", [$domain]);
        if ($exists) {
            $this->sendJsonResponse(['error' => 'Домен уже существует'], 400);
            return;
        }
        
        // Создание сайта (часть функциональности из модуля sites)
        $path = '/'. trim(str_replace('.', '_', $domain), '/');
        
        // Создание директории
        executeCommand("mkdir -p /var/www{$path}");
        executeCommand("chown -R www-data:www-data /var/www{$path}");
        
        // Создание конфигурации nginx
        $templateFile = '';
        switch ($type) {
            case 'php':
                $templateFile = APP_PATH . '/templates/nginx_php.conf';
                break;
            case 'static':
                $templateFile = APP_PATH . '/templates/nginx_static.conf';
                break;
            default:
                $templateFile = APP_PATH . '/templates/nginx_php.conf';
        }
        
        $nginxConfig = file_get_contents($templateFile);
        $nginxConfig = str_replace('{DOMAIN}', $domain, $nginxConfig);
        $nginxConfig = str_replace('{PATH}', $path, $nginxConfig);
        
        file_put_contents('/etc/nginx/sites-available/' . $domain, $nginxConfig);
        executeCommand("ln -sf /etc/nginx/sites-available/{$domain} /etc/nginx/sites-enabled/{$domain}");
        executeCommand("systemctl reload nginx");
        
        // Запись в БД
        $stmt = $this->db->prepare("INSERT INTO websites (domain, path, type, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
        $stmt->execute([$domain, $path, $type]);
        $siteId = $this->db->lastInsertId();
        
        logMessage("Создан новый сайт: {$domain} через API", 'info');
        
        $this->sendJsonResponse([
            'success' => true,
            'site' => [
                'id' => $siteId,
                'domain' => $domain,
                'path' => $path,
                'type' => $type,
                'status' => 'active'
            ]
        ]);
    }
    
    /**
     * API: Удаление сайта
     * 
     * @return void
     */
    public function apiDeleteSite() {
        if (!$this->checkApiAuth(true)) {
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            $this->sendJsonResponse(['error' => 'Не указан ID сайта'], 400);
            return;
        }
        
        // Получение информации о сайте
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            $this->sendJsonResponse(['error' => 'Сайт не найден'], 404);
            return;
        }
        
        // Удаление конфигурации nginx
        if (file_exists('/etc/nginx/sites-enabled/' . $site['domain'])) {
            unlink('/etc/nginx/sites-enabled/' . $site['domain']);
        }
        
        if (file_exists('/etc/nginx/sites-available/' . $site['domain'])) {
            unlink('/etc/nginx/sites-available/' . $site['domain']);
        }
        
        executeCommand("systemctl reload nginx");
        
        // Удаление файлов сайта (опционально, по запросу)
        $removeFiles = isset($_POST['remove_files']) && $_POST['remove_files'] == 'true';
        if ($removeFiles && !empty($site['path'])) {
            executeCommand("rm -rf /var/www{$site['path']}");
        }
        
        // Удаление записи из БД
        $this->db->execute("DELETE FROM websites WHERE id = ?", [$id]);
        
        logMessage("Удален сайт: {$site['domain']} через API", 'info');
        
        $this->sendJsonResponse(['success' => true]);
    }
    
    /**
     * API: Создание базы данных
     * 
     * @return void
     */
    public function apiCreateDatabase() {
        if (!$this->checkApiAuth(true)) {
            return;
        }
        
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $user = isset($_POST['user']) ? $_POST['user'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($name) || empty($user) || empty($password)) {
            $this->sendJsonResponse(['error' => 'Не указаны все необходимые параметры'], 400);
            return;
        }
        
        // Проверка допустимости имени БД
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $this->sendJsonResponse(['error' => 'Недопустимое имя базы данных'], 400);
            return;
        }
        
        // Проверка существования БД
        $exists = $this->db->queryOne("SELECT id FROM databases WHERE name = ?", [$name]);
        if ($exists) {
            $this->sendJsonResponse(['error' => 'База данных уже существует'], 400);
            return;
        }
        
        // Создание БД
        $mysql = "CREATE DATABASE `{$name}`; 
                  CREATE USER '{$user}'@'localhost' IDENTIFIED BY '{$password}'; 
                  GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'localhost'; 
                  FLUSH PRIVILEGES;";
                  
        $tempFile = tempnam(sys_get_temp_dir(), 'mysql_');
        file_put_contents($tempFile, $mysql);
        
        $result = executeCommand("mysql -u root < {$tempFile}");
        unlink($tempFile);
        
        if (!$result['success']) {
            $this->sendJsonResponse(['error' => 'Ошибка при создании базы данных: ' . $result['output']], 500);
            return;
        }
        
        // Запись в БД
        $stmt = $this->db->prepare("INSERT INTO databases (name, user, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $user]);
        $dbId = $this->db->lastInsertId();
        
        logMessage("Создана новая база данных: {$name} через API", 'info');
        
        $this->sendJsonResponse([
            'success' => true,
            'database' => [
                'id' => $dbId,
                'name' => $name,
                'user' => $user
            ]
        ]);
    }
    
    /**
     * Проверка API авторизации
     * 
     * @param bool $requireAdmin Требуется ли права администратора
     * @return bool True если авторизация успешна
     */
    private function checkApiAuth($requireAdmin = false) {
        $apiKey = $this->getApiKeyFromRequest();
        
        if (!$apiKey) {
            $this->sendJsonResponse(['error' => 'Не указан API ключ'], 401);
            return false;
        }
        
        // Проверка ключа в БД
        $keyData = $this->db->queryOne("
            SELECT ak.*, u.role FROM api_keys ak
            LEFT JOIN users u ON ak.user_id = u.id
            WHERE ak.api_key = ? AND ak.status = 'active'
        ", [$apiKey]);
        
        if (!$keyData) {
            $this->sendJsonResponse(['error' => 'Неверный API ключ'], 401);
            return false;
        }
        
        // Проверка прав администратора
        if ($requireAdmin && $keyData['role'] !== 'admin') {
            $this->sendJsonResponse(['error' => 'Недостаточно прав для выполнения операции'], 403);
            return false;
        }
        
        // Обновление счетчика использования ключа
        $this->db->execute("
            UPDATE api_keys SET 
            last_used_at = NOW(),
            usage_count = usage_count + 1
            WHERE api_key = ?
        ", [$apiKey]);
        
        // Логирование API запроса
        logMessage("API запрос: {$_SERVER['REQUEST_URI']} (User: {$keyData['user_id']})", 'info', 'api.log');
        
        return true;
    }
    
    /**
     * Получение API ключа из заголовков или GET параметра
     * 
     * @return string|null API ключ или null
     */
    private function getApiKeyFromRequest() {
        // Проверка заголовка Authorization
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        
        // Проверка X-API-Key заголовка
        if (isset($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }
        
        // Проверка GET параметра
        if (isset($_GET['api_key'])) {
            return $_GET['api_key'];
        }
        
        return null;
    }
    
    /**
     * Получение существующего или создание нового API ключа
     * 
     * @param int $userId ID пользователя
     * @return string API ключ
     */
    private function getOrCreateApiKey($userId) {
        // Проверка существующего ключа
        $existingKey = $this->db->queryOne("
            SELECT api_key FROM api_keys 
            WHERE user_id = ? AND status = 'active'
            ORDER BY created_at DESC
            LIMIT 1
        ", [$userId]);
        
        if ($existingKey) {
            return $existingKey['api_key'];
        }
        
        // Генерация нового ключа
        $apiKey = bin2hex(random_bytes(16));
        
        // Сохранение ключа в БД
        $this->db->execute("
            INSERT INTO api_keys (user_id, api_key, created_at, status) 
            VALUES (?, ?, NOW(), 'active')
        ", [$userId, $apiKey]);
        
        return $apiKey;
    }
    
    /**
     * Отправка JSON ответа
     * 
     * @param array $data Данные для отправки
     * @param int $status HTTP статус код
     * @return void
     */
    private function sendJsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Получение последних строк из файла лога
     * 
     * @param string $file Путь к файлу
     * @param int $lines Количество строк
     * @return array Массив строк
     */
    private function getTailContent($file, $lines = 50) {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }
        
        if ($lines <= 0) {
            $lines = 50;
        }
        
        $result = [];
        
        // Для небольших файлов
        if (filesize($file) < 1024 * 1024 * 10) { // < 10MB
            $content = file($file);
            $result = array_slice($content, -$lines);
        } else {
            // Для больших файлов используем tail
            $command = "tail -n {$lines} " . escapeshellarg($file);
            $output = [];
            exec($command, $output);
            $result = $output;
        }
        
        return $result;
    }
} 