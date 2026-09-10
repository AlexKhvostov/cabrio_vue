<?php
/**
 * ValidationHelper — утилита для валидации входных данных в backend CabrioRide.
 * Используется во всех контроллерах и actions для централизованной проверки данных.
 *
 * Методы:
 * - requireFields($data, $fields) — проверяет наличие обязательных полей
 * - validateEmail($email) — проверяет корректность email
 * - validateInt($value, $fieldName) — проверяет, что значение — целое число
 *
 * Пример использования:
 *   ValidationHelper::requireFields($data, ['email', 'password']);
 *   ValidationHelper::validateEmail($data['email']);
 *   ValidationHelper::validateInt($data['id'], 'id');
 */
class ValidationHelper {
    /**
     * Проверяет наличие обязательных полей в массиве данных.
     * Если поле отсутствует — выбрасывает исключение или возвращает ошибку.
     */
    public static function requireFields($data, $fields) {
        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                // TODO: Можно выбрасывать исключение или возвращать ResponseHelper::error
                throw new \Exception("Missing required field: $field");
            }
        }
    }

    /**
     * Проверяет корректность email.
     */
    public static function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Некорректный email: $email");
        }
    }

    /**
     * Проверяет, что значение — целое число.
     */
    public static function validateInt($value, $fieldName) {
        if (!is_numeric($value) || intval($value) != $value) {
            throw new \Exception("Поле $fieldName должно быть целым числом");
        }
    }
} 