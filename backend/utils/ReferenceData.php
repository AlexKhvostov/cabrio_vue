<?php

/**
 * 📚 Справочные данные и константы CabrioRide
 * 
 * Централизованное хранение констант для статусов, ролей и других справочников.
 * Обеспечивает консистентность данных во всем приложении.
 * 
 * @package CabrioRide\Utils
 */
class ReferenceData
{
    // ========================================
    // СТАТУСЫ АВТОМОБИЛЕЙ
    // ========================================
    
    /** @var int Статус: Замечен */
    const CAR_STATUS_NOTICED = 1;
    
    /** @var int Статус: Визитка */
    const CAR_STATUS_BUSINESS_CARD = 2;
    
    /** @var int Статус: Удалён */
    const CAR_STATUS_DELETED = 3;
    
    /** @var int Статус: В архиве */
    const CAR_STATUS_ARCHIVED = 4;
    
    /** @var int Статус: Заблокирован */
    const CAR_STATUS_BLOCKED = 5;
    
    /** @var int Статус: На модерации */
    const CAR_STATUS_PENDING = 6;
    
    /** @var int Статус: Активен */
    const CAR_STATUS_ACTIVE = 7;
    
    // ========================================
    // РОЛИ ПОЛЬЗОВАТЕЛЕЙ
    // ========================================
    
    /** @var int Роль: Внешний */
    const USER_ROLE_EXTERNAL = 1;
    
    /** @var int Роль: Гость */
    const USER_ROLE_GUEST = 2;
    
    /** @var int Роль: Пользователь */
    const USER_ROLE_USER = 3;
    
    /** @var int Роль: Участник */
    const USER_ROLE_MEMBER = 4;
    
    /** @var int Роль: Модератор */
    const USER_ROLE_MODERATOR = 5;
    
    /** @var int Роль: Администратор */
    const USER_ROLE_ADMIN = 6;
    
    // ========================================
    // МАППИНГИ ID → CODE
    // ========================================
    
    /**
     * Маппинг статусов автомобилей: ID → code
     */
    private static $carStatusMap = [
        self::CAR_STATUS_NOTICED => 'noticed',
        self::CAR_STATUS_BUSINESS_CARD => 'business_card',
        self::CAR_STATUS_DELETED => 'deleted',
        self::CAR_STATUS_ARCHIVED => 'archived',
        self::CAR_STATUS_BLOCKED => 'blocked',
        self::CAR_STATUS_PENDING => 'pending',
        self::CAR_STATUS_ACTIVE => 'active'
    ];
    
    /**
     * Маппинг ролей пользователей: ID → code
     */
    private static $userRoleMap = [
        self::USER_ROLE_EXTERNAL => 'external',
        self::USER_ROLE_GUEST => 'guest',
        self::USER_ROLE_USER => 'user',
        self::USER_ROLE_MEMBER => 'member',
        self::USER_ROLE_MODERATOR => 'moderator',
        self::USER_ROLE_ADMIN => 'admin'
    ];
    
    // ========================================
    // МЕТОДЫ ДЛЯ РАБОТЫ СО СТАТУСАМИ
    // ========================================
    
    /**
     * Получить код статуса автомобиля по ID
     * 
     * @param int $statusId ID статуса
     * @return string|null Код статуса или null
     */
    public static function getCarStatusCode($statusId)
    {
        Logger::info('ReferenceData: getCarStatusCode called', [
            'status_id' => $statusId,
            'status_id_type' => gettype($statusId),
            'car_status_map' => self::$carStatusMap
        ]);
        
        $result = self::$carStatusMap[$statusId] ?? null;
        
        Logger::info('ReferenceData: getCarStatusCode result', [
            'status_id' => $statusId,
            'result' => $result,
            'result_type' => gettype($result)
        ]);
        
        return $result;
    }
    
    /**
     * Получить ID статуса автомобиля по коду
     * 
     * @param string $statusCode Код статуса
     * @return int|null ID статуса или null
     */
    public static function getCarStatusId($statusCode)
    {
        $flipped = array_flip(self::$carStatusMap);
        return $flipped[$statusCode] ?? null;
    }
    
    /**
     * Проверить, является ли статус активным
     * 
     * @param int $statusId ID статуса
     * @return bool
     */
    public static function isCarStatusActive($statusId)
    {
        return $statusId === self::CAR_STATUS_ACTIVE;
    }
    
    /**
     * Проверить, можно ли обновить статус
     * 
     * @param int $currentStatusId Текущий статус
     * @param int $newStatusId Новый статус
     * @return bool
     */
    public static function canUpdateCarStatus($currentStatusId, $newStatusId)
    {
        // Запрещенные переходы
        $forbiddenTransitions = [
            self::CAR_STATUS_DELETED => [self::CAR_STATUS_ACTIVE, self::CAR_STATUS_BUSINESS_CARD],
            self::CAR_STATUS_BLOCKED => [self::CAR_STATUS_ACTIVE, self::CAR_STATUS_BUSINESS_CARD]
        ];
        
        return !isset($forbiddenTransitions[$currentStatusId]) || 
               !in_array($newStatusId, $forbiddenTransitions[$currentStatusId]);
    }
    
    // ========================================
    // МЕТОДЫ ДЛЯ РАБОТЫ С РОЛЯМИ
    // ========================================
    
    /**
     * Получить код роли пользователя по ID
     * 
     * @param int $roleId ID роли
     * @return string|null Код роли или null
     */
    public static function getUserRoleCode($roleId)
    {
        return self::$userRoleMap[$roleId] ?? null;
    }
    
    /**
     * Получить ID роли пользователя по коду
     * 
     * @param string $roleCode Код роли
     * @return int|null ID роли или null
     */
    public static function getUserRoleId($roleCode)
    {
        $flipped = array_flip(self::$userRoleMap);
        return $flipped[$roleCode] ?? null;
    }
    
    /**
     * Проверить, имеет ли пользователь роль или выше
     * 
     * @param int $userRoleId Роль пользователя
     * @param int $requiredRoleId Требуемая роль
     * @return bool
     */
    public static function hasRoleOrHigher($userRoleId, $requiredRoleId)
    {
        return $userRoleId >= $requiredRoleId;
    }
    
    /**
     * Проверить, является ли пользователь администратором
     * 
     * @param int $roleId ID роли
     * @return bool
     */
    public static function isAdmin($roleId)
    {
        return $roleId === self::USER_ROLE_ADMIN;
    }
    
    /**
     * Проверить, является ли пользователь модератором или выше
     * 
     * @param int $roleId ID роли
     * @return bool
     */
    public static function isModeratorOrHigher($roleId)
    {
        return $roleId >= self::USER_ROLE_MODERATOR;
    }
    
    /**
     * Проверить, является ли пользователь участником или выше
     * 
     * @param int $roleId ID роли
     * @return bool
     */
    public static function isMemberOrHigher($roleId)
    {
        return $roleId >= self::USER_ROLE_MEMBER;
    }
    
    // ========================================
    // МЕТОДЫ ДЛЯ ПОЛУЧЕНИЯ РАЗВЕРНУТЫХ ДАННЫХ
    // ========================================
    
    /**
     * Получить развернутые данные статуса автомобиля
     * 
     * @param int $statusId ID статуса
     * @return array|null Развернутые данные или null
     */
    public static function getCarStatusDetails($statusId)
    {
        Logger::info('ReferenceData: getCarStatusDetails called', [
            'status_id' => $statusId,
            'status_id_type' => gettype($statusId)
        ]);
        
        $code = self::getCarStatusCode($statusId);
        Logger::info('ReferenceData: getCarStatusCode result', [
            'status_id' => $statusId,
            'code' => $code,
            'code_type' => gettype($code)
        ]);
        
        if (!$code) {
            Logger::warning('ReferenceData: No code found for status_id', [
                'status_id' => $statusId
            ]);
            return null;
        }
        
        $names = [
            'noticed' => 'НЕ в клубе (Замечен)',
            'business_card' => 'НЕ в клубе (Визитка)',
            'deleted' => 'Удалён',
            'archived' => 'В архиве',
            'blocked' => 'Заблокирован',
            'pending' => 'На модерации',
            'active' => 'Активен'
        ];
        
        $descriptions = [
            'noticed' => 'Авто замечен участницами, но еще не приглашен',
            'business_card' => 'В авто оставили визитку, владелец не в клубе',
            'deleted' => 'Удалён из системы',
            'archived' => 'В архиве (продан, не используется)',
            'blocked' => 'Заблокирован (подозрительный номер, нарушение)',
            'pending' => 'Ожидает проверки модератором',
            'active' => 'Активный автомобиль участника'
        ];
        
        $result = [
            'id' => $statusId,
            'code' => $code,
            'name' => ($names[$code] ?? 'Неизвестный статус') . "\n\n",
            'description' => $descriptions[$code] ?? ''
        ];
        
        Logger::info('ReferenceData: getCarStatusDetails result', [
            'status_id' => $statusId,
            'result' => $result,
            'result_type' => gettype($result)
        ]);
        
        return $result;
    }
    
    /**
     * Получить развернутые данные роли пользователя
     * 
     * @param int $roleId ID роли
     * @return array|null Развернутые данные или null
     */
    public static function getUserRoleDetails($roleId)
    {
        $code = self::getUserRoleCode($roleId);
        if (!$code) {
            return null;
        }
        
        $names = [
            'external' => 'Внешний',
            'guest' => 'Гость',
            'user' => 'Пользователь',
            'member' => 'Участник',
            'moderator' => 'Модератор',
            'admin' => 'Администратор'
        ];
        
        $descriptions = [
            'external' => 'Не член клуба, не состоит в чате',
            'guest' => 'В чате, не начал регистрацию',
            'user' => 'Завершил базовую регистрацию, не подтверждён',
            'member' => 'Подтверждён модератором, полный доступ',
            'moderator' => 'Может подтверждать участников, модерировать',
            'admin' => 'Полный доступ к управлению клубом'
        ];
        
        return [
            'id' => $roleId,
            'code' => $code,
            'name' => $names[$code] ?? 'Неизвестная роль',
            'description' => $descriptions[$code] ?? ''
        ];
    }
    
    // ========================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ========================================
    
    /**
     * Получить все доступные статусы автомобилей
     * 
     * @return array Массив всех статусов
     */
    public static function getAllCarStatuses()
    {
        $statuses = [];
        foreach (self::$carStatusMap as $id => $code) {
            $statuses[] = self::getCarStatusDetails($id);
        }
        return $statuses;
    }
    
    /**
     * Получить все доступные роли пользователей
     * 
     * @return array Массив всех ролей
     */
    public static function getAllUserRoles()
    {
        $roles = [];
        foreach (self::$userRoleMap as $id => $code) {
            $roles[] = self::getUserRoleDetails($id);
        }
        return $roles;
    }
    
    /**
     * Валидировать статус автомобиля
     * 
     * @param int $statusId ID статуса
     * @return bool
     */
    public static function isValidCarStatus($statusId)
    {
        return isset(self::$carStatusMap[$statusId]);
    }
    
    /**
     * Валидировать роль пользователя
     * 
     * @param int $roleId ID роли
     * @return bool
     */
    public static function isValidUserRole($roleId)
    {
        return isset(self::$userRoleMap[$roleId]);
    }
} 