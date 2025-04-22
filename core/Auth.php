<?php
/**
 * CloudPRO - Модуль аутентификации
 */

class Auth {
    private $db;
    private $user = null;
    
    /**
     * Конструктор класса аутентификации
     * 
     * @param Database $db Экземпляр базы данных
     */
    public function __construct(Database $db) {
        $this->db = $db;
        $this->checkSession();
    }
    
    /**
     * Проверка текущей сессии
     * 
     * @return bool True если пользователь авторизован
     */
    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $this->user = $user;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Авторизация пользователя
     * 
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @return bool True при успешной авторизации
     */
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Проверка пароля (хэш SHA-256)
            $passwordHash = hash('sha256', $password);
            
            if ($passwordHash === $user['password']) {
                // Успешная авторизация
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['last_activity'] = time();
                $this->user = $user;
                
                // Логируем вход
                logMessage("Пользователь {$user['username']} вошел в систему", 'info');
                
                return true;
            }
        }
        
        // Логируем неудачную попытку
        logMessage("Неудачная попытка входа для пользователя $username", 'warning');
        
        return false;
    }
    
    /**
     * Выход из системы
     * 
     * @return void
     */
    public function logout() {
        if ($this->user) {
            logMessage("Пользователь {$this->user['username']} вышел из системы", 'info');
        }
        
        // Удаляем все данные сессии
        $_SESSION = [];
        
        // Уничтожаем сессию
        session_destroy();
        
        $this->user = null;
    }
    
    /**
     * Проверка авторизации
     * 
     * @return bool True если пользователь авторизован
     */
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    /**
     * Получение данных текущего пользователя
     * 
     * @return array|null Данные пользователя или null
     */
    public function getCurrentUser() {
        return $this->user;
    }
    
    /**
     * Проверка прав администратора
     * 
     * @return bool True если пользователь администратор
     */
    public function isAdmin() {
        return $this->isLoggedIn() && $this->user['role'] === 'admin';
    }
    
    /**
     * Создание нового пользователя
     * 
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @param string $email Электронная почта
     * @param string $role Роль (admin, user)
     * @return bool|int ID пользователя или false при ошибке
     */
    public function createUser($username, $password, $email, $role = 'user') {
        // Проверка существования пользователя
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            return false; // Пользователь уже существует
        }
        
        // Хэширование пароля
        $passwordHash = hash('sha256', $password);
        
        // Добавление пользователя
        $stmt = $this->db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$username, $passwordHash, $email, $role]);
        
        if ($result) {
            logMessage("Создан новый пользователь: $username с ролью $role", 'info');
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Изменение пароля пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $newPassword Новый пароль
     * @return bool Успешность операции
     */
    public function changePassword($userId, $newPassword) {
        $passwordHash = hash('sha256', $newPassword);
        
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$passwordHash, $userId]);
        
        if ($result) {
            logMessage("Изменен пароль для пользователя с ID: $userId", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Удаление пользователя
     * 
     * @param int $userId ID пользователя
     * @return bool Успешность операции
     */
    public function deleteUser($userId) {
        // Получаем информацию о пользователе перед удалением
        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Проверяем, что не удаляем последнего администратора
        if ($userId != $this->user['id']) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = (int)$stmt->fetchColumn();
            
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $role = $stmt->fetchColumn();
            
            if ($adminCount <= 1 && $role === 'admin') {
                return false; // Нельзя удалить последнего администратора
            }
        } else {
            return false; // Нельзя удалить самого себя
        }
        
        // Удаляем пользователя
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            logMessage("Удален пользователь: {$user['username']}", 'info');
            return true;
        }
        
        return false;
    }
} 