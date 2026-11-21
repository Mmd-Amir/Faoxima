<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $pdo.
 * Inherited or exported by reference: $TK_REACTION_EMOJI, $emoji, $out, $r, $rows, $st, $thread.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketActions
{
    public static function handle(
        &$TK_REACTION_EMOJI,
        $adminWebPage,
        &$emoji,
        &$out,
        $pdo,
        &$r,
        &$rows,
        &$st,
        &$thread
    ) {
        if (($adminWebPage ?? null) !== 'tickets.php') {
            return;
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_ticket_seen') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM support_message")->fetchColumn();
                $st = $pdo->prepare("UPDATE admin SET last_ticket_seen = ? WHERE username = ?");
                $st->execute([$maxId, $_SESSION['user'] ?? '']);
                echo json_encode(['ok' => true]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false]);
            }
            exit;
        }

        $TK_REACTION_EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'react') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
                    echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
                }
                $mid = (int)($_POST['message_id'] ?? 0);
                $emoji = trim((string)($_POST['emoji'] ?? ''));
                if ($mid <= 0 || ($emoji !== '' && !in_array($emoji, $TK_REACTION_EMOJI, true))) {
                    echo json_encode(['ok' => false, 'error' => 'invalid']); exit;
                }
                $msgRow = $pdo->prepare("SELECT result FROM support_message WHERE id = ?");
                $msgRow->execute([$mid]);
                $msgResult = $msgRow->fetchColumn();
                if ($msgResult === false || (string)$msgResult === 'admin') {
                    echo json_encode(['ok' => false, 'error' => 'invalid']); exit;
                }
                $actorId = 'admin:' . (string)($_SESSION['user'] ?? '');
                if ($emoji === '') {
                    $pdo->prepare("DELETE FROM support_message_reaction WHERE message_id = ? AND actor_id = ?")->execute([$mid, $actorId]);
                } else {
                    $pdo->prepare("INSERT INTO support_message_reaction (message_id, actor, actor_id, emoji, time)
                            VALUES (?, 'admin', ?, ?, ?)
                            ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), time = VALUES(time)")
                        ->execute([$mid, $actorId, $emoji, date('Y/m/d H:i:s')]);
                }
                $rows = $pdo->prepare("SELECT actor, actor_id, emoji FROM support_message_reaction WHERE message_id = ?");
                $rows->execute([$mid]);
                $out = [];
                foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $out[] = ['emoji' => $r['emoji'], 'actor' => $r['actor'], 'mine' => $r['actor_id'] === $actorId];
                }
                echo json_encode(['ok' => true, 'reactions' => $out]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false, 'error' => 'server']);
            }
            exit;
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'bulk_delete') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
                    echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
                }
                $raw = json_decode((string)($_POST['trackings'] ?? '[]'), true);
                if (!is_array($raw)) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
                $trackings = [];
                foreach ($raw as $t) {
                    $t = (string)$t;
                    if ($t !== '' && preg_match('/^[a-f0-9]+$/i', $t)) $trackings[] = $t;
                }
                $trackings = array_values(array_unique($trackings));
                if (count($trackings) === 0) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
                if (count($trackings) > 500) $trackings = array_slice($trackings, 0, 500);

                \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_delete_trackings($pdo, $trackings);
                echo json_encode(['ok' => true, 'deleted' => count($trackings)]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false, 'error' => 'server']);
            }
            exit;
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'poll_state') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $T = trim((string)($_GET['t'] ?? ''));
                if ($T === '') { echo json_encode(['ok' => false]); exit; }
                $rows = $pdo->prepare("SELECT id, result, seen_by_user FROM support_message WHERE Tracking = ?");
                $rows->execute([$T]);
                $thread = $rows->fetchAll(PDO::FETCH_ASSOC);
                $reactionsByMsg = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_thread_reactions($pdo, $thread, 'admin:' . (string)($_SESSION['user'] ?? ''));
                $out = [];
                foreach ($thread as $r) {
                    $mid = (int)$r['id'];
                    $side = ($r['result'] === 'admin') ? 'admin' : 'user';
                    $out[] = [
                        'id'        => $mid,
                        'seen'      => ($side === 'admin') ? ((int)($r['seen_by_user'] ?? 0) === 1) : true,
                        'reactions' => $reactionsByMsg[$mid] ?? [],
                    ];
                }
                echo json_encode(['ok' => true, 'messages' => $out]);
            } catch (\Throwable $e) {
                echo json_encode(['ok' => false]);
            }
            exit;
        }

        return true;
    }
}
