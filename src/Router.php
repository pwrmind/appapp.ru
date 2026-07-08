<?php
class Router {
    private array $routes = [];

    public function add(string $route, string $file): void {
        // Очищаем роут от слэшей по краям при регистрации
        $route = trim($route, '/');
        
        // Если это главная страница, регулярное выражение должно искать строго пустоту
        if ($route === '') {
            $regex = '^$';
        } else {
            // Превращаем {id} в именованную подмаску для чисел
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[0-9]+)', $route);
            $regex = '^' . $regex . '$';
        }
        
        $this->routes['#' . $regex . '#'] = $file;
    }

    public function dispatch(string $url): bool { // Меням void на bool
        $url = trim($url, '/');
        foreach ($this->routes as $regex => $file) {
            if (preg_match($regex, $url, $matches)) {
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $_GET[$key] = $value;
                    }
                }
                if (file_exists($file)) {
                    require_once $file;
                    return true; // Файл успешно найден и подключен
                }
            }
        }
        
        // Убираем отсюда header и exit! Просто сигнализируем об ошибке
        return false; 
    }
}
