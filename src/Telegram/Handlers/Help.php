<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_nav_styles, $dataget, $datatextbot, $from_id, $json_list_helpـcategory, $message_id, $setting, $telegramRoute, $textbotlang.
 * Inherited or exported by reference: $datain.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Help
{
    public static function handle(
        $_rx_nav_styles,
        $dataget,
        &$datain,
        $datatextbot,
        $from_id,
        $json_list_helpـcategory,
        $message_id,
        $setting,
        $telegramRoute,
        $textbotlang
    ) {
        switch ($telegramRoute) {
        case 'help_menu':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_help")) {
                sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('help', $from_id, $datain, $message_id, ['helpmenubtn'], ['helpmenubtn']);
            if ($setting['categoryhelp'] == "1") {
                $rxSelectCategoryTxt = $datatextbot['dyn_tickets_select_category'] ?? "📌 یک دسته را انتخاب نمایید";
                if ($datain == "helpbtns" || $datain == "helpmenubtn") {
                    Editmessagetext($from_id, $message_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
                } else {
                    sendmessage($from_id, $rxSelectCategoryTxt, $json_list_helpـcategory, 'HTML');
                }
            } else {
                $helplist = \Faoxima\Core\Database\Db::select("help", "*", null, null, "fetchAll");
                $helpidos = ['inline_keyboard' => \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxHelpTopicButtons($helplist)];
                $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
                $helpidos['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"], 'backuser', $_rx_nav_s),
                ];
                $json_list_help = json_encode($helpidos);
                if ($datain == "helpbtns" || $datain == "helpmenubtn") {
                    Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
                } else {
                    sendmessage($from_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
                }
            }
            break;
        case 'help_category':
            $helplist = \Faoxima\Core\Database\Db::select("help", "*", "category", $dataget[1], "fetchAll");
            $helpidos = ['inline_keyboard' => \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxHelpTopicButtons($helplist)];
            $helpidos['inline_keyboard'][] = [
                ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "helpbtns", '_rxcolor' => 'navigation:help_back'],
            ];
            $json_list_help = json_encode($helpidos);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
            break;
        case 'help_topic':
            deletemessage($from_id, $message_id);
            $backinfoss = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns", '_rxcolor' => 'navigation:help_back'],
                    ]
                ]
            ]);
            $helpid = $dataget[1];
            $helpdata = \Faoxima\Core\Database\Db::select("help", "*", "id", $helpid, "select");
            if ($helpdata !== false) {
                $helpAppLinkRow = [];
                if (!empty($helpdata['app_link'])) {
                    $helpAppLinkRow[] = [
                        ['text' => $helpdata['app_title'] ?: 'دانلود برنامه', 'url' => $helpdata['app_link']],
                    ];
                }
                if (strlen($helpdata['Media_os']) != 0) {
                    if ($helpdata['type_Media_os'] == "video") {
                        $backinfoss = json_encode([
                            'inline_keyboard' => array_merge($helpAppLinkRow, [
                                [
                                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn", '_rxcolor' => 'navigation:help_back'],
                                ]
                            ])
                        ]);
                        rxSendMediaWithCaption('sendvideo', [
                            'chat_id' => $from_id,
                            'video' => $helpdata['Media_os'],
                            'caption' => $helpdata['Description_os'],
                            'reply_markup' => $backinfoss,
                            'parse_mode' => "HTML"
                        ]);
                    } elseif ($helpdata['type_Media_os'] == "document") {
                        $backinfoss = json_encode([
                            'inline_keyboard' => array_merge($helpAppLinkRow, [
                                [
                                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn", '_rxcolor' => 'navigation:help_back'],
                                ]
                            ])
                        ]);
                        rxSendMediaWithCaption('sendDocument', [
                            'chat_id' => $from_id,
                            'document' => $helpdata['Media_os'],
                            'caption' => $helpdata['Description_os'],
                            'reply_markup' => $backinfoss,
                            'parse_mode' => "HTML"
                        ]);
                    } elseif ($helpdata['type_Media_os'] == "photo") {
                        $backinfoss = json_encode([
                            'inline_keyboard' => array_merge($helpAppLinkRow, [
                                [
                                    ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtn", '_rxcolor' => 'navigation:help_back'],
                                ]
                            ])
                        ]);
                        rxSendMediaWithCaption('sendphoto', [
                            'chat_id' => $from_id,
                            'photo' => $helpdata['Media_os'],
                            'caption' => $helpdata['Description_os'],
                            'reply_markup' => $backinfoss,
                            'parse_mode' => "HTML"
                        ]);
                    }
                } else {
                    $backinfoss = json_encode([
                        'inline_keyboard' => array_merge($helpAppLinkRow, [
                            [
                                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "helpbtns", '_rxcolor' => 'navigation:help_back'],
                            ]
                        ])
                    ]);
                    sendmessage($from_id, $helpdata['Description_os'], $backinfoss, 'HTML');
                }
            }
            break;
        }

        return true;
    }
}
