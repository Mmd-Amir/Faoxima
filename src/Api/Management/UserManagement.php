<?php

namespace Faoxima\Api\Management;

use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class UserManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data, ?string $domainhosts, mixed $setting): void
    {
        switch ($data['actions'] ?? '') {
            case 'users':
                UserDirectory::users($pdo, $method, $data);
                return;
            case 'user':
                UserDirectory::user($pdo, $method, $data);
                return;
            case 'user_add':
                UserAccountManagement::userAdd($pdo, $method, $data, $setting);
                return;
            case 'block_user':
                UserAccountManagement::blockUser($method, $data, $setting);
                return;
            case 'verify_user':
                UserAccountManagement::verifyUser($method, $data);
                return;
            case 'change_status_user':
                UserAccountManagement::changeStatusUser($method, $data);
                return;
            case 'add_balance':
                UserFunds::addBalance($pdo, $method, $data);
                return;
            case 'withdrawal':
                UserFunds::withdrawal($pdo, $method, $data);
                return;
            case 'accept_number':
                UserAccountManagement::acceptNumber($method, $data);
                return;
            case 'send_message':
                UserAccountManagement::sendMessage($method, $data);
                return;
            case 'set_limit_test':
                UserAccountManagement::setLimitTest($pdo, $method, $data);
                return;
            case 'transfer_account':
                UserAccountManagement::transferAccount($pdo, $method, $data);
                return;
            case 'join_channel_exception':
                UserAccountManagement::joinChannelException($method, $data);
                return;
            case 'cron_notif':
                UserAccountManagement::cronNotif($method, $data);
                return;
            case 'manage_show_cart':
                UserAccountManagement::manageShowCart($method, $data);
                return;
            case 'zero_balance':
                UserFunds::zeroBalance($method, $data);
                return;
            case 'affiliates_users':
                UserDirectory::affiliatesUsers($pdo, $method, $data);
                return;
            case 'remove_affiliates':
                UserAccountManagement::removeAffiliates($method, $data);
                return;
            case 'remove_affiliate_user':
                UserAccountManagement::removeAffiliateUser($method, $data);
                return;
            case 'set_agent':
                UserAccountManagement::setAgent($method, $data);
                return;
            case 'set_expire_agent':
                UserAccountManagement::setExpireAgent($method, $data);
                return;
            case 'set_becoming_negative':
                UserFunds::setBecomingNegative($method, $data);
                return;
            case 'set_percentage_discount':
                UserFunds::setPercentageDiscount($method, $data);
                return;
            case 'active_bot_agent':
                AgentBotManagement::activeBotAgent($pdo, $method, $data, $domainhosts);
                return;
            case "remove_agent_bot":
                AgentBotManagement::removeAgentBot($pdo, $method, $data);
                return;
            case "set_price_volume_agent_bot":
                AgentBotManagement::setPriceVolumeAgentBot($method, $data);
                return;
            case "set_price_time_agent_bot":
                AgentBotManagement::setPriceTimeAgentBot($method, $data);
                return;
            case "SetPanelAgentShow":
                AgentBotManagement::setPanelAgentShow($method, $data);
                return;
            case "SetLimitChangeLocation":
                AgentBotManagement::setLimitChangeLocation($method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }
}
