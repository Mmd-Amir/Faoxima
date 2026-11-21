<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use PDOException;

final class SeetingXUiRequestHelpers
{
    public static function ensureXuiTableExists(PDO $pdo)
{
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'x_ui'");
        if ($result === false || $result->rowCount() === 0) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `x_ui` (" .
                "`codepanel` VARCHAR(100) NOT NULL," .
                "`setting` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
                "`protocol` VARCHAR(100) DEFAULT NULL," .
                "PRIMARY KEY (`codepanel`)" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (PDOException $e) {
        error_log('Failed to ensure x_ui table: ' . $e->getMessage());
        throw $e;
    }
}

    public static function synchronizeXuiPanels(PDO $pdo)
{
    try {
        $panels = $pdo->query("SELECT code_panel FROM marzban_panel");
        if ($panels !== false) {
            $insert = $pdo->prepare("INSERT INTO x_ui (codepanel, setting) VALUES (:codepanel, '{}') ON DUPLICATE KEY UPDATE codepanel = codepanel");
            while ($panel = $panels->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($panel['code_panel'])) {
                    $insert->execute([':codepanel' => $panel['code_panel']]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Failed to synchronize x_ui table with marzban_panel: ' . $e->getMessage());
        throw $e;
    }
}
}
