<?php
/**
 * Проверяет, состоит ли текущий человек в клубном Telegram-чате.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../utils/TelegramMembership.php';
require_once __DIR__ . '/../models/User.php';

class MembershipController extends BaseController
{
    public function check(): void
    {
        if (!$this->requireAccess('api.membership.check')) {
            return;
        }

        $user = $this->getCurrentUser();
        $telegramId = (int)($user['telegram_id'] ?? 0);
        if ($telegramId <= 0) {
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_TELEGRAM_ID',
                    'message' => 'Не удалось определить пользователя Telegram',
                ],
            ], 400);
            return;
        }

        $force = isset($_GET['force']) && (string)$_GET['force'] === '1';
        $result = TelegramMembership::check($telegramId, $force);
        if (empty($result['success'])) {
            Logger::warning('Membership check unavailable', [
                'telegram_id' => $telegramId,
                'error' => $result['error'] ?? 'unknown',
            ]);
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBERSHIP_CHECK_UNAVAILABLE',
                    'message' => 'Не удалось проверить участие в чате. Попробуйте ещё раз.',
                ],
            ], 503);
            return;
        }

        $currentRole = $this->getCurrentUserRole();
        if (is_numeric($currentRole)) {
            $currentRole = Roles::getRoleById((int)$currentRole);
        }

        $isStaff = ($currentRole === Roles::MODERATOR || $currentRole === Roles::ADMIN);
        // Модератора и администратора проверкой чата не понижаем — иначе сбой Telegram снимает админку.
        if (!$isStaff) {
            if (empty($result['is_member']) && $currentRole !== Roles::EXTERNAL) {
                User::updateRole((int)$user['id'], Roles::getRoleId(Roles::EXTERNAL));
            } elseif (!empty($result['is_member']) && $currentRole === Roles::EXTERNAL) {
                User::updateRole((int)$user['id'], Roles::getRoleId(Roles::GUEST));
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'is_member' => (bool)$result['is_member'],
                'telegram_status' => (string)$result['status'],
            ],
        ]);
    }
}
