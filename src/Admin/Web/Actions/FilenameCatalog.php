<?php

namespace Faoxima\Admin\Web\Actions;

use ManagePanel;
use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $fnAction, $pdo.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class FilenameCatalog
{
    public static function handle(
        $fnAction,
        $pdo
    ) {
        if ($fnAction === 'panels') {
            $rows = $pdo->query('SELECT id, code_panel, name_panel, type FROM marzban_panel ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
            $panels = [];
            foreach ((array) $rows as $row) {
                $panels[] = [
                    'ref' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingPanelRef($row),
                    'code' => (string) ($row['code_panel'] ?? ''),
                    'name' => (string) ($row['name_panel'] ?? ''),
                    'type' => (string) ($row['type'] ?? ''),
                ];
            }
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'panels' => $panels]);
        }

        if ($fnAction === 'services') {
            $ref = isset($_GET['panel_ref']) && is_string($_GET['panel_ref']) ? trim($_GET['panel_ref']) : '';
            $panel = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_panel_by_ref($ref);
            if ($panel === null) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('panel_not_found', 404);
            }
            $type = (string) ($panel['type'] ?? '');
            if ($type === 'Manualsale') {
                $stmt = $pdo->prepare('SELECT username FROM manualsell WHERE codepanel = ? AND TRIM(username) <> \'\' GROUP BY username ORDER BY username ASC LIMIT 500');
                $stmt->execute([trim((string) ($panel['code_panel'] ?? ''))]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $services = [];
                foreach ((array) $rows as $row) {
                    $services[] = ['value' => (string) ($row['username'] ?? ''), 'label' => (string) ($row['username'] ?? '')];
                }
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'services' => $services, 'required' => false]);
            }
            $customerId = isset($_GET['user_id']) && is_string($_GET['user_id']) ? trim($_GET['user_id']) : '';
            $customerId = strtr($customerId, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            $ruleId = (int) ($_GET['rule_id'] ?? 0);
            $sourceOrder = null;
            if ($customerId === '' && $ruleId > 0 && \Faoxima\Services\Delivery\FileNaming::rxFileNamingTableReady()) {
                $ownerStmt = $pdo->prepare('SELECT i.id_user, i.time_sell, i.id_invoice FROM config_file_rule r JOIN invoice i ON i.username = r.source_service AND i.Service_location = ? WHERE r.id = ? AND r.panel_ref = ? LIMIT 1');
                $ownerStmt->execute([(string) ($panel['name_panel'] ?? ''), $ruleId, $ref]);
                $sourceOrder = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                $customerId = is_array($sourceOrder) ? (string) $sourceOrder['id_user'] : '';
            }
            if (!preg_match('/^[1-9][0-9]{0,19}$/', $customerId)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_user_id');
            }
            $page = min(100000, max(0, (int) ($_GET['page'] ?? 0)));
            if (is_array($sourceOrder)) {
                $rankStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE Service_location = ? AND id_user = ? AND Status NOT IN ('unpaid', 'removebyuser', 'removebyadmin', 'disabledn') AND TRIM(username) <> '' AND (COALESCE(time_sell, '') > ? OR (COALESCE(time_sell, '') = ? AND id_invoice > ?))");
                $rankStmt->execute([(string) ($panel['name_panel'] ?? ''), $customerId, (string) ($sourceOrder['time_sell'] ?? ''), (string) ($sourceOrder['time_sell'] ?? ''), (string) $sourceOrder['id_invoice']]);
                $page = (int) floor((int) $rankStmt->fetchColumn() / 20);
            }
            $offset = $page * 20;
            $stmt = $pdo->prepare("SELECT i.id_invoice, i.username, i.Status, i.id_user, i.name_product, u.username AS customer_username, u.namecustom AS customer_name FROM invoice i LEFT JOIN user u ON u.id = i.id_user WHERE i.Service_location = ? AND i.id_user = ? AND i.Status NOT IN ('unpaid', 'removebyuser', 'removebyadmin', 'disabledn') AND TRIM(i.username) <> '' ORDER BY COALESCE(i.time_sell, '') DESC, i.id_invoice DESC LIMIT 21 OFFSET " . $offset);
            $stmt->execute([(string) ($panel['name_panel'] ?? ''), $customerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $hasMore = count($rows) > 20;
            $rows = array_slice($rows, 0, 20);
            $seen = [];
            $services = [];
            foreach ((array) $rows as $row) {
                $username = trim((string) ($row['username'] ?? ''));
                if ($username === '' || isset($seen[$username])) {
                    continue;
                }
                $seen[$username] = true;
                $invoice = trim((string) ($row['id_invoice'] ?? ''));
                $product = trim((string) ($row['name_product'] ?? ''));
                $customer = trim((string) ($row['customer_name'] ?? ''));
                if (in_array(strtolower($customer), ['', 'none', 'null'], true)) {
                    $customer = trim((string) ($row['customer_username'] ?? ''));
                    $customer = $customer !== '' && strtolower($customer) !== 'none' ? '@' . ltrim($customer, '@') : 'کاربر ' . (string) ($row['id_user'] ?? '');
                }
                $statuses = [
                    'unpaid' => 'در انتظار پرداخت', 'active' => 'فعال',
                    'disabledn' => 'ناموجود در پنل', 'disabled' => 'غیرفعال',
                    'end_of_time' => 'اتمام زمان', 'end_of_volume' => 'اتمام حجم',
                    'sendedwarn' => 'هشدار پایانی', 'send_on_hold' => 'خطای اتصال',
                    'removebyuser' => 'حذف توسط کاربر', 'removebyadmin' => 'حذف توسط ادمین',
                ];
                $status = $statuses[(string) ($row['Status'] ?? '')] ?? 'وضعیت نامشخص';
                $title = ($product !== '' ? $product : 'سرویس بدون عنوان') . ' · ' . $customer;
                $detail = 'فاکتور: ' . ($invoice !== '' ? $invoice : '—') . ' · ' . $status . ' · نام کاربری سرویس: ' . $username;
                $services[] = [
                    'value' => $username,
                    'label' => $title,
                    'detail' => $detail,
                    'search' => implode(' ', [$title, $detail, (string) ($row['id_user'] ?? ''), (string) ($row['customer_username'] ?? '')]),
                ];
            }
            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'services' => $services, 'required' => true, 'user_id' => $customerId, 'page' => $page, 'has_more' => $hasMore]);
        }

        if ($fnAction === 'targets') {
            $ref = isset($_GET['panel_ref']) && is_string($_GET['panel_ref']) ? trim($_GET['panel_ref']) : '';
            $panel = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_panel_by_ref($ref);
            if ($panel === null) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('panel_not_found', 404);
            }
            $type = (string) ($panel['type'] ?? '');
            $service = isset($_GET['service']) && is_string($_GET['service']) ? trim($_GET['service']) : '';

            if ($type === 'Manualsale') {
                $manual = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_panel_targets_manual($panel);
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_finalize_targets($manual['targets'], $ref, ''), 'notice' => $manual['notice'], 'service_required' => false]);
            }

            if ($type === 'WGDashboard') {
                $inboundId = trim((string) ($panel['inboundid'] ?? ''));
                $targets = [];
                $notice = '';
                $targetKey = \Faoxima\Services\Delivery\FileNaming::rxFileNamingKeySubscription($inboundId);
                if ($targetKey === '') {
                    $notice = 'اینباند این پنل تعیین نشده است؛ برای تطبیق فایل اشتراک ابتدا اینباند پنل را تنظیم کنید.';
                } else {
                    $targets[] = [
                        'key' => $targetKey,
                        'label' => 'فایل اشتراک اینباند ' . $inboundId,
                        'owner' => '',
                        'basis' => 'sub',
                        'hint' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingBasisLabel('sub'),
                        'ext' => 'conf',
                        'protocol' => 'wireguard',
                        'source' => 'wgdashboard',
                        'record_id' => 0,
                    ];
                }
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_finalize_targets($targets, $ref, ''), 'notice' => $notice, 'service_required' => false]);
            }

            if (!in_array($type, ['x-ui_single', 'jovadpanel', 'rebecca', 'pasarguard'], true)) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => [], 'notice' => 'بارگذاری فایل‌های این نوع پنل هنوز به تنظیمات نام و کپشن متصل نشده است.', 'service_required' => false]);
            }

            if ($service === '') {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => [], 'notice' => '', 'service_required' => true]);
            }

            $serviceStmt = $pdo->prepare('SELECT 1 FROM invoice WHERE Service_location = ? AND username = ? LIMIT 1');
            $serviceStmt->execute([(string) ($panel['name_panel'] ?? ''), $service]);
            if (!$serviceStmt->fetchColumn()) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('invalid_target');
            }

            $targets = [];
            $notice = '';
            $output = null;
            try {
                $manager = new ManagePanel();
                $output = $manager->DataUser((string) ($panel['name_panel'] ?? ''), $service);
            } catch (Throwable $e) {
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_fail('panel_read_failed', 502);
            }
            if (!is_array($output) || (string) ($output['status'] ?? '') === 'Unsuccessful') {
                $message = is_array($output) && is_scalar($output['msg'] ?? null) ? (string) $output['msg'] : '';
                \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => [], 'notice' => 'خواندن سرویس از پنل ناموفق بود' . ($message !== '' ? ': ' . $message : '.'), 'service_required' => true]);
            }

            if ($type === 'x-ui_single') {
                $singleFile = $output['single_config_file'] ?? null;
                if (is_array($singleFile) && trim((string) ($singleFile['filename'] ?? '')) !== '') {
                    $targets[] = [
                        'key' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingScopedKey(\Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyFile($singleFile['filename']), $singleFile['protocol'] ?? 'wireguard'),
                        'label' => (string) $singleFile['filename'],
                        'owner' => '',
                        'basis' => 'file',
                        'hint' => \Faoxima\Services\Delivery\FileNaming::rxFileNamingBasisLabel('file'),
                        'ext' => 'conf',
                        'protocol' => (string) ($singleFile['protocol'] ?? 'wireguard'),
                        'source' => 'xui_file',
                        'record_id' => 0,
                    ];
                }
                $links = is_array($output['links'] ?? null) ? $output['links'] : [];
                foreach ($links as $link) {
                    if (!is_string($link) || trim($link) === '') {
                        continue;
                    }
                    if (stripos(trim($link), 'xuifile://') === 0 || stripos(trim($link), 'xuiawgfile://') === 0) {
                        continue;
                    }
                    $descriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($link);
                    if (is_array($descriptor)) {
                        $targets[] = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_target_from_descriptor($descriptor);
                    }
                }
            } elseif (in_array($type, ['rebecca', 'pasarguard'], true)) {
                $links = is_array($output['links'] ?? null) ? $output['links'] : [];
                foreach ($links as $link) {
                    if (!is_string($link)) {
                        continue;
                    }
                    $descriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($link);
                    if (is_array($descriptor)) {
                        $targets[] = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_target_from_descriptor($descriptor);
                    }
                }
                if (empty($targets)) {
                    $notice = 'در خروجی این سرویس فایل قابل‌شناسایی پیدا نشد. فایل‌های WireGuard و AmneziaWG با لینک معتبر و فایل‌های دانلودی WireGuard و OpenVPN پشتیبانی می‌شوند؛ لینک‌های معمولی و متن کانفیگ در این فهرست نمایش داده نمی‌شوند.';
                }
            } elseif ($type === 'jovadpanel') {
                $remoteId = trim((string) ($output['remote_id'] ?? ''));
                if ($remoteId === '') {
                    $notice = 'شناسه سرویس این پنل در دسترس نیست؛ امکان بارگذاری فایل‌ها وجود ندارد.';
                } else {
                    $channels = is_array($output['channels'] ?? null) ? $output['channels'] : [];
                    $kinds = [];
                    if (in_array('wireguard', $channels, true)) {
                        $kinds[] = 'wireguard';
                    }
                    if (in_array('openvpn', $channels, true)) {
                        $kinds[] = 'openvpn';
                    }
                    $links = is_array($output['links'] ?? null) ? $output['links'] : [];
                    foreach ($links as $link) {
                        if (!is_string($link)) {
                            continue;
                        }
                        if (stripos($link, 'jovadwg://') === 0 && !in_array('wireguard', $kinds, true)) {
                            $kinds[] = 'wireguard';
                        }
                        if (stripos($link, 'jovadovpn://') === 0 && !in_array('openvpn', $kinds, true)) {
                            $kinds[] = 'openvpn';
                        }
                    }
                    if (empty($kinds)) {
                        $kinds = ['wireguard', 'openvpn'];
                    }
                    $rxFoundJovadFiles = false;
                    foreach ($kinds as $kind) {
                        $files = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelProtocolFiles($panel, $remoteId, $kind);
                        if (($files['status'] ?? false) !== true) {
                            continue;
                        }
                        foreach ((array) ($files['files'] ?? []) as $file) {
                            $descriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingJovadDescriptor($file, $kind);
                            if (!is_array($descriptor)) {
                                continue;
                            }
                            $targets[] = \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_target_from_descriptor($descriptor);
                            $rxFoundJovadFiles = true;
                        }
                    }
                    if (!$rxFoundJovadFiles) {
                        $notice = 'هیچ فایل کانفیگی برای این سرویس پیدا نشد.';
                    }
                }
            }

            \Faoxima\Admin\Web\Support\FilenamingApi::fn_json(['ok' => true, 'targets' => \Faoxima\Admin\Web\Actions\FilenameTargetsHelpers::fn_finalize_targets($targets, $ref, $service), 'notice' => $notice, 'service_required' => true]);
        }

        return 1;
    }
}
