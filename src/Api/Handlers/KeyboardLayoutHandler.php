<?php

namespace Faoxima\Api\Handlers;

final class KeyboardLayoutHandler
{
    private const STANDARD_KEYS = [
        'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck', 'text_Purchased_services',
        'accountwallet', 'text_affiliates', 'text_Tariff_list', 'text_support', 'text_help',
    ];

    public static function describe(array $textRows, ?string $encodedLayout): array
    {
        $labels = array_fill_keys([
            'text_usertest', 'text_Purchased_services', 'text_support', 'text_help', 'accountwallet',
            'text_sell', 'text_Tariff_list', 'text_affiliates', 'text_wheel_luck', 'text_extend',
        ], '');
        foreach ($textRows as $row) {
            if (array_key_exists($row['id_text'], $labels) || (is_string($row['text']) && trim($row['text']) !== '')) {
                $labels[$row['id_text']] = $row['text'];
            }
        }

        $layout = json_decode($encodedLayout ?? '', true)['keyboard'] ?? null;
        $available = self::STANDARD_KEYS;
        foreach ($layout ?? [] as $row) {
            foreach ($row as $button) {
                $position = array_search($button['text'], $available);
                if ($position !== false) {
                    unset($available[$position]);
                }
            }
        }

        return [
            'keylist' => array_map(static fn ($key) => [['text' => $key]], array_values($available)),
            'userlist' => $layout,
            'text' => $labels,
        ];
    }
}
