<?php
/**
 * ReviewController — контроллер для работы с отзывами (reviews).
 *
 * Назначение:
 *   Обрабатывает HTTP-запросы, связанные с отзывами: получение, создание, обновление, удаление, модерация и т.д.
 *
 * Зависимости:
 *   - Review (модель)
 *   - GuideObject (модель)
 *   - User (модель)
 *   - Status (модель)
 *   - AuthHelper, ResponseHelper
 *
 * Основные методы:
 *   - getList() — получить список отзывов
 *   - getById($id) — получить отзыв по id
 *   - create($data) — добавить отзыв
 *   - update($id, $data) — обновить отзыв
 *   - delete($id) — удалить отзыв
 *   - approve($id) — одобрить отзыв (модерация)
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Review.php';

class ReviewController extends BaseController
{
    /**
     * Получить список отзывов
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function getList()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.reviews.getList')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            $reviews = Review::getAll();
            $this->logUserAction('get_reviews_list', ['count' => count($reviews)]);
            $this->json(['success' => true, 'data' => $reviews, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('ReviewController: getList error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Создать новый отзыв
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function create()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.reviews.create')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $guideId = (int)($input['guide_object_id'] ?? 0);
            $feedback = trim((string)($input['feedback'] ?? ''));
            if (!$guideId || $feedback === '') {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Нужны место и текст отзыва']], 400);
                return;
            }
            $id = Review::create([
                'guide_object_id' => $guideId,
                'quality_rating' => $input['quality_rating'] ?? 3,
                'speed_rating' => $input['speed_rating'] ?? 3,
                'price_rating' => $input['price_rating'] ?? 3,
                'feedback' => $feedback,
                'author_user_id' => (int)$this->getCurrentUserId(),
            ]);
            $this->logUserAction('create_review', ['review_id' => $id, 'guide_object_id' => $guideId]);
            require_once __DIR__ . '/../models/GuideObject.php';
            $place = GuideObject::findExpanded($guideId);
            $placeName = (string)($place['name'] ?? '');
            $this->audit('create', 'review', $id, 'Написал отзыв о «' . mb_substr($placeName !== '' ? $placeName : ('место ' . $guideId), 0, 80) . '»', 'guide');
            $this->json(['success' => true, 'data' => $place, 'meta' => $this->getRequestInfo()], 201);
        } catch (Throwable $e) {
            Logger::error('ReviewController: create error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Изменить свой отзыв (оценки и текст).
     */
    public function update($id)
    {
        try {
            if (!$this->requireAccess('api.reviews.update')) {
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $feedback = trim((string)($input['feedback'] ?? ''));
            if ($feedback === '') {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Напишите текст отзыва']], 400);
                return;
            }
            $staff = $this->isModerator() || $this->isAdmin();
            $guideId = Review::updateByAuthor((int)$id, (int)$this->getCurrentUserId(), $input, $staff);
            if (!$guideId) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Чужой отзыв может править только модератор']], 403);
                return;
            }
            $this->logUserAction('update_review', ['review_id' => (int)$id, 'guide_object_id' => $guideId]);
            $this->audit('update', 'review', (int)$id, 'Изменил отзыв (место id ' . (int)$guideId . ')', 'guide');
            require_once __DIR__ . '/../models/GuideObject.php';
            $place = GuideObject::findExpanded($guideId);
            $this->json(['success' => true, 'data' => $place, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('ReviewController: update error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Удалить свой отзыв.
     */
    public function delete($id)
    {
        try {
            if (!$this->requireAccess('api.reviews.delete')) {
                return;
            }
            $staff = $this->isModerator() || $this->isAdmin();
            $guideId = Review::deleteByAuthor((int)$id, (int)$this->getCurrentUserId(), $staff);
            if (!$guideId) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Чужой отзыв может удалить только модератор']], 403);
                return;
            }
            $this->logUserAction('delete_review', ['review_id' => (int)$id, 'guide_object_id' => $guideId]);
            $this->audit('delete', 'review', (int)$id, 'Удалил отзыв (место id ' . (int)$guideId . ')', 'guide');
            require_once __DIR__ . '/../models/GuideObject.php';
            $place = GuideObject::findExpanded($guideId);
            $this->json(['success' => true, 'data' => $place, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('ReviewController: delete error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }
} 