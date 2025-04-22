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
DB_NAME="cloudpro"
DB_USER="cloudpro_user"
DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
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
apt upgrade -y

echo -e "${YELLOW}Установка зависимостей...${NC}"

# Определение версии PHP для установки
# В Ubuntu 24.04 (Noble) используется PHP 8.3 по умолчанию
PHP_VERSION="8.1"
if [[ "$VERSION_ID" == "24.04" ]]; then
    PHP_VERSION="8.3"
elif [[ "$VERSION_ID" == "22.04" ]]; then
    PHP_VERSION="8.1"
fi

echo -e "${YELLOW}Будет установлена версия PHP ${PHP_VERSION}${NC}"

# Установка зависимостей
apt install -y nginx mysql-server php$PHP_VERSION-fpm php$PHP_VERSION-mysql php$PHP_VERSION-curl \
    php$PHP_VERSION-zip php$PHP_VERSION-gd php$PHP_VERSION-mbstring php$PHP_VERSION-xml \
    php$PHP_VERSION-cli php$PHP_VERSION-json unzip wget curl git certbot python3-certbot-nginx

PORT_TO_USE=$DEFAULT_PORT
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

echo -e "${YELLOW}Настройка MySQL...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${YELLOW}Создание структуры базы данных...${NC}"
mysql -e "USE $DB_NAME; 
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
    php_version VARCHAR(10) DEFAULT '8.1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS databases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    username VARCHAR(64) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);"

mysql -e "USE $DB_NAME; INSERT INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASS_HASH', 'admin@localhost', 'admin') ON DUPLICATE KEY UPDATE password='$ADMIN_PASS_HASH';"

echo -e "${YELLOW}Установка CloudPRO...${NC}"
mkdir -p $INSTALL_DIR
git clone https://github.com/VerTusOffical/cloudpro.git $INSTALL_DIR || {
    echo -e "${YELLOW}Локальная установка...${NC}"
    mkdir -p $INSTALL_DIR/{config,modules,public,logs,tmp}
    mkdir -p $INSTALL_DIR/modules/{sites,databases,ssl,logs,filemanager,users}
}

chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 $INSTALL_DIR

mkdir -p $INSTALL_DIR/config
cat > $CONFIG_FILE << EOF
<?php
// CloudPRO Configuration

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');

// Application settings
define('APP_URL', 'http://' . \$_SERVER['SERVER_ADDR'] . ':$PORT_TO_USE');
define('APP_PORT', $PORT_TO_USE);
define('APP_PATH', '$INSTALL_DIR');
define('LOG_PATH', APP_PATH . '/logs');

// Security settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SALT', '$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 32)');
?>
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

ln -sf /etc/nginx/sites-available/cloudpro /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

echo -e "${YELLOW}Перезапуск сервисов...${NC}"
systemctl restart nginx mysql php${PHP_VERSION}-fpm

echo -e "${GREEN}"
echo "================================================================"
echo "       CloudPRO успешно установлен и готов к работе!            "
echo "================================================================"
echo -e "${NC}"
echo -e "URL панели: ${GREEN}http://$(curl -s ifconfig.me):$PORT_TO_USE${NC}"
echo -e "Логин: ${GREEN}admin${NC}"
echo -e "Пароль: ${GREEN}admin123${NC}"
echo
echo -e "${YELLOW}Рекомендуется сменить пароль после первого входа!${NC}"
echo "================================================================"

exit 0 