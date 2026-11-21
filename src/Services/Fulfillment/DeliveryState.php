<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class DeliveryState
{
    public static function manualsaleDelivery($username_service)
    {
        $result = [
            'ext' => '',
            'name' => '',
            'content' => '',
            'sub_link' => '',
            'is_file' => false,
            'is_text' => false,
            'is_link' => false,
            'sub' => '',
            'configs' => [],
            'items' => [],
        ];
        if (!\Faoxima\Core\Database\Db::runtimeReady() || trim((string) $username_service) === "") {
            return $result;
        }
        $rows = \Faoxima\Core\Database\Db::select("manualsell", "*", "username", $username_service, "fetchAll");
        if (!is_array($rows) || empty($rows)) {
            return $result;
        }
        $row = $rows[0];
        if (!is_array($row)) {
            return $result;
        }
        $ext = strtolower(ltrim(trim((string) ($row['file_ext'] ?? "")), '.'));
        $content = (string) ($row['contentrecord'] ?? "");
        $subLink = trim((string) ($row['sub_link'] ?? ""));
        $isFile = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxManualsaleExtIsFile') ? \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleExtIsFile($ext) : ($ext !== '' && $ext !== 'sub' && $ext !== 'text');
        $isText = ($ext === 'text');
        $isLink = ($ext === 'sub');
        $result['ext'] = $ext;
        $result['name'] = trim((string) ($row['namerecord'] ?? ''));
        $result['content'] = $content;
        $result['sub_link'] = $subLink;
        $result['is_file'] = $isFile;
        $result['is_text'] = $isText;
        $result['is_link'] = $isLink;
        $result['codepanel'] = trim((string) ($row['codepanel'] ?? ''));
        $rxManualPanelRef = $result['codepanel'] !== '' ? 'code:' . $result['codepanel'] : '';
        if ($isLink) {
            $result['sub'] = trim($content) !== '' ? $content : $subLink;
            $result['configs'] = [];
        } else {
            $result['sub'] = $subLink;
            $result['configs'] = (trim($content) !== '') ? [$content] : [];
        }
        $items = [];
        $unifiedSub = trim($subLink);
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $rExt = strtolower(ltrim(trim((string) ($r['file_ext'] ?? "")), '.'));
            $rContent = (string) ($r['contentrecord'] ?? "");
            $rSub = trim((string) ($r['sub_link'] ?? ""));
            if ($unifiedSub === '' && $rSub !== '') {
                $unifiedSub = $rSub;
            }
            $rIsFile = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxManualsaleExtIsFile') ? \Faoxima\Services\Delivery\ServiceDelivery::rxManualsaleExtIsFile($rExt) : ($rExt !== '' && $rExt !== 'sub' && $rExt !== 'text');
            $rIsText = ($rExt === 'text');
            $rIsLink = ($rExt === 'sub');
            if ($rIsLink) {
                $linkVal = trim($rContent) !== '' ? $rContent : $rSub;
                if ($unifiedSub === '' && trim((string) $linkVal) !== '') {
                    $unifiedSub = trim((string) $linkVal);
                }
                continue;
            }
            if (trim($rContent) === '') {
                continue;
            }
            $items[] = [
                'id' => (int) ($r['id'] ?? 0),
                'panel_ref' => $rxManualPanelRef,
                'ext' => $rExt,
                'name' => trim((string) ($r['namerecord'] ?? '')),
                'content' => $rContent,
                'sub_link' => $rSub,
                'is_file' => $rIsFile,
                'is_text' => $rIsText,
            ];
        }
        $result['items'] = $items;
        if ($unifiedSub !== '') {
            $result['sub'] = $isLink && trim($content) !== '' ? $result['sub'] : $unifiedSub;
        }
        $allConfigs = [];
        foreach ($items as $it) {
            if (!$it['is_file'] && trim((string) $it['content']) !== '') {
                $allConfigs[] = $it['content'];
            }
        }
        if (!empty($allConfigs)) {
            $result['configs'] = $allConfigs;
        }
        return $result;
        }

    public static function autoFileDeliveryState($pdo, $invoice_id)
    {
        $invoice_id = trim((string) $invoice_id);
        if ($invoice_id === '') {
            return [];
        }
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('Automatic file delivery state database is unavailable');
        }
        try {
            $stmt = $pdo->prepare('SELECT file_key, status, detail FROM auto_file_delivery WHERE id_invoice = ?');
            if (!$stmt->execute([$invoice_id])) {
                throw new RuntimeException('Automatic file delivery state query failed');
            }
            $state = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (is_array($row)) {
                    $state[(string) ($row['file_key'] ?? '')] = [
                        'status' => (string) ($row['status'] ?? ''),
                        'detail' => (string) ($row['detail'] ?? ''),
                    ];
                }
            }
            return $state;
        } catch (Throwable $e) {
            throw new RuntimeException('Automatic file delivery state could not be read', 0, $e);
        }
        }

    public static function autoFileDeliveryMark($pdo, $invoice_id, $file_key, $status, $detail = '')
    {
        $invoice_id = trim((string) $invoice_id);
        $file_key = trim((string) $file_key);
        $status = (string) $status;
        if ($invoice_id === '' || $file_key === '' || !in_array($status, ['sent', 'failed', 'unknown'], true)) {
            return false;
        }
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $detail = trim((string) $detail);
        if (function_exists('mb_substr')) {
            $detail = mb_substr($detail, 0, 500, 'UTF-8');
        } else {
            $detail = substr($detail, 0, 500);
        }
        $now = time();
        try {
            $stmt = $pdo->prepare('INSERT INTO auto_file_delivery (id_invoice, file_key, status, detail, attempts, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE status = IF(status = \'sent\', \'sent\', VALUES(status)), detail = VALUES(detail), attempts = attempts + 1, updated_at = VALUES(updated_at)');
            return $stmt->execute([$invoice_id, $file_key, $status, $detail, $now, $now]);
        } catch (Throwable $e) {
            error_log('rxAutoFileDeliveryMark failed: ' . get_class($e));
            return false;
        }
        }

    public static function deliveryResultState($response): string
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        } elseif (is_object($response)) {
            $vars = get_object_vars($response);
            if (is_array($vars)) {
                $response = $vars;
            }
        }
        if (is_bool($response)) {
            return $response ? 'confirmed' : 'unknown';
        }
        if (!is_array($response) || !array_key_exists('ok', $response) || !empty($response['_rx_invalid_response'])) {
            return 'unknown';
        }
        if ($response['ok'] === true) {
            return 'confirmed';
        }
        if ($response['ok'] !== false) {
            return 'unknown';
        }
        $code = $response['error_code'] ?? null;
        $description = strtolower((string) ($response['description'] ?? ''));
        if ($code === 'RICH_VALIDATION_FAILED') {
            return 'failed';
        }
        if ($code !== null && is_numeric($code)) {
            $numeric = (int) $code;
            if ($numeric === 429 || $numeric >= 500) {
                return 'unknown';
            }
            if ($numeric >= 400 && $numeric < 500) {
                return 'failed';
            }
        }
        foreach (['blocked', 'deactivated', 'chat not found', 'message to edit not found', 'message to delete not found', 'forbidden', 'bad request'] as $needle) {
            if ($description !== '' && strpos($description, $needle) !== false) {
                return 'failed';
            }
        }
        foreach (['timeout', 'timed out', 'curl', 'connection', 'network', 'اتصال'] as $needle) {
            if ($description !== '' && strpos($description, $needle) !== false) {
                return 'unknown';
            }
        }
        return 'unknown';
    }

    public static function deliveryAggregate(array $components): string
    {
        $required = [];
        foreach ($components as $component) {
            if (is_array($component) && !empty($component['required'])) {
                $required[] = $component;
            }
        }
        if (!$required) {
            return 'completed';
        }
        $states = [];
        foreach ($required as $component) {
            $states[] = (string) ($component['state'] ?? 'unknown');
        }
        $confirmed = 0;
        $uncertain = false;
        foreach ($states as $state) {
            if ($state === 'confirmed') {
                $confirmed++;
            } elseif ($state === 'unknown' || $state === 'attempted') {
                $uncertain = true;
            }
        }
        if ($confirmed === count($states)) {
            return 'completed';
        }
        if ($uncertain) {
            return 'unknown';
        }
        if ($confirmed > 0) {
            return 'partial';
        }
        return 'failed';
    }

    public static function deliveryComponentContext(array $components): array
    {
        $required = [];
        $confirmed = [];
        $failed = [];
        $unknown = [];
        $flat = [];
        foreach (['text', 'qr', 'card', 'files'] as $name) {
            $component = isset($components[$name]) && is_array($components[$name]) ? $components[$name] : ['required' => false, 'state' => 'not_attempted'];
            $state = (string) ($component['state'] ?? 'not_attempted');
            $isRequired = !empty($component['required']);
            $flat[$name . '_state'] = $state;
            $flat[$name . '_required'] = $isRequired;
            if (!$isRequired) {
                continue;
            }
            $required[] = $name;
            if ($state === 'confirmed') {
                $confirmed[] = $name;
            } elseif (in_array($state, ['failed', 'not_attempted', 'skipped'], true)) {
                $failed[] = $name;
            } else {
                $unknown[] = $name;
            }
        }
        $flat['required_components'] = $required;
        $flat['confirmed_components'] = $confirmed;
        $flat['failed_components'] = $failed;
        $flat['unknown_components'] = $unknown;
        $flat['delivery_status'] = self::deliveryAggregate($components);
        return $flat;
    }
}
