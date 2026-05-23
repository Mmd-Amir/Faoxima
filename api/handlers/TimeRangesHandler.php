<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class TimeRangesHandler extends BaseHandler
{


    private const RANGES = [
        ['days' => 1,   'aliases' => [1],          'label' => '1day'],
        ['days' => 7,   'aliases' => [7],          'label' => '7day'],
        ['days' => 30,  'aliases' => [30, 31],     'label' => '1'],
        ['days' => 60,  'aliases' => [60, 61],     'label' => '2'],
        ['days' => 90,  'aliases' => [90, 91],     'label' => '3'],
        ['days' => 120, 'aliases' => [120, 121],   'label' => '4'],
        ['days' => 180, 'aliases' => [180, 181],   'label' => '6'],
        ['days' => 365, 'aliases' => [365],        'label' => '365'],
        ['days' => 0,   'aliases' => [0],          'label' => 'unlimited'],
    ];

    public function handle(): void
    {
        $this->requireMethod('GET');

        global $textbotlang;

        if (($this->setting['statuscategory'] ?? '') === 'offcategory') {
            FaoximaResponse::ok([]);
        }

        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            FaoximaResponse::badRequest('country_id is required');
        }
        $panel = $this->loadPanelByCode($codePanel);

        $userAgent = $this->user['agent'] ?? 'f';
        $rawTimes = array_map('strval', array_column(FaoximaDb::fetchAll(
            "SELECT Service_time FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all')",
            [':location' => $panel['name_panel'], ':agent' => $userAgent]
        ), 'Service_time'));
        $rawTimes = array_values(array_unique($rawTimes));

        $list = [];
        foreach (self::RANGES as $range) {
            $hit = false;
            foreach ($range['aliases'] as $alias) {
                if (in_array((string)$alias, $rawTimes, true)) { $hit = true; break; }
            }
            if (!$hit) continue;

            $name = $textbotlang['Admin']['month'][$range['label']] ?? (string)$range['days'];
            $list[] = [
                'id' => 0,
                'name' => $name,
                'day' => $range['days'],
            ];
        }

        FaoximaResponse::ok($list);
    }
}

