# 🔄 Стандарты взаимодействия Actions в backend CabrioRide

> **Назначение:** Описание способов общения Actions между собой, передачи данных, обработки ошибок и транзакций.

---

## 🎯 **Принципы взаимодействия Actions**

### **1. Иерархия вызовов**
```
L4 (Комплексные) → L3 (Сложные) → L2 (Бизнес) → L1 (Базовые) → L0 (Модели)
```

### **2. Правила вызовов**
- **L4** может вызывать **L3, L2, L1** Actions
- **L3** может вызывать **L2, L1** Actions  
- **L2** может вызывать **L1** Actions
- **L1** может вызывать только **L0** Models
- **L0** (Models) не вызывает Actions

### **3. Стандартный интерфейс Actions**
```php
class _ActionName {
    /**
     * Основной метод Action
     * @param array $data Входные данные
     * @return array Результат выполнения
     */
    public static function handle($data) {
        try {
            // Логика Action
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---

## 📤 **Передача данных между Actions**

### **1. Стандартный формат входных данных**
```php
$data = [
    'user_id' => 123,
    'car_id' => 456,
    'event_id' => 789,
    'params' => [
        'name' => 'BMW Z4',
        'color' => 'red'
    ],
    'context' => [
        'source' => 'telegram_bot',
        'user_role' => 'member'
    ]
];
```

### **2. Стандартный формат результата**
```php
// Успешный результат
return [
    'success' => true,
    'data' => [
        'id' => 123,
        'name' => 'BMW Z4',
        'created_at' => '2024-01-01 12:00:00'
    ],
    'meta' => [
        'affected_rows' => 1,
        'execution_time' => 0.05
    ]
];

// Результат с ошибкой
return [
    'success' => false,
    'error' => [
        'code' => 'VALIDATION_ERROR',
        'message' => 'Некорректные данные',
        'details' => ['name' => 'Поле обязательно']
    ]
];
```

### **3. Пример вызова Action из другого Action**
```php
// L2 Action вызывает L1 Action
class __AddCarForUserAction {
    public static function handle($data) {
        try {
            // Валидация входных данных
            ValidationHelper::requireFields($data, ['user_id', 'car_data']);
            
            // Вызов L1 Action
            $createCarResult = _CreateCarWithPhotoAction::handle([
                'car_data' => $data['car_data'],
                'user_id' => $data['user_id']
            ]);
            
            if (!$createCarResult['success']) {
                return $createCarResult; // Возвращаем ошибку
            }
            
            // Дополнительная логика L2
            $linkResult = _LinkUserToCarAction::handle([
                'user_id' => $data['user_id'],
                'car_id' => $createCarResult['data']['id']
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'car' => $createCarResult['data'],
                    'link' => $linkResult['data']
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('__AddCarForUserAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

---

## 🔄 **Транзакции БД между Actions**

### **1. Транзакции на уровне L3/L4**
```php
class ___ProcessCarRegistrationAction {
    public static function handle($data) {
        $pdo = Database::getInstance();
        
        try {
            $pdo->beginTransaction();
            
            // Вызов L2 Actions в транзакции
            $carResult = __AddCarForUserAction::handle($data);
            if (!$carResult['success']) {
                $pdo->rollBack();
                return $carResult;
            }
            
            $notificationResult = __SendNotificationAction::handle([
                'user_id' => $data['user_id'],
                'type' => 'car_registered',
                'car_id' => $carResult['data']['car']['id']
            ]);
            
            if (!$notificationResult['success']) {
                $pdo->rollBack();
                return $notificationResult;
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'data' => [
                    'car' => $carResult['data']['car'],
                    'notification' => $notificationResult['data']
                ]
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            Logger::error('___ProcessCarRegistrationAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

### **2. Автоматическое управление транзакциями**
```php
class TransactionHelper {
    /**
     * Выполняет Action в транзакции
     */
    public static function executeInTransaction($action, $data) {
        $pdo = Database::getInstance();
        
        try {
            $pdo->beginTransaction();
            $result = $action::handle($data);
            
            if ($result['success']) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
            
            return $result;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            Logger::error('Transaction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

---

## 🚨 **Обработка ошибок**

### **1. Проброс ошибок вверх по иерархии**
```php
class L1Action {
    public static function handle($data) {
        try {
            // Логика L1
            $result = User::create($data);
            return ['success' => true, 'data' => $result];
            
        } catch (ValidationException $e) {
            // Специфичная ошибка валидации
            return [
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'details' => $e->getDetails()
                ]
            ];
            
        } catch (DatabaseException $e) {
            // Ошибка БД
            Logger::error('L1Action DB error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => 'Ошибка базы данных'
                ]
            ];
            
        } catch (Exception $e) {
            // Общая ошибка
            Logger::error('L1Action error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Внутренняя ошибка'
                ]
            ];
        }
    }
}
```

### **2. Логирование цепочки вызовов**
```php
class ActionLogger {
    public static function logCall($actionName, $data, $result) {
        Logger::info("Action called: $actionName", [
            'input' => $data,
            'output' => $result,
            'timestamp' => date('c'),
            'memory_usage' => memory_get_usage(true)
        ]);
    }
}
```

---

## 📊 **Мониторинг и метрики**

### **1. Время выполнения Actions**
```php
class ActionMetrics {
    public static function measureExecution($actionName, $callback) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $result = $callback();
        
        $executionTime = microtime(true) - $startTime;
        $memoryUsage = memory_get_usage(true) - $startMemory;
        
        Logger::info("Action metrics: $actionName", [
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'success' => $result['success']
        ]);
        
        return $result;
    }
}
```

### **2. Использование в Actions**
```php
class _CreateUserAction {
    public static function handle($data) {
        return ActionMetrics::measureExecution('_CreateUserAction', function() use ($data) {
            // Логика Action
            return ['success' => true, 'data' => $result];
        });
    }
}
```

---

## 🔧 **Утилиты для взаимодействия Actions**

### **1. ActionHelper — общие функции**
```php
class ActionHelper {
    /**
     * Вызывает Action с валидацией результата
     */
    public static function callAction($actionName, $data) {
        $result = $actionName::handle($data);
        
        if (!isset($result['success'])) {
            throw new Exception("Invalid Action result format");
        }
        
        return $result;
    }
    
    /**
     * Проверяет успешность результата Action
     */
    public static function isSuccess($result) {
        return isset($result['success']) && $result['success'] === true;
    }
    
    /**
     * Извлекает данные из результата Action
     */
    public static function getData($result) {
        return $result['data'] ?? null;
    }
    
    /**
     * Извлекает ошибку из результата Action
     */
    public static function getError($result) {
        return $result['error'] ?? null;
    }
}
```

### **2. Пример использования**
```php
class __ComplexAction {
    public static function handle($data) {
        try {
            // Вызов L1 Action
            $result1 = ActionHelper::callAction('_CreateUserAction', $data);
            if (!ActionHelper::isSuccess($result1)) {
                return $result1;
            }
            
            // Вызов другого L1 Action
            $result2 = ActionHelper::callAction('_CreateCarAction', [
                'user_id' => ActionHelper::getData($result1)['id'],
                'car_data' => $data['car_data']
            ]);
            
            if (!ActionHelper::isSuccess($result2)) {
                return $result2;
            }
            
            return [
                'success' => true,
                'data' => [
                    'user' => ActionHelper::getData($result1),
                    'car' => ActionHelper::getData($result2)
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('__ComplexAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

---

## 📋 **Чек-лист для создания Actions**

### **Перед созданием Action:**
- [ ] Определить уровень абстракции (L1-L4)
- [ ] Выбрать правильный префикс (_/__/___/____)
- [ ] Определить входные и выходные данные
- [ ] Планировать вызовы других Actions

### **При создании Action:**
- [ ] Использовать стандартный интерфейс `handle($data)`
- [ ] Возвращать стандартный формат результата
- [ ] Обрабатывать ошибки и исключения
- [ ] Логировать важные события
- [ ] Использовать утилиты для взаимодействия

### **После создания Action:**
- [ ] Протестировать с разными входными данными
- [ ] Проверить обработку ошибок
- [ ] Убедиться в корректности логирования
- [ ] Документировать использование

---

> **Важно:** Следуйте этим стандартам для обеспечения надёжности и предсказуемости взаимодействия Actions! 