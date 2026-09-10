<?php
/**
 * __AddCarToUserAction — L2 Action для добавления автомобиля пользователю
 * 
 * Назначение: Добавляет автомобиль пользователю (если авто был в БД и без владельца то ставит владельца, если нет в БД то создает)
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем существование автомобиля по номеру
 * 2. Если автомобиль найден:
 *    - Проверяем есть ли у него владелец
 *    - Если владельца нет - назначаем пользователя владельцем
 *    - Если владелец есть - возвращаем ошибку
 *    - Возвращаем action: "assigned"
 * 3. Если автомобиль не найден:
 *    - Создаём новый автомобиль с пользователем как владельцем
 *    - Возвращаем action: "created"
 * 4. Проверяем, является ли пользователь владельцем автомобиля:
 *    - Если да - обновляем роль пользователя (если необходимо)
 *    - Если нет - роль не изменяется
 * 
 * Входные данные:
 *   - plate_number (string) — номер автомобиля (может быть null)
 *   - model (string, опционально) — модель автомобиля
 *   - color (string, опционально) — цвет автомобиля
 *   - year (int, опционально) — год выпуска
 *   - photo (file, опционально) — фото автомобиля
 * 
 * Пользователь получается из глобального контекста (AppContext)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные автомобиля и информация о действии
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CheckCarInDbAction — проверка существования автомобиля
 *   - _CreateCarAction — создание нового автомобиля
 *   - _UpdateOwnerToCarAction — назначение владельца автомобилю
 *   - _GetRoleIdAction — получение ID роли по коду
 *   - _UpdateRoleUserAction — обновление роли пользователя
 * 

 */

require_once __DIR__ . '/../level1/_CheckCarInDbAction.php';
require_once __DIR__ . '/../level1/_CreateCarAction.php';
require_once __DIR__ . '/../level1/_UpdateOwnerToCarAction.php';
require_once __DIR__ . '/../level1/_GetRoleIdAction.php';
require_once __DIR__ . '/../level1/_UpdateRoleUserAction.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AppContext.php';

class __AddCarToUserAction {
    
    public static function handle($data) {
        try {
            Logger::info('L2 Action: Starting', [
                'plate_number' => $data['plate_number'] ?? null,
                'has_photo' => isset($data['photo']) && !empty($data['photo'])
            ]);
            
            // Получаем пользователя из глобального контекста
            $user = AppContext::getCurrentUser();
            if (!$user) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'NO_USER',
                        'message' => 'Пользователь не найден в контексте'
                    ]
                ];
            }
            
            $userId = $user['id'];
            
            // Инициализируем флаг обновления роли
            $roleUpdated = false;
            
            // Получаем номер (может быть null)
            $plateNumber = $data['plate_number'] ?? null;
            $action = null;
            $carData = null;
            
            // 1. Проверяем существование автомобиля (только если есть номер)
            if ($plateNumber) {
                Logger::info('L2 Action: Checking car in DB', ['plate_number' => $plateNumber]);
                
                $checkResult = _CheckCarInDbAction::handle(['plate_number' => $plateNumber]);
                
                Logger::info('L2 Action: Car check result', [
                    'success' => $checkResult['success'],
                    'found' => $checkResult['data'] !== null,
                    'car_data' => $checkResult['data'] ?? null,
                    'owner_user_id' => $checkResult['data']['owner_user_id'] ?? null,
                    'owner_user_id_type' => gettype($checkResult['data']['owner_user_id'] ?? null)
                ]);
                
                if ($checkResult['success'] && $checkResult['data'] !== null) {
                    // Автомобиль найден
                    $carData = $checkResult['data'];
                    $carId = $carData['id'];
                    
                    // Проверяем есть ли владелец
                    $ownerUserId = $carData['owner_user_id'] ?? null;
                    
                    Logger::info('L2 Action: Checking owner', [
                        'owner_user_id' => $ownerUserId,
                        'owner_user_id_type' => gettype($ownerUserId),
                        'isset_owner' => isset($carData['owner_user_id']),
                        'not_null_owner' => $ownerUserId !== null,
                        'condition_result' => $ownerUserId !== null
                    ]);
                    
                    if ($ownerUserId !== null) {
                        Logger::info('L2 Action: Car has owner', [
                            'car_id' => $carId,
                            'owner_user_id' => $ownerUserId,
                            'current_user_id' => $userId,
                            'is_same_owner' => $ownerUserId == $userId
                        ]);
                        
                        // Проверяем, является ли текущий пользователь владельцем
                        if ($ownerUserId == $userId) {
                            Logger::info('L2 Action: User already owns this car');
                            return [
                                'success' => false,
                                'error' => [
                                    'code' => 'CAR_ALREADY_IN_GARAGE',
                                    'message' => 'Автомобиль уже добавлен в ваш гараж'
                                ]
                            ];
                        } else {
                            Logger::info('L2 Action: Car owned by another user');
                        return [
                            'success' => false,
                            'error' => [
                                    'code' => 'CAR_OWNED_BY_OTHER',
                                    'message' => 'Автомобиль принадлежит другому участнику клуба'
                            ]
                        ];
                        }
                    } else {
                        Logger::info('L2 Action: Car has no owner, calling _UpdateOwnerToCarAction');
                    }
                    
                    // Назначаем пользователя владельцем
                    $updateResult = _UpdateOwnerToCarAction::handle([
                        'car_id' => $carId,
                        'user_id' => $userId
                    ]);
                    
                    if ($updateResult['success']) {
                        $action = 'assigned';
                        // Получаем обновленные данные автомобиля
                        $carData = $updateResult['data'];
                    } else {
                        return $updateResult;
                    }
                    
                } else {
                    // Автомобиль не найден - создаём новый с владельцем
                    $createData = [
                        'reg_number' => $plateNumber,
                        'create_user_id' => $userId,
                        'owner_user_id' => $userId, // Пользователь как владелец
                        'model' => $data['model'] ?? null,
                        'color' => $data['color'] ?? null,
                        'year' => $data['year'] ?? null,
                        'status_id' => 7 // "Активный" по умолчанию
                    ];
                    
                    $createResult = _CreateCarAction::handle($createData);
                    
                    if ($createResult['success']) {
                        $action = 'created';
                        $carData = $createResult['data'];
                        $carId = $carData['id'];
                    } else {
                        return $createResult;
                    }
                }
            } else {
                // Нет номера - создаём авто без номера
                $createData = [
                    'reg_number' => null,
                    'create_user_id' => $userId,
                    'owner_user_id' => $userId, // Пользователь как владелец
                    'model' => $data['model'] ?? null,
                    'color' => $data['color'] ?? null,
                    'year' => $data['year'] ?? null,
                    'status_id' => 7 // "Активный" по умолчанию
                ];
                
                $createResult = _CreateCarAction::handle($createData);
                
                if ($createResult['success']) {
                    $action = 'created';
                    $carData = $createResult['data'];
                    $carId = $carData['id'];
                } else {
                    return $createResult;
                }
            }
            
            // 3. Проверяем, является ли пользователь владельцем автомобиля и обновляем роль
            $finalOwnerId = $carData['owner']['id'] ?? null;
            $isOwner = ($finalOwnerId === $userId);
            
            if ($isOwner) {
                Logger::info('L2 Action: User is owner, checking role update', [
                    'user_id' => $userId,
                    'car_id' => $carData['id'],
                    'owner_user_id' => $finalOwnerId
                ]);
                $roleUpdated = self::checkAndUpdateUserRole($user);
            } else {
                Logger::info('L2 Action: User is not owner, role not updated', [
                    'user_id' => $userId,
                    'car_id' => $carData['id'],
                    'owner_user_id' => $finalOwnerId
                ]);
                $roleUpdated = false;
            }
            
            // 4. Формируем ответ
            $response = [
                'success' => true,
                'data' => [
                    'car_id' => $carData['id'],
                    'action' => $action,
                    'plate_number' => $carData['reg_number'],
                    'model' => $carData['model'],
                    'color' => $carData['color'],
                    'year' => $carData['year'],
                    'status_id' => $carData['status']['id'] ?? null,
                    'status' => $carData['status'] ?? null,
                    'owner_user_id' => $carData['owner']['id'] ?? null,
                    'create_user_id' => $carData['create_user_id'] ?? $userId,
                    'message' => self::getActionMessage($action),
                    'role_updated' => $roleUpdated
                ]
            ];
            

            
            Logger::info("Car added to user: plate_number=$plateNumber, user_id=$userId, action=$action");
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('__AddCarToUserAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка добавления автомобиля пользователю: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Проверить и обновить роль пользователя
     * 
     * @param array $user Данные пользователя
     * @return bool Обновлена ли роль
     */
    private static function checkAndUpdateUserRole($user) {
        try {
            // Получаем ID роли "user" (обычно это 3)
            $userRoleId = _GetRoleIdAction::handle(['role_code' => 'user']);
            
            if (!$userRoleId['success']) {
                Logger::error('Failed to get user role ID');
                return false;
            }
            
            $requiredRoleId = $userRoleId['data'];
            $currentRoleId = $user['role_id'] ?? $user['role']['id'] ?? 2; // guest по умолчанию
            
            // Если роль меньше user, обновляем
            if ($currentRoleId < $requiredRoleId) {
                Logger::info("Updating user role from $currentRoleId to $requiredRoleId", [
                    'user_id' => $user['id']
                ]);
                
                $updateResult = _UpdateRoleUserAction::handle([
                    'user_id' => $user['id'],
                    'role_id' => $requiredRoleId
                ]);
                
                if ($updateResult['success']) {
                    Logger::info("User role updated successfully", [
                        'user_id' => $user['id'],
                        'old_role_id' => $currentRoleId,
                        'new_role_id' => $requiredRoleId
                    ]);
                    return true;
                } else {
                    Logger::error("Failed to update user role", [
                        'user_id' => $user['id'],
                        'error' => $updateResult['error']
                    ]);
                    return false;
                }
            }
            
            return false; // Роль не обновлялась
            
        } catch (Exception $e) {
            Logger::error('Error checking/updating user role: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action) {
        switch ($action) {
            case 'assigned':
                return 'Автомобиль назначен пользователю';
            case 'created':
                return 'Автомобиль создан и назначен пользователю';
            default:
                return 'Операция выполнена';
        }
    }
} 