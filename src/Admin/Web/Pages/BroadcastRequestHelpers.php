<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

final class BroadcastRequestHelpers
{
    public static function faoxima_target_sql(string $target): array {
    switch ($target) {
        case 'active':
            return [" WHERE (User_Status IS NULL OR User_Status != 'block')", []];
        case 'agents':
            return [" WHERE agentstatus = 'agent' OR agentstatus = 'agent2'", []];
        case 'all_users':
        default:

            return [" WHERE (User_Status IS NULL OR User_Status != 'block')", []];
    }
}

    public static function fx_broadcast_count(PDO $pdo, array $query): array
{
    $target = (string)($query['target'] ?? 'all_users');
    [$whereSql, $params] = self::faoxima_target_sql($target);
    try {
        $sql = "SELECT COUNT(*) FROM user" . $whereSql;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'count' => (int)$stmt->fetchColumn()];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

    public static function fx_broadcast_send_batch(PDO $pdo, array $query, array $post): array
{
    $target  = (string)($query['target'] ?? 'all_users');
    $offset  = max(0, (int)($query['offset'] ?? 0));
    $batch   = max(1, min(30, (int)($query['batch'] ?? 15)));
    $message = (string)($post['message'] ?? '');
    $parse   = (string)($post['parse'] ?? 'HTML');
    if ($parse !== 'HTML' && $parse !== 'Markdown' && $parse !== 'plain') $parse = 'HTML';
    $parseMode = $parse === 'plain' ? '' : $parse;


    if (!in_array($target, ['all_users', 'active', 'agents'], true)) {
        return ['ok' => false, 'error' => 'target_invalid'];
    }

    $trim = trim($message);
    if ($trim === '') {
        return ['ok' => false, 'error' => 'message_empty'];
    }

    if (mb_strlen($message, 'UTF-8') > 4000) {
        return ['ok' => false, 'error' => 'message_too_long'];
    }

    [$whereSql, $params] = self::faoxima_target_sql($target);
    $sql = "SELECT id FROM user" . $whereSql . " ORDER BY id ASC LIMIT :lim OFFSET :off";
    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $batch, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'query: ' . $e->getMessage()];
    }

    $sent = 0; $failed = 0;
    foreach ($rows as $r) {
        $uid = (int)$r['id'];
        if ($uid <= 0) continue;
        try {
            $res = sendmessage($uid, $message, null, $parseMode);
            if (is_array($res) && !empty($res['ok'])) $sent++;
            else $failed++;
        } catch (\Throwable $e) {
            $failed++;
        }
    }

    return [
        'ok'       => true,
        'sent'     => $sent,
        'failed'   => $failed,
        'next_off' => $offset + count($rows),
        'done'     => count($rows) < $batch,
    ];
}
}
