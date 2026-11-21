<?php

namespace Faoxima\Services\Renewal;

use Throwable;

final class RenewalText
{
    public static function rxRenewalTextDefaults(): array
    {
        return [
            'policy_carry_all' => 'حفظ حجم و زمان باقی‌مانده + افزودن حجم و زمان خریداری‌شده',
            'policy_reset_all' => 'ریست کامل حجم و زمان؛ فقط حجم خریداری‌شده و زمان خریداری‌شده از لحظه اجرا',
            'policy_reset_traffic_append_time' => 'ریست حجم (فقط حجم خریداری‌شده) + حفظ زمان باقی‌مانده و افزودن زمان خریداری‌شده',
            'policy_carry_traffic_reset_time' => 'حفظ حجم باقی‌مانده + افزودن حجم خریداری‌شده؛ زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'policy_traffic_only' => 'فقط حجم: حجم باقی‌مانده + حجم خریداری‌شده؛ تاریخ انقضا تغییر نمی‌کند',
            'policy_time_only' => 'فقط زمان: زمان باقی‌مانده + زمان خریداری‌شده؛ حجم و مصرف تغییر نمی‌کند',
            'policy_reset_traffic_keep_time' => 'ریست حجم (فقط حجم خریداری‌شده)؛ تاریخ انقضا تغییر نمی‌کند',
            'policy_keep_traffic_reset_time' => 'حجم و مصرف بدون تغییر؛ زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'policy_short_reset_traffic_keep_time' => 'ریست حجم، انقضا بدون تغییر',
            'policy_short_keep_traffic_reset_time' => 'حجم بدون تغییر، ریست زمان',
            'policy_short_carry_all' => 'حفظ حجم و زمان',
            'policy_short_reset_all' => 'ریست کامل حجم و زمان',
            'policy_short_reset_traffic_append_time' => 'ریست حجم، حفظ زمان',
            'policy_short_carry_traffic_reset_time' => 'حفظ حجم، ریست زمان',
            'policy_short_traffic_only' => 'فقط افزایش حجم',
            'policy_short_time_only' => 'فقط افزایش زمان',
            'policy_usage_reset_suffix' => ' (شمارنده مصرف صفر می‌شود)',
            'trigger_either' => 'اتمام حجم یا انقضای زمان (هرکدام زودتر)',
            'trigger_traffic' => 'فقط اتمام حجم',
            'trigger_expire' => 'فقط انقضای زمان',
            'trigger_date' => 'در تاریخ مشخص',
            'trigger_date_at' => 'در تاریخ {date}',
            'trigger_immediate' => 'اجرای فوری (بدون رزرو)',
            'trigger_unavailable_traffic' => 'حجم این سرویس نامحدود است و هرگز تمام نمی‌شود.',
            'trigger_unavailable_expire' => 'زمان این سرویس نامحدود است و هرگز منقضی نمی‌شود.',
            'trigger_unavailable_either' => 'حجم و زمان این سرویس هر دو نامحدود است؛ فقط فعال‌سازی در تاریخ مشخص ممکن است.',
            'reason_admin_blocked' => '⛔️ این سرویس توسط مدیریت غیرفعال شده است و تمدید، این محدودیت را برطرف نمی‌کند. برای رفع محدودیت با پشتیبانی در ارتباط باشید؛ مبلغی کسر نشد.',
            'reason_pending_unsupported' => '⚠️ این سرویس هنوز در انتظار اولین اتصال است و این پنل امکان اعمال روش تمدید انتخاب‌شده روی مدت انتظار را ندارد. لطفاً ابتدا به سرویس متصل شوید و سپس تمدید کنید؛ مبلغی کسر نشد.',
            'reason_result_not_usable' => '⚠️ با این روش، سرویس پس از اجرا همچنان قابل استفاده نخواهد بود ({detail}). روش یا محصول دیگری انتخاب کنید؛ مبلغی کسر نشد.',
            'reason_result_detail_traffic' => 'حجم قابل استفاده باقی نمی‌ماند',
            'reason_result_detail_time' => 'سرویس منقضی باقی می‌ماند',
            'reason_invalid_purchase' => '❌ مقدار حجم یا زمان خریداری‌شده نامعتبر است.',
            'reason_unknown_method' => '❌ روش تمدید تنظیم‌شده برای این پنل نامعتبر است؛ لطفاً به مدیریت اطلاع دهید. مبلغی کسر نشد.',
            'reason_panel_unreachable' => '⚠️ اطلاعات فعلی سرویس از پنل دریافت نشد؛ پیش‌نمایش در دسترس نیست. هنگام تأیید، وضعیت سرویس دوباره از پنل خوانده می‌شود.',
            'reason_panel_state_unavailable' => '⚠️ وضعیت فعلی سرویس از پنل قابل خواندن نیست؛ لطفاً چند دقیقه بعد دوباره تلاش کنید. مبلغی کسر نشد.',
            'reason_user_missing' => '❌ این سرویس در پنل یافت نشد؛ لطفاً با پشتیبانی در ارتباط باشید.',
            'reason_renewal_in_progress' => '⏳ عملیات دیگری روی این سرویس در حال انجام است؛ چند لحظه بعد دوباره تلاش کنید. مبلغی کسر نشد.',
            'reason_queued_exists' => '❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.',
            'reason_trigger_unavailable' => '❌ زمان فعال‌سازی انتخاب‌شده برای این سرویس در دسترس نیست: {detail}',
            'reason_trigger_date_required' => '❌ برای فعال‌سازی در تاریخ مشخص، ابتدا تاریخ فعال‌سازی را تعیین کنید.',
            'reason_trigger_date_invalid' => '❌ تاریخ فعال‌سازی نامعتبر است؛ تاریخ باید در آینده و حداکثر تا یک سال بعد باشد.',
            'reason_policy_invalid' => '❌ سیاست اجرای انتخاب‌شده نامعتبر است.',
            'reason_panel_policy_unsupported' => '⚠️ این روش تمدید با محدودیت‌های پنل JovadPanel برای وضعیت فعلی این سرویس هم‌خوان نیست (مثلاً کاهش حجم سرویس فعال یا حفظ مصرف یک سرویس تمام‌شده). روش یا محصول دیگری انتخاب کنید؛ مبلغی کسر نشد.',
            'reason_reservation_unsupported' => '❌ رزرو اشتراک برای این نوع سرویس پشتیبانی نمی‌شود.',
            'reason_generic' => '❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید',
            'pending_verification' => '⏳ درخواست تمدید به پنل ارسال شد اما نتیجه نهایی هنوز تأیید نشده است. وضعیت به‌صورت خودکار بررسی می‌شود و نیازی به پرداخت مجدد نیست؛ در صورت بروز مشکل، پشتیبانی پیگیری می‌کند.',
            'preview_title' => '🔎 پیش‌نمایش تمدید',
            'preview_policy' => '⚙️ روش تمدید : {label}',
            'preview_traffic_kept' => '🔋 حجم باقی‌مانده فعلی حفظ می‌شود',
            'preview_traffic_discarded' => '🗑 حجم باقی‌مانده فعلی حذف می‌شود و فقط حجم خریداری‌شده باقی می‌ماند',
            'preview_traffic_unchanged' => '📦 حجم کل و مصرف بدون تغییر می‌ماند',
            'preview_traffic_unlimited' => '♾ حجم سرویس نامحدود می‌ماند',
            'preview_time_kept' => '⏳ زمان باقی‌مانده فعلی حفظ می‌شود',
            'preview_time_discarded' => '🗑 زمان باقی‌مانده فعلی حذف می‌شود و زمان خریداری‌شده از لحظه اجرا شروع می‌شود',
            'preview_time_unchanged' => '📅 تاریخ انقضا بدون تغییر می‌ماند',
            'preview_time_unlimited' => '♾ زمان سرویس نامحدود می‌ماند',
            'preview_usage_reset' => '♻️ شمارنده مصرف : صفر می‌شود',
            'preview_usage_kept' => '♻️ شمارنده مصرف : حفظ می‌شود',
            'preview_usage_note' => 'ℹ️ صفر شدن شمارنده مصرف به معنی حذف حجم باقی‌مانده نیست؛ حجم باقی‌مانده به حجم کل جدید منتقل می‌شود.',
            'preview_current_expire' => '⏳ انقضای فعلی : {value}',
            'preview_new_expire' => '⏳ انقضای جدید : {value}',
            'preview_current_limit' => '📦 حجم کل فعلی : {value}',
            'preview_used' => '📉 حجم مصرف‌شده : {value}',
            'preview_current_remaining' => '🔋 حجم باقی‌مانده فعلی : {value}',
            'preview_purchased_time' => '➕ زمان خریداری‌شده : {value}',
            'preview_purchased_volume' => '➕ حجم خریداری‌شده : {value}',
            'preview_new_limit' => '📦 حجم کل جدید : {value}',
            'preview_new_remaining' => '🔋 حجم قابل استفاده جدید : {value}',
            'preview_pending' => 'ℹ️ سرویس در انتظار اولین اتصال است؛ مدت سرویس پس از اولین اتصال شروع می‌شود ({value}).',
            'preview_enable' => '✅ سرویس منقضی/تمام‌شده پس از تمدید دوباره فعال می‌شود',
            'reservation_title' => '📌 این خرید به‌صورت رزرو ثبت می‌شود و سرویس فعلی تا زمان فعال‌سازی تغییر نمی‌کند.',
            'reservation_trigger' => '⏱ زمان فعال‌سازی : {label}',
            'reservation_policy' => '⚙️ سیاست اجرا : {label}',
            'reservation_note' => 'ℹ️ حجم و زمان باقی‌مانده در لحظه فعال‌سازی محاسبه می‌شود؛ مصرف شما تا آن لحظه از بسته رزرو کم نمی‌شود.',
            'reservation_estimate' => '🔎 برآورد در صورت فعال‌سازی در همین لحظه:',
            'reservation_estimate_unavailable' => 'ℹ️ برآورد لحظه‌ای در دسترس نیست؛ مقادیر در زمان فعال‌سازی محاسبه می‌شود.',
            'mark_on' => '✅',
            'mark_off' => '⬜️',
            'button_outdated' => '⚠️ این دکمه منقضی شده است؛ لطفاً مراحل تمدید را دوباره شروع کنید.',
            'admin_title' => '🗓 تنظیمات رزرو اشتراک پنل {panel}',
            'admin_section_policy' => '⚙️ سیاست تمدید (یک گزینه)',
            'admin_section_conditions' => '⏱ شرایط فعال‌سازی (چند گزینه؛ هرکدام زودتر)',
            'admin_btn_date' => '📅 تغییر تاریخ',
            'admin_summary' => '📌 تنظیم فعلی: {text}',
            'admin_timezone_note' => '🕒 تاریخ‌ها به وقت {tz} ثبت و نمایش داده می‌شوند.',
            'admin_conditions_empty' => '❌ حداقل یک شرط فعال‌سازی را انتخاب کنید.',
            'admin_date_required' => '❌ برای شرط «تاریخ مشخص» ابتدا تاریخ را تعیین کنید.',
            'admin_date_past' => '❌ تاریخ انتخاب‌شده گذشته است؛ یک تاریخ آینده تعیین کنید.',
            'admin_date_invalid' => '❌ تاریخ نامعتبر است. تاریخ و ساعت را به شکل 1405/08/01 12:00 (شمسی) یا 2026-10-23 12:00 و حداکثر تا یک سال آینده ارسال کنید.',
            'admin_date_prompt' => '📅 تاریخ و ساعت فعال‌سازی را به وقت {tz} ارسال کنید؛ مثال: 1405/08/01 12:00 (شمسی) یا 2026-10-23 12:00. ساعت اختیاری است (پیش‌فرض 00:00).',
            'admin_date_saved' => '✅ تاریخ فعال‌سازی تعیین شد: {date}',
            'cond_traffic' => 'اتمام حجم',
            'cond_expire' => 'پایان زمان',
            'cond_date' => 'تاریخ مشخص',
            'cond_date_at' => 'تاریخ مشخص ({date})',
            'activation_phrase_traffic' => 'پایان حجم',
            'activation_phrase_expire' => 'پایان زمان',
            'activation_phrase_traffic_expire' => 'پایان حجم یا زمان سرویس فعلی',
            'activation_phrase_date' => 'رسیدن تاریخ {date}',
            'activation_single_traffic' => 'پایان حجم سرویس فعلی',
            'activation_single_expire' => 'پایان زمان سرویس فعلی',
            'activation_single' => 'پس از {phrase} فعال می‌شود',
            'activation_multi' => 'پس از {phrase}، هرکدام زودتر باشد، فعال می‌شود',
            'activation_joiner' => ' یا ',
            'reservation_confirmed' => '✅ اشتراک شما رزرو شد',
            'reason_trigger_date_expired' => '❌ تاریخ فعال‌سازی رزرو که مدیریت تعیین کرده گذشته است و رزرو جدید با این تاریخ ثبت نمی‌شود؛ لطفاً با پشتیبانی در ارتباط باشید. مبلغی کسر نشد.',
            'admin_hint' => 'یک سیاست تمدید و یک یا چند شرط فعال‌سازی انتخاب کنید؛ رزرو با برقراری هرکدام از شرط‌ها (هرکدام زودتر) فعال می‌شود. سپس «ذخیره تنظیمات» را بزنید. این تنظیمات برای همه رزروهای جدید این پنل اعمال می‌شود و مشتری آن را تغییر نمی‌دهد.',
            'admin_btn_save' => '💾 ذخیره تنظیمات',
            'admin_btn_back' => '🔙 بازگشت',
            'admin_saved' => '✅ روش تمدید «رزرو اشتراک» و تنظیمات آن ذخیره شد.',
            'admin_cancelled' => 'تنظیمات رزرو ذخیره نشد.',
            'admin_pending_unsupported' => 'ℹ️ این نوع پنل تغییر مدت سرویس‌های «در انتظار اولین اتصال» را پشتیبانی نمی‌کند؛ رزرو چنین سرویسی با سیاست‌های دارای زمان تا اولین اتصال قابل اجرا نیست و به پشتیبانی ارجاع می‌شود.',
            'admin_outdated' => '⚠️ این دکمه منقضی شده است؛ لطفاً دوباره از منوی پنل وارد شوید.',
            'reservation_date_unset' => 'تعیین نشده',
            'status_pending' => 'در انتظار فعال‌سازی',
            'status_processing' => 'در حال اجرا',
            'status_retry' => 'خطای موقت؛ تلاش مجدد خودکار',
            'status_reconciling' => 'در حال بررسی نتیجه',
            'status_applied' => 'فعال شد',
            'status_failed' => 'نیازمند بررسی پشتیبانی',
            'status_cancelled' => 'لغو شده',
            'reservation_notice' => '📌 رزرو اشتراک: {status}\n⏱ زمان فعال‌سازی: {trigger}\n⚙️ سیاست اجرا: {policy}',
            'reservation_failed_user_tpl' => "⚠️ فعال‌سازی رزرو اشتراک سرویس {username} با مشکل مواجه شد و برای بررسی به پشتیبانی ارسال شد. بسته رزرو شما محفوظ است.",
            'reconciled_user_tpl' => '✅ تمدید سرویس {username} در پنل تأیید و اعمال شد.',
            'success_same_link' => "🔗 لینک اشتراک شما همان لینک قبلی است و نیازی به دریافت کد یا کانفیگ جدید ندارید.\nبرای دریافت اطلاعات و کانفیگ‌های به‌روز، در برنامهٔ اتصال خود یک بار «به‌روزرسانی اشتراک» را بزنید.",
            'result_line_expire' => '⏳ انقضا : {before} ← {after}',
            'result_line_limit' => '📦 حجم کل : {before} ← {after}',
            'result_line_remaining' => '🔋 حجم باقی‌مانده : {before} ← {after}',
            'adm_title' => '🔄 تمدید سرویس توسط مدیر',
            'adm_service_lines' => "👤 نام کاربری : <code>{username}</code>\n📍 پنل : {panel}",
            'adm_state_unchanged' => 'بدون تغییر',
            'adm_state_unlimited' => 'نامحدود (صفر)',
            'adm_state_unset' => 'انتخاب نشده',
            'adm_package_product' => '«{name}» ({volume} / {days})',
            'adm_package_custom' => 'حجم : {volume} | زمان : {days}',
            'adm_btn_back' => '◀️ مرحله قبل',
            'adm_btn_service' => '🧾 بازگشت به مدیریت سرویس',
            'adm_btn_admin' => '🏠 منوی مدیریت',
            'adm_btn_cancel' => '❌ لغو تمدید',
            'adm_btn_product' => '🛍 انتخاب محصول',
            'adm_btn_custom' => '✍️ حجم و زمان دلخواه',
            'adm_btn_keep_traffic' => '📦 حجم بدون تغییر',
            'adm_btn_keep_time' => '📅 انقضا بدون تغییر',
            'adm_btn_continue' => '➡️ ادامه',
            'adm_btn_notify_on' => 'اطلاع‌رسانی به مشتری',
            'adm_btn_notify_off' => 'بدون اطلاع‌رسانی',
            'adm_btn_preview' => '📋 پیش‌نمایش',
            'adm_btn_refresh' => '♻️ بروزرسانی پیش‌نمایش',
            'adm_btn_confirm' => '✅ تایید و اجرا',
            'adm_src_prompt' => 'بسته تمدید را چگونه تعیین می‌کنید؟',
            'adm_prod_prompt' => '🛍 یکی از محصولات مجاز این پنل را انتخاب کنید. حجم و زمان محصول اعمال می‌شود و مبلغی از مشتری کسر نمی‌شود.',
            'adm_prod_empty' => '⚠️ محصول مجازی برای این پنل یافت نشد؛ از حجم و زمان دلخواه استفاده کنید.',
            'adm_vol_prompt' => "📦 حجم تمدید را به گیگابایت و به‌صورت عدد ارسال کنید.\n\n0 = حجم نامحدود (ارسال صفر به معنی «بدون تغییر» نیست)\n«حجم بدون تغییر» = حجم کل و مصرف فعلی دست‌نخورده می‌ماند و حجمی اضافه یا ریست نمی‌شود",
            'adm_days_prompt' => "📅 زمان تمدید را به روز و به‌صورت عدد ارسال کنید.\n\n0 = زمان نامحدود (ارسال صفر به معنی «بدون تغییر» نیست)\n«انقضا بدون تغییر» = تاریخ انقضا یا مدت انتظار اولین اتصال دست‌نخورده می‌ماند و روزی اضافه یا شروع مجدد نمی‌شود",
            'adm_current_choice' => 'انتخاب فعلی : {value}',
            'adm_invalid_number' => '❌ فقط یک عدد صحیح (صفر یا بیشتر) ارسال کنید.',
            'adm_both_unchanged' => '⚠️ حجم و انقضا نمی‌توانند هم‌زمان «بدون تغییر» باشند؛ حداقل یکی باید تغییر کند.',
            'adm_product_missing' => '⚠️ محصول انتخاب‌شده دیگر برای این پنل در دسترس نیست.',
            'adm_incomplete' => '⚠️ انتخاب‌های تمدید کامل نیست.',
            'adm_method_prompt' => '⚙️ روش تمدید را برای همین عملیات انتخاب کنید (تنظیم روش تمدید پنل تغییر نمی‌کند):',
            'adm_method_override_note' => 'ℹ️ گزینه «بدون تغییر» همان بُعد (حجم یا زمان) را در روش انتخابی بازنویسی می‌کند و مقدار فعلی آن بدون تغییر می‌ماند.',
            'adm_method_required' => 'ابتدا یک روش تمدید انتخاب کنید.',
            'adm_opt_prompt' => '🔔 مشخص کنید مشتری از تمدید مطلع شود یا نه (تمدید همیشه فوری اجرا می‌شود):',
            'adm_preview_title' => '🔎 پیش‌نمایش تمدید توسط مدیر',
            'adm_preview_package' => '🛍 بسته : {value}',
            'adm_preview_method' => '⚙️ روش تمدید : ✅ {label}',
            'adm_preview_effective' => '↳ روش اعمال‌شده با احتساب «بدون تغییر» : {label}',
            'adm_preview_exec_now' => '🚦 اجرا : فوری',
            'adm_preview_free' => '💰 هزینه : تمدید رایگان توسط مدیر؛ مبلغی از کیف پول یا حساب مشتری کسر نمی‌شود',
            'adm_preview_notify_on' => '🔔 اطلاع‌رسانی به مشتری : بله',
            'adm_preview_notify_off' => '🔕 اطلاع‌رسانی به مشتری : خیر',
            'adm_preview_current' => '📍 وضعیت فعلی سرویس',
            'adm_preview_pending_current' => '⏳ مدت پس از اولین اتصال : {value}',
            'adm_preview_expected' => '📍 وضعیت پس از اجرا',
            'adm_preview_new_used' => '📉 مصرف پس از اجرا : {value}',
            'adm_preview_unchanged' => '🔒 بدون تغییر : {value}',
            'adm_unchanged_traffic' => 'حجم کل و مصرف',
            'adm_unchanged_time' => 'تاریخ انقضا',
            'adm_none' => 'هیچ‌کدام',
            'adm_revise_hint' => 'یک مرحله به عقب بازگردید و انتخاب‌ها را اصلاح کنید؛ چیزی اعمال نشده است.',
            'adm_state_changed' => '⚠️ وضعیت سرویس از زمان پیش‌نمایش تغییر کرده است؛ پیش‌نمایش جدید را بررسی و دوباره تایید کنید. چیزی اعمال نشد.',
            'adm_already_running' => 'این عملیات قبلاً تایید شده و در حال اجرا یا انجام‌شده است.',
            'adm_cancelled' => '❌ تمدید لغو شد و هیچ تغییری اعمال نشد.',
            'adm_error_start' => '❌ تمدید با خطا مواجه گردید؛ مراحل تمدید را مجددا انجام دهید.',
            'adm_error_manual_panel' => '⚠️ این پنل از نوع فروش دستی یا انبار ملی است و تمدید حجم/زمان با روش‌های این فرایند برای آن پشتیبانی نمی‌شود.',
            'adm_error_status' => '⚠️ وضعیت فعلی سفارش ({status}) اجازه تمدید نمی‌دهد.',
            'adm_failed_generic' => '❌ خطایی در تمدید سرویس رخ داد و چیزی اعمال‌شده تایید نشد؛ لاگ‌ها را بررسی کنید.',
            'adm_failed_stock' => '❌ موجودی انبار فروش دستی برای این محصول تمام شده است.',
            'adm_admin_done' => '✅ تمدید سرویس انجام و در پنل تایید شد.',
            'adm_notified_yes' => '🔔 پیام اطلاع‌رسانی برای مشتری ارسال شد.',
            'adm_notified_no' => '🔕 به انتخاب شما مشتری مطلع نشد.',
            'adm_finalize_pending' => '⏳ ثبت نهایی در فاکتور و تاریخچه به‌صورت خودکار تکمیل می‌شود؛ تمدید در پنل دوباره ارسال نخواهد شد.',
            'adm_notified_later' => '🔔 پیام اطلاع‌رسانی پس از ثبت نهایی برای مشتری ارسال می‌شود.',
            'adm_notified_failed' => '⚠️ ارسال پیام به مشتری ممکن نشد (مثلاً ربات را مسدود کرده است) و دیگر تلاش نمی‌شود؛ تمدید و ثبت فاکتور تکمیل شده است.',
            'adm_notified_retrying' => '⏳ ارسال پیام به مشتری فعلاً انجام نشد و به‌صورت خودکار و محدود دوباره تلاش می‌شود؛ تمدید و ثبت فاکتور تکمیل شده است. ممکن است در موارد نادر پیام تکراری برسد.',
            'adm_customer_done' => '✅ سرویس {username} توسط مدیریت تمدید شد.',
            'adm_customer_activated' => '✅ رزرو تمدید سرویس {username} فعال شد و سرویس شما طبق رزرو تمدید گردید.',
            'adm_report_done' => '⭕️ ادمین سرویس کاربر را تمدید کرد.',
            'unlimited' => 'نامحدود',
            'days_unit' => 'روز',
            'gb_unit' => 'گیگ',
        ];
    }

    public static function rxRenewalText(string $key, array $vars = []): string
    {
        $defaults = self::rxRenewalTextDefaults();
        $default = (string) ($defaults[$key] ?? $key);
        $text = $default;
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_textbot_get')) {
            try {
                $text = (string) \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_renewal_' . $key, $default);
            } catch (Throwable $e) {
                $text = $default;
            }
        }
        $text = str_replace('\n', "\n", $text);
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    public static function rxRenewalAdminMethodLabel(string $basePolicy): ?string
    {
        foreach (\Faoxima\Services\Renewal\Plans::renewalModes() as $label => $mode) {
            if (empty($mode['queued']) && ($mode['key'] ?? '') === $basePolicy) {
                return (string) $label;
            }
        }
        return null;
    }

    public static function rxRenewalPolicyLabel(string $policy, bool $resetUsage = false, bool $short = false): string
    {
        $label = self::rxRenewalText(($short ? 'policy_short_' : 'policy_') . $policy);
        if ($resetUsage && \Faoxima\Services\Renewal\Plans::renewalPolicyAllowsUsageReset($policy)) {
            $label .= self::rxRenewalText('policy_usage_reset_suffix');
        }
        return $label;
    }

    public static function rxRenewalModeLabel(string $key): string
    {
        foreach (\Faoxima\Services\Renewal\Plans::renewalModes() as $mode) {
            if ($mode['key'] === $key && $mode['policy'] !== null) {
                return self::rxRenewalPolicyLabel($mode['policy'], !empty($mode['reset_usage']));
            }
        }
        return isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[$key]) ? self::rxRenewalPolicyLabel($key) : $key;
    }

    public static function rxRenewalFormatExpire($timestamp): string
    {
        $timestamp = \Faoxima\Payments\Fulfillment\Claims::int($timestamp);
        if ($timestamp <= 0) {
            return self::rxRenewalText('unlimited');
        }
        return function_exists('jdate') ? (string) jdate('Y/m/d H:i', $timestamp) : date('Y/m/d H:i', $timestamp);
    }

    public static function rxRenewalFormatBytes($bytes, bool $zeroIsUnlimited = true): string
    {
        if ($bytes === null) {
            return self::rxRenewalText('unlimited');
        }
        $bytes = \Faoxima\Payments\Fulfillment\Claims::int($bytes);
        if ($bytes <= 0 && $zeroIsUnlimited) {
            return self::rxRenewalText('unlimited');
        }
        $gb = max(0, $bytes) / RX_PF_GIB;
        return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.') . ' ' . self::rxRenewalText('gb_unit');
    }

    public static function rxRenewalFormatDays($days): string
    {
        $days = (float) $days;
        if ($days <= 0) {
            return self::rxRenewalText('unlimited');
        }
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.') . ' ' . self::rxRenewalText('days_unit');
    }

    public static function rxRenewalReasonText(string $reason, array $plan = []): string
    {
        switch ($reason) {
            case 'admin_blocked':
                return self::rxRenewalText('reason_admin_blocked');
            case 'pending_unsupported':
                return self::rxRenewalText('reason_pending_unsupported');
            case 'result_not_usable':
                return self::rxRenewalText('reason_result_not_usable', ['detail' => self::rxRenewalText(($plan['unusable'] ?? '') === 'traffic' ? 'reason_result_detail_traffic' : 'reason_result_detail_time')]);
            case 'invalid_purchase':
                return self::rxRenewalText('reason_invalid_purchase');
            case 'unknown_renewal_method':
            case 'unsupported_renewal_method':
            case 'unknown_policy':
                return self::rxRenewalText('reason_unknown_method');
            case 'panel_state_unavailable':
            case 'panel_read_failed':
                return self::rxRenewalText('reason_panel_state_unavailable');
            case 'user_missing':
                return self::rxRenewalText('reason_user_missing');
            case 'renewal_in_progress':
                return self::rxRenewalText('reason_renewal_in_progress');
            case 'queued_renewal_exists':
                return self::rxRenewalText('reason_queued_exists');
            case 'trigger_unavailable':
                return self::rxRenewalText('reason_trigger_unavailable', ['detail' => (string) ($plan['detail'] ?? '')]);
            case 'trigger_date_required':
                return self::rxRenewalText('reason_trigger_date_required');
            case 'trigger_date_invalid':
                return self::rxRenewalText('reason_trigger_date_invalid');
            case 'trigger_date_expired':
                return self::rxRenewalText('reason_trigger_date_expired');
            case 'policy_invalid':
                return self::rxRenewalText('reason_policy_invalid');
            case 'panel_policy_unsupported':
                return self::rxRenewalText('reason_panel_policy_unsupported');
            case 'panel_credit_suspended':
                return '⚠️ سرویس به‌دلیل اعتبار فروشنده متوقف است. پس از رفع مشکل اعتبار دوباره تلاش کنید؛ مبلغی کسر نشد.';
            case 'reservation_unsupported':
                return self::rxRenewalText('reason_reservation_unsupported');
        }
        return self::rxRenewalText('reason_generic');
    }

    public static function rxRenewalFailureText($extend, string $default = ''): string
    {
        $code = is_array($extend) ? (string) ($extend['code'] ?? '') : '';
        if (\Faoxima\Services\Renewal\ProviderRenewal::renewalFailureKnown($extend)) {
            return self::rxRenewalReasonText($code, is_array($extend['plan'] ?? null) ? $extend['plan'] : (array) $extend);
        }
        return $default !== '' ? $default : self::rxRenewalText('reason_generic');
    }

    public static function rxRenewalIsPending($extend): bool
    {
        return \Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend);
    }

    public static function rxRenewalPlanLines(array $plan): array
    {
        $lines = [];
        $lines[] = self::rxRenewalText('preview_policy', ['label' => (string) ($plan['label'] ?? '')]);
        $trafficMode = (string) ($plan['traffic_mode'] ?? '');
        $timeMode = (string) ($plan['time_mode'] ?? '');
        if ($trafficMode === 'keep') {
            $lines[] = self::rxRenewalText('preview_traffic_unchanged');
        } elseif (\Faoxima\Payments\Fulfillment\Claims::int($plan['data_limit'] ?? 0) <= 0) {
            $lines[] = self::rxRenewalText('preview_traffic_unlimited');
        } else {
            $lines[] = self::rxRenewalText($trafficMode === 'reset' ? 'preview_traffic_discarded' : 'preview_traffic_kept');
        }
        if ($timeMode === 'keep') {
            $lines[] = self::rxRenewalText('preview_time_unchanged');
        } elseif (empty($plan['target_pending']) && \Faoxima\Payments\Fulfillment\Claims::int($plan['expire'] ?? 0) <= 0) {
            $lines[] = self::rxRenewalText('preview_time_unlimited');
        } else {
            $lines[] = self::rxRenewalText($timeMode === 'reset' ? 'preview_time_discarded' : 'preview_time_kept');
        }
        $lines[] = self::rxRenewalText(!empty($plan['reset_usage']) ? 'preview_usage_reset' : 'preview_usage_kept');
        if (!empty($plan['reset_usage']) && $trafficMode === 'carry') {
            $lines[] = self::rxRenewalText('preview_usage_note');
        }
        $lines[] = self::rxRenewalText('preview_current_expire', ['value' => !empty($plan['before_pending']) ? self::rxRenewalFormatDays(((int) ($plan['before_pending_duration'] ?? 0)) / 86400) : self::rxRenewalFormatExpire($plan['before_expire'] ?? 0)]);
        if (!empty($plan['target_pending'])) {
            $lines[] = self::rxRenewalText('preview_pending', ['value' => self::rxRenewalFormatDays(((int) ($plan['target_pending_duration'] ?? 0)) / 86400)]);
        } else {
            $lines[] = self::rxRenewalText('preview_new_expire', ['value' => self::rxRenewalFormatExpire($plan['expire'] ?? 0)]);
        }
        $lines[] = self::rxRenewalText('preview_current_limit', ['value' => self::rxRenewalFormatBytes($plan['before_data_limit'] ?? 0)]);
        $lines[] = self::rxRenewalText('preview_used', ['value' => self::rxRenewalFormatBytes($plan['before_used_traffic'] ?? 0, false)]);
        $lines[] = self::rxRenewalText('preview_current_remaining', ['value' => self::rxRenewalFormatBytes($plan['before_remaining'] ?? null, false)]);
        $lines[] = self::rxRenewalText('preview_purchased_time', ['value' => self::rxRenewalFormatDays($plan['purchased_days'] ?? 0)]);
        $lines[] = self::rxRenewalText('preview_purchased_volume', ['value' => self::rxRenewalFormatBytes($plan['purchased_volume_bytes'] ?? 0)]);
        $lines[] = self::rxRenewalText('preview_new_limit', ['value' => self::rxRenewalFormatBytes($plan['data_limit'] ?? 0)]);
        $lines[] = self::rxRenewalText('preview_new_remaining', ['value' => self::rxRenewalFormatBytes($plan['target_remaining'] ?? null)]);
        if (!empty($plan['enable'])) {
            $lines[] = self::rxRenewalText('preview_enable');
        }
        return $lines;
    }

    public static function rxRenewalPreviewText(array $plan): string
    {
        if (empty($plan['ok'])) {
            return self::rxRenewalReasonText((string) ($plan['reason'] ?? ''), $plan);
        }
        if (!empty($plan['queued'])) {
            return self::rxRenewalText('reservation_title') . "\n"
                . self::rxRenewalText('preview_current_expire', ['value' => self::rxRenewalFormatExpire($plan['before_expire'] ?? 0)]) . "\n"
                . self::rxRenewalText('preview_current_remaining', ['value' => self::rxRenewalFormatBytes($plan['before_remaining'] ?? null, false)]);
        }
        return self::rxRenewalText('preview_title') . "\n" . implode("\n", self::rxRenewalPlanLines($plan));
    }

    public static function rxRenewalResultText(array $before, array $after): string
    {
        $beforeLimit = \Faoxima\Payments\Fulfillment\Claims::int($before['data_limit'] ?? 0);
        $afterLimit = \Faoxima\Payments\Fulfillment\Claims::int($after['data_limit'] ?? 0);
        $beforeRemaining = $beforeLimit > 0 ? max(0, $beforeLimit - \Faoxima\Payments\Fulfillment\Claims::int($before['used_traffic'] ?? 0)) : null;
        $afterRemaining = $afterLimit > 0 ? max(0, $afterLimit - \Faoxima\Payments\Fulfillment\Claims::int($after['used_traffic'] ?? 0)) : null;
        return self::rxRenewalText('result_line_expire', ['before' => self::rxRenewalFormatExpire($before['expire'] ?? 0), 'after' => self::rxRenewalFormatExpire($after['expire'] ?? 0)]) . "\n"
            . self::rxRenewalText('result_line_limit', ['before' => self::rxRenewalFormatBytes($beforeLimit), 'after' => self::rxRenewalFormatBytes($afterLimit)]) . "\n"
            . self::rxRenewalText('result_line_remaining', ['before' => self::rxRenewalFormatBytes($beforeRemaining, false), 'after' => self::rxRenewalFormatBytes($afterRemaining, false)]);
    }

    public static function rxRenewalOutcomeText($extend): string
    {
        if (!is_array($extend) || !is_array($extend['rx_renewal'] ?? null)) {
            return \Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend) ? self::rxRenewalText('pending_verification') : '';
        }
        $renewal = $extend['rx_renewal'];
        if (!empty($renewal['verified']) && is_array($renewal['before'] ?? null) && is_array($renewal['after'] ?? null)) {
            return self::rxRenewalResultText($renewal['before'], $renewal['after']);
        }
        return self::rxRenewalText('pending_verification');
    }

    public static function rxRenewalSameLinkNote($extend, $panelType, $afterState = null): string
    {
        if (!\Faoxima\Services\Renewal\ProviderRenewal::renewalSameLinkSupported($panelType) || !\Faoxima\Services\Renewal\ProviderRenewal::renewalIsAppliedVerified($extend, $afterState)) {
            return '';
        }
        return trim(self::rxRenewalText('success_same_link'));
    }

    public static function rxRenewalDecorateSuccess(string $successText, $extend, string $username, string $panelName): string
    {
        if (\Faoxima\Services\Renewal\ProviderRenewal::renewalIsPending($extend)) {
            return self::rxRenewalText('pending_verification') . "\n\n▫️نام سرویس : " . $username;
        }
        if (!is_array($extend) || !is_array($extend['rx_renewal'] ?? null)) {
            return $successText;
        }
        $renewal = $extend['rx_renewal'];
        if (!empty($renewal['verified']) && is_array($renewal['before'] ?? null) && is_array($renewal['after'] ?? null)) {
            $decorated = rtrim($successText) . "\n\n" . self::rxRenewalResultText($renewal['before'], $renewal['after']);
            $sameLink = self::rxRenewalSameLinkNote($extend, \Faoxima\Services\Fulfillment\ProviderState::panelType($panelName));
            return $sameLink !== '' ? $decorated . "\n\n" . $sameLink : $decorated;
        }
        \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('result_decoration', 'unverified_result', [
            'username' => $username,
            'panel_name' => $panelName,
            'plan' => is_array($renewal['plan'] ?? null) ? $renewal['plan'] : [],
            'before' => is_array($renewal['before'] ?? null) ? $renewal['before'] : null,
            'after' => is_array($renewal['after'] ?? null) ? $renewal['after'] : null,
            'operation_id' => $renewal['operation_id'] ?? null,
            'mutation' => 'uncertain',
        ]);
        return self::rxRenewalText('pending_verification') . "\n\n▫️نام سرویس : " . $username;
    }
}
