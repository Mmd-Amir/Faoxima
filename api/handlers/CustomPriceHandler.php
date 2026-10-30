<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

final class CustomPriceHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            FaoximaResponse::badRequest('country_id is required');
        }
        $panel = $this->loadPanelByCode($codePanel);
        if (jovadMiniAppReject($panel)) {
            return;
        }
        if (panel_creation_limit_reached($panel)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_panel_limit_reached', 'ظرفیت ساخت کانفیگ در این پنل تکمیل شده است.'));
        }

        $panel['_custom_quote_user'] = [(string)$this->user['id'], (string)($this->user['agent'] ?? 'f'), (int)($this->user['pricediscount'] ?? 0)];
        $agent = $this->user['agent'] ?? 'f';

        $custom = $this->decodeJsonField($panel['customvolume'] ?? null);
        $main   = $this->decodeJsonField($panel['mainvolume'] ?? null);
        $max    = $this->decodeJsonField($panel['maxvolume'] ?? null);
        $minT   = $this->decodeJsonField($panel['maintime'] ?? null);
        $maxT   = $this->decodeJsonField($panel['maxtime'] ?? null);

        $isCustomActive = (int)($custom[$agent] ?? 0) === 1
            && ($panel['type'] ?? '') !== 'Manualsale';

        $trafficGb = FaoximaInput::int($this->data, 'traffic_gb', 0);
        $timeDays  = FaoximaInput::int($this->data, 'time_days', 0);

        $fxKinds = ['custom_volume', 'custom_time'];
        $resolved = null;
        $config = fx_duration_config($panel, $agent);
        if ($isCustomActive && ($trafficGb !== 0 || $timeDays !== 0)) {
            if ($trafficGb < (int)($main[$agent] ?? 0) || $trafficGb > (int)($max[$agent] ?? 0)
                || $timeDays < (int)($minT[$agent] ?? 0) || $timeDays > (int)($maxT[$agent] ?? 0)) {
                FaoximaResponse::badRequest('حجم یا زمان نامعتبر است.');
            }
            try {
                $resolved = fx_custom_resolve($panel, $agent, $config['enabled'] ? ($this->data['traffic_gb'] ?? 0) : $trafficGb, $config['enabled'] ? ($this->data['time_days'] ?? 0) : $timeDays);
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
            try {
                $panel = fx_custom_api_quote_panel($panel, $this->user, ['price_product' => $resolved['price'], 'code_product' => 'customvolume',
                    'Volume_constraint' => $trafficGb, 'Service_time' => $timeDays], 'buy', FaoximaInput::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                FaoximaResponse::badRequest($e->getMessage());
            }
            $price = $panel['_custom_quote_total'];
        } else {
            $price = $isCustomActive ? 0 : false;
        }

        FaoximaResponse::ok([
            'price'        => $price,
            'duration_pricing' => $config['enabled'],
            'duration_tariffs' => fx_duration_public_tariffs($panel, $agent),
            'price_per_gb' => $resolved['price_per_gb'] ?? null,
            'traffic_min'  => (int)($main[$agent] ?? 0),
            'traffic_max'  => (int)($max[$agent] ?? 0),
            'time_min'     => (int)($minT[$agent] ?? 0),
            'time_max'     => (int)($maxT[$agent] ?? 0),
            'fx_enabled'   => fx_context($panel, $fxKinds) !== null,
            'fx_quote'     => fx_quote_token($panel, $fxKinds),
        ]);
    }
}

