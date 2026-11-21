<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $adminAction, $adminExecution, $admin_ids, $callback_query_id, $dataget, $datain, $from_id, $pdo, $text, $update.
 * Inherited or exported by reference: $id_invoice, $stmt, $usernameconfig.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalAdminAndDeletion
{
    public static function handle(
        $ManagePanel,
        $adminAction,
        $adminExecution,
        $admin_ids,
        $callback_query_id,
        $dataget,
        $datain,
        $from_id,
        &$id_invoice,
        $pdo,
        &$stmt,
        $text,
        $update,
        &$usernameconfig
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Services\Renewal\AdminRenewalFlow::rxArOnCallback($ManagePanel, $from_id, $datain);
            break;
        case 2:
            if (!in_array($from_id, $admin_ids)) {
                return;
            }
            if ($text[0] == "/") {
                $usernameconfig = explode(" ", $text)[1] ?? '';
                $id_invoice = \Faoxima\Core\Database\Db::select("invoice", "id_invoice", "username", $usernameconfig, 'select');
                if ($id_invoice == false) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کاربر وجو ندارد.", null, 'HTML');
                    return;
                }
                $id_invoice = $id_invoice['id_invoice'];
            } else {
                $id_invoice = $dataget[1];
            }
            \Faoxima\Services\Renewal\AdminRenewalFlow::rxArStart($ManagePanel, $from_id, $id_invoice);
            break;
        case 3:
            if (!isset($update['message']) && empty($text)) {
                return;
            }
            \Faoxima\Services\Renewal\AdminRenewalFlow::rxArOnText($ManagePanel, $from_id, (string) $text);
            break;
        case 4:
            \Faoxima\Services\Renewal\AdminRenewalFlow::rxArAnswer($callback_query_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalText('button_outdated'));
            break;
        case 5:
            $idorder = $dataget[1];
            if (method_exists(\Faoxima\Payments\Receipts\ReceiptActions::class, 'rxReceiptHardDelete')) {
                \Faoxima\Payments\Receipts\Cancellation::receiptHardDelete($pdo, $idorder);
            } else {
                $stmt = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :id_order");
                $stmt->bindParam(':id_order', $idorder, PDO::PARAM_STR);
                $stmt->execute();
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ رسید با موفقیت حذف شد.", null, 'HTML');
            break;
        }

        return true;
    }
}
