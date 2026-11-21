<?php

namespace Faoxima\Core\Support;

final class Formatting
{
    public static function safe_divide($numerator, $denominator, $fallback = 0)
    {
        if (!is_numeric($numerator) || !is_numeric($denominator)) {
            return $fallback;
        }

        $denominator = (float) $denominator;
        if ($denominator == 0.0) {
            return $fallback;
        }

        $result = (float) $numerator / $denominator;

        if (!is_finite($result)) {
            return $fallback;
        }

        return $result;
    }

    public static function rxFormatToman($amount)
    {
        if ($amount === null) {
            return '0';
        }
        if (is_bool($amount)) {
            return $amount ? '1' : '0';
        }
        if (!is_scalar($amount)) {
            return '0';
        }
        if (is_string($amount)) {
            $amount = trim($amount);
            if ($amount === '' || !is_numeric($amount)) {
                return '0';
            }
        }
        return number_format((float) $amount, 0, '.', ',');
    }

    public static function generateUUID()
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $uuid;
    }

    public static function isValidDate($date)
    {
        return (strtotime($date) != false);
    }

    /**
 * Ask the gateway, server to server, whether this order was paid.
 *
 * `paid` is true only when the gateway says so AND names the same order AND
 * reports at least the billed amount. The amount comes back in Rial.
 */
    public static function formatBytes($bytes, $precision = 2): string
    {
        $base = log($bytes, 1024);
        $power = $bytes > 0 ? floor($base) : 0;
        $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
        return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
    }

    public static function rxInvoiceVolumeUnit(array $invoice): string
    {
        $unit = strtoupper(trim((string) ($invoice['Volume_unit'] ?? '')));
        if ($unit === 'MB' || $unit === 'GB') {
            return $unit;
        }
        return (($invoice['name_product'] ?? '') === 'سرویس تست') ? 'MB' : 'GB';
    }

    public static function rxVolumeToBytes($value, string $unit): float
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return $amount * ($unit === 'MB' ? pow(1024, 2) : pow(1024, 3));
    }

    public static function formatInvoiceVolume(array $invoice, $precision = 2): string
    {
        $value = is_numeric($invoice['Volume'] ?? null) ? (float) $invoice['Volume'] : 0.0;
        if ($value == 0.0) {
            return 'نامحدود';
        }
        return self::formatBytes(self::rxVolumeToBytes($value, self::rxInvoiceVolumeUnit($invoice)), $precision);
    }

    public static function sanitizeUserName($userName)
    {
        $forbiddenCharacters = [
            "'",
            "\"",
            "<",
            ">",
            "--",
            "#",
            ";",
            "\\",
            "%",
            "(",
            ")"
        ];

        foreach ($forbiddenCharacters as $char) {
            $userName = str_replace($char, "", $userName);
        }
        return $userName;
    }

    public static function rx_copy_text_fits($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return false;
        }
        if (function_exists('mb_strlen')) {
            $units = mb_strlen($value, 'UTF-8');
        } elseif (function_exists('iconv_strlen')) {
            $units = @iconv_strlen($value, 'UTF-8');
            if ($units === false) {
                $units = strlen($value);
            }
        } else {
            $units = strlen($value);
        }
        $astral = @preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value);
        if ($astral > 0) {
            $units += $astral;
        }
        return $units <= 256;
    }

    public static function sanitize_recursive(array $data): array
    {
        $sanitized_data = [];
        foreach ($data as $key => $value) {
            $sanitized_key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            if (is_array($value)) {
                $sanitized_data[$sanitized_key] = self::sanitize_recursive($value);
            } elseif (is_string($value)) {
                $sanitized_data[$sanitized_key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } elseif (is_int($value)) {
                $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            } elseif (is_float($value)) {
                $sanitized_data[$sanitized_key] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            } elseif (is_bool($value) || is_null($value)) {
                $sanitized_data[$sanitized_key] = $value;
            } else {
                $sanitized_data[$sanitized_key] = $value;
            }
        }
        return $sanitized_data;
    }

    public static function isBase64($string)
    {
        if (base64_encode(base64_decode($string, true)) === $string) {
            return true;
        }
        return false;
    }
}
