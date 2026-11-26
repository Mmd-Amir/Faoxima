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
        [LayoutFunctions::class, 'rx_keyboardToInline'],
        [\Faoxima\Admin\Keyboard\ProviderMenuRenderingHelpers::class, 'rx_finalizeInlineAdminKb'],
    );
}
}
