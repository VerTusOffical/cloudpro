<?php
/**
 * CloudPRO - Модуль файлового менеджера
 */

require_once APP_PATH . '/modules/filemanager/FileOperations.php';

class FilemanagerModule extends Module {
    protected $name = 'Файловый менеджер';
    protected $icon = 'fa-folder-open';
    protected $menuPosition = 30;
    private $fileOps;
    
    /**
     * Конструктор модуля
     * 
     * @param Database $db Экземпляр базы данных
     */
    public function __construct(Database $db) {
        parent::__construct($db);
        $this->fileOps = new FileOperations();
    }
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/filemanager', [$this, 'actionIndex']);
        $router->get('/filemanager/browse', [$this, 'actionBrowse']);
        $router->post('/filemanager/upload', [$this, 'actionUpload']);
        $router->post('/filemanager/create-folder', [$this, 'actionCreateFolder']);
        $router->post('/filemanager/create-file', [$this, 'actionCreateFile']);
        $router->post('/filemanager/rename', [$this, 'actionRename']);
        $router->post('/filemanager/delete', [$this, 'actionDelete']);
        $router->get('/filemanager/edit-file', [$this, 'actionEditFile']);
        $router->post('/filemanager/save-file', [$this, 'actionSaveFile']);
        $router->get('/filemanager/download', [$this, 'actionDownload']);
        $router->post('/filemanager/change-permissions', [$this, 'actionChangePermissions']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $websites = $this->db->query("SELECT COUNT(*) as count, SUM(CHAR_LENGTH(path)) as total_path FROM websites");
        $webCount = $websites[0]['count'] ?? 0;
        
        // Получение общего размера /var/www
        $totalSize = $this->fileOps->getDirSize('/var/www/');
        
        return [
            'sites_count' => $webCount,
            'total_size' => formatFileSize($totalSize)
        ];
    }
    
    /**
     * Действие: главная страница файлового менеджера
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Получение списка веб-сайтов
        $websites = $this->db->query("SELECT * FROM websites ORDER BY domain");
        
        // Стандартные директории
        $directories = [
            ['path' => '/var/www', 'name' => 'Web Root'],
            ['path' => '/etc/nginx/sites-available', 'name' => 'Nginx Sites'],
            ['path' => '/var/log', 'name' => 'System Logs'],
            ['path' => APP_PATH, 'name' => 'CloudPRO']
        ];
        
        // Добавление директорий сайтов
        foreach ($websites as $website) {
            $directories[] = [
                'path' => '/var/www' . $website['path'],
                'name' => $website['domain']
            ];
        }
        
        $this->render('index', [
            'directories' => $directories,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: просмотр содержимого директории
     * 
     * @return void
     */
    public function actionBrowse() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_GET['path'] ?? '/var/www';
        $path = realpath($path);
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Получение содержимого директории
        $items = $this->fileOps->getDirectoryContents($path);
        
        // Получение родительской директории
        $parentPath = dirname($path);
        
        $this->render('browse', [
            'path' => $path,
            'parentPath' => $parentPath,
            'items' => $items,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: загрузка файла
     * 
     * @return void
     */
    public function actionUpload() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка наличия файла
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ошибка при загрузке файла: ';
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error .= 'Превышен максимальный размер файла';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error .= 'Превышен максимальный размер файла, указанный в форме';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error .= 'Файл был загружен частично';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error .= 'Файл не был загружен';
                    break;
                default:
                    $error .= 'Неизвестная ошибка';
            }
            
            $this->render('error', [
                'error' => $error,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Загрузка файла
        $result = $this->fileOps->uploadFile($_FILES['file'], $path);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Загружен файл {$result['filename']} в директорию $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: создание директории
     * 
     * @return void
     */
    public function actionCreateFolder() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $folderName = $_POST['folder_name'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка имени директории
        if (empty($folderName) || !$this->fileOps->isValidFilename($folderName)) {
            $this->render('error', [
                'error' => 'Некорректное имя директории',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Создание директории
        $result = $this->fileOps->createFolder($path, $folderName);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Создана директория $folderName в $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: создание файла
     * 
     * @return void
     */
    public function actionCreateFile() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $fileName = $_POST['file_name'] ?? '';
        $content = $_POST['content'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path)) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка имени файла
        if (empty($fileName) || !$this->fileOps->isValidFilename($fileName)) {
            $this->render('error', [
                'error' => 'Некорректное имя файла',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Создание файла
        $result = $this->fileOps->createFile($path, $fileName, $content);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Создан файл $fileName в $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: переименование файла или директории
     * 
     * @return void
     */
    public function actionRename() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $oldName = $_POST['old_name'] ?? '';
        $newName = $_POST['new_name'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$oldName")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка имен
        if (empty($oldName) || empty($newName) || !$this->fileOps->isValidFilename($newName)) {
            $this->render('error', [
                'error' => 'Некорректное имя файла',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Переименование
        $result = $this->fileOps->rename($path, $oldName, $newName);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Переименован файл $oldName в $newName в директории $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: удаление файла или директории
     * 
     * @return void
     */
    public function actionDelete() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $name = $_POST['name'] ?? '';
        $isDir = isset($_POST['is_dir']) && $_POST['is_dir'] == 1;
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$name")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Удаление
        $result = $this->fileOps->delete($path, $name, $isDir);
        
        if ($result['success']) {
            // Запись в лог
            $type = $isDir ? 'директория' : 'файл';
            logMessage("Удален $type $name из директории $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: редактирование файла
     * 
     * @return void
     */
    public function actionEditFile() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_GET['path'] ?? '';
        $file = $_GET['file'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$file")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка, что файл существует и доступен для чтения
        $fullPath = "$path/$file";
        if (!file_exists($fullPath) || !is_file($fullPath) || !is_readable($fullPath)) {
            $this->render('error', [
                'error' => 'Файл не существует или недоступен для чтения',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Чтение содержимого файла
        $content = file_get_contents($fullPath);
        
        // Определение типа файла для подсветки синтаксиса
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        $this->render('edit-file', [
            'path' => $path,
            'file' => $file,
            'content' => $content,
            'extension' => $extension,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: сохранение файла
     * 
     * @return void
     */
    public function actionSaveFile() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $file = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$file")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Сохранение файла
        $result = $this->fileOps->saveFile("$path/$file", $content);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Изменен файл $file в директории $path", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: скачивание файла
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
        $file = $_GET['file'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$file")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка, что файл существует и доступен для чтения
        $fullPath = "$path/$file";
        if (!file_exists($fullPath) || !is_file($fullPath) || !is_readable($fullPath)) {
            $this->render('error', [
                'error' => 'Файл не существует или недоступен для чтения',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Запись в лог
        logMessage("Скачан файл $file из директории $path", 'info');
        
        // Отправка файла
        $this->fileOps->downloadFile($fullPath);
    }
    
    /**
     * Действие: изменение прав доступа
     * 
     * @return void
     */
    public function actionChangePermissions() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $path = $_POST['path'] ?? '';
        $name = $_POST['name'] ?? '';
        $permissions = $_POST['permissions'] ?? '';
        
        // Проверка безопасности пути
        if (!$this->fileOps->isPathSafe($path) || !$this->fileOps->isPathSafe("$path/$name")) {
            $this->render('error', [
                'error' => 'Недопустимый путь',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка формата прав доступа
        if (!preg_match('/^[0-7]{3}$/', $permissions)) {
            $this->render('error', [
                'error' => 'Некорректный формат прав доступа',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Изменение прав доступа
        $result = $this->fileOps->changePermissions("$path/$name", $permissions);
        
        if ($result['success']) {
            // Запись в лог
            logMessage("Изменены права доступа для $name в директории $path на $permissions", 'info');
            
            // Перенаправление на просмотр директории
            redirect("filemanager/browse?path=" . urlencode($path));
        } else {
            $this->render('error', [
                'error' => $result['error'],
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
} 