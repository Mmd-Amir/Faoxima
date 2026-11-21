<?php

namespace Faoxima\Telegram\Bootstrap;

/**
 * Handler stage. Inherited by value: $first_name, $from_id, $last_name, $pdo, $receiptreport, $reportbackup, $reportcron, $reportnight, $reporttest, $setting, $textbotlang, $user, $username, $version.
 * Inherited or exported by reference: $buyreport, $datatextbot, $errorreport, $otherservice, $paymentreports, $porsantreport.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Localization
{
    public static function handle(
        &$buyreport,
        &$datatextbot,
        &$errorreport,
        $first_name,
        $from_id,
        $last_name,
        &$otherservice,
        &$paymentreports,
        $pdo,
        &$porsantreport,
        $receiptreport,
        $reportbackup,
        $reportcron,
        $reportnight,
        $reporttest,
        $setting,
        $textbotlang,
        $user,
        $username,
        $version
    ) {
        $datatextbotget = isset($GLOBALS['_rx_textbot_rows']) && is_array($GLOBALS['_rx_textbot_rows'])
            ? $GLOBALS['_rx_textbot_rows']
            : \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
        $topicRows = \Faoxima\Core\Database\Db::select('topicid', '*', null, null, 'fetchAll');
        $reportTopics = \Faoxima\Telegram\Bootstrap\LocalizationHelpers::reportTopicIds($topicRows);
        $reportnight = $reportTopics['reportnight'] ?? ($reportnight ?? null);
        $reporttest = $reportTopics['reporttest'] ?? ($reporttest ?? null);
        $errorreport = $reportTopics['errorreport'] ?? ($errorreport ?? null);
        $porsantreport = $reportTopics['porsantreport'] ?? ($porsantreport ?? null);
        $reportcron = $reportTopics['reportcron'] ?? ($reportcron ?? null);
        $reportbackup = $reportTopics['backupfile'] ?? ($reportbackup ?? null);
        $buyreport = $reportTopics['buyreport'] ?? ($buyreport ?? null);
        $otherservice = $reportTopics['otherservice'] ?? ($otherservice ?? null);
        $paymentreports = $reportTopics['paymentreport'] ?? ($paymentreports ?? null);
        $receiptreport = $reportTopics['receiptreport'] ?? ($receiptreport ?? null);
        $statusnote = \Faoxima\Telegram\Bootstrap\LocalizationHelpers::purchaseNotesEnabled($setting, $user);
        
        \Faoxima\Telegram\Bootstrap\LocalizationHelpers::createForumTopicIfMissing($porsantreport, 'porsantreport', $textbotlang['Admin']['affiliates']['titletopic'], $setting['Channel_Report']);
        \Faoxima\Telegram\Bootstrap\LocalizationHelpers::createForumTopicIfMissing($reportnight, 'reportnight', $textbotlang['Admin']['report']['reportnight'], $setting['Channel_Report']);
        \Faoxima\Telegram\Bootstrap\LocalizationHelpers::createForumTopicIfMissing($reportcron, 'reportcron', $textbotlang['Admin']['report']['reportcron'], $setting['Channel_Report']);
        \Faoxima\Telegram\Bootstrap\LocalizationHelpers::createForumTopicIfMissing($reportbackup, 'backupfile', "🤖 بکاپ ربات نماینده", $setting['Channel_Report']);
        \Faoxima\Telegram\Bootstrap\LocalizationHelpers::createForumTopicIfMissing($receiptreport, 'receiptreport', $textbotlang['Admin']['report']['receiptreport'], $setting['Channel_Report']);
        
        $datatextbot = \Faoxima\Telegram\Bootstrap\BotLocalization::textCatalog($datatextbotget);
        $startTemplate = \Faoxima\Telegram\Bootstrap\BotLocalization::renderStart(
            $pdo, $datatextbot, $user, $from_id, $username, $first_name, $last_name, $version,
        );
        if ($startTemplate !== null) {
            $GLOBALS['rx_rich_diag_templates']['text_start'] = $startTemplate;
        }
        unset($datatextbotget, $topicRows, $reportTopics, $startTemplate);
        return true;
    }
}
