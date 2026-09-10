<?php
/**
 * ResponseHelper — формирует стандартные ответы API для backend CabrioRide.
 * Используйте для возврата успеха, ошибок, списков и т.д.
 *
 * Пример:
 * echo ResponseHelper::success(['id' => 1, ...]);
 * echo ResponseHelper::error('VALIDATION_ERROR', 'Некорректные данные', ['field' => 'Ошибка']);
 */
class ResponseHelper {
    public static function success($data = null, $pagination = null) {
        $response = [
            'success' => true,
            'data' => $data,
            'error' => null
        ];
        if ($pagination !== null) {
            $response['pagination'] = $pagination;
        }
        header('Content-Type: application/json');
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    public static function error($code, $message, $details = null) {
        $response = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ];
        header('Content-Type: application/json');
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
} 