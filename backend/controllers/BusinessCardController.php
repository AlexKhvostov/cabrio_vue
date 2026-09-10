<?php
/**
 * BusinessCardController — контроллер для работы с визитками (business_cards).
 *
 * Назначение:
 *   Обрабатывает HTTP-запросы, связанные с визитками: получение, создание, удаление.
 *
 * Зависимости:
 *   - BusinessCard (модель)
 *   - Car (модель)
 *   - User (модель)
 *   - AuthHelper, ResponseHelper
 *
 * Основные методы:
 *   - getList() — получить список визиток
 *   - getById($id) — получить визитку по id
 *   - create($data) — добавить визитку
 *   - delete($id) — удалить визитку
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/BusinessCard.php';

class BusinessCardController extends BaseController
{
    /**
     * Получить список визиток
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function getList()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.businessCards.getList')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            // Получаем список визиток с развернутыми данными
            $businessCards = BusinessCard::getAll();
            
            $this->logUserAction('get_business_cards_list', ['count' => count($businessCards)]);
            
            $this->json([
                'success' => true, 
                'data' => $businessCards, // Уже содержит развернутые данные из модели
                'meta' => $this->getRequestInfo()
            ]);
            
        } catch (Throwable $e) {
            Logger::error('BusinessCardController: getList error', [
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
     * Создать новую визитку
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function create()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.businessCards.create')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Добавляем ID пользователя
            $input['user_id'] = $this->getCurrentUserId();
            
            $this->logUserAction('create_business_card', ['input_data' => $input]);
            
            // Создаем визитку с развернутыми данными
            $businessCard = BusinessCard::createWithDetails($input);
            
            if (!$businessCard) {
                $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CARD_CREATION_FAILED',
                        'message' => 'Не удалось создать визитку'
                    ]
                ], 400);
                return;
            }
            
            $this->json([
                'success' => true,
                'data' => $businessCard, // Развернутые данные созданной визитки
                'meta' => $this->getRequestInfo()
            ], 201);
            
        } catch (Throwable $e) {
            Logger::error('BusinessCardController: create error', [
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
} 