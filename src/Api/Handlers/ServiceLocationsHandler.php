<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class ServiceLocationsHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = \Faoxima\Api\Runtime\Input::string($this->data, 'username');
        if ($username === '') {
            \Faoxima\Api\Runtime\Response::badRequest('username is required');
        }

        $invoice = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            \Faoxima\Api\Runtime\Response::notFound('Service not found');
        }

        $currentPanel = (string)($invoice['Service_location'] ?? '');
        $agent = (string)($this->user['agent'] ?? 'f');

        $rows = \Faoxima\Api\Runtime\Db::fetchAll(
            "SELECT * FROM marzban_panel
              WHERE status = 'active'
                AND type != 'jovadpanel'
                AND (agent = :agent OR agent = 'all')
                AND name_panel != :current ORDER BY (position = 0) ASC, position ASC, id ASC",
            [':agent' => $agent, ':current' => $currentPanel]
        );

        $list = [];
        foreach ((is_array($rows) ? $rows : []) as $row) {
            if (($row['changeloc'] ?? '') === 'offchangeloc') continue;

            $hide = $this->decodeJsonField($row['hide_user'] ?? null);
            if (!empty($hide) && in_array((string)$this->user['id'], array_map('strval', $hide), true)) {
                continue;
            }

            $list[] = [
                'id'    => (string)$row['code_panel'],
                'name'  => (string)$row['name_panel'],
                'price' => (int)($row['priceChangeloc'] ?? 0),
            ];
        }

        $limitJson = json_decode((string)($this->setting['limitnumber'] ?? ''), true);
        $limitAll = (int)($limitJson['all'] ?? 0);
        $limitFreeMax = (int)($limitJson['free'] ?? 0);
        $limitEnforced = (int)($this->setting['statuslimitchangeloc'] ?? 0) === 1;
        $userLimitUsed = (int)($this->user['limitchangeloc'] ?? 0);

        \Faoxima\Api\Runtime\Response::ok([
            'panels'      => $list,
            'is_free'     => !$limitEnforced || $userLimitUsed < $limitFreeMax,
            'limit_left'  => $limitEnforced ? max(0, $limitAll - $userLimitUsed) : null,
        ]);
    }
}
