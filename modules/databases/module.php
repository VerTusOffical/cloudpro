<?php
/**
 * CloudPRO - Модуль управления базами данных
 */

class DatabasesModule extends Module {
    protected $name = 'Базы данных';
    protected $icon = 'fa-database';
    protected $menuPosition = 20;
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/databases', [$this, 'actionIndex']);
        $router->get('/databases/create', [$this, 'actionCreate']);
        $router->post('/databases/create', [$this, 'actionStore']);
        $router->get('/databases/edit/:id', [$this, 'actionEdit']);
        $router->post('/databases/edit/:id', [$this, 'actionUpdate']);
        $router->get('/databases/delete/:id', [$this, 'actionDelete']);
        $router->post('/databases/delete/:id', [$this, 'actionDestroy']);
        $router->get('/databases/:id/export', [$this, 'actionExport']);
        $router->post('/databases/:id/import', [$this, 'actionImport']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $totalDatabases = $this->db->queryScalar("SELECT COUNT(*) FROM databases");
        
        // Получение общего размера всех баз данных
        $totalSize = 0;
        $databases = $this->db->query("SELECT name FROM databases");
        
        foreach ($databases as $database) {
            $size = $this->getDatabaseSize($database['name']);
            $totalSize += $size;
        }
        
        return [
            'total' => $totalDatabases,
            'size' => formatFileSize($totalSize)
        ];
    }
    
    /**
     * Получение размера базы данных
     * 
     * @param string $dbName Имя базы данных
     * @return int Размер в байтах
     */
    private function getDatabaseSize($dbName) {
        $query = "SELECT SUM(data_length + index_length) AS size 
                FROM information_schema.TABLES 
                WHERE table_schema = ? 
                GROUP BY table_schema";
        
        $size = $this->db->queryScalar($query, [$dbName]);
        return $size ? (int)$size : 0;
    }
    
    /**
     * Действие: список баз данных
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $databases = $this->db->query("SELECT * FROM databases ORDER BY name");
        
        // Получение размера для каждой базы данных
        foreach ($databases as &$database) {
            $database['size'] = formatFileSize($this->getDatabaseSize($database['name']));
        }
        
        $this->render('index', [
            'databases' => $databases,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: форма создания базы данных
     * 
     * @return void
     */
    public function actionCreate() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $this->render('create', [
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: сохранение новой базы данных
     * 
     * @return void
     */
    public function actionStore() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $name = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Валидация
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Название базы данных не может быть пустым';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $errors[] = 'Название базы данных может содержать только латинские буквы, цифры и знак подчеркивания';
        }
        
        if (empty($username)) {
            $errors[] = 'Имя пользователя не может быть пустым';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Имя пользователя может содержать только латинские буквы, цифры и знак подчеркивания';
        }
        
        if (empty($password)) {
            $errors[] = 'Пароль не может быть пустым';
        }
        
        // Проверка наличия базы данных с таким именем
        $existingDb = $this->db->queryOne("SELECT id FROM databases WHERE name = ?", [$name]);
        if ($existingDb) {
            $errors[] = 'База данных с таким именем уже существует';
        }
        
        if (!empty($errors)) {
            $this->render('create', [
                'errors' => $errors,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Создание базы данных
        try {
            // Экранирование параметров для MySQL
            $escName = $this->db->quote($name);
            $escUsername = $this->db->quote($username);
            $escPassword = $this->db->quote($password);
            
            // Создание базы данных
            $this->db->execute("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Создание пользователя
            $this->db->execute("CREATE USER '$username'@'localhost' IDENTIFIED BY '$password'");
            
            // Предоставление прав пользователю
            $this->db->execute("GRANT ALL PRIVILEGES ON $name.* TO '$username'@'localhost'");
            $this->db->execute("FLUSH PRIVILEGES");
            
            // Сохранение в базе данных CloudPRO
            $this->db->execute(
                "INSERT INTO databases (name, username, password) VALUES (?, ?, ?)",
                [$name, $username, $password]
            );
            
            // Запись в лог
            logMessage("Создана новая база данных: $name с пользователем $username", 'info');
            
            // Перенаправление на список баз данных
            redirect('databases');
        } catch (PDOException $e) {
            // Ошибка
            $errors[] = 'Ошибка при создании базы данных: ' . $e->getMessage();
            
            $this->render('create', [
                'errors' => $errors,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: форма редактирования базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionEdit($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        $this->render('edit', [
            'database' => $database,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: обновление базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionUpdate($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        $password = $_POST['password'] ?? '';
        
        // Валидация
        $errors = [];
        
        if (empty($password)) {
            $errors[] = 'Пароль не может быть пустым';
        }
        
        if (!empty($errors)) {
            $this->render('edit', [
                'errors' => $errors,
                'database' => $database,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Обновление пароля пользователя
        try {
            // Экранирование параметров для MySQL
            $escUsername = $this->db->quote($database['username']);
            $escPassword = $this->db->quote($password);
            
            // Изменение пароля пользователя
            $this->db->execute("ALTER USER '{$database['username']}'@'localhost' IDENTIFIED BY '$password'");
            $this->db->execute("FLUSH PRIVILEGES");
            
            // Обновление в базе данных CloudPRO
            $this->db->execute(
                "UPDATE databases SET password = ? WHERE id = ?",
                [$password, $id]
            );
            
            // Запись в лог
            logMessage("Обновлен пароль для пользователя базы данных {$database['username']}", 'info');
            
            // Перенаправление на список баз данных
            redirect('databases');
        } catch (PDOException $e) {
            // Ошибка
            $errors[] = 'Ошибка при обновлении пароля: ' . $e->getMessage();
            
            $this->render('edit', [
                'errors' => $errors,
                'database' => $database,
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: форма удаления базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionDelete($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        $this->render('delete', [
            'database' => $database,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: удаление базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionDestroy($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        // Защита от удаления системной базы данных
        if ($database['name'] == 'mysql' || $database['name'] == 'information_schema' || 
            $database['name'] == 'performance_schema' || $database['name'] == 'sys' || 
            $database['name'] == DB_NAME) {
            
            $this->render('delete', [
                'database' => $database,
                'error' => 'Невозможно удалить системную базу данных',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        try {
            // Удаление базы данных
            $this->db->execute("DROP DATABASE IF EXISTS {$database['name']}");
            
            // Удаление пользователя
            $this->db->execute("DROP USER IF EXISTS '{$database['username']}'@'localhost'");
            $this->db->execute("FLUSH PRIVILEGES");
            
            // Удаление из базы данных CloudPRO
            $this->db->execute("DELETE FROM databases WHERE id = ?", [$id]);
            
            // Запись в лог
            logMessage("Удалена база данных {$database['name']} с пользователем {$database['username']}", 'info');
            
            // Перенаправление на список баз данных
            redirect('databases');
        } catch (PDOException $e) {
            // Ошибка
            $this->render('delete', [
                'database' => $database,
                'error' => 'Ошибка при удалении базы данных: ' . $e->getMessage(),
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: экспорт базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionExport($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        // Создание директории для экспорта, если не существует
        $exportDir = APP_PATH . '/tmp/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Имя файла для экспорта
        $filename = $database['name'] . '_' . date('Y-m-d_H-i-s') . '.sql';
        $exportFile = $exportDir . '/' . $filename;
        
        // Экспорт базы данных
        $command = "mysqldump -u " . DB_USER . " -p" . DB_PASS . " " . escapeshellarg($database['name']) . " > " . escapeshellarg($exportFile);
        $result = executeCommand($command);
        
        if ($result['success']) {
            // Отправка файла на скачивание
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($exportFile));
            
            // Читаем файл и выводим его содержимое
            readfile($exportFile);
            
            // Удаляем временный файл
            unlink($exportFile);
            
            // Запись в лог
            logMessage("Экспортирована база данных {$database['name']}", 'info');
            
            exit;
        } else {
            // Ошибка экспорта
            $this->render('index', [
                'databases' => $this->db->query("SELECT * FROM databases ORDER BY name"),
                'error' => 'Ошибка при экспорте базы данных: ' . $result['output'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: импорт базы данных
     * 
     * @param int $id ID базы данных
     * @return void
     */
    public function actionImport($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $database = $this->db->queryOne("SELECT * FROM databases WHERE id = ?", [$id]);
        
        if (!$database) {
            redirect('databases');
        }
        
        // Проверка загруженного файла
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->render('index', [
                'databases' => $this->db->query("SELECT * FROM databases ORDER BY name"),
                'error' => 'Ошибка при загрузке файла',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Создание директории для импорта, если не существует
        $importDir = APP_PATH . '/tmp/imports';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }
        
        // Перемещение загруженного файла
        $importFile = $importDir . '/' . $database['name'] . '_' . date('Y-m-d_H-i-s') . '.sql';
        move_uploaded_file($_FILES['import_file']['tmp_name'], $importFile);
        
        // Импорт базы данных
        $command = "mysql -u " . DB_USER . " -p" . DB_PASS . " " . escapeshellarg($database['name']) . " < " . escapeshellarg($importFile);
        $result = executeCommand($command);
        
        // Удаление временного файла
        unlink($importFile);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Импортирована база данных {$database['name']}", 'info');
            
            // Перенаправление на список баз данных
            redirect('databases');
        } else {
            // Ошибка импорта
            $this->render('index', [
                'databases' => $this->db->query("SELECT * FROM databases ORDER BY name"),
                'error' => 'Ошибка при импорте базы данных: ' . $result['output'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
} 