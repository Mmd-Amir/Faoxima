<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class AgentBotManagement
{
    public static function activeBotAgent(PDO $pdo, string $method, array $data, ?string $domainhosts): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!isset($data['token']) || empty($data['token']))
            EndpointSupport::sendJsonResponse(false, "token empty", [], 200);


        $chatIdRaw = html_entity_decode((string) $data['chat_id'], ENT_QUOTES, 'UTF-8');
        $tokenRaw = html_entity_decode((string) $data['token'], ENT_QUOTES, 'UTF-8');
        if (!preg_match('/^-?\d{3,20}$/', $chatIdRaw)) {
            error_log('[api/users active_bot_agent] rejected malformed chat_id');
            EndpointSupport::sendJsonResponse(false, "chat_id invalid", [], 400);
        }
        if (!preg_match('/^\d+:[A-Za-z0-9_\-]{20,}$/', $tokenRaw)) {
            error_log('[api/users active_bot_agent] rejected malformed bot token');
            EndpointSupport::sendJsonResponse(false, "token invalid", [], 400);
        }

        $data['chat_id'] = $chatIdRaw;
        $data['token'] = $tokenRaw;
        $chec_kbot = \Faoxima\Core\Database\Db::select("botsaz", "*", "id_user", $data['chat_id'], "count");
        $check_bots = \Faoxima\Core\Database\Db::select("botsaz", "*", null, null, "count");


        if ($check_bots >= 15)
            EndpointSupport::sendJsonResponse(false, "You are allowed to create 15 representative bots in your bot.");
        if ($chec_kbot != 0)
            EndpointSupport::sendJsonResponse(false, "You already have an active bot.");
        $getInfoToken = json_decode(file_get_contents("https://api.telegram.org/bot{$data['token']}/getme"), true);
        if ($getInfoToken == false or empty($getInfoToken['ok']) || empty($getInfoToken['result']['username']))
            EndpointSupport::sendJsonResponse(false, "Token invalid");


        $remoteUsername = (string) $getInfoToken['result']['username'];
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $remoteUsername)) {
            error_log('[api/users active_bot_agent] rejected unsafe bot username from getMe: ' . substr($remoteUsername, 0, 64));
            EndpointSupport::sendJsonResponse(false, "Bot username unsafe", [], 400);
        }
        $check_exits_token = \Faoxima\Core\Database\Db::select("botsaz", "*", "bot_token", $data['token'], "count");
        if ($check_exits_token != 0)
            EndpointSupport::sendJsonResponse(false, "Token already exists");
        $admin_ids = json_encode(array(
            $data['chat_id']
        ));
        $destination = dirname(getcwd());
        $defaultSource = $destination . '/vpnbot/Default';
        $dirsource = "$destination/vpnbot/{$data['chat_id']}{$getInfoToken['result']['username']}";


        if (!is_dir($defaultSource)) {
            error_log('Default bot template missing at: ' . $defaultSource);
            EndpointSupport::sendJsonResponse(false, "Default bot template not found on server", [], 500);
        }


        if (is_dir($dirsource) && !\Faoxima\Core\Support\Filesystem::deleteDirectory($dirsource)) {
            error_log('Failed to remove existing bot directory: ' . $dirsource);
            EndpointSupport::sendJsonResponse(false, "Failed to clean previous bot directory", [], 500);
        }
        if (!\Faoxima\Core\Support\Filesystem::copyDirectoryContents($defaultSource, $dirsource)) {
            error_log('Failed to copy default bot files into: ' . $dirsource);
            EndpointSupport::sendJsonResponse(false, "Failed to install bot files", [], 500);
        }
        $configFile = $dirsource . "/config.php";
        if (!is_file($configFile)) {
            error_log('config.php missing in newly-created bot directory: ' . $configFile);
            EndpointSupport::sendJsonResponse(false, "Bot config file missing", [], 500);
        }
        $contentconfig = file_get_contents($configFile);
        $new_code = str_replace('BotTokenNew', $data['token'], $contentconfig);
        file_put_contents($configFile, $new_code);
        telegram('setWebhook', ['url' => "https://$domainhosts/vpnbot/{$data['chat_id']}{$getInfoToken['result']['username']}/index.php"], $data['token']);
        @file_get_contents("https://api.telegram.org/bot{$data['token']}/sendmessage?chat_id={$data['chat_id']}&text=✅ کاربر عزیز ربات شما با موفقیت نصب گردید.");
        $datasetting = json_encode(array(
            "minpricetime" => 4000,
            "pricetime" => 4000,
            "minpricevolume" => 4000,
            "pricevolume" => 4000,
            "support_username" => "@support",
            "Channel_Report" => 0,
            "cart_info" => "جهت پرداخت مبلغ را به شماره کارت زیر واریز نمایید",
            'show_product' => true,
        ));
        $value = "{}";
        $stmt = $pdo->prepare("INSERT INTO botsaz (id_user,bot_token,admin_ids,username,time,setting,hide_panel) VALUES (:id_user,:bot_token,:admin_ids,:username,:time,:setting,:hide_panel)");
        $stmt->bindParam(':id_user', $data['chat_id'], PDO::PARAM_STR);
        $stmt->bindParam(':bot_token', $data['token'], PDO::PARAM_STR);
        $stmt->bindParam(':admin_ids', $admin_ids);
        $stmt->bindParam(':username', $getInfoToken['result']['username'], PDO::PARAM_STR);
        $stmt->bindParam(':time', date('Y/m/d H:i:s'), PDO::PARAM_STR);
        $stmt->bindParam(':setting', $datasetting, PDO::PARAM_STR);
        $stmt->bindParam(':hide_panel', $value, PDO::PARAM_STR);
        $stmt->execute();
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function removeAgentBot(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        $contentbot = \Faoxima\Core\Database\Db::select("botsaz", "*", "id_user", $data['chat_id'], "select");
        if (!$contentbot)
            EndpointSupport::sendJsonResponse(false, "User does not have an active bot.", [], 200);


        try {
            $stmt = $pdo->prepare("DELETE FROM botsaz WHERE id_user = :id_user");
            $stmt->bindParam(':id_user', $data['chat_id'], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to delete botsaz row for user {$data['chat_id']}: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Failed to remove bot record", [], 500);
        }

        $destination = dirname(getcwd());
        $dirsource = "$destination/vpnbot/{$data['chat_id']}{$contentbot['username']}";
        if (is_dir($dirsource) && !\Faoxima\Core\Support\Filesystem::deleteDirectory($dirsource)) {
            error_log('Failed to remove bot directory: ' . $dirsource);
        }
        if (!empty($contentbot['bot_token'])) {
            @file_get_contents("https://api.telegram.org/bot{$contentbot['bot_token']}/deletewebhook");
        }
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setPriceVolumeAgentBot(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!isset($data['amount']) || empty($data['amount']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        $bot_info = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "setting", "id_user", $data['chat_id'], "select")['setting'], true);
        $bot_info['minpricevolume'] = $data['amount'];
        \Faoxima\Core\Database\Db::update("botsaz", "setting", json_encode($bot_info), "id_user", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setPriceTimeAgentBot(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);


        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!isset($data['amount']) || empty($data['amount']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        $bot_info = json_decode(\Faoxima\Core\Database\Db::select("botsaz", "setting", "id_user", $data['chat_id'], "select")['setting'], true);
        $bot_info['minpricetime'] = $data['amount'];
        \Faoxima\Core\Database\Db::update("botsaz", "setting", json_encode($bot_info), "id_user", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setPanelAgentShow(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);

        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!is_array($data['panels']))
            EndpointSupport::sendJsonResponse(false, "json invalid", [], 200);
        \Faoxima\Core\Database\Db::update("botsaz", "hide_panel", json_encode($data['panels']), "id_user", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }

    public static function setLimitChangeLocation(string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);

        if (!isset($data['chat_id']) || empty($data['chat_id']))
            EndpointSupport::sendJsonResponse(false, "user-id empty", [], 200);
        if (!isset($data['Limit']))
            EndpointSupport::sendJsonResponse(false, "Limit empty", [], 200);
        \Faoxima\Core\Database\Db::update("user", "limitchangeloc", $data['Limit'], "id", $data['chat_id']);
        EndpointSupport::sendJsonResponse(true, "Successful");
    }
}
