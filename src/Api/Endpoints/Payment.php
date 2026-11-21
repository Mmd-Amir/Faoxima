<?php

namespace Faoxima\Api\Endpoints;

use ManagePanel;

/**
 * Handler stage. Inherited by value: $APIKEY, $pdo.
 * Inherited or exported by reference: $ManagePanel, $errorreport, $otherservice, $paymentreports, $porsantreport, $reportbackup, $reportcron, $reportnight, $reporttest, $setting.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Payment
{
    public static function handle(
        $APIKEY,
        &$ManagePanel,
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
        header('Content-Type: application/json');
        date_default_timezone_set('Asia/Tehran');
        ini_set('default_charset', 'UTF-8');
        ini_set('error_log', 'error_log');
        $ManagePanel = new ManagePanel();

        $otherservice = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
        $paymentreports = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
        $reportnight = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "reportnight", "select")['idreport'];
        $reporttest = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "reporttest", "select")['idreport'];
        $errorreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "errorreport", "select")['idreport'];
        $porsantreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "porsantreport", "select")['idreport'];
        $reportcron = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
        $reportbackup = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
        $setting = \Faoxima\Core\Database\Db::select("setting", "*");

        $request = \Faoxima\Api\Management\ManagementRequest::accept(
            $pdo, getallheaders(), $_SERVER['REQUEST_METHOD'], (string) ($APIKEY ?? ''), $_SERVER['REMOTE_ADDR'] ?? null,
            static fn () => file_get_contents('php://input'),
            [\Faoxima\Core\Support\Formatting::class, "sanitize_recursive"], strictAudit: true,
        );
        \Faoxima\Api\Management\PaymentManagement::dispatch($pdo, $request->method, $request->data);

        return 1;
    }
}
