<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';
require_once __DIR__ . '/DiscountSupport.php';

final class DiscountEligibleHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $ctxIn = FaoximaInput::string($this->data, 'context');
        $section = in_array($ctxIn, ['buy', 'extend', 'volume', 'time', 'charge'], true) ? $ctxIn : 'all';

        $username = FaoximaInput::nullableString($this->data, 'username');
        $codeProduct = FaoximaInput::string($this->data, 'product_code');
        $codePanel   = FaoximaInput::string($this->data, 'code_panel');
        $codeCategory = FaoximaInput::string($this->data, 'category');
        $namePanel = '';

        if ($codePanel !== '') {
            $panelByCode = select('marzban_panel', '*', 'code_panel', $codePanel, 'select');
            if (is_array($panelByCode)) {
                $namePanel = (string)($panelByCode['name_panel'] ?? '');
            }
        }

        if (($codePanel === '' || $codeCategory === '') && $username !== null && $username !== '') {
            $invoice = FaoximaDb::fetchOne(
                'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
                [':u' => $this->user['id'], ':n' => $username]
            );
            if (is_array($invoice)) {
                if ($codePanel === '') {
                    $panel = select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
                    if (is_array($panel)) {
                        $codePanel = (string)($panel['code_panel'] ?? '');
                        $namePanel = (string)($invoice['Service_location'] ?? '');
                    }
                }
                if ($codeCategory === '') {
                    $sourceProduct = FaoximaDb::fetchOne(
                        "SELECT category FROM product WHERE name_product = :n AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1",
                        [':n' => (string)($invoice['name_product'] ?? ''), ':loc' => (string)($invoice['Service_location'] ?? ''), ':agent' => (string)($this->user['agent'] ?? 'f')]
                    );
                    if (is_array($sourceProduct)) {
                        $codeCategory = (string)($sourceProduct['category'] ?? '');
                    }
                }
            }
        }

        if ($codeCategory === '' && $codeProduct !== '') {
            $productRow = FaoximaDb::fetchOne(
                "SELECT category FROM product WHERE code_product = :cp AND (FIND_IN_SET(:loc, Location) > 0 OR Location = '/all') AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers')) LIMIT 1",
                [':cp' => $codeProduct, ':loc' => $namePanel, ':agent' => (string)($this->user['agent'] ?? 'f')]
            );
            if (is_array($productRow)) {
                $codeCategory = (string)($productRow['category'] ?? '');
            }
        }

        $codeCategories = array_values(array_filter(array_map('trim', explode(',', $codeCategory)), function ($v) {
            return $v !== '';
        }));
        $eligible = MiniDiscount::hasEligibleForCategories($section, $codeProduct, $codePanel, $codeCategories, $this->user);

        FaoximaResponse::ok(['eligible' => $eligible]);
    }
}
