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

    abstract public function handle(): void;
}

