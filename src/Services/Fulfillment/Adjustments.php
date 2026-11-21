<?php

namespace Faoxima\Services\Fulfillment;

final class Adjustments
{
    public static function apply($provider, string $kind, $username, $panelCode, $amount, ?array $target = null)
    {
        return match ($kind) {
            'time' => $provider->extra_time($username, $panelCode, $amount, $target),
            'volume' => $provider->extra_volume($username, $panelCode, $amount, $target),
        };
    }

    public static function extraVolumeTarget(array $before, $volumeGb): array
    {
        $volume = (float) $volumeGb;
        return ['data_limit' => $volume == 0 ? 0 : \Faoxima\Payments\Fulfillment\Claims::int($before['data_limit'] ?? 0) + (int) round($volume * RX_PF_GIB)];
    }

    public static function extraTimeTarget(array $before, $days, int $now): array
    {
        $timeOld = \Faoxima\Payments\Fulfillment\Claims::int($before['expire'] ?? 0);
        $timeOld = ($now - $timeOld > 0) ? $now : $timeOld;
        $days = (float) $days;
        return ['expire' => $days == 0 ? 0 : $timeOld + (int) round($days * 86400)];
    }

    public static function adoptCreated(array $raw): array
    {
        $output = $raw;
        $output['status'] = 'successful';
        if (empty($output['configs']) && !empty($output['links'])) {
            $output['configs'] = is_array($output['links']) ? $output['links'] : explode("\n", (string) $output['links']);
        }
        return $output;
    }
}
