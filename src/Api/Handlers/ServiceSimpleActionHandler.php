<?php

declare(strict_types=1);

namespace Faoxima\Api\Handlers;

use ManagePanel;
use Throwable;

final class ServiceSimpleActionHandler extends \Faoxima\Api\Handlers\BaseHandler
{
    public function handle(): void
    {
        $this->requireMethod('POST');

        $action = \Faoxima\Api\Runtime\Input::string($this->data, 'action');
        $username = \Faoxima\Api\Runtime\Input::string($this->data, 'username');

        if ($action === '' || $username === '') {
            \Faoxima\Api\Runtime\Response::badRequest('action and username are required');
        }

        $invoice = \Faoxima\Api\Runtime\Db::fetchOne(
            'SELECT * FROM invoice WHERE id_user = :u AND username = :n LIMIT 1',
            [':u' => $this->user['id'], ':n' => $username]
        );
        if ($invoice === null) {
            \Faoxima\Api\Runtime\Response::notFound('Service not found');
        }

        $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $invoice['Service_location'], 'select');
        if (empty($panel)) {
            \Faoxima\Api\Runtime\Response::notFound('Panel not found');
        }

        switch ($action) {
            case 'note':
                $this->handleNote($invoice);
                return;
            case 'toggle_status':
                $this->handleToggleStatus($invoice, $panel);
                return;
            case 'report_problem':
                $this->handleReportProblem($invoice, $panel);
                return;
            case 'update_info':
                \Faoxima\Api\Runtime\Response::ok([
                    'kind'    => 'done',
                    'message' => '✅ اطلاعات بروزرسانی شد',
                ]);
                return;
        }

        \Faoxima\Api\Runtime\Response::badRequest('Unknown simple action: ' . $action);
    }


    private function handleNote(array $invoice): void
    {
        $row = \Faoxima\Core\Database\Db::select('setting', '*', null, null, 'select');
        $statusNameCustom = is_array($row) ? (string)($row['statusnamecustom'] ?? '') : '';
        if ($statusNameCustom === 'offnamecustom') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $text = trim(\Faoxima\Api\Runtime\Input::string($this->data, 'text'));

        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200);
        }

        \Faoxima\Core\Database\Db::update('invoice', 'note', $text, 'id_invoice', $invoice['id_invoice']);

        \Faoxima\Api\Runtime\Response::ok([
            'kind'    => 'done',
            'message' => $text === '' ? '✅ یادداشت حذف شد' : '✅ یادداشت ذخیره شد',
            'note'    => $text,
        ]);
    }


    private function handleToggleStatus(array $invoice, array $panel): void
    {
        if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'changeservice')) {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }
        if (($invoice['Status'] ?? '') === 'disablebyadmin') {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $managePanel = new ManagePanel();


        $remote = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (!is_array($remote)) {
            \Faoxima\Api\Runtime\Response::fail(502, '❌ ارتباط با پنل برقرار نشد');
        }
        $remoteStatus = (string)($remote['status'] ?? '');
        if ($remoteStatus === 'active' && !$managePanel->HasLiveConnectionHistory($panel, $remote)) {
            \Faoxima\Api\Runtime\Response::fail(409, 'ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.');
        }
        if ($remoteStatus === 'on_hold') {
            \Faoxima\Api\Runtime\Response::fail(409, 'ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.');
        }
        if ($remoteStatus === 'Unsuccessful') {
            \Faoxima\Api\Runtime\Response::fail(502, '❌ خطایی در دریافت وضعیت سرویس از پنل رخ داده است');
        }

        $output = $managePanel->Change_status((string)$invoice['username'], (string)$invoice['Service_location']);
        if (!is_array($output) || (string)($output['status'] ?? '') === 'Unsuccessful') {
            if (($output['code'] ?? '') === 'not_connected') {
                \Faoxima\Api\Runtime\Response::fail(409, (string)$output['msg']);
            }
            \Faoxima\Api\Runtime\Response::fail(502, '❌ تغییر وضعیت سرویس انجام نشد. لطفاً دوباره تلاش کنید.');
        }


        $remoteAfter = $managePanel->DataUser($invoice['Service_location'], $invoice['username']);
        $newStatus = is_array($remoteAfter) ? (string)($remoteAfter['status'] ?? '') : '';

        if ($newStatus === 'active') {
            \Faoxima\Core\Database\Db::update('invoice', 'Status', 'active', 'id_invoice', $invoice['id_invoice']);
        } elseif ($newStatus === 'disabled') {
            \Faoxima\Core\Database\Db::update('invoice', 'Status', 'disabled', 'id_invoice', $invoice['id_invoice']);
        }

        $msg = $newStatus === 'active'
            ? '💡 سرویس روشن شد'
            : '❌ سرویس خاموش شد';

        \Faoxima\Api\Runtime\Response::ok([
            'kind'    => 'done',
            'message' => $msg,
            'status'  => $newStatus,
        ]);
    }


    private function handleReportProblem(array $invoice, array $panel): void
    {
        if (!\Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($panel, 'disorder')) {
            \Faoxima\Api\Runtime\Response::fail(409, '❌ این قابلیت درحال حاضر در دسترس نیست');
        }

        $userText = trim(\Faoxima\Api\Runtime\Input::string($this->data, 'text'));
        if (mb_strlen($userText) > 500) {
            $userText = mb_substr($userText, 0, 500);
        }

        $userId = (string)$this->user['id'];
        $userName = (string)($this->user['username'] ?? '');
        $channel = (string)($this->setting['Channel_Report'] ?? '');
        $topicRow = \Faoxima\Core\Database\Db::select('topicid', 'idreport', 'report', 'otherservice', 'select');
        $topic = is_array($topicRow) ? (string)($topicRow['idreport'] ?? '') : '';

        $report =
            "⚠️ گزارش اختلال سرویس (از مینی‌اپ)\n\n" .
            "<blockquote>👤 کاربر: <code>{$userId}</code>" . ($userName !== '' ? ' (@' . htmlspecialchars($userName, ENT_QUOTES) . ')' : '') . "</blockquote>\n" .
            "<blockquote>🌍 پنل: " . htmlspecialchars((string)$invoice['Service_location'], ENT_QUOTES) . "</blockquote>\n" .
            "<blockquote>🛍 محصول: " . htmlspecialchars((string)$invoice['name_product'], ENT_QUOTES) . "</blockquote>\n" .
            "<blockquote>👤 نام کاربری سرویس: <code>" . htmlspecialchars((string)$invoice['username'], ENT_QUOTES) . "</code></blockquote>\n" .
            ($userText !== '' ? "\n<blockquote>📝 توضیحات کاربر:\n" . htmlspecialchars($userText, ENT_QUOTES) . "</blockquote>\n" : '');

        if ($channel !== '') {
            try {
                telegram('sendmessage', [
                    'chat_id'           => $channel,
                    'message_thread_id' => $topic,
                    'text'              => $report,
                    'parse_mode'        => 'HTML',
                ]);
            } catch (Throwable $e) {
                \Faoxima\Api\Runtime\Logger::warn('Disorder report send failed', ['err' => $e->getMessage()]);
            }
        }

        \Faoxima\Api\Runtime\Response::ok([
            'kind'    => 'done',
            'message' => '✅ گزارش اختلال شما برای ادمین ارسال شد',
        ]);
    }
}

