<?php

declare(strict_types=1);
require_once __DIR__ . '/../../re/rx/function/file_naming.php';

if (!function_exists('faoxima_apply_file_names')) {
    function faoxima_apply_file_names(array $output, array $panel, array $links = [], string $prefix = ''): array
    {
        if (empty($links)) {
            foreach ($output as $entry) {
                if (($entry['type'] ?? '') === 'config') {
                    foreach ((array) ($entry['value'] ?? []) as $link) {
                        if (is_string($link)) $links[] = $link;
                    }
                }
            }
        }
        $counts = rxFileNamingLinkKeyCounts($links, $panel);
        $fileIndex = 0;
        $offset = 0;
        foreach ($output as &$entry) {
            if (($entry['type'] ?? '') === 'config') {
                $entry['download_offset'] = $offset;
                $entry['download_filenames'] = [];
                $entry['download_custom_names'] = [];
                $items = is_array($entry['value'] ?? null) ? array_values($entry['value']) : [(string) ($entry['value'] ?? '')];
                foreach ($items as $index => $link) {
                    $descriptor = rxFileNamingDescriptorFromLink($link);
                    if (!is_array($descriptor)) continue;
                    if ($descriptor['source'] === 'xui_wg') {
                        $original = xui_wg_conf_filename($link, $descriptor['protocol'], $index);
                    } else {
                        $download = rebeccaConfigDownloadInfo($link);
                        $original = is_array($download) ? $download['filename'] : '';
                    }
                    if ($original === '') continue;
                    $original = normalizeConfigFilename($original, '', $descriptor['ext'] === 'conf');
                    $resolved = rxFileNamingResolve($panel, $descriptor['source'], $descriptor['key'], $original, '', ($counts[$descriptor['key']] ?? 0) > 1);
                    $entry['download_filenames'][$index] = $resolved['filename'];
                    $entry['download_custom_names'][$index] = $resolved['applied'];
                }
                $offset += count($items);
            }
            if (($entry['type'] ?? '') !== 'file') continue;
            $entry['file_index'] = $fileIndex++;
            $original = (string) ($entry['filename'] ?? 'config.conf');
            $original = function_exists('normalizeConfigFilename')
                ? normalizeConfigFilename($original, $prefix, strtolower((string) pathinfo($original, PATHINFO_EXTENSION)) === 'conf')
                : str_replace('_', '-', $original);
            $source = (string) ($entry['naming_source'] ?? '');
            $key = (string) ($entry['naming_key'] ?? '');
            if (($panel['type'] ?? '') === 'Manualsale') {
                $source = 'manual';
                $key = rxFileNamingKeyRecord($entry['record_id'] ?? 0);
            }
            $resolved = rxFileNamingResolve($panel, $source, $key, $original, '', !empty($entry['naming_ambiguous']));
            $entry['filename'] = $resolved['filename'];
            $entry['filename_custom'] = $resolved['applied'];
            unset($entry['naming_source'], $entry['naming_key'], $entry['naming_ambiguous']);
        }
        unset($entry);
        return $output;
    }
}

if (!function_exists('faoxima_manualsale_ext_is_file')) {
    function faoxima_manualsale_ext_is_file(string $ext): bool
    {
        $ext = strtolower(ltrim(trim($ext), '.'));
        if ($ext === '' || $ext === 'sub' || $ext === 'text') {
            return false;
        }
        if (function_exists('rxManualsaleExtIsFile')) {
            return (bool)rxManualsaleExtIsFile($ext);
        }
        return true;
    }
}

if (!function_exists('faoxima_manualsale_public_filename')) {
    function faoxima_manualsale_public_filename(string $name, string $ext): string
    {
        if (function_exists('rxManualsalePublicName')) {
            return rxManualsalePublicName($name, $ext);
        }
        $ext = strtolower(ltrim(trim($ext), '.'));
        if ($ext === '') {
            $ext = 'bin';
        }
        $stem = trim($name);
        $suffix = '.' . $ext;
        if ($stem !== '' && strtolower(substr($stem, -strlen($suffix))) === $suffix) {
            $stem = substr($stem, 0, -strlen($suffix));
        }
        return function_exists('normalizeConfigFilename')
            ? normalizeConfigFilename($stem . $suffix, '', $ext === 'conf')
            : ($stem !== '' ? $stem : 'config') . $suffix;
    }
}

if (!function_exists('faoxima_build_service_output')) {
    function faoxima_build_service_output(array $opts): array
    {
        $panelType = (string)($opts['panel_type'] ?? '');
        $content   = (string)($opts['content'] ?? '');
        $subLink   = trim((string)($opts['sub_link'] ?? ''));
        $fileExt   = strtolower(ltrim(trim((string)($opts['file_ext'] ?? '')), '.'));
        $recordName = trim((string)($opts['name'] ?? ''));

        $configs = [];
        if (isset($opts['configs']) && is_array($opts['configs'])) {
            foreach ($opts['configs'] as $c) {
                if (is_string($c) && trim($c) !== '') {
                    $configs[] = $c;
                }
            }
        }

        $items = [];
        if (isset($opts['items']) && is_array($opts['items'])) {
            foreach ($opts['items'] as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $itContent = (string)($it['content'] ?? '');
                if (trim($itContent) === '') {
                    continue;
                }
                $items[] = [
                    'record_id' => (int) ($it['record_id'] ?? 0),
                    'content'  => $itContent,
                    'file_ext' => strtolower(ltrim(trim((string)($it['file_ext'] ?? '')), '.')),
                    'sub_link' => trim((string)($it['sub_link'] ?? '')),
                    'name'     => trim((string)($it['name'] ?? '')),
                ];
            }
        }
        if ($recordName === '' && !empty($items)) {
            $recordName = $items[0]['name'];
        }

        $output = [];

        if ($panelType === 'Manualsale') {
            if (count($items) > 1) {
                $unifiedSub = $subLink;
                if ($unifiedSub === '') {
                    foreach ($items as $it) {
                        if ($it['sub_link'] !== '') {
                            $unifiedSub = $it['sub_link'];
                            break;
                        }
                    }
                }
                if ($unifiedSub !== '') {
                    $output[] = ['type' => 'link', 'value' => $unifiedSub];
                }
                foreach ($items as $it) {
                    $itExt = $it['file_ext'];
                    $itContent = $it['content'];
                    if ($itExt === 'sub') {
                        $linkValue = $itContent !== '' ? $itContent : $it['sub_link'];
                        if ($linkValue !== '') {
                            $output[] = ['type' => 'link', 'value' => $linkValue];
                        }
                        continue;
                    }
                    if (faoxima_manualsale_ext_is_file($itExt)) {
                        $ext = ($itExt !== '') ? $itExt : 'bin';
                        $output[] = [
                            'type'     => 'file',
                            'value'    => 'data:application/octet-stream;base64,' . base64_encode($itContent),
                            'filename' => faoxima_manualsale_public_filename($it['name'], $ext),
                            'record_id' => (int) ($it['record_id'] ?? 0),
                        ];
                    } else {
                        $output[] = ['type' => 'text', 'value' => $itContent];
                    }
                }
                return $output;
            }

            $isLink = $fileExt === 'sub';
            $isFile = faoxima_manualsale_ext_is_file($fileExt);

            if ($isLink) {
                $linkValue = $content !== '' ? $content : $subLink;
                if ($linkValue !== '') {
                    $output[] = ['type' => 'link', 'value' => $linkValue];
                }
                return $output;
            }

            if ($subLink !== '') {
                $output[] = ['type' => 'link', 'value' => $subLink];
            }

            if ($content !== '') {
                if ($isFile) {
                    $ext = ($fileExt !== '') ? $fileExt : 'bin';
                    $output[] = [
                        'type'     => 'file',
                        'value'    => 'data:application/octet-stream;base64,' . base64_encode($content),
                        'filename' => faoxima_manualsale_public_filename($recordName, $ext),
                        'record_id' => (int) ($items[0]['record_id'] ?? 0),
                    ];
                } else {
                    $output[] = ['type' => 'text', 'value' => $content];
                }
            } elseif (!empty($configs)) {
                $output[] = ['type' => 'text', 'value' => $configs[0]];
            }

            return $output;
        }

        if ($subLink !== '') {
            $output[] = ['type' => 'link', 'value' => $subLink];
        }

        $configItems = [];
        foreach ($configs as $c) {
            if ($c !== $subLink) {
                $configItems[] = $c;
            }
        }
        if (empty($configItems) && $content !== '' && $content !== $subLink) {
            $configItems[] = $content;
        }
        if (!empty($configItems)) {
            $output[] = ['type' => 'config', 'value' => $configItems];
        }

        return $output;
    }
}
