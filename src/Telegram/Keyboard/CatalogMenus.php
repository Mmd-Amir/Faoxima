<?php

namespace Faoxima\Telegram\Keyboard;

use Faoxima\Services\TestAccounts\TestAccountPolicy;
use PDO;

final class CatalogMenus
{
    public static function help(PDO $pdo, $back): string|false
    {
        $rows = [];
        $seen = [];
        $stmt = $pdo->prepare('SELECT * FROM help');
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['category'];
            if ($name == null || in_array($name, $seen)) {
                continue;
            }
            $seen[] = $name;
            $rows[] = [['text' => $name, 'callback_data' => 'helpctgoryـ' . $name]];
        }
        return self::encode($rows, $back, 'backuser');
    }

    public static function purchase(PDO $pdo, array $user, $userId, $back, bool $notes): string|false
    {
        $panels = self::activePanels($pdo, $user['agent']);
        $buttons = [];
        $prefix = $user['step'] === 'getusernameinfo' ? 'locationnotuser_' : 'location_';
        foreach ($panels as $panel) {
            if (self::hidden($panel, $userId)) {
                continue;
            }
            if ($panel['type'] === 'Manualsale') {
                $stock = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
                $stock->bindParam(':codepanel', $panel['code_panel']);
                $stock->execute();
                if ($stock->rowCount() === 0) {
                    continue;
                }
            }
            $buttons[] = self::button($panel, $prefix);
        }
        return self::encode(array_chunk($buttons, self::columns($pdo)), $back, $notes ? 'buyback' : 'backuser');
    }

    public static function alternate(PDO $pdo, array $user, $userId, $back): string|false
    {
        $rows = [];
        foreach (self::activePanels($pdo, $user['agent']) as $panel) {
            if (!self::hidden($panel, $userId)) {
                $rows[] = [self::button($panel, 'locationom_')];
            }
        }
        return self::encode($rows, $back, 'backuser');
    }

    public static function changeLocation(PDO $pdo, array $user, $userId, $back): string|false
    {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :ag OR agent = 'all') AND name_panel != :exclude ORDER BY (position = 0) ASC, position ASC, id ASC");
        $stmt->execute([':ag' => (string) ($user['agent'] ?? ''), ':exclude' => (string) ($user['Processing_value_four'] ?? '')]);
        $buttons = [];
        while ($panel = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!self::hidden($panel, $userId)) {
                $buttons[] = self::button($panel, 'changelocselectlo-');
            }
        }
        return self::encode(array_chunk($buttons, self::columns($pdo)), $back, 'backorder');
    }

    public static function trial(PDO $pdo, array $user, $userId, $back): string|false
    {
        $user['id'] = $userId;
        $rows = [];
        foreach (TestAccountPolicy::rx_test_eligible_panels($user, $pdo) as $panel) {
            $rows[] = [self::button($panel, 'locationtest_')];
        }
        return self::encode($rows, $back, 'backuser');
    }

    private static function activePanels(PDO $pdo, $agent): array
    {
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') ORDER BY (position = 0) ASC, position ASC, id ASC");
        $stmt->bindParam(':agent', $agent);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function columns(PDO $pdo): int
    {
        $count = $pdo->query("SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'")->fetchColumn();
        return $count > 10 ? 2 : 1;
    }

    private static function hidden(array $panel, $userId): bool
    {
        $hidden = json_decode((string) ($panel['hide_user'] ?? ''), true);
        return is_array($hidden) && in_array($userId, $hidden);
    }

    private static function button(array $panel, string $prefix): array
    {
        return ['text' => $panel['name_panel'], 'callback_data' => $prefix . $panel['code_panel']];
    }

    private static function encode(array $rows, $back, string $callback): string|false
    {
        $rows[] = [['text' => $back, 'callback_data' => $callback]];
        return json_encode(['inline_keyboard' => $rows]);
    }
}
