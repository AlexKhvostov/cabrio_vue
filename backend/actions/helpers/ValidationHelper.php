<?php
/**
 * 🔧 ValidationHelper - Дополнительные функции валидации для Actions
 * 
 * Назначение: Специфичная валидация для бизнес-логики
 * Используется в: L1, L2, L3 Actions для проверки данных
 */

require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';

class ValidationHelper {
    
    /**
     * 🚗 Валидация номера автомобиля
     * 
     * @param string $plateNumber - Номер для валидации
     * @param string $fieldName - Название поля для ошибки
     * @throws ValidationException - Если номер не прошел валидацию
     */
    public static function validatePlateNumber($plateNumber, $fieldName = 'plate_number') {
        // Проверка на пустое значение
        if (empty($plateNumber)) {
            throw new ValidationException("Номер автомобиля не может быть пустым");
        }
        
        // Проверка длины (минимум 3 символа, максимум 10)
        if (strlen($plateNumber) < 3) {
            throw new ValidationException("Номер автомобиля слишком короткий (минимум 3 символа)");
        }
        
        if (strlen($plateNumber) > 10) {
            throw new ValidationException("Номер автомобиля слишком длинный (максимум 10 символов)");
        }
        
        // Проверка на допустимые символы (буквы, цифры, дефис)
        if (!preg_match('/^[A-Z0-9\-]+$/i', $plateNumber)) {
            throw new ValidationException("Номер автомобиля содержит недопустимые символы. Разрешены: буквы, цифры, дефис");
        }
        
        // Проверка на наличие хотя бы одной буквы и одной цифры
        if (!preg_match('/[A-Z]/i', $plateNumber)) {
            throw new ValidationException("Номер автомобиля должен содержать хотя бы одну букву");
        }
        
        if (!preg_match('/[0-9]/', $plateNumber)) {
            throw new ValidationException("Номер автомобиля должен содержать хотя бы одну цифру");
        }
        
        Logger::info("Plate number validated: $plateNumber");
    }
    
    /**
     * 👤 Валидация данных пользователя
     * 
     * @param array $userData - Данные пользователя
     * @throws ValidationException - Если данные не прошли валидацию
     */
    public static function validateUserData($userData) {
        // Проверка обязательных полей
        $requiredFields = ['first_name', 'last_name', 'telegram_id'];
        foreach ($requiredFields as $field) {
            if (!isset($userData[$field]) || empty($userData[$field])) {
                throw new ValidationException("Поле '$field' обязательно для заполнения");
            }
        }
        
        // Валидация telegram_id
        if (!is_numeric($userData['telegram_id']) || $userData['telegram_id'] <= 0) {
            throw new ValidationException("Telegram ID должен быть положительным числом");
        }
        
        // Валидация имени и фамилии
        if (strlen($userData['first_name']) < 2) {
            throw new ValidationException("Имя должно содержать минимум 2 символа");
        }
        
        if (strlen($userData['last_name']) < 2) {
            throw new ValidationException("Фамилия должна содержать минимум 2 символа");
        }
        
        // Валидация email (если указан)
        if (isset($userData['email']) && !empty($userData['email'])) {
            \ValidationHelper::validateEmail($userData['email']);
        }
        
        // Валидация username (если указан)
        if (isset($userData['username']) && !empty($userData['username'])) {
            self::validateUsername($userData['username']);
        }
        
        Logger::info("User data validated for telegram_id: {$userData['telegram_id']}");
    }
    
    /**
     * 🏷️ Валидация данных визитки
     * 
     * @param array $businessCardData - Данные визитки
     * @throws ValidationException - Если данные не прошли валидацию
     */
    public static function validateBusinessCardData($businessCardData) {
        // Проверка обязательных полей
        if (!isset($businessCardData['car_id']) || !is_numeric($businessCardData['car_id'])) {
            throw new ValidationException("ID автомобиля обязателен и должен быть числом");
        }
        
        if (!isset($businessCardData['user_id']) || !is_numeric($businessCardData['user_id'])) {
            throw new ValidationException("ID пользователя обязателен и должен быть числом");
        }
        
        // Валидация сообщения (если указано)
        if (isset($businessCardData['message']) && !empty($businessCardData['message'])) {
            if (strlen($businessCardData['message']) > 500) {
                throw new ValidationException("Сообщение визитки не может превышать 500 символов");
            }
        }
        
        Logger::info("Business card data validated for car_id: {$businessCardData['car_id']}");
    }
    
    /**
     * 📸 Валидация данных фотографии
     * 
     * @param array $photoData - Данные фотографии
     * @throws ValidationException - Если данные не прошли валидацию
     */
    public static function validatePhotoData($photoData) {
        // Проверка обязательных полей
        if (!isset($photoData['entity_type']) || empty($photoData['entity_type'])) {
            throw new ValidationException("Тип сущности обязателен для фотографии");
        }
        
        if (!isset($photoData['entity_id']) || !is_numeric($photoData['entity_id'])) {
            throw new ValidationException("ID сущности обязателен и должен быть числом");
        }
        
        // Валидация типа сущности
        $allowedEntityTypes = ['user', 'car', 'event', 'business_card', 'guide_object'];
        if (!in_array($photoData['entity_type'], $allowedEntityTypes)) {
            throw new ValidationException("Неподдерживаемый тип сущности: {$photoData['entity_type']}");
        }
        
        // Валидация имени файла
        if (isset($photoData['file_name']) && !empty($photoData['file_name'])) {
            if (strlen($photoData['file_name']) > 255) {
                throw new ValidationException("Имя файла слишком длинное");
            }
        }
        
        Logger::info("Photo data validated for entity: {$photoData['entity_type']}_{$photoData['entity_id']}");
    }
    
    /**
     * 👤 Валидация username
     * 
     * @param string $username - Username для валидации
     * @throws ValidationException - Если username не прошел валидацию
     */
    public static function validateUsername($username) {
        // Проверка длины
        if (strlen($username) < 3) {
            throw new ValidationException("Username должен содержать минимум 3 символа");
        }
        
        if (strlen($username) > 30) {
            throw new ValidationException("Username не может превышать 30 символов");
        }
        
        // Проверка на допустимые символы (буквы, цифры, подчеркивание)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new ValidationException("Username может содержать только буквы, цифры и подчеркивание");
        }
        
        // Проверка на начало с буквы
        if (!preg_match('/^[a-zA-Z]/', $username)) {
            throw new ValidationException("Username должен начинаться с буквы");
        }
    }
    
    /**
     * 🚗 Валидация данных автомобиля
     * 
     * @param array $carData - Данные автомобиля
     * @throws ValidationException - Если данные не прошли валидацию
     */
    public static function validateCarData($carData) {
        // Проверка обязательных полей
        if (!isset($carData['model']) || empty($carData['model'])) {
            throw new ValidationException("Модель автомобиля обязательна");
        }
        
        if (!isset($carData['owner_user_id']) || !is_numeric($carData['owner_user_id'])) {
            throw new ValidationException("ID владельца обязателен и должен быть числом");
        }
        
        // Валидация модели
        if (strlen($carData['model']) < 2) {
            throw new ValidationException("Модель автомобиля должна содержать минимум 2 символа");
        }
        
        if (strlen($carData['model']) > 50) {
            throw new ValidationException("Модель автомобиля не может превышать 50 символов");
        }
        
        // Валидация года (если указан)
        if (isset($carData['year']) && !empty($carData['year'])) {
            \ValidationHelper::validateInt($carData['year'], 'year');
            
            $currentYear = date('Y');
            if ($carData['year'] < 1900 || $carData['year'] > $currentYear + 1) {
                throw new ValidationException("Год выпуска должен быть между 1900 и " . ($currentYear + 1));
            }
        }
        
        // Валидация цвета (если указан)
        if (isset($carData['color']) && !empty($carData['color'])) {
            if (strlen($carData['color']) > 30) {
                throw new ValidationException("Название цвета не может превышать 30 символов");
            }
        }
        
        Logger::info("Car data validated for model: {$carData['model']}");
    }
    
    /**
     * 📊 Валидация пагинации
     * 
     * @param array $pagination - Параметры пагинации
     * @throws ValidationException - Если параметры не прошли валидацию
     */
    public static function validatePagination($pagination) {
        if (isset($pagination['page'])) {
            if (!is_numeric($pagination['page']) || $pagination['page'] < 1) {
                throw new ValidationException("Номер страницы должен быть положительным числом");
            }
        }
        
        if (isset($pagination['limit'])) {
            if (!is_numeric($pagination['limit']) || $pagination['limit'] < 1 || $pagination['limit'] > 100) {
                throw new ValidationException("Лимит должен быть числом от 1 до 100");
            }
        }
    }
    
    /**
     * 🔍 Валидация поискового запроса
     * 
     * @param string $query - Поисковый запрос
     * @throws ValidationException - Если запрос не прошел валидацию
     */
    public static function validateSearchQuery($query) {
        if (empty($query)) {
            throw new ValidationException("Поисковый запрос не может быть пустым");
        }
        
        if (strlen($query) < 2) {
            throw new ValidationException("Поисковый запрос должен содержать минимум 2 символа");
        }
        
        if (strlen($query) > 100) {
            throw new ValidationException("Поисковый запрос не может превышать 100 символов");
        }
        
        // Проверка на SQL инъекции
        if (preg_match('/[<>"\']/', $query)) {
            throw new ValidationException("Поисковый запрос содержит недопустимые символы");
        }
    }
} 