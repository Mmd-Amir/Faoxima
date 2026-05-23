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

        if ($isCustomActive) {
            $price = ((float)($tp[$agent] ?? 0) * $trafficGb)
                   + ((float)($timeP[$agent] ?? 0) * $timeDays);
        } else {
            $price = false;
        }

        FaoximaResponse::ok([
            'price'        => $price,
            'traffic_min'  => (int)($main[$agent] ?? 0),
            'traffic_max'  => (int)($max[$agent] ?? 0),
            'time_min'     => (int)($minT[$agent] ?? 0),
            'time_max'     => (int)($maxT[$agent] ?? 0),
        ]);
    }
}

