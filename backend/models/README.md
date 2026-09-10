# models/

Модели — работа с БД, описание структуры данных для каждой сущности.

## 🎯 **Принципы работы с моделями**

### **1️⃣ Структура модели**
```php
<?php
/**
 * Модель User — работа с таблицей users.
 *
 * Назначение: Представляет пользователя системы
 * Ключевые поля: id, telegram_id, username, role_id
 * Связи: Role (role_id → ref_roles.id)
 */
require_once __DIR__ . '/../utils/Database.php';

class User {
    // Публичные свойства для всех полей таблицы
    public $id;
    public $telegram_id;
    public $username;
    public $role_id;
    // ... другие поля

    /**
     * Конструктор - заполняет свойства из массива данных
     */
    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    // ОБЯЗАТЕЛЬНЫЕ МЕТОДЫ (см. ниже)
}
```

### **2️⃣ Обязательные методы для каждой модели**

#### **✅ `findById($id)` - поиск по ID**
```php
public static function findById($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    return $data ? new self($data) : null;
}
```

#### **✅ `create($data)` - создание записи**
```php
public static function create($data) {
    $pdo = Database::getInstance();
    
    // Подготовка данных для вставки
    $fields = ['telegram_id', 'username', 'role_id'];
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $fieldNames = implode(', ', $fields);
    
    $stmt = $pdo->prepare("INSERT INTO users ($fieldNames, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())");
    
    $values = [
        $data['telegram_id'],
        $data['username'],
        $data['role_id'] ?? 1  // значение по умолчанию
    ];
    
    $stmt->execute($values);
    return $pdo->lastInsertId();  // Возвращаем ID созданной записи
}
```

#### **✅ `toArray()` - экспорт в массив**
```php
public function toArray() {
    return [
        'id' => $this->id,
        'telegram_id' => $this->telegram_id,
        'username' => $this->username,
        'role_id' => $this->role_id,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at
        // НЕ включаем чувствительные данные!
    ];
}
```

### **3️⃣ Специализированные методы**

#### **🔍 Поисковые методы**
```php
// Поиск по уникальному полю
public static function findByTelegramId($telegramId) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
    $stmt->execute([$telegramId]);
    $data = $stmt->fetch();
    return $data ? new self($data) : null;
}

// Поиск по номеру (для Car)
public static function findByPlateNumber($plateNumber) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT * FROM cars WHERE reg_number = ?');
    $stmt->execute([$plateNumber]);
    $data = $stmt->fetch();
    return $data ? new self($data) : null;
}
```

#### **🔄 Методы обновления**
```php
// Статический метод для обновления
public static function update($data) {
    $pdo = Database::getInstance();
    
    $updates = [];
    $values = [];
    
    // Подготавливаем поля для обновления
    $fields = ['username', 'first_name_tg', 'last_name_tg', 'email'];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        return false; // Нет данных для обновления
    }
    
    $values[] = $data['id']; // ID для WHERE
    $updates[] = "updated_at = NOW()";
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute($values);
}

// Специализированные методы обновления
public static function updateRole($userId, $roleId) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?');
    return $stmt->execute([$roleId, $userId]);
}
```

### **4️⃣ Принципы безопасности**

#### **🔒 Контроль экспортируемых данных**
```php
public function toArray() {
    return [
        'id' => $this->id,
        'username' => $this->username,
        'role_id' => $this->role_id,
        // НЕ включаем:
        // 'password_hash' => $this->password_hash,
        // 'internal_notes' => $this->internal_notes,
    ];
}
```

#### **✅ Валидация входных данных**
```php
public static function create($data) {
    // Проверяем обязательные поля
    if (empty($data['telegram_id'])) {
        throw new Exception('telegram_id обязателен');
    }
    
    // Устанавливаем значения по умолчанию
    $data['role_id'] = $data['role_id'] ?? 1;
    
    // ... остальная логика
}
```

### **5️⃣ Паттерны использования**

#### **📋 В Action'ах**
```php
// Создание
$userId = User::create($userData);
$user = User::findById($userId);

// Поиск
$user = User::findByTelegramId($telegramId);
if ($user) {
    return ['success' => true, 'data' => $user->toArray()];
}

// Обновление
$updateData = ['id' => $userId, 'username' => 'new_username'];
User::update($updateData);
```

#### **📋 В API ответах**
```php
// Всегда возвращаем массивы через toArray()
return [
    'success' => true,
    'data' => $model->toArray()
];
```

### **6️⃣ Связи между моделями**

#### **🔗 Foreign Key связи**
```php
// В Car модели
public $owner_user_id;  // FK на users.id

// Метод для получения владельца
public function getOwner() {
    return User::findById($this->owner_user_id);
}
```

#### **🔗 Многие ко многим**
```php
// В User модели
public static function getCars($userId) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('
        SELECT c.* FROM cars c 
        JOIN link_user_cars luc ON c.id = luc.car_id 
        WHERE luc.user_id = ?
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
```

### **7️⃣ Обработка ошибок**

#### **⚠️ Проверка существования**
```php
public static function findById($id) {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    return $data ? new self($data) : null;  // null если не найден
}
```

#### **⚠️ Обработка исключений**
```php
public static function create($data) {
    try {
        $pdo = Database::getInstance();
        // ... логика создания
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        // Логируем ошибку
        Logger::error('User::create failed: ' . $e->getMessage());
        return false;
    }
}
```

### **8️⃣ Тестирование моделей**

#### **🧪 Консольные тесты**
```php
// backend/_tests/test_models.php
$user = User::create([
    'telegram_id' => time(),
    'username' => 'test_user'
]);
echo "Создан пользователь ID: " . $user . "\n";
```

#### **🧪 Веб-тесты**
```php
// backend/_tests/test_models_api.php
case 'user_create':
    $userId = User::create($input['data']);
    $response = ['success' => true, 'data' => ['id' => $userId]];
```

## 🎯 **Чек-лист для новых моделей**

- [ ] Создан класс с публичными свойствами
- [ ] Реализован конструктор `__construct($data)`
- [ ] Добавлен метод `findById($id)`
- [ ] Добавлен метод `create($data)`
- [ ] Добавлен метод `toArray()`
- [ ] Добавлены специализированные методы поиска
- [ ] Добавлены методы обновления
- [ ] Проверена безопасность (нет чувствительных данных в toArray())
- [ ] Добавлены значения по умолчанию
- [ ] Написаны тесты
- [ ] Обновлена документация

## 📚 **Примеры моделей**

- `User.php` - пользователи системы
- `Car.php` - автомобили участников
- `BusinessCard.php` - визитки/приглашения
- `Photo.php` - фотографии сущностей
- `Role.php` - роли пользователей
- `Status.php` - статусы сущностей

## 🚀 **Следующие шаги**

После создания модели:
1. Создать L1 Actions для работы с моделью
2. Написать тесты для проверки функциональности
3. Интегрировать в L2 и L3 Actions
4. Добавить в контроллеры для API endpoints 