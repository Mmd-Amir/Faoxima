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
        if (!empty($panel) && function_exists('nmEmergencyHidesPanel') && nmEmergencyHidesPanel((array)$panel)) {
            $emergencyMap = nmEmergencyReplacementMap();
            $srcCode = (string)$panel['code_panel'];
            if (isset($emergencyMap['by_code'][$srcCode])) {
                $panel = $emergencyMap['by_code'][$srcCode];
            }
        }
        if (empty($panel)) {
            FaoximaResponse::notFound('Panel not found');
        }
        if (($panel['status_extend'] ?? '') === 'off_extend') {
            FaoximaResponse::fail(409, '❌ امکان تمدید در این پنل وجود ندارد');
        }

        $agent = (string)($this->user['agent'] ?? 'f');
        $statusShowPriceRow = select('shopSetting', '*', 'Namevalue', 'statusshowprice', 'select');
        $statusShowPrice = is_array($statusShowPriceRow) ? (string)($statusShowPriceRow['value'] ?? 'onshowprice') : 'onshowprice';
        $userDiscount = (int)($this->user['pricediscount'] ?? 0);


        $products = FaoximaDb::fetchAll(
            "SELECT * FROM product WHERE (Location = :loc OR Location = '/all') AND (agent = :agent OR agent = 'all')",
            [':loc' => $invoice['Service_location'], ':agent' => $agent]
        );

        $list = [];
        foreach ($products as $product) {
            if (!$this->productIsAllowedForAgent($product, $agent)) continue;

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


        $customPriceVol  = $this->jsonAgentValue($panel['pricecustomvolume'] ?? '', $agent);
        $customPriceTime = $this->jsonAgentValue($panel['pricecustomtime']   ?? '', $agent);
        $minVol  = (int) $this->jsonAgentValue($panel['mainvolume'] ?? '', $agent);
        $maxVol  = (int) $this->jsonAgentValue($panel['maxvolume']  ?? '', $agent);
        $minTime = (int) $this->jsonAgentValue($panel['maintime']   ?? '', $agent);
        $maxTime = (int) $this->jsonAgentValue($panel['maxtime']    ?? '', $agent);


        $custom_only = (count($list) === 0)
            || in_array($invoice['name_product'] ?? '', ['🛍 حجم دلخواه', '⚙️ سرویس دلخواه'], true);

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
                'enabled'        => ($customPriceVol !== '' && $customPriceTime !== ''),
                'price_per_gb'   => (int)$customPriceVol,
                'price_per_day'  => (int)$customPriceTime,
                'min_volume_gb'  => $minVol,
                'max_volume_gb'  => $maxVol,
                'min_time_days'  => $minTime,
                'max_time_days'  => $maxTime,
                'force'          => $custom_only,
            ],
        ]);
    }

    private function jsonAgentValue($raw, string $agent, $default = '')
    {
        if (is_array($raw)) {
            return $raw[$agent] ?? $default;
        }
        if (!is_string($raw) || $raw === '') return $default;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return $default;
        return $decoded[$agent] ?? $default;
    }
}

