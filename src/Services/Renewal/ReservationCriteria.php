<?php

namespace Faoxima\Services\Renewal;

final class ReservationCriteria
{
    public static function reservationTriggers(): array
    {
        return ['either', 'traffic', 'expire', 'date', 'immediate', 'multi'];
    }

    public static function reservationConditions(): array
    {
        return ['traffic', 'expire', 'date'];
    }

    public static function reservationConditionsFromTrigger(string $trigger): array
    {
        if ($trigger === 'traffic' || $trigger === 'expire' || $trigger === 'date') {
            return [$trigger];
        }
        if ($trigger === 'immediate') {
            return [];
        }
        return ['traffic', 'expire'];
    }

    public static function reservationNormalizeConditions($conditions): array
    {
        if (is_string($conditions)) {
            $conditions = explode(',', $conditions);
        }
        $conditions = is_array($conditions) ? array_map('strval', $conditions) : [];
        return array_values(array_intersect(self::reservationConditions(), $conditions));
    }

    public static function reservationTriggerForConditions(array $conditions): string
    {
        $conditions = self::reservationNormalizeConditions($conditions);
        if ($conditions === ['traffic', 'expire']) {
            return 'either';
        }
        return count($conditions) === 1 ? $conditions[0] : 'multi';
    }

    public static function reservationRowConditions(array $row): array
    {
        $stored = self::reservationNormalizeConditions((string) ($row['conditions'] ?? ''));
        if ($stored !== []) {
            return $stored;
        }
        return self::reservationConditionsFromTrigger((string) ($row['trigger_type'] ?? 'either'));
    }

    public static function reservationTriggerAvailability(?array $snapshot): array
    {
        $out = [];
        foreach (array_merge(self::reservationTriggers(), self::reservationConditions()) as $trigger) {
            $out[$trigger] = ['available' => true, 'reason' => ''];
        }
        if (!is_array($snapshot) || empty($snapshot['ok']) || empty($snapshot['exists'])) {
            return $out;
        }
        $unlimitedTraffic = \Faoxima\Payments\Fulfillment\Claims::int($snapshot['data_limit'] ?? 0) <= 0;
        $unlimitedTime = empty($snapshot['pending']) && \Faoxima\Payments\Fulfillment\Claims::int($snapshot['expire'] ?? 0) <= 0;
        if ($unlimitedTraffic) {
            $out['traffic'] = ['available' => false, 'reason' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('trigger_unavailable_traffic')];
        }
        if ($unlimitedTime) {
            $out['expire'] = ['available' => false, 'reason' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('trigger_unavailable_expire')];
        }
        if ($unlimitedTraffic && $unlimitedTime) {
            $out['either'] = ['available' => false, 'reason' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('trigger_unavailable_either')];
        }
        return $out;
    }

    public static function reservationTriggered(array $row, array $snapshot, int $now): bool
    {
        if (empty($snapshot['ok']) || empty($snapshot['exists'])) {
            return false;
        }
        $conditions = self::reservationRowConditions($row);
        if (in_array('date', $conditions, true)) {
            $at = (int) ($row['trigger_at'] ?? 0);
            if ($at > 0 && $now >= $at) {
                return true;
            }
        }
        $status = strtolower((string) ($snapshot['status'] ?? ''));
        $limit = \Faoxima\Payments\Fulfillment\Claims::int($snapshot['data_limit'] ?? 0);
        $used = \Faoxima\Payments\Fulfillment\Claims::int($snapshot['used_traffic'] ?? 0);
        $expire = \Faoxima\Payments\Fulfillment\Claims::int($snapshot['expire'] ?? 0);
        $pending = !empty($snapshot['pending']);
        if (in_array('traffic', $conditions, true) && $limit > 0 && ($used >= $limit || $status === 'limited')) {
            return true;
        }
        if (in_array('expire', $conditions, true) && !$pending && $expire > 0 && ($expire <= $now || $status === 'expired')) {
            return true;
        }
        return false;
    }

    public static function reservationParseDate($input, int $now, int $maxAhead = 31622400): ?int
    {
        $input = trim((string) $input);
        if (function_exists('convertPersianNumbersToEnglish')) {
            $input = (string) convertPersianNumbersToEnglish($input);
        }
        $input = strtr($input, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '：' => ':']);
        $input = preg_replace('/\s+/', ' ', $input);
        if ($input === '') {
            return null;
        }
        if (ctype_digit($input) && (int) $input <= 366) {
            $days = (int) $input;
            if ($days < 1) {
                return null;
            }
            return $now + $days * 86400;
        }
        if (ctype_digit($input) && (int) $input > 1000000000) {
            $ts = (int) $input;
        } elseif (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?$/', $input, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            $h = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
            $mi = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
            if ($h > 23 || $mi > 59) {
                return null;
            }
            if ($y < 1700) {
                if (!function_exists('jalali_to_gregorian')) {
                    return null;
                }
                $g = jalali_to_gregorian($y, $mo, $d);
                if (!is_array($g) || count($g) < 3) {
                    return null;
                }
                list($y, $mo, $d) = [(int) $g[0], (int) $g[1], (int) $g[2]];
            }
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            $ts = mktime($h, $mi, 0, $mo, $d, $y);
        } else {
            return null;
        }
        if ($ts === false || $ts <= $now || $ts > $now + $maxAhead) {
            return null;
        }
        return (int) $ts;
    }

    public static function reservationNormalizeSelection($selection, ?array $snapshot, int $now, bool $allowElapsedDate = false): array
    {
        $selection = is_array($selection) ? $selection : [];
        $policy = (string) ($selection['policy'] ?? 'reset_all');
        if (!isset(\Faoxima\Services\Renewal\Plans::renewalPolicies()[$policy])) {
            return ['ok' => false, 'reason' => 'policy_invalid'];
        }
        $conditions = self::reservationNormalizeConditions($selection['conditions'] ?? []);
        if ($conditions === [] && isset($selection['trigger'])) {
            if ((string) $selection['trigger'] === 'immediate') {
                return ['ok' => true, 'trigger' => 'immediate', 'conditions' => [], 'trigger_at' => null, 'policy' => $policy, 'reset_usage' => !empty($selection['reset_usage']) && \Faoxima\Services\Renewal\Plans::renewalPolicyAllowsUsageReset($policy)];
            }
            $conditions = self::reservationConditionsFromTrigger((string) $selection['trigger']);
        }
        if ($conditions === []) {
            return ['ok' => false, 'reason' => 'trigger_unavailable', 'detail' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_conditions_empty')];
        }
        $triggerAt = null;
        if (in_array('date', $conditions, true)) {
            $raw = $selection['trigger_at'] ?? null;
            if ($raw === null || $raw === '' || (int) $raw <= 0) {
                return ['ok' => false, 'reason' => 'trigger_date_required'];
            }
            $triggerAt = is_numeric($raw) ? (int) $raw : self::reservationParseDate($raw, $now);
            if ($triggerAt === null) {
                return ['ok' => false, 'reason' => 'trigger_date_invalid'];
            }
            if ($triggerAt <= $now && !$allowElapsedDate) {
                return ['ok' => false, 'reason' => 'trigger_date_expired', 'trigger_at' => $triggerAt];
            }
        } else {
            $availability = self::reservationTriggerAvailability($snapshot);
            $possible = false;
            $reasons = [];
            foreach ($conditions as $condition) {
                if (!empty($availability[$condition]['available'])) {
                    $possible = true;
                } else {
                    $reasons[] = (string) $availability[$condition]['reason'];
                }
            }
            if (!$possible) {
                return ['ok' => false, 'reason' => 'trigger_unavailable', 'detail' => implode(' ', $reasons)];
            }
        }
        return [
            'ok' => true,
            'trigger' => self::reservationTriggerForConditions($conditions),
            'conditions' => $conditions,
            'trigger_at' => $triggerAt,
            'policy' => $policy,
            'reset_usage' => !empty($selection['reset_usage']) && \Faoxima\Services\Renewal\Plans::renewalPolicyAllowsUsageReset($policy),
        ];
    }

    public static function reservationSettingsFromRaw($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $configured = isset($raw['policy']) || isset($raw['conditions']) || isset($raw['default_policy']) || isset($raw['default_trigger']);
        $policy = (string) ($raw['policy'] ?? ($raw['default_policy'] ?? ''));
        if (!in_array($policy, \Faoxima\Services\Renewal\Reservations::rxReservationAdminPolicies(), true)) {
            $policy = 'reset_all';
        }
        if (isset($raw['conditions'])) {
            $conditions = self::reservationNormalizeConditions($raw['conditions']);
        } else {
            $conditions = self::reservationConditionsFromTrigger((string) ($raw['default_trigger'] ?? 'either'));
        }
        $dateAt = isset($raw['date_at']) && is_numeric($raw['date_at']) && (int) $raw['date_at'] > 0 ? (int) $raw['date_at'] : null;
        if ($dateAt === null) {
            $conditions = array_values(array_diff($conditions, ['date']));
        }
        if ($conditions === []) {
            $conditions = ['traffic', 'expire'];
        }
        return ['policy' => $policy, 'conditions' => $conditions, 'date_at' => in_array('date', $conditions, true) ? $dateAt : null, 'configured' => $configured];
    }

    public static function reservationPanelSettings($panel): array
    {
        $raw = is_array($panel) ? json_decode((string) ($panel['reservation_settings'] ?? ''), true) : null;
        return self::reservationSettingsFromRaw($raw);
    }

    public static function reservationSettingsJson(array $settings): string
    {
        return (string) json_encode([
            'policy' => (string) $settings['policy'],
            'conditions' => self::reservationNormalizeConditions($settings['conditions']),
            'date_at' => in_array('date', self::reservationNormalizeConditions($settings['conditions']), true) ? (int) $settings['date_at'] : null,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function reservationPanelSelection($panel): array
    {
        $settings = self::reservationPanelSettings($panel);
        return [
            'policy' => $settings['policy'],
            'conditions' => $settings['conditions'],
            'trigger_at' => $settings['date_at'],
            'reset_usage' => false,
        ];
    }

    public static function reservationStoredSelection($stored): ?array
    {
        if (!is_array($stored) || !isset($stored['policy'])) {
            return null;
        }
        if (!isset($stored['conditions']) && !isset($stored['trigger'])) {
            return null;
        }
        return $stored;
    }
}
