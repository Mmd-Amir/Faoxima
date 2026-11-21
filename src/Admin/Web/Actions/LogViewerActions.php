<?php

namespace Faoxima\Admin\Web\Actions;

/**
 * Handler stage. Inherited by value: $action, $ev, $name.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LogViewerActions
{
    public static function handle(
        $action,
        $ev,
        $name
    ) {
        switch ($action) {
            case 'list':
                $files = \Faoxima\Admin\Web\Support\Logviewer::lv_discover();
                $counts = ['logs' => 0, 'php' => 0, 'support' => 0, 'binary' => 0];
                foreach ($files as $e) {
                    if ($e['kind'] === 'support') {
                        $counts['support']++;
                    } else {
                        $counts[$e['source']]++;
                        if ($e['binary']) {
                            $counts['binary']++;
                        }
                    }
                }
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json([
                    'ok' => true,
                    'files' => $files,
                    'counts' => $counts,
                    'logs_dir' => is_dir(\Faoxima\Admin\Web\Support\Logviewer::lv_root() . '/logs'),
                    'server_time' => date('Y-m-d H:i:s'),
                ]);
                break;

            case 'stats':
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => true] + \Faoxima\Admin\Web\Support\Logviewer::lv_stats());
                break;

            case 'read':
                $filt = \Faoxima\Admin\Web\Support\Logviewer::lv_parse_filters($_GET);
                $limit = \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_limit();
                $id = \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_id();
                if ($id === '*') {
                    $res = \Faoxima\Admin\Web\Support\Logviewer::lv_read_merge(\Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_cursors(), $limit, $filt, \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_source());
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => true, 'mode' => 'merge'] + $res);
                }
                $v = \Faoxima\Admin\Web\Support\Logviewer::lv_validate($id);
                if (!$v['ok']) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail($v['code'] === 'missing' ? 'missing' : 'invalid', $v['code'] === 'missing' ? 404 : 400);
                }
                if ($v['cls']['kind'] !== 'log') {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('unsupported', 400);
                }
                if (!is_readable($v['abs'])) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('permission', 403);
                }
                if (\Faoxima\Admin\Web\Support\Logviewer::lv_is_binary($v['abs'])) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('binary', 415);
                }
                $before = isset($_GET['before']) && $_GET['before'] !== '' ? max(0, (int)$_GET['before']) : null;
                $res = \Faoxima\Admin\Web\Support\Logviewer::lv_read_page($v, $before, $limit, $filt, LV_PAGE_BUDGET);
                if (isset($res['error'])) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail($res['error'], 403);
                }
                clearstatcache(true, $v['abs']);
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => true, 'mode' => 'file', 'mtime' => (int)@filemtime($v['abs'])] + $res);
                break;

            case 'delete_preview':
                $scope = $_GET['scope'] ?? '';
                if (!in_array($scope, ['logs', 'php', 'all'], true)) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('invalid', 400);
                }
                $cand = \Faoxima\Admin\Web\Support\Logviewer::lv_delete_candidates($scope);
                $bytes = 0;
                foreach ($cand as $e) {
                    $bytes += $e['size'];
                }
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => true, 'scope' => $scope, 'count' => count($cand), 'bytes' => $bytes, 'snapshot' => \Faoxima\Admin\Web\Support\Logviewer::lv_snapshot_token($scope, $cand, \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_snapshot_secret())]);
                break;

            case 'download':
                $filt = \Faoxima\Admin\Web\Support\Logviewer::lv_parse_filters($_GET);
                $mode = ($_GET['mode'] ?? 'raw') === 'json' ? 'json' : 'raw';
                $id = \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_id();
                if ($mode === 'json') {
                    $events = [];
                    if ($id === '*') {
                        $res = \Faoxima\Admin\Web\Support\Logviewer::lv_read_merge([], 1000, $filt, \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_source());
                        $events = $res['events'];
                        $name = 'logs-export';
                    } else {
                        $v = \Faoxima\Admin\Web\Support\Logviewer::lv_validate($id);
                        if (!$v['ok'] || $v['cls']['kind'] !== 'log') {
                            \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('invalid', 400);
                        }
                        if (!is_readable($v['abs'])) {
                            \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('permission', 403);
                        }
                        if (\Faoxima\Admin\Web\Support\Logviewer::lv_is_binary($v['abs'])) {
                            \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('binary', 415);
                        }
                        $res = \Faoxima\Admin\Web\Support\Logviewer::lv_read_page($v, null, 1000, $filt, 16777216);
                        if (isset($res['error'])) {
                            \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail($res['error'], 403);
                        }
                        $events = $res['events'];
                        $name = basename($id);
                    }
                    foreach ($events as &$ev) {
                        unset($ev['k'], $ev['tse']);
                    }
                    unset($ev);
                    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $name . '.json"');
                    header('Cache-Control: no-store');
                    header('X-Content-Type-Options: nosniff');
                    echo json_encode(['exported_at' => date('c'), 'count' => count($events), 'events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                    exit;
                }
                $v = \Faoxima\Admin\Web\Support\Logviewer::lv_validate($id);
                if (!$v['ok'] || $v['cls']['kind'] !== 'log') {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('invalid', 400);
                }
                if (!is_readable($v['abs'])) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('permission', 403);
                }
                if (\Faoxima\Admin\Web\Support\Logviewer::lv_is_binary($v['abs'])) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('binary', 415);
                }
                $fh = @fopen($v['abs'], 'rb');
                if (!$fh) {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('permission', 403);
                }
                $size = (int)@filesize($v['abs']);
                $start = max(0, $size - LV_EXPORT_BYTES);
                $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($id));
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $name . '.txt"');
                header('Cache-Control: no-store');
                header('X-Content-Type-Options: nosniff');
                if ($start > 0) {
                    header('X-Export-Truncated: 1');
                }
                fseek($fh, $start);
                $carry = '';
                $first = $start > 0;
                while (!feof($fh)) {
                    $piece = fread($fh, 262144);
                    if ($piece === false || $piece === '') {
                        break;
                    }
                    $buf = $carry . $piece;
                    if ($first) {
                        $p = strpos($buf, "\n");
                        if ($p === false) {
                            $carry = $buf;
                            continue;
                        }
                        $buf = substr($buf, $p + 1);
                        $first = false;
                    }
                    $cut = strrpos($buf, "\n");
                    if ($cut === false) {
                        $carry = $buf;
                        continue;
                    }
                    $carry = substr($buf, $cut + 1);
                    echo \Faoxima\Admin\Web\Support\Logviewer::lv_redact_string(\Faoxima\Admin\Web\Support\Logviewer::lv_utf8(substr($buf, 0, $cut + 1)));
                }
                if ($carry !== '' && !$first) {
                    echo \Faoxima\Admin\Web\Support\Logviewer::lv_redact_string(\Faoxima\Admin\Web\Support\Logviewer::lv_utf8($carry));
                }
                fclose($fh);
                exit;

            case 'delete_file':
                $id = \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_param_id();
                $r = \Faoxima\Admin\Web\Support\Logviewer::lv_delete_one($id);
                $status = $r['status'];
                $http = 200;
                if ($status === 'unsafe' || $status === 'protected') {
                    $http = 403;
                }
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => $status === 'deleted' || $status === 'missing', 'result' => $r], $http);
                break;

            case 'delete_bulk':
                $scope = $_POST['scope'] ?? '';
                if (!in_array($scope, ['logs', 'php', 'all'], true) || ($_POST['confirm'] ?? '') !== '1') {
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('invalid', 400);
                }
                $cand = \Faoxima\Admin\Web\Support\Logviewer::lv_delete_candidates($scope);
                $secret = \Faoxima\Admin\Web\Actions\LogViewerQueriesHelpers::lv_snapshot_secret();
                $given = isset($_POST['snapshot']) && is_string($_POST['snapshot']) ? $_POST['snapshot'] : '';
                $current = \Faoxima\Admin\Web\Support\Logviewer::lv_snapshot_token($scope, $cand, $secret);
                if ($given === '' || !hash_equals($current, $given)) {
                    $bytes = 0;
                    foreach ($cand as $e) {
                        $bytes += $e['size'];
                    }
                    \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => false, 'code' => 'changed', 'count' => count($cand), 'bytes' => $bytes, 'snapshot' => $current], 409);
                }
                $summary = ['deleted' => 0, 'missing' => 0, 'permission' => 0, 'failed' => 0, 'protected' => 0, 'unsafe' => 0];
                $failures = [];
                $deletedIds = [];
                foreach ($cand as $e) {
                    $r = \Faoxima\Admin\Web\Support\Logviewer::lv_delete_one($e['id']);
                    $summary[$r['status']] = ($summary[$r['status']] ?? 0) + 1;
                    if ($r['status'] === 'deleted' && count($deletedIds) < 500) {
                        $deletedIds[] = $e['id'];
                    }
                    if (!in_array($r['status'], ['deleted', 'missing'], true) && count($failures) < 50) {
                        $failures[] = ['id' => $e['id'], 'status' => $r['status']];
                    }
                }
                $bad = $summary['permission'] + $summary['failed'] + $summary['protected'] + $summary['unsafe'];
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_json(['ok' => $bad === 0, 'scope' => $scope, 'total' => count($cand), 'summary' => $summary, 'failures' => $failures, 'deleted_ids' => $deletedIds]);
                break;

            default:
                \Faoxima\Admin\Web\Support\LogviewerApiPage::lv_fail('invalid', 400);
        }

        return 1;
    }
}
