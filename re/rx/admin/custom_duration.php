<?php

function rx_duration_admin_handle(): bool
{
    global $text, $datain, $user, $from_id, $adminrulecheck, $callback_query_id, $update, $pdo;
    $entry = $text === '⏳ قیمت زمان دلخواه';
    $callback = is_string($datain) && strpos($datain, 'cdadm:') === 0;
    $input = ($user['step'] ?? '') === 'cdadm_input' && isset($update['message']) && empty($datain);
    if (!$entry && !$callback && !$input) {
        return false;
    }
    if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
        return true;
    }
    $state = json_decode((string)($user['Processing_value_tow'] ?? ''), true);
    $panelName = nmResolvePanelNameForUser($user);
    $panel = $panelName !== '' ? select('marzban_panel', '*', 'name_panel', $panelName, 'select', ['cache' => false]) : null;
    if (!is_array($panel) || empty($panel['code_panel'])) {
        nm_adminInstantReply($from_id, '❌ پنل انتخاب نشده است.', json_encode(['inline_keyboard' => [[['text' => 'بازگشت', 'callback_data' => 'backadmin']]]]), 'HTML');
        return true;
    }
    if ($entry) {
        $state = ['panel' => (string)$panel['code_panel'], 'token' => bin2hex(random_bytes(4)), 'group' => '-', 'mode' => 'choose', 'sample' => 5];
    } elseif (!is_array($state) || (string)($state['panel'] ?? '') !== (string)$panel['code_panel']) {
        return true;
    }
    $group = $state['group'];
    $action = 'menu';
    $days = 0;
    if ($callback) {
        if (!preg_match('/^cdadm:([a-f0-9]{8}):(-|f|n|n2|all):(modes|daily|tariff|setdaily|groups|group|menu|back|add|edit|delete|enable|disable|save|apply|preview|sample)(?::([0-9]{1,5}))?$/', $datain, $match)
            || !hash_equals((string)$state['token'], $match[1])
            || ($match[3] !== 'group' && $match[2] !== $group)) {
            if (!empty($callback_query_id)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این منو منقضی شده است.', 'show_alert' => true]);
            }
            return true;
        }
        $action = $match[3];
        $days = (int)($match[4] ?? 0);
        if (!empty($callback_query_id)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
        }
        if ($action === 'group') {
            if (!in_array($match[2], ['f', 'n', 'n2', 'all'], true)) {
                return true;
            }
            $group = $state['group'] = $match[2];
            $action = 'menu';
        }
    }
    $button = static function (string $label, string $action, int $days = 0, ?string $target = null) use (&$state, &$group): array {
        return ['text' => $label, 'callback_data' => 'cdadm:' . $state['token'] . ':' . ($target ?? $group) . ':' . $action . ($days ? ':' . $days : '')];
    };
    $rows = [];
    $message = '';
    $groups = $group === 'all' ? ['f', 'n', 'n2'] : [$group];
    $map = json_decode((string)($panel['custom_duration_pricing'] ?? ''), true) ?: [];
    if ($action === 'back') {
        step('PanelMenu', $from_id);
        update('user', 'Processing_value_tow', '', 'id', $from_id);
        outtypepanel($panel['type'], 'مدیریت پنل');
        return true;
    }
    if ($action === 'groups') {
        $group = $state['group'] = '-';
    }
    if (in_array($action, ['modes', 'daily', 'tariff'], true)) {
        $state['mode'] = $action === 'modes' ? 'choose' : $action;
        unset($state['pending'], $state['input']);
        $action = 'menu';
    }
    $mode = $state['mode'] ?? 'tariff';
    if ($mode === 'choose') {
        $message = "⏳ روش قیمت‌گذاری سرویس دلخواه را انتخاب کنید.\n\nروش روزانه: حجم × قیمت پایه هر گیگ + تعداد روز × قیمت روزانه\nروش افزایش بر اساس مدت: حجم × (قیمت پایه هر گیگ + افزایش هر گیگ برای مدت انتخاب‌شده)";
        $rows[] = [$button('📅 روش روزانه (قبلی)', 'daily')];
        $rows[] = [$button('🗓 افزایش قیمت هر گیگ بر اساس مدت', 'tariff')];
        $rows[] = [$button('🔙 مدیریت پنل', 'back')];
    } elseif ($group === '-') {
        $message = 'گروه کاربری را برای ' . ($mode === 'daily' ? 'روش روزانه' : 'افزایش قیمت هر گیگ بر اساس مدت') . ' انتخاب کنید.';
        foreach (['f' => 'کاربر عادی (f)', 'n' => 'نماینده (n)', 'n2' => 'نماینده (n2)', 'all' => 'همه گروه‌ها'] as $key => $label) {
            $rows[] = [$button($label, 'group', 0, $key)];
        }
        $rows[] = [$button('🔙 انتخاب روش محاسبه', 'modes')];
    } else {
        if (!in_array($group, ['f', 'n', 'n2', 'all'], true)) {
            return true;
        }
        if ($input) {
            $value = fx_normalize_amount($text);
            $kind = $state['input'] ?? '';
            if ($value === null || (in_array($kind, ['daily', 'price'], true) ? $value < 0 : $value <= 0) || $value != floor($value) || $value > 1000000000 || ($kind === 'days' && $value > 36500)) {
                $message = in_array($kind, ['daily', 'price'], true) ? '❌ قیمت باید عدد صحیح صفر یا بزرگ‌تر باشد.' : '❌ عدد صحیح مثبت و معتبر وارد کنید.';
                $action = 'prompt';
            } elseif ($kind === 'sample') {
                $state['sample'] = (int)$value;
                $action = 'preview';
            } elseif ($kind === 'days') {
                $state['days'] = (int)$value;
                $state['input'] = 'price';
                $message = 'مبلغ افزایش قیمت هر گیگابایت برای ' . (int)$value . " روز را به تومان وارد کنید.\nمثال: قیمت پایه ۵٬۰۰۰ + افزایش ۱٬۰۰۰ = هر گیگ ۶٬۰۰۰ تومان.\nصفر یعنی بدون افزایش؛ فقط قیمت پایه حجم.";
                $action = 'prompt';
            } elseif ($kind === 'daily') {
                $state['pending'] = ['action' => 'daily', 'price' => $value];
                $message = 'قیمت روزانه: ' . number_format($value) . ' تومان · گروه ' . $group
                    . "\nبا تأیید، روش روزانه فعال می‌شود و قیمت پایه هر گیگ تغییر نمی‌کند.";
                $action = 'confirm';
            } elseif ($kind === 'price') {
                $state['pending'] = ['action' => 'save', 'days' => $state['days'], 'price' => $value];
                $message = $state['days'] . ' روز → افزایش ' . number_format($value) . " تومان به قیمت پایه هر گیگ\nاین مبلغ در تعداد روز ضرب نمی‌شود.";
                if ($value == 0) {
                    $message = $state['days'] . " روز → استفاده از قیمت پایه حجم هر گروه، بدون هزینه مدت\nمدت سرویس تغییر نمی‌کند.";
                }
                $action = 'confirm';
            } else {
                return true;
            }
        }
        if ($action === 'setdaily') {
            $state['input'] = 'daily';
            $message = 'قیمت هر روز سرویس دلخواه را به تومان وارد کنید (صفر نیز مجاز است).';
            $action = 'prompt';
        }
        if ($action === 'add' || $action === 'edit' || $action === 'sample') {
            if ($action === 'edit') {
                $exists = false;
                foreach ($groups as $g) {
                    $exists = $exists || isset(fx_duration_config($panel, $g)['tariffs'][$days]);
                }
                if (!$exists) {
                    return true;
                }
            }
            $state['input'] = $action === 'sample' ? 'sample' : ($action === 'edit' ? 'price' : 'days');
            $state['days'] = $days;
            $message = $action === 'sample' ? 'حجم نمونه را به گیگابایت وارد کنید.' : ($action === 'edit' ? "مبلغ افزایش هر گیگابایت را به تومان وارد کنید؛ این مبلغ به قیمت پایه حجم اضافه می‌شود.\nصفر یعنی بدون افزایش." : 'مدت سرویس را به روز وارد کنید.');
            $action = 'prompt';
        }
        if (in_array($action, ['enable', 'disable', 'delete'], true)) {
            $state['pending'] = ['action' => $action, 'days' => $days];
            $message = 'تأیید ' . ['enable' => 'فعال‌سازی', 'disable' => 'غیرفعال‌سازی', 'delete' => 'حذف تعرفه'][$action] . ($days ? ' ' . $days . ' روز' : '') . ' برای گروه ' . $group . '؟';
            if ($action === 'disable') {
                $message = 'روش روزانه با قیمت‌های قبلی برای گروه ' . $group . ' فعال شود؟';
            }
            $action = 'confirm';
        }
        if ($action === 'apply' && isset($state['pending'])) {
            $pending = $state['pending'];
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT custom_duration_pricing, pricecustomtime FROM marzban_panel WHERE code_panel = ? FOR UPDATE');
                $stmt->execute([$state['panel']]);
                $stored = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($stored === false) {
                    throw new RuntimeException('Panel not found');
                }
                $map = json_decode((string)$stored['custom_duration_pricing'], true) ?: [];
                $dailyPrices = json_decode((string)$stored['pricecustomtime'], true) ?: [];
                foreach ($groups as $g) {
                    $config = fx_duration_config(['custom_duration_pricing' => json_encode($map)], $g);
                    if ($pending['action'] === 'daily') {
                        $dailyPrices[$g] = $pending['price'];
                        $config['enabled'] = false;
                    } elseif ($pending['action'] === 'save') {
                        $config['tariffs'][(int)$pending['days']] = (float)$pending['price'];
                        $config['surcharge_days'] = array_values(array_unique(array_merge($config['surcharge_days'], [(int)$pending['days']])));
                    } elseif ($pending['action'] === 'delete') {
                        unset($config['tariffs'][(int)$pending['days']]);
                        $config['surcharge_days'] = array_values(array_diff($config['surcharge_days'], [(int)$pending['days']]));
                    } else {
                        $config['enabled'] = $pending['action'] === 'enable';
                        if ($config['enabled'] && count(fx_duration_tariffs(array_merge($panel, ['custom_duration_pricing' => json_encode([$g => $config])]), $g)) === 0) {
                            throw new InvalidArgumentException('ابتدا یک تعرفه در محدوده زمان مجاز گروه ' . $g . ' ثبت کنید.');
                        }
                    }
                    $map[$g] = $config;
                }
                if ($pending['action'] === 'daily') {
                    $stmt = $pdo->prepare('UPDATE marzban_panel SET custom_duration_pricing = ?, pricecustomtime = ? WHERE code_panel = ?');
                    $stmt->execute([json_encode($map, JSON_UNESCAPED_UNICODE), json_encode($dailyPrices), $state['panel']]);
                    $panel['pricecustomtime'] = json_encode($dailyPrices);
                } else {
                    $stmt = $pdo->prepare('UPDATE marzban_panel SET custom_duration_pricing = ? WHERE code_panel = ?');
                    $stmt->execute([json_encode($map, JSON_UNESCAPED_UNICODE), $state['panel']]);
                }
                $pdo->commit();
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('marzban_panel');
                }
                if (function_exists('faoxima_bust_bot_selectcache')) {
                    faoxima_bust_bot_selectcache('marzban_panel');
                }
                $panel['custom_duration_pricing'] = json_encode($map);
                $message = "✅ ذخیره شد.\n\n";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = '❌ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n";
            }
            unset($state['pending']);
            $action = 'menu';
        }
        if ($action === 'prompt') {
            $rows[] = [$button('لغو / بازگشت', 'menu')];
        } elseif ($action === 'confirm') {
            $rows[] = [$button('✅ تأیید', 'apply'), $button('لغو', 'menu')];
        } elseif ($mode === 'daily') {
            unset($state['pending'], $state['input']);
            $message .= '📅 روش روزانه · ' . htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8') . ' · گروه ' . $group . "\n";
            foreach ($groups as $g) {
                $message .= "\n" . $g . ': ' . (fx_duration_config($panel, $g)['enabled'] ? 'روش تعرفه مدت فعال است' : '✅ روش روزانه فعال است')
                    . "\nقیمت پایه هر گیگ: " . number_format(fx_panel_raw_unit_price($panel, 'pricecustomvolume', $g) ?? 0)
                    . ' تومان · قیمت هر روز: ' . number_format(fx_panel_raw_unit_price($panel, 'pricecustomtime', $g) ?? 0) . " تومان\n";
            }
            $rows[] = [$button('✏️ تعیین قیمت روزانه و فعال‌سازی', 'setdaily')];
            $rows[] = [$button('✅ استفاده از روش روزانه با قیمت قبلی', 'disable')];
            $rows[] = [$button('انتخاب گروه', 'groups'), $button('🔙 انتخاب روش', 'modes')];
        } else {
            unset($state['pending'], $state['input']);
            $message .= '🗓 افزایش قیمت هر گیگ بر اساس مدت · ' . htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8') . ' · گروه ' . $group . "\n";
            $durations = [];
            foreach ($groups as $g) {
                $config = fx_duration_config($panel, $g);
                $message .= "\n" . $g . ': ' . ($config['enabled'] ? '✅ فعال' : 'غیرفعال') . "\n";
                if ($action === 'preview') {
                    $message .= 'حجم نمونه: ' . $state['sample'] . " GB\n";
                }
                foreach ($config['tariffs'] as $d => $price) {
                    $durations[$d] = true;
                    $unit = fx_duration_unit_price($panel, $g, $price, $d);
                    $base = fx_panel_raw_unit_price($panel, 'pricecustomvolume', $g) ?? 0.0;
                    $message .= $d . ' روز → ' . number_format($action === 'preview' ? (float)fx_adjust_base_toman($unit, $panel, 'custom_volume') : $unit) . ' تومان/GB';
                    if (in_array($d, $config['surcharge_days'], true)) {
                        $message .= ' (پایه ' . number_format($base) . ' + افزایش ' . number_format($price) . ')';
                    } elseif ($price > 0) {
                        $message .= ' (قیمت نهایی ذخیره‌شده قبلی)';
                    }
                    if ($price == 0) {
                        $message .= ' (تعرفه ثبت‌شده: صفر؛ قیمت پایه حجم، بدون هزینه مدت)';
                    }
                    if ($action === 'preview') {
                        $adjusted = fx_adjust_base_toman($unit, $panel, 'custom_volume');
                        $total = fx_finalize_amount($state['sample'] * $adjusted, $panel, ['custom_volume', 'custom_time']);
                        $message .= ' → مبلغ کل: ' . number_format((float)$total) . ' تومان';
                    }
                    if (!isset(fx_duration_tariffs($panel, $g)[$d])) {
                        $message .= ' (خارج از محدوده مجاز)';
                    }
                    $message .= "\n";
                }
            }
            ksort($durations, SORT_NUMERIC);
            foreach ($durations as $d => $_) {
                $rows[] = [$button('✏️ ' . $d . ' روز', 'edit', $d), $button('🗑 حذف ' . $d . ' روز', 'delete', $d)];
            }
            $rows[] = [$button('➕ افزودن تعرفه', 'add')];
            $rows[] = [$button('✅ فعال‌سازی روش تعرفه مدت', 'enable'), $button('استفاده از روش روزانه', 'disable')];
            $rows[] = [$button('🧮 پیش‌نمایش', 'preview'), $button('حجم نمونه', 'sample')];
            $rows[] = [$button('انتخاب گروه / بازگشت', 'groups')];
            $rows[] = [$button('🔙 انتخاب روش محاسبه', 'modes')];
            $rows[] = [$button('🔙 مدیریت پنل', 'back')];
        }
    }
    update('user', 'Processing_value_tow', json_encode($state, JSON_UNESCAPED_UNICODE), 'id', $from_id);
    step($action === 'prompt' ? 'cdadm_input' : 'PanelMenu', $from_id);
    nm_adminInstantReply($from_id, $message, json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE), 'HTML');
    return true;
}
