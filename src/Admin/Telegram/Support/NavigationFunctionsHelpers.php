<?php

namespace Faoxima\Admin\Telegram\Support;

use Throwable;

final class NavigationFunctionsHelpers
{
    public static function rxAdminCanonicalNavigation($text, $adminLabels): array
{
    $backAdmin = $adminLabels['backadmin'] ?? '🏠 بازگشت به منوی مدیریت';
    $backMenu  = $adminLabels['backmenu'] ?? '▶️ بازگشت به منوی قبل';

    $trimmed = trim((string) $text);


    $emojiPattern = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2900}-\x{2BFF}\x{1F100}-\x{1F1FF}\x{1FA00}-\x{1FAFF}\x{FE0E}\x{FE0F}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200C}]/u';
    $stripped = preg_replace($emojiPattern, '', $trimmed);
    $stripped = is_string($stripped) ? trim($stripped) : '';

    $isBackAdmin = ($trimmed === $backAdmin);
    $isBackMenu  = ($trimmed === $backMenu);


    if (!$isBackAdmin && !$isBackMenu && $stripped !== '') {
        $backAdminAliases = [
            'بازگشت به منوی مدیریت',
            'بازگشت به منوی اصلی',
            'بازگشت به منو اصلی',
            'بازگشت به خانه',
            'بازگشت به ادمین',
            'بازگشت به پنل مدیریت',
            'بازگشت به پنل ادمین',
            'منوی مدیریت',
            'منوی اصلی',
            'خانه',
        ];
        $backMenuAliases = [
            'بازگشت به منوی قبل',
            'بازگشت به منو قبل',
            'بازگشت به قبل',
            'منوی قبل',
            'بازگشت',
        ];

        if (in_array($stripped, $backAdminAliases, true)) {
            $isBackAdmin = true;
        } elseif (in_array($stripped, $backMenuAliases, true)) {
            $isBackMenu = true;
        }
    }

    if ($isBackAdmin || $isBackMenu) {
        $text = $isBackAdmin ? $backAdmin : $backMenu;
    }

    return [$text, $isBackMenu];
}

    public static function rxAdminHasNavigationInput($fromId, $adminIds, $text): bool
{
    return $fromId !== null
        && is_array($adminIds)
        && in_array((string) $fromId, array_map('strval', $adminIds), true)
        && is_string($text)
        && $text !== '';
}

    public static function rxAdminResetStepForNavigation($fromId, $text, array &$user): void
{
    if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
        try {
            \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
        } catch (Throwable $error) {
            if (function_exists('rx_log_event')) {
                rx_log_event('NAV_GUARD_STEP_RESET_FAILED', $error->getMessage(), [
                    'from_id' => $fromId,
                    'text'    => $text,
                ]);
            }
        }
    }
    $user['step'] = 'home';
}
}
