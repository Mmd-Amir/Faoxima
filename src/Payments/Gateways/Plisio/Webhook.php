<?php

namespace Faoxima\Payments\Gateways\Plisio;



final class Webhook
{
    public static function stringifyScalars(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::stringifyScalars($v);
            } elseif (is_bool($v)) {
                $data[$k] = $v ? '1' : '';
            } elseif ($v === null) {
                $data[$k] = '';
            } elseif (is_int($v) || is_float($v)) {
                $data[$k] = (string) $v;
            }
        }
        return $data;
    }

    public static function verifySignature(array $data, string $apiKey): bool
    {
        if ($apiKey === '') return false;
        if (empty($data['verify_hash']) || !is_string($data['verify_hash'])) return false;

        $received = (string) $data['verify_hash'];
        unset($data['verify_hash']);
        ksort($data);


        $data = self::stringifyScalars($data);

        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = json_encode($v);
            }
        }

        $postString = serialize($data);
        $expectedMd5  = hash_hmac('md5',  $postString, $apiKey);
        $expectedSha1 = hash_hmac('sha1', $postString, $apiKey);

        if (hash_equals($expectedMd5, $received))  return true;
        if (hash_equals($expectedSha1, $received)) return true;

        $altQuery     = http_build_query($data);
        $expectedQMd5 = hash_hmac('md5',  $altQuery, $apiKey);
        if (hash_equals($expectedQMd5, $received)) return true;

        return false;
    }
}
