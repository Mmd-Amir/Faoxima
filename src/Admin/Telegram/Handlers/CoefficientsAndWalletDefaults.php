<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $dataget, $from_id, $message_id, $shopkeyboard, $textbotlang, $update, $user.
 * Inherited or exported by reference: $text, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CoefficientsAndWalletDefaults
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $dataget,
        $from_id,
        $message_id,
        $shopkeyboard,
        &$text,
        $textbotlang,
        $update,
        $user,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            $textnode = "📌 ضریب مصرف نودتان را ارسال نمایید.";
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            \Faoxima\Telegram\Navigation\Steps::step("getusage_coefficient", $from_id);
            break;
        case 2:
            if (!isset($update['message']) && empty($text)) { return; }
            $config = array(
                'usage_coefficient' => $text
            );
            $rxNodeResult = \Faoxima\Services\Panels\Drivers\Marzban::Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
            if (is_array($rxNodeResult) && !empty($rxNodeResult['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($rxNodeResult['error']), null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ ضریب مصرف نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            $textnode = "📌 نام نودتان را ارسال نمانیید.";
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            \Faoxima\Telegram\Navigation\Steps::step("getnamenode", $from_id);
            break;
        case 4:
            if (!isset($update['message']) && empty($text)) { return; }
            $config = array(
                'name' => $text
            );
            $rxNodeResult = \Faoxima\Services\Panels\Drivers\Marzban::Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
            if (is_array($rxNodeResult) && !empty($rxNodeResult['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($rxNodeResult['error']), null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅  نام نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            $textnode = "📌 آیپی نود را ارسال نمانیید.";
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            \Faoxima\Telegram\Navigation\Steps::step("getipnodeset", $from_id);
            break;
        case 6:
            if (!isset($update['message']) && empty($text)) { return; }
            $config = array(
                'address' => $text
            );
            $rxNodeResult = \Faoxima\Services\Panels\Drivers\Marzban::Modifyuser_node($user['Processing_value'], $user['Processing_value_one'], $config);
            if (is_array($rxNodeResult) && !empty($rxNodeResult['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($rxNodeResult['error']), null, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅  آدرس نود با موفقیت ذخیره گردید.", $backinfoss, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 7:
            $rxNodeResult = \Faoxima\Services\Panels\Drivers\Marzban::reconnect_node($user['Processing_value'], $user['Processing_value_one']);
            if (is_array($rxNodeResult) && !empty($rxNodeResult['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($rxNodeResult['error']), null, 'HTML');
                return;
            }
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "node_" . $user['Processing_value_one']],
                    ]
                ]
            ]);
            $textnode = "✅ اتصال مجدد نود انجام گردید.";
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            break;
        case 8:
            $rxNodeResult = \Faoxima\Services\Panels\Drivers\Marzban::removenode($user['Processing_value'], $user['Processing_value_one']);
            if (is_array($rxNodeResult) && !empty($rxNodeResult['error'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ " . \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($rxNodeResult['error']), null, 'HTML');
                return;
            }
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔙 بازگشت به نود ", 'callback_data' => "bakcnode"],
                    ]
                ]
            ]);
            $textnode = "✅ نود با موفقیت حذف گردید";
            Editmessagetext($from_id, $message_id, $textnode, $backinfoss);
            break;
        case 9:
            \Faoxima\Telegram\Navigation\Steps::step('admin_nav_finance', $from_id);
            $Bot_Status = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildPaymentGatewayKeyboard($textbotlang);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status, 'HTML');
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 مقدار درصدی که می خواهید حساب کاربر بعد از تمدید به عنوان هدیه شارژ شود را ارسال کنید.
⚠️ در صورتی که میخواهید غیرفعال باشد عدد 0 را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getpricecashback', $from_id);
            break;
        case 11:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "price_cashback", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نوع کاربری را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getagent', $from_id);
            break;
        case 12:
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, ['f', 'n', 'n2']);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ گروه کاربری نامعتبر است", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_agent_group", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getrenewcashtarget', $from_id);
            break;
        case 13:
            $resolvedTarget = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveCashbackTargetFromReplyButton($text);
            if ($resolvedTarget === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است، لطفا از دکمه های زیر انتخاب کنید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
                return;
            }
            if ($resolvedTarget === 'custom') {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_target", $resolvedTarget);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تعداد روز مورد نظر را به صورت عدد وارد کنید (بین 1 تا 365)", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("getrenewcashtargetdays", $from_id);
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $agentGroup = $userdata['cashback_agent_group'] ?? 'f';
            if ($agentGroup == "f") {
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
            } else {
                $shop_cashbackagent = json_decode(\Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
                $shop_cashbackagent[$agentGroup] = $userdata['price_cashback'];
                \Faoxima\Core\Database\Db::update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
            }
            \Faoxima\Core\Database\Db::update("shopSetting", "value", $resolvedTarget, "Namevalue", "chashbackextend_target");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ و جامعه هدف با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 14:
            if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 365) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفا یک عدد صحیح بین 1 تا 365 ارسال کنید", $backadmin, 'HTML');
                return;
            }
            $userdata = json_decode($user['Processing_value'], true);
            $agentGroup = $userdata['cashback_agent_group'] ?? 'f';
            if ($agentGroup == "f") {
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $userdata['price_cashback'], "Namevalue", "chashbackextend");
            } else {
                $shop_cashbackagent = json_decode(\Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend_agent")['value'], true);
                $shop_cashbackagent[$agentGroup] = $userdata['price_cashback'];
                \Faoxima\Core\Database\Db::update("shopSetting", "value", json_encode($shop_cashbackagent), "Namevalue", "chashbackextend_agent");
            }
            \Faoxima\Core\Database\Db::update("shopSetting", "value", "custom:" . (int)$text, "Namevalue", "chashbackextend_target");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ و جامعه هدف با موفقیت تنظیم شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 15:
            $type = $dataget[1];
            $value = $dataget[2];
            if ($type == "Cartstatus") {
                if ($value == "oncard") {
                    $valuenew = "offcard";
                } else {
                    $valuenew = "oncard";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "Cartstatus");
            } elseif ($type == "plisio") {
                if ($value == "onnowpayment") {
                    $valuenew = "offnowpayment";
                } else {
                    $valuenew = "onnowpayment";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "nowpaymentstatus");
            } elseif ($type == "arzireyali2") {
                if ($value == "onternado") {
                    $valuenew = "offternado";
                } else {
                    $valuenew = "onternado";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustarnado");
            } elseif ($type == "zarinpal") {
                if ($value == "onzarinpal") {
                    $valuenew = "offzarinpal";
                } else {
                    $valuenew = "onzarinpal";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "zarinpalstatus");
            } elseif ($type == "affilnecurrency") {
                if ($value == "ondigi") {
                    $valuenew = "offdigi";
                } else {
                    $valuenew = "ondigi";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "digistatus");
            } elseif ($type == "startelegram") {
                if ($value == "1") {
                    $valuenew = "0";
                } else {
                    $valuenew = "1";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusstar");
            } elseif ($type == "nowpayment") {
                if ($value == "1") {
                    $valuenew = "0";
                } else {
                    $valuenew = "1";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusnowpayment");
            } elseif ($type == "tonpay") {
                if ($value == "ontonpay") {
                    $valuenew = "offtonpay";
                } else {
                    $valuenew = "ontonpay";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustonpay");
            } elseif ($type == "rialit") {
                if ($value == "onrialit") {
                    $valuenew = "offrialit";
                } else {
                    $valuenew = "onrialit";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusrialit");
            } elseif ($type == "tetraminator") {
                if ($value == "ontetraminator") {
                    $valuenew = "offtetraminator";
                } else {
                    $valuenew = "ontetraminator";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statustetraminator");
            } elseif ($type == "cubepay") {
                if ($value == "oncubepay") {
                    $valuenew = "offcubepay";
                } else {
                    $valuenew = "oncubepay";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statuscubepay");
            } elseif ($type == "variza") {
                if ($value == "onvariza") {
                    $valuenew = "offvariza";
                } else {
                    $valuenew = "onvariza";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusvariza");
            } elseif ($type == "abangateway") {
                if ($value == "onabangateway") {
                    $valuenew = "offabangateway";
                } else {
                    $valuenew = "onabangateway";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusabangateway");
            } elseif ($type == "blupal") {
                if ($value == "onblupal") {
                    $valuenew = "offblupal";
                } else {
                    $valuenew = "onblupal";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusblupal");
            } elseif ($type == "atlaspay") {
                if ($value == "onatlaspay") {
                    $valuenew = "offatlaspay";
                } else {
                    $valuenew = "onatlaspay";
                }
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $valuenew, "NamePay", "statusatlaspay");
            }
            $Bot_Status = \Faoxima\Admin\Keyboard\AdminBuildersHelpers::buildPaymentGatewayKeyboard($textbotlang);
            Editmessagetext($from_id, $message_id, "📌 از لیست زیر میتوانید درگاه ها را مدیریت کنید.

⚠️ تیم فاکسیما هیچ تضمینی برای درگاه ها نخواهد داشت و استفاده  و تمامی مسئولیت ها به عهده شما می باشد", $Bot_Status);
            break;
        }

        return true;
    }
}
