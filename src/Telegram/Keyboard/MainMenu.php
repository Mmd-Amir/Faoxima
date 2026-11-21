<?php

namespace Faoxima\Telegram\Keyboard;

final class MainMenu
{
    public static function build(
        $setting,
        array $datatextbot,
        array $textbotlang,
        $users,
        $admin_idss,
        string $rxMiniAppDomain,
        ?callable $richSection,
        ?callable $finalize,
        ?callable $sanitize,
        ?callable $mark,
        ?callable $log,
    ) {
        $replacements = [
            'text_usertest' => $datatextbot['text_usertest'],
            'text_Purchased_services' => $datatextbot['text_Purchased_services'],
            'text_support' => $datatextbot['text_support'],
            'text_help' => $datatextbot['text_help'],
            'accountwallet' => $datatextbot['accountwallet'],
            'text_sell' => $datatextbot['text_sell'],
            'text_Tariff_list' => $datatextbot['text_Tariff_list'],
            'text_affiliates' => $datatextbot['text_affiliates'],
            'text_wheel_luck' => $datatextbot['text_wheel_luck'],
            'text_extend' => $datatextbot['text_extend']
        ];
        $temp_addtional_key = [];
        $keyboardLayout = json_decode($setting['keyboardmain'], true);
        $keyboardRows = [];
        if (is_array($keyboardLayout) && isset($keyboardLayout['keyboard']) && is_array($keyboardLayout['keyboard'])) {
            $keyboardRows = $keyboardLayout['keyboard'];
        }

        $rxMiniAppApply = function (array $rows, bool $inline) use ($datatextbot, $rxMiniAppDomain, $log) {
            $rxMiniAppUrl = '';
            $rxMiniAppHost = rtrim((string)preg_replace('#^https?://#i', '', trim($rxMiniAppDomain)), '/');
            if ($rxMiniAppHost !== '') {
                $rxMiniAppCandidate = 'https://' . $rxMiniAppHost . '/app/';
                if (filter_var($rxMiniAppCandidate, FILTER_VALIDATE_URL) !== false
                    && strtolower((string)parse_url($rxMiniAppCandidate, PHP_URL_SCHEME)) === 'https'
                    && (string)parse_url($rxMiniAppCandidate, PHP_URL_HOST) !== '') {
                    $rxMiniAppUrl = $rxMiniAppCandidate;
                }
            }
            $rxMiniAppLabel = (string)($datatextbot['text_miniapp_button'] ?? '');
            if (trim($rxMiniAppLabel) === '') $rxMiniAppLabel = '🚀 Open Mini App';
            $rxMiniAppFound = false;
            $rxMiniAppRows = [];
            foreach ($rows as $rxMiniAppRow) {
                if (!is_array($rxMiniAppRow)) { $rxMiniAppRows[] = $rxMiniAppRow; continue; }
                $rxMiniAppNewRow = [];
                foreach ($rxMiniAppRow as $rxMiniAppBtn) {
                    if (is_array($rxMiniAppBtn) && isset($rxMiniAppBtn['text']) && $rxMiniAppBtn['text'] === 'text_miniapp_button') {
                        $rxMiniAppFound = true;
                        if ($rxMiniAppUrl === '') continue;
                        unset($rxMiniAppBtn['callback_data'], $rxMiniAppBtn['_rxsk'], $rxMiniAppBtn['url'], $rxMiniAppBtn['web_app']);
                        $rxMiniAppBtn['text'] = $rxMiniAppLabel;
                        // Reply-keyboard Web Apps receive no initData. Send a text
                        // command there so the bot can offer an authenticated inline launch.
                        if ($inline) {
                            $rxMiniAppBtn['web_app'] = ['url' => $rxMiniAppUrl];
                        }
                    }
                    $rxMiniAppNewRow[] = $rxMiniAppBtn;
                }
                if (!empty($rxMiniAppNewRow) || empty($rxMiniAppRow)) $rxMiniAppRows[] = $rxMiniAppNewRow;
            }
            if ($rxMiniAppFound && $rxMiniAppUrl === '' && $log !== null) {
                $log('MINIAPP_BUTTON_SKIPPED', 'Main keyboard Mini App button skipped: invalid or empty domainhosts.', [
                    'where' => 'layouts_1',
                ]);
            }
            return $rxMiniAppRows;
        };

        if (($setting['inlinebtnmain'] == "oninline" || ($richSection !== null && $richSection('main_menu'))) && !empty($keyboardRows)) {
            $trace_keyboard = $keyboardRows;
            foreach ($trace_keyboard as $key => $callback_set) {
                foreach ($callback_set as $keyboard_key => $keyboard) {
                    if ($keyboard['text'] == "text_sell") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
                        $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_buy";
                    }
                    if ($keyboard['text'] == "accountwallet") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "accountbtn";
                        $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_account";
                    }
                    if ($keyboard['text'] == "text_Tariff_list") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
                        $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_tariff";
                    }
                    if ($keyboard['text'] == "text_wheel_luck") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
                    }
                    if ($keyboard['text'] == "text_affiliates") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
                    }
                    if ($keyboard['text'] == "text_extend") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
                    }
                    if ($keyboard['text'] == "text_support") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
                    }
                    if ($keyboard['text'] == "text_Purchased_services") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "myservicesbtn";
                        $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "home_myservices";
                    }
                    if ($keyboard['text'] == "text_help") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpmenubtn";
                        $trace_keyboard[$key][$keyboard_key]['_rxsk'] = "helpbtns";
                    }
                    if ($keyboard['text'] == "text_usertest") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
                    }
                }
            }
            if ($admin_idss != 0) {
                $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
                if (trim($rx_lbl_admin) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_admin, 'callback_data' => "admin"];
                }
            }
            if ($users['agent'] != "f") {
                $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
                if (trim($rx_lbl_agent) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_agent, 'callback_data' => "agentpanel"];
                }
            }
            if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
                $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
                if (trim($rx_lbl_req) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_req, 'callback_data' => "requestagent"];
                }
            }
            $keyboard = ['inline_keyboard' => []];
            $keyboardcustom = $trace_keyboard;
            $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
            if (is_array($keyboardcustom)) $keyboardcustom = $rxMiniAppApply($keyboardcustom, true);
            if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
            $keyboard['inline_keyboard'] = $keyboardcustom;
            $keyboard = $finalize !== null
                ? $finalize(json_encode($keyboard), false)
                : json_encode($keyboard);
            $keyboard = $sanitize !== null ? $sanitize($keyboard) : $keyboard;
            if ($mark !== null) { $keyboard = $mark($keyboard, 'main_menu'); }
        } else {
            if ($admin_idss != 0) {
                $rx_lbl_admin = (string)($textbotlang['Admin']['textpaneladmin'] ?? '');
                if (trim($rx_lbl_admin) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_admin];
                }
            }
            if ($users['agent'] != "f") {
                $rx_lbl_agent = (string)($datatextbot['textpanelagent'] ?? '');
                if (trim($rx_lbl_agent) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_agent];
                }
            }
            if ($users['agent'] == "f" && $setting['statusagentrequest'] == "onrequestagent") {
                $rx_lbl_req = (string)($datatextbot['textrequestagent'] ?? '');
                if (trim($rx_lbl_req) !== '') {
                    $temp_addtional_key[] = ['text' => $rx_lbl_req];
                }
            }
            $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
            $keyboardcustom = $keyboardRows;
            $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
            if (is_array($keyboardcustom)) $keyboardcustom = $rxMiniAppApply($keyboardcustom, false);
            if (!empty($temp_addtional_key)) $keyboardcustom[] = $temp_addtional_key;
            $keyboard['keyboard'] = $keyboardcustom;
            $keyboard = $finalize !== null
                ? $finalize(json_encode($keyboard), false)
                : json_encode($keyboard);
            $keyboard = $sanitize !== null ? $sanitize($keyboard) : $keyboard;
            if ($mark !== null) { $keyboard = $mark($keyboard, 'main_menu'); }
        }

        return $keyboard;
    }
}
