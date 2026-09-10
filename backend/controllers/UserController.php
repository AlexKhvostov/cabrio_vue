<?php
/**
 * UserController — контроллер для работы с пользователями (users).
 *
 * Назначение:
 *   Обрабатывает HTTP-запросы, связанные с пользователями: получение, создание, обновление, удаление, авторизация и т.д.
 *
 * Зависимости:
 *   - User (модель)
 *   - Role (модель)
 *   - AuthHelper (утилита для авторизации)
 *   - ResponseHelper (утилита для формирования ответов)
 *
 * Основные методы:
 *   - getList() — получить список пользователей
 *   - getById($id) — получить пользователя по id
 *   - create($data) — создать пользователя
 *   - update($id, $data) — обновить пользователя
 *   - delete($id) — удалить пользователя
 *   - ... (дополнительные методы: смена роли, поиск по telegram_id и т.д.)
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';

class UserController extends BaseController
{
    /**
     * Получить список пользователей
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function getList()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.users.getList')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Получаем список пользователей с развернутыми данными
            $users = User::getAll();
            
            // Логируем действие
            $this->logUserAction('get_users_list', [
                'count' => count($users)
            ]);
            
            $this->json([
                'success' => true, 
                'data' => $users, // Уже содержит развернутые данные из модели
                'meta' => $this->getRequestInfo()
            ]);
            
        } catch (Throwable $e) {
            Logger::error('UserController: getList error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'DB_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Полная карточка участника по id (с его авто).
     * GET /api/users/{id}
     */
    public function getById($id)
    {
        try {
            if (!$this->requireAccess('api.users.getById')) {
                return;
            }

            $currentUserId = (int)$this->getCurrentUserId();
            $targetId = (int)$id;
            $isSelf = ($currentUserId === $targetId);
            $isStaff = $this->isModerator() || $this->isAdmin();
            $user = User::findByIdWithDetails($targetId, !($isSelf || $isStaff));

            if (!$user) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Пользователь не найден'
                    ]
                ], 404);
                return;
            }

            $canEdit = $isSelf || $isStaff;

            if (!$canEdit) {
                unset($user['email'], $user['phone'], $user['notes']);
            }

            if (!empty($user['cars']) && is_array($user['cars'])) {
                foreach ($user['cars'] as &$car) {
                    $car['permissions'] = ['canEdit' => $isSelf || $isStaff];
                }
                unset($car);
            }

            $user['permissions'] = [ 'canEdit' => $canEdit ];

            $this->json([
                'success' => true,
                'data' => $user,
                'meta' => $this->getRequestInfo()
            ]);
        } catch (Throwable $e) {
            Logger::error('UserController: getById error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId(),
                'target_id' => $id
            ]);
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Сменить роль пользователя (только для moderator+)
     * POST /api/users/{id}/role
     * Body: { "role": "member" } ИЛИ { "role_id": 4 }
     */
    public function updateRole($userId)
    {
        try {
            // Если по какой-то причине контекст пользователя не установлен — пытаемся аутентифицировать здесь
            if (!AppContext::hasCurrentUser()) {
                require_once __DIR__ . '/../middleware/AuthMiddleware.php';
                $routeGuess = $_GET['route'] ?? ('/api/users/' . (int)$userId . '/role');
                AuthMiddleware::authenticate($routeGuess, $_SERVER['REQUEST_METHOD'] ?? 'POST');
            }

            // Проверяем доступ: модератор и выше (робастно определяем код роли)
            $current = $this->requireUser();
            require_once __DIR__ . '/../../config/sectionGroups.php';
            $currentRoleCode = 'guest';
            if (isset($current['role']) && is_array($current['role']) && isset($current['role']['code'])) {
                $currentRoleCode = $current['role']['code'];
            } elseif (isset($current['role']) && is_string($current['role'])) {
                $currentRoleCode = $current['role'];
            } elseif (isset($current['role_id'])) {
                $currentRoleCode = Roles::getRoleById((int)$current['role_id']);
            }
            if (!Roles::hasAccess($currentRoleCode, Roles::MODERATOR)) {
                $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Недостаточно прав (требуется роль: moderator)']],403);
                return;
            }

            $targetUserId = (int)$userId;
            if ($targetUserId <= 0) {
                $this->json(['success'=>false,'error'=>['code'=>'BAD_REQUEST','message'=>'Некорректный ID пользователя']],400);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $roleCode = isset($input['role']) ? strtolower(trim((string)$input['role'])) : '';
            $roleId = isset($input['role_id']) ? (int)$input['role_id'] : 0;
            $newRoleCode = '';
            if ($roleCode !== '') {
                $newRoleCode = $roleCode;
            } elseif ($roleId > 0 && isset(Roles::ID_ROLES[$roleId])) {
                $newRoleCode = Roles::getRoleById($roleId);
            }
            if ($newRoleCode === '' || !isset(Roles::ROLE_IDS[$newRoleCode])) {
                $this->json(['success'=>false,'error'=>['code'=>'NO_ROLE','message'=>'Не указана или неизвестна роль']],400);
                return;
            }
            $roleId = Roles::getRoleId($newRoleCode);

            // Нельзя понизить/повысить себя через этот эндпоинт (безопасность)
            $currentUser = $this->requireUser();
            if ((int)$currentUser['id'] === $targetUserId) {
                $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Нельзя менять свою роль']],403);
                return;
            }

            $target = User::findById($targetUserId);
            if (!$target) {
                $this->json(['success'=>false,'error'=>['code'=>'NOT_FOUND','message'=>'Пользователь не найден']],404);
                return;
            }
            $targetRoleCode = Roles::getRoleById((int)$target->role_id);
            $actorIndex = Roles::getIndex($currentRoleCode);
            $targetIndex = Roles::getIndex($targetRoleCode);
            $newIndex = Roles::getIndex($newRoleCode);
            // Нельзя трогать равных и выше; назначить можно только роль ниже своей
            if ($targetIndex === false || $actorIndex === false || $targetIndex >= $actorIndex) {
                $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Нельзя менять роль этого человека']],403);
                return;
            }
            if ($newIndex === false || $newIndex >= $actorIndex) {
                $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Нельзя назначить эту роль']],403);
                return;
            }

            // Обновляем роль
            $ok = User::updateRole($targetUserId, $roleId);
            if (!$ok) {
                $this->json(['success'=>false,'error'=>['code'=>'UPDATE_FAILED','message'=>'Не удалось обновить роль']],400);
                return;
            }

            // Возвращаем пользователя с развернутыми данными
            $updated = User::findByIdWithDetails($targetUserId);
            $this->logUserAction('update_user_role', ['target_user_id'=>$targetUserId,'role_id'=>$roleId]);
            $who = trim((string)(($updated['first_name_app'] ?? $updated['first_name'] ?? '') . ' ' . ($updated['last_name_app'] ?? $updated['last_name'] ?? '')));
            $roleRu = AppAudit::roleName((string)($updated['role']['code'] ?? $roleCode ?? ''));
            $this->audit('update', 'user', $targetUserId, 'Сменил роль: ' . ($who !== '' ? $who : ('id ' . $targetUserId)) . ' → ' . $roleRu, 'users');
            $this->json(['success'=>true,'data'=>$updated,'meta'=>$this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('UserController: updateRole error', [ 'error'=>$e->getMessage(), 'user_id'=>$this->getCurrentUserId() ]);
            $this->json(['success'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>$e->getMessage()]],500);
        }
    }

    /**
     * Создать нового пользователя
     * 
     * Требует авторизации: Да
     * Минимальная роль: admin
     */
    public function create()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.users.create')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Логируем действие
            $this->logUserAction('create_user', [
                'input_data' => $input
            ]);

            // TODO: Реализовать создание пользователя через модель с развернутыми данными
            $this->json([
                'success' => true, 
                'data' => [
                    'id' => 3, 
                    'name' => 'Новый пользователь',
                    'created_by' => $this->getCurrentUserId()
                ],
                'meta' => $this->getRequestInfo()
            ], 201);
            
        } catch (Throwable $e) {
            Logger::error('UserController: create error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Получить профиль текущего пользователя
     * 
     * Требует авторизации: Да
     * Минимальная роль: external (свой профиль даже если человек не в чате)
     */
    public function getProfile()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.users.getProfile')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            // Получаем текущего пользователя
            $user = $this->requireUser();
            
            // Получаем развернутые данные пользователя
            $userWithDetails = User::findByIdWithDetails($user['id'], false);
            
            if (!$userWithDetails) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'Пользователь не найден'
                    ]
                ], 404);
                return;
            }
            
            // Логируем действие
            $this->logUserAction('get_profile');
            
            $userWithDetails['permissions'] = [ 'canEdit' => true ];
            if (!empty($userWithDetails['cars']) && is_array($userWithDetails['cars'])) {
                foreach ($userWithDetails['cars'] as &$car) {
                    $car['permissions'] = [ 'canEdit' => true ];
                }
                unset($car);
            }

            $this->json([
                'success' => true,
                'data' => $userWithDetails, // Развернутые данные пользователя
                'meta' => $this->getRequestInfo()
            ]);
            
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $e->getMessage()
                ]
            ], 401);
        } catch (Throwable $e) {
            Logger::error('UserController: getProfile error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Обновить профиль текущего пользователя (self)
     * 
     * Требует авторизации: Да
     * Минимальная роль: guest
     */
    public function updateProfile()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.users.updateSelf')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Текущий пользователь (ID берём только из контекста)
            $currentUser = $this->requireUser();
            $currentUserId = (int)$currentUser['id'];
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $targetId = $currentUserId;
            $requestedId = (int)($input['id'] ?? $input['user_id'] ?? 0);
            if ($requestedId && $requestedId !== $currentUserId) {
                if (!$this->requireAccess('api.users.updateOther')) {
                    return;
                }
                $targetId = $requestedId;
            }

            // Разрешённые к редактированию поля
            // Заметки (notes) исключены из self-редактирования — доступны только админам в админке
            $allowedFields = [
                'first_name_app',
                'last_name_app',
                'email',
                'phone',
                'city',
                'country',
                'about'
            ];

            $updateData = ['id' => $targetId];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }

            // Если нечего обновлять
            if (count($updateData) === 1) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_DATA',
                        'message' => 'Нет данных для обновления'
                    ]
                ], 400);
                return;
            }

            // Обновляем и возвращаем развернутые данные
            $updatedUser = User::updateWithDetails($targetId, $updateData);

            if (!$updatedUser) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Не удалось обновить профиль'
                    ]
                ], 400);
                return;
            }

            $this->logUserAction('update_profile_self', ['fields' => array_keys($updateData), 'target_id' => $targetId]);
            $self = $targetId === $currentUserId;
            $this->audit('update', 'user', $targetId, $self ? 'Изменил свой профиль' : ('Изменил профиль человека id ' . $targetId), $self ? 'me' : 'users');

            $this->json([
                'success' => true,
                'data' => $updatedUser,
                'meta' => $this->getRequestInfo()
            ]);
        } catch (\PDOException $e) {
            $sqlState = $e->getCode();
            $msg = $e->getMessage();
            if ($sqlState === '23000' && stripos($msg, 'Duplicate entry') !== false) {
                $field = (stripos($msg, 'email') !== false) ? 'email' : ((stripos($msg, 'phone') !== false) ? 'phone' : 'unique_field');
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_' . strtoupper($field),
                        'message' => 'Значение поля уже используется: ' . $field
                    ]
                ], 409);
                return;
            }
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'DB_ERROR',
                    'message' => $msg
                ]
            ], 400);
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => $e->getMessage()
                ]
            ], 401);
        } catch (Throwable $e) {
            Logger::error('UserController: updateProfile error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);

            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }
} 