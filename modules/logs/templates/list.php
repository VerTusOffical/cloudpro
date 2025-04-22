<?php
/**
 * Шаблон списка логов
 * @var array $logs Массив найденных логов
 * @var array $user Текущий пользователь
 */
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fa fa-file-text-o"></i> Логи системы</h1>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Доступные лог-файлы
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="alert alert-info">Лог-файлы не найдены</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Имя файла</th>
                                        <th>Тип</th>
                                        <th>Размер</th>
                                        <th>Последнее изменение</th>
                                        <th class="text-end">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['name']); ?></td>
                                            <td><?php echo htmlspecialchars($log['type_name']); ?></td>
                                            <td><?php echo htmlspecialchars($log['size']); ?></td>
                                            <td><?php echo htmlspecialchars($log['modified']); ?></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo $log['url']; ?>" class="btn btn-primary">
                                                        <i class="fa fa-eye"></i> Просмотр
                                                    </a>
                                                    <a href="?route=logs/download&file=<?php echo urlencode($log['name']); ?>" class="btn btn-success">
                                                        <i class="fa fa-download"></i> Скачать
                                                    </a>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#clearLogModal" 
                                                           data-log-name="<?php echo htmlspecialchars($log['name']); ?>">
                                                            <i class="fa fa-trash"></i> Очистить
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Информация о логах
                </div>
                <div class="card-body">
                    <p>Здесь вы можете просматривать и управлять логами системы. Логи помогают отслеживать активность и диагностировать проблемы.</p>
                    <p><strong>Рекомендации:</strong></p>
                    <ul>
                        <li>Регулярно проверяйте логи на наличие ошибок и предупреждений</li>
                        <li>При необходимости скачивайте логи для подробного анализа</li>
                        <li>Периодически очищайте устаревшие логи, чтобы сэкономить дисковое пространство</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно подтверждения очистки лога -->
<?php if ($user['role'] === 'admin'): ?>
<div class="modal fade" id="clearLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Подтверждение очистки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите очистить файл лога <strong id="logNameToDelete"></strong>?</p>
                <p class="text-danger">Внимание! Это действие нельзя отменить. Все данные лога будут безвозвратно удалены.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="?route=logs/clear">
                    <input type="hidden" name="file" id="logFileToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-danger">Очистить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Заполнение данных модального окна очистки лога
document.addEventListener('DOMContentLoaded', function() {
    var clearLogModal = document.getElementById('clearLogModal');
    if (clearLogModal) {
        clearLogModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var logName = button.getAttribute('data-log-name');
            
            document.getElementById('logNameToDelete').textContent = logName;
            document.getElementById('logFileToDelete').value = logName;
        });
    }
});
</script>
<?php endif; ?> 