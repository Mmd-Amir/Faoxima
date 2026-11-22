<?php

namespace Faoxima\Telegram\Support;

use CURLFile;
use Throwable;

final class InfoCards
{
    public static function nmInfoCardUsageFields(array $data): array
    {
        $used = (float) ($data['used_traffic'] ?? 0);
        $total = (float) ($data['data_limit'] ?? 0);
        $expire = $data['expire'] ?? 0;
        $unlimitedTime = empty($expire);
        $daysLeft = 0;
        if (!$unlimitedTime) {
            $diff = (int) $expire - time();
            $daysLeft = max(0, (int) floor($diff / 86400));
        }
        return [
            'active' => in_array((string) ($data['status'] ?? 'active'), ['active', 'on_hold'], true),
            'used_bytes' => $used,
            'total_bytes' => $total,
            'days_left' => $daysLeft,
            'unlimited_time' => $unlimitedTime,
        ];
    }

    public static function nmInfoCardConfiguredBotUsername($settings): string
    {
        if (is_array($settings)) {
            foreach (['bot_username', 'username_bot', 'usernamebot', 'BotUsername', 'bot_user'] as $key) {
                if (isset($settings[$key]) && is_string($settings[$key]) && trim($settings[$key]) !== '') {
                    return ltrim($settings[$key], '@');
                }
            }
        }
        return '';
    }

    public static function nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id)
    {
        if (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'createServiceInfoCard')) {
            return null;
        }
        if (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'getInfoCardStatus') || !\Faoxima\Telegram\Support\InfoCardRenderer::getInfoCardStatus()) {
            return null;
        }
        if (is_array($panel_info) && ($panel_info['type'] ?? '') === 'Manualsale') {
            return null;
        }
        if (is_array($panel_info) && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel_info)) {
            return null;
        }
        global $ManagePanel, $setting;
        try {
            if (!isset($ManagePanel) || !is_object($ManagePanel) || !method_exists($ManagePanel, 'DataUser')) {
                return null;
            }
            $name_panel = is_array($panel_info) ? ($panel_info['name_panel'] ?? '') : '';
            if ($name_panel === '') {
                return null;
            }
            $data = @$ManagePanel->DataUser($name_panel, $username_service);
            if (!is_array($data) || (isset($data['status']) && $data['status'] === 'Unsuccessful')) {
                return null;
            }
            $usage = self::nmInfoCardUsageFields($data);
            $botUsername = self::nmInfoCardConfiguredBotUsername($setting ?? null);
            if ($botUsername === '' && function_exists('telegram')) {
                $me = @telegram('getMe', []);
                if (is_array($me) && isset($me['result']['username'])) {
                    $botUsername = (string)$me['result']['username'];
                }
            }

            $color = method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'getInfoCardColor') ? \Faoxima\Telegram\Support\InfoCardRenderer::getInfoCardColor() : 'yellow';
            $params = [
                'config_name'    => (string)$username_service,
                'bot_username'   => $botUsername,
                'user_id'        => (string)$user_id,
            ] + $usage;
            $outPath = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
                . DIRECTORY_SEPARATOR . 'infocard_' . $user_id . '_' . bin2hex(random_bytes(3)) . '.png';
            $written = \Faoxima\Telegram\Support\InfoCardRenderer::createServiceInfoCard($params, $color, $outPath);
            if ($written === false) {
                return null;
            }
            return $written;
        } catch (\Throwable $e) {
            error_log('nm_renderInfoCardForInvoice failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function nm_sendServiceQrFallback($user_id, string $qrPayload, string $backgroundImage = 'images.jpg', string $caption = '', $replyMarkup = null, ?array &$deliveryDetail = null): bool
    {
        $deliveryDetail = ['state' => 'not_attempted', 'reason' => '', 'result' => null];
        if (method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) {
            $deliveryDetail = ['state' => 'skipped', 'reason' => 'qr_disabled', 'result' => null];
            return false;
        }
        if (!method_exists(\Faoxima\Telegram\Support\QrAndKeys::class, 'createqrcode') || !function_exists('telegram')) {
            $deliveryDetail = ['state' => 'failed', 'reason' => 'qr_stack_unavailable', 'result' => null];
            return false;
        }
        $qrPayload = trim($qrPayload);
        if ($qrPayload === '') {
            $deliveryDetail = ['state' => 'failed', 'reason' => 'empty_qr_payload', 'result' => null];
            return false;
        }
        $urlimage = '';
        try {
            $qrCode = \Faoxima\Telegram\Support\QrAndKeys::createqrcode($qrPayload);
            if ($qrCode === null) {
                $deliveryDetail = ['state' => 'failed', 'reason' => 'qr_generation_failed', 'result' => null];
                return false;
            }
            $urlimage = (defined('REFACTORED_LEGACY_ROOT') ? REFACTORED_LEGACY_ROOT : __DIR__)
                . DIRECTORY_SEPARATOR . $user_id . bin2hex(random_bytes(3)) . '.png';
            $written = file_put_contents($urlimage, $qrCode->getString());
            if ($written === false) {
                $deliveryDetail = ['state' => 'failed', 'reason' => 'qr_file_write_failed', 'result' => null];
                return false;
            }
            if (method_exists(\Faoxima\Telegram\Support\QrAndKeys::class, 'addBackgroundImage')) {
                @\Faoxima\Telegram\Support\QrAndKeys::addBackgroundImage($urlimage, $qrCode, $backgroundImage);
            }
            $deliveryDetail['state'] = 'attempted';
            $result = rxSendMediaWithCaption('sendphoto', [
                'chat_id'    => $user_id,
                'photo'      => new CURLFile($urlimage),
                'caption'    => trim($caption) !== '' ? $caption : '📥 کیو‌آر کد',
                'parse_mode' => 'HTML',
                'reply_markup' => $replyMarkup,
            ], null, $mediaDelivery);
            $mediaDelivery = is_array($mediaDelivery) ? $mediaDelivery : [];
            $state = (string) ($mediaDelivery['media_state'] ?? rxMediaResultState($result));
            $deliveryDetail = [
                'state' => $state,
                'reason' => !($mediaDelivery['caption_valid'] ?? true) ? 'caption_unsafe' : ('telegram_' . $state),
                'result' => method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_result') ? \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_result($result) : null,
                'message_id' => is_array($result) ? (int) ($result['result']['message_id'] ?? 0) : 0,
            ] + $mediaDelivery;
            @unlink($urlimage);
            return $state === 'confirmed';
        } catch (Throwable $e) {
            if ($urlimage !== '' && is_file($urlimage)) {
                @unlink($urlimage);
            }
            $attempted = isset($deliveryDetail['state']) && $deliveryDetail['state'] === 'attempted';
            $deliveryDetail = [
                'state' => $attempted ? 'unknown' : 'failed',
                'reason' => 'exception:' . get_class($e),
                'result' => null,
            ];
            error_log('nm_sendServiceQrFallback failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function nm_sendInfoCardsForServiceList($from_id, array $services)
    {
        if (!method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_renderInfoCardForInvoice') || !function_exists('telegram')) {
            return;
        }
        if (empty($services)) {
            return;
        }


        $cap = defined('INFOCARD_LIST_MAX') ? (int) INFOCARD_LIST_MAX : 8;
        $sent = 0;
        $attempted = 0;
        foreach ($services as $row) {
            if ($attempted >= $cap) {
                break;
            }
            if (!is_array($row) || empty($row['username']) || empty($row['id_invoice'])) {
                continue;
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $row['Service_location'] ?? '', "select");
            if (!is_array($panel)) {
                continue;
            }
            $cardPath = self::nm_renderInfoCardForInvoice($panel, $row['username'], $row['id_invoice'], $from_id);
            if ($cardPath === null) {
                if (method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_sendServiceQrFallback')) {
                    global $ManagePanel;
                    $subLink = '';
                    if (isset($ManagePanel) && is_object($ManagePanel) && method_exists($ManagePanel, 'DataUser')) {
                        $data = @$ManagePanel->DataUser($row['Service_location'] ?? '', $row['username']);
                        if (is_array($data)) {
                            $subLink = (string)($data['subscription_url'] ?? '');
                        }
                    }
                    if ($subLink !== '') {
                        self::nm_sendServiceQrFallback($from_id, $subLink);
                    }
                }
                continue;
            }
            $note = isset($row['note']) && $row['note'] !== '' ? ' | ' . $row['note'] : '';
            $caption = '✨ <b>' . htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') . '</b>'
                . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
            $__kbButtons = [['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('service_btn_manage'), 'callback_data' => 'product_' . $row['id_invoice']]];
            if (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') || !\Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) {
                $__kbButtons[] = ['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $row['id_invoice']];
            }
            $kb = json_encode(['inline_keyboard' => [$__kbButtons]], JSON_UNESCAPED_UNICODE);
            $attempted++;
            try {
                rxSendMediaWithCaption('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => new CURLFile($cardPath),
                    'reply_markup' => $kb,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ], null, $cardDelivery, true);
                if ($cardDelivery['delivery_status'] === 'completed') {
                    $sent++;
                }
            } catch (\Throwable $e) {
                error_log('nm_sendInfoCardsForServiceList send failed: ' . $e->getMessage());
            }
            @unlink($cardPath);
        }
    }
}
