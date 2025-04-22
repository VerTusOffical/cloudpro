<?php
/**
 * CloudPRO - Модуль управления сайтами
 */

class SitesModule extends Module {
    protected $name = 'Сайты';
    protected $icon = 'fa-globe';
    protected $menuPosition = 10;
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/sites', [$this, 'actionIndex']);
        $router->get('/sites/create', [$this, 'actionCreate']);
        $router->post('/sites/create', [$this, 'actionStore']);
        $router->get('/sites/edit/:id', [$this, 'actionEdit']);
        $router->post('/sites/edit/:id', [$this, 'actionUpdate']);
        $router->get('/sites/delete/:id', [$this, 'actionDelete']);
        $router->post('/sites/delete/:id', [$this, 'actionDestroy']);
        $router->get('/sites/:id/ssl', [$this, 'actionSsl']);
        $router->post('/sites/:id/ssl', [$this, 'actionSslSetup']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $totalSites = $this->db->queryScalar("SELECT COUNT(*) FROM websites");
        $sslSites = $this->db->queryScalar("SELECT COUNT(*) FROM websites WHERE ssl_enabled = 1");
        
        return [
            'total' => $totalSites,
            'ssl_enabled' => $sslSites,
            'percent_ssl' => ($totalSites > 0) ? round(($sslSites / $totalSites) * 100) : 0
        ];
    }
    
    /**
     * Действие: список сайтов
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $sites = $this->db->query("SELECT * FROM websites ORDER BY domain");
        
        $this->render('index', [
            'sites' => $sites,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: форма создания сайта
     * 
     * @return void
     */
    public function actionCreate() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
        
        $this->render('create', [
            'phpVersions' => $phpVersions,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: сохранение нового сайта
     * 
     * @return void
     */
    public function actionStore() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $domain = $_POST['domain'] ?? '';
        $path = $_POST['path'] ?? '';
        $phpVersion = $_POST['php_version'] ?? '8.1';
        
        // Валидация
        $errors = [];
        
        if (empty($domain)) {
            $errors[] = 'Домен не может быть пустым';
        } elseif (!isValidDomain($domain)) {
            $errors[] = 'Некорректное доменное имя';
        }
        
        if (empty($path)) {
            $errors[] = 'Путь к директории не может быть пустым';
        }
        
        // Проверка наличия сайта с таким доменом
        $existingSite = $this->db->queryOne("SELECT id FROM websites WHERE domain = ?", [$domain]);
        if ($existingSite) {
            $errors[] = 'Сайт с таким доменом уже существует';
        }
        
        if (!empty($errors)) {
            $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
            
            $this->render('create', [
                'errors' => $errors,
                'domain' => $domain,
                'path' => $path,
                'phpVersion' => $phpVersion,
                'phpVersions' => $phpVersions,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Нормализация пути
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }
        
        // Создание директории для сайта
        $siteDir = '/var/www' . $path;
        $result = executeCommand("mkdir -p " . escapeshellarg($siteDir));
        
        if (!$result['success']) {
            $errors[] = 'Ошибка создания директории: ' . $result['output'];
            
            $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
            
            $this->render('create', [
                'errors' => $errors,
                'domain' => $domain,
                'path' => $path,
                'phpVersion' => $phpVersion,
                'phpVersions' => $phpVersions,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Установка прав
        executeCommand("chown -R www-data:www-data " . escapeshellarg($siteDir));
        executeCommand("chmod -R 755 " . escapeshellarg($siteDir));
        
        // Создание тестового index.html
        $indexContent = "<html><head><title>Welcome to $domain</title></head><body><h1>Welcome to $domain</h1><p>This is a default page created by CloudPRO.</p></body></html>";
        file_put_contents($siteDir . '/index.html', $indexContent);
        
        // Создание конфигурации Nginx
        $nginxConfig = "server {
    listen 80;
    server_name $domain www.$domain;
    
    root $siteDir;
    index index.php index.html;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php$phpVersion-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}";
        
        $configPath = "/etc/nginx/sites-available/$domain";
        file_put_contents($configPath, $nginxConfig);
        
        // Активация конфигурации
        executeCommand("ln -sf " . escapeshellarg($configPath) . " /etc/nginx/sites-enabled/");
        executeCommand("nginx -t && systemctl reload nginx");
        
        // Сохранение в базе данных
        $this->db->execute(
            "INSERT INTO websites (domain, path, php_version) VALUES (?, ?, ?)",
            [$domain, $path, $phpVersion]
        );
        
        // Запись в лог
        logMessage("Создан новый сайт: $domain с путем $path", 'info');
        
        // Перенаправление на список сайтов
        redirect('sites');
    }
    
    /**
     * Действие: форма редактирования сайта
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionEdit($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
        
        $this->render('edit', [
            'site' => $site,
            'phpVersions' => $phpVersions,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: обновление сайта
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionUpdate($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        $domain = $_POST['domain'] ?? '';
        $path = $_POST['path'] ?? '';
        $phpVersion = $_POST['php_version'] ?? '8.1';
        
        // Валидация
        $errors = [];
        
        if (empty($domain)) {
            $errors[] = 'Домен не может быть пустым';
        } elseif (!isValidDomain($domain)) {
            $errors[] = 'Некорректное доменное имя';
        }
        
        if (empty($path)) {
            $errors[] = 'Путь к директории не может быть пустым';
        }
        
        // Проверка наличия сайта с таким доменом
        if ($domain != $site['domain']) {
            $existingSite = $this->db->queryOne("SELECT id FROM websites WHERE domain = ? AND id != ?", [$domain, $id]);
            if ($existingSite) {
                $errors[] = 'Сайт с таким доменом уже существует';
            }
        }
        
        if (!empty($errors)) {
            $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
            
            $this->render('edit', [
                'errors' => $errors,
                'site' => array_merge($site, [
                    'domain' => $domain,
                    'path' => $path,
                    'php_version' => $phpVersion
                ]),
                'phpVersions' => $phpVersions,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Нормализация пути
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }
        
        // Обновление директории для сайта
        $oldSiteDir = '/var/www' . $site['path'];
        $newSiteDir = '/var/www' . $path;
        
        // Если изменился путь
        if ($site['path'] != $path) {
            // Создаем новую директорию, если она не существует
            if (!is_dir($newSiteDir)) {
                $result = executeCommand("mkdir -p " . escapeshellarg($newSiteDir));
                
                if (!$result['success']) {
                    $errors[] = 'Ошибка создания директории: ' . $result['output'];
                    
                    $phpVersions = ['7.4', '8.0', '8.1', '8.2'];
                    
                    $this->render('edit', [
                        'errors' => $errors,
                        'site' => array_merge($site, [
                            'domain' => $domain,
                            'path' => $path,
                            'php_version' => $phpVersion
                        ]),
                        'phpVersions' => $phpVersions,
                        'user' => $auth->getCurrentUser()
                    ]);
                    return;
                }
            }
            
            // Копируем содержимое старой директории в новую
            executeCommand("cp -r " . escapeshellarg($oldSiteDir) . "/* " . escapeshellarg($newSiteDir) . "/ 2>/dev/null || true");
            
            // Удаляем старую директорию если она пуста
            executeCommand("rmdir " . escapeshellarg($oldSiteDir) . " 2>/dev/null || true");
        }
        
        // Установка прав
        executeCommand("chown -R www-data:www-data " . escapeshellarg($newSiteDir));
        executeCommand("chmod -R 755 " . escapeshellarg($newSiteDir));
        
        // Обновление конфигурации Nginx
        $nginxConfig = "server {
    listen 80;
    server_name $domain www.$domain;
    
    root $newSiteDir;
    index index.php index.html;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php$phpVersion-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}";
        
        // Удаление старого конфига
        if ($site['domain'] != $domain) {
            executeCommand("rm -f /etc/nginx/sites-available/{$site['domain']}");
            executeCommand("rm -f /etc/nginx/sites-enabled/{$site['domain']}");
        }
        
        // Создание нового конфига
        $configPath = "/etc/nginx/sites-available/$domain";
        file_put_contents($configPath, $nginxConfig);
        
        // Активация конфигурации
        executeCommand("ln -sf " . escapeshellarg($configPath) . " /etc/nginx/sites-enabled/");
        executeCommand("nginx -t && systemctl reload nginx");
        
        // Обновление SSL, если он был включен
        if ($site['ssl_enabled']) {
            // Удаляем старый сертификат
            executeCommand("certbot delete --cert-name {$site['domain']} -n");
            
            // Запрашиваем новый сертификат
            $sslResult = executeCommand("certbot --nginx -d $domain -d www.$domain --non-interactive --agree-tos --register-unsafely-without-email");
            
            if (!$sslResult['success']) {
                logMessage("Ошибка при обновлении SSL для домена $domain: " . $sslResult['output'], 'error');
            }
        }
        
        // Обновление в базе данных
        $this->db->execute(
            "UPDATE websites SET domain = ?, path = ?, php_version = ? WHERE id = ?",
            [$domain, $path, $phpVersion, $id]
        );
        
        // Запись в лог
        logMessage("Обновлен сайт ID $id: $domain с путем $path", 'info');
        
        // Перенаправление на список сайтов
        redirect('sites');
    }
    
    /**
     * Действие: форма удаления сайта
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionDelete($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        $this->render('delete', [
            'site' => $site,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: удаление сайта
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionDestroy($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        // Удаление файлов сайта, если установлен флаг
        if (isset($_POST['delete_files']) && $_POST['delete_files'] == 1) {
            $siteDir = '/var/www' . $site['path'];
            executeCommand("rm -rf " . escapeshellarg($siteDir));
        }
        
        // Удаление конфигурации Nginx
        executeCommand("rm -f /etc/nginx/sites-available/{$site['domain']}");
        executeCommand("rm -f /etc/nginx/sites-enabled/{$site['domain']}");
        executeCommand("nginx -t && systemctl reload nginx");
        
        // Удаление SSL-сертификата, если он был включен
        if ($site['ssl_enabled']) {
            executeCommand("certbot delete --cert-name {$site['domain']} -n");
        }
        
        // Удаление из базы данных
        $this->db->execute("DELETE FROM websites WHERE id = ?", [$id]);
        
        // Запись в лог
        logMessage("Удален сайт ID $id: {$site['domain']}", 'info');
        
        // Перенаправление на список сайтов
        redirect('sites');
    }
    
    /**
     * Действие: настройка SSL
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionSsl($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        $this->render('ssl', [
            'site' => $site,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: включение/выключение SSL
     * 
     * @param int $id ID сайта
     * @return void
     */
    public function actionSslSetup($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $site = $this->db->queryOne("SELECT * FROM websites WHERE id = ?", [$id]);
        
        if (!$site) {
            redirect('sites');
        }
        
        $enableSsl = isset($_POST['enable_ssl']) && $_POST['enable_ssl'] == 1;
        
        if ($enableSsl && !$site['ssl_enabled']) {
            // Включение SSL
            $email = isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) 
                ? escapeshellarg($_POST['email']) 
                : '--register-unsafely-without-email';
            
            $emailParam = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? "-m " . escapeshellarg($_POST['email']) : "--register-unsafely-without-email";
            
            $result = executeCommand("certbot --nginx -d {$site['domain']} -d www.{$site['domain']} --non-interactive --agree-tos $emailParam");
            
            if ($result['success']) {
                $this->db->execute("UPDATE websites SET ssl_enabled = 1 WHERE id = ?", [$id]);
                logMessage("SSL включен для домена {$site['domain']}", 'info');
                redirect('sites');
            } else {
                $this->render('ssl', [
                    'site' => $site,
                    'error' => 'Ошибка при настройке SSL: ' . $result['output'],
                    'user' => $auth->getCurrentUser()
                ]);
                return;
            }
        } elseif (!$enableSsl && $site['ssl_enabled']) {
            // Выключение SSL
            $result = executeCommand("certbot delete --cert-name {$site['domain']} -n");
            
            if ($result['success']) {
                $this->db->execute("UPDATE websites SET ssl_enabled = 0 WHERE id = ?", [$id]);
                logMessage("SSL отключен для домена {$site['domain']}", 'info');
                
                // Обновление конфигурации Nginx (удаление SSL)
                $siteDir = '/var/www' . $site['path'];
                $nginxConfig = "server {
    listen 80;
    server_name {$site['domain']} www.{$site['domain']};
    
    root $siteDir;
    index index.php index.html;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php{$site['php_version']}-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}";
                
                $configPath = "/etc/nginx/sites-available/{$site['domain']}";
                file_put_contents($configPath, $nginxConfig);
                executeCommand("nginx -t && systemctl reload nginx");
                
                redirect('sites');
            } else {
                $this->render('ssl', [
                    'site' => $site,
                    'error' => 'Ошибка при удалении SSL: ' . $result['output'],
                    'user' => $auth->getCurrentUser()
                ]);
                return;
            }
        } else {
            // Нет изменений
            redirect('sites');
        }
    }
} 