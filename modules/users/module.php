<?php
/**
 * CloudPRO - Модуль управления пользователями
 */

class UsersModule extends Module {
    protected $name = 'Пользователи';
    protected $icon = 'fa-users';
    protected $menuPosition = 50;
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    public function registerRoutes(Router $router) {
        $router->get('/users', [$this, 'actionIndex']);
        $router->get('/users/create', [$this, 'actionCreate']);
        $router->post('/users/create', [$this, 'actionStore']);
        $router->get('/users/edit/:id', [$this, 'actionEdit']);
        $router->post('/users/edit/:id', [$this, 'actionUpdate']);
        $router->get('/users/delete/:id', [$this, 'actionDelete']);
        $router->post('/users/delete/:id', [$this, 'actionDestroy']);
        $router->get('/users/profile', [$this, 'actionProfile']);
        $router->post('/users/profile', [$this, 'actionUpdateProfile']);
    }
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    public function getStats() {
        $totalUsers = $this->db->queryScalar("SELECT COUNT(*) FROM users");
        $adminUsers = $this->db->queryScalar("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        
        return [
            'total' => $totalUsers,
            'admins' => $adminUsers
        ];
    }
    
    /**
     * Действие: список пользователей
     * 
     * @return void
     */
    public function actionIndex() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('dashboard');
        }
        
        $users = $this->db->query("SELECT * FROM users ORDER BY username");
        
        $this->render('index', [
            'users' => $users,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: форма создания пользователя
     * 
     * @return void
     */
    public function actionCreate() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('dashboard');
        }
        
        $this->render('create', [
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: сохранение нового пользователя
     * 
     * @return void
     */
    public function actionStore() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('dashboard');
        }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        // Валидация
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'Имя пользователя не может быть пустым';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Имя пользователя может содержать только латинские буквы, цифры и знак подчеркивания';
        }
        
        if (empty($password)) {
            $errors[] = 'Пароль не может быть пустым';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать не менее 6 символов';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный адрес электронной почты';
        }
        
        if ($role != 'admin' && $role != 'user') {
            $errors[] = 'Некорректная роль пользователя';
        }
        
        // Проверка наличия пользователя с таким именем
        $existingUser = $this->db->queryOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existingUser) {
            $errors[] = 'Пользователь с таким именем уже существует';
        }
        
        if (!empty($errors)) {
            $this->render('create', [
                'errors' => $errors,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Создание пользователя
        $result = $auth->createUser($username, $password, $email, $role);
        
        if ($result) {
            // Перенаправление на список пользователей
            redirect('users');
        } else {
            $errors[] = 'Ошибка при создании пользователя';
            
            $this->render('create', [
                'errors' => $errors,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: форма редактирования пользователя
     * 
     * @param int $id ID пользователя
     * @return void
     */
    public function actionEdit($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов или для своего профиля
        $currentUser = $auth->getCurrentUser();
        if (!$auth->isAdmin() && $currentUser['id'] != $id) {
            redirect('dashboard');
        }
        
        $userToEdit = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        
        if (!$userToEdit) {
            redirect('users');
        }
        
        $this->render('edit', [
            'userToEdit' => $userToEdit,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: обновление пользователя
     * 
     * @param int $id ID пользователя
     * @return void
     */
    public function actionUpdate($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов или для своего профиля
        $currentUser = $auth->getCurrentUser();
        if (!$auth->isAdmin() && $currentUser['id'] != $id) {
            redirect('dashboard');
        }
        
        $userToEdit = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        
        if (!$userToEdit) {
            redirect('users');
        }
        
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? $userToEdit['role'];
        
        // Администратор может изменить роль, обычный пользователь - нет
        if (!$auth->isAdmin()) {
            $role = $userToEdit['role'];
        }
        
        // Валидация
        $errors = [];
        
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать не менее 6 символов';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный адрес электронной почты';
        }
        
        if ($role != 'admin' && $role != 'user') {
            $errors[] = 'Некорректная роль пользователя';
        }
        
        // Проверка, чтобы не удалить последнего администратора
        if ($userToEdit['role'] == 'admin' && $role == 'user') {
            $adminCount = $this->db->queryScalar("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($adminCount <= 1) {
                $errors[] = 'Невозможно понизить права единственного администратора';
            }
        }
        
        if (!empty($errors)) {
            $this->render('edit', [
                'errors' => $errors,
                'userToEdit' => array_merge($userToEdit, [
                    'email' => $email,
                    'role' => $role
                ]),
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Обновление пользователя
        $updates = [];
        $params = [];
        
        if (!empty($password)) {
            $passwordHash = hash('sha256', $password);
            $updates[] = "password = ?";
            $params[] = $passwordHash;
        }
        
        $updates[] = "email = ?";
        $params[] = $email;
        
        $updates[] = "role = ?";
        $params[] = $role;
        
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $result = $this->db->execute($sql, $params);
        
        if ($result) {
            // Запись в лог
            logMessage("Обновлен пользователь ID $id: {$userToEdit['username']}", 'info');
            
            // Перенаправление
            if ($auth->isAdmin()) {
                redirect('users');
            } else {
                redirect('dashboard');
            }
        } else {
            $errors[] = 'Ошибка при обновлении пользователя';
            
            $this->render('edit', [
                'errors' => $errors,
                'userToEdit' => array_merge($userToEdit, [
                    'email' => $email,
                    'role' => $role
                ]),
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: форма удаления пользователя
     * 
     * @param int $id ID пользователя
     * @return void
     */
    public function actionDelete($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('dashboard');
        }
        
        $userToDelete = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        
        if (!$userToDelete) {
            redirect('users');
        }
        
        // Нельзя удалить самого себя
        $currentUser = $auth->getCurrentUser();
        if ($currentUser['id'] == $id) {
            $this->render('index', [
                'users' => $this->db->query("SELECT * FROM users ORDER BY username"),
                'error' => 'Невозможно удалить текущего пользователя',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка, чтобы не удалить последнего администратора
        if ($userToDelete['role'] == 'admin') {
            $adminCount = $this->db->queryScalar("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($adminCount <= 1) {
                $this->render('index', [
                    'users' => $this->db->query("SELECT * FROM users ORDER BY username"),
                    'error' => 'Невозможно удалить единственного администратора',
                    'user' => $auth->getCurrentUser()
                ]);
                return;
            }
        }
        
        $this->render('delete', [
            'userToDelete' => $userToDelete,
            'user' => $auth->getCurrentUser()
        ]);
    }
    
    /**
     * Действие: удаление пользователя
     * 
     * @param int $id ID пользователя
     * @return void
     */
    public function actionDestroy($id) {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        // Доступ только для администраторов
        if (!$auth->isAdmin()) {
            redirect('dashboard');
        }
        
        $userToDelete = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        
        if (!$userToDelete) {
            redirect('users');
        }
        
        // Нельзя удалить самого себя
        $currentUser = $auth->getCurrentUser();
        if ($currentUser['id'] == $id) {
            $this->render('index', [
                'users' => $this->db->query("SELECT * FROM users ORDER BY username"),
                'error' => 'Невозможно удалить текущего пользователя',
                'user' => $auth->getCurrentUser()
            ]);
            return;
        }
        
        // Проверка, чтобы не удалить последнего администратора
        if ($userToDelete['role'] == 'admin') {
            $adminCount = $this->db->queryScalar("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($adminCount <= 1) {
                $this->render('index', [
                    'users' => $this->db->query("SELECT * FROM users ORDER BY username"),
                    'error' => 'Невозможно удалить единственного администратора',
                    'user' => $auth->getCurrentUser()
                ]);
                return;
            }
        }
        
        // Удаление пользователя
        $result = $this->db->execute("DELETE FROM users WHERE id = ?", [$id]);
        
        if ($result) {
            // Запись в лог
            logMessage("Удален пользователь ID $id: {$userToDelete['username']}", 'info');
            
            // Перенаправление на список пользователей
            redirect('users');
        } else {
            $this->render('delete', [
                'error' => 'Ошибка при удалении пользователя',
                'userToDelete' => $userToDelete,
                'user' => $auth->getCurrentUser()
            ]);
        }
    }
    
    /**
     * Действие: просмотр профиля
     * 
     * @return void
     */
    public function actionProfile() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $currentUser = $auth->getCurrentUser();
        
        $this->render('profile', [
            'userProfile' => $currentUser,
            'user' => $currentUser
        ]);
    }
    
    /**
     * Действие: обновление профиля
     * 
     * @return void
     */
    public function actionUpdateProfile() {
        // Проверка авторизации
        global $auth;
        if (!$auth->isLoggedIn()) {
            redirect('login');
        }
        
        $currentUser = $auth->getCurrentUser();
        $id = $currentUser['id'];
        
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Валидация
        $errors = [];
        
        if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
            // Проверка текущего пароля
            $currentPasswordHash = hash('sha256', $currentPassword);
            if ($currentPasswordHash !== $currentUser['password']) {
                $errors[] = 'Текущий пароль введен неверно';
            }
            
            // Проверка нового пароля
            if (empty($newPassword)) {
                $errors[] = 'Новый пароль не может быть пустым';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Новый пароль должен содержать не менее 6 символов';
            }
            
            // Проверка подтверждения пароля
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Пароли не совпадают';
            }
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный адрес электронной почты';
        }
        
        if (!empty($errors)) {
            $this->render('profile', [
                'errors' => $errors,
                'userProfile' => array_merge($currentUser, [
                    'email' => $email
                ]),
                'user' => $currentUser
            ]);
            return;
        }
        
        // Обновление профиля
        $updates = [];
        $params = [];
        
        if (!empty($newPassword)) {
            $newPasswordHash = hash('sha256', $newPassword);
            $updates[] = "password = ?";
            $params[] = $newPasswordHash;
        }
        
        if (!empty($email)) {
            $updates[] = "email = ?";
            $params[] = $email;
        }
        
        if (!empty($updates)) {
            $params[] = $id;
            
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                // Запись в лог
                logMessage("Пользователь {$currentUser['username']} обновил свой профиль", 'info');
                
                // Если пароль был изменен, нужно выйти из системы
                if (!empty($newPassword)) {
                    $auth->logout();
                    redirect('login');
                } else {
                    // Обновление данных текущего пользователя в сессии
                    $_SESSION['user_info_updated'] = true;
                    redirect('dashboard');
                }
            } else {
                $errors[] = 'Ошибка при обновлении профиля';
                
                $this->render('profile', [
                    'errors' => $errors,
                    'userProfile' => array_merge($currentUser, [
                        'email' => $email
                    ]),
                    'user' => $currentUser
                ]);
            }
        } else {
            // Нет изменений
            redirect('dashboard');
        }
    }
} 