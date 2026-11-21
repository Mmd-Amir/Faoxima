<?php

namespace Faoxima\Admin\Keyboard;

use PDO;
use Throwable;

final class ChannelAndFeatureMenusHelpers
{
    public static function rx_get_channel_keyboard($forceInline = null)
    {
        global $pdo, $textbotlang, $_rx_chan_styles;
        return self::channels($pdo instanceof PDO ? $pdo : null, $textbotlang ?? [], is_array($_rx_chan_styles) ? $_rx_chan_styles : null, $forceInline);
    }

    public static function channels(?PDO $pdo, array $textbotlang, ?array $styles, $forceInline = null)
    {
        $styles ??= ['__rxplain__' => 1];
        $rows = [];
        $rows[] = [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "➕ افزودن کانال", 'callback_data' => 'ch_add'], 'ch_add', $styles)
        ];
        if (isset($pdo) && ($pdo instanceof PDO)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM channels ORDER BY id ASC");
                $stmt->execute();
                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($channels)) {
                    foreach ($channels as $chan) {
                        $chanId = isset($chan['id']) && $chan['id'] !== '' ? $chan['id'] : $chan['link'];
                        $btnTitle = !empty($chan['remark']) ? $chan['remark'] : (!empty($chan['link']) ? $chan['link'] : 'کانال ' . $chanId);
                        $rows[] = [
                            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style([
                                'text' => "📢 " . $btnTitle,
                                'callback_data' => "ch_manage_" . $chanId
                            ], 'ch_manage_' . $chanId, $styles)
                        ];
                    }
                }
            } catch (Throwable $e) {
                error_log('[rx_get_channel_keyboard] ' . $e->getMessage());
            }
        }
        $rows[] = [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'ch_backmenu'], 'ch_backmenu', $styles)
        ];
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($rows, $forceInline);
    }

    public static function rx_get_channel_manage_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "✏️ ویرایش عنوان دکمه", 'callback_data' => "ch_editremark_" . $channelId], 'ch_editremark', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔗 ویرایش لینک عضویت", 'callback_data' => "ch_editjoin_" . $channelId], 'ch_editjoin', $styles)
            ],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🆔 ویرایش آیدی کانال", 'callback_data' => "ch_editlink_" . $channelId], 'ch_editlink', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "❌ حذف کانال", 'callback_data' => "ch_del_" . $channelId], 'ch_del', $styles)
            ],
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🔙 بازگشت به لیست کانال‌ها", 'callback_data' => "set_channel"], 'ch_list', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($rows, $forceInline);
    }

    public static function rx_get_channel_edit_back_keyboard($channelId, $forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $channelId = (int)$channelId;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "ch_manage_" . $channelId], 'ch_manage', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($rows, $forceInline);
    }

    public static function rx_get_channel_wizard_back_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        $styles = is_array($_rx_chan_styles) ? $_rx_chan_styles : (isset($GLOBALS['_rx_chan_styles']) && is_array($GLOBALS['_rx_chan_styles']) ? $GLOBALS['_rx_chan_styles'] : ['__rxplain__' => 1]);
        $rows = [
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'set_channel'], 'ch_list', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles)
            ]
        ];
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($rows, $forceInline);
    }

    public static function rx_get_backadmin_keyboard($forceInline = null)
    {
        global $textbotlang, $_rx_chan_styles;
        return self::back($textbotlang ?? [], is_array($_rx_chan_styles) ? $_rx_chan_styles : null, $forceInline);
    }

    public static function back(array $textbotlang, ?array $styles, $forceInline = null)
    {
        $styles ??= ['__rxplain__' => 1];
        $rows = [
            [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'adm_hub_main'], 'ch_back', $styles),
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu'], 'ch_backmenu', $styles)
            ]
        ];
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode($rows, $forceInline);
    }

    public static function features(array $textbotlang, ?array $styles)
    {
        return \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_encode([
                [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "⚙️ مشاهده اطلاعات اکانت", 'callback_data' => 'feat_info'], 'feat_info', $styles)],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "🧪 اکانت تست", 'callback_data' => 'feat_test'], 'feat_test', $styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📚 قابلیت آموزش", 'callback_data' => 'feat_help'], 'feat_help', $styles)
                ],
                [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'feat_back'], 'feat_back', $styles),
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'feat_backmenu'], 'feat_backmenu', $styles)
                ]
            ]);
    }
}
