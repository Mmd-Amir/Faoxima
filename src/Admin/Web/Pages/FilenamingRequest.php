<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $fnCsrf, $fnMaster, $query.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class FilenamingRequest
{
    public static function handle(
        &$fnCsrf,
        &$fnMaster,
        $pdo,
        &$query
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
        $query->execute([':username' => $_SESSION['user'] ?? '']);
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION['user']) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        $fnCsrf = \Faoxima\Admin\Web\Support\Csrf::fx_csrf_token();
        $fnMaster = \Faoxima\Services\Delivery\FileNaming::rxFileNamingEnabled(true);
        header('Cache-Control: no-store');

        return true;
    }
}
