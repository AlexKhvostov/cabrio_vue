<?php

/**
 * 🌐 Глобальный контекст приложения
 * 
 * Централизованное хранилище данных для текущего запроса:
 * - Пользователь (currentUser)
 * - Сессия (sessionId) 
 * - Telegram данные (telegramData)
 * - Метаданные запроса (requestId, startTime)
 * 
 * @package CabrioRide\Utils
 */
class AppContext
{
    /** @var array|null Текущий пользователь */
    private static $currentUser = null;
    
    /** @var string|null ID сессии */
    private static $sessionId = null;
    
    /** @var array|null Данные из Telegram */
    private static $telegramData = null;
    
    /** @var array|null Аватар пользователя */
    private static $userAvatar = null;
    
    /** @var string|null Уникальный ID запроса */
    private static $requestId = null;
    
    /** @var float|null Время начала запроса */
    private static $startTime = null;

    // ========================================
    // МЕТОДЫ УПРАВЛЕНИЯ ПОЛЬЗОВАТЕЛЕМ
    // ========================================

    /**
     * Установить текущего пользователя
     * 
     * @param array $user Данные пользователя
     * @return void
     */
    public static function setCurrentUser($user)
    {
        self::$currentUser = $user;
    }

    /**
     * Получить текущего пользователя
     * 
     * @return array|null Данные пользователя или null
     */
    public static function getCurrentUser()
    {
        return self::$currentUser;
    }

    /**
     * Проверить, есть ли текущий пользователь
     * 
     * @return bool
     */
    public static function hasCurrentUser()
    {
        return self::$currentUser !== null;
    }

    /**
     * Очистить данные пользователя
     * 
     * @return void
     */
    public static function clearCurrentUser()
    {
        self::$currentUser = null;
    }

    // ========================================
    // МЕТОДЫ УПРАВЛЕНИЯ АВАТАРОМ ПОЛЬЗОВАТЕЛЯ
    // ========================================

    /**
     * Установить аватар пользователя
     * 
     * @param array $avatar Данные аватара
     * @return void
     */
    public static function setUserAvatar($avatar)
    {
        self::$userAvatar = $avatar;
    }

    /**
     * Получить аватар пользователя
     * 
     * @return array|null Данные аватара или null
     */
    public static function getUserAvatar()
    {
        return self::$userAvatar;
    }

    /**
     * Проверить, есть ли аватар пользователя
     * 
     * @return bool
     */
    public static function hasUserAvatar()
    {
        return self::$userAvatar !== null;
    }

    /**
     * Очистить аватар пользователя
     * 
     * @return void
     */
    public static function clearUserAvatar()
    {
        self::$userAvatar = null;
    }

    // ========================================
    // МЕТОДЫ УПРАВЛЕНИЯ СЕССИЕЙ
    // ========================================

    /**
     * Установить ID сессии
     * 
     * @param string $sessionId ID сессии
     * @return void
     */
    public static function setSessionId($sessionId)
    {
        self::$sessionId = $sessionId;
    }

    /**
     * Получить ID сессии
     * 
     * @return string|null ID сессии или null
     */
    public static function getSessionId()
    {
        return self::$sessionId;
    }

    /**
     * Проверить, есть ли активная сессия
     * 
     * @return bool
     */
    public static function hasSession()
    {
        return self::$sessionId !== null;
    }

    // ========================================
    // МЕТОДЫ УПРАВЛЕНИЯ TELEGRAM ДАННЫМИ
    // ========================================

    /**
     * Установить данные из Telegram
     * 
     * @param array $telegramData Данные из Telegram
     * @return void
     */
    public static function setTelegramData($telegramData)
    {
        self::$telegramData = $telegramData;
    }

    /**
     * Получить данные из Telegram
     * 
     * @return array|null Данные из Telegram или null
     */
    public static function getTelegramData()
    {
        return self::$telegramData;
    }

    /**
     * Проверить, есть ли данные из Telegram
     * 
     * @return bool
     */
    public static function hasTelegramData()
    {
        return self::$telegramData !== null;
    }

    // ========================================
    // МЕТОДЫ ДЛЯ ОТЛАДКИ И МОНИТОРИНГА
    // ========================================

    /**
     * Установить уникальный ID запроса
     * 
     * @param string $requestId ID запроса
     * @return void
     */
    public static function setRequestId($requestId)
    {
        self::$requestId = $requestId;
    }

    /**
     * Получить уникальный ID запроса
     * 
     * @return string|null ID запроса или null
     */
    public static function getRequestId()
    {
        return self::$requestId;
    }

    /**
     * Установить время начала запроса
     * 
     * @param float $startTime Время начала запроса
     * @return void
     */
    public static function setStartTime($startTime)
    {
        self::$startTime = $startTime;
    }

    /**
     * Получить время начала запроса
     * 
     * @return float|null Время начала запроса или null
     */
    public static function getStartTime()
    {
        return self::$startTime;
    }

    /**
     * Получить время выполнения запроса в секундах
     * 
     * @return float|null Время выполнения или null
     */
    public static function getExecutionTime()
    {
        if (self::$startTime === null) {
            return null;
        }
        
        return microtime(true) - self::$startTime;
    }

    // ========================================
    // МЕТОДЫ УПРАВЛЕНИЯ КОНТЕКСТОМ
    // ========================================

    /**
     * Получить полную информацию о контексте
     * 
     * @return array Данные контекста
     */
    public static function getContextInfo()
    {
        return [
            'user_id' => self::$currentUser ? self::$currentUser['id'] : null,
            'session_id' => self::$sessionId,
            'telegram_id' => self::$telegramData ? self::$telegramData['telegram_id'] : null,
            'request_id' => self::$requestId,
            'start_time' => self::$startTime,
            'execution_time' => self::getExecutionTime()
        ];
    }

    /**
     * Проверить, инициализирован ли контекст
     * 
     * @return bool
     */
    public static function isInitialized()
    {
        return self::$currentUser !== null || 
               self::$sessionId !== null || 
               self::$telegramData !== null;
    }

    /**
     * Полная очистка контекста
     * 
     * @return void
     */
    public static function clear()
    {
        self::$currentUser = null;
        self::$sessionId = null;
        self::$telegramData = null;
        self::$userAvatar = null;
        self::$requestId = null;
        self::$startTime = null;
    }

    /**
     * Получить краткую информацию о контексте для логирования
     * 
     * @return array
     */
    public static function getLogInfo()
    {
        return [
            'user_id' => self::$currentUser ? self::$currentUser['id'] : 'none',
            'session_id' => self::$sessionId ? substr(self::$sessionId, 0, 8) . '...' : 'none',
            'request_id' => self::$requestId ?: 'none',
            'execution_time' => self::getExecutionTime()
        ];
    }
} 