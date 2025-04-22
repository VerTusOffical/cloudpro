<?php
/**
 * Шаблон страницы ошибки в модуле логов
 * @var string $error Сообщение об ошибке
 * @var array $user Информация о пользователе
 */
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Ошибка</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="/logs" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-arrow-left"></i> К списку журналов
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fa fa-exclamation-triangle me-2"></i> Произошла ошибка</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
        
        <p class="mb-3">
            Пожалуйста, вернитесь к <a href="/logs">списку журналов</a> и попробуйте снова.
        </p>
        
        <p>
            Если ошибка повторяется, обратитесь к администратору системы.
        </p>
    </div>
</div> 