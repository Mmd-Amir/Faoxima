<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class ServiceRenewPreviewHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $username = FaoximaInput::string($this->data, 'username');
        if ($username === '') {
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

        $custom = FaoximaInput::array($this->data, 'custom');
        if (!empty($custom)) {
            $volume = max(0, (int)($custom['volume_gb'] ?? 0));
            $days = max(0, (int)($custom['time_days'] ?? 0));
        } else {
            $code = FaoximaInput::string($this->data, 'product_code');
            if ($code === '') {
                FaoximaResponse::badRequest('product_code or custom is required');
            }
            $product = FaoximaDb::fetchOne(
                "SELECT Volume_constraint, Service_time FROM product
                  WHERE (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND code_product = :code
                  LIMIT 1",
                [':loc' => $invoice['Service_location'], ':code' => $code]
            );
            if (!is_array($product)) {
                FaoximaResponse::notFound('Product not found');
            }
            $volume = $product['Volume_constraint'];
            $days = $product['Service_time'];
        }

        $preview = rxRenewalPreviewForService(new ManagePanel(), $panel, (string)$invoice['username'], $volume, $days);
        $plan = is_array($preview['plan'] ?? null) ? $preview['plan'] : null;

        FaoximaResponse::ok([
            'available' => $plan !== null && !empty($plan['ok']),
            'blocked'   => !empty($preview['blocked']),
            'text'      => (string)($preview['text'] ?? ''),
            'plan'      => $plan === null || empty($plan['ok']) ? null : [
                'queued'             => !empty($plan['queued']),
                'reset_usage'        => !empty($plan['reset_usage']),
                'method'             => (string)($plan['label'] ?? ''),
                'current_expire'     => (int)($plan['before_expire'] ?? 0),
                'new_expire'         => isset($plan['expire']) ? (int)$plan['expire'] : null,
                'current_data_limit' => (int)($plan['before_data_limit'] ?? 0),
                'used_traffic'       => (int)($plan['before_used_traffic'] ?? 0),
                'current_remaining'  => $plan['before_remaining'] ?? null,
                'purchased_days'     => $plan['purchased_days'] ?? 0,
                'purchased_volume'   => (int)($plan['purchased_volume_bytes'] ?? 0),
                'new_data_limit'     => isset($plan['data_limit']) ? (int)$plan['data_limit'] : null,
                'new_remaining'      => $plan['target_remaining'] ?? null,
            ],
        ]);
    }
}
