<?php

namespace Faoxima\Admin\Telegram\Support;

use PDO;

final class CoreFunctionsHelpers
{
    public static function rxAdminTelegramTicketReply(string $T, $text, $caption, $photo, $video, $photoid, $videoid): array
    {
        $mediaToken = $photo ? "\n[[photo:$photoid]]" : ($video ? "\n[[video:$videoid]]" : "");
        $bodytext = trim(((string)$text !== '' ? (string)$text : (string)$caption) . $mediaToken);
        $userKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ', 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
        $userMsgText = (string)$text !== '' ? (string)$text : (string)$caption;
        $toUser = "📩 پاسخ پشتیبانی به تیکت <code>$T</code> :" . ($userMsgText !== '' ? "\n\n" . $userMsgText : ($photo ? "\n\n(تصویر پیوست ارسال شد)" : ($video ? "\n\n(ویدیو پیوست ارسال شد)" : "")));
        return [$bodytext, $userKb, $toUser];
    }

    public static function rxAdminClearPanelCredentials(): void
    {
        \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "username", "null");
        \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "password", "null");
    }

    public static function rxAdminPanelUrlInput($text, $user, string $typeKey): array
    {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            return ['status' => 'invalid_url'];
        }
        $state = json_decode($user['Processing_value'], true);
        $warning = \Faoxima\Services\Panels\PanelSetup::rxPanelUrlPathWarning($text, is_array($state) ? ($state[$typeKey] ?? '') : '');
        if ($warning !== '') {
            return ['status' => 'invalid_path', 'warning' => $warning];
        }
        return ['status' => 'valid', 'url' => rtrim($text, '/')];
    }

    public static function rxAdminPeriodStatistics(PDO $pdo, string $start_time, string $end_time): array
    {
        $start_time_timestamp = strtotime($start_time);
        $end_time_timestamp = strtotime($end_time);
        $sql = "SELECT COUNT(*) AS count,SUM(price_product) as sum FROM " . \Faoxima\Admin\Reporting\Statistics::rx_stats_sales_source($pdo) . " sales WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend) AND " . \Faoxima\Admin\Reporting\Statistics::rx_stats_historical_sale_predicate();
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time_timestamp);
        $stmt->bindParam(':requestedDateend', $end_time_timestamp);
        $stmt->execute();
        $statorder = $stmt->fetch(PDO::FETCH_ASSOC);
        $count_order = $statorder['count'];
        $sum_order = number_format($statorder['sum'], 0);
        $sql = "SELECT COUNT(*) AS count FROM invoice WHERE (time_sell BETWEEN :requestedDate AND :requestedDateend)  AND name_product = 'سرویس تست'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time_timestamp);
        $stmt->bindParam(':requestedDateend', $end_time_timestamp);
        $stmt->execute();
        $count_test = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extend_user' AND status = 'paid'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time);
        $stmt->bindParam(':requestedDateend', $end_time);
        $stmt->execute();
        $extend_stat = $stmt->fetch(PDO::FETCH_ASSOC);
        $count_extend = $extend_stat['count'];
        $sum_extend = number_format($extend_stat['sum'], 0);
        $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_user'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time);
        $stmt->bindParam(':requestedDateend', $end_time);
        $stmt->execute();
        $extra_volume_stat = $stmt->fetch(PDO::FETCH_ASSOC);
        $count_extra_volume = $extra_volume_stat['count'];
        $sum_extra_volume = number_format($extra_volume_stat['sum'], 0);
        $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE  (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'extra_time_user'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time);
        $stmt->bindParam(':requestedDateend', $end_time);
        $stmt->execute();
        $extra_time_stat = $stmt->fetch(PDO::FETCH_ASSOC);
        $count_extra_time = $extra_time_stat['count'];
        $sum_extrat_time = number_format($extra_time_stat['sum'], 0);
        $sql = "SELECT COUNT(*) AS count,SUM(price) as sum FROM service_other WHERE (time BETWEEN :requestedDate AND :requestedDateend) AND type = 'change_location'";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':requestedDate', $start_time);
        $stmt->bindParam(':requestedDateend', $end_time);
        $stmt->execute();
        $change_location_stat = $stmt->fetch(PDO::FETCH_ASSOC);
        $count_change_location = $change_location_stat['count'];
        $sum_change_location = number_format($change_location_stat['sum'], 0);
        $stmt = $pdo->prepare("SELECT * FROM user WHERE  (register BETWEEN :requestedDate AND :requestedDateend)  AND register != 'none'");
        $stmt->bindParam(':requestedDate', $start_time_timestamp);
        $stmt->bindParam(':requestedDateend', $end_time_timestamp);
        $stmt->execute();
        $countuser_new = $stmt->rowCount();
        return [
            'count_order' => $count_order,
            'sum_order' => $sum_order,
            'count_test' => $count_test,
            'count_extend' => $count_extend,
            'sum_extend' => $sum_extend,
            'count_extra_volume' => $count_extra_volume,
            'sum_extra_volume' => $sum_extra_volume,
            'count_extra_time' => $count_extra_time,
            'sum_extrat_time' => $sum_extrat_time,
            'count_change_location' => $count_change_location,
            'sum_change_location' => $sum_change_location,
            'countuser_new' => $countuser_new,
        ];
    }
}
