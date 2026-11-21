<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $listinvoice, $query, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class InboundRequest
{
    public static function handle(
        &$listinvoice,
        $pdo,
        &$query,
        &$result
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if( !isset($_SESSION["user"]) || !$result ){
            header('Location: login.php');
            return;
        }
        $listinvoice = [];
        try {
            $query = $pdo->prepare("SELECT * FROM Inbound");
            $query->execute();
            $listinvoice = $query->fetchAll();
        } catch (\PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1146) {
                throw $error;
            }
        }

        return true;
    }
}
