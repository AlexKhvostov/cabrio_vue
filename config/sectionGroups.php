<?php
/**
 * sectionGroups.php
 * 
 * Единая точка правды для схемы доступа (roles/functions).
 * Для каждой функции/эндпоинта указывается минимальная роль для доступа.
 * Используется и на frontend, и на backend. Все изменения — только здесь!
 *
 * Визуальная схема и подробное описание принципа доступа — см. docs/ACCESS_SCHEME.md
 *
 * ---
 *
 * 📋 Что такое минимальная роль?
 * Минимальная роль — это наименьшая роль, с которой разрешён доступ к функции.
 * Если у пользователя роль равна или выше минимальной (по порядку в ROLES), доступ разрешён.
 *
 * Например, если минимальная роль "member":
 *   - member, moderator, admin — имеют доступ
 *   - user, guest, external — не имеют доступа
 */

// Подключаем AppContext для интеграции с глобальным контекстом
require_once __DIR__ . '/../backend/utils/AppContext.php';

/**
 * Список всех ролей по возрастанию прав
 * Соответствует таблице ref_roles в БД
 */
class Roles {
    const EXTERNAL = 'external';     // Внешний пользователь, не в чате (ID: 1)
    const GUEST = 'guest';           // Гость, только что добавился в чат (ID: 2)
    const USER = 'user';             // Завершил базовую регистрацию (ID: 3)
    const MEMBER = 'member';         // Участник клуба (ID: 4)
    const MODERATOR = 'moderator';   // Модератор (ID: 5)
    const ADMIN = 'admin';           // Администратор (ID: 6)
    
    /**
     * Маппинг строковых кодов на числовые ID из БД
     */
    const ROLE_IDS = [
        self::EXTERNAL => 1,
        self::GUEST => 2,
        self::USER => 3,
        self::MEMBER => 4,
        self::MODERATOR => 5,
        self::ADMIN => 6
    ];
    
    /**
     * Маппинг числовых ID на строковые коды
     */
    const ID_ROLES = [
        1 => self::EXTERNAL,
        2 => self::GUEST,
        3 => self::USER,
        4 => self::MEMBER,
        5 => self::MODERATOR,
        6 => self::ADMIN
    ];

    /**
     * Получить массив всех ролей в порядке возрастания прав
     */
    public static function getAll() {
        return [
            self::EXTERNAL,
            self::GUEST,
            self::USER,
            self::MEMBER,
            self::MODERATOR,
            self::ADMIN
        ];
    }

    /**
     * Получить индекс роли (для сравнения прав)
     */
    public static function getIndex($role) {
        $roles = self::getAll();
        return array_search($role, $roles);
    }

    /**
     * Проверить, имеет ли пользователь доступ к функции
     */
    public static function hasAccess($userRole, $requiredRole) {
        $userIndex = self::getIndex($userRole);
        $requiredIndex = self::getIndex($requiredRole);
        
        if ($userIndex === false || $requiredIndex === false) {
            return false;
        }
        
        return $userIndex >= $requiredIndex;
    }
    
    /**
     * Проверить доступ по числовым ID ролей (для работы с БД)
     */
    public static function hasAccessById($userRoleId, $requiredRoleId) {
        return $userRoleId >= $requiredRoleId;
    }
    
    /**
     * Получить строковый код роли по числовому ID
     * Переименовано для ясности: ранее называлось getRoleByCode($roleId)
     */
    public static function getRoleById($roleId) {
        return self::ID_ROLES[$roleId] ?? self::GUEST;
    }
    
    /**
     * Получить числовой ID роли по строковому коду
     */
    public static function getRoleId($roleCode) {
        return self::ROLE_IDS[$roleCode] ?? 2; // По умолчанию guest
    }
}

/**
 * Привязка функций/эндпоинтов к минимальной роли доступа
 */
class FunctionRoles {
    // users
    const USER_ROLE_SET = 'moderator';
    
    // API endpoints - Users
    // Списки людей — только member+. Гость чата (guest) и «user» списки не видят.
    const API_USERS_GET_LIST = 'member';
    const API_USERS_GET_BY_ID = 'member';
    const API_USERS_CREATE = 'admin';
    const API_USERS_GET_PROFILE = 'external';
    // Обновление собственного профиля
    const API_USERS_UPDATE_SELF = 'guest';
    // Правка чужой карточки: модератор и админ
    const API_USERS_UPDATE_OTHER = 'moderator';
    
    // API endpoints - Cars
    // Список и карточка авто: роль user и выше (гость чата без анкеты — нет). Хозяин в карточке — member.
    const API_CARS_GET_LIST = 'user';
    const API_CARS_GET_BY_ID = 'user';
    const API_CARS_CREATE = 'guest';
    // Включение владельца в ответе по авто (приватность)
    const API_CARS_INCLUDE_OWNER = 'member';
    // Обновление авто
    const API_CARS_UPDATE_SELF = 'guest';
    const API_CARS_UPDATE_BY_ID = 'moderator';
    
    // API endpoints - Events
    // События: смотреть список и карточку и ответить «еду» — user (чтобы приехать познакомиться).
    // Создать / править — member (участник).
    const API_EVENTS_GET_LIST = 'user';
    const API_EVENTS_GET_BY_ID = 'user';
    const API_EVENTS_CREATE = 'member';
    const API_EVENTS_UPDATE = 'member';
    const API_EVENTS_DELETE = 'member';
    const API_EVENTS_RSVP = 'user';
    
    // API endpoints - Guide Objects
    const API_GUIDE_OBJECTS_GET_LIST = 'member';
    const API_GUIDE_OBJECTS_GET_BY_ID = 'member';
    const API_GUIDE_OBJECTS_CREATE = 'member';
    const API_GUIDE_OBJECTS_UPDATE = 'member';
    const API_GUIDE_OBJECTS_DELETE = 'moderator';
    
    // API endpoints - Business Cards
    const API_BUSINESS_CARDS_GET_LIST = 'member';
    const API_BUSINESS_CARDS_CREATE = 'member';
    
    // API endpoints - Photos
    const API_PHOTOS_GET_LIST = 'member';
    const API_PHOTOS_UPLOAD = 'user';

    // API endpoints - Reference Data
    const API_REF_CAR_BRANDS_GET_LIST = 'guest';
    
    // API endpoints - Reviews
    const API_REVIEWS_GET_LIST = 'member';
    const API_REVIEWS_CREATE = 'member';
    const API_REVIEWS_UPDATE = 'member'; // свой отзыв: правка оценок и текста
    const API_REVIEWS_DELETE = 'member'; // свой отзыв: удаление
    
    // API endpoints - System
    const API_HEALTH = 'external';
    const API_STATUS = 'external';
    // Цифры на главной: гости уже в приложении, полные списки им не нужны
    const API_STATS_DASHBOARD = 'guest';
    
    // API endpoints - L3 Actions (с OCR)
    const API_ACTIONS_CHECK_CAR_IN_CLUB = 'user';        // минимальная роль: user
    const API_ACTIONS_LEAVE_BUSINESS_CARD = 'member';    // минимальная роль: member
    const API_ACTIONS_ADD_CAR_TO_GARAGE = 'guest';       // минимальная роль: guest

    // API endpoints - User Locations (Map)
    const API_USER_LOCATIONS_INDEX = 'member';
    const API_USER_LOCATIONS_STORE = 'user';
    const API_USER_LOCATIONS_DESTROY = 'user';

    // Журнал: вход и разделы может писать любой, кто открыл Mini App
    const API_AUDIT_WRITE = 'external';

    // Проверка членства доступна даже роли external, чтобы можно было вернуться в клуб
    const API_MEMBERSHIP_CHECK = 'external';

    /**
     * Получить массив всех функций с их минимальными ролями
     */
    public static function getAll() {
        return [
            // users
            'userRoleSet' => self::USER_ROLE_SET,
            
            // API endpoints - Users
            'api.users.getList' => self::API_USERS_GET_LIST,
            'api.users.getById' => self::API_USERS_GET_BY_ID,
            'api.users.create' => self::API_USERS_CREATE,
            'api.users.getProfile' => self::API_USERS_GET_PROFILE,
            'api.users.updateSelf' => self::API_USERS_UPDATE_SELF,
            'api.users.updateOther' => self::API_USERS_UPDATE_OTHER,
            
            // API endpoints - Cars
            'api.cars.getList' => self::API_CARS_GET_LIST,
            'api.cars.getById' => self::API_CARS_GET_BY_ID,
            'api.cars.create' => self::API_CARS_CREATE,
            'api.cars.includeOwner' => self::API_CARS_INCLUDE_OWNER,
            'api.cars.updateSelf' => self::API_CARS_UPDATE_SELF,
            'api.cars.updateById' => self::API_CARS_UPDATE_BY_ID,
            
            // API endpoints - Events
            'api.events.getList' => self::API_EVENTS_GET_LIST,
            'api.events.getById' => self::API_EVENTS_GET_BY_ID,
            'api.events.create' => self::API_EVENTS_CREATE,
            'api.events.update' => self::API_EVENTS_UPDATE,
            'api.events.delete' => self::API_EVENTS_DELETE,
            'api.events.rsvp' => self::API_EVENTS_RSVP,
            
            // API endpoints - Guide Objects
            'api.guide-objects.getList' => self::API_GUIDE_OBJECTS_GET_LIST,
            'api.guide-objects.getById' => self::API_GUIDE_OBJECTS_GET_BY_ID,
            'api.guide-objects.create' => self::API_GUIDE_OBJECTS_CREATE,
            'api.guide-objects.update' => self::API_GUIDE_OBJECTS_UPDATE,
            'api.guide-objects.delete' => self::API_GUIDE_OBJECTS_DELETE,
            
            // API endpoints - Business Cards
            'api.businessCards.getList' => self::API_BUSINESS_CARDS_GET_LIST,
            'api.businessCards.create' => self::API_BUSINESS_CARDS_CREATE,
            
            // API endpoints - Photos
            'api.photos.getList' => self::API_PHOTOS_GET_LIST,
            'api.photos.upload' => self::API_PHOTOS_UPLOAD,

            // API endpoints - Reference Data
            'api.ref.getCarBrands' => self::API_REF_CAR_BRANDS_GET_LIST,
            'api.ref.getEventTypes' => self::API_REF_CAR_BRANDS_GET_LIST,
            'api.ref.getGuideObjectTypes' => self::API_REF_CAR_BRANDS_GET_LIST,
            'api.ref.getGuideObjectKinds' => self::API_REF_CAR_BRANDS_GET_LIST,
            'api.ref.getLabels' => self::API_REF_CAR_BRANDS_GET_LIST,
            
            // API endpoints - Reviews
            'api.reviews.getList' => self::API_REVIEWS_GET_LIST,
            'api.reviews.create' => self::API_REVIEWS_CREATE,
            'api.reviews.update' => self::API_REVIEWS_UPDATE,
            'api.reviews.delete' => self::API_REVIEWS_DELETE,
            
            // API endpoints - System
            'api.health' => self::API_HEALTH,
            'api.status' => self::API_STATUS,
            'api.stats.dashboard' => self::API_STATS_DASHBOARD,
            
            // API endpoints - L3 Actions (с OCR)
            'api.actions.checkCarInClub' => self::API_ACTIONS_CHECK_CAR_IN_CLUB,
            'api.actions.leaveBusinessCard' => self::API_ACTIONS_LEAVE_BUSINESS_CARD,
            'api.actions.addCarToGarage' => self::API_ACTIONS_ADD_CAR_TO_GARAGE,

            // API endpoints - User Locations (Map)
            'api.userLocations.index' => self::API_USER_LOCATIONS_INDEX,
            'api.userLocations.store' => self::API_USER_LOCATIONS_STORE,
            'api.userLocations.destroy' => self::API_USER_LOCATIONS_DESTROY,

            'api.audit.write' => self::API_AUDIT_WRITE,
            'api.membership.check' => self::API_MEMBERSHIP_CHECK,
        ];
    }

    /**
     * Получить минимальную роль для функции
     */
    public static function getRequiredRole($function) {
        $functions = self::getAll();
        return $functions[$function] ?? null;
    }

    /**
     * Проверить доступ пользователя к функции
     */
    public static function checkAccess($userRole, $function) {
        $requiredRole = self::getRequiredRole($function);
        if (!$requiredRole) {
            return false;
        }
        
        return Roles::hasAccess($userRole, $requiredRole);
    }
}

/**
 * Утилиты для работы с ролями и правами доступа
 */
class AccessUtils {
    /**
     * Получить роль пользователя из Telegram данных
     * (упрощённая версия, в реальности нужно проверять через API)
     */
    public static function getUserRole($telegramUser) {
        // По умолчанию - гость
        $role = Roles::GUEST;
        
        // Если пользователь в группе - проверяем статус
        if (isset($telegramUser['id'])) {
            // TODO: Здесь должна быть проверка через API backend
            // Пока возвращаем базовую роль
            $role = Roles::GUEST;
        }
        
        return $role;
    }

    /**
     * Проверить, может ли пользователь выполнить команду бота
     */
    public static function canExecuteBotCommand($telegramUser, $command) {
        $userRole = self::getUserRole($telegramUser);
        return FunctionRoles::checkAccess($userRole, $command);
    }
    
    /**
     * Проверить доступ к API эндпоинту через AppContext
     */
    public static function checkApiAccess($function) {
        // Получаем пользователя из глобального контекста
        $user = AppContext::getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Если роль развернута (объект), берём код роли
        if (isset($user['role']) && is_array($user['role']) && isset($user['role']['code'])) {
            $userRoleCode = $user['role']['code'];
        }
        // Если роль строкой (например, 'moderator') — используем её напрямую
        elseif (isset($user['role']) && is_string($user['role'])) {
            $userRoleCode = $user['role'];
        }
        // Если есть числовой role_id — конвертируем в код
        elseif (isset($user['role_id'])) {
            // Если роль не развернута (число), конвертируем в код
            $userRoleId = (int)$user['role_id'];
            $userRoleCode = Roles::getRoleById($userRoleId);
        } else {
            // По умолчанию - гость
            $userRoleCode = 'guest';
        }
        
        return FunctionRoles::checkAccess($userRoleCode, $function);
    }
    
    /**
     * Получить минимальную роль для API эндпоинта
     */
    public static function getRequiredRoleForApi($function) {
        return FunctionRoles::getRequiredRole($function);
    }
    
    /**
     * Проверить доступ по числовым ID ролей (для работы с БД)
     */
    public static function checkAccessById($userRoleId, $function) {
        $requiredRole = FunctionRoles::getRequiredRole($function);
        if (!$requiredRole) {
            return false;
        }
        
        $requiredRoleId = Roles::getRoleId($requiredRole);
        return Roles::hasAccessById($userRoleId, $requiredRoleId);
    }
} 