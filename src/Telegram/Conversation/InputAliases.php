<?php

namespace Faoxima\Telegram\Conversation;

final class InputAliases
{
    public static function apply(
        &$datain,
        &$text,
        $inlineMode,
        $from_id,
        $callback_query_id,
        $keyboard,
        ?callable $resolveInline,
        ?callable $restoreReply,
        ?callable $stripStyle,
        ?callable $resolveAdmin,
        ?callable $telegram,
        callable $send,
    ): bool {
        if ($resolveInline !== null && isset($datain) && is_string($datain) && strpos($datain, 'rxb_') === 0) {
            $rxResolvedInlineText = $resolveInline($datain);
            if ($rxResolvedInlineText !== null && $rxResolvedInlineText !== '') {
                $datain = $rxResolvedInlineText;
                $text = $rxResolvedInlineText;
            } else {
                if (!empty($callback_query_id) && $telegram !== null) {
                    @$telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => 'این دکمه دیگر معتبر نیست؛ لطفاً از منوی اصلی دوباره انتخاب کنید.',
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                }
                $send($from_id, 'این دکمه دیگر معتبر نیست. از منوی اصلی گزینه مورد نظر را انتخاب کنید.', $keyboard, 'html');
                return false;
            }
        }

        if (
            $restoreReply !== null
            && $inlineMode !== null && $inlineMode !== 'oninline'
            && is_string($text) && $text !== ''
            && empty($datain)
        ) {
            $text = $restoreReply($text);
        }

        if (
            $stripStyle !== null
            && is_string($text) && $text !== ''
            && empty($datain)
        ) {
            $text = $stripStyle($text);
        }

        if (
            is_string($datain) && $datain !== ''
            && $resolveAdmin !== null
            && (strpos($datain, 'apn:') === 0 || strpos($datain, 'apnh:') === 0)
        ) {
            $rxResolvedText = $resolveAdmin($datain);
            if ($rxResolvedText !== null && $rxResolvedText !== '') {

                if (!empty($callback_query_id) && $telegram !== null) {
                    @$telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
                }
                $text  = $rxResolvedText;
                $datain = '';
            } else {
                if (!empty($callback_query_id) && $telegram !== null) {
                    @$telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text' => 'این دکمه دیگر معتبر نیست؛ لطفاً منوی مدیریت را دوباره باز کنید.',
                        'show_alert' => true,
                        'cache_time' => 0,
                    ]);
                }
                return false;
            }
        }

        return true;
    }
}
