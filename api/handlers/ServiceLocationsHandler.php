<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceLocationsHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
            FaoximaResponse::badRequest('username is required');
        }

        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        $currentPanel = (string)($invoice['Service_location'] ?? '');
        $agent = (string)($this->user['agent'] ?? 'f');

        $rows = FaoximaDb::fetchAll(
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

        FaoximaResponse::ok([
            'panels'      => $list,
            'is_free'     => !$limitEnforced || $userLimitUsed < $limitFreeMax,
            'limit_left'  => $limitEnforced ? max(0, $limitAll - $userLimitUsed) : null,
        ]);
    }
}
