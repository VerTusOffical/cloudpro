<?php
/**
 * CloudPRO - Автозагрузка классов
 */

/**
 * Функция автозагрузки классов
 * 
 * @param string $className Имя класса
 * @return void
 */
function cloudproAutoload($className) {
    // Проверяем, если это класс модуля
    if (substr($className, -6) === 'Module') {
        $moduleName = substr($className, 0, -6);
        $moduleName = strtolower($moduleName);
        $path = __DIR__ . "/../modules/$moduleName/module.php";
        
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
    
    // Проверяем, если это основной класс
    $path = __DIR__ . "/$className.php";
    if (file_exists($path)) {
        require_once $path;
        return;
    }
    
    // Проверяем, если это класс вспомогательной библиотеки
    $path = __DIR__ . "/lib/$className.php";
    if (file_exists($path)) {
        require_once $path;
        return;
    }
}

// Регистрация функции автозагрузки
spl_autoload_register('cloudproAutoload'); 