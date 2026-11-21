<?php

namespace Faoxima\Admin\Keyboard;

use Faoxima\Telegram\Keyboard\LayoutFunctions;

final class ShopMenus
{
    public static function help(array $textbotlang, ?array $styles): string
    {
        return LayoutFunctions::rx_kb_encode([
        [
            LayoutFunctions::rx_kb_style(['text' => "📚 افزودن آموزش", 'callback_data' => 'help_add'], 'help_add', $styles),
            LayoutFunctions::rx_kb_style(['text' => "❌ حذف آموزش", 'callback_data' => 'help_del'], 'help_del', $styles)
        ],
        [LayoutFunctions::rx_kb_style(['text' => "✏️ ویرایش آموزش", 'callback_data' => 'help_edit'], 'help_edit', $styles),
         LayoutFunctions::rx_kb_style(['text' => "📥 دریافت پیش‌فرض‌ها", 'callback_data' => 'help_load_default'], 'help_load_default', $styles)],
        [
            LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'help_back'], 'help_back', $styles),
            LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'help_backmenu'], 'help_backmenu', $styles)
        ]]);
    }

    public static function shop(array $textbotlang, ?array $styles): string
    {
        return LayoutFunctions::rx_kb_encode([
        [
            LayoutFunctions::rx_kb_style(['text' => "🗂 مدیریت دسته‌بندی", 'callback_data' => 'shop_category'], 'shop_category', $styles),
            LayoutFunctions::rx_kb_style(['text' => "🛍 مدیریت محصولات", 'callback_data' => 'shop_products'], 'shop_products', $styles)
        ],
        [
            LayoutFunctions::rx_kb_style(['text' => "🎁 ساخت کد هدیه", 'callback_data' => 'shop_giftadd'], 'shop_giftadd', $styles),
            LayoutFunctions::rx_kb_style(['text' => "❌ حذف کد هدیه", 'callback_data' => 'shop_giftdel'], 'shop_giftdel', $styles)
        ],
        [
            LayoutFunctions::rx_kb_style(['text' => "🎁 ساخت کد تخفیف", 'callback_data' => 'shop_discountadd'], 'shop_discountadd', $styles),
            LayoutFunctions::rx_kb_style(['text' => "❌ حذف کد تخفیف", 'callback_data' => 'shop_discountdel'], 'shop_discountdel', $styles)
        ],
        [
            LayoutFunctions::rx_kb_style(['text' => "⬇️ کف خرید عمده", 'callback_data' => 'shop_minbulk'], 'shop_minbulk', $styles),
            LayoutFunctions::rx_kb_style(['text' => "🎁 کش بک تمدید", 'callback_data' => 'shop_renewcb'], 'shop_renewcb', $styles)
        ],
        [
            LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'shop_backadmin'], 'shop_backadmin', $styles),
            LayoutFunctions::rx_kb_style(['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'shop_backmenu'], 'shop_backmenu', $styles)
        ]]);
    }

    public static function categories(?array $styles): string
    {
        return LayoutFunctions::rx_kb_encode([
        [
            LayoutFunctions::rx_kb_style(['text' => "🛒 افزودن دسته‌بندی", 'callback_data' => 'cat_add'], 'cat_add', $styles),
            LayoutFunctions::rx_kb_style(['text' => "❌ حذف دسته بندی", 'callback_data' => 'cat_del'], 'cat_del', $styles)
        ],
        [LayoutFunctions::rx_kb_style(['text' => "✏️ ویرایش دسته بندی", 'callback_data' => 'cat_edit'], 'cat_edit', $styles)],
        [LayoutFunctions::rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'cat_back'], 'cat_back', $styles)]]);
    }

    public static function products(?array $styles): string
    {
        return LayoutFunctions::rx_kb_encode([
        [
            LayoutFunctions::rx_kb_style(['text' => "🛍 افزودن محصول", 'callback_data' => 'shopitem_add'], 'shopitem_add', $styles),
            LayoutFunctions::rx_kb_style(['text' => "❌ حذف محصول", 'callback_data' => 'shopitem_del'], 'shopitem_del', $styles)
        ],
        [LayoutFunctions::rx_kb_style(['text' => "✏️ ویرایش محصول", 'callback_data' => 'shopitem_edit'], 'shopitem_edit', $styles)],
        [
            LayoutFunctions::rx_kb_style(['text' => "⬆️ افزایش قیمت", 'callback_data' => 'shopitem_priceinc'], 'shopitem_priceinc', $styles),
            LayoutFunctions::rx_kb_style(['text' => "⬇️ کاهش گروهی قیمت", 'callback_data' => 'shopitem_pricedec'], 'shopitem_pricedec', $styles)
        ],
        [LayoutFunctions::rx_kb_style(['text' => "⬅️ بازگشت به منوی فروشگاه", 'callback_data' => 'shopitem_back'], 'shopitem_back', $styles)]]);
    }
}
