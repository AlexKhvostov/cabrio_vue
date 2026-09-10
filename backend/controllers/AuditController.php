<?php
/**
 * Клиентские события журнала: вход в Mini App и открытие раздела.
 * Создание/правка/удаление пишутся с сервера в контроллерах.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../utils/AppAudit.php';

class AuditController extends BaseController
{
    public function client()
    {
        try {
            if (!$this->requireAccess('api.audit.write')) {
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $kind = strtolower(trim((string)($input['kind'] ?? '')));
            $section = strtolower(trim((string)($input['section'] ?? '')));
            $allowedSection = ['home', 'users', 'cars', 'map', 'events', 'guide', 'me'];
            if ($section !== '' && !in_array($section, $allowedSection, true)) {
                $section = '';
            }
            if ($kind === 'login') {
                AppAudit::write([
                    'action' => 'login',
                    'entity_type' => 'app',
                    'section' => $section ?: 'home',
                    'summary' => 'Открыл приложение',
                ], 30);
            } elseif ($kind === 'view') {
                if ($section === '') {
                    $this->json(['success' => true, 'data' => ['ok' => true]]);
                    return;
                }
                AppAudit::write([
                    'action' => 'view',
                    'entity_type' => 'page',
                    'section' => $section,
                    'summary' => 'Открыл раздел: ' . AppAudit::sectionName($section),
                ], 10);
            }
            $this->json(['success' => true, 'data' => ['ok' => true]]);
        } catch (Throwable $e) {
            $this->json(['success' => true, 'data' => ['ok' => false]]);
        }
    }
}
