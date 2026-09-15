<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class CategoriesHandler extends BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('GET');

        $codePanel = $this->resolveCountryId();
        if ($codePanel === '') {
            FaoximaResponse::badRequest('country_id is required');
        }
        $panel = $this->loadPanelByCode($codePanel);
        if (!panel_feature_enabled($panel, 'categorygeneral')) {
            FaoximaResponse::ok([]);
        }

        $allCategories = FaoximaDb::fetchAll('SELECT * FROM category');

        $userAgent = $this->user['agent'] ?? 'f';
        $list = [];
        foreach ($allCategories as $cat) {
            $count = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM product
                  WHERE (FIND_IN_SET(:location, Location) > 0 OR Location = '/all')
                    AND FIND_IN_SET(:category, category) > 0
                    AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))",
                [
                    ':location' => $panel['name_panel'],
                    ':category' => $cat['remark'],
                    ':agent'    => $userAgent,
                ]
            );
            if ($count === 0) continue;

            $list[] = [
                'id' => $cat['id'],
                'name' => $cat['remark'],
            ];
        }

        FaoximaResponse::ok($list);
    }
}

