<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

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
        if (panel_creation_limit_reached($panel)) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_purchase_panel_limit_reached', 'ظرفیت ساخت کانفیگ در این پنل تکمیل شده است.'));
        }

        $agent = $this->user['agent'] ?? 'f';

        $custom = $this->decodeJsonField($panel['customvolume'] ?? null);
        $main   = $this->decodeJsonField($panel['mainvolume'] ?? null);
        $max    = $this->decodeJsonField($panel['maxvolume'] ?? null);
        $minT   = $this->decodeJsonField($panel['maintime'] ?? null);
        $maxT   = $this->decodeJsonField($panel['maxtime'] ?? null);
        $tp     = $this->decodeJsonField($panel['pricecustomvolume'] ?? null);
        $timeP  = $this->decodeJsonField($panel['pricecustomtime'] ?? null);

        $isCustomActive = (int)($custom[$agent] ?? 0) === 1
            && ($panel['type'] ?? '') !== 'Manualsale';

        $trafficGb = FaoximaInput::int($this->data, 'traffic_gb', 0);
        $timeDays  = FaoximaInput::int($this->data, 'time_days', 0);

        $fxKinds = ['custom_volume', 'custom_time'];
        if ($isCustomActive) {
            $price = ((float) fx_adjust_base_toman($tp[$agent] ?? 0, $panel, 'custom_volume') * $trafficGb)
                   + ((float) fx_adjust_base_toman($timeP[$agent] ?? 0, $panel, 'custom_time') * $timeDays);
            $price = fx_finalize_amount($price, $panel, $fxKinds);
        } else {
            $price = false;
        }

        FaoximaResponse::ok([
            'price'        => $price,
            'traffic_min'  => (int)($main[$agent] ?? 0),
            'traffic_max'  => (int)($max[$agent] ?? 0),
            'time_min'     => (int)($minT[$agent] ?? 0),
            'time_max'     => (int)($maxT[$agent] ?? 0),
            'fx_enabled'   => fx_context($panel, $fxKinds) !== null,
            'fx_quote'     => fx_quote_token($panel, $fxKinds),
        ]);
    }
}

