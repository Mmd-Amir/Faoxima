<?php

namespace Faoxima\Api\Management;

use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class UserFunds
{
    public static function addBalance(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['amount']) || empty($data['amount'])) {
            EndpointSupport::sendJsonResponse(false, "amount empty", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE user SET Balance = Balance + :amount WHERE id = :user_id");
        $stmt->bindValue(':user_id', intval($data['chat_id']), PDO::PARAM_INT);
        $stmt->bindValue(':amount', intval($data['amount']), PDO::PARAM_INT);
        $stmt->execute();
        if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
            \Faoxima\Wallet\Ledger\Ledger::record($pdo, $data['chat_id'], 'credit', $data['amount'], 'admin_credit', 'افزایش موجودی توسط ادمین');
        }
        $text_balance = "💎 کاربر عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($data['amount']) . " تومان به موجودی کیف پول تان اضافه گردید.";
        sendmessage($data['chat_id'], $text_balance, null, 'html');
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function withdrawal(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['amount']) || empty($data['amount'])) {
            EndpointSupport::sendJsonResponse(false, "amount empty", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE user SET Balance = Balance - :amount WHERE id = :user_id");
        $stmt->bindValue(':user_id', intval($data['chat_id']), PDO::PARAM_INT);
        $stmt->bindValue(':amount', intval($data['amount']), PDO::PARAM_INT);
        $stmt->execute();
        if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
            \Faoxima\Wallet\Ledger\Ledger::record($pdo, $data['chat_id'], 'debit', $data['amount'], 'admin_debit', 'کاهش موجودی توسط ادمین');
        }
        $text_balance = "❌ کاربر عزیز مبلغ " . \Faoxima\Core\Support\Formatting::rxFormatToman($data['amount']) . " تومان از  موجودی کیف پول تان کسر گردید.";
        sendmessage($data['chat_id'], $text_balance, null, 'html');
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function zeroBalance(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        \Faoxima\Core\Database\Db::update("user", "Balance", 0, "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setBecomingNegative(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['amount'])) {
            EndpointSupport::sendJsonResponse(false, "amount empty", [], 200);
        }
        \Faoxima\Core\Database\Db::update("user", "maxbuyagent", $data['amount'], "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setPercentageDiscount(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['percentage'])) {
            EndpointSupport::sendJsonResponse(false, "percentage empty", [], 200);
        }
        \Faoxima\Core\Database\Db::update("user", "pricediscount", $data['percentage'], "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }
}
