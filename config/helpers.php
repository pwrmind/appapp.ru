<?php

/**
 * Безопасное экранирование строк для защиты от XSS (включая поддержку PHP 8.1+)
 *
 * @param mixed $value Входная строка или NULL
 * @return string Безопасный HTML-текст
 */
function e($value): string {
    // Если пришел NULL или пустой массив/объект, возвращаем пустую строку
    if ($value === null || is_array($value) || is_object($value)) {
        return '';
    }
    
    // Принудительно приводим к строке (на случай, если передали число int/float)
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
