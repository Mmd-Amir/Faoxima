<?php

if (!function_exists('rxFileNamingFlagOn')) {
    function rxFileNamingFlagOn($value)
    {
        $value = strtolower(trim((string) $value));
        return !in_array($value, ['', '0', 'off', 'false', 'no', 'disabled'], true);
    }
}

if (!function_exists('rxFileNamingSettingRow')) {
    function rxFileNamingSettingRow($refresh = false)
    {
        static $row = null;
        if ($refresh) {
            $row = null;
        }
        if (is_array($row)) {
            return $row;
        }
        $setting = null;
        $pdo = $GLOBALS['pdo'] ?? null;
        try {
            if ($pdo instanceof PDO) {
                $setting = $pdo->query('SELECT config_file_custom_status FROM setting LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            } elseif (function_exists('select')) {
                $setting = select('setting', '*', null, null, 'select', ['cache' => false]);
            }
        } catch (Throwable $e) {
            error_log('[file naming setting] ' . $e->getMessage());
        }
        $row = is_array($setting) ? $setting : [];
        if (array_key_exists('config_file_custom_status', $row) && isset($GLOBALS['setting']) && is_array($GLOBALS['setting'])) {
            $GLOBALS['setting']['config_file_custom_status'] = $row['config_file_custom_status'];
        }
        return $row;
    }
}

if (!function_exists('rxFileNamingEnabled')) {
    function rxFileNamingEnabled($refresh = false)
    {
        static $enabled = null;
        if ($refresh) {
            $enabled = null;
        }
        if ($enabled !== null) {
            return $enabled;
        }
        $row = rxFileNamingSettingRow($refresh);
        $enabled = array_key_exists('config_file_custom_status', $row)
            ? rxFileNamingFlagOn($row['config_file_custom_status'])
            : false;
        return $enabled;
    }
}

if (!function_exists('rxFileNamingText')) {
    function rxFileNamingText($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $clean = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $value);
        if ($clean !== null) {
            $value = $clean;
        }
        $spaced = preg_replace('/\s+/u', ' ', $value);
        if ($spaced !== null) {
            $value = $spaced;
        }
        return trim($value);
    }
}

if (!function_exists('rxFileNamingKeyText')) {
    function rxFileNamingKeyText($value)
    {
        $value = rxFileNamingText($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 160, 'UTF-8');
        } else {
            $value = substr($value, 0, 160);
        }
        return $value;
    }
}

if (!function_exists('rxFileNamingKeyLabel')) {
    function rxFileNamingKeyLabel($label)
    {
        $value = rxFileNamingKeyText($label);
        return $value === '' ? '' : 'label:' . $value;
    }
}

if (!function_exists('rxFileNamingKeyEndpoint')) {
    function rxFileNamingKeyEndpoint($endpoint)
    {
        $value = rxFileNamingKeyText($endpoint);
        return $value === '' ? '' : 'endpoint:' . $value;
    }
}

if (!function_exists('rxFileNamingKeyFile')) {
    function rxFileNamingKeyFile($name)
    {
        $name = rxFileNamingText($name);
        if ($name === '') {
            return '';
        }
        $base = basename(str_replace('\\', '/', $name));
        $stem = (string) pathinfo($base, PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = $base;
        }
        $value = rxFileNamingKeyText($stem);
        return $value === '' ? '' : 'file:' . $value;
    }
}

if (!function_exists('rxFileNamingScopedKey')) {
    function rxFileNamingScopedKey($key, $protocol)
    {
        $key = (string) $key;
        if ($key === '') {
            return '';
        }
        $separator = strpos($key, ':');
        if ($separator === false) {
            return '';
        }
        return substr($key, 0, $separator + 1) . strtolower((string) $protocol) . '|' . hash('sha256', $key);
    }
}

if (!function_exists('rxFileNamingJovadDescriptor')) {
    function rxFileNamingJovadDescriptor($file, $kind)
    {
        $name = trim((string) ($file['name'] ?? ''));
        $server = trim((string) ($file['server_id'] ?? ''));
        if ($server === '') {
            $servers = [];
            foreach ((array) ($file['alternatives'] ?? []) as $alternative) {
                $id = trim((string) ($alternative['server_id'] ?? ''));
                if ($id !== '') {
                    $servers[$id] = true;
                }
            }
            if (count($servers) > 1) {
                return null;
            }
            $server = (string) (array_key_first($servers) ?? '');
        }
        $basis = $server !== '' ? 'server' : 'file';
        if ($name === '') {
            $name = basename((string) parse_url((string) ($file['url'] ?? ''), PHP_URL_PATH));
        }
        $key = $server !== '' ? 'server:' . $server : rxFileNamingKeyFile($name);
        if ($key === '') {
            return null;
        }
        return [
            'source' => 'jovad',
            'key' => rxFileNamingScopedKey($key, $kind),
            'label' => $name !== '' ? $name : $server,
            'basis' => $basis,
            'ext' => $kind === 'openvpn' ? 'ovpn' : 'conf',
            'protocol' => $kind,
        ];
    }
}

if (!function_exists('rxFileNamingKeyRecord')) {
    function rxFileNamingKeyRecord($id)
    {
        $id = (int) $id;
        return $id > 0 ? 'record:' . $id : '';
    }
}

if (!function_exists('rxFileNamingKeySubscription')) {
    function rxFileNamingKeySubscription($inboundId)
    {
        $value = rxFileNamingKeyText($inboundId);
        return $value === '' ? '' : 'sub:' . $value;
    }
}

if (!function_exists('rxFileNamingPanelRef')) {
    function rxFileNamingPanelRef($panel)
    {
        if (!is_array($panel)) {
            return '';
        }
        $code = trim((string) ($panel['code_panel'] ?? ''));
        if ($code !== '') {
            return 'code:' . $code;
        }
        $name = trim((string) ($panel['name_panel'] ?? ''));
        return $name !== '' ? 'name:' . $name : '';
    }
}

if (!function_exists('rxFileNamingPanelByRef')) {
    function rxFileNamingPanelByRef($ref)
    {
        static $cache = [];
        $ref = trim((string) $ref);
        if ($ref === '' || !function_exists('select')) {
            return null;
        }
        if (array_key_exists($ref, $cache)) {
            return $cache[$ref];
        }
        $panel = false;
        if (strpos($ref, 'code:') === 0) {
            $panel = select('marzban_panel', '*', 'code_panel', substr($ref, 5), 'select', ['cache' => false]);
        } elseif (strpos($ref, 'name:') === 0) {
            $panel = select('marzban_panel', '*', 'name_panel', substr($ref, 5), 'select', ['cache' => false]);
        }
        $cache[$ref] = is_array($panel) ? $panel : null;
        return $cache[$ref];
    }
}

if (!function_exists('rxFileNamingTableReady')) {
    function rxFileNamingTableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO)) {
            $ready = false;
            return $ready;
        }
        try {
            $pdo->query('SELECT 1 FROM config_file_rule LIMIT 1');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('rxFileNamingRuleIndexKey')) {
    function rxFileNamingRuleIndexKey($panelRef, $source, $key)
    {
        $separator = "\x1F";
        return rxFileNamingKeyText($panelRef) . $separator . rxFileNamingKeyText($source) . $separator . rxFileNamingKeyText($key);
    }
}

if (!function_exists('rxFileNamingLegacyRuleKeys')) {
    function rxFileNamingLegacyRuleKeys($rule)
    {
        if (!is_array($rule)) return [];
        $key = rxFileNamingKeyText($rule['match_key'] ?? '');
        $source = (string) ($rule['source'] ?? '');
        $extension = strtolower(ltrim((string) ($rule['file_ext'] ?? ''), '.'));
        if (strpos($key, '|') !== false || !preg_match('/^(label|endpoint|file):.+$/u', $key)) return [];
        $protocols = [];
        if (in_array($source, ['xui_wg', 'xui_file'], true) && $extension === 'conf') {
            $protocols = ['wireguard', 'amneziawg'];
        } elseif ($source === 'rebecca') {
            if ($extension === 'conf') $protocols = ['wireguard'];
            if ($extension === 'ovpn') $protocols = ['openvpn'];
        }
        $keys = [];
        foreach ($protocols as $protocol) $keys[] = rxFileNamingScopedKey($key, $protocol);
        return $keys;
    }
}

if (!function_exists('rxFileNamingRules')) {
    function rxFileNamingRules($refresh = false)
    {
        static $rules = null;
        if ($rules !== null && !$refresh) {
            return $rules;
        }
        $rules = [];
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!($pdo instanceof PDO) || !rxFileNamingTableReady()) {
            return $rules;
        }
        try {
            $stmt = $pdo->query('SELECT id, panel_ref, panel_code, panel_name, panel_type, source, source_service, match_key, match_label, match_basis, file_ext, custom_filename, custom_caption, is_enabled FROM config_file_rule WHERE is_enabled = 1');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $rules;
        }
        $legacyAliases = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rxRulePanelRef = rxFileNamingKeyText((string) ($row['panel_ref'] ?? ''));
            $rxRuleSource = rxFileNamingKeyText((string) ($row['source'] ?? ''));
            $rxRuleKey = rxFileNamingKeyText((string) ($row['match_key'] ?? ''));
            if ($rxRulePanelRef === '' || $rxRuleSource === '' || $rxRuleKey === '') {
                continue;
            }
            $index = rxFileNamingRuleIndexKey($rxRulePanelRef, $rxRuleSource, $rxRuleKey);
            if (array_key_exists($index, $rules)) {
                $rules[$index] = null;
                foreach (rxFileNamingLegacyRuleKeys($row) as $alias) {
                    $legacyAliases[rxFileNamingRuleIndexKey($rxRulePanelRef, $rxRuleSource, $alias)] = null;
                }
                continue;
            }
            $rules[$index] = $row;
            foreach (rxFileNamingLegacyRuleKeys($row) as $alias) {
                $aliasIndex = rxFileNamingRuleIndexKey($rxRulePanelRef, $rxRuleSource, $alias);
                if (array_key_exists($aliasIndex, $legacyAliases)) {
                    $legacyAliases[$aliasIndex] = null;
                } else {
                    $legacyRow = $row;
                    $legacyRow['_legacy_match'] = true;
                    $legacyAliases[$aliasIndex] = $legacyRow;
                }
            }
        }
        foreach ($legacyAliases as $index => $row) {
            if (!array_key_exists($index, $rules)) $rules[$index] = $row;
        }
        return $rules;
    }
}

if (!function_exists('rxFileNamingLookup')) {
    function rxFileNamingLookup($panelRef, $source, $key)
    {
        if ($panelRef === '' || $source === '' || $key === '') {
            return null;
        }
        $rules = rxFileNamingRules();
        $index = rxFileNamingRuleIndexKey($panelRef, $source, $key);
        if (!array_key_exists($index, $rules)) {
            return null;
        }
        $rule = $rules[$index];
        return is_array($rule) ? $rule : null;
    }
}

if (!function_exists('rxFileNamingSafeName')) {
    function rxFileNamingSafeName($name)
    {
        $name = (string) $name;
        if ($name === '') {
            return '';
        }
        $name = str_replace(["\0", "\r", "\n", "\t", '\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $name);
        $stripped = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        if ($stripped !== null) {
            $name = $stripped;
        }
        $name = trim($name);
        $name = trim($name, " \t\n\r\0\x0B.");
        if ($name === '' || $name === '.' || $name === '..') {
            return '';
        }
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length > 180) {
            $name = function_exists('mb_substr') ? mb_substr($name, 0, 180, 'UTF-8') : substr($name, 0, 180);
        }
        return $name;
    }
}

if (!function_exists('rxFileNamingNormalizeName')) {
    function rxFileNamingNormalizeName($stem, $ext, $wireguard = false)
    {
        $stem = str_replace('_', '-', (string) $stem);
        $stem = $wireguard
            ? preg_replace('/[^A-Za-z0-9.-]+/', '-', $stem)
            : preg_replace('/[^\p{L}\p{N}.-]+/u', '-', $stem);
        $stem = trim((string) preg_replace('/-+/', '-', (string) $stem), '-.');
        if ($wireguard) {
            $stem = substr($stem, 0, 15);
            $stem = rtrim($stem, '-.');
            $ext = 'conf';
        }
        $ext = strtolower(ltrim(trim((string) $ext), '.'));
        if ($stem === '') {
            return '';
        }
        if ($ext === '') {
            $ext = $wireguard ? 'conf' : 'bin';
        }
        return $stem . '.' . $ext;
    }
}

if (!function_exists('rxFileNamingFinalFilename')) {
    function rxFileNamingFinalFilename($custom, $ext, $wireguard = false)
    {
        $custom = rxFileNamingSafeName($custom);
        if ($custom === '') {
            return '';
        }
        $ext = strtolower(ltrim(trim((string) $ext), '.'));
        if ($ext === '') {
            $ext = $wireguard ? 'conf' : 'bin';
        }
        $stem = (string) pathinfo($custom, PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = $custom;
        }
        $stem = rxFileNamingSafeName($stem);
        if ($stem === '') {
            return '';
        }
        $probe = str_replace('_', '-', $stem);
        $probe = $wireguard
            ? preg_replace('/[^A-Za-z0-9.-]+/', '-', $probe)
            : preg_replace('/[^\p{L}\p{N}.-]+/u', '-', $probe);
        $probe = trim((string) preg_replace('/-+/', '-', (string) $probe), '-.');
        if ($probe === '') {
            return '';
        }
        if (function_exists('normalizeConfigFilename')) {
            $final = normalizeConfigFilename($stem . '.' . $ext, '', $wireguard);
        } else {
            $final = rxFileNamingNormalizeName($stem, $ext, $wireguard);
        }
        return rxFileNamingSafeName($final);
    }
}

if (!function_exists('rxFileNamingValidateCustomName')) {
    function rxFileNamingValidateCustomName($custom, $ext, $wireguard = false)
    {
        $input = trim((string) $custom);
        $final = rxFileNamingFinalFilename($input, $ext, $wireguard);
        return [
            'ok' => $final !== '',
            'input' => $input,
            'filename' => $final,
            'changed' => ($final !== '' && $final !== $input),
            'error' => $final === '' ? 'unusable_filename' : '',
        ];
    }
}

if (!function_exists('rxFileNamingTrimCaption')) {
    function rxFileNamingTrimCaption($text, $limit = 1024)
    {
        $text = (string) $text;
        $text = str_replace("\r\n", "\n", $text);
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        if ($stripped !== null) {
            $text = $stripped;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length > (int) $limit) {
            $text = function_exists('mb_substr') ? mb_substr($text, 0, (int) $limit, 'UTF-8') : substr($text, 0, (int) $limit);
        }
        return trim($text);
    }
}

if (!function_exists('rxFileNamingMergeCaption')) {
    function rxFileNamingMergeCaption($base, $custom, bool $preserveFull = false)
    {
        $custom = rxFileNamingTrimCaption($custom, 1024);
        $base = (string) $base;
        if ($custom === '') {
            return $base;
        }
        $rxFnLen = function ($text) {
            return function_exists('mb_convert_encoding')
                ? (int) (strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2)
                : strlen($text);
        };
        $separator = trim($base) === '' ? '' : "\n\n";
        if ($preserveFull) {
            return $base . $separator . htmlspecialchars($custom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $plainBase = html_entity_decode(strip_tags($base), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $allowance = 1024 - $rxFnLen($plainBase) - strlen($separator);
        if ($allowance <= 0) {
            return $base;
        }
        $characters = preg_split('//u', $custom, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            return $base;
        }
        $clipped = '';
        foreach ($characters as $character) {
            $length = $rxFnLen($character);
            if ($length > $allowance) {
                break;
            }
            $clipped .= $character;
            $allowance -= $length;
        }
        $clipped = rtrim($clipped);
        return $clipped === '' ? $base : $base . $separator . htmlspecialchars($clipped, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('rxFileNamingResolve')) {
    function rxFileNamingResolve($panel, $source, $key, $originalName, $originalCaption, $ambiguous = false)
    {
        $result = [
            'filename' => (string) $originalName,
            'caption' => (string) $originalCaption,
            'applied' => false,
            'rule_id' => 0,
        ];
        if ($ambiguous || !rxFileNamingEnabled()) {
            return $result;
        }
        $panelRef = rxFileNamingPanelRef($panel);
        $rule = rxFileNamingLookup($panelRef, (string) $source, (string) $key);
        if (!is_array($rule)) {
            return $result;
        }
        $ext = strtolower((string) pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(ltrim(trim((string) ($rule['file_ext'] ?? '')), '.'));
        }
        $wireguard = ($ext === 'conf');
        $filename = rxFileNamingFinalFilename((string) ($rule['custom_filename'] ?? ''), $ext, $wireguard);
        if ($filename !== '') {
            $result['filename'] = $filename;
        }
        $caption = rxFileNamingMergeCaption($originalCaption, (string) ($rule['custom_caption'] ?? ''), true);
        if ($caption !== '') {
            $result['caption'] = $caption;
        }
        $result['applied'] = true;
        $result['rule_id'] = (int) ($rule['id'] ?? 0);
        return $result;
    }
}

if (!function_exists('rxFileNamingDescriptorFromLink')) {
    function rxFileNamingDescriptorFromLink($link)
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
        if ($scheme === 'http' || $scheme === 'https') {
            $download = function_exists('rebeccaConfigDownloadInfo') ? rebeccaConfigDownloadInfo($link) : null;
            if (!is_array($download)) {
                return null;
            }
            $path = (string) parse_url((string) $download['url'], PHP_URL_PATH);
            $kind = (string) $download['kind'];
            $ext = $kind === 'wireguard' ? 'conf' : 'ovpn';
            $remark = '';
            $hashPos = strpos($link, '#');
            if ($hashPos !== false) {
                $remark = rawurldecode(substr($link, $hashPos + 1));
            }
            $remark = rxFileNamingText($remark);
            $base = basename($path);
            if ($remark !== '') {
                return [
                    'source' => 'rebecca',
                    'key' => rxFileNamingScopedKey(rxFileNamingKeyLabel($remark), $kind),
                    'label' => $remark,
                    'basis' => 'label',
                    'ext' => $ext,
                    'protocol' => $kind,
                ];
            }
            if ($base !== '') {
                return [
                    'source' => 'rebecca',
                    'key' => rxFileNamingScopedKey(rxFileNamingKeyFile($base), $kind),
                    'label' => $base,
                    'basis' => 'file',
                    'ext' => $ext,
                    'protocol' => $kind,
                ];
            }
            return null;
        }
        if (!in_array($scheme, ['vpn', 'awg', 'amneziawg', 'wireguard', 'wg'], true)) {
            return null;
        }
        $protocol = in_array($scheme, ['awg', 'amneziawg'], true) ? 'amneziawg' : 'wireguard';
        $remark = '';
        $hashPos = strpos($link, '#');
        if ($hashPos !== false) {
            $remark = rxFileNamingText(rawurldecode(substr($link, $hashPos + 1)));
        }
        $endpoint = '';
        $conf = null;
        if (function_exists('xui_wg_conf_from_link')) {
            $conf = xui_wg_conf_from_link($link);
            if (!is_array($conf)) {
                return null;
            }
            $protocol = (string) ($conf['protocol'] ?? $protocol);
            $body = (string) ($conf['conf'] ?? '');
            if ($remark === '' && preg_match('/^\s*#\s*(.+)$/m', $body, $matches)) {
                $remark = rxFileNamingText($matches[1]);
            }
            if (preg_match('/^\s*Endpoint\s*=\s*(.+)$/mi', $body, $matches)) {
                $endpoint = rxFileNamingText($matches[1]);
            }
        }
        if ($remark !== '') {
            return [
                'source' => 'xui_wg',
                'key' => rxFileNamingScopedKey(rxFileNamingKeyLabel($remark), $protocol),
                'label' => $remark,
                'basis' => 'label',
                'ext' => 'conf',
                'protocol' => $protocol,
            ];
        }
        if ($endpoint !== '') {
            return [
                'source' => 'xui_wg',
                'key' => rxFileNamingScopedKey(rxFileNamingKeyEndpoint($endpoint), $protocol),
                'label' => $endpoint,
                'basis' => 'endpoint',
                'ext' => 'conf',
                'protocol' => $protocol,
            ];
        }
        return null;
    }
}

if (!function_exists('rxFileNamingLinkKeyCounts')) {
    function rxFileNamingLinkKeyCounts($links, $panel = null)
    {
        $counts = [];
        $legacyCounts = [];
        $descriptors = [];
        foreach ((array) $links as $link) {
            $descriptor = rxFileNamingDescriptorFromLink($link);
            if (!is_array($descriptor) || (string) ($descriptor['key'] ?? '') === '') {
                continue;
            }
            $key = (string) $descriptor['key'];
            $counts[$key] = (isset($counts[$key]) ? (int) $counts[$key] : 0) + 1;
            $separator = strpos($key, '|');
            if ($separator !== false) {
                $legacyKey = $descriptor['source'] . ':' . substr($key, $separator + 1);
                $legacyCounts[$legacyKey] = ($legacyCounts[$legacyKey] ?? 0) + 1;
                $descriptors[$key] = [$descriptor['source'], $legacyKey];
            }
        }
        if (is_array($panel)) {
            $panelRef = rxFileNamingPanelRef($panel);
            foreach ($descriptors as $key => $descriptor) {
                $rule = rxFileNamingLookup($panelRef, $descriptor[0], $key);
                if (is_array($rule) && !empty($rule['_legacy_match'])) {
                    $counts[$key] = max($counts[$key], $legacyCounts[$descriptor[1]] ?? 0);
                }
            }
        }
        return $counts;
    }
}

if (!function_exists('rxFileNamingManualItemKey')) {
    function rxFileNamingManualItemKey($item)
    {
        if (!is_array($item)) {
            return '';
        }
        $id = (int) ($item['id'] ?? 0);
        if ($id > 0) {
            return rxFileNamingKeyRecord($id);
        }
        return rxFileNamingKeyFile($item['name'] ?? '');
    }
}

if (!function_exists('rxFileNamingManualKeyCounts')) {
    function rxFileNamingManualKeyCounts($items)
    {
        $counts = [];
        foreach ((array) $items as $item) {
            $key = rxFileNamingManualItemKey($item);
            if ($key === '') {
                continue;
            }
            $counts[$key] = (isset($counts[$key]) ? (int) $counts[$key] : 0) + 1;
        }
        return $counts;
    }
}

if (!function_exists('rxFileNamingResolveManualItem')) {
    function rxFileNamingResolveManualItem($item, $originalName, $originalCaption, $ambiguous = false)
    {
        if (!is_array($item)) {
            return ['filename' => (string) $originalName, 'caption' => (string) $originalCaption, 'applied' => false, 'rule_id' => 0];
        }
        $ref = (string) ($item['panel_ref'] ?? '');
        $panel = $ref !== '' ? rxFileNamingPanelByRef($ref) : null;
        $key = rxFileNamingManualItemKey($item);
        return rxFileNamingResolve($panel, 'manual', $key, $originalName, $originalCaption, $ambiguous);
    }
}

if (!function_exists('rxFileNamingDisplayNames')) {
    function rxFileNamingDisplayNames($panel, $links)
    {
        $names = [];
        if (!is_array($panel) || !rxFileNamingEnabled()) {
            return $names;
        }
        $hasPanelRules = false;
        $hasJovadRules = false;
        $panelRef = rxFileNamingKeyText(rxFileNamingPanelRef($panel));
        foreach (rxFileNamingRules() as $rule) {
            if (is_array($rule) && rxFileNamingKeyText($rule['panel_ref'] ?? '') === $panelRef) {
                $hasPanelRules = true;
                if (($rule['source'] ?? '') === 'jovad') $hasJovadRules = true;
            }
        }
        if (!$hasPanelRules) return $names;
        $links = array_values((array) $links);
        $counts = rxFileNamingLinkKeyCounts($links, $panel);
        $jovadGroups = [];
        foreach ($links as $index => $link) {
            if (!is_string($link)) continue;
            $descriptor = rxFileNamingDescriptorFromLink($link);
            if (is_array($descriptor)) {
                $resolved = rxFileNamingResolve($panel, $descriptor['source'], $descriptor['key'], 'config.' . $descriptor['ext'], '', ($counts[$descriptor['key']] ?? 0) > 1);
            } elseif (preg_match('~^(xuifile|xuiawgfile)://([^#]*)~i', $link, $match)) {
                $protocol = strtolower($match[1]) === 'xuiawgfile' ? 'amneziawg' : 'wireguard';
                $filename = rawurldecode($match[2]);
                $key = rxFileNamingScopedKey(rxFileNamingKeyFile($filename), $protocol);
                $resolved = rxFileNamingResolve($panel, 'xui_file', $key, $filename, '');
            } elseif (($panel['type'] ?? '') === 'jovadpanel'
                && $hasJovadRules
                && function_exists('jovadpanelProtocolFiles')
                && preg_match('~^jovad(wg|ovpn)://([^#]*)~i', $link, $match)) {
                $kind = strtolower($match[1]) === 'ovpn' ? 'openvpn' : 'wireguard';
                $remoteId = rawurldecode($match[2]);
                $groupKey = $kind . ':' . $remoteId;
                if (!array_key_exists($groupKey, $jovadGroups)) {
                    $files = jovadpanelProtocolFiles($panel, $remoteId, $kind);
                    if (($files['status'] ?? false) !== true) {
                        $jovadGroups[$groupKey] = '';
                        continue;
                    }
                    $groupNames = [];
                    $descriptors = [];
                    $keyCounts = [];
                    foreach ((array) ($files['files'] ?? []) as $file) {
                        $fileDescriptor = rxFileNamingJovadDescriptor($file, $kind);
                        $descriptors[] = $fileDescriptor;
                        if (is_array($fileDescriptor)) {
                            $fileKey = $fileDescriptor['key'];
                            $keyCounts[$fileKey] = ($keyCounts[$fileKey] ?? 0) + 1;
                        }
                    }
                    foreach ($descriptors as $fileDescriptor) {
                        if (!is_array($fileDescriptor)) {
                            $groupNames = [];
                            break;
                        }
                        $fileResolved = rxFileNamingResolve($panel, 'jovad', $fileDescriptor['key'], 'config.' . $fileDescriptor['ext'], '', ($keyCounts[$fileDescriptor['key']] ?? 0) > 1);
                        if (!$fileResolved['applied']) {
                            $groupNames = [];
                            break;
                        }
                        $groupNames[] = (string) pathinfo($fileResolved['filename'], PATHINFO_FILENAME);
                    }
                    $jovadGroups[$groupKey] = implode(' / ', array_unique($groupNames));
                }
                if ($jovadGroups[$groupKey] !== '') $names[$index] = $jovadGroups[$groupKey];
                continue;
            } elseif (($panel['type'] ?? '') === 'WGDashboard' && count($links) === 1) {
                $resolved = rxFileNamingResolve($panel, 'wgdashboard', rxFileNamingKeySubscription($panel['inboundid'] ?? ''), 'wireguard.conf', '');
            } else {
                continue;
            }
            if ($resolved['applied']) {
                $name = (string) pathinfo($resolved['filename'], PATHINFO_FILENAME);
                if ($name !== '') $names[$index] = $name;
            }
        }
        return $names;
    }
}

if (!function_exists('rxFileNamingBasisLabel')) {
    function rxFileNamingBasisLabel($basis)
    {
        $map = [
            'label' => 'برچسب کانفیگ',
            'endpoint' => 'آدرس سرور (Endpoint)',
            'file' => 'نام فایل پیکربندی',
            'record' => 'شناسه رکورد فروش دستی',
            'sub' => 'فایل اشتراک اینباند',
            'server' => 'شناسه ثابت سرور',
        ];
        $basis = (string) $basis;
        return isset($map[$basis]) ? $map[$basis] : 'شناسه پیکربندی';
    }
}
