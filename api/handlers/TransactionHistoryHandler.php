<?php


declare(strict_types=1);

require_once __DIR__ . '/BaseHandler.php';

final class TransactionHistoryHandler extends BaseHandler
{

    private const CATEGORY_LABELS = [
        'topup_card'            => 'شارژ کارت به کارت',
        'topup_crypto'          => 'شارژ ارز دیجیتال',
        'topup_gateway'         => 'شارژ درگاه پرداخت',
        'admin_credit'          => 'افزایش موجودی توسط ادمین',
        'admin_debit'           => 'کاهش موجودی توسط ادمین',
        'purchase'              => 'خرید سرویس',
        'refund'                => 'بازگشت وجه',
        'affiliate_commission'  => 'پورسانت زیرمجموعه',
        'gift_code'             => 'کد هدیه',
        'lottery'               => 'جایزه قرعه‌کشی',
        'cashback'              => 'هدیه بازگشت وجه',
        'service_action'        => 'عملیات سرویس',
        'transfer_out'          => 'انتقال موجودی به کاربر دیگر',
        'transfer_in'           => 'انتقال موجودی از کاربر دیگر',
        'referral_gift'         => 'هدیه عضویت زیرمجموعه',
    ];

    public function handle(): void
    {
        $this->requireMethod('GET');

        $userId = (string)($this->user['id'] ?? '');
        if ($userId === '') {
            FaoximaResponse::ok(['items' => [], 'total' => 0, 'total_pages' => 0, 'page' => 1, 'limit' => 4]);
            return;
        }

        $limit = FaoximaInput::intRange($this->data, 'limit', 1, 50, 4);
        $page = FaoximaInput::intMin($this->data, 'page', 1, 1);
        $offset = ($page - 1) * $limit;

        $params = [':u' => $userId, ':cutoff' => time() - (30 * 24 * 60 * 60)];
        $where = 'id_user = :u AND created_at >= FROM_UNIXTIME(:cutoff)';

        try {
            $totalItems = (int) FaoximaDb::fetchScalar(
                "SELECT COUNT(*) FROM wallet_ledger WHERE {$where}",
                $params
            );
            $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0;

            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $rows = FaoximaDb::fetchAll(
                "SELECT id, direction, amount, balance_after, category, description, id_order, created_at
                   FROM wallet_ledger
                  WHERE {$where}
                  ORDER BY id DESC
                  LIMIT :limit OFFSET :offset",
                $params
            );
        } catch (Throwable $e) {
            FaoximaLogger::userFacing('TransactionHistory fetch failed', ['err' => $e->getMessage()]);
            FaoximaResponse::ok(['items' => [], 'total' => 0, 'total_pages' => 0, 'page' => $page, 'limit' => $limit]);
            return;
        }

        $items = [];
        foreach ((array)$rows as $r) {
            $category = (string)$r['category'];
            $items[] = [
                'id'            => (int)$r['id'],
                'direction'     => (string)$r['direction'],
                'amount'        => (int)$r['amount'],
                'balance_after' => $r['balance_after'] !== null ? (int)$r['balance_after'] : null,
                'category'      => $category,
                'category_label'=> self::CATEGORY_LABELS[$category] ?? $category,
                'description'   => $r['description'] !== null ? (string)$r['description'] : null,
                'order_id'      => $r['id_order'] !== null ? (string)$r['id_order'] : null,
                'created_at'    => (string)$r['created_at'],
            ];
        }

        FaoximaResponse::ok([
            'items'       => $items,
            'total'       => $totalItems,
            'total_pages' => $totalPages,
            'page'        => $page,
            'limit'       => $limit,
        ]);
    }
}
