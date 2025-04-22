<?php
/**
 * CloudPRO - Класс маршрутизатора
 */

class Router {
    private $routes = [
        'GET' => [],
        'POST' => []
    ];
    
    /**
     * Регистрация GET маршрута
     * 
     * @param string $path Путь
     * @param callable $handler Обработчик маршрута
     * @return void
     */
    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }
    
    /**
     * Регистрация POST маршрута
     * 
     * @param string $path Путь
     * @param callable $handler Обработчик маршрута
     * @return void
     */
    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }
    
    /**
     * Получение текущего пути
     * 
     * @return string Текущий путь
     */
    private function getCurrentPath() {
        $path = $_SERVER['REQUEST_URI'];
        $position = strpos($path, '?');
        
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }
        
        return $path;
    }
    
    /**
     * Нахождение подходящего маршрута
     * 
     * @param string $method HTTP метод
     * @param string $path Путь
     * @return array|null Данные о маршруте или null
     */
    private function findRoute($method, $path) {
        // Прямое совпадение
        if (isset($this->routes[$method][$path])) {
            return [
                'handler' => $this->routes[$method][$path],
                'params' => []
            ];
        }
        
        // Поиск маршрута с параметрами
        foreach ($this->routes[$method] as $route => $handler) {
            // Проверка на наличие параметров в маршруте
            if (strpos($route, ':') !== false) {
                $routePattern = preg_replace('/:([^\/]+)/', '(?P<$1>[^/]+)', $route);
                $routePattern = "#^$routePattern$#";
                
                if (preg_match($routePattern, $path, $matches)) {
                    $params = [];
                    
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = $value;
                        }
                    }
                    
                    return [
                        'handler' => $handler,
                        'params' => $params
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Обработка текущего запроса
     * 
     * @return void
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getCurrentPath();
        
        $route = $this->findRoute($method, $path);
        
        if ($route) {
            $handler = $route['handler'];
            $params = $route['params'];
            
            // Вызов обработчика с параметрами
            call_user_func_array($handler, $params);
        } else {
            // Маршрут не найден
            http_response_code(404);
            echo '404 - Страница не найдена';
        }
    }
} 