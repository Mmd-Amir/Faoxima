<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use CURLFile;

final class TicketsRequestHelpers
{
    public static function tk_jdate($raw)
{
    $s = trim((string)$raw);
    if ($s === '') return '';
    $ts = strtotime($s);
    if ($ts === false) return $s;
    return jdate('Y/m/d H:i', $ts, '', 'Asia/Tehran', 'fa');
}

    public static function tk_media($text)
{
    $t = (string)$text;
    $media = [];
    if (preg_match_all('/\[\[(photo|video):([^\]]+)\]\]/', $t, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            if (count($media) >= 5) break;
            $media[] = ['type' => $m[1], 'file_id' => $m[2]];
            $t = str_replace($m[0], '', $t);
        }
    }
    $t = preg_replace('/\[\[subj:[^\]]*\]\]/u', '', $t);
    return ['media' => $media, 'body' => trim((string)$t)];
}

    public static function tk_subject($text)
{
    if (preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$text, $m)) {
        return trim($m[1]);
    }
    return '';
}

    public static function tk_status_badge($s)
{
    if ($s === 'close') return ['بسته', 'badge-block'];
    if ($s === 'Unseen' || $s === 'Customerresponse') return ['پاسخ‌نداده', 'badge-warning'];
    if ($s === 'Answered') return ['پاسخ‌داده', 'badge-active'];
    return ['باز', 'badge-gray'];
}

    public static function tk_delete_trackings(PDO $pdo, array $trackings): int
{
    $deleted = 0;
    $trackings = array_values(array_unique(array_map('strval', $trackings)));
    foreach (array_chunk($trackings, 500) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $idStmt = $pdo->prepare("SELECT id, Tracking FROM support_message WHERE Tracking IN ($ph)");
        $idStmt->execute($chunk);
        $rows = $idStmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(function ($r) { return (int)$r['id']; }, $rows);
        if ($ids) {
            $idPh = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM support_message_reaction WHERE message_id IN ($idPh)")->execute($ids);
        }
        $del = $pdo->prepare("DELETE FROM support_message WHERE Tracking IN ($ph)");
        $del->execute($chunk);
        $deleted += count(array_unique(array_column($rows, 'Tracking')));
    }
    return $deleted;
}

    public static function tk_validate_reply_target(PDO $pdo, string $T, int $replyToId): ?int
{
    if ($replyToId > 0) {
        $rtChk = $pdo->prepare("SELECT id FROM support_message WHERE id = ? AND Tracking = ?");
        $rtChk->execute([$replyToId, $T]);
        if (!$rtChk->fetchColumn()) $replyToId = 0;
    }
    return $replyToId > 0 ? $replyToId : null;
}

    public static function tk_store_admin_message(PDO $pdo, $T, $departmentId, $recipient, $department, $body, string $time, ?int $replyToId): void
{
    $ins = $pdo->prepare("INSERT INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status,reply_to_id) VALUES (?,?,?,?,?,?,?,?,?)");
    $ins->execute([$T, $departmentId, $recipient, $department, $body, 'admin', $time, 'Answered', $replyToId]);
}

    public static function tk_uploads(array $files): array
{
    $uploads = [];
    if (isset($files['media']) && is_array($files['media']['tmp_name'] ?? null)) {
        $F = $files['media'];
        $n = count($F['tmp_name']);
        for ($i = 0; $i < $n && count($uploads) < 5; $i++) {
            if ((int)($F['error'][$i] ?? 1) !== 0) continue;
            $tmp = (string)($F['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            $mime = function_exists('mime_content_type') ? (string)(mime_content_type($tmp) ?: '') : (string)($F['type'][$i] ?? '');
            $uploads[] = ['tmp' => $tmp, 'mime' => $mime, 'name' => (string)($F['name'][$i] ?? 'file'), 'video' => stripos($mime, 'video') === 0];
        }
    }
    return $uploads;
}

    public static function tk_deliver_uploads(array $uploads, string $recipient, string $text): array
{
    $mediaTokens = [];
    if (count($uploads) === 1) {
        $u = $uploads[0];
        $cf = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
        if ($u['video']) {
            $res = telegram('sendVideo', ['chat_id' => $recipient, 'video' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
            $fid = $res['result']['video']['file_id'] ?? '';
            if ($fid !== '') $mediaTokens[] = "[[video:$fid]]";
        } else {
            $res = telegram('sendPhoto', ['chat_id' => $recipient, 'photo' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
            $ph = $res['result']['photo'] ?? [];
            $fid = (is_array($ph) && $ph) ? (end($ph)['file_id'] ?? '') : '';
            if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
        }
    } elseif (count($uploads) >= 2) {
        $mediaArr = [];
        $filesPayload = [];
        foreach ($uploads as $i => $u) {
            $key = 'tkf' . $i;
            $type = $u['video'] ? 'video' : 'photo';
            $entry = ['type' => $type, 'media' => 'attach://' . $key];
            if ($i === 0 && $text !== '') { $entry['caption'] = $text; $entry['parse_mode'] = 'HTML'; }
            $mediaArr[] = $entry;
            $filesPayload[$key] = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
        }
        $res = telegram('sendMediaGroup', array_merge(['chat_id' => $recipient, 'media' => $mediaArr], $filesPayload));
        foreach (($res['result'] ?? []) as $r) {
            if (isset($r['photo']) && is_array($r['photo'])) {
                $fid = end($r['photo'])['file_id'] ?? '';
                if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
            } elseif (isset($r['video']['file_id'])) {
                $mediaTokens[] = "[[video:" . $r['video']['file_id'] . "]]";
            }
        }
    }
    return $mediaTokens;
}

    public static function tk_thread_reactions(PDO $pdo, array $thread, string $adminActorId): array
{
    $ids = array_map(function ($r) { return (int)$r['id']; }, $thread);
    $reactionsByMsg = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rq = $pdo->prepare("SELECT message_id, actor, actor_id, emoji FROM support_message_reaction WHERE message_id IN ($ph)");
        $rq->execute($ids);
        foreach ($rq->fetchAll(PDO::FETCH_ASSOC) as $rr) {
            $mid = (int)$rr['message_id'];
            if (!isset($reactionsByMsg[$mid])) $reactionsByMsg[$mid] = [];
            $reactionsByMsg[$mid][] = ['emoji' => $rr['emoji'], 'actor' => $rr['actor'], 'mine' => $rr['actor_id'] === $adminActorId];
        }
    }
    return $reactionsByMsg;
}

    public static function tk_admin_ticket_page(PDO $pdo, string $tkWhereSql, array $tkParams): array
{
    $ticketSubjects = [];
    $tkPg = \Faoxima\Admin\Web\Support\Pagination::fx_paginate($pdo, "SELECT COUNT(*) FROM (SELECT 1 FROM support_message WHERE $tkWhereSql GROUP BY Tracking, iduser, name_departman) t", $tkParams, 5);
    $tkStmt = $pdo->prepare("SELECT Tracking, iduser, name_departman, MAX(id) last_id, MAX(time) last_time, MAX(status) status, COUNT(*) cnt FROM support_message WHERE $tkWhereSql GROUP BY Tracking, iduser, name_departman ORDER BY last_id DESC LIMIT :perPage OFFSET :offset");
    foreach ($tkParams as $k => $v) $tkStmt->bindValue($k, $v, PDO::PARAM_STR);
    $tkStmt->bindValue(':perPage', $tkPg['perPage'], PDO::PARAM_INT);
    $tkStmt->bindValue(':offset', $tkPg['offset'], PDO::PARAM_INT);
    $tkStmt->execute();
    $tickets = $tkStmt->fetchAll(PDO::FETCH_ASSOC);

    $tkTrackings = array_column($tickets, 'Tracking');
    if ($tkTrackings) {
        $tkPh = implode(',', array_fill(0, count($tkTrackings), '?'));
        $tkSubjStmt = $pdo->prepare("SELECT s.Tracking, s.text FROM support_message s INNER JOIN (SELECT Tracking, MIN(id) mid FROM support_message WHERE Tracking IN ($tkPh) GROUP BY Tracking) f ON s.id = f.mid");
        $tkSubjStmt->execute($tkTrackings);
        foreach ($tkSubjStmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $ticketSubjects[$fr['Tracking']] = self::tk_subject((string)$fr['text']);
        }
    }
    return [$tkPg, $tickets, $ticketSubjects];
}
}
