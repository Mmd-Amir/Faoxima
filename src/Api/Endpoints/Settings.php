<?php

namespace Faoxima\Api\Endpoints;

/**
 * Handler stage. Inherited by value: $APIKEY, $pdo.
 * Inherited or exported by reference: $setting.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Settings
{
    public static function handle(
        $APIKEY,
        $pdo,
        &$setting
    ) {
        header('Content-Type: application/json; charset=UTF-8');
        date_default_timezone_set('Asia/Tehran');
        ini_set('default_charset', 'UTF-8');
        ini_set('error_log', 'error_log');

        $request = \Faoxima\Api\Management\ManagementRequest::accept(
            $pdo, getallheaders(), $_SERVER['REQUEST_METHOD'], (string) ($APIKEY ?? ''), $_SERVER['REMOTE_ADDR'] ?? null,
            static fn () => file_get_contents('php://input'),
            [\Faoxima\Api\Runtime\EndpointSupport::class, "sanitizeRecursive"],
        );
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        \Faoxima\Api\Management\SettingsManagement::dispatch($request->method, $request->data);

        return 1;
    }
}
