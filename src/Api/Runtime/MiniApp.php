<?php

namespace Faoxima\Api\Runtime;

use Faoxima\Api\Handlers\ConfigFileHandler;

final class MiniApp
{
    public static function dispatch(array $payload, callable $authenticate): void
    {
        $actions = self::rx_miniapp_routes();

        $action = \Faoxima\Api\Runtime\Input::string($payload, 'actions');

        if ($action === '' || !isset($actions[$action])) {
            \Faoxima\Api\Runtime\Response::badRequest('Action invalid');
        }

        $user = $authenticate($action);

        [$handlerClass, $handlerMode] = self::rx_miniapp_handler_route($actions[$action]);

        if (!class_exists($handlerClass)) {
            \Faoxima\Api\Runtime\Logger::critical('Handler class missing', ['handler' => $handlerClass]);
            \Faoxima\Api\Runtime\Response::serverError('Handler class not loadable');
        }


        $handler = new $handlerClass($user, $payload);
        if ($handlerMode !== null && property_exists($handler, 'mode')) {
            $handler->mode = $handlerMode;
        }
        $handler->handle();

        self::__miniapp_emit(500, [
            'status' => false,
            'msg'    => 'Handler returned without responding',
            'obj'    => [],
        ]);
    }

    public static function __miniapp_emit(int $http, array $payload): void
    {
        if (class_exists(\Faoxima\Api\Runtime\Response::class)) {
            \Faoxima\Api\Runtime\Response::emitJson($http, $payload);
        } else {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code($http);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, max-age=0');
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $GLOBALS['__miniapp_response_sent'] = true;
    }

    public static function rx_miniapp_routes(): array
    {
        return [
            'user_info'              => \Faoxima\Api\Handlers\UserInfoHandler::class,
            'invoices'               => \Faoxima\Api\Handlers\InvoicesHandler::class,
            'service'                => \Faoxima\Api\Handlers\ServiceHandler::class,
            'countries'              => \Faoxima\Api\Handlers\CountriesHandler::class,
            'categories'             => \Faoxima\Api\Handlers\CategoriesHandler::class,
            'time_ranges'            => \Faoxima\Api\Handlers\TimeRangesHandler::class,
            'services'               => \Faoxima\Api\Handlers\ServicesHandler::class,
            'custom_price'           => \Faoxima\Api\Handlers\CustomPriceHandler::class,
            'purchase'               => \Faoxima\Api\Handlers\PurchaseHandler::class,
            'payment_methods'        => \Faoxima\Api\Handlers\PaymentMethodsHandler::class,
            'payment_init'           => \Faoxima\Api\Handlers\PaymentInitHandler::class,
            'payment_receipt'        => \Faoxima\Api\Handlers\PaymentReceiptHandler::class,
            'payment_status'         => \Faoxima\Api\Handlers\PaymentStatusHandler::class,
            'tonpay_custom_view'     => ['class' => \Faoxima\Api\Handlers\TonpayCustomHandler::class, 'mode' => 'view'],
            'tonpay_custom_change_card' => ['class' => \Faoxima\Api\Handlers\TonpayCustomHandler::class, 'mode' => 'change_card'],
            'tonpay_custom_cancel'   => ['class' => \Faoxima\Api\Handlers\TonpayCustomHandler::class, 'mode' => 'cancel'],
            'tonpay_custom_receipt'  => ['class' => \Faoxima\Api\Handlers\TonpayCustomHandler::class, 'mode' => 'receipt'],
            'tonpay_custom_refund'   => ['class' => \Faoxima\Api\Handlers\TonpayCustomHandler::class, 'mode' => 'refund'],
            'atlaspay_custom_view'   => ['class' => \Faoxima\Api\Handlers\AtlaspayCustomHandler::class, 'mode' => 'view'],
            'atlaspay_custom_cancel' => ['class' => \Faoxima\Api\Handlers\AtlaspayCustomHandler::class, 'mode' => 'cancel'],
            'payment_reset'          => \Faoxima\Api\Handlers\PaymentResetHandler::class,
            'crypto_currencies'      => \Faoxima\Api\Handlers\CryptoCurrenciesHandler::class,
            'crypto_invoice_init'    => \Faoxima\Api\Handlers\CryptoInvoiceInitHandler::class,
            'crypto_submit_hash'     => \Faoxima\Api\Handlers\CryptoSubmitHashHandler::class,
            'crypto_upload_receipt'  => \Faoxima\Api\Handlers\CryptoReceiptUploadHandler::class,
            'crypto_cancel_invoice'  => \Faoxima\Api\Handlers\CryptoCancelInvoiceHandler::class,
            'service_action'         => \Faoxima\Api\Handlers\ServiceActionHandler::class,
            'service_renew_options'  => \Faoxima\Api\Handlers\ServiceRenewOptionsHandler::class,
            'service_renew_confirm'  => \Faoxima\Api\Handlers\ServiceRenewConfirmHandler::class,
            'service_renew_preview'  => \Faoxima\Api\Handlers\ServiceRenewPreviewHandler::class,
            'service_extra_quote'    => ['class' => \Faoxima\Api\Handlers\ServiceExtraHandler::class, 'mode' => 'quote'],
            'service_extra_confirm'  => ['class' => \Faoxima\Api\Handlers\ServiceExtraHandler::class, 'mode' => 'confirm'],
            'service_simple_action'  => \Faoxima\Api\Handlers\ServiceSimpleActionHandler::class,
            'brand_info'             => ['class' => \Faoxima\Api\Handlers\BrandHandler::class, 'mode' => 'info'],
            'brand_save'             => ['class' => \Faoxima\Api\Handlers\BrandHandler::class, 'mode' => 'save'],
            'brand_upload_logo'      => ['class' => \Faoxima\Api\Handlers\BrandHandler::class, 'mode' => 'upload'],
            'notification_info'      => ['class' => \Faoxima\Api\Handlers\NotificationHandler::class, 'mode' => 'info'],
            'notification_recent'    => ['class' => \Faoxima\Api\Handlers\NotificationHandler::class, 'mode' => 'recent'],
            'notification_save'      => ['class' => \Faoxima\Api\Handlers\NotificationHandler::class, 'mode' => 'save'],
            'notification_delete'    => ['class' => \Faoxima\Api\Handlers\NotificationHandler::class, 'mode' => 'delete'],
            'notification_dismiss'   => ['class' => \Faoxima\Api\Handlers\NotificationHandler::class, 'mode' => 'dismiss'],
            'service_configs'        => \Faoxima\Api\Handlers\ServiceConfigsHandler::class,
            'pending_payments'       => \Faoxima\Api\Handlers\PendingPaymentsHandler::class,
            'transactions'           => \Faoxima\Api\Handlers\TransactionHistoryHandler::class,
            'wallet_transfer_quote'   => ['class' => \Faoxima\Api\Handlers\WalletTransferHandler::class, 'mode' => 'quote'],
            'wallet_transfer_confirm' => ['class' => \Faoxima\Api\Handlers\WalletTransferHandler::class, 'mode' => 'confirm'],
            'redeem_giftcode'        => \Faoxima\Api\Handlers\GiftCodeHandler::class,
            'discount_validate'      => \Faoxima\Api\Handlers\DiscountValidateHandler::class,
            'discount_eligible'      => \Faoxima\Api\Handlers\DiscountEligibleHandler::class,
            'tickets'                => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'list'],
            'ticket_thread'          => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'thread'],
            'ticket_departments'     => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'departments'],
            'ticket_create'          => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'create'],
            'ticket_reply'           => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'reply'],
            'ticket_close'           => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'close'],
            'ticket_media'           => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'media'],
            'config_file'            => \Faoxima\Api\Handlers\ConfigFileHandler::class,
            'config_dl_token'        => ['class' => \Faoxima\Api\Handlers\ConfigFileHandler::class, 'mode' => 'token'],
            'ticket_react'           => ['class' => \Faoxima\Api\Handlers\TicketsHandler::class, 'mode' => 'react'],
            'card_photo'             => \Faoxima\Api\Handlers\CardPhotoUploadHandler::class,
            'card_select'            => \Faoxima\Api\Handlers\CardSelectHandler::class,
            'test_account_info'      => ['class' => \Faoxima\Api\Handlers\TestAccountHandler::class, 'mode' => 'info'],
            'test_account_create'    => ['class' => \Faoxima\Api\Handlers\TestAccountHandler::class, 'mode' => 'create'],
            'service_locations'      => \Faoxima\Api\Handlers\ServiceLocationsHandler::class,
            'checkout_confirm'       => ['class' => \Faoxima\Api\Handlers\CheckoutHandler::class, 'mode' => 'confirm'],
            'checkout_cancel'        => ['class' => \Faoxima\Api\Handlers\CheckoutHandler::class, 'mode' => 'cancel'],
            'checkout_preview'       => ['class' => \Faoxima\Api\Handlers\CheckoutHandler::class, 'mode' => 'preview'],
        ];
    }

    public static function rx_miniapp_user(string $action): array
    {
        $token = \Faoxima\Api\Runtime\Auth::extractBearerToken();
        $signedDownloadUser = null;
        if ($token === null && $action === 'config_file') {
            $queryToken = $_GET['dl_token'] ?? '';
            if (is_string($queryToken) && $queryToken !== '') {
                $signedDownloadUser = \Faoxima\Api\Handlers\ConfigFileHandler::verifyDownloadToken($queryToken);
                if ($signedDownloadUser === null && preg_match('/^[a-f0-9]{40}$/i', $queryToken)) {
                    $token = $queryToken;
                }
            }
        }
        if ($token === null && $signedDownloadUser === null) {
            \Faoxima\Api\Runtime\Logger::debug('Missing/invalid Authorization header', ['action' => $action]);
            \Faoxima\Api\Runtime\Response::unauthorized('Authorization header missing or malformed');
        }

        $user = $signedDownloadUser !== null
            ? \Faoxima\Core\Database\Db::select('user', '*', 'id', (string)$signedDownloadUser, 'select')
            : \Faoxima\Api\Runtime\Auth::userFromToken($token);
        if ($signedDownloadUser !== null && !is_array($user)) {
            $user = null;
        }
        if ($user === null) {
            \Faoxima\Api\Runtime\Logger::debug('Bearer token did not match any user', ['action' => $action]);
            \Faoxima\Api\Runtime\Response::forbidden('Token invalid');
        }

        if (($user['User_Status'] ?? '') === 'block') {
            \Faoxima\Api\Runtime\Logger::warn('Blocked user attempted miniapp action', [
                'user_id' => $user['id'],
                'action'  => $action,
            ]);
            \Faoxima\Api\Runtime\Response::fail(403, 'user blocked');
        }

        $isAdmin = \Faoxima\Api\Runtime\Auth::isMiniAppAdministrator((int)$user['id']);
        if (!$isAdmin && ($user['joinchannel'] ?? '') !== 'active') {
            $channelsId = \Faoxima\Core\Database\Db::select('channels', 'link', null, null, 'FETCH_COLUMN', ['cache' => false]);
            if (!empty($channelsId) && is_array($channelsId)) {
                \Faoxima\Api\Runtime\Response::fail(403, 'join channel required');
            }
        }
        return $user;
    }

    public static function rx_miniapp_handler_route($entry): array
    {
        if (is_string($entry)) {
            $handlerClass = $entry;
            $handlerMode = null;
        } else {
            $handlerClass = (string)($entry['class'] ?? '');
            $handlerMode = $entry['mode'] ?? null;
        }

        if (!class_exists($handlerClass)) {
            \Faoxima\Api\Runtime\Logger::critical('Handler file missing', ['handler' => $handlerClass]);
            \Faoxima\Api\Runtime\Response::serverError('Handler not available');
        }
        return [$handlerClass, $handlerMode];
    }
}
