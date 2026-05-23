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
        if (!is_string($agent) || $agent === '') return true;

        $fields = [
            'agent', 'agents', 'agent_list', 'agent_lists',
            'agent_access', 'agent_type', 'allowed_agents',
            'allowed_agent', 'user_type', 'user_types',
            'type_user', 'typeuser', 'group_user', 'group_users',
            'audience', 'audiences',
        ];

        foreach ($fields as $field) {
            if (!isset($product[$field]) || $product[$field] === null || $product[$field] === '') continue;

            $raw = $product[$field];
            $values = [];

            if (is_array($raw)) {
                $values = $raw;
            } else {
                $rawStr = trim((string)$raw);
                if ($rawStr === '') continue;

                $decoded = json_decode($rawStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (is_array($decoded)) $values = $decoded;
                    elseif (is_scalar($decoded)) $values = [(string)$decoded];
                }
                if (empty($values)) {
                    $values = preg_split('/[,|]/', $rawStr);
                }
            }

            $normalised = [];
            foreach ((array)$values as $v) {
                if (is_array($v)) continue;
                $token = strtolower(trim((string)$v));
                if ($token !== '') $normalised[] = $token;
            }

            if (!empty($normalised)) {
                $agent = strtolower(trim($agent));
                $wildcards = ['all', '*', 'any', 'everyone'];
                foreach ($normalised as $allowed) {
                    if (in_array($allowed, $wildcards, true)) return true;
                    if ($allowed === $agent) return true;
                }
                return false;
            }
        }

        return true;
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

