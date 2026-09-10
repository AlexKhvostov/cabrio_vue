<?php
/**
 * _GetRoleIdAction — L1 Action для получения ID роли по коду
 * 
 * Назначение: Получает ID роли из справочника по коду роли
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - role_code (string) — код роли (например, 'user', 'member', 'admin')
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (int) — ID роли
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';

class _GetRoleIdAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['role_code']);
            
            $roleCode = $data['role_code'];
            
            // Получаем ID роли из БД
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('SELECT id FROM ref_roles WHERE code = ?');
            $stmt->execute([$roleCode]);
            
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$role) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'ROLE_NOT_FOUND',
                        'message' => "Роль с кодом '$roleCode' не найдена"
                    ]
                ];
            }
            
            Logger::info("Role ID retrieved: role_code=$roleCode, role_id=" . $role['id']);
            
            return [
                'success' => true,
                'data' => (int)$role['id']
            ];
            
        } catch (Exception $e) {
            Logger::error('_GetRoleIdAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка получения ID роли: ' . $e->getMessage()
                ]
            ];
        }
    }
} 