# CloudPRO

CloudPRO - современная панель управления сервером для VPS на Ubuntu/Debian, являющаяся аналогом ISPmanager с базовыми функциями для управления веб-хостингом.

## Возможности

- Создание и управление сайтами (домен + директория)
- Автоматическое получение и привязка SSL-сертификатов Let's Encrypt
- Управление базами данных MySQL/MariaDB
- Просмотр системных логов
- Файловый менеджер
- Управление пользователями панели
- REST API для интеграции с внешними системами

## Установка

```bash
wget -O cloudpro_install.sh https://raw.githubusercontent.com/VerTusOffical/cloudpro/main/install.sh
chmod +x cloudpro_install.sh
sudo ./cloudpro_install.sh
```

После установки панель будет доступна по адресу: `http://YOUR_SERVER_IP:9999`

Логин/пароль по умолчанию: `admin` / `admin123`

### Особенности установки

- Скрипт автоматически создаст базу данных для панели и сгенерирует случайный пароль
- Все параметры доступа будут сохранены в файле `/usr/local/CloudPRO/config/config.php`
- После установки рекомендуется сменить пароль администратора

### Управление панелью

CloudPRO включает встроенные команды для управления:

```bash
# Восстановление панели (проверка структуры БД, прав доступа и т.д.)
sudo ./cloudpro_install.sh repair

# Удаление панели
sudo ./cloudpro_install.sh remove
```

## Локальная разработка

Для локальной разработки:

1. Клонировать репозиторий
2. Создать копию `config/config.example.php` и назвать её `config/config.php`
3. Настроить параметры подключения к базе данных
4. Убедиться, что директории `logs` и `public` имеют необходимые права доступа

## Решение проблем

Если вы столкнулись с ошибками при установке или первом запуске:

1. Просмотр логов ошибок PHP и Nginx:
   ```bash
   sudo tail -n 100 /var/log/nginx/error.log
   ```

2. Восстановление панели может решить большинство проблем:
   ```bash
   sudo ./cloudpro_install.sh repair
   ```

3. Включение отображения ошибок PHP - добавьте в начало `bootstrap.php`:
   ```php
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
   ```

Подробная информация о поиске и устранении неисправностей доступна в файле [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## Системные требования

- Ubuntu 20.04+ или Debian 11+
- Минимум 1 ГБ RAM
- 10 ГБ свободного места на диске
- PHP 7.4+ (рекомендуется PHP 8.0+)
- MySQL 5.7+ или MariaDB 10.3+
- Nginx или Apache

## Лицензия

MIT 