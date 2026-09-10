# 🔧 Примеры использования Helpers в Actions

> **Назначение:** Практические примеры использования Helpers в Actions всех уровней.

---

## 📸 **FileHelper - Работа с файлами**

### **В L1 Action (создание авто с фото):**
```php
// backend/actions/level1/_CreateCarAction.php
require_once __DIR__ . '/../helpers/FileHelper.php';

class _CreateCarAction {
    public static function handle($data) {
        try {
            // Валидация данных
            ValidationHelper::validateCarData($data);
            
            // Сохранение фото если есть
            if (isset($data['photo']) && !empty($data['photo'])) {
                $photoPath = FileHelper::savePhoto($data['photo'], 'cars', $data['id'] ?? null);
                $data['photo_path'] = $photoPath;
            }
            
            // Создание авто
            return Car::create($data);
            
        } catch (Exception $e) {
            Logger::error('_CreateCarAction failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

### **В L2 Action (оставление визитки с фото):**
```php
// backend/actions/level2/__DropBusinessCardAction.php
require_once __DIR__ . '/../helpers/FileHelper.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class __DropBusinessCardAction {
    public static function handle($plateNumber, $telegramId, $message, $photoFile) {
        try {
            // Валидация номера
            ValidationHelper::validatePlateNumber($plateNumber);
            
            // Создание визитки
            $businessCardData = [
                'car_id' => $carId,
                'user_id' => $telegramId,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            ValidationHelper::validateBusinessCardData($businessCardData);
            $businessCardId = _CreateBusinessCardAction::handle($businessCardData);
            
            // Сохранение фото визитки
            if ($photoFile) {
                $photoPath = FileHelper::savePhoto($photoFile, 'business_cards', $businessCardId);
                
                $photoData = [
                    'entity_type' => 'business_card',
                    'entity_id' => $businessCardId,
                    'file_path' => $photoPath
                ];
                
                ValidationHelper::validatePhotoData($photoData);
                _CreatePhotoAction::handle($photoData);
            }
            
            return ['success' => true, 'data' => ['business_card_id' => $businessCardId]];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

## 🔍 **IntegrationHelper - Внешние интеграции**

### **В L3 Action (распознавание номера):**
```php
// backend/actions/level3/___CheckCarInClubAction.php
require_once __DIR__ . '/../helpers/IntegrationHelper.php';

class ___CheckCarInClubAction {
    public static function handle($photoFile, $telegramId) {
        try {
            // Распознавание номера через OCR
            $plateNumber = IntegrationHelper::recognizePlateNumber($photoFile);
            
            // Поиск авто в клубе
            $result = __SearchCarAction::handle($plateNumber, $telegramId);
            
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

### **В L3 Action (оставление визитки):**
```php
// backend/actions/level3/___LeaveBusinessCardAction.php
require_once __DIR__ . '/../helpers/IntegrationHelper.php';

class ___LeaveBusinessCardAction {
    public static function handle($photoFile, $telegramId, $message = null) {
        try {
            // Распознавание номера
            $plateNumber = IntegrationHelper::recognizePlateNumber($photoFile);
            
            // Оставление визитки
            $result = __DropBusinessCardAction::handle($plateNumber, $telegramId, $message, $photoFile);
            
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

## ✅ **ValidationHelper - Дополнительная валидация**

### **В L1 Action (создание пользователя):**
```php
// backend/actions/level1/_CreateUserAction.php
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class _CreateUserAction {
    public static function handle($data) {
        try {
            // Валидация данных пользователя
            ValidationHelper::validateUserData($data);
            
            // Создание пользователя
            $userData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'telegram_id' => $data['telegram_id'],
                'username' => $data['username'] ?? null,
                'role_id' => $role->id,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return User::create($userData);
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

### **В L2 Action (синхронизация пользователя):**
```php
// backend/actions/level2/__SyncUserDataAction.php
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class __SyncUserDataAction {
    public static function handle($userData) {
        try {
            // Валидация входных данных
            ValidationHelper::validateUserData($userData);
            
            // Проверка существования пользователя
            $existingUser = _CheckUserByTelegramIdAction::handle($userData['telegram_id']);
            
            if ($existingUser) {
                // Обновление существующего пользователя
                return _UpdateUserAction::handle($userData);
            } else {
                // Создание нового пользователя
                return _CreateUserAction::handle($userData);
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

## 🔄 **Комбинированное использование**

### **В L3 Action (полный сценарий):**
```php
// backend/actions/level3/___AddCarToGarageAction.php
require_once __DIR__ . '/../helpers/IntegrationHelper.php';
require_once __DIR__ . '/../helpers/FileHelper.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class ___AddCarToGarageAction {
    public static function handle($photoFile, $telegramId) {
        try {
            // 1. Распознавание номера (IntegrationHelper)
            $plateNumber = IntegrationHelper::recognizePlateNumber($photoFile);
            
            // 2. Валидация номера (ValidationHelper)
            ValidationHelper::validatePlateNumber($plateNumber);
            
            // 3. Добавление авто пользователю (L2)
            $result = __AddCarToUserAction::handle($plateNumber, $telegramId, $photoFile);
            
            // 4. Сохранение фото если нужно (FileHelper)
            if ($result['success'] && $photoFile) {
                $carId = $result['data']['car_id'];
                $photoPath = FileHelper::savePhoto($photoFile, 'cars', $carId);
                
                // Обновление пути к фото в БД
                _UpdateCarPhotoAction::handle($carId, $photoPath);
            }
            
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

## 📊 **Преимущества использования Helpers**

### **✅ Переиспользование кода:**
- Один `FileHelper::savePhoto()` используется в разных Actions
- `ValidationHelper::validatePlateNumber()` применяется везде где нужна валидация номера
- `IntegrationHelper::recognizePlateNumber()` централизованно обрабатывает OCR

### **✅ Упрощение тестирования:**
```php
// Тест FileHelper отдельно
public function testSavePhoto() {
    $result = FileHelper::savePhoto($testFile, 'cars', 123);
    $this->assertTrue($result !== false);
}

// Тест Action без файловой логики
public function testCreateCarAction() {
    $result = _CreateCarAction::handle($testData);
    $this->assertTrue($result['success']);
}
```

### **✅ Легкость поддержки:**
- Изменения в `FileHelper` автоматически применяются ко всем Actions
- Добавление новой валидации в `ValidationHelper` доступно всем
- Обновление OCR логики в одном месте

### **✅ Читаемость кода:**
```php
// Без Helpers - много кода
class _CreateCarAction {
    public static function handle($data) {
        // 50 строк валидации файла
        // 30 строк сохранения файла
        // 20 строк обработки ошибок
        // 10 строк бизнес-логики
    }
}

// С Helpers - чистый код
class _CreateCarAction {
    public static function handle($data) {
        ValidationHelper::validateCarData($data);
        $photoPath = FileHelper::savePhoto($data['photo'], 'cars');
        return Car::create($data);
    }
}
```

**Helpers делают код более чистым, переиспользуемым и легким в поддержке!** 🚀 