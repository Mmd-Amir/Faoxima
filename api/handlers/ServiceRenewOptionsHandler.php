<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceRenewOptionsHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $username = FaoximaInput::nullableString($this->data, 'username');
        if ($username === null || $username === '') {
            FaoximaResponse::badRequest('username is required');
        }

        $invoice = FaoximaDb::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            FaoximaResponse::notFound('Service not found');
        }

        $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            FaoximaResponse::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            FaoximaResponse::fail(409, '❌ امکان تمدید در این پنل وجود ندارد');
        }

        $agent = (string)($this->user['agent'] ?? 'f');
        $statusShowPrice = panel_feature_enabled($panel, 'showprice') ? 'onshowprice' : 'offshowprice';
        $userDiscount = (int)($this->user['pricediscount'] ?? 0);


        $products = FaoximaDb::fetchAll(
            "SELECT * FROM product WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) ORDER BY (position = 0) ASC, position ASC, id ASC",
            [':loc' => $invoice['Service_location'], ':agent' => $agent]
        );

        $nationalPanel = function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($panel);

        $list = [];
        foreach ($products as $product) {
            if (!$this->productIsAllowedForAgent($product, $agent)) continue;
            if ($nationalPanel && function_exists('nmStockHasAvailableForProduct')
                && !nmStockHasAvailableForProduct($panel, $product)) {
                continue;
            }

            $price = (float)$product['price_product'];
            if ($userDiscount !== 0) {
                $price = $price - (($price * $userDiscount) / 100);
            }
            $price = (int) round($price);

            $list[] = [
                'code'         => (string)$product['code_product'],
                'name'         => (string)$product['name_product'],
                'volume_gb'    => (int)($product['Volume_constraint'] ?? 0),
                'time_days'    => (int)($product['Service_time'] ?? 0),
                'price'        => $price,
                'show_price'   => $statusShowPrice !== 'offshowprice',
                'note'         => (string)($product['note'] ?? ''),
                'ip_limit'     => (int)($product['ip_limit'] ?? 0),
                'ip_limit_guard_active' => (($panel['ip_limit_guard'] ?? '') === 'onipguard'),
                'symbolic_limit_enabled' => (faoxima_symbolic_limit_label($product) !== null),
                'symbolic_limit_users'   => (int)($product['symbolic_limit_users'] ?? 0),
            ];
        }


        $currentPlan = null;
        $serviceOther = FaoximaDb::fetchOne(
            "SELECT value FROM service_other
              WHERE username = :u AND type = 'extend_user' AND status = 'paid'
              ORDER BY time DESC LIMIT 1",
            [':u' => $invoice['username']]
        );
        $currentCode = null;
        if (is_array($serviceOther) && !empty($serviceOther['value'])) {
            $decoded = json_decode((string)$serviceOther['value'], true);
            if (is_array($decoded) && !empty($decoded['code_product'])) {
                $currentCode = (string)$decoded['code_product'];
            }
        }
        if ($currentCode === null) {
            $byName = select('product', '*', 'name_product', $invoice['name_product'], 'select');
            if (is_array($byName) && !empty($byName['code_product'])) {
                $currentCode = (string)$byName['code_product'];
            }
        }
        if ($currentCode !== null) {
            foreach ($list as $p) {
                if ($p['code'] === $currentCode) {
                    $currentPlan = $p;
                    $currentPlan['name'] = '♻️ ' . $currentPlan['name'] . ' (پلن فعلی)';
                    break;
                }
            }
        }


        $custom_only = (count($list) === 0)
            || in_array($invoice['name_product'] ?? '', ['🛍 حجم دلخواه', '⚙️ سرویس دلخواه'], true);

        $customPriceVol  = $this->jsonAgentValue($panel['pricecustomvolume'] ?? '', $agent);
        $customPriceTime = $this->jsonAgentValue($panel['pricecustomtime']   ?? '', $agent);
        $customStatus    = (string) $this->jsonAgentValue($panel['customvolume'] ?? '', $agent);
        $minVol  = (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent);
        $maxVol  = (int) $this->jsonAgentValue($panel['maxvolume']  ?? '', $agent);
        $minTime = (int) $this->jsonAgentValue($panel['maintime']   ?? '', $agent);
        $maxTime = (int) $this->jsonAgentValue($panel['maxtime']    ?? '', $agent);

        $hasVolPrice  = ($customPriceVol !== '' && $customPriceVol !== null);
        $hasTimePrice = ($customPriceTime !== '' && $customPriceTime !== null);
        $timeVariable = ($maxTime > $minTime);
        $customConfigured = (($panel['type'] ?? '') !== 'Manualsale')
            && $hasVolPrice
            && (!$timeVariable || $hasTimePrice);
        $customEnabled   = $customConfigured
            && ($customStatus === '1' || $custom_only)
            && !$nationalPanel;


        FaoximaResponse::ok([
            'username'        => (string)$invoice['username'],
            'panel'           => [
                'code'  => (string)($panel['code_panel'] ?? ''),
                'name'  => (string)($panel['name_panel'] ?? ''),
                'type'  => (string)($panel['type'] ?? ''),
            ],
            'current_plan'    => $currentPlan,
            'products'        => $list,
            'show_price'      => $statusShowPrice !== 'offshowprice',
            'discount'        => $userDiscount,
            'balance'         => (float)($this->user['Balance'] ?? 0),
            'custom'          => [
                'enabled'        => $customEnabled,
                'price_per_gb'   => (int)$customPriceVol,
                'price_per_day'  => (int)$customPriceTime,
                'min_volume_gb'  => $minVol,
                'max_volume_gb'  => $maxVol,
                'min_time_days'  => $minTime,
                'max_time_days'  => $maxTime,
                'force'          => ($custom_only && !$nationalPanel),
            ],
        ]);
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) {
            return $this->pickAgent($raw, $agent, $default);
        }
        if (!is_string($raw)) return $default;
        $raw = trim($raw);
        if ($raw === '') return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return is_numeric($raw) ? $raw : $default;
        }
        return $this->pickAgent($decoded, $agent, $default);
    }

    private function pickAgent(array $map, string $agent, $default)
    {
        foreach ([$agent, 'allusers', 'f'] as $key) {
            if (array_key_exists($key, $map)) {
                $val = $map[$key];
                if ($val !== '' && $val !== null) {
                    return $val;
                }
            }
        }
        return $default;
    }
}
