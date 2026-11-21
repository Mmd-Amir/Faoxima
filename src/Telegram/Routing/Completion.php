<?php

namespace Faoxima\Telegram\Routing;

/**
 * Handler stage. Inherited by value: $ManagePanel, $codeproduct, $datain, $datakeyboard, $datatextbot, $errorreport, $extend, $first_name, $from_id, $keyboard, $location, $marzban_list_get, $message_id, $nameloc, $otherservice, $pdo, $prodcut, $query, $queryParams, $result, $setting, $statuscustom, $step_payment, $stmt, $textbotlang, $textextend, $update, $user, $username, $value.
 * Inherited or exported by reference: $dataget, $telegramHandler, $telegramRoute.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Completion
{
    public static function handle(
        $ManagePanel,
        $codeproduct,
        &$dataget,
        $datain,
        $datakeyboard,
        $datatextbot,
        $errorreport,
        $extend,
        $first_name,
        $from_id,
        $keyboard,
        $location,
        $marzban_list_get,
        $message_id,
        $nameloc,
        $otherservice,
        $pdo,
        $prodcut,
        $query,
        $queryParams,
        $result,
        $setting,
        $statuscustom,
        $step_payment,
        $stmt,
        &$telegramHandler,
        &$telegramRoute,
        $textbotlang,
        $textextend,
        $update,
        $user,
        $username,
        $value
    ) {
        $telegramRoute = \Faoxima\Telegram\Routing\CompletionRoutes::finalization($update, $datain, $dataget);
        $telegramHandler = match ($telegramRoute) {
            'stars_pre_checkout' => 'StarsPreCheckout',
            'renewal_start', 'renewal_product', 'renewal_confirm' => 'ExternalRenewal',
            default => null,
        };

        $telegramHandlerResult = match ($telegramHandler) {
                null => true,
                'ExternalRenewal' => \Faoxima\Telegram\Handlers\ExternalRenewal::handle(
                    ManagePanel: $ManagePanel ?? null,
                    codeproduct: $codeproduct,
                    dataget: $dataget ?? null,
                    datakeyboard: $datakeyboard,
                    datatextbot: $datatextbot ?? null,
                    errorreport: $errorreport ?? null,
                    extend: $extend,
                    first_name: $first_name ?? null,
                    from_id: $from_id ?? null,
                    keyboard: $keyboard ?? null,
                    location: $location,
                    marzban_list_get: $marzban_list_get ?? null,
                    message_id: $message_id ?? null,
                    nameloc: $nameloc,
                    otherservice: $otherservice ?? null,
                    pdo: $pdo ?? null,
                    prodcut: $prodcut,
                    query: $query,
                    queryParams: $queryParams,
                    result: $result,
                    setting: $setting ?? null,
                    statuscustom: $statuscustom,
                    step_payment: $step_payment ?? null,
                    stmt: $stmt,
                    telegramRoute: $telegramRoute ?? null,
                    textbotlang: $textbotlang ?? null,
                    textextend: $textextend,
                    user: $user ?? null,
                    username: $username,
                    value: $value
                ),
                'StarsPreCheckout' => \Faoxima\Telegram\Handlers\StarsPreCheckout::handle(
                    telegramRoute: $telegramRoute ?? null,
                    update: $update ?? null
                ),
                default => throw new \RuntimeException('Unknown Telegram handler: ' . $telegramHandler),
            };
        if ($telegramHandlerResult !== true) {
            return;
        }
        unset($telegramRoute, $telegramHandler);

        return true;
    }
}
