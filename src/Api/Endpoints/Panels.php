<?php

namespace Faoxima\Api\Endpoints;

/**
 * Handler stage. Inherited by value: $APIKEY, $pdo.
 * Inherited or exported by reference: $buyreport, $errorreport, $otherservice, $paymentreports, $porsantreport, $reportbackup, $reportcron, $reportnight, $reporttest, $setting.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Panels
{
    public static function handle(
        $APIKEY,
        &$buyreport,
        &$errorreport,
        &$otherservice,
        &$paymentreports,
        $pdo,
        &$porsantreport,
        &$reportbackup,
        &$reportcron,
        &$reportnight,
        &$reporttest,
        &$setting
    ) {
        header('Content-Type: application/json; charset=UTF-8');
        date_default_timezone_set('Asia/Tehran');
        $topic_id = \Faoxima\Core\Database\Db::select("topicid", "*", null, null, "fetchAll");
        foreach ($topic_id as $topic) {
            if ($topic['report'] == "reportnight")
                $reportnight = $topic['idreport'];
            if ($topic['report'] == 'reporttest')
                $reporttest = $topic['idreport'];
            if ($topic['report'] == 'errorreport')
                $errorreport = $topic['idreport'];
            if ($topic['report'] == 'porsantreport')
                $porsantreport = $topic['idreport'];
            if ($topic['report'] == 'reportcron')
                $reportcron = $topic['idreport'];
            if ($topic['report'] == 'backupfile')
                $reportbackup = $topic['idreport'];
            if ($topic['report'] == 'buyreport')
                $buyreport = $topic['idreport'];
            if ($topic['report'] == 'otherservice')
                $otherservice = $topic['idreport'];
            if ($topic['report'] == 'paymentreport')
                $paymentreports = $topic['idreport'];

        }
        ini_set('default_charset', 'UTF-8');
        ini_set('error_log', 'error_log');

        $request = \Faoxima\Api\Management\ManagementRequest::accept(
            $pdo, getallheaders(), $_SERVER['REQUEST_METHOD'], (string) ($APIKEY ?? ''), $_SERVER['REMOTE_ADDR'] ?? null,
            static fn () => file_get_contents('php://input'),
            [\Faoxima\Api\Runtime\EndpointSupport::class, "sanitizeRecursive"],
        );
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");
        \Faoxima\Api\Management\PanelManagement::dispatch($pdo, $request->method, $request->data);

        return 1;
    }
}
