<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $_csrf, $initialCount, $query, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class BroadcastRequest
{
    public static function handle(
        &$_csrf,
        &$initialCount,
        $pdo,
        &$query,
        &$stmt
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
        $_csrf = \Faoxima\Admin\Web\Support\Csrf::fx_csrf_token();









        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            $ajax = (string)$_GET['ajax'];

            if ($ajax === 'count') {
                echo json_encode(\Faoxima\Admin\Web\Pages\BroadcastRequestHelpers::fx_broadcast_count($pdo, $_GET));
                exit;
            }

            if ($ajax === 'send_batch') {
                @set_time_limit(20);
                echo json_encode(\Faoxima\Admin\Web\Pages\BroadcastRequestHelpers::fx_broadcast_send_batch($pdo, $_GET, $_POST));
                exit;
            }

            echo json_encode(['ok' => false, 'error' => 'unknown_ajax']);
            exit;
        }


        $initialCount = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM user WHERE (User_Status IS NULL OR User_Status != 'block')");
            $initialCount = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {

        }

        return true;
    }
}
