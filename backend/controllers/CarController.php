<?php
/**
 * CarController — контроллер для работы с автомобилями (cars).
 *
 * Назначение:
 *   Обрабатывает HTTP-запросы, связанные с автомобилями: получение, создание, обновление, удаление, передача владения и т.д.
 *
 * Зависимости:
 *   - Car (модель)
 *   - CarBrand (модель)
 *   - User (модель)
 *   - LinkUserCar (модель)
 *   - AuthHelper, ResponseHelper
 *
 * Основные методы:
 *   - getList() — получить список авто
 *   - getById($id) — получить авто по id
 *   - create($data) — добавить авто
 *   - update($id, $data) — обновить авто
 *   - delete($id) — удалить авто
 *   - transferOwnership($car_id, $new_user_id) — передать авто другому пользователю
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Status.php';
require_once __DIR__ . '/../actions/level3/___CheckCarInClubAction.php';
require_once __DIR__ . '/../actions/level3/___LeaveBusinessCardAction.php';
require_once __DIR__ . '/../actions/level3/___AddCarToGarageAction.php';

class CarController extends BaseController
{
    /**
     * Получить список автомобилей
     * 
     * Требует авторизации: Да
     * Минимальная роль: user
     */
    public function getList()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.cars.getList')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Получаем список автомобилей с развернутыми данными
            $cars = Car::getAll();
            // Добавим флаг прав на редактирование для каждого авто
            $currentUserId = (int)$this->getCurrentUserId();
            foreach ($cars as &$c) {
                $isOwner = isset($c['owner']) && isset($c['owner']['id']) && ((int)$c['owner']['id'] === $currentUserId);
                $c['permissions'] = [ 'canEdit' => $isOwner || $this->isModerator() || $this->isAdmin() ];
                $c = Car::applyRegNumberPrivacy($c, $isOwner);
            }
            unset($c);
            // Приватность: скрываем владельца, если нет прав на просмотр участников
            $canIncludeOwner = $this->checkAccess('api.cars.includeOwner');
            if (!$canIncludeOwner) {
                foreach ($cars as &$c) {
                    if (isset($c['owner'])) {
                        unset($c['owner']);
                    }
                }
                unset($c);
            }
            
            // Логируем действие
            $this->logUserAction('get_cars_list', [
                'count' => count($cars)
            ]);
            
            $this->json([
                'success' => true, 
                'data' => $cars, // Уже содержит развернутые данные из модели
                'meta' => $this->getRequestInfo()
            ]);
            
        } catch (Throwable $e) {
            Logger::error('CarController: getList error', [
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
     * Обновить автомобиль по id (владелец или модератор)
     * 
     * PATCH /api/cars/{id}
     */
    public function update($id)
    {
        try {
            // Требуем базовый доступ (гость и выше) — конкретная проверка ниже
            if (!$this->isAuthenticated()) {
                $this->json([
                    'success' => false,
                    'error' => [ 'code' => 'UNAUTHORIZED', 'message' => 'Пользователь не авторизован' ]
                ], 401);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Получаем текущее авто
            $car = Car::findByIdWithDetails($id);
            if (!$car) {
                $this->json([
                    'success' => false,
                    'error' => [ 'code' => 'NOT_FOUND', 'message' => 'Автомобиль не найден' ]
                ], 404);
                return;
            }

            $currentUserId = (int)$this->getCurrentUserId();
            $isOwner = isset($car['owner']) && (int)$car['owner']['id'] === $currentUserId;

            // Проверка прав: владелец или модератор/админ
            if ($isOwner) {
                if (!$this->requireAccess('api.cars.updateSelf')) { return; }
            } else {
                if (!$this->requireAccess('api.cars.updateById')) { return; }
            }

            // Список разрешённых полей для владельца
            $allowedFieldsOwner = [
                'reg_number', 'show_reg_number',
                'car_brand_id', 'model', 'color', 'year',
                'engine_power', 'engine_volume', 'vin', 'roof_type', 'description'
            ];
            // Доп. поля для модератора
            $allowedFieldsModerator = array_merge($allowedFieldsOwner, [
                'status_id', // например, модератор может менять статус
                'owner_user_id' // смена владельца — только модератор/админ
            ]);

            $allowed = $isOwner ? $allowedFieldsOwner : $allowedFieldsModerator;
            $updateData = [];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $input)) {
                    $updateData[$field] = $input[$field];
                }
            }

            // Валидация бренда (если пришёл) — допускаем null (не выбрано)
            if (array_key_exists('car_brand_id', $updateData)) {
                $brandId = $updateData['car_brand_id'];
                if ($brandId === '' || $brandId === null) {
                    $updateData['car_brand_id'] = null;
                } else {
                    $pdo = Database::getInstance();
                    $st = $pdo->prepare('SELECT 1 FROM ref_car_brands WHERE id = ?');
                    $st->execute([ (int)$brandId ]);
                    if (!$st->fetchColumn()) {
                        $this->json([
                            'success' => false,
                            'error' => [ 'code' => 'INVALID_BRAND', 'message' => 'Неизвестная марка автомобиля' ]
                        ], 400);
                        return;
                    }
                }
            }

            // Нормализация значений: пустые строки → NULL, числовые поля приводим, bool → 0/1
            $nullableStringFields = ['model','color','roof_type','vin','description','reg_number'];
            foreach ($nullableStringFields as $f) {
                if (array_key_exists($f, $updateData)) {
                    $val = is_string($updateData[$f]) ? trim($updateData[$f]) : $updateData[$f];
                    $updateData[$f] = ($val === '' ? null : $val);
                }
            }
            // Защита: если на фронте пришло текстовое значение "скрыт" для рег. номера — не перезаписываем БД
            if (array_key_exists('reg_number', $updateData)) {
                $rn = is_string($updateData['reg_number']) ? trim($updateData['reg_number']) : $updateData['reg_number'];
                if ($rn === 'скрыт') {
                    unset($updateData['reg_number']);
                }
            }
            $nullableIntFields = ['year'];
            foreach ($nullableIntFields as $f) {
                if (array_key_exists($f, $updateData)) {
                    $val = $updateData[$f];
                    if ($val === '' || $val === null || !is_numeric($val)) {
                        $updateData[$f] = null;
                    } else {
                        $updateData[$f] = (int)$val;
                    }
                }
            }
            $nullableNumFields = ['engine_power','engine_volume'];
            foreach ($nullableNumFields as $f) {
                if (array_key_exists($f, $updateData)) {
                    $val = $updateData[$f];
                    if ($val === '' || $val === null || !is_numeric($val)) {
                        $updateData[$f] = null;
                    } else {
                        // допускаем дробные значения
                        $updateData[$f] = $val + 0;
                    }
                }
            }
            if (array_key_exists('show_reg_number', $updateData)) {
                $updateData['show_reg_number'] = Car::isRegNumberPublic($updateData['show_reg_number']) ? 1 : 0;
            }

            if (empty($updateData)) {
                $this->json([
                    'success' => false,
                    'error' => [ 'code' => 'NO_DATA', 'message' => 'Нет данных для обновления' ]
                ], 400);
                return;
            }

            // Применяем обновление
            $updated = Car::updateWithDetails((int)$id, $updateData);
            if (!$updated) {
                $this->json([
                    'success' => false,
                    'error' => [ 'code' => 'UPDATE_FAILED', 'message' => 'Не удалось обновить автомобиль' ]
                ], 400);
                return;
            }

            // Приватность владельца в ответе
            if (!$this->checkAccess('api.cars.includeOwner') && isset($updated['owner'])) {
                unset($updated['owner']);
            }

            // Флаг прав на редактирование
            $updated['permissions'] = [ 'canEdit' => $isOwner || $this->isModerator() || $this->isAdmin() ];

            $this->logUserAction('update_car', [ 'car_id' => (int)$id, 'fields' => array_keys($updateData) ]);
            $label = trim((string)(($updated['brand']['name'] ?? $updated['brand_name'] ?? '') . ' ' . ($updated['model'] ?? '')));
            if ($label === '') {
                $label = 'id ' . (int)$id;
            }
            $this->audit('update', 'car', (int)$id, 'Изменил авто «' . mb_substr($label, 0, 80) . '»', 'cars');
            $this->json(['success' => true, 'data' => $updated, 'meta' => $this->getRequestInfo()]);

        } catch (Throwable $e) {
            Logger::error('CarController: update error', [ 'error' => $e->getMessage(), 'car_id' => $id ]);
            $this->json([
                'success' => false,
                'error' => [ 'code' => 'INTERNAL_ERROR', 'message' => $e->getMessage() ]
            ], 500);
        }
    }

    /**
     * Получить автомобиль по id
     * 
     * Требует авторизации: Да
     * Минимальная роль: user (свою карточку правит и без роли member)
     */
    public function getById($id)
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.cars.getById')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Получаем автомобиль с развернутыми данными
            $car = Car::findByIdWithDetails($id);
            if (!$car) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Автомобиль не найден'
                    ]
                ], 404);
                return;
            }
            // Флаг прав на редактирование
            $isOwner = isset($car['owner']) && isset($car['owner']['id']) && ((int)$car['owner']['id'] === (int)$this->getCurrentUserId());
            $car['permissions'] = [ 'canEdit' => $isOwner || $this->isModerator() || $this->isAdmin() ];

            $car = Car::applyRegNumberPrivacy($car, $isOwner);

            // Приватность: скрываем владельца, если нет прав на просмотр участников
            if (!$this->checkAccess('api.cars.includeOwner') && isset($car['owner'])) {
                unset($car['owner']);
            }
            
            // Логируем действие
            $this->logUserAction('get_car_by_id', [
                'car_id' => $id
            ]);
            
            $this->json([
                'success' => true,
                'data' => $car, // Развернутые данные автомобиля
                'meta' => $this->getRequestInfo()
            ]);
            
        } catch (Throwable $e) {
            Logger::error('CarController: getById error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId(),
                'car_id' => $id
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
     * Создать новый автомобиль
     * 
     * Требует авторизации: Да
     * Минимальная роль: guest
     */
    public function create()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.cars.create')) {
                return; // Ответ уже отправлен в requireAccess
            }

            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = [];
            }

            // Владелец всегда тот, кто нажал «Добавить мой авто» — чужого id с клиента не принимаем
            $uid = (int)$this->getCurrentUserId();
            $input['create_user_id'] = $uid;
            $input['owner_user_id'] = $uid;
            // Новое авто участника сначала на модерации, не «замечено»
            $input['status_id'] = Status::idByCode('pending', 6);
            
            // Логируем действие
            $this->logUserAction('create_car', [
                'input_data' => $input
            ]);

            // Создаем автомобиль с развернутыми данными
            $car = Car::createWithDetails($input);
            
            if (!$car) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CAR_CREATION_FAILED',
                        'message' => 'Не удалось создать автомобиль'
                    ]
                ], 400);
                return;
            }
            
            // Создатель = владелец, сразу можно править свою карточку
            $car['permissions'] = [ 'canEdit' => true ];
            $car = Car::applyRegNumberPrivacy($car, true);
            $label = trim((string)(($car['brand']['name'] ?? $car['brand_name'] ?? '') . ' ' . ($car['model'] ?? '')));
            $this->audit('create', 'car', (int)($car['id'] ?? 0), 'Добавил авто «' . mb_substr($label !== '' ? $label : 'новое', 0, 80) . '»', 'cars');

            $this->json([
                'success' => true,
                'data' => $car, // Развернутые данные созданного автомобиля
                'meta' => $this->getRequestInfo()
            ], 201);
            
        } catch (Throwable $e) {
            Logger::error('CarController: create error', [
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
     * Проверить автомобиль в клубе с OCR
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     * 
     * POST /api/actions/check-car-in-club
     */
    public function checkCarInClub()
    {
        try {
            // Проверка доступа
            if (!$this->requireAccess('api.actions.checkCarInClub')) {
                return;
            }
            
            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Логируем действие
            $this->logUserAction('check_car_in_club', [
                'input_data' => $input
            ]);
            
            // Проверяем наличие фото
            if (empty($input['photo'])) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_REQUIRED',
                        'message' => 'Фото автомобиля обязательно для проверки'
                    ]
                ], 400);
                return;
            }
            
            // Вызываем L3 Action с base64 данными
            $result = ___CheckCarInClubAction::handle($input);
            
            if ($result['success']) {
                $this->json([
                    'success' => true,
                    'data' => $result['data']
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }
            
        } catch (Throwable $e) {
            Logger::error('CarController: checkCarInClub error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Внутренняя ошибка сервера'
                ]
            ], 500);
        }
    }

    /**
     * Оставить визитку с OCR
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     * 
     * POST /api/actions/leave-business-card
     */
    public function leaveBusinessCard()
    {
        try {
            if (!$this->requireAccess('api.actions.leaveBusinessCard')) {
                return;
            }
            
            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Логируем действие
            $this->logUserAction('leave_business_card', [
                'input_data' => $input
            ]);
            
            // Вызываем L3 Action
            $result = ___LeaveBusinessCardAction::handle($input);
            
            if ($result['success']) {
                $this->json([
                    'success' => true,
                    'data' => $result['data']
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }
            
        } catch (Throwable $e) {
            Logger::error('CarController: leaveBusinessCard error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Внутренняя ошибка сервера'
                ]
            ], 500);
        }
    }

    /**
     * Добавить автомобиль в гараж с OCR
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     * 
     * POST /api/actions/add-car-to-garage
     */
    public function addCarToGarage()
    {
        try {
            if (!$this->requireAccess('api.actions.addCarToGarage')) {
                return;
            }
            
            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Логируем входящий запрос
            Logger::info('CarController: Incoming request', [
                'method' => 'POST',
                'endpoint' => '/api/actions/add-car-to-garage',
                'input_data' => $input,
                'headers' => getallheaders()
            ]);
            
            // Логируем действие
            $this->logUserAction('add_car_to_garage', [
                'input_data' => $input
            ]);
            
            // Вызываем L3 Action
            $result = ___AddCarToGarageAction::handle($input);
            
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'data' => $result['data']
                ];
                
                // Логируем успешный ответ
                Logger::info('CarController: Success response', [
                    'http_code' => 200,
                    'response' => $response
                ]);
                
                $this->json($response);
            } else {
                $response = [
                    'success' => false,
                    'error' => $result['error']
                ];
                
                // Логируем ответ с ошибкой
                Logger::info('CarController: Error response', [
                    'http_code' => 400,
                    'response' => $response
                ]);
                
                $this->json($response, 400);
            }
            
        } catch (Throwable $e) {
            Logger::error('CarController: addCarToGarage error', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Внутренняя ошибка сервера'
                ]
            ], 500);
        }
    }
    
    /**
     * Конвертирует base64 в временный файл
     * 
     * @param string $base64Data - Base64 данные
     * @return string|false - Путь к временному файлу или false при ошибке
     */
    private function convertBase64ToTempFile($base64Data) {
        if (empty($base64Data)) {
            return false;
        }
        
        try {
            // Декодируем base64
            $binaryData = base64_decode($base64Data, true);
            if ($binaryData === false) {
                Logger::error('CarController: Invalid base64 data');
                return false;
            }
            
            // Создаем временный файл
            $tempFile = tempnam(sys_get_temp_dir(), 'car_photo_');
            if ($tempFile === false) {
                Logger::error('CarController: Failed to create temp file');
                return false;
            }
            
            // Записываем данные в файл
            if (file_put_contents($tempFile, $binaryData) === false) {
                Logger::error('CarController: Failed to write to temp file');
                unlink($tempFile);
                return false;
            }
            
            Logger::info('CarController: Base64 converted to temp file', [
                'temp_file' => $tempFile,
                'size' => filesize($tempFile)
            ]);
            
            return $tempFile;
            
        } catch (Exception $e) {
            Logger::error('CarController: convertBase64ToTempFile error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 