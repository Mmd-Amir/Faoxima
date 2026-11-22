<?php

namespace Faoxima\Admin\Web\Support;

use Faoxima\Core\Database\TextbotTemporary;
use PDO;
use Throwable;

final class TextbotSavePlanner
{
    public static function parsePayload($raw): ?array
    {
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || ($payload['version'] ?? null) !== 1
            || !isset($payload['t'], $payload['rich']) || !is_array($payload['t']) || !is_array($payload['rich'])) {
            return null;
        }
        if (count($payload['t']) !== count($payload['rich'])) {
            return null;
        }
        foreach ($payload['t'] as $key => $value) {
            if (!array_key_exists($key, $payload['rich'])) {
                return null;
            }
        }
        foreach ([$payload['t'], $payload['rich']] as $values) {
            foreach ($values as $value) {
                if (!is_string($value)) {
                    return null;
                }
            }
        }
        if (isset($payload['o']) && !is_array($payload['o'])) {
            $payload['o'] = [];
        }
        $deletes = [];
        if (isset($payload['d'])) {
            if (!is_array($payload['d']) || count($payload['d']) > 500) {
                return null;
            }
            foreach ($payload['d'] as $key) {
                if (!is_string($key) || $key === '' || strlen($key) > 191) {
                    return null;
                }
                $deletes[$key] = $key;
            }
        }
        $payload['d'] = array_values($deletes);
        return $payload;
    }

    public static function currentRows(PDO $pdo, array $defaults): array
    {
        $cur = [];
        $stmt = $pdo->query('SELECT id_text, text FROM textbot');
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cur[(string) $row['id_text']] = (string) $row['text'];
            }
        }
        return $cur + $defaults;
    }

    public static function plan(array $payload, array $cur, ?PDO $pdo = null): array
    {
        $deleteKeys = array_flip(array_map('strval', $payload['d'] ?? []));
        foreach ($deleteKeys as $deleteKey => $unused) {
            unset($payload['t'][$deleteKey], $payload['rich'][$deleteKey]);
        }
        $newValues = $payload['t'];
        $baseline = isset($payload['o']) && is_array($payload['o']) ? $payload['o'] : [];
        $prefix = TextbotEdge::fx_textbot_rich_prefix();
        $result = ['units' => [], 'edgeRejected' => [], 'tonpayErrors' => [], 'richRejected' => [], 'richErrors' => [], 'conflicts' => [], 'unchanged' => [], 'missing' => []];
        $richPrepared = [];
        $richDropped = [];
        $richBlocked = [];
        foreach ($payload['rich'] as $rKey => $rRaw) {
            $rKey = (string) $rKey;
            if (!isset($cur[$rKey])) {
                continue;
            }
            $rText = array_key_exists($rKey, $newValues) ? (string) $newValues[$rKey] : (string) $cur[$rKey];
            $rCompanion = $prefix . $rKey;
            $rIssue = null;
            $rState = TextbotEdge::fx_textbot_rich_state_for_save($rRaw, $rText, $rIssue);
            if ($rState === null) {
                if ($rIssue !== null) {
                    $result['richRejected'][] = $rKey;
                    $richBlocked[$rKey] = true;
                    $result['richErrors'][$rKey] = $rIssue;
                    continue;
                }
                if (isset($cur[$rCompanion])) {
                    $richDropped[$rCompanion] = true;
                }
                continue;
            }
            $newValues[$rKey] = (string) $rState['html'];
            $richPrepared[$rKey] = $rState;
        }
        foreach ($newValues as $key => $val) {
            $key = (string) $key;
            $val = (string) $val;
            if (isset($richBlocked[$key])) {
                continue;
            }
            $rCompanion = $prefix . $key;
            if (isset($baseline[$key]) && is_array($baseline[$key]) && self::baselineConflicts($baseline[$key], $cur, $key, $rCompanion)) {
                $result['conflicts'][] = $key;
                continue;
            }
            $hasRichChange = (isset($richPrepared[$key]) && ($cur[$rCompanion] ?? '') !== TextbotEdge::fx_textbot_rich_state_encode($richPrepared[$key])) || isset($richDropped[$rCompanion]);
            if (isset($cur[$key]) && $cur[$key] === $val && !$hasRichChange) {
                $result['unchanged'][] = $key;
                continue;
            }
            $tonpayIssue = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateValidate($key, $val);
            if ($tonpayIssue !== '') {
                $result['tonpayErrors'][$key] = $tonpayIssue;
                continue;
            }
            if (TextbotEdge::fx_textbot_edge_violation($key, $val) !== '') {
                $result['edgeRejected'][] = $key;
                continue;
            }
            $rows = [$key => $val];
            if (isset($richPrepared[$key])) {
                $rows[$rCompanion] = TextbotEdge::fx_textbot_rich_state_encode($richPrepared[$key]);
            } elseif (isset($richDropped[$rCompanion])) {
                $rows[$rCompanion] = null;
            }
            $result['units'][$key] = $rows;
        }
        foreach (array_keys($deleteKeys) as $deleteKey) {
            $deleteKey = (string) $deleteKey;
            if (strpos($deleteKey, $prefix) === 0) {
                continue;
            }
            if (($pdo !== null && TextbotTemporary::peek($pdo, $deleteKey) === null) || ($pdo === null && !isset($cur[$deleteKey]))) {
                $result['missing'][] = $deleteKey;
                continue;
            }
            $companion = $prefix . $deleteKey;
            if (isset($baseline[$deleteKey]) && is_array($baseline[$deleteKey]) && self::baselineConflicts($baseline[$deleteKey], $cur, $deleteKey, $companion)) {
                $result['conflicts'][] = $deleteKey;
                continue;
            }
            $result['units'][$deleteKey] = [$deleteKey => null, $companion => null];
        }
        return $result;
    }

    public static function rowsOf(array $units): array
    {
        $rows = [];
        foreach ($units as $unit) {
            foreach ($unit as $idText => $text) {
                $rows[(string) $idText] = $text;
            }
        }
        return $rows;
    }

    public static function applyDirect(PDO $pdo, array $units): array
    {
        $updated = 0;
        $errors = [];
        $detail = '';
        foreach ($units as $key => $rows) {
            $multi = count($rows) > 1;
            try {
                if ($multi) {
                    $pdo->beginTransaction();
                }
                foreach ($rows as $idText => $text) {
                    TextbotTemporary::putRow($pdo, (string) $idText, $text);
                }
                if ($multi) {
                    $pdo->commit();
                }
                $updated++;
            } catch (Throwable $e) {
                if ($multi && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[(string) $key] = 'ذخیرهٔ متن انجام نشد؛ نسخهٔ قبلی حفظ شد.';
                $detail = $e->getMessage();
                error_log('[panel/textbot] update failed for ' . $key . ': ' . $e->getMessage());
            }
        }
        return ['updated' => $updated, 'errors' => $errors, 'detail' => $detail];
    }

    public static function valuesFor(PDO $pdo, array $idTexts, array $defaults): array
    {
        $prefix = TextbotEdge::fx_textbot_rich_prefix();
        $keys = [];
        foreach ($idTexts as $idText) {
            $idText = (string) $idText;
            $keys[strpos($idText, $prefix) === 0 ? substr($idText, strlen($prefix)) : $idText] = true;
        }
        $out = [];
        foreach (array_keys($keys) as $key) {
            $key = (string) $key;
            $text = TextbotTemporary::peek($pdo, $key);
            $rich = TextbotTemporary::peek($pdo, $prefix . $key);
            $effective = $text ?? (array_key_exists($key, $defaults) ? (string) $defaults[$key] : null);
            $out[$key] = [
                'exists' => $text !== null,
                'ver' => self::versionToken($effective, $rich),
                'text' => $text ?? (string) ($defaults[$key] ?? ''),
                'rich' => $rich === null ? '' : TextbotEdge::fx_textbot_rich_state_decode_storage($rich),
            ];
        }
        return $out;
    }

    public static function versionToken(?string $text, ?string $companion): string
    {
        return sha1(($text === null ? 'N:' : 'T:' . $text) . "\0" . ($companion === null ? 'N:' : 'T:' . $companion));
    }

    private static function baselineConflicts(array $base, array $cur, string $key, string $companion): bool
    {
        if (!isset($base['v']) || !is_string($base['v']) || $base['v'] === '') {
            return false;
        }
        $token = self::versionToken(isset($cur[$key]) ? (string) $cur[$key] : null, isset($cur[$companion]) ? (string) $cur[$companion] : null);
        return !hash_equals($token, $base['v']);
    }
}
