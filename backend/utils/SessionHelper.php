<?php

require_once __DIR__ . '/../models/Session.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * 🔐 Помощник для управления сессиями
 * 
 * Централизованное управление сессиями пользователей:
 * - Создание и обновление сессий
 * - Валидация активных сессий
 * - Удаление сессий
 * - Очистка устаревших сессий
 * 
 * @package CabrioRide\Utils
 */
class SessionHelper
{
    /** @var int Время жизни сессии в секундах (24 часа) */
    const SESSION_LIFETIME = 86400;
    
    /** @var int Максимальная длина session_id */
    const SESSION_ID_LENGTH = 64;

    // ========================================
    // ОСНОВНЫЕ МЕТОДЫ УПРАВЛЕНИЯ СЕССИЯМИ
    // ========================================

    /**
     * Создать или обновить сессию для пользователя
     * 
     * @param int $userId ID пользователя
     * @param array $options Дополнительные опции (telegram_data, etc.)
     * @return array Результат операции
     */
    public static function createOrUpdateSession($userId, $options = [])
    {
        try {
            // Проверяем существующую активную сессию
            $existingSession = Session::findByUserId($userId);
            
            if ($existingSession && self::isSessionValid($existingSession)) {
                // Обновляем существующую сессию
                $sessionToken = self::generateSessionId();
                $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME);
                
                $updateData = [
                    'session_token' => $sessionToken,
                    'expires_at' => $expiresAt
                ];
                
                // Добавляем telegram_data если предоставлено
                if (isset($options['telegram_data'])) {
                    $updateData['telegram_data'] = json_encode($options['telegram_data']);
                }
                
                $updateResult = Session::update($existingSession->id, $updateData);
                
                if ($updateResult) {
                    Logger::info('Session updated', [
                        'user_id' => $userId,
                        'session_id' => substr($sessionToken, 0, 8) . '...',
                        'expires_at' => $expiresAt
                    ]);
                    
                    return [
                        'success' => true,
                        'session_id' => $sessionToken,
                        'expires_at' => $expiresAt,
                        'action' => 'updated'
                    ];
                }
            }
            
            // Создаем новую сессию
            $sessionId = self::generateSessionId();
            $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME);
            
            $sessionData = [
                'user_id' => $userId,
                'session_token' => $sessionId,
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
                'is_active' => 1
            ];
            
            // Добавляем telegram_data если предоставлено
            if (isset($options['telegram_data'])) {
                $sessionData['telegram_data'] = json_encode($options['telegram_data']);
            }
            
            $sessionToken = Session::create($sessionData);
            
            if ($sessionToken) {
                Logger::info('Session created', [
                    'user_id' => $userId,
                    'session_id' => substr($sessionToken, 0, 8) . '...',
                    'expires_at' => $expiresAt
                ]);
                
                return [
                    'success' => true,
                    'session_id' => $sessionToken,
                    'expires_at' => $expiresAt,
                    'action' => 'created'
                ];
            }
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'SESSION_CREATE_ERROR',
                    'message' => 'Не удалось создать сессию'
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Session creation error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'SESSION_ERROR',
                    'message' => 'Ошибка при создании сессии: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Унифицированный вход: если user_id = 0 (system) — не создаём/обновляем БД,
     * а возвращаем фиктивную сессию, иначе вызываем createOrUpdateSession.
     *
     * @param int  $userId
     * @param array $options
     * @return array [success, session_id, action]
     */
    public static function maybeCreateSession($userId, $options = [])
    {
        if ($userId === 0) {
            return [
                'success'     => true,
                'session_id'  => 'system',
                'expires_at'  => null,
                'action'      => 'system'
            ];
        }
        return self::createOrUpdateSession($userId, $options);
    }

    /**
     * Валидировать сессию по токену
     * 
     * @param string $sessionToken Токен сессии
     * @return array Результат валидации
     */
    public static function validateSession($sessionToken)
    {
        try {
            if (empty($sessionToken)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TOKEN',
                        'message' => 'Токен сессии не предоставлен'
                    ]
                ];
            }
            
            $session = Session::findByToken($sessionToken);
            
            if (!$session) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_NOT_FOUND',
                        'message' => 'Сессия не найдена'
                    ]
                ];
            }
            
            if (!$session['is_active']) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_INACTIVE',
                        'message' => 'Сессия неактивна'
                    ]
                ];
            }
            
            if (!self::isSessionValid($session)) {
                // Автоматически деактивируем устаревшую сессию
                Session::update($session['id'], ['is_active' => 0]);
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_EXPIRED',
                        'message' => 'Сессия истекла'
                    ]
                ];
            }
            
            // Обновляем время последнего использования
            Session::update($session['id'], [
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'user_id' => $session['user_id'],
                'session_id' => $session['session_token'],
                'expires_at' => $session['expires_at']
            ];
            
        } catch (Exception $e) {
            Logger::error('Session validation error', [
                'session_token' => substr($sessionToken, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Ошибка валидации сессии: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Удалить сессию
     * 
     * @param string $sessionToken Токен сессии
     * @return array Результат операции
     */
    public static function destroySession($sessionToken)
    {
        try {
            if (empty($sessionToken)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TOKEN',
                        'message' => 'Токен сессии не предоставлен'
                    ]
                ];
            }
            
            $session = Session::findByToken($sessionToken);
            
            if (!$session) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_NOT_FOUND',
                        'message' => 'Сессия не найдена'
                    ]
                ];
            }
            
            $result = Session::update($session['id'], [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                Logger::info('Session destroyed', [
                    'user_id' => $session['user_id'],
                    'session_id' => substr($sessionToken, 0, 8) . '...'
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Сессия успешно удалена'
                ];
            }
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'DESTROY_ERROR',
                    'message' => 'Не удалось удалить сессию'
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Session destruction error', [
                'session_token' => substr($sessionToken, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'DESTROY_ERROR',
                    'message' => 'Ошибка удаления сессии: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Удалить все сессии пользователя
     * 
     * @param int $userId ID пользователя
     * @return array Результат операции
     */
    public static function destroyAllUserSessions($userId)
    {
        try {
            $sessions = Session::findAllByUserId($userId);
            $destroyedCount = 0;
            
            foreach ($sessions as $session) {
                if ($session['is_active']) {
                    Session::update($session['id'], [
                        'is_active' => 0,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $destroyedCount++;
                }
            }
            
            Logger::info('All user sessions destroyed', [
                'user_id' => $userId,
                'destroyed_count' => $destroyedCount
            ]);
            
            return [
                'success' => true,
                'destroyed_count' => $destroyedCount,
                'message' => "Удалено {$destroyedCount} сессий пользователя"
            ];
            
        } catch (Exception $e) {
            Logger::error('Destroy all sessions error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'DESTROY_ALL_ERROR',
                    'message' => 'Ошибка удаления сессий: ' . $e->getMessage()
                ]
            ];
        }
    }

    // ========================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ========================================

    /**
     * Проверить, валидна ли сессия
     * 
     * @param array $session Данные сессии
     * @return bool
     */
    private static function isSessionValid($session)
    {
        if (!$session->is_active) {
            return false;
        }
        
        $expiresAt = strtotime($session->expires_at);
        $currentTime = time();
        
        return $expiresAt > $currentTime;
    }

    /**
     * Сгенерировать уникальный ID сессии
     * 
     * @return string
     */
    private static function generateSessionId()
    {
        $randomBytes = random_bytes(self::SESSION_ID_LENGTH / 2);
        return bin2hex($randomBytes);
    }

    /**
     * Очистить устаревшие сессии
     * 
     * @return array Результат операции
     */
    public static function cleanupExpiredSessions()
    {
        try {
            $currentTime = date('Y-m-d H:i:s');
            
            // Находим все устаревшие активные сессии
            $expiredSessions = Session::findExpiredSessions($currentTime);
            $cleanedCount = 0;
            
            foreach ($expiredSessions as $session) {
                Session::update($session['id'], [
                    'is_active' => 0,
                    'updated_at' => $currentTime
                ]);
                $cleanedCount++;
            }
            
            if ($cleanedCount > 0) {
                Logger::info('Expired sessions cleaned', [
                    'cleaned_count' => $cleanedCount
                ]);
            }
            
            return [
                'success' => true,
                'cleaned_count' => $cleanedCount,
                'message' => "Очищено {$cleanedCount} устаревших сессий"
            ];
            
        } catch (Exception $e) {
            Logger::error('Session cleanup error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'CLEANUP_ERROR',
                    'message' => 'Ошибка очистки сессий: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Получить статистику сессий
     * 
     * @return array Статистика
     */
    public static function getSessionStats()
    {
        try {
            $totalSessions = Session::countAll();
            $activeSessions = Session::countActive();
            $expiredSessions = Session::countExpired();
            
            return [
                'success' => true,
                'stats' => [
                    'total' => $totalSessions,
                    'active' => $activeSessions,
                    'expired' => $expiredSessions
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Session stats error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'STATS_ERROR',
                    'message' => 'Ошибка получения статистики: ' . $e->getMessage()
                ]
            ];
        }
    }
} 