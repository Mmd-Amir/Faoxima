<?php

namespace Faoxima\Services\Renewal;

final class Reservations
{
    public static function rxReservationActivationText(array $conditions, $triggerAt = null): string
    {
        $conditions = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeConditions($conditions);
        $date = is_numeric($triggerAt) && (int) $triggerAt > 0 ? \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatExpire((int) $triggerAt) : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_date_unset');
        if (count($conditions) === 1) {
            $phrase = $conditions[0] === 'date'
                ? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_phrase_date', ['date' => $date])
                : \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_single_' . $conditions[0]);
            return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_single', ['phrase' => $phrase]);
        }
        $parts = [];
        if (in_array('traffic', $conditions, true) && in_array('expire', $conditions, true)) {
            $parts[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_phrase_traffic_expire');
        } else {
            foreach (['traffic', 'expire'] as $key) {
                if (in_array($key, $conditions, true)) {
                    $parts[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_phrase_' . $key);
                }
            }
        }
        if (in_array('date', $conditions, true)) {
            $parts[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_phrase_date', ['date' => $date]);
        }
        return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_multi', ['phrase' => implode(\Faoxima\Services\Renewal\RenewalText::rxRenewalText('activation_joiner'), $parts)]);
    }

    public static function rxReservationConfirmationText(array $conditions, $triggerAt = null): string
    {
        return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_confirmed') . "\n" . self::rxReservationActivationText($conditions, $triggerAt);
    }

    public static function rxReservationTriggerLabel(string $trigger, $triggerAt = null, $conditions = null): string
    {
        $list = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeConditions($conditions ?? '');
        if ($list === []) {
            $list = \Faoxima\Services\Renewal\ReservationCriteria::reservationConditionsFromTrigger($trigger);
        }
        if ($list === []) {
            return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('trigger_immediate');
        }
        return self::rxReservationActivationText($list, $triggerAt);
    }

    public static function rxReservationStatusLabel(string $status): string
    {
        $known = ['pending', 'processing', 'retry', 'reconciling', 'applied', 'failed', 'cancelled'];
        return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('status_' . (in_array($status, $known, true) ? $status : 'pending'));
    }

    public static function rxReservationAdminPolicies(): array
    {
        return ['carry_all', 'reset_all', 'reset_traffic_append_time', 'carry_traffic_reset_time'];
    }

    public static function rxReservationUiEnabled($panel): bool
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel)) {
            return false;
        }
        $mode = \Faoxima\Services\Renewal\Plans::renewalMode($panel['Methodextend'] ?? '');
        return is_array($mode) && !empty($mode['queued']);
    }

    public static function rxReservationAdminState(array $settings): array
    {
        $mask = 0;
        foreach (\Faoxima\Services\Renewal\ReservationCriteria::reservationConditions() as $index => $key) {
            if (in_array($key, $settings['conditions'], true)) {
                $mask |= (1 << $index);
            }
        }
        $index = array_search($settings['policy'], self::rxReservationAdminPolicies(), true);
        return ['p' => $index === false ? 1 : (int) $index, 'mask' => $mask, 'at' => (int) ($settings['date_at'] ?? 0)];
    }

    public static function rxReservationAdminSettingsFromState(array $state): array
    {
        $conditions = [];
        foreach (\Faoxima\Services\Renewal\ReservationCriteria::reservationConditions() as $index => $key) {
            if (((int) $state['mask'] >> $index) & 1) {
                $conditions[] = $key;
            }
        }
        return [
            'policy' => self::rxReservationAdminPolicies()[(int) $state['p']] ?? 'reset_all',
            'conditions' => $conditions,
            'date_at' => in_array('date', $conditions, true) && (int) $state['at'] > 0 ? (int) $state['at'] : null,
        ];
    }

    public static function rxReservationAdminCallback(string $code, array $state, string $action): string
    {
        return 'rsva:' . $code . ':' . (int) $state['p'] . ':' . ((int) $state['mask'] & 7) . ':' . base_convert((string) max(0, (int) $state['at']), 10, 36) . ':' . (!empty($state['selected']) ? '1' : '0') . ':' . $action;
    }

    public static function rxReservationAdminParse(string $data): ?array
    {
        if (!preg_match('/^rsva:([A-Za-z0-9_-]{1,40}):([0-3]):([0-7]):([0-9a-z]{1,8}):(?:([01]):)?([a-z])([0-9]?)$/', $data, $m)) {
            return null;
        }
        return [
            'code' => $m[1],
            'state' => ['p' => (int) $m[2], 'mask' => (int) $m[3], 'at' => (int) base_convert($m[4], 36, 10), 'selected' => $m[5] === '' || $m[5] === '1'],
            'action' => $m[6],
            'arg' => $m[7] === '' ? null : (int) $m[7],
        ];
    }

    public static function rxReservationAdminStepEncode(string $code, array $state): string
    {
        return 'rsvadate|' . $code . '|' . (int) $state['p'] . '|' . ((int) $state['mask'] & 7) . '|' . max(0, (int) $state['at']);
    }

    public static function rxReservationAdminStepDecode(string $step): ?array
    {
        if (!preg_match('/^rsvadate\|([A-Za-z0-9_-]{1,40})\|([0-3])\|([0-7])\|([0-9]{1,12})$/', $step, $m)) {
            return null;
        }
        return ['code' => $m[1], 'state' => ['p' => (int) $m[2], 'mask' => (int) $m[3], 'at' => (int) $m[4]]];
    }

    public static function rxReservationAdminValidate(array $state, int $now): ?string
    {
        if (((int) $state['mask'] & 7) === 0) {
            return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_conditions_empty');
        }
        if ((((int) $state['mask'] >> 2) & 1) === 1) {
            if ((int) $state['at'] <= 0) {
                return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_date_required');
            }
            if ((int) $state['at'] <= $now) {
                return \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_date_past');
            }
        }
        return null;
    }

    public static function rxReservationAdminApply(array $state, string $action, ?int $arg): array
    {
        if ($action === 'p' && $arg !== null && $arg < count(self::rxReservationAdminPolicies())) {
            $state['p'] = $arg;
        } elseif ($action === 'c' && $arg !== null && $arg < count(\Faoxima\Services\Renewal\ReservationCriteria::reservationConditions())) {
            $state['mask'] = ((int) $state['mask'] ^ (1 << $arg)) & 7;
        }
        return $state;
    }

    public static function rxReservationAdminView(array $panel, array $state, string $notice = ''): array
    {
        $code = (string) $panel['code_panel'];
        $policies = self::rxReservationAdminPolicies();
        $settings = self::rxReservationAdminSettingsFromState($state);
        $lines = [\Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_title', ['panel' => htmlspecialchars((string) $panel['name_panel'], ENT_QUOTES, 'UTF-8')]), ''];
        if ($notice !== '') {
            $lines[] = $notice;
            $lines[] = '';
        }
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_hint');
        $lines[] = '';
        foreach ($policies as $index => $key) {
            $lines[] = ($index + 1) . '. ' . \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($key);
        }
        $lines[] = '';
        if ($settings['conditions'] !== [] && (!in_array('date', $settings['conditions'], true) || (int) $state['at'] > 0)) {
            $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_summary', ['text' => self::rxReservationActivationText($settings['conditions'], $state['at'])]);
        }
        $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_timezone_note', ['tz' => date_default_timezone_get()]);
        if (!\Faoxima\Services\Renewal\Plans::renewalPendingSupported((string) ($panel['type'] ?? ''))) {
            $lines[] = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_pending_unsupported');
        }
        $rows = [[['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_section_policy'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'x')]]];
        foreach ($policies as $index => $key) {
            $rows[] = [['text' => ((int) $state['p'] === $index ? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('mark_on') . ' ' : '') . \Faoxima\Services\Renewal\RenewalText::rxRenewalPolicyLabel($key, false, true), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'p' . $index)]];
        }
        if (empty($state['selected'])) {
            $rows[] = [['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_btn_back'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'b')]];
            return ['text' => implode("\n", $lines), 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
        }
        $rows[] = [['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_section_conditions'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'x')]];
        foreach (\Faoxima\Services\Renewal\ReservationCriteria::reservationConditions() as $index => $key) {
            $enabled = ((int) $state['mask'] >> $index) & 1;
            $label = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('cond_' . $key);
            if ($key === 'date' && $enabled && (int) $state['at'] > 0) {
                $label = \Faoxima\Services\Renewal\RenewalText::rxRenewalText('cond_date_at', ['date' => \Faoxima\Services\Renewal\RenewalText::rxRenewalFormatExpire((int) $state['at'])]);
            }
            $rows[] = [['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText($enabled ? 'mark_on' : 'mark_off') . ' ' . $label, 'callback_data' => self::rxReservationAdminCallback($code, $state, 'c' . $index)]];
        }
        if ((((int) $state['mask'] >> 2) & 1) === 1) {
            $rows[] = [['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_btn_date'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'e')]];
        }
        $rows[] = [
            ['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_btn_save'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 's')],
            ['text' => \Faoxima\Services\Renewal\RenewalText::rxRenewalText('admin_btn_back'), 'callback_data' => self::rxReservationAdminCallback($code, $state, 'b')],
        ];
        return ['text' => implode("\n", $lines), 'keyboard' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
    }
}
