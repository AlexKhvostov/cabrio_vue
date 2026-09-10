<?php
/**
 * __HandleUserLeftAction — L2 Action для обработки выхода пользователя из клуба
 * 
 * Назначение: Обрабатывает событие выхода пользователя из клуба и устанавливает роль external
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем существование пользователя по Telegram ID
 * 2. Если пользователь найден:
 *    - Обновляем дату выхода из клуба (left_date)
 *    - Устанавливаем роль external (ID=1)
 *    - Возвращаем action: "updated"
 * 3. Если пользователь не найден:
 *    - Создаём запись с ролью external (ID=1)
 *    - Возвращаем action: "created"
 * 
 * Входные данные:
 *   - telegram_id (int) — Telegram ID пользователя (обязательно)
 *   - first_name (string, опционально) — имя пользователя
 *   - last_name (string, опционально) — фамилия пользователя
 *   - username (string, опционально) — username в Telegram
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные пользователя и информация о действии
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CheckUserByTelegramIdAction — проверка существования пользователя
 *   - _CreateUserAction — создание нового пользователя
 *   - _UpdateUserAction — обновление данных пользователя
 *   - _UpdateRoleUserAction — обновление роли пользователя
 */

require_once __DIR__ . '/../level1/_CheckUserByTelegramIdAction.php';
require_once __DIR__ . '/../level1/_CreateUserAction.php';
require_once __DIR__ . '/../level1/_UpdateUserAction.php';
require_once __DIR__ . '/../level1/_UpdateRoleUserAction.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';

class __HandleUserLeftAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['telegram_id']);
            
            // Валидация telegram_id
            ValidationHelper::validateInt($data['telegram_id'], 'telegram_id');
            
            $telegramId = $data['telegram_id'];
            $action = null;
            $userData = null;
            
            // 1. Проверяем существование пользователя
            $checkResult = _CheckUserByTelegramIdAction::handle(['telegram_id' => $telegramId]);
            
            if ($checkResult['success'] && $checkResult['data'] !== null) {
                // Пользователь найден - обновляем данные и роль
                $userData = $checkResult['data'];
                $userId = $userData['id'];
                
                // Подготавливаем данные для обновления
                $updateData = [];
                $changedFields = [];
                
                // Проверяем какие поля изменились
                if (isset($data['first_name']) && $data['first_name'] !== $userData['first_name']) {
                    $updateData['first_name'] = $data['first_name'];
                    $changedFields[] = 'first_name';
                }
                
                if (isset($data['last_name']) && $data['last_name'] !== $userData['last_name']) {
                    $updateData['last_name'] = $data['last_name'];
                    $changedFields[] = 'last_name';
                }
                
                if (isset($data['username']) && $data['username'] !== $userData['username']) {
                    $updateData['username'] = $data['username'];
                    $changedFields[] = 'username';
                }
                
                // Добавляем дату выхода из клуба
                $updateData['left_date'] = date('Y-m-d');
                $changedFields[] = 'left_date';
                
                // Если есть изменения - обновляем
                if (!empty($updateData)) {
                    $updateResult = _UpdateUserAction::handle([
                        'user_id' => $userId,
                        'first_name' => $updateData['first_name'] ?? null,
                        'last_name' => $updateData['last_name'] ?? null,
                        'username' => $updateData['username'] ?? null,
                        'left_date' => $updateData['left_date']
                    ]);
                    
                    if ($updateResult['success']) {
                        $userData = $updateResult['data'];
                    } else {
                        return $updateResult;
                    }
                }
                
                // Обновляем роль на external (ID=1)
                $roleUpdateResult = _UpdateRoleUserAction::handle([
                    'user_id' => $userId,
                    'role_id' => 1 // external
                ]);
                
                if ($roleUpdateResult['success']) {
                    $action = 'updated';
                    $userData = $roleUpdateResult['data'];
                } else {
                    return $roleUpdateResult;
                }
                
            } else {
                // Пользователь не найден - создаём запись с ролью external
                $createData = [
                    'telegram_id' => $telegramId,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'username' => $data['username'] ?? null,
                    'role_id' => 1, // external
                    'left_date' => date('Y-m-d')
                ];
                
                $createResult = _CreateUserAction::handle($createData);
                
                if ($createResult['success']) {
                    $action = 'created';
                    $userData = $createResult['data'];
                } else {
                    return $createResult;
                }
            }
            
            // 3. Формируем ответ
            $response = [
                'success' => true,
                'data' => array_merge($userData, [ // Используем развернутые данные из L1 Actions
                    'action' => $action,
                    'message' => self::getActionMessage($action)
                ])
            ];
            
            // Добавляем информацию об изменённых полях если обновляли
            if ($action === 'updated' && !empty($changedFields)) {
                $response['data']['updated_fields'] = $changedFields;
            }
            
            Logger::info("User left club: telegram_id=$telegramId, action=$action");
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('__HandleUserLeftAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка обработки выхода пользователя из клуба: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action) {
        switch ($action) {
            case 'created':
                return 'Пользователь создан с ролью external';
            case 'updated':
                return 'Роль пользователя изменена на external';
            default:
                return 'Операция выполнена';
        }
    }
} 