<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class InvoicesHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $limit = FaoximaInput::intRange($this->data, 'limit', 1, 10, 10);
        $page  = FaoximaInput::intMin($this->data, 'page', 1, 1);
        $offset = ($page - 1) * $limit;

        $search = FaoximaInput::nullableString($this->data, 'q');

        $where = "id_user = :user_id
                  AND (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume'
                       OR Status = 'sendedwarn' OR Status = 'send_on_hold'
                       OR Status = 'disabled' OR Status = 'disablebyadmin')";
        $params = [':user_id' => $this->user['id']];

        if ($search !== null) {
            $where .= " AND username LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $totalItems = (int) FaoximaDb::fetchScalar(
            "SELECT COUNT(*) FROM invoice WHERE {$where}",
            $params
        );
        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0;

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $rows = FaoximaDb::fetchAll(
            "SELECT * FROM invoice
              WHERE {$where}
           ORDER BY time_sell DESC
              LIMIT :limit OFFSET :offset",
            $params
        );


        $queuedUsernames = [];
        if (!empty($rows)) {
            $usernames = array_values(array_unique(array_map(static fn($r) => (string)$r['username'], $rows)));
            $placeholders = [];
            $queuedParams = [];
            foreach ($usernames as $i => $uname) {
                $ph = ":qu{$i}";
                $placeholders[] = $ph;
                $queuedParams[$ph] = $uname;
            }
            $queuedRows = FaoximaDb::fetchAll(
                "SELECT DISTINCT username FROM queued_renewal WHERE status = 'pending' AND username IN (" . implode(',', $placeholders) . ")",
                $queuedParams
            );
            foreach ($queuedRows as $qr) {
                $queuedUsernames[(string)$qr['username']] = true;
            }
        }

        foreach ($rows as &$row) {
            $cachedUserInfo = null;
            if (!empty($row['user_info'])) {
                $cachedUserInfo = json_decode((string)$row['user_info'], true);
            }

            if (is_array($cachedUserInfo)) {
                $exp = is_numeric($cachedUserInfo['expire'] ?? null) ? (int)$cachedUserInfo['expire'] : 0;
                $dl  = is_numeric($cachedUserInfo['data_limit'] ?? null) ? (float)$cachedUserInfo['data_limit'] : 0.0;
                $ut  = is_numeric($cachedUserInfo['used_traffic'] ?? null) ? (float)$cachedUserInfo['used_traffic'] : 0.0;
                $st  = strtolower((string)($cachedUserInfo['status'] ?? ''));

                if ($st === 'expired' || ($exp > 0 && $exp <= time())) {
                    if (($row['Status'] ?? '') !== 'end_of_time') {
                        update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_time';
                    }
                    $row['status'] = 'end_of_time';
                } elseif ($st === 'limited' || ($dl > 0.0 && $ut >= $dl)) {
                    if (($row['Status'] ?? '') !== 'end_of_volume') {
                        update('invoice', 'Status', 'end_of_volume', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_volume';
                    }
                    $row['status'] = 'end_of_volume';
                } elseif ($st === 'on_hold') {
                    if (($row['Status'] ?? '') !== 'send_on_hold') {
                        update('invoice', 'Status', 'send_on_hold', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'send_on_hold';
                    }
                    $row['status'] = 'send_on_hold';
                } elseif ($st === 'disabled') {
                    if (($row['Status'] ?? '') !== 'disablebyadmin' && ($row['Status'] ?? '') !== 'disabled') {
                        update('invoice', 'Status', 'disabled', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'disabled';
                    }
                    $row['status'] = $row['Status'];
                } elseif ($st === 'active') {
                    $row['status'] = $row['Status'] ?? 'active';
                }
            }

            if (($row['Status'] ?? '') === 'active') {
                $timeSell = is_numeric($row['time_sell'] ?? null) ? (int)$row['time_sell'] : 0;
                $serviceTime = is_numeric($row['Service_time'] ?? null) ? (int)$row['Service_time'] : 0;
                $calcExpire = ($timeSell > 0 && $serviceTime > 0) ? ($timeSell + ($serviceTime * 86400)) : 0;

                if ($calcExpire > 0 && $calcExpire <= time()) {
                    try {
                        $managePanel = new ManagePanel();
                        $liveData = $managePanel->DataUser($row['Service_location'], $row['username']);
                        if (is_array($liveData) && !empty($liveData['status']) && $liveData['status'] !== 'Unsuccessful') {
                            $liveSt = strtolower((string)$liveData['status']);
                            $liveExp = is_numeric($liveData['expire'] ?? null) ? (int)$liveData['expire'] : 0;
                            $liveDl = is_numeric($liveData['data_limit'] ?? null) ? (float)$liveData['data_limit'] : 0.0;
                            $liveUt = is_numeric($liveData['used_traffic'] ?? null) ? (float)$liveData['used_traffic'] : 0.0;
                            if ($liveExp > 0 && $liveExp <= time()) {
                                $liveSt = 'expired';
                                $liveData['status'] = 'expired';
                            } elseif ($liveDl > 0.0 && $liveUt >= $liveDl) {
                                $liveSt = 'limited';
                                $liveData['status'] = 'limited';
                            }
                            update('invoice', 'user_info', json_encode($liveData, JSON_UNESCAPED_UNICODE), 'id_invoice', $row['id_invoice']);
                            if ($liveSt === 'expired') {
                                update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_time';
                                $row['status'] = 'end_of_time';
                            } elseif ($liveSt === 'limited') {
                                update('invoice', 'Status', 'end_of_volume', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_volume';
                                $row['status'] = 'end_of_volume';
                            } elseif ($liveSt === 'on_hold') {
                                update('invoice', 'Status', 'send_on_hold', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'send_on_hold';
                                $row['status'] = 'send_on_hold';
                            } else {
                                update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_time';
                                $row['status'] = 'end_of_time';
                            }
                        } else {
                            update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                            $row['Status'] = 'end_of_time';
                            $row['status'] = 'end_of_time';
                        }
                    } catch (Throwable $e) {
                        update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_time';
                        $row['status'] = 'end_of_time';
                    }
                }
            }

            if (!isset($row['status'])) {
                $row['status'] = $row['Status'] ?? 'active';
            }
            $row['has_queued_renewal'] = isset($queuedUsernames[(string)$row['username']]);
            $row['display_username'] = guardDisplayUsername((string)$row['username'], (string)($row['Service_location'] ?? ''));
        }
        unset($row);


        FaoximaResponse::ok([
            'items'       => $rows,
            'total'       => $totalItems,
            'total_pages' => $totalPages,
            'page'        => $page,
            'limit'       => $limit,
        ]);
    }
}

