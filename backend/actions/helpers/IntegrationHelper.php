<?php
/**
 * 🔧 IntegrationHelper - Вспомогательные функции для внешних интеграций
 * 
 * Назначение: OCR распознавание, API вызовы, внешние сервисы
 * Используется в: L3 Actions для распознавания номеров авто
 */

require_once __DIR__ . '/../../utils/Logger.php';

class IntegrationHelper {
    
    /**
     * 🔍 Распознавание номера автомобиля из фотографии
     * 
     * @param array $photoFile - Данные файла из $_FILES
     * @return string - Распознанный номер автомобиля
     * @throws Exception - Если не удалось распознать номер
     */
    public static function recognizePlateNumber($photoFile) {
        try {
            // Валидация файла
            if (!$photoFile || !isset($photoFile['tmp_name'])) {
                throw new Exception('Файл фотографии не предоставлен');
            }
            
            // Подготовка данных для OCR API
            $imageData = self::prepareImageForOCR($photoFile);
            
            // Вызов OCR API
            $result = self::callOCRAPI($imageData);
            
            // Обработка результата
            $plateNumber = self::extractPlateNumber($result);
            
            Logger::info("Plate number recognized: $plateNumber");
            
            return $plateNumber;
            
        } catch (Exception $e) {
            Logger::error('IntegrationHelper::recognizePlateNumber failed: ' . $e->getMessage());
            throw new Exception('Не удалось распознать номер автомобиля: ' . $e->getMessage());
        }
    }

    /**
     * 🔍 Распознавание номера автомобиля из base64 данных
     * 
     * @param string $base64Data - Base64 кодированное изображение
     * @return string - Распознанный номер автомобиля
     * @throws Exception - Если не удалось распознать номер
     */
    public static function recognizePlateNumberFromBase64($base64Data) {
        try {
            // Валидация base64 данных
            if (empty($base64Data)) {
                throw new Exception('Base64 данные не предоставлены');
            }
            
            // Проверка валидности base64
            if (!base64_decode($base64Data, true)) {
                throw new Exception('Неверный формат base64 данных');
            }
            
            // Вызов OCR API напрямую с base64
            $result = self::callOCRAPIFromBase64($base64Data);
            
            // Обработка результата
            $plateNumber = self::extractPlateNumber($result);
            
            Logger::info("Plate number recognized from base64: $plateNumber");
            
            return $plateNumber;
            
        } catch (Exception $e) {
            Logger::error('IntegrationHelper::recognizePlateNumberFromBase64 failed: ' . $e->getMessage());
            throw new Exception('Не удалось распознать номер автомобиля: ' . $e->getMessage());
        }
    }
    
    /**
     * 🖼️ Подготовка изображения для OCR
     * 
     * @param array $photoFile - Данные файла
     * @return string - Base64 кодированное изображение
     */
    private static function prepareImageForOCR($photoFile) {
        // Проверка размера файла (максимум 3MB для platerecognizer.com)
        if ($photoFile['size'] > 3 * 1024 * 1024) {
            throw new Exception('Размер файла превышает 3MB');
        }
        
        // Проверка типа файла
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($photoFile['type'], $allowedTypes)) {
            throw new Exception('Неподдерживаемый тип файла. Разрешены только JPEG и PNG');
        }
        
        // Чтение файла
        $imageContent = file_get_contents($photoFile['tmp_name']);
        if ($imageContent === false) {
            throw new Exception('Не удалось прочитать файл изображения');
        }
        
        // Конвертация в base64
        $base64Image = base64_encode($imageContent);
        
        return $base64Image;
    }
    
    /**
     * 📡 Вызов OCR API (platerecognizer.com) с временным файлом
     * 
     * @param string $imageData - Base64 изображение
     * @return array - Ответ от OCR API
     */
    private static function callOCRAPI($imageData) {
        // Загрузка переменных окружения
        require_once __DIR__ . '/../../utils/load_env.php';
        
        $apiUrl = 'https://api.platerecognizer.com/v1/plate-reader/';
        $apiToken = $_ENV['OCR_TOKEN'] ?? '';
        
        if (empty($apiToken)) {
            throw new Exception('OCR_TOKEN не найден в переменных окружения');
        }
        
        // Декодируем base64 в бинарные данные
        $imageBinary = base64_decode($imageData);
        
        // Создаем временный файл для cURL
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
        file_put_contents($tempFile, $imageBinary);
        
        // Подготовка данных для API
        $postData = [
            'upload' => new CURLFile($tempFile, 'image/jpeg', 'image.jpg'),
            'regions' => 'by' // Только Беларусь для нашего теста
        ];
        
        // Заголовки для API
        $headers = [
            'Authorization: Token ' . $apiToken
        ];
        
        // Отправка запроса через cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        Logger::info("OCR API request started...");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Удаляем временный файл
        unlink($tempFile);
        
        if ($error) {
            throw new Exception('CURL ошибка: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception('HTTP ошибка: ' . $httpCode . ' - ' . $response);
        }
        
        // Парсинг ответа
        Logger::info("OCR API response: " . substr($response, 0, 500) . "...");
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Неверный ответ от OCR API');
        }
        
        // Проверка на ошибки API
        if (isset($result['error'])) {
            throw new Exception('OCR API ошибка: ' . $result['error']);
        }
        
        // Извлечение номера из результата
        if (isset($result['results']) && !empty($result['results'])) {
            $plate = $result['results'][0]['plate'];
            $confidence = $result['results'][0]['confidence'] ?? 0;
            
            return [
                'success' => true,
                'data' => [
                    'plate_number' => $plate,
                    'confidence' => $confidence
                ]
            ];
        } else {
            throw new Exception('Номер автомобиля не найден на изображении');
        }
    }
    
    /**
     * 📡 Вызов OCR API (platerecognizer.com) напрямую с base64
     * 
     * @param string $base64Data - Base64 изображение
     * @return array - Ответ от OCR API
     */
    private static function callOCRAPIFromBase64($base64Data) {
        // Загрузка переменных окружения
        require_once __DIR__ . '/../../utils/load_env.php';
        
        $apiUrl = 'https://api.platerecognizer.com/v1/plate-reader/';
        $apiToken = $_ENV['OCR_TOKEN'] ?? '';
        
        if (empty($apiToken)) {
            throw new Exception('OCR_TOKEN не найден в переменных окружения');
        }
        
        // Декодируем base64 в бинарные данные
        $imageBinary = base64_decode($base64Data);
        
        // Проверяем размер данных (максимум 3MB для platerecognizer.com)
        if (strlen($imageBinary) > 3 * 1024 * 1024) {
            throw new Exception('Размер изображения превышает 3MB');
        }
        
        // Создаем временный файл для cURL
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
        file_put_contents($tempFile, $imageBinary);
        
        // Подготовка данных для API
        $postData = [
            'upload' => new CURLFile($tempFile, 'image/jpeg', 'image.jpg'),
            'regions' => 'by' // Только Беларусь для нашего теста
        ];
        
        // Заголовки для API
        $headers = [
            'Authorization: Token ' . $apiToken
        ];
        
        // Отправка запроса через cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        Logger::info("OCR API request started...");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Удаляем временный файл
        unlink($tempFile);
        
        if ($error) {
            throw new Exception('CURL ошибка: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception('HTTP ошибка: ' . $httpCode . ' - ' . $response);
        }
        
        // Парсинг ответа
        Logger::info("OCR API response: " . substr($response, 0, 500) . "...");
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Неверный ответ от OCR API');
        }
        
        // Проверка на ошибки API
        if (isset($result['error'])) {
            throw new Exception('OCR API ошибка: ' . $result['error']);
        }
        
        // Извлечение номера из результата
        if (isset($result['results']) && !empty($result['results'])) {
            $plate = $result['results'][0]['plate'];
            $confidence = $result['results'][0]['confidence'] ?? 0;
            
            return [
                'success' => true,
                'data' => [
                    'plate_number' => $plate,
                    'confidence' => $confidence
                ]
            ];
        } else {
            throw new Exception('Номер автомобиля не найден на изображении');
        }
    }
    
    /**
     * 📋 Извлечение номера из ответа OCR
     * 
     * @param array $ocrResult - Результат OCR API
     * @return string - Номер автомобиля
     */
    private static function extractPlateNumber($ocrResult) {
        if (!isset($ocrResult['success']) || !$ocrResult['success']) {
            throw new Exception('OCR API не смог распознать текст');
        }
        
        if (!isset($ocrResult['data']['plate_number'])) {
            throw new Exception('Номер автомобиля не найден в ответе OCR');
        }
        
        return $ocrResult['data']['plate_number'];
    }
    

    
    /**
     * 🔄 Отправка HTTP запроса
     * 
     * @param string $url - URL для запроса
     * @param array $data - Данные для отправки
     * @param string $method - HTTP метод (GET, POST)
     * @param array $headers - Заголовки запроса
     * @return array - Ответ API
     */
    public static function makeHTTPRequest($url, $data = [], $method = 'GET', $headers = []) {
        try {
            $ch = curl_init();
            
            // Настройка CURL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Увеличиваем таймаут до 60 секунд
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Установка метода
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            
            // Установка заголовков
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            
            // Выполнение запроса
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception('CURL ошибка: ' . $error);
            }
            
            if ($httpCode >= 400) {
                throw new Exception('HTTP ошибка: ' . $httpCode);
            }
            
            return [
                'success' => true,
                'data' => $response,
                'http_code' => $httpCode
            ];
            
        } catch (Exception $e) {
            Logger::error('IntegrationHelper::makeHTTPRequest failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 📊 Получение статистики API вызовов
     * 
     * @return array - Статистика
     */
    public static function getAPIStats() {
        // TODO: Реализовать сбор статистики API вызовов
        return [
            'ocr_calls' => 0,
            'successful_calls' => 0,
            'failed_calls' => 0,
            'average_response_time' => 0
        ];
    }
} 