<?php
/**
 * CloudPRO - Модуль просмотра логов
 */

class LogsModule extends Module {
    protected $name = 'Логи';
    protected $icon = 'fa-file-text';
    protected $menuPosition = 40;
    
    // Пути к логам
    private $logPaths = [
        'nginx_access' => '/var/log/nginx/access.log',
        'nginx_error' => '/var/log/nginx/error.log',
        'apache_access' => '/var/log/apache2/access.log',
        'apache_error' => '/var/log/apache2/error.log',
        'mysql' => '/var/log/mysql/error.log',
        'system' => '/var/log/syslog',
        'app' => APP_PATH . '/logs'
    ];
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/logs', [$this, 'actionIndex']);
        $router->get('/logs/view', [$this, 'actionView']);
        $router->get('/logs/download', [$this, 'actionDownload']);
        $router->post('/logs/clear', [$this, 'actionClear']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $appLogDir = APP_PATH . '/logs';
        $appLogCount = 0;
        $appLogSize = 0;
        
        if (is_dir($appLogDir)) {
            $files = scandir($appLogDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($appLogDir . '/' . $file)) {
                    $appLogCount++;
                    $appLogSize += filesize($appLogDir . '/' . $file);
                }
            }
        }
        
        return [
            'app_log_count' => $appLogCount,
            'app_log_size' => formatFileSize($appLogSize)
        ];
    }
    
    /**
     * Действие: список логов
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Получение списка лог-файлов
        $logs = [];
        
        // Системные логи
        foreach ($this->logPaths as $type => $path) {
            if ($type === 'app') {
                // Обработка директории с логами приложения
                if (is_dir($path)) {
                    $files = scandir($path);
                    
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        
                        $filePath = $path . '/' . $file;
                        
                        if (is_file($filePath)) {
                            $logs[] = [
                                'name' => 'Приложение: ' . $file,
                                'path' => $filePath,
                                'size' => filesize($filePath),
                                'modified' => filemtime($filePath),
                                'exists' => true
                            ];
                        }
                    }
                }
            } else {
                // Отдельные лог-файлы
                $name = $this->getLogTypeName($type);
                $exists = file_exists($path);
                
                $logs[] = [
                    'name' => $name,
                    'path' => $path,
                    'size' => $exists ? filesize($path) : 0,
                    'modified' => $exists ? filemtime($path) : 0,
                    'exists' => $exists
                ];
            }
        }
        
        // Сортировка по времени изменения (сначала новые)
        usort($logs, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        $this->render('index', [
            'logs' => $logs,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: просмотр содержимого лога
     * 
     * @return void
     */
    public function actionView() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_GET['path'] ?? '';
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
        
        // Проверка безопасности пути
        if (!$this->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь к логу',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка существования файла
        if (!file_exists($path) || !is_file($path) || !is_readable($path)) {
            $this->render('error', [
                'error' => 'Файл лога не существует или недоступен для чтения',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Получение последних строк лога
        $content = $this->getTailContent($path, $lines);
        
        // Получение имени файла
        $filename = basename($path);
        
        // Определение типа лога
        $type = 'unknown';
        foreach ($this->logPaths as $logType => $logPath) {
            if ($path === $logPath || strpos($path, $logPath . '/') === 0) {
                $type = $logType;
                break;
            }
        }
        
        $logTypeName = $this->getLogTypeName($type);
        
        $this->render('view', [
            'path' => $path,
            'filename' => $filename,
            'content' => $content,
            'lines' => $lines,
            'type' => $type,
            'typeName' => $logTypeName,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: скачивание лога
     * 
     * @return void
     */
    public function actionDownload() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_GET['path'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь к логу',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка существования файла
        if (!file_exists($path) || !is_file($path) || !is_readable($path)) {
            $this->render('error', [
                'error' => 'Файл лога не существует или недоступен для чтения',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Получение имени файла
        $filename = basename($path);
        
        // Запись в лог
        logMessage("Скачан лог-файл: $filename", 'info');
        
        // Отправка файла
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    
    /**
     * Действие: очистка лога
     * 
     * @return void
     */
    public function actionClear() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('logs');
        }
        
        $path = $_POST['path'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь к логу',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка существования файла
        if (!file_exists($path) || !is_file($path)) {
            redirect('logs');
        }
        
        // Очистка файла
        if (is_writable($path)) {
            // Записываем пустую строку в файл
            file_put_contents($path, '');
            
            // Запись в лог
            logMessage("Очищен лог-файл: " . basename($path), 'info');
        }
        
        // Перенаправление на список логов
        redirect('logs');
    }
    
    /**
     * Получение имени типа лога
     * 
     * @param string $type Тип лога
     * @return string Название типа
     */
    private function getLogTypeName($type) {
        $names = [
            'nginx_access' => 'Nginx: журнал доступа',
            'nginx_error' => 'Nginx: журнал ошибок',
            'apache_access' => 'Apache: журнал доступа',
            'apache_error' => 'Apache: журнал ошибок',
            'mysql' => 'MySQL: журнал ошибок',
            'system' => 'Системный журнал',
            'app' => 'Журнал приложения'
        ];
        
        return $names[$type] ?? 'Неизвестный тип';
    }
    
    /**
     * Проверка безопасности пути к логу
     * 
     * @param string $path Путь для проверки
     * @return bool True если путь безопасен
     */
    private function isPathSafe($path) {
        // Нормализация пути
        $realPath = realpath($path);
        
        // Проверка существования пути
        if ($realPath === false) {
            return false;
        }
        
        // Проверка соответствия типичным путям логов
        foreach ($this->logPaths as $logPath) {
            if ($realPath === realpath($logPath) || (is_dir($logPath) && strpos($realPath, realpath($logPath) . '/') === 0)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Получение последних строк лога
     * 
     * @param string $file Путь к файлу
     * @param int $lines Количество строк
     * @return string Содержимое
     */
    private function getTailContent($file, $lines = 100) {
        // Проверяем размер файла
        $fileSize = filesize($file);
        if ($fileSize === 0) {
            return '';
        }
        
        // Для небольших файлов просто читаем все содержимое
        if ($fileSize < 1024 * 1024) { // Менее 1 МБ
            $content = file_get_contents($file);
            $contentLines = explode("\n", $content);
            $totalLines = count($contentLines);
            
            if ($totalLines <= $lines) {
                return $content;
            }
            
            return implode("\n", array_slice($contentLines, -$lines));
        }
        
        // Для больших файлов используем более эффективный метод
        $handle = fopen($file, 'r');
        if (!$handle) {
            return 'Ошибка при открытии файла';
        }
        
        $buffer = 4096;
        $position = $fileSize - 1;
        $chunksRead = 0;
        $foundLines = 0;
        $result = '';
        
        while ($position >= 0 && $foundLines < $lines) {
            $readSize = min($buffer, $position + 1);
            $position -= $readSize;
            
            fseek($handle, $position);
            $chunk = fread($handle, $readSize);
            $chunksRead++;
            
            // Считаем количество новых строк в текущем куске
            $newLinesCount = substr_count($chunk, "\n");
            $foundLines += $newLinesCount;
            
            $result = $chunk . $result;
            
            // Если прочитали достаточно строк или достигли начала файла
            if ($foundLines >= $lines || $position < 0) {
                break;
            }
            
            // Ограничение на количество итераций для безопасности
            if ($chunksRead > 1000) {
                break;
            }
        }
        
        fclose($handle);
        
        // Если нашли больше строк, чем нужно, удаляем лишние
        if ($foundLines > $lines) {
            $resultLines = explode("\n", $result);
            $result = implode("\n", array_slice($resultLines, -$lines));
        }
        
        return $result;
    }
} 