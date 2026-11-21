<?php

namespace Faoxima\Services\Fulfillment;

use Throwable;

final class ProviderState
{
    public static function panelNumber(array $data, string $key, bool $nullIsZero)
    {
        if (!array_key_exists($key, $data)) {
            return false;
        }
        $value = $data[$key];
        if ($value === null) {
            return $nullIsZero ? 0 : false;
        }
        if (is_bool($value) || !is_numeric($value)) {
            return false;
        }
        return (int) round((float) $value);
    }

    public static function panelType(string $namePanel): string
    {
        if ($namePanel === '' || !\Faoxima\Core\Database\Db::runtimeReady()) {
            return '';
        }
        try {
            $row = \Faoxima\Core\Database\Db::select('marzban_panel', 'type', 'name_panel', $namePanel, 'select');
        } catch (Throwable $e) {
            return '';
        }
        return is_array($row) ? (string) ($row['type'] ?? '') : '';
    }

    public static function snapshotFromDatauser($data, string $panelType = ''): array
    {
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_panel_response'];
        }
        if (($data['status'] ?? '') === 'Unsuccessful') {
            $msg = $data['msg'] ?? '';
            $msg = is_scalar($msg) ? (string) $msg : (string) json_encode($msg, JSON_UNESCAPED_UNICODE);
            if (\Faoxima\Services\Fulfillment\Delivery::userMissingMessage($msg)) {
                return ['ok' => true, 'exists' => false, 'error' => $msg];
            }
            return ['ok' => false, 'error' => $msg !== '' ? $msg : 'panel_unsuccessful'];
        }
        if (!isset($data['username']) || (string) $data['username'] === '') {
            return ['ok' => false, 'error' => 'panel_response_without_username'];
        }
        $limit = self::panelNumber($data, 'data_limit', true);
        $used = self::panelNumber($data, 'used_traffic', false);
        $expire = self::panelNumber($data, 'expire', true);
        if ($limit === false || $used === false || $expire === false || $limit < 0 || $expire < 0) {
            return ['ok' => false, 'error' => 'malformed_panel_values'];
        }
        if (in_array($panelType, ['remnawave', 'guard'], true) && $expire > time() + RX_RN_FAR_FUTURE) {
            $expire = 0;
        }
        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $duration = null;
        if (isset($data['on_hold_expire_duration']) && is_numeric($data['on_hold_expire_duration']) && (int) $data['on_hold_expire_duration'] > 0) {
            $duration = (int) $data['on_hold_expire_duration'];
        }
        return [
            'ok' => true,
            'exists' => true,
            'username' => (string) $data['username'],
            'data_limit' => $limit,
            'expire' => $expire,
            'used_traffic' => max(0, $used),
            'status' => $status,
            'pending' => $status === 'on_hold',
            'pending_duration' => $duration,
            'raw' => $data,
        ];
    }

    public static function panelSnapshot($managePanel, string $namePanel, string $username, string $panelType = ''): array
    {
        if (!is_object($managePanel)) {
            return ['ok' => false, 'error' => 'manage_panel_unavailable'];
        }
        try {
            $data = $managePanel->DataUser($namePanel, $username);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return self::snapshotFromDatauser($data, $panelType !== '' ? $panelType : self::panelType($namePanel));
    }

    public static function limits(array $snapshot): array
    {
        return [
            'data_limit' => \Faoxima\Payments\Fulfillment\Claims::int($snapshot['data_limit'] ?? 0),
            'expire' => \Faoxima\Payments\Fulfillment\Claims::int($snapshot['expire'] ?? 0),
            'used_traffic' => \Faoxima\Payments\Fulfillment\Claims::int($snapshot['used_traffic'] ?? 0),
        ];
    }

    public static function stateMatches(array $snapshot, array $expected): bool
    {
        if (!empty($expected['rx_renewal_plan'])) {
            return !empty(\Faoxima\Services\Renewal\Plans::renewalVerify($snapshot, $expected)['ok']);
        }
        if (array_key_exists('data_limit', $expected) && \Faoxima\Payments\Fulfillment\Claims::int($snapshot['data_limit'] ?? 0) !== \Faoxima\Payments\Fulfillment\Claims::int($expected['data_limit'])) {
            return false;
        }
        if (array_key_exists('expire', $expected)) {
            $current = \Faoxima\Payments\Fulfillment\Claims::int($snapshot['expire'] ?? 0);
            $wanted = \Faoxima\Payments\Fulfillment\Claims::int($expected['expire']);
            if ($wanted <= 0 || $current <= 0) {
                return $wanted <= 0 && $current <= 0;
            }
            if (abs($current - $wanted) > RX_PF_EXPIRE_TOLERANCE) {
                return false;
            }
        }
        return true;
    }

    public static function classify(array $snapshot, ?array $before, ?array $target): string
    {
        if (is_array($target) && !empty($target['rx_renewal_plan'])) {
            return \Faoxima\Services\Renewal\Plans::renewalClassify($snapshot, $target);
        }
        if (empty($snapshot['ok'])) {
            return 'unknown';
        }
        if (empty($snapshot['exists']) || !is_array($before) || !is_array($target)) {
            return 'ambiguous';
        }
        $dims = array_values(array_intersect(['data_limit', 'expire'], array_keys($target)));
        if ($dims === []) {
            return 'ambiguous';
        }
        $wanted = array_intersect_key($target, array_flip($dims));
        $original = array_intersect_key($before, array_flip($dims));
        if (count($original) !== count($dims)) {
            return 'ambiguous';
        }
        $isTarget = self::stateMatches($snapshot, $wanted);
        $isBefore = self::stateMatches($snapshot, $original);
        if ($isTarget && !$isBefore) {
            return 'target';
        }
        if ($isBefore && !$isTarget) {
            return 'before';
        }
        return 'ambiguous';
    }
}
