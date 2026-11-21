<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

final class PhoneVerification
{
    public static function submit(
        PDO $pdo,
        $from_id,
        $user_phone,
        $contact_id,
        array $setting,
        array $labels,
        $welcomeText,
        $request_contact,
        $keyboard,
    ): ?bool {
        if (empty($user_phone)) {
            sendmessage($from_id, $labels['false'], $request_contact, 'html');
            return null;
        }
        if ($contact_id != $from_id) {
            sendmessage($from_id, $labels['Warning'], $request_contact, 'html');
            return null;
        }
        if ($setting['iran_number'] == "onAuthenticationiran" && !preg_match("/989[0-9]{9}$/", $user_phone)) {
            sendmessage($from_id, $labels['erroriran'], $request_contact, 'html');
            return null;
        }
        sendmessage($from_id, $labels['active'], json_encode(['inline_keyboard' => [], 'remove_keyboard' => true]), 'html');
        if (method_exists(\Faoxima\Telegram\Rich\Diagnostics::class, 'rx_richDiag_traceSend')) {
            \Faoxima\Telegram\Rich\Diagnostics::rx_richDiag_traceSend('text_start', static function () use ($from_id, $welcomeText, $keyboard) {
                return \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'start', $welcomeText, $keyboard, 'html');
            });
        } else {
            \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'start', $welcomeText, $keyboard, 'html');
        }
        \Faoxima\Core\Database\Db::update("user", "number", $user_phone, "id", $from_id);
        if ($setting['verifystart'] == "onverify") {
            \Faoxima\Core\Database\Db::update("user", "verify", "1", "id", $from_id);
        }
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id, $pdo);
        return true;
    }
}
