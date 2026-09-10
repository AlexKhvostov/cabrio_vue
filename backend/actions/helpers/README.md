# 🔧 Helpers для Actions

> **Назначение:** Вспомогательные функции для переиспользования в Actions всех уровней.

---

## 📋 **Принципы использования**

### **✅ Когда создавать Helper:**
- Функция используется в **2+ Actions** разных уровней
- Логика **не привязана к конкретной сущности** (User, Car, etc.)
- **Универсальная операция** (валидация, форматирование, вычисления)
- **Внешние интеграции** (OCR, API, файловые операции)

### **❌ Когда НЕ создавать Helper:**
- Функция используется **только в одном Action**
- Логика **специфична для конкретной сущности**
- **Простые операции** (сложение, конкатенация строк)
- **Бизнес-логика** (должна быть в Actions)

---

## 🗂️ **Структура файлов**

```
helpers/
├── README.md                    # Этот файл
├── FileHelper.php              # Работа с файлами
├── ValidationHelper.php         # Дополнительная валидация
├── FormatHelper.php            # Форматирование данных
├── CalculationHelper.php       # Математические вычисления
├── IntegrationHelper.php       # Внешние интеграции
└── DatabaseHelper.php          # Дополнительные DB операции
```

---

## 📝 **Правила именования**

### **Файлы:**
- `{Назначение}Helper.php` - PascalCase
- Пример: `FileHelper.php`, `ValidationHelper.php`

### **Классы:**
- `{Назначение}Helper` - PascalCase
- Пример: `FileHelper`, `ValidationHelper`

### **Методы:**
- `{действие}{Объект}` - camelCase
- Пример: `savePhoto()`, `validatePlateNumber()`

---

## 🔄 **Примеры использования**

### **В L1 Action:**
```php
// backend/actions/level1/_CreateCarAction.php
require_once __DIR__ . '/../helpers/FileHelper.php';

class _CreateCarAction {
    public static function handle($data) {
        // Сохранение фото
        $photoPath = FileHelper::savePhoto($data['photo'], 'cars');
        
        // Создание авто с путем к фото
        $carData['photo_path'] = $photoPath;
        return Car::create($carData);
    }
}
```

### **В L2 Action:**
```php
// backend/actions/level2/__SearchCarAction.php
require_once __DIR__ . '/../helpers/ValidationHelper.php';

class __SearchCarAction {
    public static function handle($plateNumber, $telegramId) {
        // Валидация номера
        ValidationHelper::validatePlateNumber($plateNumber);
        
        // Бизнес-логика...
    }
}
```

### **В L3 Action:**
```php
// backend/actions/level3/___CheckCarInClubAction.php
require_once __DIR__ . '/../helpers/IntegrationHelper.php';

class ___CheckCarInClubAction {
    public static function handle($photoFile, $telegramId) {
        // OCR распознавание
        $plateNumber = IntegrationHelper::recognizePlateNumber($photoFile);
        
        // Остальная логика...
    }
}
```

---

## ⚡ **Преимущества Helpers**

### **✅ Переиспользование:**
- Один код используется в разных Actions
- Изменения в одном месте влияют на все использования

### **✅ Тестируемость:**
- Helpers можно тестировать отдельно
- Упрощает unit-тесты для Actions

### **✅ Поддерживаемость:**
- Логика вынесена в отдельные файлы
- Легко найти и исправить проблемы

### **✅ Читаемость:**
- Actions содержат только бизнес-логику
- Вспомогательные операции скрыты в Helpers

---

## 🚫 **Ограничения**

### **❌ Не размещать в Helpers:**
- Бизнес-логику (должна быть в Actions)
- Специфичные для сущности операции
- Простые операции (сложение, конкатенация)
- Логику авторизации (должна быть в AuthHelper)

### **❌ Не создавать Helpers для:**
- Операций, используемых только один раз
- Слишком специфичных функций
- Функций, которые легко реализовать inline

---

## 📚 **Документация**

Каждый Helper должен содержать:
1. **Описание назначения** в комментарии класса
2. **Документацию методов** с примерами
3. **Примеры использования** в комментариях
4. **Обработку ошибок** с понятными сообщениями

**Helpers делают код более чистым и переиспользуемым!** 🚀 