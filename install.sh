#!/bin/bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' 

INSTALL_DIR="/usr/local/CloudPRO"
DEFAULT_PORT=9999
CONFIG_FILE="$INSTALL_DIR/config/config.php"

# Функция для проверки существования панели
check_panel_exists() {
    if [ ! -d "$INSTALL_DIR" ]; then
        echo -e "${RED}Ошибка: Панель CloudPRO не установлена в $INSTALL_DIR${NC}" 1>&2
        return 1
    fi
    return 0
}

# Функция для удаления панели
remove_panel() {
    echo -e "${YELLOW}Удаление панели CloudPRO...${NC}"
    
    # Проверяем, существует ли панель
    if ! check_panel_exists; then
        exit 1
    fi
    
    read -p "Вы уверены, что хотите полностью удалить панель CloudPRO? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Операция отменена.${NC}"
        exit 0
    fi
    
    # Считываем информацию о базе данных
    if [ -f "$CONFIG_FILE" ]; then
        DB_NAME=$(grep -oP "define\('DB_NAME', '\K[^']+" "$CONFIG_FILE" || echo "")
        DB_USER=$(grep -oP "define\('DB_USER', '\K[^']+" "$CONFIG_FILE" || echo "")
    fi
    
    # Удаляем конфигурацию Nginx
    if [ -f /etc/nginx/sites-enabled/cloudpro ]; then
        rm -f /etc/nginx/sites-enabled/cloudpro
    fi
    
    if [ -f /etc/nginx/sites-available/cloudpro ]; then
        rm -f /etc/nginx/sites-available/cloudpro
    fi
    
    # Перезапускаем Nginx
    systemctl restart nginx 2>/dev/null || true
    
    # Удаляем директорию панели
    rm -rf "$INSTALL_DIR"
    
    # Удаляем базу данных, если указано
    read -p "Удалить базу данных панели? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]] && [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
        echo -e "${YELLOW}Удаление базы данных $DB_NAME и пользователя $DB_USER...${NC}"
        mysql -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null || echo -e "${RED}Не удалось удалить базу данных${NC}"
        mysql -e "DROP USER IF EXISTS '$DB_USER'@'localhost';" 2>/dev/null || echo -e "${RED}Не удалось удалить пользователя БД${NC}"
        mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true
    fi
    
    echo -e "${GREEN}Панель CloudPRO успешно удалена.${NC}"
    exit 0
}

# Функция для восстановления панели
repair_panel() {
    echo -e "${YELLOW}Восстановление панели CloudPRO...${NC}"
    
    # Проверяем, существует ли панель
    if ! check_panel_exists; then
        exit 1
    fi
    
    # Проверяем наличие файла конфигурации
    if [ ! -f "$CONFIG_FILE" ]; then
        echo -e "${RED}Ошибка: Файл конфигурации не найден. Установите панель заново.${NC}"
        exit 1
    fi
    
    # Считываем информацию из конфигурации
    DB_NAME=$(grep -oP "define\('DB_NAME', '\K[^']+" "$CONFIG_FILE" || echo "")
    DB_USER=$(grep -oP "define\('DB_USER', '\K[^']+" "$CONFIG_FILE" || echo "")
    DB_PASS=$(grep -oP "define\('DB_PASS', '\K[^']+" "$CONFIG_FILE" || echo "")
    
    if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
        echo -e "${RED}Ошибка: Не удалось получить данные для подключения к базе данных.${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Проверка соединения с базой данных...${NC}"
    # Проверяем соединение с базой данных
    if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null; then
        echo -e "${RED}Ошибка: Не удалось подключиться к базе данных $DB_NAME.${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}Соединение с базой данных успешно установлено.${NC}"
    
    # Проверяем и создаем необходимые таблицы
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
    );

    CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        api_key VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        usage_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP NULL DEFAULT NULL,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );"
    
    # Проверяем наличие пользователя admin
    ADMIN_EXISTS=$(mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM $DB_NAME.users WHERE username='admin';" | grep -v "COUNT" || echo "0")
    
    # Если админа нет, создаем его
    if [ "$ADMIN_EXISTS" = "0" ]; then
        ADMIN_PASS_HASH=$(echo -n "admin123" | sha256sum | awk '{print $1}')
        mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME; INSERT INTO users (username, password, email, role) VALUES ('admin', '$ADMIN_PASS_HASH', 'admin@localhost', 'admin') ON DUPLICATE KEY UPDATE password='$ADMIN_PASS_HASH';"
        echo -e "${GREEN}Создан пользователь admin с паролем admin123${NC}"
    fi
    
    # Проверяем и настраиваем права доступа
    echo -e "${YELLOW}Настройка прав доступа...${NC}"
    find "$INSTALL_DIR" -type d -exec chmod 755 {} \;
    find "$INSTALL_DIR" -type f -exec chmod 644 {} \;
    chmod -R 755 "$INSTALL_DIR/public"
    if [ -d "$INSTALL_DIR/tmp" ]; then
        chmod -R 777 "$INSTALL_DIR/tmp"
    fi
    if [ -d "$INSTALL_DIR/logs" ]; then
        chmod -R 755 "$INSTALL_DIR/logs"
    fi
    chown -R www-data:www-data "$INSTALL_DIR"
    
    # Проверяем наличие nginx конфигурации
    if [ ! -f /etc/nginx/sites-available/cloudpro ]; then
        echo -e "${YELLOW}Создание конфигурации nginx...${NC}"
        
        # Определяем версию PHP
        PHP_VERSION=""
        for ver in 8.3 8.2 8.1 8.0 7.4; do
            if [ -S "/var/run/php/php${ver}-fpm.sock" ]; then
                PHP_VERSION=$ver
                break
            fi
        done
        
        if [ -z "$PHP_VERSION" ]; then
            echo -e "${RED}Не удалось определить версию PHP.${NC}"
            PHP_VERSION="8.1" # Значение по умолчанию
        fi
        
        # Находим свободный порт
        PORT_TO_USE=$DEFAULT_PORT
        if command -v ss >/dev/null 2>&1; then
            if ss -tuln | grep -q ":$PORT_TO_USE "; then
                for port in $(seq 8000 10000); do
                    if ! ss -tuln | grep -q ":$port "; then
                        PORT_TO_USE=$port
                        break
                    fi
                done
            fi
        fi
        
        # Создаем конфигурацию nginx
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
        
        # Создаем символическую ссылку
        ln -sf /etc/nginx/sites-available/cloudpro /etc/nginx/sites-enabled/
        
        # Перезапускаем nginx
        systemctl restart nginx
        
        echo -e "${GREEN}Nginx сконфигурирован на порту $PORT_TO_USE${NC}"
    fi
    
    # Проверяем наличие директорий и базовых файлов
    mkdir -p "$INSTALL_DIR/public" "$INSTALL_DIR/logs" "$INSTALL_DIR/tmp"
    
    # Выводим информацию об установке
    IP_ADDRESS=$(hostname -I | awk '{print $1}')
    PORT=$(grep -oP 'listen \K\d+' /etc/nginx/sites-available/cloudpro || echo "$DEFAULT_PORT")
    
    echo -e "${GREEN}"
    echo "================================================================"
    echo "       CloudPRO успешно восстановлен и готов к работе!           "
    echo "================================================================"
    echo -e "${NC}"
    echo -e "URL панели: ${GREEN}http://${IP_ADDRESS}:${PORT}${NC}"
    echo -e "Логин: ${GREEN}admin${NC}"
    echo -e "Пароль: ${GREEN}admin123${NC}"
    echo
    echo -e "База данных: ${GREEN}$DB_NAME${NC}"
    echo -e "Пользователь БД: ${GREEN}$DB_USER${NC}"
    echo
    echo -e "${YELLOW}Рекомендуется сменить пароль после первого входа!${NC}"
    echo "================================================================"
    
    exit 0
}

# Проверяем, запущен ли скрипт от имени root
if [ "$(id -u)" != "0" ]; then
   echo -e "${RED}Ошибка: Скрипт должен быть запущен с правами root${NC}" 1>&2
   exit 1
fi

# Обработка аргументов командной строки
if [ "$1" = "remove" ]; then
    remove_panel
elif [ "$1" = "repair" ]; then
    repair_panel
fi

clear
echo -e "${GREEN}"
echo "================================================================"
echo "            CloudPRO - Установка панели управления              "
echo "================================================================"
echo -e "${NC}"

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
echo -e "База данных: ${GREEN}$DB_NAME${NC}"
echo -e "Пользователь БД: ${GREEN}$DB_USER${NC}"
echo -e "Пароль БД: ${GREEN}$DB_PASS${NC}"
echo -e "Файл конфигурации: ${GREEN}$CONFIG_FILE${NC}"
echo
echo -e "${YELLOW}Рекомендуется сменить пароль после первого входа!${NC}"
echo -e "${YELLOW}Для управления панелью доступны следующие команды:${NC}"
echo -e " - ${GREEN}$0 repair${NC} - восстановление панели"
echo -e " - ${GREEN}$0 remove${NC} - удаление панели"
echo "================================================================"

exit 0