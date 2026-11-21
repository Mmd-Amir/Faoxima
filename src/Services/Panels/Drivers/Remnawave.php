<?php

namespace Faoxima\Services\Panels\Drivers;

use PDO;
use Faoxima\Services\Panels\Remnawave\RemnawaveManager;
use Throwable;

final class Remnawave
{
    public static function remnawave_manager_for($name_panel)
    {
        global $pdo;
        $row = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
        if (!is_array($row)) {
            return null;
        }
        return new RemnawaveManager($row);
    }

    public static function remnawave_squad_keyboard_rows(array $squadsList, array $selectedSquadUuids)
    {
        $rows = [];
        foreach ($squadsList as $squadRow) {
            if (empty($squadRow['uuid'])) {
                continue;
            }
            $squadUuid = (string) $squadRow['uuid'];
            $isSelected = in_array($squadUuid, $selectedSquadUuids, true);
            $checkIcon = $isSelected ? '✅' : '❌';
            $membersCount = (int) ($squadRow['info']['membersCount'] ?? 0);
            $rows[] = [[
                'text' => (string) ($squadRow['name'] ?? $squadUuid) . " ({$membersCount} عضو) {$checkIcon}",
                'callback_data' => 'remnatogglesquad#' . $squadUuid,
            ]];
        }
        return $rows;
    }

    public static function remnawave_bytes_from_gb($gb)
    {
        $gb = (float) $gb;
        if ($gb <= 0) {
            return 0;
        }
        return (int) round($gb * 1073741824);
    }

    public static function remnawave_iso_from_timestamp($timestamp)
    {
        $ts = (int) $timestamp;
        if ($ts <= 0) {
            $ts = time() + (3650 * 86400);
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    public static function remnawave_find_user($username)
    {
        global $pdo;
        $username = trim((string) $username);
        $stmt = $pdo->prepare("SELECT * FROM remnawave_users WHERE username = :u AND status <> 'removed' ORDER BY id DESC LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function remnawave_is_uuid($value)
    {
        $value = trim((string) $value);
        return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value);
    }

    public static function remnawave_adduser($name_panel, $data_limit_bytes, $username_ac, $timestamp, $squadUuid = null, $invoice_id = '', $hwid_limit = null)
    {
        global $pdo;
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $panelRow = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $name_panel, 'select');
        $squadRaw = trim((string) ($squadUuid ?? ''));
        if ($squadRaw === '') {
            $squadRaw = is_array($panelRow) ? trim((string) ($panelRow['remna_squad'] ?? '')) : '';
        }
        if ($squadRaw === '' && is_array($panelRow)) {
            $squadRaw = trim((string) ($panelRow['inboundid'] ?? ''));
        }
        $squads = array_values(array_filter(array_map('trim', explode(',', $squadRaw)), function ($v) {
            return self::remnawave_is_uuid($v);
        }));
        if (count($squads) === 0) {
            error_log("[REMNAMWAVE-CREATE-ERR] Invoice: $invoice_id | User: $username_ac | Res: invalid_or_missing_squad_uuid: '$squadRaw'");
            return ['status' => 'Unsuccessful', 'msg' => 'شناسه Squad تنظیم نشده یا نامعتبر است. لطفاً از منوی مدیریت پنل، حداقل یک Squad معتبر تنظیم کنید.'];
        }
        $params = [
            'username' => $username_ac,
            'trafficLimitBytes' => (int) $data_limit_bytes,
            'expireAt' => self::remnawave_iso_from_timestamp($timestamp),
            'status' => 'ACTIVE',
            'activeInternalSquads' => $squads,
        ];
        if ($hwid_limit !== null && (int) $hwid_limit > 0) {
            $params['hwidDeviceLimit'] = (int) $hwid_limit;
        }
        $res = $mgr->createUser($params, $invoice_id, $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'msg' => $res['message'] ?? 'create failed'];
        }
        $data = $res['data'];
        $userId = (string) ($data['id'] ?? '');
        $shortUuid = (string) ($data['shortUuid'] ?? '');
        $subUrl = (string) ($data['subscriptionUrl'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO remnawave_users (id_user, id_order, username, name_panel, panel_uuid, panel_user_id, short_uuid, subscription_url, status, created_at) VALUES (:iu, :io, :un, :np, :pu, :pid, :su, :sub, 'active', :ca)");
        $stmt->execute([
            ':iu' => (string) ($data['telegramId'] ?? ''),
            ':io' => (string) $invoice_id,
            ':un' => $username_ac,
            ':np' => $name_panel,
            ':pu' => (string) ($data['uuid'] ?? ''),
            ':pid' => $userId,
            ':su' => $shortUuid,
            ':sub' => $subUrl,
            ':ca' => (string) time(),
        ]);
        $createdConfigs = [];
        if (method_exists(\Faoxima\Services\Panels\Drivers\Remnawave::class, 'remnawave_get_configs')) {
            $cfgRes = self::remnawave_get_configs($name_panel, $username_ac);
            if (($cfgRes['status'] ?? '') === 'successful' && !empty($cfgRes['links']) && is_array($cfgRes['links'])) {
                $createdConfigs = array_values(array_filter($cfgRes['links'], function ($c) {
                    return is_string($c) && trim($c) !== '';
                }));
            }
        }
        return [
            'status' => 'successful',
            'username' => $username_ac,
            'subscription_url' => $subUrl,
            'uuid' => $userId,
            'configs' => $createdConfigs,
        ];
    }

    public static function remnawave_user_data($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        if (empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User ID Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->getUserById($local['panel_user_id'], $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'fetch failed'];
        }
        return ['status' => 'successful', 'data' => $res['data'], 'local' => $local];
    }

    public static function remnawave_extend($name_panel, $username, $timestamp)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->extendUser($local['panel_user_id'], self::remnawave_iso_from_timestamp($timestamp), $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_extra_volume($name_panel, $username, $new_limit_bytes)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->addVolume($local['panel_user_id'], (int) $new_limit_bytes, $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_change_status($name_panel, $username, $enable)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $enable ? $mgr->enableUser($local['panel_user_id'], $pdo) : $mgr->disableUser($local['panel_user_id'], $pdo);
        if (!empty($res['ok'])) {
            $upd = $pdo->prepare("UPDATE remnawave_users SET status = :s WHERE id = :id");
            $upd->execute([':s' => $enable ? 'active' : 'disabled', ':id' => $local['id']]);
        }
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_remove_user($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->disableUser($local['panel_user_id'], $pdo);
        $upd = $pdo->prepare("UPDATE remnawave_users SET status = 'removed' WHERE id = :id");
        $upd->execute([':id' => $local['id']]);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_revoke($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->revokeUser($local['panel_user_id'], false, $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'revoke failed'];
        }
        $d = $res['data'];
        $newShort = (string) ($d['shortUuid'] ?? '');
        $newSub = (string) ($d['subscriptionUrl'] ?? '');
        if ($newShort !== '' || $newSub !== '') {
            $upd = $pdo->prepare("UPDATE remnawave_users SET short_uuid = :su, subscription_url = :sub WHERE id = :id");
            $upd->execute([
                ':su' => $newShort !== '' ? $newShort : (string) ($local['short_uuid'] ?? ''),
                ':sub' => $newSub !== '' ? $newSub : (string) ($local['subscription_url'] ?? ''),
                ':id' => $local['id'],
            ]);
        }
        return ['status' => 'successful', 'subscription_url' => $newSub];
    }

    public static function remnawave_reset_credentials($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->revokeUser($local['panel_user_id'], true, $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_user_data_count($name_panel)
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM remnawave_users WHERE name_panel = :n AND status <> 'removed'");
            $stmt->execute([':n' => $name_panel]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function remnawave_format_bytes($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 بایت';
        }
        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت', 'پتابایت'];
        $i = (int) floor(log($bytes, 1024));
        if ($i < 0) {
            $i = 0;
        }
        if ($i >= count($units)) {
            $i = count($units) - 1;
        }
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public static function remnawave_panel_version($name_panel)
    {
        global $pdo;
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return null;
        }
        $res = $mgr->getSystemMetadata($pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return null;
        }
        $version = trim((string) ($res['data']['version'] ?? ''));
        return $version !== '' ? $version : null;
    }

    public static function remnawave_system_stats($name_panel)
    {
        global $pdo;
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['ok' => false];
        }
        $res = $mgr->getSystemStats($pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['ok' => false];
        }
        $d = $res['data'];
        $users = is_array($d['users'] ?? null) ? $d['users'] : [];
        $statusCounts = is_array($users['statusCounts'] ?? null) ? $users['statusCounts'] : [];
        $memory = is_array($d['memory'] ?? null) ? $d['memory'] : [];
        $nodes = is_array($d['nodes'] ?? null) ? $d['nodes'] : [];
        $onlineStats = is_array($d['onlineStats'] ?? null) ? $d['onlineStats'] : [];
        $cpu = is_array($d['cpu'] ?? null) ? $d['cpu'] : [];
        $totalUsers = (int) ($users['totalUsers'] ?? 0);
        $activeUsers = (int) ($statusCounts['ACTIVE'] ?? 0);
        $totalTraffic = (float) ($nodes['totalBytesLifetime'] ?? 0);
        $memTotal = (float) ($memory['total'] ?? 0);
        $memUsed = (float) ($memory['used'] ?? 0);
        return [
            'ok' => true,
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'disabled_users' => (int) ($statusCounts['DISABLED'] ?? 0),
            'limited_users' => (int) ($statusCounts['LIMITED'] ?? 0),
            'expired_users' => (int) ($statusCounts['EXPIRED'] ?? 0),
            'online_now' => (int) ($onlineStats['onlineNow'] ?? 0),
            'online_last_day' => (int) ($onlineStats['lastDay'] ?? 0),
            'online_last_week' => (int) ($onlineStats['lastWeek'] ?? 0),
            'never_online' => (int) ($onlineStats['neverOnline'] ?? 0),
            'total_traffic' => $totalTraffic,
            'mem_total' => $memTotal,
            'mem_used' => $memUsed,
            'cpu_cores' => (int) ($cpu['cores'] ?? 0),
            'uptime_seconds' => (float) ($d['uptime'] ?? 0),
            'nodes_online' => (int) ($nodes['totalOnline'] ?? 0),
        ];
    }

    public static function remnawave_format_uptime($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds <= 0) {
            return 'نامشخص';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days} روز";
        }
        if ($hours > 0) {
            $parts[] = "{$hours} ساعت";
        }
        if ($minutes > 0 || count($parts) === 0) {
            $parts[] = "{$minutes} دقیقه";
        }
        return implode(' و ', $parts);
    }

    public static function remnawave_get_configs($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null) {
            return ['status' => 'Unsuccessful', 'links' => []];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'links' => []];
        }
        $links = [];
        $shortUuid = trim((string) ($local['short_uuid'] ?? ''));
        if ($shortUuid !== '') {
            $res = $mgr->getSubscriptionByShortUuid($shortUuid, $pdo);
            if (!empty($res['ok']) && is_array($res['data'])) {
                $d = $res['data'];
                $rwUser = is_array($d['user'] ?? null) ? $d['user'] : $d;
                $candidates = $rwUser['links'] ?? ($d['links'] ?? ($rwUser['ssConfLinks'] ?? ($d['ssConfLinks'] ?? [])));
                if (is_array($candidates)) {
                    foreach ($candidates as $c) {
                        if (is_string($c) && $c !== '') {
                            $links[] = $c;
                        } elseif (is_array($c) && !empty($c['link'])) {
                            $links[] = (string) $c['link'];
                        }
                    }
                }
            }
        }
        if (count($links) === 0) {
            $sub = (string) ($local['subscription_url'] ?? '');
            if ($sub !== '' && method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'outputlunk')) {
                $fetched = \Faoxima\Services\Delivery\ServiceDelivery::outputlunk($sub);
                if (is_string($fetched) && $fetched !== '') {
                    if (method_exists(\Faoxima\Core\Support\Formatting::class, 'isBase64') && \Faoxima\Core\Support\Formatting::isBase64($fetched)) {
                        $fetched = base64_decode($fetched);
                    }
                    foreach (preg_split("/\r\n|\n|\r/", $fetched) as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $links[] = $part;
                        }
                    }
                }
            }
        }
        if (count($links) === 0) {
            $sub = (string) ($local['subscription_url'] ?? '');
            if ($sub !== '') {
                $links[] = $sub;
            }
        }
        return ['status' => count($links) > 0 ? 'successful' : 'Unsuccessful', 'links' => $links];
    }

    public static function remnawave_get_configs_by_protocol($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null) {
            return ['status' => 'Unsuccessful', 'protocols' => []];
        }
        $shortUuid = trim((string) ($local['short_uuid'] ?? ''));
        if ($shortUuid === '') {
            return ['status' => 'Unsuccessful', 'protocols' => []];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'protocols' => []];
        }
        $res = $mgr->getRawSubscriptionByShortUuid($shortUuid, $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'protocols' => []];
        }
        $hosts = is_array($res['data']['resolvedProxyConfigs'] ?? null) ? $res['data']['resolvedProxyConfigs'] : [];
        $protocols = [];
        foreach ($hosts as $host) {
            if (!is_array($host)) {
                continue;
            }
            $protocol = strtolower((string) ($host['protocol'] ?? ''));
            if ($protocol === '') {
                continue;
            }
            $entry = [
                'protocol' => $protocol,
                'remark' => (string) ($host['finalRemark'] ?? ''),
                'address' => (string) ($host['address'] ?? ''),
                'port' => (int) ($host['port'] ?? 0),
                'options' => is_array($host['protocolOptions'] ?? null) ? $host['protocolOptions'] : [],
            ];
            if (!isset($protocols[$protocol])) {
                $protocols[$protocol] = [];
            }
            $protocols[$protocol][] = $entry;
        }
        return ['status' => count($protocols) > 0 ? 'successful' : 'Unsuccessful', 'protocols' => $protocols];
    }

    public static function remnawave_get_nodes($name_panel)
    {
        global $pdo;
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'nodes' => []];
        }
        $res = $mgr->getNodes($pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'nodes' => []];
        }
        $nodes = [];
        foreach ($res['data'] as $n) {
            if (!is_array($n)) {
                continue;
            }
            $sys = is_array($n['system'] ?? null) ? $n['system'] : [];
            $stats = is_array($sys['stats'] ?? null) ? $sys['stats'] : [];
            $info = is_array($sys['info'] ?? null) ? $sys['info'] : [];
            $memTotal = (float) ($info['memoryTotal'] ?? 0);
            $memFree = (float) ($stats['memoryFree'] ?? 0);
            $memPct = $memTotal > 0 ? (int) round((($memTotal - $memFree) / $memTotal) * 100) : 0;
            $load = is_array($stats['loadAvg'] ?? null) ? $stats['loadAvg'] : [];
            $nodes[] = [
                'name' => (string) ($n['name'] ?? ''),
                'countryCode' => (string) ($n['countryCode'] ?? ''),
                'usersOnline' => (int) ($n['usersOnline'] ?? 0),
                'isConnected' => !empty($n['isConnected']),
                'isDisabled' => !empty($n['isDisabled']),
                'loadAvg' => isset($load[0]) ? round((float) $load[0], 2) : 0,
                'memPct' => $memPct,
            ];
        }
        return ['status' => 'successful', 'nodes' => $nodes];
    }

    public static function remnawave_node_name($name_panel, $nodeUuid)
    {
        global $pdo;
        $nodeUuid = trim((string) $nodeUuid);
        if ($nodeUuid === '') {
            return '';
        }
        $map = [];
        $ts = 0;
        try {
            $sel = $pdo->prepare("SELECT cache_json, ts FROM remnawave_nodes_cache WHERE name_panel = :n LIMIT 1");
            $sel->execute([':n' => $name_panel]);
            $cacheRow = $sel->fetch(PDO::FETCH_ASSOC);
            if (is_array($cacheRow)) {
                $decoded = json_decode((string) ($cacheRow['cache_json'] ?? ''), true);
                if (is_array($decoded)) {
                    $map = $decoded;
                }
                $ts = (int) ($cacheRow['ts'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log("[REMNAWAVE-NODECACHE] read | " . $e->getMessage());
        }
        if (empty($map) || (time() - $ts) >= 600) {
            $mgr = self::remnawave_manager_for($name_panel);
            if ($mgr !== null) {
                $res = $mgr->getNodes($pdo);
                if (!empty($res['ok']) && is_array($res['data'])) {
                    $map = [];
                    foreach ($res['data'] as $n) {
                        if (is_array($n) && !empty($n['uuid'])) {
                            $cc = (string) ($n['countryCode'] ?? '');
                            $map[(string) $n['uuid']] = (string) ($n['name'] ?? '') . ($cc !== '' ? " ($cc)" : '');
                        }
                    }
                    try {
                        $ins = $pdo->prepare("INSERT INTO remnawave_nodes_cache (name_panel, cache_json, ts) VALUES (:n, :c, :t) ON DUPLICATE KEY UPDATE cache_json = :c2, ts = :t2");
                        $json = json_encode($map, JSON_UNESCAPED_UNICODE);
                        $now = time();
                        $ins->execute([':n' => $name_panel, ':c' => $json, ':t' => $now, ':c2' => $json, ':t2' => $now]);
                    } catch (Throwable $e) {
                        error_log("[REMNAWAVE-NODECACHE] write | " . $e->getMessage());
                    }
                }
            }
        }
        return (string) ($map[$nodeUuid] ?? '');
    }

    public static function remnawave_set_hwid_limit($name_panel, $username, $limit)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->updateUser(['id' => (int) $local['panel_user_id'], 'hwidDeviceLimit' => (int) $limit], null, $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_get_user_hwid_devices($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'total' => 0, 'devices' => []];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'total' => 0, 'devices' => []];
        }
        $res = $mgr->getUserHwidDevices($local['panel_user_id'], $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'total' => 0, 'devices' => []];
        }
        $devices = is_array($res['data']['devices'] ?? null) ? $res['data']['devices'] : [];
        return [
            'status' => 'successful',
            'total' => (int) ($res['data']['total'] ?? count($devices)),
            'devices' => $devices,
        ];
    }

    public static function remnawave_set_squads($name_panel, $username, array $squadUuids)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->setUserSquads($local['panel_user_id'], $squadUuids, $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_user_by_tg($name_panel, $telegramId)
    {
        global $pdo;
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'data' => null];
        }
        $res = $mgr->getUserByTelegramId($telegramId, $pdo);
        if (empty($res['ok'])) {
            return ['status' => 'Unsuccessful', 'data' => null];
        }
        return ['status' => 'successful', 'data' => $res['data']];
    }

    public static function remnawave_flush_hwid($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->flushHwid($local['panel_user_id'], $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_set_tag($name_panel, $username, $tag)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'msg' => 'User Not Found'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'msg' => 'Panel Not Found'];
        }
        $res = $mgr->updateUser(['id' => (int) $local['panel_user_id'], 'tag' => (string) $tag], null, $pdo);
        return ['status' => !empty($res['ok']) ? 'successful' : 'Unsuccessful'];
    }

    public static function remnawave_user_bandwidth($name_panel, $username)
    {
        global $pdo;
        $local = self::remnawave_find_user($username);
        if ($local === null || empty($local['panel_user_id'])) {
            return ['status' => 'Unsuccessful', 'text' => ''];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'text' => ''];
        }
        $end = gmdate('Y-m-d');
        $start = gmdate('Y-m-d', time() - (30 * 86400));
        $res = $mgr->getUserBandwidth($local['panel_user_id'], $start, $end, 10, $pdo);
        if (empty($res['ok']) || !is_array($res['data'])) {
            return ['status' => 'Unsuccessful', 'text' => ''];
        }
        $top = is_array($res['data']['topNodes'] ?? null) ? $res['data']['topNodes'] : [];
        if (count($top) === 0) {
            return ['status' => 'successful', 'text' => 'در ۳۰ روز اخیر مصرفی ثبت نشده است.'];
        }
        $lines = [];
        foreach ($top as $node) {
            if (!is_array($node)) {
                continue;
            }
            $nm = (string) ($node['name'] ?? '');
            $cc = (string) ($node['countryCode'] ?? '');
            $tot = self::remnawave_format_bytes((float) ($node['total'] ?? 0));
            $lines[] = '🌍 ' . $nm . ($cc !== '' ? " ($cc)" : '') . ' : ' . $tot;
        }
        return ['status' => 'successful', 'text' => implode("\n", $lines)];
    }

    public static function remnawave_bulk_extend($name_panel, $days)
    {
        global $pdo;
        $days = (int) $days;
        if ($days < 1) {
            return ['status' => 'Unsuccessful', 'count' => 0, 'msg' => 'invalid days'];
        }
        $mgr = self::remnawave_manager_for($name_panel);
        if ($mgr === null) {
            return ['status' => 'Unsuccessful', 'count' => 0, 'msg' => 'Panel Not Found'];
        }
        $stmt = $pdo->prepare("SELECT panel_user_id FROM remnawave_users WHERE name_panel = :n AND status = 'active' AND panel_user_id IS NOT NULL AND panel_user_id <> ''");
        $stmt->execute([':n' => $name_panel]);
        $userIds = array_values(array_filter(array_map(function ($r) {
            return (int) ($r['panel_user_id'] ?? 0);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC)), function ($v) {
            return $v > 0;
        }));
        if (count($userIds) === 0) {
            return ['status' => 'Unsuccessful', 'count' => 0, 'msg' => 'no active users'];
        }
        $ok = 0;
        foreach (array_chunk($userIds, 500) as $chunk) {
            $res = $mgr->bulkExtend($chunk, $days, $pdo);
            if (!empty($res['ok'])) {
                $ok += count($chunk);
            }
        }
        return ['status' => $ok > 0 ? 'successful' : 'Unsuccessful', 'count' => $ok];
    }

    public static function remnawave_admin_log($button, $userId, $action, $error)
    {
        error_log("[REMNAWAVE-ADMIN-ERROR] Button: $button | UserID: $userId | Action: $action | Error: $error");
    }

    public static function remnawave_capability_report(array $panel, array &$report)
    {
        global $pdo;
        $mgr = new RemnawaveManager($panel);
        if (trim((string) ($panel['remna_api_token'] ?? '')) === '') {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', 'توکن API برای این پنل تنظیم نشده است.');
            \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
            return;
        }
        $test = $mgr->testConnection($pdo);
        if (empty($test['ok'])) {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'failed', \Faoxima\Services\Panels\PanelCapability::rx_cap_clean_text((string) ($test['message'] ?? ''), 160));
            \Faoxima\Services\Panels\PanelCapability::rx_cap_mark_untested_after_connection_failure($report);
            return;
        }
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'connection', 'verified', 'GET /api/system/stats موفق بود.');
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set_writes($report, 'unknown', 'رمن‌ویو برای توکن API متادیتای نقش یا مجوز ارائه نمی‌دهد؛ عملیات نوشتنی تست نشد.');

        $list = $mgr->probeGet('/api/users?size=1&start=0', $pdo);
        $listRead = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($list);
        if ($listRead[0] === 'verified') {
            $total = $list['json']['response']['total'] ?? null;
            $listRead[1] = 'GET /api/users موفق بود' . ($total !== null ? '؛ تعداد کل: ' . (int) $total : '') . '.';
        }
        \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'list_users', $listRead[0], $listRead[1]);

        \Faoxima\Services\Panels\PanelCapability::rx_cap_probe_sample_user_via_manager($panel, $report);

        $squads = $mgr->probeGet('/api/internal-squads', $pdo);
        $squadRead = \Faoxima\Services\Panels\PanelCapability::rx_cap_http_read($squads);
        if ($squadRead[0] === 'verified') {
            $list = $squads['json']['response']['internalSquads'] ?? [];
            $available = [];
            foreach ((array) $list as $squad) {
                if (is_array($squad) && !empty($squad['uuid'])) {
                    $available[] = strtolower((string) $squad['uuid']);
                }
            }
            $configured = array_values(array_filter(array_map(function ($v) {
                return strtolower(trim($v));
            }, explode(',', (string) ($panel['remna_squad'] ?? '')))));
            $missing = array_values(array_diff($configured, $available));
            $note = count($available) . ' Squad قابل مشاهده.';
            if (empty($configured)) {
                $note .= ' Squad پیش‌فرضی در ربات تنظیم نشده است.';
            } elseif (!empty($missing)) {
                $note .= ' ' . count($missing) . ' Squad تنظیم‌شده در پنل یافت نشد.';
            }
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', (empty($missing) && !empty($configured)) ? 'verified' : 'failed', $note);
        } else {
            \Faoxima\Services\Panels\PanelCapability::rx_cap_set($report, 'resources', $squadRead[0], 'GET /api/internal-squads: ' . $squadRead[1]);
        }
    }
}
