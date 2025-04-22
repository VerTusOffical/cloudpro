<?php
/**
 * Шаблон страницы списка логов
 * @var array $logs Список логов
 * @var array $user Информация о пользователе
 */
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Журналы</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="/dashboard" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> Назад
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Список доступных журналов</h5>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> Журналы не найдены или недоступны.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Размер</th>
                        <th>Последнее изменение</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr<?php echo !$log['exists'] ? ' class="table-danger"' : ''; ?>>
                        <td>
                            <?php if ($log['exists']): ?>
                            <i class="fa fa-file-text text-primary me-2"></i>
                            <?php else: ?>
                            <i class="fa fa-times-circle text-danger me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($log['name']); ?>
                        </td>
                        <td>
                            <?php echo $log['exists'] ? formatFileSize($log['size']) : 'Н/Д'; ?>
                        </td>
                        <td>
                            <?php echo $log['exists'] ? date('d.m.Y H:i:s', $log['modified']) : 'Н/Д'; ?>
                        </td>
                        <td>
                            <?php if ($log['exists']): ?>
                            <div class="btn-group btn-group-sm">
                                <a href="/logs/view?path=<?php echo urlencode($log['path']); ?>" class="btn btn-outline-primary" title="Просмотр">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="/logs/download?path=<?php echo urlencode($log['path']); ?>" class="btn btn-outline-success" title="Скачать">
                                    <i class="fa fa-download"></i>
                                </a>
                                <?php if ($user['role'] === 'admin'): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmClear('<?php echo htmlspecialchars(addslashes($log['name'])); ?>', '<?php echo htmlspecialchars(addslashes($log['path'])); ?>')" 
                                        title="Очистить">
                                    <i class="fa fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">Недоступен</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Форма для очистки лога -->
<form id="clearLogForm" method="post" action="/logs/clear">
    <input type="hidden" name="path" id="logPath">
</form>

<script>
function confirmClear(name, path) {
    if (confirm('Вы уверены, что хотите очистить лог "' + name + '"?\nЭто действие нельзя отменить.')) {
        document.getElementById('logPath').value = path;
        document.getElementById('clearLogForm').submit();
    }
}
</script> 