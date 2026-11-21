<?php

namespace Faoxima\Admin\Keyboard;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo, $text, $textbotlang, $users.
 * Inherited or exported by reference: $button, $json_list_product_list_admin, $keyboardprotocollist, $product, $result, $row, $stmt, $table_exists, $textbot.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TableAndDiscountLists
{
    public static function handle(
        &$button,
        &$json_list_product_list_admin,
        &$keyboardprotocollist,
        $pdo,
        &$product,
        &$result,
        &$row,
        &$stmt,
        &$table_exists,
        $text,
        &$textbot,
        $textbotlang,
        $users
    ) {
        $textbot = json_encode([
            'keyboard' => [
                [['text' => $textbotlang['Admin']['backadmin']], ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'adm_backmenu']]
            ],
            'resize_keyboard' => true
        ]);

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        if ($table_exists) {
            $getdataprotocol = \Faoxima\Core\Database\Db::select("protocol","*",null,null,"fetchAll");
            $protocol = [];
            foreach($getdataprotocol as $result)
            {
                $protocol[] = [['text'=>$result['NameProtocol']]];
            }
            $protocol[] = [['text'=>$textbotlang['Admin']['backadmin']]];
            $keyboardprotocollist = json_encode(['resize_keyboard'=>true,'keyboard'=> $protocol]);
         }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        if ($table_exists) {
            $product = [];
            $stmt = $pdo->prepare("SELECT * FROM product WHERE FIND_IN_SET(:text, Location) > 0 or Location = '/all' ");
            $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $product[] = [$row['name_product']];
            }
            $list_product = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($product as $button) {
                $list_product['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
            // Back row appended last so it renders at the bottom of the product
            // picker. Same fix as the gift-code/discount-code delete lists below.
            $list_product['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
            ];
            $json_list_product_list_admin = json_encode($list_product);
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        if ($table_exists) {
            $Discount = [];
            $stmt = $pdo->prepare("SELECT * FROM Discount");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $Discount[] = [$row['code']];
            }
            $list_Discount = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($Discount as $button) {
                $list_Discount['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
            // Back row goes LAST so it renders at the bottom of the reply keyboard
            // (matches the convention used by $list_Inbound below + every static
            // admin keyboard). Previously this was inserted before the foreach loop
            // which forced the back row to the top of the gift-code delete list.
            $list_Discount['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
            ];
            $json_list_Discount_list_admin = json_encode($list_Discount);
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        if ($table_exists) {
            $Inboundkeyboard = [];
            $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
            $stmt->bindParam(':text', $text  , PDO::PARAM_STR);
            $stmt->bindParam(':Processing_value', $users['Processing_value']  , PDO::PARAM_STR);
            $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $Inboundkeyboard[] = [$row['NameInbound']];
        }

        }
            $list_Inbound = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($Inboundkeyboard as $button) {
                $list_Inbound['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
                $list_Inbound['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
            ];
            $json_list_Inbound_list_admin = json_encode($list_Inbound);
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE 'DiscountSell'");
        $stmt->execute();
        $result = $stmt->fetchAll();
        $table_exists = count($result) > 0;
        if ($table_exists) {
            $DiscountSell = [];
            $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $DiscountSell[] = [$row['codeDiscount']];
            }
            $list_Discountsell = [
                'keyboard' => [],
                'resize_keyboard' => true,
            ];
            foreach ($DiscountSell as $button) {
                $list_Discountsell['keyboard'][] = [
                    ['text' => $button[0]]
                ];
            }
            // Back row appended last so it renders at the bottom (same fix as the
            // gift-code list above — the original code prepended this row before
            // the foreach loop, forcing it to the top of the discount-code delete
            // keyboard).
            $list_Discountsell['keyboard'][] = [
                ['text' => $textbotlang['Admin']['backadmin']],
            ];
            $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
        }

        return 1;
    }
}
