<?php
/**
 * StatsController — короткие цифры для главной страницы.
 *
 * Зачем отдельный метод: раньше главная скачивала все списки пользователей,
 * машин и событий только чтобы показать три числа. Так нельзя при росте клуба.
 *
     * Ответ: { success, data: { users, cars_active, events, reviews, cities, on_map } }
     * users — роли user и выше, без гостей чата и внешних.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Car.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/GuideObject.php';
require_once __DIR__ . '/../models/UserLocation.php';

class StatsController extends BaseController
{
    /**
     * GET /api/stats
     * Минимальная роль: guest (кто уже открыл приложение в Telegram)
     */
    public function dashboard()
    {
        try {
            if (!$this->requireAccess('api.stats.dashboard')) {
                return;
            }

            $liveMin = (int)(getenv('MAP_LIVE_TIME_MIN') ?: getenv('map_live_time_min') ?: 60);
            $cities = 0;
            $onMap = 0;
            try { $cities = User::countCities(); } catch (Throwable $e) {}
            try { $onMap = UserLocation::countLive($liveMin ?: 60); } catch (Throwable $e) {}
            $reviews = 0;
            try { $reviews = GuideObject::countListed(); } catch (Throwable $e) {}
            $this->json([
                'success' => true,
                'data' => [
                    'users' => User::countRegistered(),
                    'cars_active' => Car::countActive(),
                    'events' => Event::countAll(),
                    'reviews' => $reviews,
                    'cities' => $cities,
                    'on_map' => $onMap,
                ],
                'meta' => $this->getRequestInfo()
            ]);
        } catch (Throwable $e) {
            Logger::error('StatsController: dashboard error', [
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
}
