<?php

namespace Faoxima\Telegram\Keyboard;

use Throwable;

final class ConfigurationKeyboard
{
    public static function protocolLabel($config)
    {
        $schemeMap = [
            'hysteria2' => 'Hysteria2',
            'ss' => 'Shadowsocks',
            'vless' => 'VLESS',
            'vmess' => 'VMess',
            'trojan' => 'Trojan',
            'tg' => 'MTProto',
            'xuifile' => 'WireGuard',
            'xuiawgfile' => 'AmneziaWG',
            'jovadwg' => 'WireGuard',
            'jovadovpn' => 'OpenVPN',
            'jovaddns' => 'DNS',
            'amneziawg' => 'AmneziaWG',
            'awg' => 'AmneziaWG',
        ];

        if(strpos($config, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $config)){
            if(preg_match('/^Protocol:\s*(.+)$/mi', $config, $protoMatch)){
                return trim($protoMatch[1]);
            }
            if(preg_match('/^\s*(Jc|S1|S2|S3|S4|H1|H2|H3|H4)\s*=/mi', $config)){
                return 'AmneziaWG';
            }
            if(preg_match('/^\s*\[Interface\]/mi', $config)){
                return 'WireGuard';
            }
            return null;
        }

        if(preg_match('#^https?://#i', $config)){
            if(method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaConfigDownloadInfo')){
                $downloadInfo = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaConfigDownloadInfo($config);
                if(is_array($downloadInfo)){
                    return $downloadInfo['kind'] === 'wireguard' ? 'WireGuard' : 'OpenVPN';
                }
                return null;
            }
            $path = parse_url(strtok($config, '#'), PHP_URL_PATH) ?: '';
            if(preg_match('#/wg/#', $path)){
                return 'WireGuard';
            }
            if(preg_match('#/ov/#', $path)){
                return 'OpenVPN';
            }
            return null;
        }

        $split_config = explode("://",$config,2);
        if(count($split_config) === 2){
            $scheme = strtolower($split_config[0]);
            if(in_array($scheme, ['wireguard', 'wg', 'vpn', 'awg', 'amneziawg'], true) && method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_wg_conf_from_link')){
                $wgInfo = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_from_link($config);
                if(is_array($wgInfo)){
                    return $wgInfo['protocol'] === 'amneziawg' ? 'AmneziaWG' : 'WireGuard';
                }
            }
            if($scheme === 'wireguard'){
                return 'WireGuard';
            }
            if(isset($schemeMap[$scheme])){
                return $schemeMap[$scheme];
            }
        }

        return null;
    }

    public static function build($textbotlang, $setting, $config_split,$id_invoice,$back_active = true,$page = 0,&$rowCount = null)
    {
        $rxConfigPageSize = 5;

        $rxCfgItemStyles = [];
        $rxCfgDecoded = null;
        if (isset($setting['keyboard_styles_all']) && $setting['keyboard_styles_all'] !== '') {
            $rxCfgDecoded = json_decode((string) $setting['keyboard_styles_all'], true);
            if (is_array($rxCfgDecoded) && !empty($rxCfgDecoded['config_items']) && is_array($rxCfgDecoded['config_items'])) {
                $rxCfgItemStyles = $rxCfgDecoded['config_items'];
            }
        } elseif (\Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $rxCfgRow = \Faoxima\Core\Database\Db::select("setting", "keyboard_styles_all", null, null, "select");
                $rxCfgDecoded = (is_array($rxCfgRow) && !empty($rxCfgRow['keyboard_styles_all'])) ? json_decode((string) $rxCfgRow['keyboard_styles_all'], true) : null;
                if (is_array($rxCfgDecoded) && !empty($rxCfgDecoded['config_items']) && is_array($rxCfgDecoded['config_items'])) {
                    $rxCfgItemStyles = $rxCfgDecoded['config_items'];
                }
            } catch (Throwable $e) {}
        }
        $rxCfgExactStyle = function (string $hash, string $role) use ($rxCfgItemStyles, $rxCfgDecoded): ?string {
            $style = $rxCfgItemStyles[$hash . ':' . $role] ?? null;
            if (is_string($style) && in_array($style, ['none', 'primary', 'success', 'danger'], true)) { return $style; }
            $headerKey = ['download' => 'cfg_header_action', 'protocol' => 'cfg_header_protocol', 'name' => 'cfg_header_name'][$role] ?? '';
            $columnStyle = $rxCfgDecoded['config_list'][$headerKey] ?? null;
            if (is_string($columnStyle) && in_array($columnStyle, ['none', 'primary', 'success', 'danger'], true)) { return $columnStyle; }
            return null;
        };

        $rows = [];
        $config_split = is_array($config_split) ? $config_split : [];
        $customDisplayNames = [];
        if (method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingDisplayNames') && \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled() && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $namingInvoice = \Faoxima\Core\Database\Db::select('invoice', '*', 'id_invoice', $id_invoice, 'select');
                $namingPanel = is_array($namingInvoice)
                    ? \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $namingInvoice['Service_location'] ?? '', 'select')
                    : null;
                $customDisplayNames = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDisplayNames($namingPanel, $config_split);
            } catch (Throwable $e) {
                error_log('[config list file naming] ' . $e->getMessage());
            }
        }
        foreach (array_values($config_split) as $i => $config){
            if(!is_string($config) || $config === ''){
                error_log('Invalid configuration entry encountered while building keyboard');
                continue;
            }

            $cfgHash = substr(sha1($config), 0, 16);
            $protocolLabel = self::protocolLabel($config);
            $protocolButton = ['text' => $protocolLabel !== null ? $protocolLabel : '—', 'callback_data' => "none", '_rxsk' => 'cfg_protocol_cell', '_rxcolor' => 'config_cell:protocol'];
            $protocolExact = $rxCfgExactStyle($cfgHash, 'protocol');
            if ($protocolExact !== null) { $protocolButton['style'] = $protocolExact; }

            $downloadBtn = ['text' => "دریافت کانفیگ", 'callback_data' => "configget_{$id_invoice}_$i", '_rxsk' => 'cfg_download_one', '_rxcolor' => 'config_cell:download'];
            $downloadExact = $rxCfgExactStyle($cfgHash, 'download');
            if ($downloadExact !== null) { $downloadBtn['style'] = $downloadExact; }

            if(strpos($config, "\n") !== false && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:\/\//', $config)){
                $firstLine = trim(explode("\n", $config, 2)[0]);
                $nameBtn = ['text' => $customDisplayNames[$i] ?? ($firstLine !== '' ? $firstLine : sprintf('Config %d', $i + 1)), 'callback_data' => "none", '_rxsk' => 'cfg_name_cell', '_rxcolor' => 'config_cell:name'];
                $nameExact = $rxCfgExactStyle($cfgHash, 'name');
                if ($nameExact !== null) { $nameBtn['style'] = $nameExact; }
                $rows[] = [$downloadBtn, $protocolButton, $nameBtn];
                continue;
            }

            $split_config = explode("://",$config,2);
            if(count($split_config) !== 2 || strpos($split_config[0], '{') !== false){
                $structuredConfig = json_decode($config, true);
                if (is_array($structuredConfig)) {
                    $structuredName = $structuredConfig['name'] ?? ($structuredConfig['server'] ?? sprintf('Config %d', $i + 1));
                    $nameBtn = ['text' => $customDisplayNames[$i] ?? urldecode((string) $structuredName), 'callback_data' => "none", '_rxsk' => 'cfg_name_cell', '_rxcolor' => 'config_cell:name'];
                    $nameExact = $rxCfgExactStyle($cfgHash, 'name');
                    if ($nameExact !== null) { $nameBtn['style'] = $nameExact; }
                    $rows[] = [$downloadBtn, $protocolButton, $nameBtn];
                    continue;
                }
                error_log('Malformed configuration string: missing scheme separator');
                continue;
            }

            $type_prtocol = $split_config[0];
            $payload = $split_config[1];
            if(\Faoxima\Core\Support\Formatting::isBase64($payload)){
                $decoded = base64_decode($payload, true);
                if($decoded === false){
                    error_log('Failed to decode base64 configuration payload');
                    continue;
                }
                $payload = $decoded;
            }

            $displayName = '';
            if($type_prtocol == "vmess"){
                $configJson = json_decode($payload, true);
                if(is_array($configJson) && isset($configJson['ps'])){
                    $displayName = $configJson['ps'];
                }
            }elseif(in_array($type_prtocol, ["anyconnect","l2tp","ikev2","pptp"], true)){
                $parts = explode("?",$payload,2);
                if(count($parts) >= 1 && $parts[0] !== ''){
                    $displayName = $parts[0];
                }
            }else{
                $parts = explode("#",$payload,2);
                if(count($parts) === 2){
                    $displayName = $parts[1];
                }
            }

            if($displayName === '' || $displayName === null){
                $displayName = sprintf('Config %d', $i + 1);
            }

            $nameBtn = ['text' => $customDisplayNames[$i] ?? urldecode($displayName), 'callback_data' => "none", '_rxsk' => 'cfg_name_cell', '_rxcolor' => 'config_cell:name'];
            $nameExact = $rxCfgExactStyle($cfgHash, 'name');
            if ($nameExact !== null) { $nameBtn['style'] = $nameExact; }
            $rows[] = [$downloadBtn, $protocolButton, $nameBtn];
        }

        $totalRows = count($rows);
        $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $rxConfigPageSize) : 1;
        $page = max(0, min($page, $totalPages - 1));
        $pageRows = array_slice($rows, $page * $rxConfigPageSize, $rxConfigPageSize);

        $keyboard_config = ['inline_keyboard' => []];
        $keyboard_config['inline_keyboard'][] = [
            ['text' => "⚙️ کانفیگ", 'callback_data' => "none", '_rxsk' => 'cfg_header_action', '_rxcolor' => 'config_header:cfg_header_action'],
            ['text' => "🔌 پروتکل", 'callback_data' => "none", '_rxsk' => 'cfg_header_protocol', '_rxcolor' => 'config_header:cfg_header_protocol'],
            ['text' => "✏️نام کانفیگ", 'callback_data' => "none", '_rxsk' => 'cfg_header_name', '_rxcolor' => 'config_header:cfg_header_name'],
            ];
        foreach ($pageRows as $row) {
            $keyboard_config['inline_keyboard'][] = $row;
        }

        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 0) {
                $navRow[] = ['text' => "◀️ قبلی", 'callback_data' => "configpage_{$id_invoice}_" . ($page - 1), '_rxsk' => 'cfg_page_prev'];
            }
            $navRow[] = ['text' => sprintf('%d/%d', $page + 1, $totalPages), 'callback_data' => "none", '_rxsk' => 'cfg_page_indicator'];
            if ($page < $totalPages - 1) {
                $navRow[] = ['text' => "بعدی ▶️", 'callback_data' => "configpage_{$id_invoice}_" . ($page + 1), '_rxsk' => 'cfg_page_next'];
            }
            $keyboard_config['inline_keyboard'][] = $navRow;
        }

        $keyboard_config['inline_keyboard'][] = [['text' => "⚙️ دریافت همه کانفیگ ها", 'callback_data' => "configget_$id_invoice"."_1520", '_rxsk' => 'cfg_download_all']];
        if($back_active){
        $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_$id_invoice", '_rxsk' => 'cfg_back_service']];
        }
        $rowCount = $totalRows;
        $encoded = json_encode($keyboard_config);
        if ($encoded === false) {
            error_log('keyboard_config json_encode failed: ' . json_last_error_msg());
            $encoded = defined('JSON_INVALID_UTF8_SUBSTITUTE') ? json_encode($keyboard_config, JSON_INVALID_UTF8_SUBSTITUTE) : false;
        }
        return $encoded;
    }
    public static function unavailableText($datatextbot, $dataUserOut, $outcomeOverride = null)
    {
        $diagnostic = is_array($dataUserOut) && is_array($dataUserOut['links_diagnostic'] ?? null) ? $dataUserOut['links_diagnostic'] : [];
        $outcome = $outcomeOverride !== null ? (string) $outcomeOverride : (string) ($diagnostic['outcome'] ?? 'failed');
        $baseError = $datatextbot['dyn_errors_config_read_from_panel_error'] ?? "❌ خطا در خواندن کانفیگ از پنل.";
        if ($outcome === 'empty') {
            $message = "ℹ️ در حال حاضر پنل هیچ کانفیگی برای این سرویس ارائه نمی‌دهد.";
        } elseif ($outcome === 'invalid') {
            $message = $baseError . "\nپاسخ پنل شامل کانفیگ قابل استفاده نبود.";
        } else {
            $message = $baseError;
        }
        return $message;
    }
}
