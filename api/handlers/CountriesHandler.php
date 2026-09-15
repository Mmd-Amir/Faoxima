<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CountriesHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        global $textbotlang;

        $rows = FaoximaDb::fetchAll(
            "SELECT * FROM marzban_panel
              WHERE status = 'active'
                AND (agent = :agent OR agent = 'all')
                AND (
                    type != 'Manualsale'
                    OR EXISTS (
                        SELECT 1 FROM manualsell
                         WHERE manualsell.codepanel = marzban_panel.code_panel
                           AND manualsell.status = 'active'
                    )
                )",
            [':agent' => $this->user['agent']]
        );

        $isNoteGlobal = false;
        if (($this->setting['statusnamecustom'] ?? '') === 'onnamecustom') {
            $isNoteGlobal = true;
        }
        if (($this->setting['statusnoteforf'] ?? '') === '0' && ($this->user['agent'] ?? '') === 'f') {
            $isNoteGlobal = false;
        }

        $customUsernameLabel = $textbotlang['users']['customusername'] ?? 'نام کاربری دلخواه';
        $alternateUsernameLabel = 'نام کاربری دلخواه + عدد رندوم';
        $smartUsernameLabel = 'متن دلخواه کاربر + رندوم';

        $list = [];
        foreach ($rows as $row) {

            if (panel_creation_limit_reached($row)) {
                continue;
            }

            $hide = $this->decodeJsonField($row['hide_user'] ?? null);
            if (!empty($hide) && in_array($this->user['id'], $hide)) {
                continue;
            }

            $isUsername = false;
            $isUsernameRequired = false;
            $isUsernameRandom = false;
            $methodUsername = $row['MethodUsername'] ?? '';
            if ($methodUsername === $customUsernameLabel || $methodUsername === $alternateUsernameLabel || $methodUsername === $smartUsernameLabel) {
                $isUsername = true;
                if ($methodUsername === $customUsernameLabel || $methodUsername === $smartUsernameLabel) {
                    $isUsernameRequired = true;
                }
                if ($methodUsername === $alternateUsernameLabel || $methodUsername === $smartUsernameLabel) {
                    $isUsernameRandom = true;
                }
            }

            $customVolumeMap = $this->decodeJsonField($row['customvolume'] ?? null);
            $customForAgent = (int)($customVolumeMap[$this->user['agent']] ?? 0);
            $isCustom = ($customForAgent === 1 && ($row['type'] ?? '') !== 'Manualsale');

            $list[] = [
                'id'                   => $row['code_panel'],
                'name'                 => $row['name_panel'],
                'is_custom'            => $isCustom,
                'is_username'          => $isUsername,
                'is_username_required' => $isUsernameRequired,
                'is_username_random'   => $isUsernameRandom,
                'is_note'              => $isNoteGlobal,
            ];
        }

        FaoximaResponse::ok($list);
    }
}

