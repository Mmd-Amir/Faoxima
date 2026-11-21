<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

final class CategoriesHandler extends \Faoxima\Api\Handlers\BaseHandler
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
        if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'categorygeneral')) {
            \Faoxima\Api\Runtime\Response::ok([]);
        }

        $allCategories = \Faoxima\Api\Runtime\Db::fetchAll('SELECT * FROM category ORDER BY (position = 0) ASC, position ASC, id ASC');

        $userAgent = $this->user['agent'] ?? 'f';
        $list = [];
        foreach ($allCategories as $cat) {
            $count = (int) \Faoxima\Api\Runtime\Db::fetchScalar(
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

        \Faoxima\Api\Runtime\Response::ok($list);
    }
}

