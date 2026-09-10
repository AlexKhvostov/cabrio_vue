<?php
/**
 * _UpdateStatusAction — базовый L1 Action для изменения статуса сущности.
 * 
 * Назначение: Обновляет статус сущности (авто, событие, гид-объект) в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - entity_type (string) — тип сущности (car, event, guide_object)
 *   - entity_id (int) — ID сущности
 *   - status_id (int) — ID нового статуса
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — обновленные данные сущности
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Car.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/GuideObject.php';
require_once __DIR__ . '/../../models/Status.php';

class _UpdateStatusAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['entity_type', 'entity_id', 'status_id']);
            
            // Валидация entity_id и status_id
            ValidationHelper::validateInt($data['entity_id'], 'entity_id');
            ValidationHelper::validateInt($data['status_id'], 'status_id');
            
            // Проверяем существование статуса
            $status = Status::findById($data['status_id']);
            if (!$status) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'STATUS_NOT_FOUND',
                        'message' => 'Статус не найден'
                    ]
                ];
            }
            
            // Обновляем статус в зависимости от типа сущности
            $result = false;
            switch ($data['entity_type']) {
                case 'car':
                    $result = Car::updateStatus($data['entity_id'], $data['status_id']);
                    break;
                case 'event':
                    $result = Event::updateStatus($data['entity_id'], $data['status_id']);
                    break;
                case 'guide_object':
                    $result = GuideObject::updateStatus($data['entity_id'], $data['status_id']);
                    break;
                default:
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'INVALID_ENTITY_TYPE',
                            'message' => 'Некорректный тип сущности: ' . $data['entity_type']
                        ]
                    ];
            }
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Ошибка обновления статуса'
                    ]
                ];
            }
            
            Logger::info("Status updated: entity_type={$data['entity_type']}, entity_id={$data['entity_id']}, status_id={$data['status_id']}");
            
            return [
                'success' => true,
                'data' => [
                    'entity_type' => $data['entity_type'],
                    'entity_id' => $data['entity_id'],
                    'status_id' => $data['status_id']
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('_UpdateStatusAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка обновления статуса: ' . $e->getMessage()
                ]
            ];
        }
    }
} 