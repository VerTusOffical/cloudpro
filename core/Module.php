<?php
/**
 * CloudPRO - Базовый класс для модулей
 */

abstract class Module {
    protected $db;
    protected $name;
    protected $icon;
    protected $menuPosition = 100;
    
    /**
     * Конструктор базового класса модуля
     * 
     * @param Database $db Экземпляр базы данных
     */
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Получение имени модуля
     * 
     * @return string Имя модуля
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * Получение иконки модуля
     * 
     * @return string Иконка модуля (FontAwesome)
     */
    public function getIcon() {
        return $this->icon;
    }
    
    /**
     * Получение позиции в меню
     * 
     * @return int Позиция в меню
     */
    public function getMenuPosition() {
        return $this->menuPosition;
    }
    
    /**
     * Регистрация маршрутов модуля
     * 
     * @param Router $router Экземпляр маршрутизатора
     * @return void
     */
    abstract public function registerRoutes(Router $router);
    
    /**
     * Получение статистики модуля
     * 
     * @return array Статистика модуля
     */
    abstract public function getStats();
    
    /**
     * Проверка доступности модуля
     * 
     * @return bool Доступность модуля
     */
    public function isAvailable() {
        return true;
    }
    
    /**
     * Получение URL модуля
     * 
     * @return string URL модуля
     */
    public function getUrl() {
        $className = get_class($this);
        $moduleName = strtolower(str_replace('Module', '', $className));
        return '/' . $moduleName;
    }
    
    /**
     * Рендеринг шаблона модуля
     * 
     * @param string $template Имя шаблона
     * @param array $data Данные для шаблона
     * @return void
     */
    protected function render($template, $data = []) {
        $className = get_class($this);
        $moduleName = strtolower(str_replace('Module', '', $className));
        
        $templatePath = "modules/$moduleName/$template";
        $templateObj = new Template($templatePath);
        $templateObj->render($data);
    }
    
    /**
     * Рендеринг без макета
     * 
     * @param string $template Имя шаблона
     * @param array $data Данные для шаблона
     * @return string HTML код
     */
    protected function renderPartial($template, $data = []) {
        $className = get_class($this);
        $moduleName = strtolower(str_replace('Module', '', $className));
        
        $templatePath = "modules/$moduleName/$template";
        $templateObj = new Template($templatePath);
        return $templateObj->renderPartial($data);
    }
} 