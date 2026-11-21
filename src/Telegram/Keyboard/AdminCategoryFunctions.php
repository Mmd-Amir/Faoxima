<?php

namespace Faoxima\Telegram\Keyboard;

final class AdminCategoryFunctions
{
    public static function KeyboardCategoryadmin()
{
    global $pdo, $textbotlang;
    return \Faoxima\Admin\Keyboard\CategoryKeyboard::build(
        $pdo,
        $textbotlang,
        method_exists(\Faoxima\Telegram\Keyboard\LayoutFunctions::class, 'rx_keyboardToInline') ? 'rx_keyboardToInline' : null,
        method_exists(\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb') ? 'rx_finalizeInlineAdminKb' : null,
    );
}
}
