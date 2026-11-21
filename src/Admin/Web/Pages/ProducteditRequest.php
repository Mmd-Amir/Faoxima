<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $id_product, $infomesssage, $listcategory, $listpanel, $panelTypeMap, $product, $query, $result, $statusmessage.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProducteditRequest
{
    public static function handle(
        &$id_product,
        &$infomesssage,
        &$listcategory,
        &$listpanel,
        &$panelTypeMap,
        $pdo,
        &$product,
        &$query,
        &$result,
        &$statusmessage
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!isset($_SESSION["user"]) || !$result) {
            header('Location: login.php');
            return;
        }

        $statusmessage = false;
        $infomesssage  = "";
        $id_product    = htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8');
        $product       = \Faoxima\Core\Database\Db::select("product", "*", "id", $id_product, "select");

        $panelQuery = $pdo->prepare("SELECT name_panel, type FROM marzban_panel ORDER BY (position = 0) ASC, position ASC, id ASC");
        $panelQuery->execute();
        $listpanel = $panelQuery->fetchAll(PDO::FETCH_ASSOC);

        $panelTypeMap = [];
        foreach ($listpanel as $panelRow) {
            $panelTypeMap[$panelRow['name_panel']] = $panelRow['type'];
        }

        $catQuery = $pdo->prepare("SELECT remark FROM category ORDER BY (position = 0) ASC, position ASC, id ASC");
        $catQuery->execute();
        $listcategory = $catQuery->fetchAll(PDO::FETCH_COLUMN);







        if ($product == false) {
            $statusmessage = true;
            $infomesssage  = "محصول مورد نظر یافت نشد!";
        } else {
            if (isset($_GET['action']) && $_GET['action'] == "save") {
                [$statusmessage, $infomesssage] = \Faoxima\Admin\Web\Pages\ProducteditRequestHelpers::fx_product_edit_save($pdo, $product, $id_product, $_POST);

                if (!$statusmessage) {
                    header('Location: product.php');
                    exit;
                }
            }
        }

        return true;
    }
}
