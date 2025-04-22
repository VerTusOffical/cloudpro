<?php
/**
 * Шаблон просмотра содержимого лога
 * @var string $filename Имя файла
 * @var string $content Содержимое лога
 * @var string $type Тип лога
 * @var string $type_name Название типа лога
 * @var int $lines Количество показанных строк
 * @var string $file_size Размер файла
 * @var array $user Текущий пользователь
 */
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fa fa-file-text-o"></i> Просмотр лога</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="?route=logs" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-arrow-left"></i> Вернуться к списку
                </a>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fa fa-file-text-o me-2"></i> <?php echo htmlspecialchars($filename); ?>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark me-2"><?php echo htmlspecialchars($type_name); ?></span>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($file_size); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-toolbar">
                            <div class="btn-group me-2">
                                <a href="?route=logs/view&file=<?php echo urlencode($filename); ?>&lines=50" class="btn btn-sm <?php echo $lines == 50 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Последние 50 строк
                                </a>
                                <a href="?route=logs/view&file=<?php echo urlencode($filename); ?>&lines=100" class="btn btn-sm <?php echo $lines == 100 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Последние 100 строк
                                </a>
                                <a href="?route=logs/view&file=<?php echo urlencode($filename); ?>&lines=500" class="btn btn-sm <?php echo $lines == 500 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Последние 500 строк
                                </a>
                                <a href="?route=logs/view&file=<?php echo urlencode($filename); ?>&lines=1000" class="btn btn-sm <?php echo $lines == 1000 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                    Последние 1000 строк
                                </a>
                            </div>
                            
                            <div class="btn-group ms-2">
                                <a href="?route=logs/download&file=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-success">
                                    <i class="fa fa-download"></i> Скачать файл
                                </a>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#clearLogModal" 
                                        data-log-name="<?php echo htmlspecialchars($filename); ?>">
                                        <i class="fa fa-trash"></i> Очистить лог
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="log-content">
                        <?php if (empty($content)): ?>
                            <div class="alert alert-info">
                                Лог-файл пуст или не содержит данных.
                            </div>
                        <?php else: ?>
                            <pre id="logContent" class="log-box <?php echo $type; ?>"><?php echo htmlspecialchars($content); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fa fa-search"></i> Поиск в логах
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Введите текст для поиска..." aria-label="Поиск">
                            <button class="btn btn-outline-primary" type="button" id="searchButton">
                                <i class="fa fa-search"></i> Найти
                            </button>
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchButton">
                                <i class="fa fa-times"></i> Очистить
                            </button>
                        </div>
                        <div class="form-text text-muted">
                            Поиск работает только на текущей странице и не ищет по всему файлу логов.
                        </div>
                    </div>
                    <div id="searchResults" class="mt-2"></div>
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
<?php endif; ?>

<style>
.log-box {
    background-color: #1e1e1e;
    color: #f0f0f0;
    font-family: monospace;
    padding: 15px;
    border-radius: 5px;
    white-space: pre-wrap;
    max-height: 600px;
    overflow-y: auto;
    font-size: 13px;
    line-height: 1.5;
}

.log-box .highlight {
    background-color: #ffe066;
    color: #333;
    padding: 2px;
    border-radius: 2px;
}

/* Стили для разных типов логов */
.log-box.error, .log-box.php {
    color: #f8f8f8;
    background-color: #2d2d2d;
}
.log-box.access, .log-box.nginx {
    color: #e0e0e0;
    background-color: #282c34;
}
.log-box.system {
    color: #d0d0d0;
    background-color: #232937;
}
.log-box.mysql {
    color: #e0e0e0;
    background-color: #263238;
}
</style>

<script>
// Прокрутка к концу лога при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    var logContent = document.getElementById('logContent');
    if (logContent) {
        logContent.scrollTop = logContent.scrollHeight;
    }
    
    // Поиск в логе
    var searchInput = document.getElementById('searchInput');
    var searchButton = document.getElementById('searchButton');
    var clearSearchButton = document.getElementById('clearSearchButton');
    var searchResults = document.getElementById('searchResults');
    
    function performSearch() {
        var searchText = searchInput.value.trim();
        if (!searchText) {
            searchResults.innerHTML = '';
            // Очищаем все подсветки
            var highlights = document.querySelectorAll('.highlight');
            highlights.forEach(function(el) {
                var parent = el.parentNode;
                parent.replaceChild(document.createTextNode(el.textContent), el);
            });
            return;
        }
        
        var content = logContent.textContent;
        var matches = content.split('\n').filter(function(line) {
            return line.toLowerCase().includes(searchText.toLowerCase());
        });
        
        searchResults.innerHTML = '';
        if (matches.length > 0) {
            searchResults.innerHTML = '<div class="alert alert-success">Найдено совпадений: ' + matches.length + '</div>';
            
            // Подсвечиваем совпадения
            var regex = new RegExp('(' + searchText.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + ')', 'gi');
            logContent.innerHTML = logContent.textContent.replace(regex, '<span class="highlight">$1</span>');
            
            // Прокрутка к первому совпадению
            var firstHighlight = document.querySelector('.highlight');
            if (firstHighlight) {
                firstHighlight.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        } else {
            searchResults.innerHTML = '<div class="alert alert-warning">Совпадений не найдено</div>';
        }
    }
    
    if (searchButton) {
        searchButton.addEventListener('click', performSearch);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
                e.preventDefault();
            }
        });
    }
    
    if (clearSearchButton) {
        clearSearchButton.addEventListener('click', function() {
            searchInput.value = '';
            searchResults.innerHTML = '';
            // Очищаем все подсветки
            logContent.innerHTML = logContent.textContent;
        });
    }
    
    // Модальное окно очистки лога
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