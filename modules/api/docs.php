<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Документация</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/dashboard">Главная</a></li>
                    <li class="breadcrumb-item"><a href="/api">API</a></li>
                    <li class="breadcrumb-item active">Документация</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">REST API CloudPRO</h3>
                    </div>
                    <div class="card-body">
                        <p>
                            REST API CloudPRO предоставляет программный доступ к функциям и данным вашего сервера. 
                            Используйте его для интеграции с внешними системами или для создания собственных инструментов управления.
                        </p>
                        
                        <h4 class="mt-4">Аутентификация</h4>
                        <p>
                            Для доступа к API необходим API ключ. Существует несколько способов его передачи:
                        </p>
                        
                        <ul>
                            <li>
                                <strong>Заголовок Authorization</strong>: <code>Authorization: Bearer YOUR_API_KEY</code>
                            </li>
                            <li>
                                <strong>Заголовок X-API-Key</strong>: <code>X-API-Key: YOUR_API_KEY</code>
                            </li>
                            <li>
                                <strong>Параметр URL</strong>: <code>?api_key=YOUR_API_KEY</code>
                            </li>
                        </ul>
                        
                        <h4 class="mt-4">Базовый URL</h4>
                        <p>
                            Базовый URL для всех API запросов: <code>https://your-server.com/api/v1/</code>
                        </p>
                        
                        <h4 class="mt-4">Формат ответа</h4>
                        <p>
                            Все ответы API возвращаются в формате JSON. В случае успешного выполнения запроса, 
                            ответ будет содержать код статуса 200 OK и соответствующие данные. В случае ошибки, 
                            ответ будет содержать код ошибки (4xx или 5xx) и объект с полем <code>error</code>, 
                            описывающим причину ошибки.
                        </p>
                        
                        <h4 class="mt-4">Коды ошибок</h4>
                        <ul>
                            <li><strong>400 Bad Request</strong> - Неверные параметры запроса</li>
                            <li><strong>401 Unauthorized</strong> - Не указан API ключ или он недействителен</li>
                            <li><strong>403 Forbidden</strong> - Недостаточно прав для выполнения операции</li>
                            <li><strong>404 Not Found</strong> - Запрашиваемый ресурс не найден</li>
                            <li><strong>500 Internal Server Error</strong> - Внутренняя ошибка сервера</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Endpoints -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Доступные Endpoints</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion" id="apiEndpoints">
                            <!-- Статус сервера -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingStatus">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseStatus" aria-expanded="true" aria-controls="collapseStatus">
                                            <span class="badge badge-success">GET</span> /api/v1/status - Статус сервера
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseStatus" class="collapse show" aria-labelledby="headingStatus" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Получение статуса и базовой информации о сервере.</p>
                                        <p><strong>Аутентификация:</strong> Не требуется</p>
                                        <p><strong>Параметры запроса:</strong> Отсутствуют</p>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X GET https://your-server.com/api/v1/status</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "status": "ok",
  "version": "1.0",
  "uptime": "up 10 days, 4 hours, 25 minutes",
  "datetime": "2023-06-01 12:34:56",
  "hostname": "cloud-server"
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Список сайтов -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingSites">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseSites" aria-expanded="false" aria-controls="collapseSites">
                                            <span class="badge badge-success">GET</span> /api/v1/sites - Список сайтов
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseSites" class="collapse" aria-labelledby="headingSites" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Получение списка всех сайтов.</p>
                                        <p><strong>Аутентификация:</strong> Требуется</p>
                                        <p><strong>Параметры запроса:</strong> Отсутствуют</p>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X GET https://your-server.com/api/v1/sites -H "X-API-Key: YOUR_API_KEY"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "sites": [
    {
      "id": 1,
      "domain": "example.com",
      "path": "/example_com",
      "status": "active"
    },
    {
      "id": 2,
      "domain": "test.com",
      "path": "/test_com",
      "status": "active"
    }
  ]
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Детали сайта -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingSiteDetails">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseSiteDetails" aria-expanded="false" aria-controls="collapseSiteDetails">
                                            <span class="badge badge-success">GET</span> /api/v1/sites/{id} - Детали сайта
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseSiteDetails" class="collapse" aria-labelledby="headingSiteDetails" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Получение подробной информации о сайте.</p>
                                        <p><strong>Аутентификация:</strong> Требуется</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>id</code> - ID сайта (обязательный, в URL)</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X GET https://your-server.com/api/v1/sites/1 -H "X-API-Key: YOUR_API_KEY"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "site": {
    "id": 1,
    "domain": "example.com",
    "path": "/example_com",
    "type": "php",
    "status": "active",
    "created_at": "2023-05-15 10:30:00",
    "disk_usage": "156.4 MB",
    "files_count": "742"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Статистика -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingStats">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseStats" aria-expanded="false" aria-controls="collapseStats">
                                            <span class="badge badge-success">GET</span> /api/v1/stats - Статистика сервера
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseStats" class="collapse" aria-labelledby="headingStats" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Получение статистики использования ресурсов сервера.</p>
                                        <p><strong>Аутентификация:</strong> Требуется</p>
                                        <p><strong>Параметры запроса:</strong> Отсутствуют</p>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X GET https://your-server.com/api/v1/stats -H "X-API-Key: YOUR_API_KEY"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "stats": {
    "cpu": "15.6",
    "memory": {
      "total": "16.0 GB",
      "used": "8.2 GB",
      "free": "7.8 GB"
    },
    "disk": {
      "total": "500.0 GB",
      "free": "350.5 GB",
      "used": "149.5 GB"
    },
    "websites": 8,
    "databases": 12,
    "users": 5
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Логи -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingLogs">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseLogs" aria-expanded="false" aria-controls="collapseLogs">
                                            <span class="badge badge-success">GET</span> /api/v1/logs - Логи сервера
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseLogs" class="collapse" aria-labelledby="headingLogs" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Получение логов сервера.</p>
                                        <p><strong>Аутентификация:</strong> Требуется</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>type</code> - Тип лога (опционально, по умолчанию: app): app, nginx, mysql, system</li>
                                            <li><code>lines</code> - Количество строк (опционально, по умолчанию: 50)</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X GET "https://your-server.com/api/v1/logs?type=nginx&lines=100" -H "X-API-Key: YOUR_API_KEY"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "log": {
    "type": "nginx",
    "lines": 100,
    "content": [
      "2023/06/01 12:34:56 [error] 12345#0: *123 open() \"/var/www/html/favicon.ico\" failed (2: No such file or directory), client: 192.168.1.1, server: example.com, request: \"GET /favicon.ico HTTP/1.1\", host: \"example.com\"",
      "2023/06/01 12:35:12 [error] 12345#0: *124 open() \"/var/www/html/robots.txt\" failed (2: No such file or directory), client: 192.168.1.1, server: example.com, request: \"GET /robots.txt HTTP/1.1\", host: \"example.com\""
    ]
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Авторизация -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingAuth">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseAuth" aria-expanded="false" aria-controls="collapseAuth">
                                            <span class="badge badge-primary">POST</span> /api/v1/auth - Авторизация
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseAuth" class="collapse" aria-labelledby="headingAuth" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Авторизация пользователя и получение API ключа.</p>
                                        <p><strong>Аутентификация:</strong> Не требуется</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>username</code> - Имя пользователя (обязательный)</li>
                                            <li><code>password</code> - Пароль (обязательный)</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X POST https://your-server.com/api/v1/auth -d "username=admin&password=password"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "success": true,
  "api_key": "f8e7d6c5b4a3210f8e7d6c5b4a32109",
  "user": {
    "id": 1,
    "username": "admin",
    "role": "admin"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Создание сайта -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingCreateSite">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseCreateSite" aria-expanded="false" aria-controls="collapseCreateSite">
                                            <span class="badge badge-primary">POST</span> /api/v1/sites/create - Создание сайта
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseCreateSite" class="collapse" aria-labelledby="headingCreateSite" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Создание нового сайта.</p>
                                        <p><strong>Аутентификация:</strong> Требуется (права администратора)</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>domain</code> - Доменное имя (обязательный)</li>
                                            <li><code>type</code> - Тип сайта (опционально, по умолчанию: php). Допустимые значения: php, static</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X POST https://your-server.com/api/v1/sites/create -H "X-API-Key: YOUR_API_KEY" -d "domain=newsite.com&type=php"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "success": true,
  "site": {
    "id": 3,
    "domain": "newsite.com",
    "path": "/newsite_com",
    "type": "php",
    "status": "active"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Удаление сайта -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingDeleteSite">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseDeleteSite" aria-expanded="false" aria-controls="collapseDeleteSite">
                                            <span class="badge badge-primary">POST</span> /api/v1/sites/delete - Удаление сайта
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseDeleteSite" class="collapse" aria-labelledby="headingDeleteSite" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Удаление сайта.</p>
                                        <p><strong>Аутентификация:</strong> Требуется (права администратора)</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>id</code> - ID сайта (обязательный)</li>
                                            <li><code>remove_files</code> - Удалить файлы сайта (опционально, по умолчанию: false)</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X POST https://your-server.com/api/v1/sites/delete -H "X-API-Key: YOUR_API_KEY" -d "id=3&remove_files=true"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "success": true
}</code></pre>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Создание базы данных -->
                            <div class="card mb-0">
                                <div class="card-header" id="headingCreateDb">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseCreateDb" aria-expanded="false" aria-controls="collapseCreateDb">
                                            <span class="badge badge-primary">POST</span> /api/v1/databases/create - Создание базы данных
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseCreateDb" class="collapse" aria-labelledby="headingCreateDb" data-parent="#apiEndpoints">
                                    <div class="card-body">
                                        <p><strong>Описание:</strong> Создание новой базы данных MySQL.</p>
                                        <p><strong>Аутентификация:</strong> Требуется (права администратора)</p>
                                        <p><strong>Параметры запроса:</strong></p>
                                        <ul>
                                            <li><code>name</code> - Имя базы данных (обязательный)</li>
                                            <li><code>user</code> - Имя пользователя базы данных (обязательный)</li>
                                            <li><code>password</code> - Пароль пользователя базы данных (обязательный)</li>
                                        </ul>
                                        <p><strong>Пример запроса:</strong></p>
                                        <pre><code>curl -X POST https://your-server.com/api/v1/databases/create -H "X-API-Key: YOUR_API_KEY" -d "name=newdb&user=newuser&password=password123"</code></pre>
                                        <p><strong>Пример ответа:</strong></p>
                                        <pre><code>{
  "success": true,
  "database": {
    "id": 13,
    "name": "newdb",
    "user": "newuser"
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section> 