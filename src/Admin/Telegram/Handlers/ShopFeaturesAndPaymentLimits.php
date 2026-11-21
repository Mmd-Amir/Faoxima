<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $dataget, $from_id, $message_id, $textbotlang.
 * Inherited or exported by reference: $setting, $value, $valuenew.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ShopFeaturesAndPaymentLimits
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $dataget,
        $from_id,
        $message_id,
        &$setting,
        $textbotlang,
        &$value,
        &$valuenew
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select") ?? [];

            $marzbanstatusextraRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
            $marzbandirectpayRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
            $statustimeextraRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
            $statusdisorderRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
            $statuschangeserviceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
            $statusshowpriceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
            $statusshowconfigRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
            $statusremoveserveiceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

            $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
            $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
            $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
            $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
            $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
            $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
            $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
            $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

            $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
            if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
                $categoryStatusGeneralKey = 'offcategorys';
            }
            $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
            if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
                $categoryStatusKey = 'offcategory';
            }

            $name_status_extra_Vloume = [
                'onextra' => $textbotlang['Admin']['Status']['statuson'],
                'offextra' => $textbotlang['Admin']['Status']['statusoff']
            ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_paydirect = [
                'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
                'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
            ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_timeextra = [
                'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
                'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
            ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_disorder = [
                'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
                'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $categorygenral = [
                'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
                'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
            ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statustextchange = [
                'onstatus' => $textbotlang['Admin']['Status']['statuson'],
                'offstatus' => $textbotlang['Admin']['Status']['statusoff']
            ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusshowpricestext = [
                'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
                'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusshowconfigtext = [
                'onconfig' => $textbotlang['Admin']['Status']['statuson'],
                'offconfig' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusbackremovetext = [
                'on' => $textbotlang['Admin']['Status']['statuson'],
                'off' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_categorytime = [
                'oncategory' => $textbotlang['Admin']['Status']['statuson'],
                'offcategory' => $textbotlang['Admin']['Status']['statusoff']
            ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $Bot_Status = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                        ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
                    ],
                    [
                        ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "none"],
                    ],
                    [
                        ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                        ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
                    ],
                    [
                        ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
                    ],
                    [
                        ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                        ['text' => "⚠️ گزارش اختلال", 'callback_data' => "disorderss"],
                    ],
                    [
                        ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                        ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
                    ],
                    [
                        ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
                    ],
                    [
                        ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                        ['text' => "❓ غیرفعال اکانت", 'callback_data' => "changgestatus"],
                    ],
                    [
                        ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                        ['text' => "💰 قیمت محصول", 'callback_data' => "showprice"],
                    ],
                    [
                        ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                        ['text' => "🔗 دریافت کانفیگ", 'callback_data' => "config"],
                    ],
                    [
                        ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                        ['text' => "💎 بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
                    ],
                    [
                        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status, 'HTML');
            break;
        case 2:
            $type = $dataget[1];
            $value = $dataget[2];
            if ($type == "extravolunme") {
                if ($value == "onextra") {
                    $valuenew = "offextra";
                } else {
                    $valuenew = "onextra";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statusextra");
            } elseif ($type == "paydirect") {
                if ($value == "ondirectbuy") {
                    $valuenew = "offdirectbuy";
                } else {
                    $valuenew = "ondirectbuy";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statusdirectpabuy");
            } elseif ($type == "statustimeextra") {
                if ($value == "ontimeextraa") {
                    $valuenew = "offtimeextraa";
                } else {
                    $valuenew = "ontimeextraa";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statustimeextra");
            } elseif ($type == "disorderss") {
                if ($value == "ondisorder") {
                    $valuenew = "offdisorder";
                } else {
                    $valuenew = "ondisorder";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statusdisorder");
            } elseif ($type == "categroygenral") {
                if ($value == "oncategorys") {
                    $valuenew = "offcategorys";
                } else {
                    $valuenew = "oncategorys";
                }
                \Faoxima\Core\Database\Db::update("setting", "statuscategorygenral", $valuenew, null, null);
            } elseif ($type == "changgestatus") {
                if ($value == "onstatus") {
                    $valuenew = "offstatus";
                } else {
                    $valuenew = "onstatus";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statuschangeservice");
            } elseif ($type == "showprice") {
                if ($value == "onshowprice") {
                    $valuenew = "offshowprice";
                } else {
                    $valuenew = "onshowprice";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "statusshowprice");
            } elseif ($type == "showconfig") {
                if ($value == "onconfig") {
                    $valuenew = "offconfig";
                } else {
                    $valuenew = "onconfig";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "configshow");
            } elseif ($type == "removeservicebackbtn") {
                if ($value == "on") {
                    $valuenew = "off";
                } else {
                    $valuenew = "on";
                }
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $valuenew, "Namevalue", "backserviecstatus");
            } elseif ($type == "categorytime") {
                if ($value == "oncategory") {
                    $valuenew = "offcategory";
                } else {
                    $valuenew = "oncategory";
                }
                \Faoxima\Core\Database\Db::update("setting", "statuscategory", $valuenew);
            }
            $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select") ?? [];

            $marzbanstatusextraRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusextra", "select") ?? [];
            $marzbandirectpayRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusdirectpabuy", "select") ?? [];
            $statustimeextraRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statustimeextra", "select") ?? [];
            $statusdisorderRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusdisorder", "select") ?? [];
            $statuschangeserviceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statuschangeservice", "select") ?? [];
            $statusshowpriceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "statusshowprice", "select") ?? [];
            $statusshowconfigRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "configshow", "select") ?? [];
            $statusremoveserveiceRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "backserviecstatus", "select") ?? [];

            $marzbanstatusextra = $marzbanstatusextraRow['value'] ?? 'offextra';
            $marzbandirectpay = $marzbandirectpayRow['value'] ?? 'offdirectbuy';
            $statustimeextra = $statustimeextraRow['value'] ?? 'offtimeextraa';
            $statusdisorder = $statusdisorderRow['value'] ?? 'offdisorder';
            $statuschangeservice = $statuschangeserviceRow['value'] ?? 'offstatus';
            $statusshowprice = $statusshowpriceRow['value'] ?? 'offshowprice';
            $statusshowconfig = $statusshowconfigRow['value'] ?? 'offconfig';
            $statusremoveserveice = $statusremoveserveiceRow['value'] ?? 'off';

            $categoryStatusGeneralKey = $setting['statuscategorygenral'] ?? 'offcategorys';
            if (!in_array($categoryStatusGeneralKey, ['oncategorys', 'offcategorys'], true)) {
                $categoryStatusGeneralKey = 'offcategorys';
            }

            $categoryStatusKey = $setting['statuscategory'] ?? 'offcategory';
            if (!in_array($categoryStatusKey, ['oncategory', 'offcategory'], true)) {
                $categoryStatusKey = 'offcategory';
            }

            $name_status_extra_Vloume = [
                'onextra' => $textbotlang['Admin']['Status']['statuson'],
                'offextra' => $textbotlang['Admin']['Status']['statusoff']
            ][$marzbanstatusextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_paydirect = [
                'ondirectbuy' => $textbotlang['Admin']['Status']['statuson'],
                'offdirectbuy' => $textbotlang['Admin']['Status']['statusoff']
            ][$marzbandirectpay] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_timeextra = [
                'ontimeextraa' => $textbotlang['Admin']['Status']['statuson'],
                'offtimeextraa' => $textbotlang['Admin']['Status']['statusoff']
            ][$statustimeextra] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_disorder = [
                'ondisorder' => $textbotlang['Admin']['Status']['statuson'],
                'offdisorder' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusdisorder] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $categorygenral = [
                'oncategorys' => $textbotlang['Admin']['Status']['statuson'],
                'offcategorys' => $textbotlang['Admin']['Status']['statusoff']
            ][$categoryStatusGeneralKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statustextchange = [
                'onstatus' => $textbotlang['Admin']['Status']['statuson'],
                'offstatus' => $textbotlang['Admin']['Status']['statusoff']
            ][$statuschangeservice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusshowpricestext = [
                'onshowprice' => $textbotlang['Admin']['Status']['statuson'],
                'offshowprice' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusshowprice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusshowconfigtext = [
                'onconfig' => $textbotlang['Admin']['Status']['statuson'],
                'offconfig' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusshowconfig] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $statusbackremovetext = [
                'on' => $textbotlang['Admin']['Status']['statuson'],
                'off' => $textbotlang['Admin']['Status']['statusoff']
            ][$statusremoveserveice] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $name_status_categorytime = [
                'oncategory' => $textbotlang['Admin']['Status']['statuson'],
                'offcategory' => $textbotlang['Admin']['Status']['statusoff']
            ][$categoryStatusKey] ?? ($textbotlang['Admin']['Status']['statusoff'] ?? 'غیرفعال');
            $Bot_Status = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => (string)($textbotlang['Admin']['Status']['statussubject'] ?? '📌 موضوع'), 'callback_data' => "subjectde"],
                        ['text' => (string)($textbotlang['Admin']['Status']['subject'] ?? '📌 موضوع'), 'callback_data' => "subject"],
                    ],
                    [
                        ['text' => $name_status_extra_Vloume, 'callback_data' => "editshops-extravolunme-$marzbanstatusextra"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statusvolumeextra'] ?? '📦 حجم اضافه'), 'callback_data' => "none"],
                    ],
                    [
                        ['text' => $name_status_paydirect, 'callback_data' => "editshops-paydirect-$marzbandirectpay"],
                        ['text' => (string)($textbotlang['Admin']['Status']['paydirect'] ?? '💳 پرداخت مستقیم'), 'callback_data' => "paydirect"],
                    ],
                    [
                        ['text' => $name_status_timeextra, 'callback_data' => "editshops-statustimeextra-$statustimeextra"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statustimeextra'] ?? '⏱ زمان اضافه'), 'callback_data' => "statustimeextra"],
                    ],
                    [
                        ['text' => $name_status_disorder, 'callback_data' => "editshops-disorderss-$statusdisorder"],
                        ['text' => "⚠️ گزارش اختلال", 'callback_data' => "disorderss"],
                    ],
                    [
                        ['text' => $categorygenral, 'callback_data' => "editshops-categroygenral-" . $setting['statuscategorygenral']],
                        ['text' => "🐛 دسته بندی ", 'callback_data' => "categroygenral"],
                    ],
                    [
                        ['text' => $name_status_categorytime, 'callback_data' => "editshops-categorytime-{$setting['statuscategory']}"],
                        ['text' => (string)($textbotlang['Admin']['Status']['statuscategorytime'] ?? '📂 دسته زمان‌دار'), 'callback_data' => "statuscategorytime"],
                    ],
                    [
                        ['text' => $statustextchange, 'callback_data' => "editshops-changgestatus-" . $statuschangeservice],
                        ['text' => "❓ غیرفعال اکانت", 'callback_data' => "changgestatus"],
                    ],
                    [
                        ['text' => $statusshowpricestext, 'callback_data' => "editshops-showprice-" . $statusshowprice],
                        ['text' => "💰 قیمت محصول", 'callback_data' => "showprice"],
                    ],
                    [
                        ['text' => $statusshowconfigtext, 'callback_data' => "editshops-showconfig-" . $statusshowconfig],
                        ['text' => "🔗 دریافت کانفیگ", 'callback_data' => "config"],
                    ],
                    [
                        ['text' => $statusbackremovetext, 'callback_data' => "editshops-removeservicebackbtn-" . $statusremoveserveice],
                        ['text' => "💎 بازگشت وجه", 'callback_data' => "removeservicebackbtn"],
                    ],
                    [
                        ['text' => "❌ بستن", 'callback_data' => 'close_stat']
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['BotTitle'], $Bot_Status);
            break;
        }

        return true;
    }
}
