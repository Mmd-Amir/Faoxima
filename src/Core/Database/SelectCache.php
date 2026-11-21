<?php

namespace Faoxima\Core\Database;

use Redis;

final class SelectCache
{
    public static function &getSelectCacheStore()
    {
        static $store = [
            'results' => [],
            'tableIndex' => [],
            'rowIndex' => [],
        ];
        return $store;
    }

    public static function rx_select_cache_hot_tables()
    {
        return [
            'user'               => true,
            'invoice'            => true,
            'Payment_report'     => true,
            'reagent_report'     => true,
            'cron_runtime_state' => true,
        ];
    }

    public static function rx_select_cache_redis_ttl()
    {
        return 300;
    }

    public static function clearSelectCache($table = null)
    {
        $store =& self::getSelectCacheStore();

        if ($table === null) {
            $store['results'] = [];
            $store['tableIndex'] = [];
            $store['rowIndex'] = [];
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
                self::rx_redis_clear_selectcache_table(null);
            }
            return;
        }

        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
            self::rx_redis_clear_selectcache_table($table);
        }

        if (!isset($store['tableIndex'][$table])) {
            return;
        }

        foreach (array_keys($store['tableIndex'][$table]) as $cacheKey) {
            unset($store['results'][$cacheKey]);
        }

        unset($store['tableIndex'][$table]);


        if (isset($store['rowIndex']) && is_array($store['rowIndex'])) {
            foreach (array_keys($store['rowIndex']) as $rowKey) {
                if (strpos($rowKey, $table . '|') === 0) {
                    unset($store['rowIndex'][$rowKey]);
                }
            }
        }
    }

    public static function rx_redis_clear_selectcache_table($table)
    {
        if ($table === null) {
            if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_scan_delete')) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_scan_delete('faoxima:selectcache:*');
            }
            return;
        }

        $indexKey = 'faoxima:selectcache:tableindex:' . $table;
        $client = \Faoxima\Core\Cache\RedisCache::getRedisConnection();
        if ($client === null) {
            return;
        }
        try {
            $members = $client->sMembers(\Faoxima\Core\Cache\RedisCache::rx_redis_key($indexKey));
            if (is_array($members) && !empty($members)) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_del($members);
            }
            \Faoxima\Core\Cache\RedisCache::rx_redis_del($indexKey);
        } catch (\Throwable $e) {
            \Faoxima\Core\Cache\RedisCache::rx_redis_mark_unavailable('exception');
        }
    }

    public static function rx_redis_clear_selectcache_row($table, $whereField, $whereValue)
    {
        $client = \Faoxima\Core\Cache\RedisCache::getRedisConnection();
        if ($client === null) {
            return;
        }
        $rowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
        $allRowsIdx = $table . '||';
        foreach ([$rowIdx, $allRowsIdx] as $idx) {
            $indexKey = 'faoxima:selectcache:rowindex:' . $idx;
            try {
                $members = $client->sMembers(\Faoxima\Core\Cache\RedisCache::rx_redis_key($indexKey));
                if (is_array($members) && !empty($members)) {
                    \Faoxima\Core\Cache\RedisCache::rx_redis_del($members);
                }
                \Faoxima\Core\Cache\RedisCache::rx_redis_del($indexKey);
            } catch (\Throwable $e) {
                \Faoxima\Core\Cache\RedisCache::rx_redis_mark_unavailable('exception');
            }
        }
    }

    public static function rx_select_cache_store_result(array &$store, $table, $cacheKey, $whereField, $whereValue, $result): string
    {
        $store['results'][$cacheKey] = $result;
        if (!isset($store['tableIndex'][$table])) {
            $store['tableIndex'][$table] = [];
        }
        $store['tableIndex'][$table][$cacheKey] = true;
        $rowIndex = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
        if (!isset($store['rowIndex'][$rowIndex])) {
            $store['rowIndex'][$rowIndex] = [];
        }
        $store['rowIndex'][$rowIndex][$cacheKey] = true;
        return $rowIndex;
    }

    public static function rx_select_failure_result($type)
    {
        switch ($type) {
            case 'count':
                return 0;
            case 'FETCH_COLUMN':
            case 'fetchAll':
                return [];
            default:
                return null;
        }
    }

    public static function clearSelectCacheRow($table, $whereField, $whereValue)
    {
        if (method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_is_active') && \Faoxima\Core\Cache\RedisCache::rx_redis_is_active()) {
            self::rx_redis_clear_selectcache_row($table, $whereField, $whereValue);
        }

        $store =& self::getSelectCacheStore();
        if (!isset($store['tableIndex'][$table])) {
            return;
        }


        $rowIdx = $table . '|' . (string) $whereField . '|' . (string) $whereValue;
        if (isset($store['rowIndex'][$rowIdx]) && is_array($store['rowIndex'][$rowIdx])) {
            foreach (array_keys($store['rowIndex'][$rowIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$rowIdx]);
        }


        $allRowsIdx = $table . '||';
        if (isset($store['rowIndex'][$allRowsIdx]) && is_array($store['rowIndex'][$allRowsIdx])) {
            foreach (array_keys($store['rowIndex'][$allRowsIdx]) as $cacheKey) {
                unset($store['results'][$cacheKey]);
                unset($store['tableIndex'][$table][$cacheKey]);
            }
            unset($store['rowIndex'][$allRowsIdx]);
        }
    }

    public static function faoxima_bust_bot_selectcache($table)
    {
        $host = (string) ($GLOBALS['redis_host'] ?? '');
        if ($host === '' || !class_exists('Redis')) return;
        try {
            $client = new Redis();
            if (!$client->connect($host, (int) ($GLOBALS['redis_port'] ?? 6379), 1.0)) return;
            $password = (string) ($GLOBALS['redis_password'] ?? '');
            if ($password !== '') $client->auth($password);
            $client->select((int) ($GLOBALS['redis_database'] ?? 0));
            $rxKey = method_exists(\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_key') ? [\Faoxima\Core\Cache\RedisCache::class, 'rx_redis_key'] : 'strval';
            $indexKey = $rxKey('faoxima:selectcache:tableindex:' . $table);
            $members = $client->sMembers($indexKey);
            if (is_array($members) && !empty($members)) {
                $client->del(array_map($rxKey, $members));
            }
            $client->del($indexKey);
            $client->close();
        } catch (\Throwable $e) {
        }
    }
}
