<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

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

        $panel['_custom_quote_user'] = [(string)$this->user['id'], (string)($this->user['agent'] ?? 'f'), (int)($this->user['pricediscount'] ?? 0)];
        $custom = FaoximaInput::array($this->data, 'custom');
        $monetary = null;
        if (!empty($custom)) {
            $volume = max(0, (int)($custom['volume_gb'] ?? 0));
            $days = max(0, (int)($custom['time_days'] ?? 0));
            $agent = (string)($this->user['agent'] ?? 'f');
            $pricingPanel = fx_custom_renew_panel($panel, $agent);
            if ($volume < (fx_panel_raw_unit_price($pricingPanel, 'mainvolume', $agent) ?? 0)
                || $volume > (fx_panel_raw_unit_price($pricingPanel, 'maxvolume', $agent) ?? 0)
                || $days < (fx_panel_raw_unit_price($pricingPanel, 'maintime', $agent) ?? 0)
                || $days > (fx_panel_raw_unit_price($pricingPanel, 'maxtime', $agent) ?? 0)) {
                FaoximaResponse::badRequest('حجم یا زمان نامعتبر است.');
            }
            try {
                $durationPricing = fx_duration_config($panel, $agent)['enabled'];
                $monetary = fx_custom_resolve($pricingPanel, $agent, $durationPricing ? ($custom['volume_gb'] ?? 0) : $volume, $durationPricing ? ($custom['time_days'] ?? 0) : $days);
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
            $kinds = ['custom_volume', 'custom_time'];
            try {
                $panel = fx_custom_api_quote_panel($panel, $this->user, ['price_product' => $monetary['price'], 'code_product' => 'customvolume',
                    'Volume_constraint' => $volume, 'Service_time' => $days], 'extend', FaoximaInput::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
            $monetary['price'] = $panel['_custom_quote_total'];
            $monetary['fx_quote'] = fx_quote_token($panel, $kinds);
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

        $managePanel = new ManagePanel();
        $reservationUi = rxReservationUiEnabled($panel);
        $snapshot = rx_pf_panel_snapshot($managePanel, (string)$panel['name_panel'], (string)$invoice['username'], (string)($panel['type'] ?? ''));
        $usableSnapshot = !empty($snapshot['ok']) && !empty($snapshot['exists']) ? $snapshot : null;
        $selection = null;
        if ($reservationUi) {
            $selection = rxReservationPanelSelection($panel);
        }

        $preview = rxRenewalPreviewForService($managePanel, $panel, (string)$invoice['username'], $volume, $days, [
            'reservation' => $selection,
            'snapshot' => $snapshot,
            'id_invoice' => (string)$invoice['id_invoice'],
            'stage' => 'api_preview',
        ]);
        $plan = is_array($preview['plan'] ?? null) ? $preview['plan'] : null;

        $reservation = null;
        if ($reservationUi) {
            $check = rxReservationNormalizeSelection($selection, $usableSnapshot, time());
            if (empty($check['ok'])) {
                rx_renewal_log('api_preview_selection', (string)$check['reason'], ['panel' => $panel, 'username' => (string)$invoice['username'], 'invoice_id' => (string)$invoice['id_invoice'], 'invoice_status' => $invoice['Status'] ?? null, 'method' => (string)($panel['Methodextend'] ?? ''), 'volume_gb' => $volume, 'days' => $days, 'snapshot' => $snapshot, 'detail' => $check['detail'] ?? null, 'mutation' => 'none']);
            }
            $reservation = [
                'valid' => !empty($check['ok']),
                'error' => empty($check['ok']) ? rxRenewalReasonText((string)$check['reason'], $check) : '',
            ];
        }

        FaoximaResponse::ok([
            'pricing'     => $monetary,
            'available'   => !empty($preview['ok']) && ($plan !== null || ($preview['mode'] ?? '') !== 'immediate'),
            'blocked'     => !empty($preview['blocked']) || ($reservation !== null && empty($reservation['valid'])),
            'mode'        => (string)($preview['mode'] ?? 'immediate'),
            'reason'      => (string)($preview['reason'] ?? ''),
            'text'        => $reservationUi && empty($preview['blocked']) ? '' : (string)($preview['text'] ?? ''),
            'reservation' => $reservation,
            'plan'        => $plan === null || empty($plan['ok']) ? null : [
                'queued'             => !empty($plan['queued']),
                'policy'             => (string)($plan['policy'] ?? ''),
                'traffic_mode'       => (string)($plan['traffic_mode'] ?? ''),
                'time_mode'          => (string)($plan['time_mode'] ?? ''),
                'reset_usage'        => !empty($plan['reset_usage']),
                'method'             => (string)($plan['label'] ?? ''),
                'current_expire'     => (int)($plan['before_expire'] ?? 0),
                'new_expire'         => isset($plan['expire']) ? (int)$plan['expire'] : null,
                'pending'            => !empty($plan['target_pending']),
                'pending_duration'   => $plan['target_pending_duration'] ?? null,
                'current_data_limit' => (int)($plan['before_data_limit'] ?? 0),
                'used_traffic'       => (int)($plan['before_used_traffic'] ?? 0),
                'current_remaining'  => $plan['before_remaining'] ?? null,
                'purchased_days'     => $plan['purchased_days'] ?? 0,
                'purchased_volume'   => (int)($plan['purchased_volume_bytes'] ?? 0),
                'new_data_limit'     => isset($plan['data_limit']) ? (int)$plan['data_limit'] : null,
                'new_remaining'      => $plan['target_remaining'] ?? null,
                'reactivates'        => !empty($plan['enable']),
            ],
        ]);
    }
}
