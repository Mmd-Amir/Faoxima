<?php


declare(strict_types=1);

require_once __DIR__ . '/../lib/Bootstrap.php';

abstract class BaseHandler
{

    protected $user;


    protected $data;


    protected $method;


    protected $setting;


    private static $paySettingCache = [];


    private static $adminLookupCache = [];


    private static $diagRequestId = null;

    public function __construct(array $user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->setting = select('setting', '*');
    }


    protected function paySetting(string $name, string $default = ''): string
    {
        if (array_key_exists($name, self::$paySettingCache)) {
            return self::$paySettingCache[$name];
        }
        $row = select('PaySetting', 'ValuePay', 'NamePay', $name, 'select');
        $value = is_array($row) ? (string)($row['ValuePay'] ?? $default) : $default;
        self::$paySettingCache[$name] = $value;
        return $value;
    }


    protected function diag(string $channel, string $step, array $ctx = []): void
    {
        try {
            $base = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($base)) {
                @mkdir($base, 0775, true);
            }

            $entry = [
                'ts'      => date('Y-m-d H:i:s'),
                'req'     => $this->diagRequestId(),
                'channel' => $channel,
                'step'    => $step,
                'user_id' => $this->user['id'] ?? null,
                'agent'   => $this->user['agent'] ?? null,
                'method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'ctx'     => $ctx,
            ];

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                $line = json_encode([
                    'ts'      => date('Y-m-d H:i:s'),
                    'channel' => $channel,
                    'step'    => $step,
                    'ctx'     => '<unencodable diagnostic context>',
                ]);
            }

            $file = $base . '/' . $channel . '-' . date('Y-m-d') . '.log';
            @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
        }
    }

    private function diagRequestId(): string
    {
        if (self::$diagRequestId === null) {
            try {
                self::$diagRequestId = bin2hex(random_bytes(4));
            } catch (Throwable $e) {
                self::$diagRequestId = substr(md5(uniqid('', true)), 0, 8);
            }
        }
        return self::$diagRequestId;
    }


    protected function userIsAdmin(): bool
    {
        $uid = (string)($this->user['id'] ?? '');
        if ($uid === '') return false;
        if (array_key_exists($uid, self::$adminLookupCache)) {
            return self::$adminLookupCache[$uid];
        }
        $cnt = (int) select('admin', '*', 'id_admin', $uid, 'count');
        self::$adminLookupCache[$uid] = $cnt > 0;
        return self::$adminLookupCache[$uid];
    }


    protected function requireMethod(string $expected): void
    {
        if (strcasecmp($this->method, $expected) !== 0) {
            FaoximaResponse::methodNotAllowed($expected);
        }
    }


    protected function loadPanelByCode(string $codePanel): array
    {
        $panel = select('marzban_panel', '*', 'code_panel', $codePanel, 'select');
        if (empty($panel)) {
            FaoximaLogger::userFacing('Panel not found', [
                'user_id' => $this->user['id'] ?? null,
                'code_panel' => $codePanel,
            ]);
            FaoximaResponse::fail(404, 'panel not found (invalid id_panel)');
        }
        return $panel;
    }


    protected function decodeJsonField($raw): array
    {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }


    protected function productIsAllowedForAgent(array $product, $agent): bool
    {
        return function_exists('rx_product_allows_agent')
            && rx_product_allows_agent($product, $agent);
    }


    protected function resolveCountryId(): string
    {
        $value = FaoximaInput::string($this->data, 'country_id');
        if ($value === '') {
            $value = FaoximaInput::string($this->data, 'id_panel');
        }
        return $value;
    }

    protected function serverPurchaseDueAmount(string $purchaseUsername): int
    {
        $price = FaoximaDb::fetchScalar(
            "SELECT price_product FROM invoice
              WHERE username = :u AND id_user = :uid AND Status = 'unpaid'
              ORDER BY time_sell DESC LIMIT 1",
            [':u' => $purchaseUsername, ':uid' => $this->user['id']]
        );
        if ($price === null || $price === false || !is_numeric($price)) {
            FaoximaResponse::fail(404, faoxima_textbot_get('dyn_paymentinit_unpaid_invoice_not_found', '❌ فاکتور خرید ناتمامی برای این نام کاربری پیدا نشد.'));
        }
        $due = (int) ceil((float) $price - (float) ($this->user['Balance'] ?? 0));
        if ($due <= 1) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_restart_purchase', '❌ مبلغ این خرید تغییر کرده است. لطفاً خرید را دوباره انجام دهید.'));
        }
        return $due;
    }

    protected function serverPendingActionAmount(?string $expectedAction = null, ?string $expectedOne = null): int
    {
        $tow = (string) ($this->user['Processing_value_tow'] ?? '');
        $one = (string) ($this->user['Processing_value_one'] ?? '');
        $allowedTow = ['getextenduser', 'getextravolumeuser', 'getextratimeuser'];
        $stateValid = in_array($tow, $allowedTow, true) && $one !== '' && strpos($one, '%') !== false;
        if ($stateValid && $expectedAction !== null && !hash_equals($tow, $expectedAction)) {
            $stateValid = false;
        }
        if ($stateValid && $expectedOne !== null && !hash_equals($one, $expectedOne)) {
            $stateValid = false;
        }
        $stored = $this->user['Processing_value'] ?? null;
        $due = is_numeric($stored) ? (int) ceil((float) $stored) : 0;
        if (!$stateValid || $due <= 0) {
            FaoximaResponse::fail(409, faoxima_textbot_get('dyn_paymentinit_renew_steps_incomplete', '❌ مراحل تمدید کامل نشده است. لطفاً تمدید را از ابتدا انجام دهید.'));
        }
        return $due;
    }

    abstract public function handle(): void;
}

