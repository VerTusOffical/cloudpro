<?php
/**
 * Шаблон страницы просмотра содержимого лога
 * @var string $path Путь к файлу лога
 * @var string $filename Имя файла
 * @var string $content Содержимое файла
 * @var int $lines Количество строк
 * @var string $type Тип лога
 * @var string $typeName Название типа лога
 * @var array $user Информация о пользователе
 */
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Просмотр журнала</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="/logs" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> К списку журналов
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fa fa-file-text me-2"></i> <?php echo htmlspecialchars($filename); ?>
        </h5>
        <span class="badge bg-info"><?php echo htmlspecialchars($typeName); ?></span>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="row">
                <div class="col-md-6">
                    <p>
                        <strong>Путь:</strong> <?php echo htmlspecialchars($path); ?><br>
                        <strong>Размер:</strong> <?php echo formatFileSize(filesize($path)); ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <form method="get" action="/logs/view" class="mb-3">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <div class="input-group">
                            <label class="input-group-text" for="lines">Показать строк:</label>
                            <select class="form-select" id="lines" name="lines" onchange="this.form.submit()">
                                <option value="50" <?php echo $lines == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $lines == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo $lines == 200 ? 'selected' : ''; ?>>200</option>
                                <option value="500" <?php echo $lines == 500 ? 'selected' : ''; ?>>500</option>
                                <option value="1000" <?php echo $lines == 1000 ? 'selected' : ''; ?>>1000</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="btn-group mb-3">
            <a href="/logs/download?path=<?php echo urlencode($path); ?>" class="btn btn-success">
                <i class="fa fa-download"></i> Скачать журнал
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <button type="button" class="btn btn-danger" 
                    onclick="confirmClear('<?php echo htmlspecialchars(addslashes($filename)); ?>', '<?php echo htmlspecialchars(addslashes($path)); ?>')">
                <i class="fa fa-trash"></i> Очистить журнал
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" onclick="refreshLog()">
                <i class="fa fa-refresh"></i> Обновить
            </button>
        </div>
        
        <div class="log-viewer card">
            <div class="card-header bg-dark text-white py-1">
                <div class="row">
                    <div class="col-md-6">
                        Последние <?php echo $lines; ?> строк
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-sm btn-outline-light" onclick="toggleWrap()">
                            <i class="fa fa-text-width"></i> Перенос строк
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <pre id="logContent" class="m-0 p-3 log-content"><?php echo htmlspecialchars($content); ?></pre>
            </div>
        </div>
    </div>
</div>

<!-- Форма для очистки лога -->
<form id="clearLogForm" method="post" action="/logs/clear">
    <input type="hidden" name="path" id="logPath">
</form>

<style>
.log-content {
    min-height: 300px;
    max-height: 700px;
    overflow-y: auto;
    white-space: pre-wrap;
    background-color: #f8f9fa;
    color: #212529;
    font-family: monospace;
    font-size: 0.9rem;
}

.log-content.nowrap {
    white-space: pre;
}

pre {
    tab-size: 4;
}
</style>

<script>
function confirmClear(name, path) {
    if (confirm('Вы уверены, что хотите очистить лог "' + name + '"?\nЭто действие нельзя отменить.')) {
        document.getElementById('logPath').value = path;
        document.getElementById('clearLogForm').submit();
    }
}

function toggleWrap() {
    const logContent = document.getElementById('logContent');
    logContent.classList.toggle('nowrap');
}

function refreshLog() {
    window.location.reload();
}

// При загрузке страницы прокручиваем к последней записи
window.addEventListener('DOMContentLoaded', (event) => {
    const logContent = document.getElementById('logContent');
    logContent.scrollTop = logContent.scrollHeight;
});
</script> 