<?php

namespace Faoxima\Admin\Telegram\Conversation;

/**
 * Handler stage. Inherited by value: $adminExecution, $rxK, $rxV.
 * Inherited or exported by reference: $rawProcessing, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PanelState
{
    public static function handle(
        $adminExecution,
        &$rawProcessing,
        $rxK,
        $rxV,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        if (is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser'])) {
            // Some legacy handlers in this file (panel-name edit, URL edit, etc.)
            // expect $user['Processing_value'] to be a SCALAR panel name. This shim
            // extracts the panel name from a JSON-encoded state and writes it back
            // as a scalar — but doing that UNCONDITIONALLY destroys the multi-key
            // JSON state used by the discount/gift creation flows.
            //
            // Symptom: in step `getproductdiscount` the admin types "all", the
            // handler runs json_decode($user['Processing_value']) and gets NULL
            // (because this shim just overwrote the JSON with "/all"), every key
            // looks "missing", and the bot replies "اطلاعات ساخت کد تخفیف ناقص".
            //
            // Skip the shim while the user is mid-flow in one of those JSON-state
            // steps. Other steps keep their previous behavior.
            $jsonStateSteps = [
                'get_code','get_price_code','getlimitcodedis',
                'get_codesell','get_price_codesell','getlimitcode','gettypecodeagent',
                'gettimediscount','getfirstdiscount','getuseuser','getlocdiscount','getproductdiscount',
                'switchtype_pick','switchtype_link_panel','switchtype_username_panel',
                'switchtype_password_panel','switchtype_guard_api_key','switchtype_remna_token',
                'switchtype_xui_api_mode','switchtype_xui_token','switchtype_confirm',
            ];
            if (!in_array($user['step'] ?? '', $jsonStateSteps, true)) {
                $rxResolvedPanelName = \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user);
                if ($rxResolvedPanelName !== '') {
                    $rawProcessing = (string)($user['Processing_value'] ?? '');
                    $rxFlatten = ($rawProcessing === '');
                    if (!$rxFlatten && ($rawProcessing[0] === '{' || $rawProcessing[0] === '[')) {
                        // Only flatten a JSON state that is a BARE panel reference. If it
                        // carries ANY other key it is an in-progress multi-step flow state
                        // (custom volume/time/price, add-config, etc.) whose JSON must
                        // survive — otherwise the next step json_decode()s it, gets the
                        // scalar panel name back, and reports "اطلاعات مرحله قبلی ناقص".
                        $rxDecodedState = json_decode($rawProcessing, true);
                        $rxFlatten = true;
                        if (is_array($rxDecodedState)) {
                            foreach ($rxDecodedState as $rxK => $rxV) {
                                if (!in_array($rxK, ['namepanel', 'name_panel', 'panel', 'panel_name'], true)) {
                                    $rxFlatten = false;
                                    break;
                                }
                            }
                        }
                        unset($rxDecodedState, $rxK, $rxV);
                    }
                    if ($rxFlatten) {
                        $user['Processing_value'] = $rxResolvedPanelName;
                    }
                    unset($rxFlatten);
                }
                unset($rxResolvedPanelName, $rawProcessing);
            }
            unset($jsonStateSteps);
        }

        return true;
    }
}
