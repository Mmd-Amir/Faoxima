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
                       OR Status = 'sendedwarn' OR Status = 'send_on_hold')";
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


        foreach ($rows as &$row) {
            if (!isset($row['status']) && isset($row['Status'])) {
                $row['status'] = $row['Status'];
            }
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

