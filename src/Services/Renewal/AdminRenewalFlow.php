<?php

namespace Faoxima\Services\Renewal;

use PDO;
use Throwable;

final class AdminRenewalFlow
{
    public static function rxArText(string $key, array $vars = []): string
    {
        return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('adm_' . $key, $vars);
    }

    public static function rxArEsc($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function rxArAnswer($callbackId, string $text = '', bool $alert = true): void
    {
        if (empty($callbackId) || !function_exists('telegram')) {
            return;
        }
        $payload = ['callback_query_id' => $callbackId, 'cache_time' => 1];
        if ($text !== '') {
            $payload['text'] = $text;
            $payload['show_alert'] = $alert;
        }
        try {
            telegram('answerCallbackQuery', $payload);
        } catch (Throwable $e) {
        }
    }

    public static function rxArDraftEncode(array $draft): string
    {
        return (string) json_encode($draft);
    }

    public static function rxArDraftLoad($raw): ?array
    {
        if (!is_string($raw) || $raw === '' || $raw[0] !== '{') {
            return null;
        }
        $draft = json_decode($raw, true);
        if (!is_array($draft) || empty($draft['rxar']) || empty($draft['t']) || empty($draft['inv']) || !isset($draft['ph'])) {
            return null;
        }
        if (time() - (int) ($draft['ts'] ?? 0) > RX_AR_TTL) {
            return null;
        }
        return $draft;
    }

    public static function rxArDraftSave(array $draft, $fromId): array
    {
        global $user;
        $draft['ts'] = time();
        $draft['v'] = (int) ($draft['v'] ?? 0) + 1;
        $json = self::rxArDraftEncode($draft);
        \Faoxima\Core\Database\Db::update("user", "Processing_value", $json, "id", $fromId);
        if (is_array($user)) {
            $user['Processing_value'] = $json;
        }
        return $draft;
    }

    public static function rxArDraftClear($fromId): void
    {
        global $user;
        \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $fromId);
        if (is_array($user)) {
            $user['Processing_value'] = "0";
        }
        \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
    }

    public static function rxArDraftResetIfAny($fromId, $user = null): void
    {
        if (is_array($user) && self::rxArDraftLoad($user['Processing_value'] ?? null) !== null) {
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $fromId);
        }
    }

    public static function rxArDraftClaim($fromId, string $expectedRaw, array $next): bool
    {
        global $pdo, $user;
        $next['ts'] = time();
        $next['v'] = (int) ($next['v'] ?? 0) + 1;
        $json = self::rxArDraftEncode($next);
        try {
            $stmt = $pdo->prepare("UPDATE user SET Processing_value = ? WHERE id = ? AND Processing_value = ?");
            $stmt->execute([$json, (string) $fromId, $expectedRaw]);
        } catch (Throwable $e) {
            return false;
        }
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        if (is_array($user)) {
            $user['Processing_value'] = $json;
        }
        return true;
    }

    public static function rxArNew($adminId, array $invoice, array $panel): array
    {
        return [
            'rxar' => 1,
            't' => bin2hex(random_bytes(4)),
            'ts' => time(),
            'v' => 1,
            'adm' => (string) $adminId,
            'inv' => (string) $invoice['id_invoice'],
            'u' => (string) $invoice['username'],
            'pn' => (string) $panel['name_panel'],
            'ph' => 'src',
            'mode' => '',
            'pid' => 0,
            'pg' => 1,
            'vt' => '',
            'vg' => 0,
            'dt' => '',
            'dg' => 0,
            'm' => '',
            'nf' => 1,
            'pp' => null,
        ];
    }

    public static function rxArCb(array $draft, string $code, $arg = null): string
    {
        return 'rxar:' . $draft['t'] . ':' . $code . ($arg !== null ? ':' . $arg : '');
    }

    public static function rxArParseCallback($data): ?array
    {
        if (!is_string($data) || !preg_match('/^rxar:([0-9a-f]{8}):([a-z]{2})(?::([A-Za-z0-9]{1,12}))?$/', $data, $m)) {
            return null;
        }
        return ['t' => $m[1], 'code' => $m[2], 'arg' => isset($m[3]) ? $m[3] : null];
    }

    public static function rxArMethods(): array
    {
        return \Faoxima\Services\Renewal\Plans::renewalAdminBasePolicies();
    }

    public static function rxArKeepFlags(array $d): array
    {
        if (($d['mode'] ?? '') === 'custom') {
            return [($d['vt'] ?? '') === 'unchanged', ($d['dt'] ?? '') === 'unchanged'];
        }
        return [false, false];
    }

    public static function rxArEligibleProducts(string $panelName): array
    {
        global $pdo;
        return \Faoxima\Services\Renewal\AdministrativePlans::arEligibleProducts($pdo, $panelName);
    }

    public static function rxArFormatNumber($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    public static function rxArStateLabel(string $state, $value, string $unit): string
    {
        if ($state === 'unchanged') {
            return self::rxArText('state_unchanged');
        }
        if ($state === 'unlimited') {
            return self::rxArText('state_unlimited');
        }
        if ($state === 'finite') {
            return self::rxArFormatNumber($value) . ' ' . $unit;
        }
        return self::rxArText('state_unset');
    }

    public static function rxArPackageLabel(array $d): string
    {
        if (($d['mode'] ?? '') === 'product') {
            $product = \Faoxima\Services\Renewal\AdministrativePlans::arProduct($d);
            if ($product === null) {
                return self::rxArText('state_unset');
            }
            return self::rxArText('package_product', [
                'name' => self::rxArEsc($product['name_product']),
                'volume' => (float) $product['Volume_constraint'] > 0 ? self::rxArFormatNumber($product['Volume_constraint']) . ' ' . \Faoxima\Services\Renewal\RenewalText::rxRenewalText('gb_unit') : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('unlimited'),
                'days' => (float) $product['Service_time'] > 0 ? self::rxArFormatNumber($product['Service_time']) . ' ' . \Faoxima\Services\Renewal\RenewalText::rxRenewalText('days_unit') : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('unlimited'),
            ]);
        }
        return self::rxArText('package_custom', [
            'volume' => self::rxArStateLabel((string) ($d['vt'] ?? ''), $d['vg'] ?? 0, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('gb_unit')),
            'days' => self::rxArStateLabel((string) ($d['dt'] ?? ''), $d['dg'] ?? 0, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('days_unit')),
        ]);
    }

    public static function rxArPrevPhase(array $d): ?string
    {
        switch ((string) $d['ph']) {
            case 'prod':
            case 'vol':
                return 'src';
            case 'days':
                return 'vol';
            case 'meth':
                return ($d['mode'] ?? '') === 'product' ? 'prod' : 'days';
            case 'opt':
                return 'meth';
            case 'prev':
                return 'opt';
        }
        return null;
    }

    public static function rxArNavRows(array $d, bool $showBack = true): array
    {
        $rows = [];
        if ($showBack && self::rxArPrevPhase($d) !== null) {
            $rows[] = [['text' => self::rxArText('btn_back'), 'callback_data' => self::rxArCb($d, 'bk')]];
        }
        $rows[] = [
            ['text' => self::rxArText('btn_service'), 'callback_data' => 'manageinvoice_' . $d['inv']],
            ['text' => self::rxArText('btn_admin'), 'callback_data' => self::rxArCb($d, 'am')],
        ];
        $rows[] = [['text' => self::rxArText('btn_cancel'), 'callback_data' => self::rxArCb($d, 'cx')]];
        return $rows;
    }

    public static function rxArHeader(array $d): string
    {
        return self::rxArText('title') . "\n" . self::rxArText('service_lines', ['username' => self::rxArEsc($d['u']), 'panel' => self::rxArEsc($d['pn'])]);
    }

    public static function rxArKeyboard(array $rows): string
    {
        return (string) json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public static function rxArReasonText(string $reason, array $plan = []): string
    {
        switch ($reason) {
            case 'both_unchanged':
                return self::rxArText('both_unchanged');
            case 'product_missing':
                return self::rxArText('product_missing');
            case 'incomplete':
                return self::rxArText('incomplete');
            case 'panel_unreachable':
                return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reason_panel_unreachable');
        }
        return \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText($reason, $plan);
    }

    public static function rxArBuild($managePanel, array $panel, array $d, ?array $snapshot = null): array
    {
        global $pdo;
        return \Faoxima\Services\Renewal\AdministrativePlans::arBuild($pdo, $managePanel, $panel, $d, $snapshot);
    }

    public static function rxArPreviewText(array $d, array $built): string
    {
        $plan = $built['plan'];
        $lines = [];
        $lines[] = self::rxArText('preview_title');
        $lines[] = self::rxArText('service_lines', ['username' => self::rxArEsc($d['u']), 'panel' => self::rxArEsc($d['pn'])]);
        $lines[] = self::rxArText('preview_package', ['value' => self::rxArPackageLabel($d)]);
        $lines[] = self::rxArText('preview_method', ['label' => self::rxArEsc(\Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel((string) $d['m']))]);
        if ((string) $built['policy'] !== (string) $d['m']) {
            $lines[] = self::rxArText('preview_effective', ['label' => self::rxArEsc(\Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel((string) $built['policy']))]);
        }
        $lines[] = self::rxArText('preview_exec_now');
        $lines[] = self::rxArText('preview_free');
        $lines[] = self::rxArText('preview_notify_' . (!empty($d['nf']) ? 'on' : 'off'));
        $lines[] = '';
        $lines[] = self::rxArText('preview_current');
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_current_limit', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['before_data_limit'] ?? 0)]);
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_used', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['before_used_traffic'] ?? 0, false)]);
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_current_remaining', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['before_remaining'] ?? null, false)]);
        $lines[] = !empty($plan['before_pending'])
            ? self::rxArText('preview_pending_current', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatDays(((int) ($plan['before_pending_duration'] ?? 0)) / 86400)])
            : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_current_expire', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatExpire($plan['before_expire'] ?? 0)]);
        $lines[] = '';
        $lines[] = self::rxArText('preview_expected');
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_new_limit', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['data_limit'] ?? 0)]);
        $lines[] = self::rxArText('preview_new_used', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['target_used_traffic'] ?? 0, false)]);
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_new_remaining', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatBytes($plan['target_remaining'] ?? null)]);
        $lines[] = !empty($plan['target_pending'])
            ? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_pending', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatDays(((int) ($plan['target_pending_duration'] ?? 0)) / 86400)])
            : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_new_expire', ['value' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatExpire($plan['expire'] ?? 0)]);
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText(!empty($plan['reset_usage']) ? 'preview_usage_reset' : 'preview_usage_kept');
        $unchanged = [];
        if (($plan['traffic_mode'] ?? '') === 'keep') {
            $unchanged[] = self::rxArText('unchanged_traffic');
        }
        if (($plan['time_mode'] ?? '') === 'keep') {
            $unchanged[] = self::rxArText('unchanged_time');
        }
        $lines[] = self::rxArText('preview_unchanged', ['value' => $unchanged === [] ? self::rxArText('none') : implode('، ', $unchanged)]);
        if (!empty($plan['enable'])) {
            $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('preview_enable');
        }
        return implode("\n", $lines);
    }

    public static function rxArRender($managePanel, array $panel, array $d, string $notice = ''): array
    {
        $header = self::rxArHeader($d);
        $notice = $notice !== '' ? "\n\n" . $notice : '';
        switch ((string) $d['ph']) {
            case 'prod':
                $products = self::rxArEligibleProducts((string) $d['pn']);
                $perPage = 8;
                $pages = max(1, (int) ceil(count($products) / $perPage));
                $page = min($pages, max(1, (int) ($d['pg'] ?? 1)));
                $rows = [];
                foreach (array_slice($products, ($page - 1) * $perPage, $perPage) as $product) {
                    $label = (string) $product['name_product'] . ' • '
                        . ((float) $product['Volume_constraint'] > 0 ? self::rxArFormatNumber($product['Volume_constraint']) . \Faoxima\Services\Renewal\RenewalText::rxRenewalText('gb_unit') : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('unlimited')) . ' • '
                        . ((float) $product['Service_time'] > 0 ? self::rxArFormatNumber($product['Service_time']) . \Faoxima\Services\Renewal\RenewalText::rxRenewalText('days_unit') : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('unlimited'));
                    $selected = ($d['mode'] ?? '') === 'product' && (int) $d['pid'] === (int) $product['id'];
                    $rows[] = [['text' => ($selected ? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('mark_on') . ' ' : '') . $label, 'callback_data' => self::rxArCb($d, 'pk', (int) $product['id'])]];
                }
                if ($pages > 1) {
                    $pager = [];
                    if ($page > 1) {
                        $pager[] = ['text' => '◀️', 'callback_data' => self::rxArCb($d, 'pg', $page - 1)];
                    }
                    $pager[] = ['text' => $page . '/' . $pages, 'callback_data' => self::rxArCb($d, 'pg', $page)];
                    if ($page < $pages) {
                        $pager[] = ['text' => '▶️', 'callback_data' => self::rxArCb($d, 'pg', $page + 1)];
                    }
                    $rows[] = $pager;
                }
                $text = $header . "\n\n" . ($products === [] ? self::rxArText('prod_empty') : self::rxArText('prod_prompt')) . $notice;
                return [$text, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
            case 'vol':
                $text = $header . "\n\n" . self::rxArText('vol_prompt');
                if (($d['vt'] ?? '') !== '') {
                    $text .= "\n\n" . self::rxArText('current_choice', ['value' => self::rxArStateLabel((string) $d['vt'], $d['vg'], \Faoxima\Services\Renewal\RenewalText::rxRenewalText('gb_unit'))]);
                }
                $rows = [[['text' => self::rxArText('btn_keep_traffic'), 'callback_data' => self::rxArCb($d, 'kt')]]];
                return [$text . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
            case 'days':
                $text = $header . "\n\n" . self::rxArText('days_prompt');
                if (($d['dt'] ?? '') !== '') {
                    $text .= "\n\n" . self::rxArText('current_choice', ['value' => self::rxArStateLabel((string) $d['dt'], $d['dg'], \Faoxima\Services\Renewal\RenewalText::rxRenewalText('days_unit'))]);
                }
                $rows = [[['text' => self::rxArText('btn_keep_time'), 'callback_data' => self::rxArCb($d, 'kd')]]];
                return [$text . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
            case 'meth':
                $text = $header . "\n" . self::rxArText('preview_package', ['value' => self::rxArPackageLabel($d)]) . "\n\n" . self::rxArText('method_prompt') . "\n";
                $rows = [];
                foreach (self::rxArMethods() as $index => $policy) {
                    $text .= "\n" . ($index + 1) . '. ' . self::rxArEsc(\Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy));
                    $rows[] = [['text' => (($d['m'] ?? '') === $policy ? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('mark_on') . ' ' : '') . \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($policy, false, true), 'callback_data' => self::rxArCb($d, 'mt', $index)]];
                }
                list($keepTraffic, $keepTime) = self::rxArKeepFlags($d);
                if ($keepTraffic || $keepTime) {
                    $text .= "\n\n" . self::rxArText('method_override_note');
                }
                if (($d['m'] ?? '') !== '') {
                    $rows[] = [['text' => self::rxArText('btn_continue'), 'callback_data' => self::rxArCb($d, 'nx')]];
                }
                return [$text . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
            case 'opt':
                $text = $header . "\n" . self::rxArText('preview_package', ['value' => self::rxArPackageLabel($d)]) . "\n" . self::rxArText('preview_method', ['label' => self::rxArEsc(\Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel((string) $d['m']))]) . "\n\n" . self::rxArText('opt_prompt');
                $rows = [];
                $rows[] = [
                    ['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText(!empty($d['nf']) ? 'mark_on' : 'mark_off') . ' ' . self::rxArText('btn_notify_on'), 'callback_data' => self::rxArCb($d, 'nf', 1)],
                    ['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText(empty($d['nf']) ? 'mark_on' : 'mark_off') . ' ' . self::rxArText('btn_notify_off'), 'callback_data' => self::rxArCb($d, 'nf', 0)],
                ];
                $rows[] = [['text' => self::rxArText('btn_preview'), 'callback_data' => self::rxArCb($d, 'go')]];
                return [$text . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
            case 'prev':
                $built = self::rxArBuild($managePanel, $panel, $d);
                if (empty($built['ok'])) {
                    $text = $header . "\n\n" . $built['text'] . "\n\n" . self::rxArText('revise_hint');
                    $rows = [[['text' => self::rxArText('btn_refresh'), 'callback_data' => self::rxArCb($d, 'rf')]]];
                    return [$text . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
                }
                $rows = [[
                    ['text' => self::rxArText('btn_confirm'), 'callback_data' => self::rxArCb($d, 'ok')],
                    ['text' => self::rxArText('btn_refresh'), 'callback_data' => self::rxArCb($d, 'rf')],
                ]];
                return [self::rxArPreviewText($d, $built) . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d))), $built];
        }
        $rows = [[
            ['text' => self::rxArText('btn_product'), 'callback_data' => self::rxArCb($d, 'sp')],
            ['text' => self::rxArText('btn_custom'), 'callback_data' => self::rxArCb($d, 'sc')],
        ]];
        return [$header . "\n\n" . self::rxArText('src_prompt') . $notice, self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d)))];
    }

    public static function rxArRef(array $d): string
    {
        return 'admin:' . $d['adm'] . ':' . $d['t'];
    }

    public static function rxArShow($managePanel, array $panel, array $d, string $notice = ''): array
    {
        $d['ph'] = (string) $d['ph'];
        $rendered = self::rxArRender($managePanel, $panel, $d, $notice);
        if ($d['ph'] === 'prev' && isset($rendered[2])) {
            $d['pp'] = \Faoxima\Services\Renewal\AdministrativePlans::arPlanSignature($rendered[2]);
        } elseif ($d['ph'] === 'prev') {
            $d['pp'] = null;
        }
        $d = self::rxArDraftSave($d, $d['adm']);
        \Faoxima\Telegram\Navigation\Steps::step($d['ph'] === 'vol' ? 'rxar_vol' : ($d['ph'] === 'days' ? 'rxar_days' : 'home'), $d['adm']);
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($d['adm'], $rendered[0], $rendered[1], 'HTML');
        return $d;
    }

    public static function rxArStart($managePanel, $fromId, $idInvoice): void
    {
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $idInvoice, "select");
        if (!is_array($invoice)) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('error_start'), null, 'HTML');
            return;
        }
        $backRow = [[['text' => self::rxArText('btn_service'), 'callback_data' => 'manageinvoice_' . $invoice['id_invoice']]]];
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!is_array($panel)) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('error_start'), self::rxArKeyboard($backRow), 'HTML');
            return;
        }
        if (($panel['type'] ?? '') === 'Manualsale' || (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel))) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('error_manual_panel'), self::rxArKeyboard($backRow), 'HTML');
            return;
        }
        if (!in_array($invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('error_status', ['status' => self::rxArEsc($invoice['Status'])]), self::rxArKeyboard($backRow), 'HTML');
            return;
        }
        $snapshot = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($managePanel, (string) $panel['name_panel'], (string) $invoice['username'], (string) $panel['type']);
        if (empty($snapshot['ok']) || empty($snapshot['exists'])) {
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, empty($snapshot['ok']) ? self::rxArReasonText('panel_unreachable') : self::rxArReasonText('user_missing'), self::rxArKeyboard($backRow), 'HTML');
            return;
        }
        self::rxArShow($managePanel, $panel, self::rxArNew($fromId, $invoice, $panel));
    }

    public static function rxArNotifyStateOf(string $opId): string
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT notify_state FROM renewal_operation WHERE op_id = ? LIMIT 1");
            $stmt->execute([$opId]);
            $value = $stmt->fetchColumn();
            return $value === false || $value === null ? 'none' : (string) $value;
        } catch (Throwable $e) {
            return 'none';
        }
    }

    public static function rxArNotifyClassify($response): array
    {
        if (!is_array($response) || $response === []) {
            return ['kind' => 'ambiguous', 'reason' => 'empty_or_invalid_response', 'retry_after' => 0];
        }
        if (array_key_exists('ok', $response) && $response['ok'] === true) {
            if (is_array($response['result'] ?? null) && isset($response['result']['message_id'])) {
                return ['kind' => 'sent', 'reason' => '', 'retry_after' => 0];
            }
            return ['kind' => 'ambiguous', 'reason' => 'success_without_message', 'retry_after' => 0];
        }
        if (!array_key_exists('ok', $response) || $response['ok'] !== false) {
            return ['kind' => 'ambiguous', 'reason' => 'unexpected_response', 'retry_after' => 0];
        }
        $code = isset($response['error_code']) && is_numeric($response['error_code']) ? (int) $response['error_code'] : 0;
        $description = \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean((string) ($response['description'] ?? ''), 120);
        if ($code === 0) {
            return ['kind' => 'ambiguous', 'reason' => 'network:' . $description, 'retry_after' => 0];
        }
        if ($code === 429) {
            $retryAfter = isset($response['parameters']['retry_after']) && is_numeric($response['parameters']['retry_after']) ? (int) $response['parameters']['retry_after'] : 0;
            return ['kind' => 'rate_limited', 'reason' => 'rate_limited', 'retry_after' => max(1, $retryAfter)];
        }
        if ($code >= 500 || $code === 401 || $code === 408) {
            return ['kind' => 'transient', 'reason' => 'http_' . $code . ':' . $description, 'retry_after' => 0];
        }
        return ['kind' => 'permanent', 'reason' => 'http_' . $code . ':' . $description, 'retry_after' => 0];
    }

    public static function rxArNotifyBackoff(int $attempt, int $retryAfter = 0): int
    {
        $delay = (int) min(1800, 60 * pow(2, max(0, $attempt - 1)));
        return max($delay, min(3600, $retryAfter + 1));
    }

    public static function rxArNotifyLog(string $stage, string $reason, array $op, int $attempt, string $detail): void
    {
        \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log($stage, $reason, [
            'panel_name' => (string) ($op['name_panel'] ?? ''),
            'username' => (string) ($op['username'] ?? ''),
            'invoice_id' => (string) ($op['id_invoice'] ?? ''),
            'operation_id' => (string) ($op['op_id'] ?? ''),
            'method' => (string) ($op['method'] ?? ''),
            'policy' => (string) ($op['policy'] ?? ''),
            'mutation' => 'none',
            'explanation' => 'attempt=' . $attempt,
            'error' => \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean($detail, 160),
        ]);
    }

    public static function rxArNotifyOperation(string $opId): string
    {
        global $pdo;
        try {
            $now = time();
            $max = RX_AR_NOTIFY_MAX_ATTEMPTS;
            $token = bin2hex(random_bytes(8));
            $claim = $pdo->prepare(
                "UPDATE renewal_operation SET notify_state = 'sending', notify_token = ?, notify_claimed_at = ?, notify_attempts = notify_attempts + 1, updated_at = ? "
                . "WHERE op_id = ? AND finalize_state = 'done' AND notify_attempts < ? AND ("
                . "notify_state = 'pending' "
                . "OR (notify_state = 'failed' AND (notify_next IS NULL OR notify_next <= ?)) "
                . "OR (notify_state = 'sending' AND (notify_claimed_at IS NULL OR notify_claimed_at <= ?)))"
            );
            $claim->execute([$token, $now, $now, $opId, $max, $now, $now - RX_AR_NOTIFY_STALE_SECONDS]);
            if ($claim->rowCount() !== 1) {
                return self::rxArNotifyStateOf($opId);
            }
            $stmt = $pdo->prepare("SELECT * FROM renewal_operation WHERE op_id = ? LIMIT 1");
            $stmt->execute([$opId]);
            $op = $stmt->fetch(PDO::FETCH_ASSOC);
            $ctx = is_array($op) ? \Faoxima\Payments\Fulfillment\Claims::decode($op['finalize_context'] ?? null) : null;
            $attempt = is_array($op) ? (int) $op['notify_attempts'] : 1;
            $outcome = ['kind' => 'permanent', 'reason' => 'no_recipient', 'retry_after' => 0];
            if (is_array($ctx) && (string) ($ctx['uid'] ?? '') !== '') {
                $text = self::rxArText('customer_done', ['username' => self::rxArEsc($ctx['u'] ?? '')]);
                $before = \Faoxima\Payments\Fulfillment\Claims::decode($op['before_state'] ?? null);
                $after = \Faoxima\Payments\Fulfillment\Claims::decode($op['result_state'] ?? null);
                if (is_array($before) && is_array($after)) {
                    $text .= "\n\n" . \Faoxima\Services\Renewal\RenewalText::rxRenewalResultText($before, $after);
                }
                try {
                    $outcome = self::rxArNotifyClassify(sendmessage($ctx['uid'], $text, null, 'HTML', $ctx['bottype'] ?? null));
                } catch (Throwable $e) {
                    $outcome = ['kind' => 'ambiguous', 'reason' => 'exception:' . $e->getMessage(), 'retry_after' => 0];
                }
            }
            $kind = $outcome['kind'];
            if ($kind === 'sent') {
                $state = 'sent';
                $next = null;
            } elseif ($kind === 'permanent') {
                $state = 'dead';
                $next = null;
            } elseif ($attempt >= $max) {
                $state = 'dead';
                $next = null;
            } else {
                $state = 'failed';
                $next = $now + self::rxArNotifyBackoff($attempt, (int) $outcome['retry_after']);
            }
            $final = $pdo->prepare("UPDATE renewal_operation SET notify_state = ?, notify_next = ?, notify_error = ?, notify_token = NULL, updated_at = ? WHERE op_id = ? AND notify_state = 'sending' AND notify_token = ?");
            $final->execute([$state, $next, $kind === 'sent' ? null : substr(\Faoxima\Services\Renewal\RenewalLog::rx_renewal_log_clean($outcome['reason'], 200), 0, 250), time(), $opId, $token]);
            if ($kind !== 'sent' && is_array($op)) {
                $stage = $state === 'dead' ? ($kind === 'permanent' ? 'notify_permanent' : 'notify_exhausted') : ($kind === 'ambiguous' ? 'notify_ambiguous' : 'notify_retry');
                self::rxArNotifyLog('admin_notify', $stage, $op, $attempt, $kind . ':' . $outcome['reason']);
            }
            return $state;
        } catch (Throwable $e) {
            \Faoxima\Payments\Fulfillment\PaymentPolicy::log('ADMIN_RENEWAL_NOTIFY_FAILED', $e->getMessage(), ['op_id' => $opId]);
            return self::rxArNotifyStateOf($opId);
        }
    }

    public static function rxArFinish($managePanel, array $panel, array $invoice, array $d, array $run): void
    {
        global $setting, $errorreport, $otherservice;
        $adminId = $d['adm'];
        $extend = $run['extend'];
        $username = (string) $d['u'];
        $serviceRow = [[['text' => self::rxArText('btn_service'), 'callback_data' => 'manageinvoice_' . $d['inv']]]];
        if ($run['state'] === 'pending') {
            self::rxArDraftClear($adminId);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('pending_verification') . "\n\n🧾 " . self::rxArEsc($extend['operation_id'] ?? ''), self::rxArKeyboard($serviceRow), 'HTML');
            return;
        }
        if ($run['state'] === 'failed') {
            $extend['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
            $message = \Faoxima\Services\Renewal\RenewalText::rxRenewalFailureText($extend, self::rxArText('failed_generic'));
            if (($extend['code'] ?? '') === 'manual_stock_empty') {
                $message = self::rxArText('failed_stock');
            }
            if (!empty($run['revisable'])) {
                $d['ph'] = 'prev';
                $d = self::rxArDraftSave($d, $adminId);
                \Faoxima\Telegram\Navigation\Steps::step('home', $adminId);
                $rows = [[['text' => self::rxArText('btn_refresh'), 'callback_data' => self::rxArCb($d, 'rf')]]];
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, $message . "\n\n" . self::rxArText('revise_hint'), self::rxArKeyboard(array_merge($rows, self::rxArNavRows($d))), 'HTML');
            } else {
                self::rxArDraftClear($adminId);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, $message, self::rxArKeyboard($serviceRow), 'HTML');
            }
            if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => "\n        خطای تمدید سرویس\n<blockquote>نام پنل : " . self::rxArEsc($panel['name_panel']) . "</blockquote>\n<blockquote>نام کاربری سرویس : " . self::rxArEsc($username) . "</blockquote>\n<blockquote>کد خطا : " . self::rxArEsc($extend['code'] ?? '') . "</blockquote>\n<blockquote>دلیل خطا : " . self::rxArEsc($extend['msg'] ?? '') . "</blockquote>",
                    'parse_mode' => "HTML",
                ]);
            }
            return;
        }
        self::rxArDraftClear($adminId);
        $final = is_array($extend['admin_finalize'] ?? null) ? $extend['admin_finalize'] : ['status' => 'pending', 'notify' => 'none'];
        $adminText = self::rxArText('admin_done') . "\n\n" . \Faoxima\Services\Renewal\RenewalText::rxRenewalOutcomeText($extend);
        if (($final['status'] ?? '') !== 'done') {
            $adminText .= "\n\n" . self::rxArText('finalize_pending');
        }
        if (empty($d['nf'])) {
            $adminText .= "\n\n" . self::rxArText('notified_no');
        } elseif (($final['notify'] ?? '') === 'sent') {
            $adminText .= "\n\n" . self::rxArText('notified_yes');
        } elseif (($final['notify'] ?? '') === 'dead') {
            $adminText .= "\n\n" . self::rxArText('notified_failed');
        } elseif (in_array(($final['notify'] ?? ''), ['failed', 'sending'], true)) {
            $adminText .= "\n\n" . self::rxArText('notified_retrying');
        } else {
            $adminText .= "\n\n" . self::rxArText('notified_later');
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, $adminText, self::rxArKeyboard($serviceRow), 'HTML');
        if (strlen((string) ($setting['Channel_Report'] ?? '')) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => self::rxArText('report_done') . "\n\n"
                    . "<blockquote>🪪 آیدی عددی ادمین : <code>" . self::rxArEsc($adminId) . "</code></blockquote>\n"
                    . "<blockquote>🪪 آیدی عددی : <code>" . self::rxArEsc($invoice['id_user']) . "</code></blockquote>\n"
                    . "<blockquote>🛍 بسته : " . self::rxArPackageLabel($d) . "</blockquote>\n"
                    . "<blockquote>⚙️ روش : " . self::rxArEsc(\Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel((string) $d['m'])) . "</blockquote>\n"
                    . "<blockquote>👤 نام کاربری مشتری در پنل  : " . self::rxArEsc($username) . "</blockquote>\n"
                    . "<blockquote>موقعیت سرویس سرویس کاربر : " . self::rxArEsc($invoice['Service_location']) . "</blockquote>",
                'parse_mode' => "HTML",
            ]);
        }
    }

    public static function rxArOnCallback($managePanel, $fromId, $datain): void
    {
        global $user, $callback_query_id, $admin_ids;
        $parsed = self::rxArParseCallback($datain);
        $raw = (string) ($user['Processing_value'] ?? '');
        $d = self::rxArDraftLoad($raw);
        if (!in_array($fromId, $admin_ids) || $parsed === null || $d === null || $d['t'] !== $parsed['t'] || (string) $d['adm'] !== (string) $fromId) {
            self::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
            return;
        }
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $d['inv'], "select");
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $d['pn'], "select");
        if (!is_array($invoice) || !is_array($panel) || (string) $invoice['username'] !== (string) $d['u'] || (string) $invoice['Service_location'] !== (string) $d['pn']) {
            self::rxArDraftClear($fromId);
            self::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
            return;
        }
        $code = $parsed['code'];
        $arg = $parsed['arg'];
        $phase = (string) $d['ph'];
        if (!empty($d['ex']) && $d['ex'] !== 'now') {
            unset($d['ex'], $d['tr']);
            $d['pp'] = null;
            if (in_array($phase, ['opt', 'prev'], true)) {
                $d['ph'] = 'opt';
            }
            if ($code !== 'cx' && $code !== 'am') {
                self::rxArShow($managePanel, $panel, $d, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
                return;
            }
        }
        if ($code === 'cx') {
            self::rxArDraftClear($fromId);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('cancelled'), self::rxArKeyboard([[['text' => self::rxArText('btn_service'), 'callback_data' => 'manageinvoice_' . $d['inv']]]]), 'HTML');
            return;
        }
        if ($code === 'am') {
            self::rxArDraftClear($fromId);
            global $text_panel_admin_login_template, $version, $keyboardadmin;
            $versionMiniApp = @file_get_contents('app/version');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, sprintf($text_panel_admin_login_template, $version, $versionMiniApp), $keyboardadmin, 'HTML');
            return;
        }
        if ($phase === 'run') {
            self::rxArAnswer($callback_query_id, self::rxArText('already_running'));
            return;
        }
        if ($code === 'bk') {
            $prev = self::rxArPrevPhase($d);
            if ($prev === null) {
                self::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
                return;
            }
            $d['ph'] = $prev;
            self::rxArShow($managePanel, $panel, $d);
            return;
        }
        $allowedFrom = [
            'sp' => ['src'], 'sc' => ['src'], 'pg' => ['prod'], 'pk' => ['prod'],
            'kt' => ['vol'], 'kd' => ['days'], 'mt' => ['meth'], 'nx' => ['meth'], 'nf' => ['opt'],
            'go' => ['opt', 'prev'], 'rf' => ['prev'], 'ok' => ['prev'],
        ];
        if (!isset($allowedFrom[$code]) || !in_array($phase, $allowedFrom[$code], true)) {
            self::rxArShow($managePanel, $panel, $d, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
            return;
        }
        switch ($code) {
            case 'sp':
                $d['ph'] = 'prod';
                $d['pg'] = 1;
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'sc':
                $d['mode'] = 'custom';
                $d['ph'] = 'vol';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'pg':
                $d['pg'] = max(1, (int) $arg);
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'pk':
                $product = \Faoxima\Core\Database\Db::select("product", "*", "id", (int) $arg, "select");
                if (!\Faoxima\Services\Renewal\AdministrativePlans::arProductEligible($product, (string) $d['pn'])) {
                    self::rxArAnswer($callback_query_id, self::rxArText('product_missing'));
                    return;
                }
                $d['mode'] = 'product';
                $d['pid'] = (int) $product['id'];
                $d['ph'] = 'meth';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'kt':
                if (($d['dt'] ?? '') === 'unchanged') {
                    self::rxArAnswer($callback_query_id, self::rxArText('both_unchanged'));
                    return;
                }
                $d['vt'] = 'unchanged';
                $d['vg'] = 0;
                $d['ph'] = 'days';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'kd':
                if (($d['vt'] ?? '') === 'unchanged') {
                    self::rxArAnswer($callback_query_id, self::rxArText('both_unchanged'));
                    return;
                }
                $d['dt'] = 'unchanged';
                $d['dg'] = 0;
                $d['ph'] = 'meth';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'mt':
                $methods = self::rxArMethods();
                if (!isset($methods[(int) $arg])) {
                    self::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
                    return;
                }
                $d['m'] = $methods[(int) $arg];
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'nx':
                if (($d['m'] ?? '') === '') {
                    self::rxArAnswer($callback_query_id, self::rxArText('method_required'));
                    return;
                }
                $d['ph'] = 'opt';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'nf':
                $d['nf'] = $arg === '1' ? 1 : 0;
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'go':
            case 'rf':
                $d['ph'] = 'prev';
                self::rxArShow($managePanel, $panel, $d);
                return;
            case 'ok':
                self::rxArConfirm($managePanel, $fromId, $raw, $d, $invoice, $panel);
                return;
        }
    }

    public static function rxArConfirm($managePanel, $fromId, string $raw, array $d, array $invoice, array $panel): void
    {
        global $callback_query_id;
        if (!is_array($d['pp'] ?? null)) {
            self::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
            return;
        }
        $claimed = $d;
        $claimed['ph'] = 'run';
        if (!self::rxArDraftClaim($fromId, $raw, $claimed)) {
            self::rxArAnswer($callback_query_id, self::rxArText('already_running'));
            return;
        }
        $claimed['v'] = (int) $claimed['v'] + 1;
        $built = self::rxArBuild($managePanel, $panel, $d);
        if (empty($built['ok'])) {
            $d['ph'] = 'prev';
            self::rxArShow($managePanel, $panel, $d, self::rxArText('state_changed'));
            return;
        }
        if (\Faoxima\Services\Renewal\AdministrativePlans::arPlanSignature($built) !== $d['pp']) {
            $d['ph'] = 'prev';
            self::rxArShow($managePanel, $panel, $d, self::rxArText('state_changed'));
            return;
        }
        if (!in_array($invoice['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            self::rxArDraftClear($fromId);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, self::rxArText('error_status', ['status' => self::rxArEsc($invoice['Status'])]), self::rxArKeyboard([[['text' => self::rxArText('btn_service'), 'callback_data' => 'manageinvoice_' . $d['inv']]]]), 'HTML');
            return;
        }
        $run = \Faoxima\Services\Renewal\AdministrativeSettlement::arRun($managePanel, $panel, $invoice, $d, $built);
        $run['q'] = $built['q'];
        self::rxArFinish($managePanel, $panel, $invoice, $d, $run);
    }

    public static function rxArOnText($managePanel, $fromId, string $text): void
    {
        global $user, $admin_ids;
        $raw = (string) ($user['Processing_value'] ?? '');
        $d = self::rxArDraftLoad($raw);
        $step = (string) ($user['step'] ?? '');
        $expected = $step === 'rxar_vol' ? 'vol' : 'days';
        if (!in_array($fromId, $admin_ids) || $d === null || (string) $d['adm'] !== (string) $fromId || (string) $d['ph'] !== $expected) {
            \Faoxima\Telegram\Navigation\Steps::step('home', $fromId);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'), null, 'HTML');
            return;
        }
        $invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $d['inv'], "select");
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $d['pn'], "select");
        if (!is_array($invoice) || !is_array($panel)) {
            self::rxArDraftClear($fromId);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'), null, 'HTML');
            return;
        }
        $value = trim((string) convertPersianNumbersToEnglish($text));
        if (!ctype_digit($value) || strlen($value) > 7) {
            $rendered = self::rxArRender($managePanel, $panel, $d, self::rxArText('invalid_number'));
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($fromId, $rendered[0], $rendered[1], 'HTML');
            return;
        }
        $number = (int) $value;
        if ($expected === 'vol') {
            $d['vt'] = $number === 0 ? 'unlimited' : 'finite';
            $d['vg'] = $number;
            $d['ph'] = 'days';
        } else {
            $d['dt'] = $number === 0 ? 'unlimited' : 'finite';
            $d['dg'] = $number;
            $d['ph'] = 'meth';
        }
        self::rxArShow($managePanel, $panel, $d);
    }
}
