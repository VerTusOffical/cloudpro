<?php
/**
 * CloudPRO - Класс шаблонизатора
 */

class Template {
    private $template;
    private $layout = 'layout';
    
    /**
     * Конструктор класса шаблонизатора
     * 
     * @param string $template Имя шаблона
     * @param string $layout Имя макета
     */
    public function __construct($template, $layout = 'layout') {
        $this->template = $template;
        $this->layout = $layout;
    }
    
    /**
     * Установка шаблона
     * 
     * @param string $template Имя шаблона
     * @return void
     */
    public function setTemplate($template) {
        $this->template = $template;
    }
    
    /**
     * Установка макета
     * 
     * @param string $layout Имя макета
     * @return void
     */
    public function setLayout($layout) {
        $this->layout = $layout;
    }
    
    /**
     * Рендеринг шаблона
     * 
     * @param array $data Данные для шаблона
     * @return void
     */
    public function render($data = []) {
        // Извлекаем переменные из массива
        extract($data);
        
        // Буферизируем вывод шаблона
        ob_start();
        
        $templateFile = APP_PATH . '/templates/' . $this->template . '.php';
        
        if (file_exists($templateFile)) {
            require $templateFile;
        } else {
            echo "Ошибка: шаблон '$templateFile' не найден.";
        }
        
        $content = ob_get_clean();
        
        // Если макет не установлен, выводим только содержимое шаблона
        if ($this->layout === null) {
            echo $content;
            return;
        }
        
        // Буферизируем вывод макета
        ob_start();
        
        $layoutFile = APP_PATH . '/templates/layouts/' . $this->layout . '.php';
        
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo "Ошибка: макет '$layoutFile' не найден.";
        }
        
        echo ob_get_clean();
    }
    
    /**
     * Рендеринг без макета
     * 
     * @param array $data Данные для шаблона
     * @return string HTML код
     */
    public function renderPartial($data = []) {
        extract($data);
        
        ob_start();
        
        $templateFile = APP_PATH . '/templates/' . $this->template . '.php';
        
        if (file_exists($templateFile)) {
            require $templateFile;
        } else {
            echo "Ошибка: шаблон '$templateFile' не найден.";
        }
        
        return ob_get_clean();
    }
    
    /**
     * Вывод подшаблона
     * 
     * @param string $template Имя подшаблона
     * @param array $data Данные для подшаблона
     * @return void
     */
    public static function renderPartialTemplate($template, $data = []) {
        extract($data);
        
        $templateFile = APP_PATH . '/templates/' . $template . '.php';
        
        if (file_exists($templateFile)) {
            require $templateFile;
        } else {
            echo "Ошибка: шаблон '$templateFile' не найден.";
        }
    }
} 