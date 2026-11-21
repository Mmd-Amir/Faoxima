<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use ManagePanel;
use Throwable;

final class InvoicesHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $limit = \Faoxima\Api\Runtime\Input::intRange($this->data, 'limit', 1, 10, 10);
        $page  = \Faoxima\Api\Runtime\Input::intMin($this->data, 'page', 1, 1);
        $offset = ($page - 1) * $limit;

        $search = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'q');

        $where = "id_user = :user_id
                  AND (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume'
                       OR Status = 'sendedwarn' OR Status = 'send_on_hold'
                       OR Status = 'disabled' OR Status = 'disablebyadmin')";
        $params = [':user_id' => $this->user['id']];

        if ($search !== null) {
            $where .= " AND username LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $totalItems = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
            "SELECT COUNT(*) FROM invoice WHERE {$where}",
            $params
        );
        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0;

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $rows = \Faoxima\Api\Runtime\Db::fetchAll(
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
            $queuedRows = \Faoxima\Api\Runtime\Db::fetchAll(
                "SELECT DISTINCT username FROM queued_renewal WHERE status IN (" . \Faoxima\Services\Renewal\ReservationStore::unresolvedSql() . ") AND username IN (" . implode(',', $placeholders) . ")",
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
                        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_time';
                    }
                    $row['status'] = 'end_of_time';
                } elseif ($st === 'limited' || ($dl > 0.0 && $ut >= $dl)) {
                    if (($row['Status'] ?? '') !== 'end_of_volume') {
                        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_volume', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_volume';
                    }
                    $row['status'] = 'end_of_volume';
                } elseif ($st === 'on_hold') {
                    if (($row['Status'] ?? '') !== 'send_on_hold') {
                        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'send_on_hold', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'send_on_hold';
                    }
                    $row['status'] = 'send_on_hold';
                } elseif ($st === 'disabled') {
                    if (($row['Status'] ?? '') !== 'disablebyadmin' && ($row['Status'] ?? '') !== 'disabled') {
                        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'disabled', 'id_invoice', $row['id_invoice']);
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
                $unitSeconds = (($row['name_product'] ?? '') === 'سرویس تست') ? 3600 : 86400;
                $calcExpire = ($timeSell > 0 && $serviceTime > 0) ? ($timeSell + ($serviceTime * $unitSeconds)) : 0;

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
                            \Faoxima\Core\Database\Db::update('invoice', 'user_info', json_encode($liveData, JSON_UNESCAPED_UNICODE), 'id_invoice', $row['id_invoice']);
                            if ($liveSt === 'expired') {
                                \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_time';
                                $row['status'] = 'end_of_time';
                            } elseif ($liveSt === 'limited') {
                                \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_volume', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_volume';
                                $row['status'] = 'end_of_volume';
                            } elseif ($liveSt === 'on_hold') {
                                \Faoxima\Core\Database\Db::update('invoice', 'Status', 'send_on_hold', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'send_on_hold';
                                $row['status'] = 'send_on_hold';
                            } else {
                                \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                                $row['Status'] = 'end_of_time';
                                $row['status'] = 'end_of_time';
                            }
                        } else {
                            \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                            $row['Status'] = 'end_of_time';
                            $row['status'] = 'end_of_time';
                        }
                    } catch (Throwable $e) {
                        \Faoxima\Core\Database\Db::update('invoice', 'Status', 'end_of_time', 'id_invoice', $row['id_invoice']);
                        $row['Status'] = 'end_of_time';
                        $row['status'] = 'end_of_time';
                    }
                }
            }

            if (!isset($row['status'])) {
                $row['status'] = $row['Status'] ?? 'active';
            }
            $row['has_queued_renewal'] = isset($queuedUsernames[(string)$row['username']]);
            $row['display_username'] = \Faoxima\Services\Panels\Drivers\Guard::guardDisplayUsername((string)$row['username'], (string)($row['Service_location'] ?? ''));
        }
        unset($row);


        \Faoxima\Api\Runtime\Response::ok([
            'items'       => $rows,
            'total'       => $totalItems,
            'total_pages' => $totalPages,
            'page'        => $page,
            'limit'       => $limit,
        ]);
    }
}

