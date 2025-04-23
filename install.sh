#!/bin/bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' 

if [ "$(id -u)" != "0" ]; then
   echo -e "${RED}Ошибка: Скрипт должен быть запущен с правами root${NC}" 1>&2
   exit 1
fi

clear
echo -e "${GREEN}"
echo "================================================================"
echo "            CloudPRO - Установка панели управления              "
echo "================================================================"
echo -e "${NC}"

INSTALL_DIR="/usr/local/CloudPRO"
DEFAULT_PORT=9999
CONFIG_FILE="$INSTALL_DIR/config/config.php"

# Генерация случайных данных для базы данных панели
PANEL_DB_NAME="cloudpro"
PANEL_DB_USER="cloudpro_user"
PANEL_DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c $(shuf -i 8-15 -n 1))
ADMIN_PASS_HASH=$(echo -n "admin123" | sha256sum | awk '{print $1}')

echo -e "${YELLOW}Проверка системы...${NC}"

if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
        echo -e "${RED}Ошибка: Поддерживаются только Ubuntu и Debian${NC}"
        exit 1
    fi
else
    echo -e "${RED}Ошибка: Невозможно определить операционную систему${NC}"
    exit 1
fi

echo -e "${GREEN}Операционная система: $PRETTY_NAME - OK${NC}"

# Определяем доступную версию PHP автоматически
if [[ "$ID" == "debian" && "$VERSION_CODENAME" == "bookworm" ]]; then
    # Debian 12 (bookworm) использует PHP 8.2 по умолчанию
    PHP_VERSION="8.2"
elif [[ "$ID" == "ubuntu" && "$VERSION_ID" == "24.04" ]]; then
    # Ubuntu 24.04 (Noble) использует PHP 8.3 по умолчанию
    PHP_VERSION="8.3"
elif [[ "$ID" == "ubuntu" && "$VERSION_ID" == "22.04" ]]; then
    # Ubuntu 22.04 использует PHP 8.1 по умолчанию
    PHP_VERSION="8.1"
else
    # По умолчанию пробуем PHP 8.2, затем падаем до более старых версий при необходимости
    PHP_VERSION="8.2"
fi

echo -e "${YELLOW}Автоматически определена версия PHP: $PHP_VERSION${NC}"
echo -e "${YELLOW}Проверка доступности выбранной версии PHP...${NC}"

# Проверяем доступность версии PHP в репозиториях
if ! apt-cache search php$PHP_VERSION-fpm 2>/dev/null | grep -q php$PHP_VERSION-fpm; then
    echo -e "${RED}Версия PHP $PHP_VERSION не найдена.${NC}"
    
    # Пробуем найти доступные версии PHP
    echo -e "${YELLOW}Поиск доступных версий PHP...${NC}"
    
    for ver in 8.3 8.2 8.1 8.0 7.4; do
        if apt-cache search php$ver-fpm 2>/dev/null | grep -q php$ver-fpm; then
            PHP_VERSION=$ver
            echo -e "${GREEN}Найдена доступная версия: PHP $PHP_VERSION${NC}"
            break
        fi
    done
fi

if [ -d "$INSTALL_DIR" ]; then
    echo -e "${YELLOW}CloudPRO уже установлен в $INSTALL_DIR${NC}"
    read -p "Хотите переустановить? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${RED}Установка отменена${NC}"
        exit 1
    fi

    BACKUP_DIR="/usr/local/CloudPRO_backup_$(date +%Y%m%d%H%M%S)"
    echo -e "${YELLOW}Создание резервной копии в $BACKUP_DIR${NC}"
    mv "$INSTALL_DIR" "$BACKUP_DIR"
fi

echo -e "${YELLOW}Обновление пакетов...${NC}"
apt update

# Убедимся, что необходимые базовые пакеты установлены
echo -e "${YELLOW}Установка основных зависимостей...${NC}"
apt install -y apt-transport-https ca-certificates curl gnupg lsb-release

echo -e "${YELLOW}Установка PHP $PHP_VERSION и других зависимостей...${NC}"

# В некоторых версиях могут различаться имена пакетов, поэтому проверяем каждый пакет
PACKAGES="nginx"

# Проверяем доступность mysql-server и mariadb-server
if apt-cache search mysql-server 2>/dev/null | grep -q "^mysql-server "; then
    PACKAGES="$PACKAGES mysql-server"
elif apt-cache search mariadb-server 2>/dev/null | grep -q "^mariadb-server "; then
    echo -e "${YELLOW}mysql-server не найден, используем mariadb-server${NC}"
    PACKAGES="$PACKAGES mariadb-server"
else
    echo -e "${RED}Ошибка: ни mysql-server, ни mariadb-server не найдены в репозитории${NC}"
    exit 1
fi

# Добавляем пакеты PHP
for pkg in fpm mysql curl zip gd mbstring xml cli; do
    if apt-cache search php$PHP_VERSION-$pkg 2>/dev/null | grep -q php$PHP_VERSION-$pkg; then
        PACKAGES="$PACKAGES php$PHP_VERSION-$pkg"
    fi
done

# Проверяем пакет php-json (в некоторых версиях включен по умолчанию)
if apt-cache search php$PHP_VERSION-json 2>/dev/null | grep -q php$PHP_VERSION-json; then
    PACKAGES="$PACKAGES php$PHP_VERSION-json"
fi

# Добавляем другие необходимые пакеты
PACKAGES="$PACKAGES unzip wget curl git"

# Проверяем наличие certbot
if apt-cache search certbot 2>/dev/null | grep -q "^certbot "; then
    PACKAGES="$PACKAGES certbot"
    
    if apt-cache search python3-certbot-nginx 2>/dev/null | grep -q python3-certbot-nginx; then
        PACKAGES="$PACKAGES python3-certbot-nginx"
    fi
fi

echo -e "${YELLOW}Установка пакетов: $PACKAGES${NC}"
apt install -y $PACKAGES

PORT_TO_USE=$DEFAULT_PORT
if command -v netstat >/dev/null 2>&1; then
    if netstat -tuln | grep -q ":$PORT_TO_USE "; then
        echo -e "${YELLOW}Порт $PORT_TO_USE занят, поиск свободного порта...${NC}"
        for port in $(seq 8000 10000); do
            if ! netstat -tuln | grep -q ":$port "; then
                PORT_TO_USE=$port
                echo -e "${GREEN}Найден свободный порт: $PORT_TO_USE${NC}"
                break
            fi
        done
    fi
elif command -v ss >/dev/null 2>&1; then
    if ss -tuln | grep -q ":$PORT_TO_USE "; then
        echo -e "${YELLOW}Порт $PORT_TO_USE занят, поиск свободного порта...${NC}"
        for port in $(seq 8000 10000); do
            if ! ss -tuln | grep -q ":$port "; then
                PORT_TO_USE=$port
                echo -e "${GREEN}Найден свободный порт: $PORT_TO_USE${NC}"
                break
            fi
        done
    fi
fi

echo -e "${YELLOW}Настройка MySQL...${NC}"
# Проверяем, запущен ли MySQL или MariaDB
MYSQL_SERVICE="mysql"
if ! systemctl is-active --quiet $MYSQL_SERVICE; then
    echo -e "${YELLOW}MySQL/MariaDB не активен, пробуем mariadb...${NC}"
    MYSQL_SERVICE="mariadb"
    if ! systemctl is-active --quiet $MYSQL_SERVICE; then
        echo -e "${YELLOW}Запуск службы MySQL/MariaDB...${NC}"
        systemctl start $MYSQL_SERVICE || {
            echo -e "${RED}Не удалось запустить MySQL/MariaDB. Пробуем запустить mysql...${NC}"
            systemctl start mysql || {
                echo -e "${RED}Ошибка: Не удалось запустить сервер MySQL/MariaDB${NC}"
                exit 1
            }
            MYSQL_SERVICE="mysql"
        }
    fi
fi

echo -e "${GREEN}Сервер баз данных $MYSQL_SERVICE запущен${NC}"

# Создаем базу данных и пользователя для панели
echo -e "${YELLOW}Создание базы данных для панели управления...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS $PANEL_DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$PANEL_DB_USER'@'localhost' IDENTIFIED BY '$PANEL_DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $PANEL_DB_NAME.* TO '$PANEL_DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${YELLOW}Создание структуры базы данных...${NC}"
mysql -e "USE $PANEL_DB_NAME; 
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    ssl_enabled TINYINT(1) DEFAULT 0,
    php_version VARCHAR(10) DEFAULT '$PHP_VERSION',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS db_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    username VARCHAR(64) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);"

mysql -e "USE $PANEL_DB_NAME; INSERT INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASS_HASH', 'admin@localhost', 'admin') ON DUPLICATE KEY UPDATE password='$ADMIN_PASS_HASH';"

echo -e "${YELLOW}Установка CloudPRO...${NC}"
mkdir -p $INSTALL_DIR
git clone https://github.com/VerTusOffical/cloudpro.git $INSTALL_DIR || {
    echo -e "${YELLOW}Локальная установка...${NC}"
    mkdir -p $INSTALL_DIR/{config,modules,public,logs,tmp}
    mkdir -p $INSTALL_DIR/modules/{sites,databases,ssl,logs,filemanager,users}
    
    # Создаем публичный каталог с простым index.html если git-клонирование не удалось
    mkdir -p $INSTALL_DIR/public
    cat > $INSTALL_DIR/public/index.html << EOF
<!DOCTYPE html>
<html>
<head>
    <title>CloudPRO</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>CloudPRO установлен</h1>
    <p>Если вы видите эту страницу, значит PHP не настроен правильно.</p>
</body>
</html>
EOF

    # Создаем базовый index.php для проверки работы PHP
    cat > $INSTALL_DIR/public/index.php << EOF
<?php
    // Базовый файл для проверки работы PHP
    echo '<h1>CloudPRO установлен</h1>';
    echo '<p>PHP работает корректно.</p>';
    phpinfo();
?>
EOF
}

# Устанавливаем правильные права
echo -e "${YELLOW}Настройка прав доступа...${NC}"
find $INSTALL_DIR -type d -exec chmod 755 {} \;
find $INSTALL_DIR -type f -exec chmod 644 {} \;
chmod -R 755 $INSTALL_DIR/public
if [ -d "$INSTALL_DIR/tmp" ]; then
    chmod -R 777 $INSTALL_DIR/tmp
fi
if [ -d "$INSTALL_DIR/logs" ]; then
    chmod -R 755 $INSTALL_DIR/logs
fi
chown -R www-data:www-data $INSTALL_DIR

mkdir -p $INSTALL_DIR/config
cat > $CONFIG_FILE << EOF
<?php
// CloudPRO Configuration

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', '$PANEL_DB_NAME');
define('DB_USER', '$PANEL_DB_USER');
define('DB_PASS', '$PANEL_DB_PASS');

// Application settings
define('APP_URL', 'http://' . \$_SERVER['SERVER_ADDR'] . ':$PORT_TO_USE');
define('APP_PORT', $PORT_TO_USE);
define('APP_PATH', '$INSTALL_DIR');
define('LOG_PATH', APP_PATH . '/logs');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SALT', '$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 32)');
define('APP_VERSION', '1.0.0');
?>
EOF

# Создаем директорию для логов, если она не существует
mkdir -p $INSTALL_DIR/logs
chown -R www-data:www-data $INSTALL_DIR/logs

# Создаем публичную директорию, если она не существует
mkdir -p $INSTALL_DIR/public

# Убедимся, что в публичной директории есть индексные файлы
if [ ! -f "$INSTALL_DIR/public/index.php" ]; then
    echo -e "${YELLOW}Создание index.php...${NC}"
    cat > $INSTALL_DIR/public/index.php << EOF
<?php
// Проверка наличия основных файлов
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
} else {
    // Если основных файлов нет, показываем информацию о системе
    echo '<h1>CloudPRO установлен</h1>';
    echo '<p>PHP работает корректно.</p>';
    
    // Информация о системе
    echo '<h2>Информация о системе:</h2>';
    echo '<ul>';
    echo '<li>PHP версия: ' . phpversion() . '</li>';
    echo '<li>Сервер: ' . \$_SERVER['SERVER_SOFTWARE'] . '</li>';
    echo '<li>Документ root: ' . \$_SERVER['DOCUMENT_ROOT'] . '</li>';
    echo '</ul>';
    
    // Проверка соединения с базой данных
    echo '<h2>Проверка базы данных:</h2>';
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        try {
            \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (\$conn->connect_errno) {
                echo '<p style="color:red">Ошибка соединения с базой данных: ' . \$conn->connect_error . '</p>';
            } else {
                echo '<p style="color:green">Соединение с базой данных успешно!</p>';
                \$conn->close();
            }
        } catch (Exception \$e) {
            echo '<p style="color:red">Ошибка: ' . \$e->getMessage() . '</p>';
        }
    } else {
        echo '<p style="color:orange">Конфигурация базы данных не определена.</p>';
    }
}
?>
EOF
fi

# Создаем .htaccess для Apache (если используется)
cat > $INSTALL_DIR/public/.htaccess << EOF
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?/$1 [L]
</IfModule>
EOF

cat > /etc/nginx/sites-available/cloudpro << EOF
server {
    listen $PORT_TO_USE;
    server_name _;
    
    root $INSTALL_DIR/public;
    index index.php index.html;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
EOF

# Создаем символическую ссылку и удаляем стандартную конфигурацию
ln -sf /etc/nginx/sites-available/cloudpro /etc/nginx/sites-enabled/
if [ -f /etc/nginx/sites-enabled/default ]; then
    rm -f /etc/nginx/sites-enabled/default
fi

echo -e "${YELLOW}Перезапуск сервисов...${NC}"
systemctl restart nginx
systemctl restart php${PHP_VERSION}-fpm

# Проверяем IP-адрес сервера
SERVER_IP=$(hostname -I | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="ваш_ip_адрес"
fi

echo -e "${GREEN}"
echo "================================================================"
echo "       CloudPRO успешно установлен и готов к работе!            "
echo "================================================================"
echo -e "${NC}"
echo -e "URL панели: ${GREEN}http://${SERVER_IP}:$PORT_TO_USE${NC}"
echo -e "Логин: ${GREEN}admin${NC}"
echo -e "Пароль: ${GREEN}admin123${NC}"
echo -e "Версия PHP: ${GREEN}$PHP_VERSION${NC}"
echo
echo -e "${YELLOW}Рекомендуется сменить пароль после первого входа!${NC}"
echo "================================================================"

exit 0 