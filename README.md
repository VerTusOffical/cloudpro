# CloudPRO

CloudPRO - современная панель управления сервером для VPS на Ubuntu/Debian, являющаяся аналогом ISPmanager с базовыми функциями для управления веб-хостингом.

## Возможности

- Создание и управление сайтами (домен + директория)
- Автоматическое получение и привязка SSL-сертификатов Let's Encrypt
- Управление базами данных MySQL
- Просмотр системных логов
- Файловый менеджер
- Управление пользователями панели

## Установка

```bash
wget -O cloudpro_install.sh https://raw.githubusercontent.com/VerTusOffical/cloudpro/main/install.sh
chmod +x cloudpro_install.sh
sudo ./cloudpro_install.sh
```

После установки панель будет доступна по адресу: `http://YOUR_SERVER_IP:9999`

Логин/пароль по умолчанию: `admin` / `admin123`

## Системные требования

- Ubuntu 20.04+ или Debian 11+
- Минимум 1 ГБ RAM
- 10 ГБ свободного места на диске

## Лицензия

MIT 