<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Интеграции</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/dashboard">Главная</a></li>
                    <li class="breadcrumb-item active">API</li>
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
                        <h3 class="card-title">API Ключи</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#create-api-key-modal">
                                <i class="fas fa-plus"></i> Создать новый ключ
                            </button>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ключ</th>
                                    <th>Пользователь</th>
                                    <th>Статус</th>
                                    <th>Использований</th>
                                    <th>Создан</th>
                                    <th>Последнее использование</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($apiKeys)): ?>
                                    <?php foreach ($apiKeys as $key): ?>
                                        <tr>
                                            <td><?= $key['id'] ?></td>
                                            <td>
                                                <div class="api-key-container">
                                                    <span class="api-key-mask"><?= substr($key['api_key'], 0, 8) ?>...<?= substr($key['api_key'], -8) ?></span>
                                                    <span class="api-key-full d-none"><?= $key['api_key'] ?></span>
                                                    <button class="btn btn-xs btn-default ml-2 toggle-key-visibility">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-xs btn-default ml-1 copy-key" data-key="<?= $key['api_key'] ?>">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($key['username']) ?></td>
                                            <td>
                                                <?php if ($key['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Активен</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Неактивен</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $key['usage_count'] ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($key['created_at'])) ?></td>
                                            <td>
                                                <?= $key['last_used_at'] ? date('d.m.Y H:i', strtotime($key['last_used_at'])) : 'Не использовался' ?>
                                            </td>
                                            <td>
                                                <?php if ($key['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-danger btn-xs revoke-key" data-id="<?= $key['id'] ?>">
                                                        <i class="fas fa-ban"></i> Отозвать
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-success btn-xs activate-key" data-id="<?= $key['id'] ?>">
                                                        <i class="fas fa-check"></i> Активировать
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">API ключи не найдены. Создайте новый ключ.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Использование API</h3>
                    </div>
                    <div class="card-body">
                        <p>API позволяет программно взаимодействовать с вашим сервером CloudPRO. Для работы с API выполните следующие шаги:</p>
                        
                        <ol>
                            <li>Создайте API ключ, нажав на кнопку "Создать новый ключ"</li>
                            <li>Включите API ключ в заголовок запроса:</li>
                        </ol>
                        
                        <div class="bg-dark p-2 mb-3">
                            <code>
                                Authorization: Bearer YOUR_API_KEY<br>
                                или<br>
                                X-API-Key: YOUR_API_KEY
                            </code>
                        </div>
                        
                        <p>Вы также можете передать ключ как параметр URL:</p>
                        
                        <div class="bg-dark p-2">
                            <code>
                                https://ваш-сервер.ru/api/v1/sites?api_key=YOUR_API_KEY
                            </code>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="/api/docs" class="btn btn-info">
                            <i class="fas fa-book"></i> Перейти к документации API
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Примеры использования</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Пример запроса статуса сервера (CURL):</strong></p>
                        
                        <div class="bg-dark p-2 mb-3">
                            <code>
                                curl -X GET \<br>
                                &nbsp;&nbsp;https://ваш-сервер.ru/api/v1/status \<br>
                                &nbsp;&nbsp;-H 'X-API-Key: YOUR_API_KEY'
                            </code>
                        </div>
                        
                        <p><strong>Пример создания сайта (CURL):</strong></p>
                        
                        <div class="bg-dark p-2">
                            <code>
                                curl -X POST \<br>
                                &nbsp;&nbsp;https://ваш-сервер.ru/api/v1/sites/create \<br>
                                &nbsp;&nbsp;-H 'X-API-Key: YOUR_API_KEY' \<br>
                                &nbsp;&nbsp;-H 'Content-Type: application/x-www-form-urlencoded' \<br>
                                &nbsp;&nbsp;-d 'domain=example.com&type=php'
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Модальное окно создания API ключа -->
<div class="modal fade" id="create-api-key-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Создание нового API ключа</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-api-key-form">
                    <div class="form-group">
                        <label for="description">Описание (опционально)</label>
                        <input type="text" class="form-control" id="description" name="description" placeholder="Например: Интеграция с CRM">
                    </div>
                    
                    <div class="form-group">
                        <label for="expires_at">Срок действия (опционально)</label>
                        <input type="date" class="form-control" id="expires_at" name="expires_at">
                        <small class="form-text text-muted">Оставьте пустым для бессрочного ключа</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="create-api-key-submit">Создать</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно успешного создания API ключа -->
<div class="modal fade" id="api-key-created-modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">API ключ создан!</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Внимание! Скопируйте API ключ сейчас. После закрытия этого окна вы увидите только частичный ключ.
                </div>
                <div class="form-group">
                    <label>Ваш новый API ключ:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="new-api-key" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="copy-new-key">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal">Понятно</button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Переключение видимости API ключа
    $('.toggle-key-visibility').on('click', function() {
        var container = $(this).closest('.api-key-container');
        var mask = container.find('.api-key-mask');
        var full = container.find('.api-key-full');
        var icon = $(this).find('i');
        
        if (mask.hasClass('d-none')) {
            mask.removeClass('d-none');
            full.addClass('d-none');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            mask.addClass('d-none');
            full.removeClass('d-none');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        }
    });
    
    // Копирование API ключа
    $('.copy-key').on('click', function() {
        var key = $(this).data('key');
        copyToClipboard(key);
        toastr.success('API ключ скопирован в буфер обмена');
    });
    
    // Функция копирования в буфер обмена
    function copyToClipboard(text) {
        var textarea = document.createElement('textarea');
        textarea.textContent = text;
        textarea.style.position = 'fixed';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
    
    // Отзыв API ключа
    $('.revoke-key').on('click', function() {
        var keyId = $(this).data('id');
        
        if (confirm('Вы уверены, что хотите отозвать этот API ключ? Все интеграции, использующие его, перестанут работать.')) {
            $.post('/api/revoke', {key_id: keyId}, function(response) {
                if (response.success) {
                    toastr.success('API ключ успешно отозван');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    toastr.error(response.error || 'Ошибка при отзыве API ключа');
                }
            }, 'json');
        }
    });
    
    // Активация API ключа
    $('.activate-key').on('click', function() {
        var keyId = $(this).data('id');
        
        $.post('/api/activate', {key_id: keyId}, function(response) {
            if (response.success) {
                toastr.success('API ключ успешно активирован');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                toastr.error(response.error || 'Ошибка при активации API ключа');
            }
        }, 'json');
    });
    
    // Создание API ключа
    $('#create-api-key-submit').on('click', function() {
        var formData = $('#create-api-key-form').serialize();
        
        $.post('/api/create_key', formData, function(response) {
            if (response.success) {
                $('#create-api-key-modal').modal('hide');
                $('#new-api-key').val(response.api_key);
                $('#api-key-created-modal').modal('show');
                
                // Очистить форму
                $('#create-api-key-form')[0].reset();
            } else {
                toastr.error(response.error || 'Ошибка при создании API ключа');
            }
        }, 'json');
    });
    
    // Копирование нового API ключа
    $('#copy-new-key').on('click', function() {
        copyToClipboard($('#new-api-key').val());
        toastr.success('Новый API ключ скопирован в буфер обмена');
    });
});
</script> 