<?php
/**
 * 🛣️ API Router — точка входа для всех API-запросов CabrioRide
 * 
 * Назначение: Маршрутизация запросов с интеграцией AuthMiddleware
 * Использование: Все API запросы проходят через этот файл
 */

// Загрузка переменных окружения
require_once __DIR__ . '/../utils/load_env.php';
require_once __DIR__ . '/../utils/ResponseHelper.php';
require_once __DIR__ . '/../utils/AuthHelper.php';
require_once __DIR__ . '/../utils/AppContext.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';

try {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    $route = $_GET['route'] ?? $uri; // если параметр не передан, берём путь URI

    // Убираем префикс /app если запрос идёт как /app/api/...
    if (str_starts_with($route, '/app/')) {
        $route = substr($route, 4); // '/app' -> удаляем 4 символа, остаётся '/api/...'
    }
    
    // Логируем для отладки
    Logger::info('API Router: Request received', [
        'uri' => $uri,
        'method' => $method,
        'route' => $route ?? 'null',
        'headers' => getallheaders(),
        'query_params' => $_GET,
        'post_data' => $_POST
    ]);

    // Сначала проверяем, существует ли маршрут
    $routeExists = false;
    
    // Проверяем существующие маршруты
    $existingRoutes = [
        '/api/users' => ['GET', 'POST'],
        '/api/users/profile' => ['GET', 'POST'],
        '/api/users/check-by-telegram' => ['POST'],
        '/api/users/find-by-telegram' => ['POST'],
        '/api/cars' => ['GET', 'POST'],
        // PATCH по id
        // динамический маршрут проверится ниже
        '/api/events' => ['GET', 'POST'],
        '/api/guide-objects' => ['GET', 'POST'],
        '/api/business-cards' => ['GET', 'POST'],
        '/api/photos' => ['GET', 'POST'],
        '/api/ref/car-brands' => ['GET'],
        '/api/ref/event-types' => ['GET'],
        '/api/ref/guide-object-types' => ['GET'],
        '/api/ref/guide-object-kinds' => ['GET'],
        '/api/ref/labels' => ['GET'],
        '/api/reviews' => ['GET', 'POST'],
        '/api/health' => ['GET'],
        '/api/status' => ['GET'],
        '/api/stats' => ['GET'],
        '/api/system/user-sync' => ['POST'],
        '/api/system/user-role' => ['POST'],
        '/api/system/entity-status' => ['POST'],
        '/api/actions/check-car-in-club' => ['POST'],
        '/api/actions/leave-business-card' => ['POST'],
        '/api/actions/add-car-to-garage' => ['POST'],
        // User locations (map): смотреть / слать / скрыть себя
        '/api/user-locations' => ['GET', 'POST', 'DELETE'],
        '/api/audit/client' => ['POST'],
        '/api/membership/check' => ['GET'],
    ];
    
    // Проверяем точное совпадение
    if (isset($existingRoutes[$route]) && in_array($method, $existingRoutes[$route])) {
        $routeExists = true;
    }
    
    // Проверяем динамические маршруты (например, /api/cars/{id})
    if (!$routeExists && preg_match('/^\/api\/cars\/\d+$/', $route) && ($method === 'GET' || $method === 'PATCH')) {
        $routeExists = true;
    }
    if (!$routeExists && preg_match('/^\/api\/users\/\d+$/', $route) && $method === 'GET') {
        $routeExists = true;
    }
    if (!$routeExists && preg_match('/^\/api\/events\/\d+$/', $route) && ($method === 'GET' || $method === 'PATCH' || $method === 'DELETE')) {
        $routeExists = true;
    }
    if (!$routeExists && preg_match('/^\/api\/events\/\d+\/rsvp$/', $route) && $method === 'POST') {
        $routeExists = true;
    }
    if (!$routeExists && preg_match('/^\/api\/guide-objects\/\d+$/', $route) && ($method === 'GET' || $method === 'PATCH' || $method === 'DELETE')) {
        $routeExists = true;
    }
    if (!$routeExists && preg_match('/^\/api\/reviews\/\d+$/', $route) && ($method === 'PATCH' || $method === 'DELETE')) {
        $routeExists = true;
    }
    // Динамический маршрут для смены роли пользователя: /api/users/{id}/role
    if (!$routeExists && preg_match('/^\/api\/users\/(\d+)\/role$/', $route) && $method === 'POST') {
        $routeExists = true;
    }
    
    // Если маршрут не существует, возвращаем 404
    if (!$routeExists) {
        Logger::warning('API Router: Route not found', [
            'route' => $route,
            'method' => $method
        ]);
        
        http_response_code(404);
        echo ResponseHelper::error('NOT_FOUND', 'Маршрут не найден');
        return;
    }
    // Смена роли пользователя (модератор+)
    elseif (preg_match('/^\/api\/users\/(\d+)\/role$/', $route, $matches) && $method === 'POST') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->updateRole((int)$matches[1]);
    }
    
    // Здоровье API остаётся полностью публичным
    if (!($route === '/api/health' && $method === 'GET')) {
        Logger::info('API Router: About to authenticate', [ 'route' => $route, 'method' => $method ]);
        $authResult = AuthMiddleware::authenticate($route, $method);
        Logger::info('API Router: Auth result', [ 'success' => $authResult['success'] ?? null, 'error' => $authResult['error']['code'] ?? null ]);
        if (!$authResult['success']) {
            Logger::warning('API Router: Authentication failed', [
                'route' => $route,
                'error' => $authResult['error']['message'] ?? 'unknown'
            ]);
            http_response_code($authResult['error']['code'] === 'INVALID_HASH' ? 401 : 403);
            echo ResponseHelper::error('AUTH_ERROR', $authResult['error']['message'] ?? 'Auth error');
            exit;
        }
        // Диагностика текущего пользователя после авторизации
        $currentUser = AppContext::getCurrentUser();
        Logger::info('API Router: Authenticated user context', [
            'user_id' => $currentUser['id'] ?? null,
            'role' => ($currentUser['role']['code'] ?? ($currentUser['role'] ?? null))
        ]);
    }

    // Маршрутизация запросов
    if ($route === '/api/users' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->getList();
    } elseif ($route === '/api/users' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->create();
    }
    // Маршруты для автомобилей
    elseif ($route === '/api/cars' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->getList();
    } elseif ($route === '/api/cars' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->create();
    } elseif (preg_match('/^\/api\/cars\/(\d+)$/', $route, $matches) && $method === 'GET') {
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->getById($matches[1]);
    } elseif (preg_match('/^\/api\/cars\/(\d+)$/', $route, $matches) && $method === 'PATCH') {
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->update($matches[1]);
    }
    // Справочники
    elseif ($route === '/api/ref/car-brands' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/RefController.php';
        (new RefController())->getCarBrands();
    } elseif ($route === '/api/ref/event-types' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/RefController.php';
        (new RefController())->getEventTypes();
    } elseif ($route === '/api/ref/guide-object-types' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/RefController.php';
        (new RefController())->getGuideObjectTypes();
    } elseif ($route === '/api/ref/guide-object-kinds' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/RefController.php';
        (new RefController())->getGuideObjectKinds();
    } elseif ($route === '/api/ref/labels' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/RefController.php';
        (new RefController())->getLabels();
    }
    // Маршруты для координат пользователей (карта)
    elseif ($route === '/api/user-locations' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/UserLocationController.php';
        (new UserLocationController())->index();
    } elseif ($route === '/api/user-locations' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/UserLocationController.php';
        (new UserLocationController())->store();
    } elseif ($route === '/api/user-locations' && $method === 'DELETE') {
        require_once __DIR__ . '/../controllers/UserLocationController.php';
        (new UserLocationController())->destroy();
    }
    // Маршруты для событий
    elseif ($route === '/api/events' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->getList();
    } elseif ($route === '/api/events' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->create();
    } elseif (preg_match('/^\/api\/events\/(\d+)\/rsvp$/', $route, $matches) && $method === 'POST') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->rsvp((int)$matches[1]);
    } elseif (preg_match('/^\/api\/events\/(\d+)$/', $route, $matches) && $method === 'GET') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->getById((int)$matches[1]);
    } elseif (preg_match('/^\/api\/events\/(\d+)$/', $route, $matches) && $method === 'PATCH') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->update((int)$matches[1]);
    } elseif (preg_match('/^\/api\/events\/(\d+)$/', $route, $matches) && $method === 'DELETE') {
        require_once __DIR__ . '/../controllers/EventController.php';
        (new EventController())->delete((int)$matches[1]);
    }
    // Маршруты для гид-объектов
    elseif ($route === '/api/guide-objects' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/GuideObjectController.php';
        (new GuideObjectController())->getList();
    } elseif ($route === '/api/guide-objects' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/GuideObjectController.php';
        (new GuideObjectController())->create();
    } elseif (preg_match('/^\/api\/guide-objects\/(\d+)$/', $route, $matches) && $method === 'GET') {
        require_once __DIR__ . '/../controllers/GuideObjectController.php';
        (new GuideObjectController())->getById((int)$matches[1]);
    } elseif (preg_match('/^\/api\/guide-objects\/(\d+)$/', $route, $matches) && $method === 'PATCH') {
        require_once __DIR__ . '/../controllers/GuideObjectController.php';
        (new GuideObjectController())->update((int)$matches[1]);
    } elseif (preg_match('/^\/api\/guide-objects\/(\d+)$/', $route, $matches) && $method === 'DELETE') {
        require_once __DIR__ . '/../controllers/GuideObjectController.php';
        (new GuideObjectController())->delete((int)$matches[1]);
    }
    // Маршруты для визиток
    elseif ($route === '/api/business-cards' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/BusinessCardController.php';
        (new BusinessCardController())->getList();
    } elseif ($route === '/api/business-cards' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/BusinessCardController.php';
        (new BusinessCardController())->create();
    }
    // Маршруты для фото
    elseif ($route === '/api/photos' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/PhotoController.php';
        (new PhotoController())->getList();
    } elseif ($route === '/api/photos' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/PhotoController.php';
        (new PhotoController())->upload();
    }
    // Маршруты для отзывов
    elseif ($route === '/api/reviews' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/ReviewController.php';
        (new ReviewController())->getList();
    } elseif ($route === '/api/reviews' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/ReviewController.php';
        (new ReviewController())->create();
    } elseif (preg_match('/^\/api\/reviews\/(\d+)$/', $route, $matches) && $method === 'PATCH') {
        require_once __DIR__ . '/../controllers/ReviewController.php';
        (new ReviewController())->update((int)$matches[1]);
    } elseif (preg_match('/^\/api\/reviews\/(\d+)$/', $route, $matches) && $method === 'DELETE') {
        require_once __DIR__ . '/../controllers/ReviewController.php';
        (new ReviewController())->delete((int)$matches[1]);
    }
    // Публичные маршруты для проверки состояния
    elseif ($route === '/api/health' && $method === 'GET') {
        echo ResponseHelper::success(['status' => 'ok', 'message' => 'API is healthy']);
    } elseif ($route === '/api/status' && $method === 'GET') {
        echo ResponseHelper::success(['status' => 'online', 'version' => '1.0.0']);
    } elseif ($route === '/api/stats' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/StatsController.php';
        (new StatsController())->dashboard();
    } elseif ($route === '/api/audit/client' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/AuditController.php';
        (new AuditController())->client();
    } elseif ($route === '/api/membership/check' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/MembershipController.php';
        (new MembershipController())->check();
    }
    // Маршрут для профиля пользователя
    elseif ($route === '/api/users/profile' && $method === 'GET') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->getProfile();
    }     elseif ($route === '/api/users/profile' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->updateProfile();
    } elseif (preg_match('/^\/api\/users\/(\d+)$/', $route, $matches) && $method === 'GET') {
        require_once __DIR__ . '/../controllers/UserController.php';
        (new UserController())->getById((int)$matches[1]);
    }
    // Системные маршруты (требуют SYSTEM_TOKEN)
    elseif ($route === '/api/system/user-sync' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/SystemController.php';
        (new SystemController())->userSync();
    } elseif ($route === '/api/system/user-role' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/SystemController.php';
        (new SystemController())->userRole();
    } elseif ($route === '/api/system/entity-status' && $method === 'POST') {
        require_once __DIR__ . '/../controllers/SystemController.php';
        (new SystemController())->entityStatus();
    }
    // Маршруты для L3 Actions (с OCR)
    elseif ($route === '/api/actions/check-car-in-club' && $method === 'POST') {
        Logger::info('API Router: Routing to checkCarInClub');
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->checkCarInClub();
    } elseif ($route === '/api/actions/leave-business-card' && $method === 'POST') {
        Logger::info('API Router: Routing to leaveBusinessCard');
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->leaveBusinessCard();
    } elseif ($route === '/api/actions/add-car-to-garage' && $method === 'POST') {
        Logger::info('API Router: Routing to addCarToGarage');
        require_once __DIR__ . '/../controllers/CarController.php';
        (new CarController())->addCarToGarage();
    }
    // ...добавляйте остальные маршруты по аналогии
} catch (Throwable $e) {
    Logger::error('API Router: Unexpected error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo ResponseHelper::error('INTERNAL_ERROR', $e->getMessage());
} finally {
    // Очищаем глобальный контекст в конце запроса
    if (class_exists('AppContext')) {
        AppContext::clear();
    }
} 