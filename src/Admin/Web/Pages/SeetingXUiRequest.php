<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $action, $query, $result, $resultpanel, $sanitizedPanelCode, $selectedPanelCode, $settingsValueForTextarea, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SeetingXUiRequest
{
    public static function handle(
        &$action,
        $pdo,
        &$query,
        &$result,
        &$resultpanel,
        &$sanitizedPanelCode,
        &$selectedPanelCode,
        &$settingsValueForTextarea,
        &$stmt
    ) {
        \Faoxima\Admin\Web\Pages\SeetingXUiRequestHelpers::ensureXuiTableExists($pdo);



        \Faoxima\Admin\Web\Pages\SeetingXUiRequestHelpers::synchronizeXuiPanels($pdo);



        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        $query = $pdo->prepare("SELECT code_panel, name_panel FROM marzban_panel ORDER BY name_panel");
        $query->execute();
        $resultpanel = $query->fetchAll(PDO::FETCH_ASSOC);

        if( !isset($_SESSION["user"]) || !$result ){
            header('Location: login.php');
            return;
        }

        $action = $_GET['action'] ?? '';
        $selectedPanelCode = '';
        $settingsValue = '';

        if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST'){
            $selectedPanelCode = $_POST['namepanel'] ?? '';
            $settingsValue = $_POST['settings'] ?? '';
            if ($selectedPanelCode !== '') {
                $stmt = $pdo->prepare("SELECT 1 FROM x_ui WHERE codepanel = :codepanel");
                $stmt->execute([':codepanel' => $selectedPanelCode]);
                if ($stmt->fetchColumn() === false) {
                    $insert = $pdo->prepare("INSERT INTO x_ui (codepanel, setting) VALUES (:codepanel, :setting)");
                    $insert->execute([
                        ':codepanel' => $selectedPanelCode,
                        ':setting' => $settingsValue
                    ]);
                } else {
                    $updateStmt = $pdo->prepare("UPDATE x_ui SET setting = :setting WHERE codepanel = :codepanel");
                    $updateStmt->execute([
                        ':setting' => $settingsValue,
                        ':codepanel' => $selectedPanelCode
                    ]);
                }
            }
            header('Location: seeting_x_ui.php');
            exit;
        }

        if ($action === 'change' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $selectedPanelCode = $_POST['namepanel'] ?? '';
        }

        $sanitizedPanelCode = htmlspecialchars($selectedPanelCode, ENT_QUOTES, 'UTF-8');

        if ($action === 'change' && $selectedPanelCode !== '') {
            $stmt = $pdo->prepare("SELECT setting FROM x_ui WHERE codepanel=:codepanel");
            $stmt->execute([':codepanel' => $selectedPanelCode]);
            $getsetting = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($getsetting && isset($getsetting['setting'])) {
                $decoded = json_decode($getsetting['setting'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $settingsValue = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } else {
                    $settingsValue = $getsetting['setting'];
                }
            }
        }

        $settingsValueForTextarea = htmlspecialchars($settingsValue, ENT_NOQUOTES, 'UTF-8');

        return true;
    }
}
