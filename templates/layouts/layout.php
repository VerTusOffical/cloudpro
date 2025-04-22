<?php
/**
 * CloudPRO - Основной макет приложения
 * @var string $content Содержимое шаблона для вставки в макет
 */

// Проверка авторизации для отображения меню
$isLoggedIn = isset($user);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudPRO - Панель управления VPS</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/fontawesome.min.css">
    <script src="/assets/js/jquery.min.js"></script>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="/">CloudPRO</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php if ($isLoggedIn): ?>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="/?action=logout">Выйти</a>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <div class="container-fluid">
        <div class="row">
            <?php if ($isLoggedIn): ?>
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/dashboard">
                                <i class="fa fa-tachometer-alt"></i> Панель управления
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/sites">
                                <i class="fa fa-globe"></i> Сайты
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/databases">
                                <i class="fa fa-database"></i> Базы данных
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/filemanager">
                                <i class="fa fa-folder"></i> Файловый менеджер
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/ssl">
                                <i class="fa fa-lock"></i> SSL сертификаты
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/logs">
                                <i class="fa fa-list"></i> Журналы
                            </a>
                        </li>
                        <?php if (isset($user) && $user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/users">
                                <i class="fa fa-users"></i> Пользователи
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>

            <main class="<?php echo $isLoggedIn ? 'col-md-9 ms-sm-auto col-lg-10 px-md-4' : 'col-12'; ?>">
                <?php echo $content; ?>
            </main>
        </div>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">CloudPRO &copy; <?php echo date('Y'); ?></span>
        </div>
    </footer>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
</body>
</html> 