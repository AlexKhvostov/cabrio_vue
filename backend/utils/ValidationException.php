<?php
/**
 * ValidationException — исключение для ошибок валидации
 * 
 * Назначение: Используется для обработки ошибок валидации данных
 * Выбрасывается при: неверном формате данных, отсутствии обязательных полей и т.д.
 */

class ValidationException extends Exception {
    
    /**
     * Конструктор с сообщением об ошибке
     */
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
} 