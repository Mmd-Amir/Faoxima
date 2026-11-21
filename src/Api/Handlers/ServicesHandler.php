<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class ServicesHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            \Faoxima\Api\Runtime\Response::badRequest('country_id is required');
        }
        $panel = $this->loadPanelByCode($codePanel);
        if (\Faoxima\Services\Panels\Drivers\JovadPanel::jovadMiniAppReject($panel)) {
            return;
        }

        $categoryId = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'category_id');
        $timeRangeDay = \Faoxima\Api\Runtime\Input::nullableString($this->data, 'time_range_day');

        $categoryRow = null;
        if ($categoryId !== null && $categoryId !== '0') {
            $categoryRow = \Faoxima\Core\Database\Db::select('category', '*', 'id', $categoryId, 'select');
            if (!is_array($categoryRow) || !isset($categoryRow['remark'])) {
                \Faoxima\Api\Runtime\Response::badRequest('category not found (invalid category_id)');
            }
        }

        $sql = "SELECT * FROM product WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all')";
        $params = [':location' => $panel['name_panel']];

        $userAgent = $this->user['agent'] ?? 'f';
        $sql .= " AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))";
        $params[':agent'] = $userAgent;

        if ($categoryRow !== null) {
            $sql .= " AND FIND_IN_SET(:category, category) > 0";
            $params[':category'] = $categoryRow['remark'];
        }
        if ($timeRangeDay !== null && $timeRangeDay !== '0' && $timeRangeDay !== '') {
            $sql .= " AND Service_time = :service_time";
            $params[':service_time'] = $timeRangeDay;
        }

        $sql .= " ORDER BY (position = 0) ASC, position ASC, id ASC";

        $rows = \Faoxima\Api\Runtime\Db::fetchAll($sql, $params);
        $discount = (int)($this->user['pricediscount'] ?? 0);

        $nationalPanel = method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel);
        $fxEnabled = \Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, 'product') !== null;
        $fxQuote = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteToken($panel, 'product');

        $list = [];
        foreach ($rows as $row) {
            if (!$this->productIsAllowedForAgent($row, $this->user['agent'])) continue;
            if ($nationalPanel && method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmStockHasAvailableForProduct')
                && !\Faoxima\Services\Fulfillment\Inventory::nmStockHasAvailableForProduct(\Faoxima\Api\Runtime\Db::pdo(), $panel, $row)) {
                continue;
            }

            $basePrice = $row['price_product'] ?? 0;
            $row = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($row, $panel);
            $price = (float)($row['price_product'] ?? 0);
            if ($discount !== 0) {
                $price = $price - (($price * $discount) / 100);
            }
            $price = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($price, $panel, 'product');

            $list[] = [
                'id'             => $row['code_product'],
                'name'           => $row['name_product'],
                'description'    => $row['note'] ?? '',
                'price'          => $price,
                'base_price'     => (float) $basePrice,
                'fx_enabled'     => $fxEnabled,
                'fx_quote'       => $fxQuote,
                'traffic_gb'     => (int)($row['Volume_constraint'] ?? 0),
                'time_days'      => (int)($row['Service_time'] ?? 0),
                'category_id'    => $categoryRow['id'] ?? null,
                'country_id'     => $panel['code_panel'],
                'time_range_id'  => (int)($row['Service_time'] ?? 0),
                'ip_limit'       => (int)($row['ip_limit'] ?? 0),
                'ip_limit_guard_active' => (($panel['ip_limit_guard'] ?? '') === 'onipguard'),
                'symbolic_limit_enabled' => (\Faoxima\Telegram\Support\Texts::faoxima_symbolic_limit_label($row) !== null),
                'symbolic_limit_users'   => (int)($row['symbolic_limit_users'] ?? 0),
                'hwid_limit'     => (int)($row['hwid_limit'] ?? 0),
                'hwid_limit_supported' => in_array($panel['type'] ?? '', ['pasarguard', 'remnawave', 'x-ui_single'], true),
            ];
        }

        \Faoxima\Api\Runtime\Response::ok($list);
    }
}

