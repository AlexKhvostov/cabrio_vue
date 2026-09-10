# Структура backend CabrioRide

Данный каталог содержит весь серверный код проекта CabrioRide. Ниже приведена рекомендуемая структура файлов и папок с пояснениями.

```
backend/
│
├── controllers/                # HTTP-слой (точка входа)
│   ├── BaseController.php          # Базовый класс контроллеров
│   ├── UserController.php          # Пользователи: CRUD, профиль, действия с пользователем
│   ├── CarController.php           # Автомобили: CRUD, действия с авто
│   ├── EventController.php         # События: CRUD, участие, приглашения
│   ├── GuideObjectController.php   # Гид-объекты: CRUD, модерация
│   ├── ReviewController.php        # Отзывы: CRUD, модерация
│   ├── MapHintController.php       # Подсказки на карте: CRUD
│   ├── BusinessCardController.php  # Визитки: CRUD
│   ├── PhotoController.php         # Фото: загрузка, удаление, получение
│   ├── ReferenceController.php     # Справочники: роли, статусы, типы и т.д.
│   ├── AuthController.php          # Авторизация, сессии, выход
│   ├── ActivityController.php      # Активность пользователя: выдача, история
│   └── MeController.php            # Информация о текущем пользователе ("/me")
│
├── actions/                    # Бизнес-логика (уровни 1-4)
│   ├── level1/                # Базовые операции (префикс: _)
│   │   ├── _CheckUserExistsAction.php           # Проверка существования пользователя
│   │   ├── _FindCarByNumberAction.php           # Поиск авто по номеру
│   │   └── ...                                 # Другие базовые операции
│   ├── level2/                # Бизнес-операции (префикс: __)
│   │   ├── __AddCarForUserAction.php            # Добавление авто пользователю
│   │   ├── __AddUserToEventAction.php           # Добавление пользователя к событию
│   │   ├── __EditUserProfileAction.php          # Редактирование профиля
│   │   ├── __RemoveCarFromUserAction.php        # Удаление авто у пользователя
│   │   └── ...                                 # Другие бизнес-операции
│   ├── level3/                # Сложные сценарии (префикс: ___)
│   │   ├── ___ProcessCarRegistrationAction.php  # Регистрация авто
│   │   ├── ___TransferCarOwnershipAction.php    # Передача авто
│   │   ├── ___SyncUserFromTelegramAction.php    # Синхронизация из Telegram
│   │   └── ...                                 # Другие сложные сценарии
│   └── level4/                # Комплексные сценарии (префикс: ____)
│       ├── ____ExecuteUserOnboardingAction.php  # Полная регистрация пользователя
│       ├── ____HandleEventCreationAction.php    # Создание события с уведомлениями
│       └── ...                                 # Другие комплексные сценарии
│
├── models/                     # Уровень 0 - работа с БД, структура данных
│   ├── User.php                    # Модель пользователя
│   ├── Car.php                     # Модель автомобиля
│   ├── Event.php                   # Модель события
│   ├── GuideObject.php             # Модель гид-объекта
│   ├── Review.php                  # Модель отзыва
│   ├── MapHint.php                 # Модель подсказки на карте
│   ├── BusinessCard.php            # Модель визитки
│   ├── Photo.php                   # Модель фото
│   ├── Role.php                    # Модель роли (справочник)
│   ├── Status.php                  # Модель статуса (справочник)
│   └── ...                         # Другие модели по необходимости
│
├── routes/
│   └── api.php                 # Файл маршрутов: связывает URL с методами контроллеров
│
├── utils/                      # Общие утилиты
│   ├── ResponseHelper.php          # Формирование стандартных ответов API
│   ├── AuthHelper.php              # Проверка авторизации, ролей, токенов
│   ├── ValidationHelper.php        # Валидация входных данных
│   ├── Database.php                # Подключение к базе данных через параметры из .env (Singleton)
│   ├── Logger.php                  # Логирование событий и ошибок
│   ├── load_env.php               # Загрузка переменных окружения
│   └── ...                        # Другие утилиты по необходимости
│
├── docs/                       # Вся документация по backend
│   ├── CONVENTIONS.md              # Стандарты и правила для backend
│   ├── ARCHITECTURE_GUIDE.md      # Руководство по архитектуре и уровням абстракции
│   ├── ENDPOINTS.md                # Полный перечень эндпоинтов
│   ├── ENVIRONMENT.md              # Переменные окружения
│   └── openapi.yaml                # OpenAPI спецификация
├── _tests/                     # Интеграционные тесты backend
│   └── README.md                   # Оглавление и инструкция по тестам
└── README.md                   # Краткое описание структуры backend, ссылки на документацию
```

---

## 🎯 Уровни абстракции (L0-L4)

### **L0 (Модели)** — работа с БД
- Только прямые CRUD операции
- Маппинг данных из БД в объекты
- Базовая валидация данных
- **НЕ содержит бизнес-логику**

### **L1 (Базовые операции)** — простые комбинации
- Комбинация 2-3 операций L0
- Простая бизнес-логика
- Базовая валидация данных
- Обработка файлов (фото, документы)
- **Префикс: 1 подчёркивание (_)**

### **L2 (Бизнес-операции)** — проверки и правила
- Используют Actions L1
- Проверки прав доступа
- Бизнес-правила
- Валидация бизнес-логики
- **Префикс: 2 подчёркивания (__)**

### **L3 (Сложные сценарии)** — транзакции и интеграции
- Используют Actions L2
- Транзакции БД
- Интеграции с внешними API
- Множественные операции
- **Префикс: 3 подчёркивания (___)**

### **L4 (Комплексные сценарии)** — полные пользовательские сценарии
- Используют Actions L3
- Полные пользовательские сценарии
- Оркестрация множественных операций
- **Префикс: 4 подчёркивания (____)**

---

## Документация backend

### Основная документация
- [Стандарты и соглашения (CONVENTIONS.md)](docs/CONVENTIONS.md)
- [Руководство по архитектуре (ARCHITECTURE_GUIDE.md)](docs/ARCHITECTURE_GUIDE.md)
- [Взаимодействие Actions (ACTIONS_INTERACTION.md)](actions/ACTIONS_INTERACTION.md)
- [Эндпоинты API (ENDPOINTS.md)](docs/ENDPOINTS.md)
- [Переменные окружения (ENVIRONMENT.md)](docs/ENVIRONMENT.md)
- [OpenAPI спецификация (openapi.yaml)](docs/openapi.yaml)

### Новая документация
- [Коды ошибок API (ERROR_CODES.md)](docs/ERROR_CODES.md)
- [Развёртывание (DEPLOYMENT.md)](docs/DEPLOYMENT.md)
- [Безопасность (SECURITY.md)](docs/SECURITY.md)
- [Тестирование (TESTING.md)](docs/TESTING.md)
- [Производительность (PERFORMANCE.md)](docs/PERFORMANCE.md)

### Архитектура и зависимости
- [Модели (MODELS.md)](docs/MODELS.md)
- [Зависимости (DEPENDENCIES.md)](docs/DEPENDENCIES.md)
- [Диаграмма зависимостей (DEPENDENCIES_DIAGRAM.md)](docs/DEPENDENCIES_DIAGRAM.md)

## Тесты backend

- [Оглавление и инструкция по тестам](../backend/_tests/README.md)
- Все интеграционные тесты размещаются в каталоге _tests/

---

## Схема движения запроса по backend

```
HTTP Request → Controller → L2/L3 Action → L1 Action → L0 Model → Database
                ↓              ↓              ↓
              Utils ←────── Utils ←────── Utils
                ↓              ↓              ↓
           External APIs   File System   Logging
```

---

## 🛠️ Использование утилит

### Подключение утилит в начале файла:
```php
<?php
require_once __DIR__ . '/../utils/load_env.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/AuthHelper.php';
require_once __DIR__ . '/../utils/ValidationHelper.php';
require_once __DIR__ . '/../utils/ResponseHelper.php';
require_once __DIR__ . '/../utils/Logger.php';
```

### Примеры использования:
```php
// Подключение к БД
$pdo = Database::getInstance();

// Проверка авторизации
$userId = AuthHelper::checkAuth();

// Валидация данных
ValidationHelper::requireFields($data, ['email']);

// Формирование ответов
echo ResponseHelper::success($data);

// Логирование
Logger::info('Operation completed');
```

---

> **Важно:** Следуйте архитектуре с уровнями абстракции и используйте существующие утилиты для общих операций! 