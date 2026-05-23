<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServicesHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            FaoximaResponse::badRequest('country_id is required');
        }
        $panel = $this->loadPanelByCode($codePanel);

        $categoryId = FaoximaInput::nullableString($this->data, 'category_id');
        $timeRangeDay = FaoximaInput::nullableString($this->data, 'time_range_day');

        $categoryRow = null;
        if ($categoryId !== null && $categoryId !== '0') {
            $categoryRow = select('category', '*', 'id', $categoryId, 'select');
            if (!is_array($categoryRow) || !isset($categoryRow['remark'])) {
                FaoximaResponse::badRequest('category not found (invalid category_id)');
            }
        }

        $sql = "SELECT * FROM product WHERE (Location = :location OR Location = '/all')";
        $params = [':location' => $panel['name_panel']];

        $userAgent = $this->user['agent'] ?? 'f';
        $sql .= " AND (agent = :agent OR agent = 'all')";
        $params[':agent'] = $userAgent;

        if ($categoryRow !== null) {
            $sql .= " AND category = :category";
            $params[':category'] = $categoryRow['remark'];
        }
        if ($timeRangeDay !== null && $timeRangeDay !== '0' && $timeRangeDay !== '') {
            $sql .= " AND Service_time = :service_time";
            $params[':service_time'] = $timeRangeDay;
        }

        $rows = FaoximaDb::fetchAll($sql, $params);
        $discount = (int)($this->user['pricediscount'] ?? 0);

        $list = [];
        foreach ($rows as $row) {
            if (!$this->productIsAllowedForAgent($row, $this->user['agent'])) continue;

            $price = (float)($row['price_product'] ?? 0);
            if ($discount !== 0) {
                $price = $price - (($price * $discount) / 100);
            }

            $list[] = [
                'id'             => $row['code_product'],
                'name'           => $row['name_product'],
                'description'    => $row['note'] ?? '',
                'price'          => $price,
                'traffic_gb'     => (int)($row['Volume_constraint'] ?? 0),
                'time_days'      => (int)($row['Service_time'] ?? 0),
                'category_id'    => $categoryRow['id'] ?? null,
                'country_id'     => $panel['code_panel'],
                'time_range_id'  => (int)($row['Service_time'] ?? 0),
            ];
        }

        FaoximaResponse::ok($list);
    }
}

