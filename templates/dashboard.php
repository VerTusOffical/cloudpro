<?php
/**
 * Шаблон главной страницы панели управления CloudPRO
 * @var array $user Информация о текущем пользователе
 * @var array $stats Статистика модулей
 * @var array $modules Загруженные модули
 */
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Панель управления</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="/users/profile" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-user"></i> Профиль
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Информация о системе</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><i class="fa fa-server fa-fw text-primary"></i></h3>
                                <h4>Сервер</h4>
                                <p><?php echo php_uname('n'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><i class="fa fa-microchip fa-fw text-success"></i></h3>
                                <h4>Процессор</h4>
                                <p><?php echo isset($_SERVER['PROCESSOR_IDENTIFIER']) ? $_SERVER['PROCESSOR_IDENTIFIER'] : 'Нет данных'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><i class="fa fa-memory fa-fw text-info"></i></h3>
                                <h4>Память</h4>
                                <p><?php
                                    $memInfo = function_exists('shell_exec') ? shell_exec('free -m | grep Mem') : '';
                                    if ($memInfo) {
                                        $values = preg_split('/\s+/', $memInfo);
                                        echo isset($values[1]) ? $values[1] . ' МБ' : 'Нет данных';
                                    } else {
                                        echo 'Нет данных';
                                    }
                                ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3><i class="fa fa-hdd fa-fw text-warning"></i></h3>
                                <h4>Диск</h4>
                                <p><?php
                                    $disk = function_exists('disk_free_space') ? disk_free_space('/') : 0;
                                    $totalDisk = function_exists('disk_total_space') ? disk_total_space('/') : 0;
                                    if ($disk && $totalDisk) {
                                        echo formatFileSize($disk) . ' / ' . formatFileSize($totalDisk);
                                    } else {
                                        echo 'Нет данных';
                                    }
                                ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php if (isset($stats['sites']) && isset($stats['sites']['sites_count'])): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Сайты</h5>
            </div>
            <div class="card-body text-center">
                <h3><?php echo $stats['sites']['sites_count']; ?></h3>
                <p>Активных сайтов</p>
                <?php if (isset($stats['sites']['ssl_enabled'])): ?>
                <h5><?php echo $stats['sites']['ssl_enabled']; ?></h5>
                <p>С включенным SSL</p>
                <?php endif; ?>
                <a href="/sites" class="btn btn-outline-success">Управление сайтами</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($stats['databases']) && isset($stats['databases']['db_count'])): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Базы данных</h5>
            </div>
            <div class="card-body text-center">
                <h3><?php echo $stats['databases']['db_count']; ?></h3>
                <p>Баз данных</p>
                <?php if (isset($stats['databases']['total_size'])): ?>
                <h5><?php echo $stats['databases']['total_size']; ?></h5>
                <p>Общий размер</p>
                <?php endif; ?>
                <a href="/databases" class="btn btn-outline-info">Управление базами</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($stats['logs']) && isset($stats['logs']['app_log_count'])): ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Журналы</h5>
            </div>
            <div class="card-body text-center">
                <h3><?php echo $stats['logs']['app_log_count']; ?></h3>
                <p>Файлов журналов</p>
                <?php if (isset($stats['logs']['app_log_size'])): ?>
                <h5><?php echo $stats['logs']['app_log_size']; ?></h5>
                <p>Общий размер</p>
                <?php endif; ?>
                <a href="/logs" class="btn btn-outline-warning">Просмотр журналов</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Панель модулей -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Быстрый доступ к модулям</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($modules as $moduleId => $module): ?>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo $module->getUrl(); ?>" class="text-decoration-none">
                            <div class="card text-center hover-card">
                                <div class="card-body">
                                    <h3><i class="fa <?php echo $module->getIcon(); ?> fa-fw"></i></h3>
                                    <h5><?php echo htmlspecialchars($module->getName()); ?></h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,.1);
    transform: translateY(-2px);
    transition: all .3s;
}
</style> 