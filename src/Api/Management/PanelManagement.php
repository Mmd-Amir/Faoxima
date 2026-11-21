<?php

namespace Faoxima\Api\Management;

use Exception;
use Faoxima\Api\Runtime\EndpointSupport;
use PDO;

final class PanelManagement
{
    public static function dispatch(PDO $pdo, string $method, array $data): void
    {
        switch ($data['actions'] ?? '') {
            case 'panels':
                self::panels($pdo, $method, $data);
                return;
            case 'panel':
                self::panel($method, $data);
                return;
            case 'panel_add':
                self::panelAdd($pdo, $method, $data);
                return;
            case 'panel_edit':
                self::panelEdit($pdo, $method, $data);
                return;
            case 'panel_delete':
                self::panelDelete($pdo, $method, $data);
                return;
            case 'set_inbounds':
                self::setInbounds($pdo, $method, $data);
                return;
            default:
                EndpointSupport::sendJsonResponse(false, "Action Invalid");
                return;
        }
    }

    private static function panels(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        $limit = 50;
        if (isset($data['limit']) && is_numeric($data['limit']))
            $limit = min(max((int) $data['limit'], 1), 1000);


        $page = isset($data['page']) && is_numeric($data['page']) ? max((int) $data['page'], 1) : 1;
        $offset = ($page - 1) * $limit;
        $q = isset($data['q']) ? $data['q'] : '';

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM marzban_panel WHERE (name_panel LIKE :name_panel )");
            $search = "%$q%";
            $stmt->bindParam(':name_panel', $search, PDO::PARAM_STR);
            $stmt->execute();
            $totalpanel = (int) $stmt->fetchColumn();
            $totalPages = ceil($totalpanel / $limit);
            $query = "SELECT * FROM marzban_panel WHERE name_panel  LIKE CONCAT('%', :name_panel, '%') ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':name_panel', $q, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $panels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'panels' => $panels,
                'pagination' => [
                    'total_panel' => $totalpanel,
                    'total_pages' => $totalPages,
                    'current_page' => $page,
                    'per_page' => $limit
                ]
            ]);

        } catch (Exception $e) {
            error_log("Database error in panel: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function panel(string $method, array $data): void
    {
        EndpointSupport::validateMethod('GET', $method);


        if (!isset($data['id']) || empty($data['id'])) {
            EndpointSupport::sendJsonResponse(false, "id empty", []);
        }

        try {
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "id", $data['id'], "select");
            if (!$panel) {
                EndpointSupport::sendJsonResponse(false, "panel not found", [
                    'panel' => [],
                ]);
            }
            $panel['hide_panel'] = json_decode($panel['hide_panel'],true);
            EndpointSupport::sendJsonResponse(true, "Successful", [
                'panel' => $panel,
                'category' => $category,
            ]);
        } catch (Exception $e) {
            error_log("Database error in panel: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "Database error occurred", [], 500);
        }
    }

    private static function panelAdd(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['name', 'price', 'data_limit', 'time', 'location'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $panel = \Faoxima\Core\Database\Db::select("panel", "*", "name_panel", $data['name'], "count");
        if ($panel != 0) {
            EndpointSupport::sendJsonResponse(false, "panel name exits", [], 200);
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $data['location'], "select");
        if (!$panel & $data['location'] != "/all")
            EndpointSupport::sendJsonResponse(false, "location not found", [], 200);
        try {
            $randomString = bin2hex(random_bytes(3));

            $panelData = [
                'code_panel' => $randomString,
                'name_panel' => $data['name'],
                'price_panel' => $data['price'],
                'Volume_constraint' => $data['data_limit'],
                'Service_time' => $data['time'],
                'Location' => $panel['name_panel'],
                'agent' => empty($data['agent']) ? "f" : $data['agent'],
                'note' => empty($data['note']) ? "" : $data['note'],
                'data_limit_reset' => empty($data['data_limit_reset']) ? "no_reset" : $data['data_limit_reset'],
                'inbounds' => empty($data['note']) ? null : $data['inbounds'],
                'proxies' => empty($data['proxies']) ? null : $data['proxies'],
                'category' => empty($data['category']) ? null : $data['category'],
                'one_buy_status' => empty($data['one_buy_status']) ? 0 : $data['one_buy_status'],
                'hide_panel' => empty($data['hide_panel']) ? "{}" : $data['hide_panel'],
            ];


            $columns = implode(',', array_keys($panelData));
            $placeholders = ':' . implode(', :', array_keys($panelData));

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO panel ({$columns}) VALUES ({$placeholders})"
            );

            foreach ($panelData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            EndpointSupport::sendJsonResponse(true, "Successful");

        } catch (Exception $e) {
            error_log("Error in panel_add: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while editing panel");
        }
    }

    private static function panelEdit(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "id", $data['id'], "select");
        if (!$panel) {
            EndpointSupport::sendJsonResponse(false, "panel not found", [], 200);
        }
        if (isset($data['name']) && $panel['name_panel'] != $data['name']) {


            $panel_check = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $data['name'], "count");
            if ($panel_check != 0)
                EndpointSupport::sendJsonResponse(false, "panel name exits", [], 200);
            \Faoxima\Core\Database\Db::update("invoice", "Service_location", $data['name'], "Service_location", $panel['name_panel']);
        }

        try {
            $panelData = [
                'name_panel' => isset($data['name']) ? $data['name'] : $panel['name_panel'],
                'sublink' => isset($data['sublink']) ? $data['sublink'] : $panel['sublink'],
                'config' => isset($data['config']) ? $data['config'] : $panel['config'],
                'status' => isset($data['status']) ? $data['status'] : $panel['status'],
                'Location' => isset($data['location']) ? $data['location'] : $panel['Location'],
                'agent' => isset($data['agent']) ? $data['agent'] : $panel['agent'],
                'note' => isset($data['note']) ? $data['note'] : $panel['note'],
                'data_limit_reset' => isset($data['data_limit_reset']) ? $data['data_limit_reset'] : $panel['data_limit_reset'],
                'inbounds' => isset($data['inbounds']) ? $data['inbounds'] : $panel['inbounds'],
                'proxies' => isset($data['proxies']) ? $data['proxies'] : $panel['proxies'],
                'category' => isset($data['category']) ? $data['category'] : $panel['category'],
                'one_buy_status' => isset($data['one_buy_status']) ? $data['one_buy_status'] : $panel['one_buy_status'],
                'hide_panel' => isset($data['hide_panel']) ? json_encode($data['hide_panel']) : $panel['hide_panel'],
            ];
            $setParts = [];
            foreach ($panelData as $key => $value) {
                $setParts[] = "{$key} = :{$key}";
            }
            $setClause = implode(", ", $setParts);


            $stmt = $pdo->prepare("UPDATE marzban_panel SET {$setClause} WHERE id = :id");

            foreach ($panelData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);

            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "panel updated successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in panel_edit: " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while updating panel");
        }
    }

    private static function panelDelete(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $panel = \Faoxima\Core\Database\Db::select("panel", "*", "id", $data['id'], "select");
        if (!$panel) {
            EndpointSupport::sendJsonResponse(false, "panel not found", [], 200);
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM panel  WHERE id = :id");
            $stmt->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $stmt->execute();

            EndpointSupport::sendJsonResponse(true, "panel delete successfully", [], 200);

        } catch (Exception $e) {
            error_log("Error in panel delete : " . $e->getMessage());
            EndpointSupport::sendJsonResponse(false, "An error occurred while delete panel");
        }
    }

    private static function setInbounds(PDO $pdo, string $method, array $data): void
    {
        EndpointSupport::validateMethod('POST', $method);
        $required_fields = ['id', 'input'];
        $missing_fields = array_diff($required_fields, array_keys($data));
        if (!empty($missing_fields)) {
            EndpointSupport::sendJsonResponse(false, "Missing required fields: " . implode(', ', $missing_fields), []);
        }
        $panel = \Faoxima\Core\Database\Db::select("panel", "*", "id", $data['id'], "select");
        if (!$panel) {
            EndpointSupport::sendJsonResponse(false, "panel not found", [], 200);
        }
        $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", 'name_panel', $panel['Location'], "select");
        if ($panel['type'] == "marzban") {
            $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($data['input'], $panel['name_panel']);
            if (!empty($DataUserOut['error']))
                EndpointSupport::sendJsonResponse(false, $DataUserOut['error'], [], 200);
            if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200)
                EndpointSupport::sendJsonResponse(false, $DataUserOut['msg'], [], 200);
            $DataUserOut = json_decode($DataUserOut['body'], true);
            if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                EndpointSupport::sendJsonResponse(false, "User Not Found", [], 200);
            }
            foreach ($DataUserOut['proxies'] as $key => &$value) {
                if ($key == "shadowsocks") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } elseif ($key == "trojan") {
                    unset($DataUserOut['proxies'][$key]['password']);
                } else {
                    unset($DataUserOut['proxies'][$key]['id']);
                }
                if (count($DataUserOut['proxies'][$key]) == 0) {
                    $DataUserOut['proxies'][$key] = new stdClass();
                }
            }
            $stmt = $pdo->prepare("UPDATE panel SET proxies = :proxies WHERE id = :id_panel");
            $proxy_output = json_encode($DataUserOut['proxies']);
            $stmt->bindParam(':proxies', $proxy_output);
            $stmt->bindParam(':id_panel', $data['id']);
            $stmt->execute();
            $datainbound = json_encode($DataUserOut['inbounds']);
        } elseif ($panel['type'] == "x-ui_single") {
            $user_data = \Faoxima\Services\Panels\Drivers\XuiSingle::get_clinets($data['input'], $panel['name_panel']);
            if (!empty($user_data['error']))
                EndpointSupport::sendJsonResponse(false, $user_data['error'], [], 200);
            if (!empty($user_data['status']) && $user_data['status'] != 200)
                EndpointSupport::sendJsonResponse(false, $user_data['msg'], [], 200);
            $user_data = json_decode($user_data['body'], true)['obj'];
            if ($user_data == null)
                EndpointSupport::sendJsonResponse(false, "User Not Found", [], 200);
            $datainbound = $user_data['inboundId'];
        } else {
            EndpointSupport::sendJsonResponse(false, "panel_not_support_options", [], 200);
        }
        $stmt = $pdo->prepare("UPDATE panel SET inbounds = :inbounds WHERE id = :id_panel ");
        $stmt->bindParam(':inbounds', $datainbound);
        $stmt->bindParam(':id_panel', $data['id']);
        $stmt->execute();
        EndpointSupport::sendJsonResponse(true, "successfully", [], 200);
    }
}
