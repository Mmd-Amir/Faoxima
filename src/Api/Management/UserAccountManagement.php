<?php

namespace Faoxima\Api\Management;

use CURLFile;
use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;
use Throwable;

final class UserAccountManagement
{
    public static function userAdd(PDO $pdo, string $method, array $data, array $setting): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }


        try {
            $userInfo = telegram('getChat', ['chat_id' => $data['chat_id']]);

            if (!$userInfo['ok']) {
                EndpointSupport::sendJsonResponse(false, $userInfo['description'] ?? 'Telegram API error');
            }


            $randomString = bin2hex(random_bytes(6));
            $currentTime = time();


            $verifyValue = ($setting['verifystart'] === "onverify") ? 0 : 1;


            $userData = [
                'id' => $data['chat_id'],
                'step' => 'none',
                'limit_usertest' => $setting['limit_usertest_all'],
                'User_Status' => 'Active',
                'number' => 'none',
                'Balance' => '0',
                'pagenumber' => '1',
                'username' => $userInfo['result']['username'] ?? 'none',
                'agent' => 'f',
                'message_count' => '0',
                'last_message_time' => '0',
                'affiliates' => '0',
                'affiliatescount' => '0',
                'cardpayment' => $setting['showcard'],
                'number_username' => '100',
                'namecustom' => 'none',
                'register' => $currentTime,
                'verify' => $verifyValue,
                'codeInvitation' => $randomString,
                'pricediscount' => '0',
                'maxbuyagent' => '0',
                'joinchannel' => '0',
                'score' => '0'
            ];


            $columns = implode(',', array_keys($userData));
            $placeholders = ':' . implode(', :', array_keys($userData));

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO user ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($userData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in user_add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while adding user");
        }
    }

    public static function blockUser(string $method, array $data, array $setting): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (empty($data['description'])) {
            EndpointSupport::sendJsonResponse(false, "description empty", [], 200);
        }
        if ($data['type_block'] == "block") {
            $typeblock = "block";
            $text_report = "کاربر با آیدی عددی {$data['chat_id']} در ربات  مسدود گردید
ادمین انجام دهنده : api site";
        } else {
            $text_report = "کاربر با آیدی عددی {$data['chat_id']} در ربات از مسدودیت خارج گردید
ادمین انجام دهنده : api site";
            sendmessage($data['chat_id'], "✳️ حساب کاربری شما از مسدودی خارج شد ✳️
اکنون میتوانید از ربات استفاده کنید ✔️", null, 'HTML');
            $typeblock = "Active";
        }
        \Faoxima\Core\Database\Db::update("user", "description_blocking", $data['description'], "id", $data['chat_id']);
        \Faoxima\Core\Database\Db::update("user", "User_Status", $typeblock, "id", $data['chat_id']);
        \Faoxima\Admin\Users\UserStatus::report((string) ($setting['Channel_Report'] ?? ''), $text_report);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function verifyUser(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        if ($data['type_verify'] == "1") {
            $type_verify = "0";
        } else {
            $type_verify = "1";
            sendmessage($data['chat_id'], "💎 کاربر گرامی حساب کاربری شما با موفقیت احراز هویت گردید و هم اکنون می توانیدخرید خود را انجام دهید", null, 'HTML');
        }
        \Faoxima\Core\Database\Db::update("user", "verify", $type_verify, "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function changeStatusUser(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        $checkexits = \Faoxima\Core\Database\Db::select("user", "*", "id", $data['chat_id'], "select");
        if (intval(value: $checkexits['checkstatus']) != 0) {
            EndpointSupport::sendJsonResponse(false, "actions exits", [], 200);
        }
        if ($data['type'] == "active") {
            \Faoxima\Core\Database\Db::update("user", "checkstatus", "1", "id", $data['chat_id']);
        } else {
            \Faoxima\Core\Database\Db::update("user", "checkstatus", "2", "id", $data['chat_id']);
        }
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function acceptNumber(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        \Faoxima\Core\Database\Db::update("user", "number", "confrim number by admin", "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function sendMessage(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['text']) || empty($data['text'])) {
            EndpointSupport::sendJsonResponse(false, "text empty", [], 200);
        }
        if (!isset($data['file']) || $data['file'] == null || $data['file'] === '') {
            sendmessage($data['chat_id'], $data['text'], null, 'html');
        } else {
            if (!isset($data['content_type']) || empty($data['content_type'])) {
                EndpointSupport::sendJsonResponse(false, "content_type empty", [], 200);
            }
            $data['content_type'] = explode('/', $data['content_type'])[0];


            $tmpDir = sys_get_temp_dir();
            $tmpSuffix = bin2hex(random_bytes(6));
            $mediaDelivery = null;
            if ($data['content_type'] == "image") {
                $tmpFile = $tmpDir . '/api_users_img_' . $tmpSuffix . '.jpg';
                file_put_contents($tmpFile, base64_decode($data['file']));
                sendphoto($data['chat_id'], new CURLFile($tmpFile), $data['text'], $mediaDelivery);
                @unlink($tmpFile);
            } elseif ($data['content_type'] == "video") {
                $tmpFile = $tmpDir . '/api_users_vid_' . $tmpSuffix . '.mp4';
                file_put_contents($tmpFile, base64_decode($data['file']));
                sendvideo($data['chat_id'], new CURLFile($tmpFile), $data['text'], $mediaDelivery);
                @unlink($tmpFile);
            } elseif ($data['content_type'] == "application") {
                $tmpFile = $tmpDir . '/api_users_doc_' . $tmpSuffix . '.pdf';
                file_put_contents($tmpFile, base64_decode($data['file']));
                sendDocument($data['chat_id'], $tmpFile, $data['text'], null, $mediaDelivery);
                @unlink($tmpFile);
            } elseif ($data['content_type'] == "audio") {


                $rawExt = isset($data['file_ext']) ? (string) $data['file_ext'] : 'mp3';
                if (!preg_match('/^[a-zA-Z0-9]{1,5}$/', $rawExt)) {
                    $rawExt = 'mp3';
                }
                $tmpFile = $tmpDir . '/api_users_aud_' . $tmpSuffix . '.' . $rawExt;
                file_put_contents($tmpFile, base64_decode($data['file']));
                telegram('sendAudio', [
                    'chat_id' => $data['chat_id'],
                    'audio' => new CURLFile($tmpFile),
                    'caption' => $data['text'],
                ]);
                @unlink($tmpFile);
            } else {
                EndpointSupport::sendJsonResponse(false, "content_type invalid", [], 200);
            }
            if ($mediaDelivery !== null && $mediaDelivery['delivery_status'] !== 'completed') {
                $status = $mediaDelivery['delivery_status'];
                EndpointSupport::sendJsonResponse(false, [
                    'partial' => 'Media delivery was incomplete',
                    'unknown' => 'Media delivery could not be confirmed',
                ][$status] ?? 'Media delivery failed', ['delivery_status' => $status], 200);
            }
        }
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setLimitTest(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['limit_test']) || empty($data['limit_test'])) {
            EndpointSupport::sendJsonResponse(false, "limit_test empty", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE user SET limit_usertest =  :limit_test WHERE id = :user_id");
        $stmt->bindValue(':user_id', intval($data['chat_id']), PDO::PARAM_INT);
        $stmt->bindValue(':limit_test', intval($data['limit_test']), PDO::PARAM_INT);
        $stmt->execute();
        if (method_exists(\Faoxima\Services\TestAccounts\TestAccountPolicy::class, 'rx_test_override_set')) {
            \Faoxima\Services\TestAccounts\TestAccountPolicy::rx_test_override_set((string) intval($data['chat_id']), (string) max(0, intval($data['limit_test'])), 'api');
        }
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function transferAccount(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!isset($data['new_userid']) || empty($data['new_userid']))
            EndpointSupport::sendJsonResponse(false, "new_userid empty", [], 200);
        if ($data["chat_id"] == $data["new_userid"])
            EndpointSupport::sendJsonResponse(false, "inavlid user_id", [], 200);


        if (!ctype_digit((string) $data['chat_id']) || !ctype_digit((string) $data['new_userid'])) {
            EndpointSupport::sendJsonResponse(false, "ids must be numeric", [], 400);
        }


        $sourceUser = \Faoxima\Core\Database\Db::select("user", "id", "id", $data['chat_id'], "select");
        if (!$sourceUser) {
            EndpointSupport::sendJsonResponse(false, "source user not found", [], 404);
        }


        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
            $stmt->bindParam(':id_user', $data["new_userid"], PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Core\Database\Db::update("user", "id", $data["new_userid"], "id", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("Payment_report", "id_user", $data["new_userid"], "id_user", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("invoice", "id_user", $data["new_userid"], "id_user", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("support_message", "iduser", $data["new_userid"], "iduser", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("service_other", "id_user", $data["new_userid"], "id_user", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("Giftcodeconsumed", "id_user", $data["new_userid"], "id_user", $data['chat_id']);
            \Faoxima\Core\Database\Db::update("botsaz", "id_user", $data["new_userid"], "id_user", $data['chat_id']);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('transfer_account failed and was rolled back: ' . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "transfer failed; no data was changed", [], 500);
        }
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function joinChannelException(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        \Faoxima\Core\Database\Db::update("user", "joinchannel", "active", "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function cronNotif(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        if ($data['type'] == "1") {
            $type = "0";
        } else {
            $type = "1";
        }
        \Faoxima\Core\Database\Db::update("user", "status_cron", $type, "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function manageShowCart(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        if ($data['type'] == "1") {
            $type = "0";
        } else {
            $type = "1";
        }
        \Faoxima\Core\Database\Db::update("user", "cardpayment", $type, "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function removeAffiliates(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        \Faoxima\Core\Database\Db::update("user", "affiliates", "0", "affiliates", $data['chat_id']);
        \Faoxima\Core\Database\Db::update("user", "affiliatescount", "0", "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function removeAffiliateUser(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        \Faoxima\Core\Database\Db::update("user", "affiliates", "0", "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setAgent(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 500);
        }
        \Faoxima\Core\Database\Db::update("user", "agent", $data['agent_type'], "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setExpireAgent(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id'])) {
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        }
        if (!isset($data['expire_time'])) {
            EndpointSupport::sendJsonResponse(false, "expire_time empty", [], 200);
        }
        if ($data['expire_time'] != 0) {
            $timestamp = time() + (intval($data['expire_time']) * 86400);
        } else {
            $timestamp = null;
        }
        \Faoxima\Core\Database\Db::update("user", "expire", $timestamp, "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }
}
