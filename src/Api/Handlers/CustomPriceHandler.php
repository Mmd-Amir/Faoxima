<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use InvalidArgumentException;

final class CustomPriceHandler extends \Faoxima\Api\Handlers\BaseHandler
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
        if (\Faoxima\Services\Panels\PanelLimits::panel_creation_limit_reached($panel)) {
            \Faoxima\Api\Runtime\Response::fail(409, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_panel_limit_reached', 'ظرفیت ساخت کانفیگ در این پنل تکمیل شده است.'));
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

        $trafficGb = \Faoxima\Api\Runtime\Input::int($this->data, 'traffic_gb', 0);
        $timeDays  = \Faoxima\Api\Runtime\Input::int($this->data, 'time_days', 0);

        $fxKinds = ['custom_volume', 'custom_time'];
        $resolved = null;
        $config = \Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, $agent);
        if ($isCustomActive && ($trafficGb !== 0 || $timeDays !== 0)) {
            if ($trafficGb < (int)($main[$agent] ?? 0) || $trafficGb > (int)($max[$agent] ?? 0)
                || $timeDays < (int)($minT[$agent] ?? 0) || $timeDays > (int)($maxT[$agent] ?? 0)) {
                \Faoxima\Api\Runtime\Response::badRequest('حجم یا زمان نامعتبر است.');
            }
            try {
                $resolved = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_resolve($panel, $agent, $config['enabled'] ? ($this->data['traffic_gb'] ?? 0) : $trafficGb, $config['enabled'] ? ($this->data['time_days'] ?? 0) : $timeDays);
            } catch (InvalidArgumentException $e) {
                \Faoxima\Api\Runtime\Response::badRequest($e->getMessage());
            }
            try {
                $panel = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_api_quote_panel($panel, $this->user, ['price_product' => $resolved['price'], 'code_product' => 'customvolume',
                    'Volume_constraint' => $trafficGb, 'Service_time' => $timeDays], 'buy', \Faoxima\Api\Runtime\Input::string($this->data, 'discount_code'));
            } catch (InvalidArgumentException $e) {
                \Faoxima\Api\Runtime\Response::badRequest($e->getMessage());
            }
            $price = $panel['_custom_quote_total'];
        } else {
            $price = $isCustomActive ? 0 : false;
        }

        \Faoxima\Api\Runtime\Response::ok([
            'price'        => $price,
            'duration_pricing' => $config['enabled'],
            'duration_tariffs' => \Faoxima\Orders\Pricing\CustomDuration::fx_duration_public_tariffs($panel, $agent),
            'price_per_gb' => $resolved['price_per_gb'] ?? null,
            'traffic_min'  => (int)($main[$agent] ?? 0),
            'traffic_max'  => (int)($max[$agent] ?? 0),
            'time_min'     => (int)($minT[$agent] ?? 0),
            'time_max'     => (int)($maxT[$agent] ?? 0),
            'fx_enabled'   => \Faoxima\Orders\Pricing\ExchangePricing::fxContext($panel, $fxKinds) !== null,
            'fx_quote'     => \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteToken($panel, $fxKinds),
        ]);
    }
}

