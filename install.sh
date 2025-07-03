#!/bin/bash

# CLOUD PRO V2 - Скрипт установки панели управления
# Автоматический установщик для Debian 11/12

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Функция для вывода сообщений
print_message() {
    echo -e "${BLUE}[CLOUD PRO V2]${NC} $1"
}

print_error() {
    echo -e "${RED}[ОШИБКА]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[УСПЕХ]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[ВНИМАНИЕ]${NC} $1"
}

# Проверка прав суперпользователя
if [ "$(id -u)" != "0" ]; then
   print_error "Этот скрипт должен быть запущен с правами суперпользователя (root)"
   exit 1
fi

# Проверка ОС
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
    
    if [ "$OS" != "debian" ]; then
        print_error "Эта панель поддерживает только Debian 11/12"
        exit 1
    fi
    
    if [ "$VERSION" != "11" ] && [ "$VERSION" != "12" ]; then
        print_warning "Рекомендуется использовать Debian 11 или Debian 12"
        read -p "Продолжить установку? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
else
    print_error "Невозможно определить операционную систему"
    exit 1
fi

# Приветствие
clear
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                        CLOUD PRO V2                           ║"
echo "║                                                               ║"
echo "║               Панель управления хостингом                     ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
print_message "Добро пожаловать в установщик панели управления CLOUD PRO V2"
print_message "Этот скрипт установит все необходимые компоненты для работы панели"
print_message "Поддерживаемые ОС: Debian 11/12"
echo ""
print_warning "ВНИМАНИЕ: Установка изменит конфигурацию вашего сервера!"
read -p "Вы уверены, что хотите продолжить? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_message "Установка отменена"
    exit 1
fi

# Обновление системы
print_message "Обновление системных пакетов..."
apt update && apt upgrade -y
if [ $? -ne 0 ]; then
    print_error "Ошибка при обновлении системы"
    exit 1
fi
print_success "Система обновлена"

# Установка необходимых пакетов
print_message "Установка необходимых пакетов..."
PACKAGES="curl wget git unzip zip tar software-properties-common apt-transport-https ca-certificates gnupg lsb-release ufw"
apt install -y $PACKAGES
if [ $? -ne 0 ]; then
    print_error "Ошибка при установке необходимых пакетов"
    exit 1
fi
print_success "Необходимые пакеты установлены"

# Выбор веб-сервера
echo ""
print_message "Выберите веб-сервер для установки:"
echo "1) Nginx (рекомендуется)"
echo "2) Apache2"
echo "3) Nginx + Apache2"
read -p "Введите номер [1-3]: " web_server_choice

case $web_server_choice in
    1)
        WEB_SERVER="nginx"
        print_message "Выбран веб-сервер: Nginx"
        ;;
    2)
        WEB_SERVER="apache2"
        print_message "Выбран веб-сервер: Apache2"
        ;;
    3)
        WEB_SERVER="nginx_apache"
        print_message "Выбран веб-сервер: Nginx + Apache2"
        ;;
    *)
        print_warning "Неверный выбор. Будет установлен Nginx"
        WEB_SERVER="nginx"
        ;;
esac

# download web server (apache/nginx)
print_message "Установка веб-сервера..."
if [ "$WEB_SERVER" = "nginx" ]; then
    apt install -y nginx
    systemctl enable nginx
    systemctl start nginx
elif [ "$WEB_SERVER" = "apache2" ]; then
    apt install -y apache2
    systemctl enable apache2
    systemctl start apache2
else
    apt install -y nginx apache2 libapache2-mod-rpaf
    systemctl enable nginx apache2
    systemctl start nginx apache2
fi

if [ $? -ne 0 ]; then
    print_error "Ошибка при установке веб-сервера"
    exit 1
fi
print_success "Веб-сервер установлен"

# download php versions
print_message "Установка PHP и необходимых расширений..."
apt install -y php8.1 php8.1-fpm php8.1-cli php8.1-common php8.1-mysql php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl php8.1-xml php8.1-bcmath php8.1-intl
if [ $? -ne 0 ]; then
    # попробуем установить 7.4, если 8.1 инворк
    print_warning "PHP 8.1 недоступен, пробуем установить PHP 7.4..."
    apt install -y php7.4 php7.4-fpm php7.4-cli php7.4-common php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-bcmath php7.4-intl
    if [ $? -ne 0 ]; then
        print_error "Ошибка при установке PHP"
        exit 1
    fi
fi

# enable php-fpm
if [ "$WEB_SERVER" = "apache2" ]; then
    apt install -y libapache2-mod-php8.1 || apt install -y libapache2-mod-php7.4
    a2enmod php8.1 || a2enmod php7.4
    systemctl restart apache2
fi

print_success "PHP и необходимые расширения установлены"

# install mariadb
print_message "Установка MariaDB..."
apt install -y mariadb-server mariadb-client
if [ $? -ne 0 ]; then
    print_error "Ошибка при установке MariaDB"
    exit 1
fi
systemctl enable mariadb
systemctl start mariadb
print_success "MariaDB установлена"

# security mariadb
print_message "Настройка безопасности MariaDB..."
DB_ROOT_PASSWORD=$(openssl rand -base64 12)
mysql -e "UPDATE mysql.user SET Password=PASSWORD('$DB_ROOT_PASSWORD') WHERE User='root'"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')"
mysql -e "DELETE FROM mysql.user WHERE User=''"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%'"
mysql -e "FLUSH PRIVILEGES"
print_success "Безопасность MariaDB настроена. Пароль root: $DB_ROOT_PASSWORD"

# create database for panel
print_message "Создание базы данных для панели управления..."
DB_NAME="cldpro"
DB_USER="cldpro_user"
DB_PASSWORD=$(openssl rand -base64 12)
mysql -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'"
mysql -e "FLUSH PRIVILEGES"
print_success "База данных создана. Имя: $DB_NAME, Пользователь: $DB_USER, Пароль: $DB_PASSWORD"

# create directories for panel
print_message "Создание директорий для панели управления..."
PANEL_DIR="/var/www/cldpro"
mkdir -p $PANEL_DIR
mkdir -p $PANEL_DIR/public
mkdir -p $PANEL_DIR/config
mkdir -p $PANEL_DIR/logs
mkdir -p $PANEL_DIR/templates
mkdir -p $PANEL_DIR/backups
mkdir -p /var/www/sites
print_success "Директории созданы"

# create config file
print_message "Создание конфигурационного файла..."
cat > $PANEL_DIR/config/config.php << EOF
<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASSWORD', '$DB_PASSWORD');

// Пути к директориям
define('PANEL_DIR', '$PANEL_DIR');
define('SITES_DIR', '/var/www/sites');
define('BACKUPS_DIR', '$PANEL_DIR/backups');
define('TEMPLATES_DIR', '$PANEL_DIR/templates');
define('LOGS_DIR', '$PANEL_DIR/logs');

// Настройки безопасности
define('ADMIN_USER', 'admin');
define('ADMIN_PASSWORD_HASH', '');  // Будет установлено позже
define('SESSION_TIMEOUT', 3600);    // 1 час
define('SECURE_COOKIES', true);
?>
EOF
print_success "Конфигурационный файл создан"

# Создание файла функций
print_message "Создание файла функций..."
cat > $PANEL_DIR/config/functions.php << 'EOF'
<?php
// Функции для работы с базой данных
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Ошибка подключения к базе данных: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Функция для безопасного выполнения SQL запросов
function db_query($sql, $params = []) {
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $types = '';
        $bindParams = [];
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $bindParams[] = $param;
        }
        
        $bindValues = array_merge([$types], $bindParams);
        $stmt->bind_param(...$bindValues);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Функция для создания нового сайта
function create_site($domain, $user, $php_version = '8.1') {
    // Создание директории для сайта
    $site_dir = SITES_DIR . '/' . $domain;
    if (!file_exists($site_dir)) {
        mkdir($site_dir, 0755, true);
    }
    
    // Создание директории для логов
    $logs_dir = $site_dir . '/logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Создание директории для публичных файлов
    $public_dir = $site_dir . '/public_html';
    if (!file_exists($public_dir)) {
        mkdir($public_dir, 0755, true);
    }
    
    // Создание индексного файла
    $index_file = $public_dir . '/index.html';
    file_put_contents($index_file, '<html><head><title>Welcome to ' . $domain . '</title></head><body><h1>Welcome to ' . $domain . '</h1><p>Your website is ready!</p></body></html>');
    
    // Установка прав доступа
    chown($site_dir, $user);
    chgrp($site_dir, $user);
    
    // Добавление сайта в базу данных
    $sql = "INSERT INTO sites (domain, user, php_version, created_at) VALUES (?, ?, ?, NOW())";
    db_query($sql, [$domain, $user, $php_version]);
    
    // Создание конфигурации веб-сервера
    create_webserver_config($domain, $php_version);
    
    return true;
}

// Функция для создания конфигурации веб-сервера
function create_webserver_config($domain, $php_version) {
    $site_dir = SITES_DIR . '/' . $domain;
    $public_dir = $site_dir . '/public_html';
    $logs_dir = $site_dir . '/logs';
    
    // Определение веб-сервера
    $web_server = getenv('WEB_SERVER') ?: 'nginx';
    
    if ($web_server == 'nginx' || $web_server == 'nginx_apache') {
        // Создание конфигурации Nginx
        $nginx_config = '/etc/nginx/sites-available/' . $domain . '.conf';
        $nginx_content = "server {\n";
        $nginx_content .= "    listen 80;\n";
        $nginx_content .= "    server_name " . $domain . " www." . $domain . ";\n";
        $nginx_content .= "    root " . $public_dir . ";\n";
        $nginx_content .= "    index index.php index.html index.htm;\n\n";
        $nginx_content .= "    access_log " . $logs_dir . "/access.log;\n";
        $nginx_content .= "    error_log " . $logs_dir . "/error.log;\n\n";
        $nginx_content .= "    location / {\n";
        $nginx_content .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
        $nginx_content .= "    }\n\n";
        $nginx_content .= "    location ~ \.php$ {\n";
        $nginx_content .= "        include snippets/fastcgi-php.conf;\n";
        $nginx_content .= "        fastcgi_pass unix:/var/run/php/php" . $php_version . "-fpm.sock;\n";
        $nginx_content .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
        $nginx_content .= "        include fastcgi_params;\n";
        $nginx_content .= "    }\n";
        $nginx_content .= "}\n";
        
        file_put_contents($nginx_config, $nginx_content);
        
        // Создание символической ссылки
        if (!file_exists('/etc/nginx/sites-enabled/' . $domain . '.conf')) {
            symlink($nginx_config, '/etc/nginx/sites-enabled/' . $domain . '.conf');
        }
        
        // Перезагрузка Nginx
        shell_exec('systemctl reload nginx');
    }
    
    if ($web_server == 'apache2' || $web_server == 'nginx_apache') {
        // Создание конфигурации Apache
        $apache_config = '/etc/apache2/sites-available/' . $domain . '.conf';
        $apache_content = "<VirtualHost *:80>\n";
        $apache_content .= "    ServerName " . $domain . "\n";
        $apache_content .= "    ServerAlias www." . $domain . "\n";
        $apache_content .= "    DocumentRoot " . $public_dir . "\n\n";
        $apache_content .= "    <Directory " . $public_dir . ">\n";
        $apache_content .= "        Options -Indexes +FollowSymLinks +MultiViews\n";
        $apache_content .= "        AllowOverride All\n";
        $apache_content .= "        Require all granted\n";
        $apache_content .= "    </Directory>\n\n";
        $apache_content .= "    ErrorLog " . $logs_dir . "/error.log\n";
        $apache_content .= "    CustomLog " . $logs_dir . "/access.log combined\n";
        $apache_content .= "</VirtualHost>\n";
        
        file_put_contents($apache_config, $apache_content);
        
        // Включение сайта
        shell_exec('a2ensite ' . $domain . '.conf');
        
        // Перезагрузка Apache
        shell_exec('systemctl reload apache2');
    }
    
    return true;
}

// Функция для создания базы данных
function create_database($db_name, $db_user, $db_password) {
    // Создание базы данных
    $sql = "CREATE DATABASE IF NOT EXISTS `" . $db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    db_query($sql);
    
    // Создание пользователя
    $sql = "CREATE USER '" . $db_user . "'@'localhost' IDENTIFIED BY '" . $db_password . "'";
    db_query($sql);
    
    // Назначение прав
    $sql = "GRANT ALL PRIVILEGES ON `" . $db_name . "`.* TO '" . $db_user . "'@'localhost'";
    db_query($sql);
    
    // Применение изменений
    $sql = "FLUSH PRIVILEGES";
    db_query($sql);
    
    // Добавление в базу данных панели
    $sql = "INSERT INTO databases (name, user, created_at) VALUES (?, ?, NOW())";
    db_query($sql, [$db_name, $db_user]);
    
    return true;
}

// Функция для создания пользователя
function create_user($username, $password, $email, $is_admin = false) {
    // Хеширование пароля
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Добавление пользователя в базу данных
    $sql = "INSERT INTO users (username, password, email, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())";
    db_query($sql, [$username, $password_hash, $email, $is_admin ? 1 : 0]);
    
    // Создание системного пользователя
    $user_exists = shell_exec("id -u " . $username . " 2>/dev/null");
    if (empty($user_exists)) {
        shell_exec("useradd -m -s /bin/bash " . $username);
        shell_exec("echo '" . $username . ":" . $password . "' | chpasswd");
    }
    
    return true;
}

// Функция для создания резервной копии
function create_backup($site_id, $include_database = true) {
    // Получение информации о сайте
    $sql = "SELECT * FROM sites WHERE id = ?";
    $result = db_query($sql, [$site_id]);
    $site = $result->fetch_assoc();
    
    if (!$site) {
        return false;
    }
    
    $domain = $site['domain'];
    $site_dir = SITES_DIR . '/' . $domain;
    $backup_dir = BACKUPS_DIR . '/' . $domain;
    
    // Создание директории для резервных копий
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Создание имени файла резервной копии
    $date = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . '/' . $domain . '_' . $date . '.tar.gz';
    
    // Создание архива сайта
    shell_exec("tar -czf " . $backup_file . " -C " . SITES_DIR . " " . $domain);
    
    // Резервное копирование базы данных
    if ($include_database) {
        $sql = "SELECT * FROM site_databases WHERE site_id = ?";
        $result = db_query($sql, [$site_id]);
        
        while ($db = $result->fetch_assoc()) {
            $db_backup_file = $backup_dir . '/' . $db['name'] . '_' . $date . '.sql';
            shell_exec("mysqldump " . $db['name'] . " > " . $db_backup_file);
            shell_exec("gzip " . $db_backup_file);
        }
    }
    
    // Добавление информации о резервной копии в базу данных
    $sql = "INSERT INTO backups (site_id, file_path, created_at) VALUES (?, ?, NOW())";
    db_query($sql, [$site_id, $backup_file]);
    
    return true;
}

// Функция для проверки авторизации
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // Проверка таймаута сессии
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

// Функция для логирования действий
function log_action($user_id, $action, $details = '') {
    $sql = "INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    db_query($sql, [$user_id, $action, $details]);
}

// Функция для получения информации о системе
function get_system_info() {
    $info = [];
    
    // Информация о ОС
    $info['os'] = php_uname('s') . ' ' . php_uname('r');
    
    // Информация о веб-сервере
    $info['web_server'] = $_SERVER['SERVER_SOFTWARE'];
    
    // Информация о PHP
    $info['php_version'] = phpversion();
    
    // Информация о MySQL
    $conn = db_connect();
    $result = $conn->query("SELECT VERSION() as version");
    $row = $result->fetch_assoc();
    $info['mysql_version'] = $row['version'];
    $conn->close();
    
    return $info;
}

// Функция для получения статистики использования ресурсов
function get_resource_usage() {
    $usage = [];
    
    // Загрузка CPU
    $load = sys_getloadavg();
    $usage['cpu'] = $load[0];
    
    // Использование памяти
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $usage['memory_total'] = $mem[1];
    $usage['memory_used'] = $mem[2];
    $usage['memory_percent'] = round($mem[2] / $mem[1] * 100, 2);
    
    // Использование диска
    $disk_total = disk_total_space('/');
    $disk_free = disk_free_space('/');
    $disk_used = $disk_total - $disk_free;
    $usage['disk_total'] = $disk_total;
    $usage['disk_used'] = $disk_used;
    $usage['disk_percent'] = round($disk_used / $disk_total * 100, 2);
    
    return $usage;
}
EOF
print_success "Файл функций создан"

# Создание файла для авторизации
print_message "Создание файла авторизации..."
cat > $PANEL_DIR/public/login.php << 'EOF'
<?php
session_start();
require_once '../config/config.php';
require_once '../config/functions.php';

// Перенаправление, если пользователь уже авторизован
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Обработка формы авторизации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, введите имя пользователя и пароль';
    } else {
        // Проверка учетных данных
        $sql = "SELECT * FROM users WHERE username = ?";
        $result = db_query($sql, [$username]);
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Успешная авторизация
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['last_activity'] = time();
                
                // Логирование действия
                log_action($user['id'], 'login', 'Успешная авторизация');
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверное имя пользователя или пароль';
            }
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}

// Заголовок страницы
$page_title = "CLOUD PRO V2 - Авторизация";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-form {
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-header h1 {
            color: #007bff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-login {
            width: 100%;
        }
        .alert {
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="bg-dark">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-form bg-light p-4 rounded shadow">
                    <div class="login-header">
                        <h1 class="text-primary">CLOUD PRO V2</h1>
                        <p class="text-muted">Панель управления хостингом</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['timeout'])): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-clock"></i> Ваша сессия истекла. Пожалуйста, авторизуйтесь снова.
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="login.php">
                        <div class="form-group mb-3">
                            <label for="username"><i class="fas fa-user"></i> Имя пользователя</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="password"><i class="fas fa-lock"></i> Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt"></i> Войти
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
EOF
print_success "Файл авторизации создан"

# Загрузка файлов панели управления
print_message "Загрузка файлов панели управления..."

# Создание основного файла index.php
cat > $PANEL_DIR/public/index.php << 'EOF'
<?php
session_start();
require_once '../config/config.php';
require_once '../config/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: login.php');
    exit;
}

// Заголовок страницы
$page_title = "CLOUD PRO V2 - Панель управления";
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Боковое меню -->
        <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Панель управления
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sites.php">
                            <i class="fas fa-globe"></i> Сайты
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="databases.php">
                            <i class="fas fa-database"></i> Базы данных
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Пользователи
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="backups.php">
                            <i class="fas fa-save"></i> Резервные копии
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Настройки
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Выход
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Основной контент -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Панель управления</h1>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-globe"></i> Сайты
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">0</h5>
                            <p class="card-text">Активных сайтов</p>
                            <a href="sites.php" class="btn btn-sm btn-outline-primary">Управление сайтами</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-database"></i> Базы данных
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">0</h5>
                            <p class="card-text">Активных баз данных</p>
                            <a href="databases.php" class="btn btn-sm btn-outline-success">Управление базами</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-users"></i> Пользователи
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">1</h5>
                            <p class="card-text">Активных пользователей</p>
                            <a href="users.php" class="btn btn-sm btn-outline-info">Управление пользователями</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header">
                            <i class="fas fa-server"></i> Информация о системе
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Операционная система
                                    <span class="badge bg-primary rounded-pill"><?php echo php_uname('s') . ' ' . php_uname('r'); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Веб-сервер
                                    <span class="badge bg-primary rounded-pill"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    PHP версия
                                    <span class="badge bg-primary rounded-pill"><?php echo phpversion(); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    База данных
                                    <span class="badge bg-primary rounded-pill">MariaDB</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header">
                            <i class="fas fa-tachometer-alt"></i> Использование ресурсов
                        </div>
                        <div class="card-body">
                            <?php
                            // Получение информации о загрузке CPU
                            $load = sys_getloadavg();
                            $cpu_usage = $load[0];
                            
                            // Получение информации о памяти
                            $free = shell_exec('free');
                            $free = (string)trim($free);
                            $free_arr = explode("\n", $free);
                            $mem = explode(" ", $free_arr[1]);
                            $mem = array_filter($mem);
                            $mem = array_merge($mem);
                            $memory_usage = $mem[2]/$mem[1]*100;
                            
                            // Получение информации о диске
                            $disk_total = disk_total_space('/');
                            $disk_free = disk_free_space('/');
                            $disk_used = $disk_total - $disk_free;
                            $disk_usage = ($disk_used / $disk_total) * 100;
                            ?>
                            
                            <h6>CPU</h6>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $cpu_usage; ?>%" 
                                    aria-valuenow="<?php echo $cpu_usage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($cpu_usage, 2); ?>%
                                </div>
                            </div>
                            
                            <h6>Память</h6>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $memory_usage; ?>%" 
                                    aria-valuenow="<?php echo $memory_usage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($memory_usage, 2); ?>%
                                </div>
                            </div>
                            
                            <h6>Диск</h6>
                            <div class="progress">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $disk_usage; ?>%" 
                                    aria-valuenow="<?php echo $disk_usage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($disk_usage, 2); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
EOF
print_success "Основной файл index.php создан"

# Создание файла header.php
print_message "Создание файла header.php..."
cat > $PANEL_DIR/public/header.php << 'EOF'
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'CLOUD PRO V2 - Панель управления'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-size: .875rem;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .sidebar .nav-link.active {
            color: #007bff;
        }
        
        .sidebar .nav-link:hover {
            color: #007bff;
        }
        
        .sidebar .nav-link .feather {
            margin-right: 4px;
            color: #999;
        }
        
        .sidebar .nav-link.active .feather {
            color: inherit;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        
        .navbar .form-control {
            padding: .75rem 1rem;
            border-width: 0;
            border-radius: 0;
        }
        
        .form-control-dark {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .1);
        }
        
        .form-control-dark:focus {
            border-color: transparent;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="index.php">CLOUD PRO V2</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="logout.php">Выход <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </header>
EOF
print_success "Файл header.php создан"

# Создание файла footer.php
print_message "Создание файла footer.php..."
cat > $PANEL_DIR/public/footer.php << 'EOF'
    <footer class="mt-5 py-3 text-muted border-top">
        <div class="container">
            <p class="mb-0 text-center">CLOUD PRO V2 &copy; <?php echo date('Y'); ?> - Панель управления хостингом</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script>
        // Активация текущего пункта меню
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const filename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
            
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(function(link) {
                const href = link.getAttribute('href');
                if (href === filename) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
EOF
print_success "Файл footer.php создан"

# Создание файла выхода из системы
print_message "Создание файла logout.php..."
cat > $PANEL_DIR/public/logout.php << 'EOF'
<?php
session_start();
require_once '../config/config.php';
require_once '../config/functions.php';

// Логирование действия
if (isset($_SESSION['user_id'])) {
    log_action($_SESSION['user_id'], 'logout', 'Выход из системы');
}

// Уничтожение сессии
session_unset();
session_destroy();

// Перенаправление на страницу авторизации
header('Location: login.php');
exit;
EOF
print_success "Файл logout.php создан"

# Создание файла для управления сайтами
print_message "Создание файла sites.php..."
cat > $PANEL_DIR/public/sites.php << 'EOF'
<?php
session_start();
require_once '../config/config.php';
require_once '../config/functions.php';

// Проверка авторизации
check_auth();

// Обработка формы создания сайта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_site') {
    $domain = $_POST['domain'] ?? '';
    $user = $_POST['user'] ?? '';
    $php_version = $_POST['php_version'] ?? '8.1';
    
    if (empty($domain) || empty($user)) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } else {
        // Создание сайта
        if (create_site($domain, $user, $php_version)) {
            // Логирование действия
            log_action($_SESSION['user_id'], 'create_site', 'Создан сайт: ' . $domain);
            $success = 'Сайт успешно создан';
        } else {
            $error = 'Ошибка при создании сайта';
        }
    }
}

// Получение списка сайтов
$sql = "SELECT * FROM sites ORDER BY created_at DESC";
$result = db_query($sql);
$sites = [];
while ($row = $result->fetch_assoc()) {
    $sites[] = $row;
}

// Получение списка пользователей
$sql = "SELECT * FROM users WHERE is_admin = 0";
$result = db_query($sql);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Заголовок страницы
$page_title = "CLOUD PRO V2 - Управление сайтами";
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Боковое меню -->
        <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Панель управления
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sites.php">
                            <i class="fas fa-globe"></i> Сайты
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="databases.php">
                            <i class="fas fa-database"></i> Базы данных
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Пользователи
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="backups.php">
                            <i class="fas fa-save"></i> Резервные копии
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Настройки
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Выход
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Основной контент -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Управление сайтами</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSiteModal">
                        <i class="fas fa-plus"></i> Создать сайт
                    </button>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Домен</th>
                            <th>Пользователь</th>
                            <th>PHP версия</th>
                            <th>Дата создания</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sites)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Нет доступных сайтов</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td><?php echo $site['id']; ?></td>
                                    <td><?php echo $site['domain']; ?></td>
                                    <td><?php echo $site['user']; ?></td>
                                    <td><?php echo $site['php_version']; ?></td>
                                    <td><?php echo $site['created_at']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="#" class="btn btn-outline-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-success" title="Резервная копия">
                                                <i class="fas fa-save"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Удалить">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Модальное окно для создания сайта -->
<div class="modal fade" id="createSiteModal" tabindex="-1" aria-labelledby="createSiteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSiteModalLabel">Создать новый сайт</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="sites.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_site">
                    
                    <div class="mb-3">
                        <label for="domain" class="form-label">Домен</label>
                        <input type="text" class="form-control" id="domain" name="domain" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="user" class="form-label">Пользователь</label>
                        <select class="form-select" id="user" name="user" required>
                            <option value="">Выберите пользователя</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['username']; ?>"><?php echo $user['username']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="php_version" class="form-label">Версия PHP</label>
                        <select class="form-select" id="php_version" name="php_version">
                            <option value="8.1">PHP 8.1</option>
                            <option value="7.4">PHP 7.4</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать сайт</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
EOF
print_success "Файл sites.php создан"

# Создание структуры базы данных
print_message "Создание структуры базы данных..."
mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME << 'EOF'
-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сайтов
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(100) NOT NULL UNIQUE,
    user VARCHAR(50) NOT NULL,
    php_version VARCHAR(10) NOT NULL DEFAULT '8.1',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица баз данных
CREATE TABLE IF NOT EXISTS databases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    user VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица связи сайтов и баз данных
CREATE TABLE IF NOT EXISTS site_databases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    database_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица резервных копий
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица журнала активности
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
print_success "Структура базы данных создана"

# Создание администратора
print_message "Создание администратора..."
ADMIN_PASSWORD=$(openssl rand -base64 8)
ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_DEFAULT);")

mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "INSERT INTO users (username, password, email, is_admin, created_at) VALUES ('admin', '$ADMIN_PASSWORD_HASH', 'admin@localhost', 1, NOW())"

# Обновление конфигурационного файла с хешем пароля администратора
sed -i "s/define('ADMIN_PASSWORD_HASH', '');/define('ADMIN_PASSWORD_HASH', '$ADMIN_PASSWORD_HASH');/" $PANEL_DIR/config/config.php

print_success "Администратор создан. Логин: admin, Пароль: $ADMIN_PASSWORD"

# Настройка веб-сервера для панели управления
print_message "Настройка веб-сервера для панели управления..."

if [ "$WEB_SERVER" = "nginx" ] || [ "$WEB_SERVER" = "nginx_apache" ]; then
    # Создание конфигурации Nginx для панели
    cat > /etc/nginx/sites-available/cldpro.conf << EOF
server {
    listen 80;
    server_name _;
    
    location /cldpro {
        alias $PANEL_DIR/public;
        index index.php;
        
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        }
        
        location ~ /\.ht {
            deny all;
        }
    }
}
EOF
    
    # Создание символической ссылки
    ln -sf /etc/nginx/sites-available/cldpro.conf /etc/nginx/sites-enabled/
    
    # Перезагрузка Nginx
    systemctl reload nginx
fi

if [ "$WEB_SERVER" = "apache2" ] || [ "$WEB_SERVER" = "nginx_apache" ]; then
    # Создание конфигурации Apache для панели
    cat > /etc/apache2/sites-available/cldpro.conf << EOF
Alias /cldpro $PANEL_DIR/public

<Directory $PANEL_DIR/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
EOF
    
    # Включение сайта
    a2ensite cldpro.conf
    
    # Перезагрузка Apache
    systemctl reload apache2
fi

print_success "Веб-сервер настроен"

# Установка прав доступа
print_message "Установка прав доступа..."
chown -R www-data:www-data $PANEL_DIR
chmod -R 755 $PANEL_DIR
print_success "Права доступа установлены"

# Настройка брандмауэра
print_message "Настройка брандмауэра..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 3306/tcp
ufw --force enable
print_success "Брандмауэр настроен"

# Завершение установки
clear
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                        CLOUD PRO V2                           ║"
echo "║                                                               ║"
echo "║               Панель управления хостингом                     ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
print_success "Установка CLOUD PRO V2 успешно завершена!"
echo ""
print_message "Информация для доступа к панели управления:"
echo "URL: http://IP-адрес-сервера/cldpro"
echo "Логин: admin"
echo "Пароль: $ADMIN_PASSWORD"
echo ""
print_message "Информация для доступа к базе данных:"
echo "Хост: localhost"
echo "База данных: $DB_NAME"
echo "Пользователь: $DB_USER"
echo "Пароль: $DB_PASSWORD"
echo "Root пароль MySQL: $DB_ROOT_PASSWORD"
echo ""
print_warning "Сохраните эту информацию в надежном месте!"
echo ""
print_message "Для получения дополнительной информации посетите документацию."
echo "" 
