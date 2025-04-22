<?php
/**
 * Шаблон страницы входа в CloudPRO
 * @var array $error Сообщение об ошибке (если есть)
 */
?>
<div class="login-container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-lg mt-5">
                <div class="card-header bg-primary text-white text-center">
                    <h3>CloudPRO</h3>
                    <p>Вход в панель управления</p>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Запомнить меня</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Войти</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>CloudPRO &copy; <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background-color: #f8f9fa;
}
</style> 