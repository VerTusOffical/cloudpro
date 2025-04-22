<?php
/**
 * CloudPRO - Модуль управления логами
 */

class LogsModule extends Module {
    /**
     * Конструктор модуля
     * 
     * @param Database $db Экземпляр базы данных
     */
    public function __construct(Database $db) {
        parent::__construct($db);
        $this->name = 'Логи';
        $this->icon = 'fa-file-text-o';
        $this->menuPosition = 5;
    }
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     */
    public function registerRoutes(Router $router) {
        // Список логов
        $router->get('/logs', function() {
            return $this->actionIndex();
        });
        
        // Просмотр содержимого лога
        $router->get('/logs/view', function() {
            return $this->actionView();
        });
        
        // Скачивание лога
        $router->get('/logs/download', function() {
            return $this->actionDownload();
        });
        
        // Очистка лога
        $router->post('/logs/clear', function() {
            return $this->actionClear();
        });
    }
    
    /**
     * Получение статистики для отображения на главной странице
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $logPath = LOG_PATH;
        $logFiles = glob($logPath . '/*.log');
        
        $totalLogs = count($logFiles);
        $totalSize = 0;
        
        foreach ($logFiles as $logFile) {
            $totalSize += filesize($logFile);
        }
        
        return [
            'title' => 'Логи',
            'count' => $totalLogs,
            'size' => formatBytes($totalSize),
            'icon' => $this->icon
        ];
    }
    
    /**
     * Отображение списка доступных логов
     * 
     * @return string HTML-код страницы
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $logPath = LOG_PATH;
        $logFiles = glob($logPath . '/*.log');
        
        $logs = [];
        
        foreach ($logFiles as $logFile) {
            $filename = basename($logFile);
            $size = filesize($logFile);
            $modified = filemtime($logFile);
            
            // Определяем тип лога (system, access, error и т.д.)
            $type = 'unknown';
            if (preg_match('/^([a-z]+)(_[0-9-]+)?\.log$/', $filename, $matches)) {
                $type = $matches[1];
            }
            
            $logs[] = [
                'name' => $filename,
                'type' => $type,
                'type_name' => $this->getLogTypeName($type),
                'size' => formatBytes($size),
                'modified' => date('Y-m-d H:i:s', $modified),
                'url' => '?route=logs/view&file=' . urlencode($filename)
            ];
        }
        
        // Сортировка логов по времени изменения (новые вверху)
        usort($logs, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        $template = new Template('modules/logs/list');
        return $template->render([
            'logs' => $logs,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Просмотр содержимого лога
     * 
     * @return string HTML-код страницы
     */
    public function actionView() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $file = isset($_GET['file']) ? $_GET['file'] : null;
        $lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 100;
        
        if (!$file || !$this->isPathSafe($file)) {
            setFlash('error', 'Указан неверный или небезопасный файл лога');
            redirect('logs');
        }
        
        $logPath = LOG_PATH . '/' . $file;
        
        if (!file_exists($logPath)) {
            setFlash('error', 'Файл лога не найден');
            redirect('logs');
        }
        
        // Определяем тип лога для подсветки
        $type = 'unknown';
        if (preg_match('/^([a-z]+)(_[0-9-]+)?\.log$/', $file, $matches)) {
            $type = $matches[1];
        }
        
        // Получаем последние N строк из файла
        $content = $this->getTailContent($logPath, $lines);
        
        $template = new Template('modules/logs/view');
        return $template->render([
            'filename' => $file,
            'content' => $content,
            'type' => $type,
            'type_name' => $this->getLogTypeName($type),
            'lines' => $lines,
            'file_size' => formatBytes(filesize($logPath)),
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Скачивание файла лога
     * 
     * @return void
     */
    public function actionDownload() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $file = isset($_GET['file']) ? $_GET['file'] : null;
        
        if (!$file || !$this->isPathSafe($file)) {
            setFlash('error', 'Указан неверный или небезопасный файл лога');
            redirect('logs');
        }
        
        $logPath = LOG_PATH . '/' . $file;
        
        if (!file_exists($logPath)) {
            setFlash('error', 'Файл лога не найден');
            redirect('logs');
        }
        
        // Отправляем файл
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($logPath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($logPath));
        readfile($logPath);
        exit;
    }
    
    /**
     * Очистка файла лога
     * 
     * @return void
     */
    public function actionClear() {
        // Проверка авторизации и прав
        global $auth;
        if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
            setFlash('error', 'У вас нет прав на очистку логов');
            redirect('logs');
        }
        
        $file = isset($_POST['file']) ? $_POST['file'] : null;
        
        if (!$file || !$this->isPathSafe($file)) {
            setFlash('error', 'Указан неверный или небезопасный файл лога');
            redirect('logs');
        }
        
        $logPath = LOG_PATH . '/' . $file;
        
        if (!file_exists($logPath)) {
            setFlash('error', 'Файл лога не найден');
            redirect('logs');
        }
        
        // Очищаем файл
        file_put_contents($logPath, '');
        
        setFlash('success', 'Файл лога успешно очищен');
        redirect('logs');
    }
    
    /**
     * Получение человекочитаемого названия типа лога
     * 
     * @param string $type Тип лога
     * @return string Название типа
     */
    private function getLogTypeName($type) {
        $types = [
            'system' => 'Системные логи',
            'access' => 'Логи доступа',
            'error' => 'Логи ошибок',
            'mysql' => 'Логи MySQL',
            'nginx' => 'Логи Nginx',
            'php' => 'Логи PHP',
            'app' => 'Логи приложения'
        ];
        
        return isset($types[$type]) ? $types[$type] : 'Прочие логи';
    }
    
    /**
     * Проверка, что путь к файлу безопасен
     * 
     * @param string $path Имя файла
     * @return bool True если путь безопасен
     */
    private function isPathSafe($path) {
        // Проверяем, что путь не содержит .. и не выходит за пределы директории логов
        if (strpos($path, '..') !== false || strpos($path, '/') !== false) {
            return false;
        }
        
        // Проверяем что это файл .log
        if (!preg_match('/^[a-zA-Z0-9_-]+\.log$/', $path)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Получение последних N строк из файла
     * 
     * @param string $file Путь к файлу
     * @param int $lines Количество строк
     * @return string Содержимое
     */
    private function getTailContent($file, $lines = 100) {
        $content = '';
        
        // Используем команду tail для больших файлов
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            $content = @exec("tail -n $lines " . escapeshellarg($file));
            
            if (!empty($content)) {
                return $content;
            }
        }
        
        // Если exec недоступен или вернул пустой результат, читаем файл вручную
        $fileHandle = @fopen($file, 'r');
        if ($fileHandle) {
            $lineArray = [];
            while (!feof($fileHandle)) {
                $line = fgets($fileHandle);
                if ($line !== false) {
                    $lineArray[] = $line;
                    
                    // Ограничиваем количество строк для больших файлов
                    if (count($lineArray) > $lines * 2) {
                        array_shift($lineArray);
                    }
                }
            }
            fclose($fileHandle);
            
            // Берем последние N строк
            $lineArray = array_slice($lineArray, -$lines);
            $content = implode('', $lineArray);
        }
        
        return $content;
    }
} 