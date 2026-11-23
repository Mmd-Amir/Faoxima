<?php

function fx_admin_ip_allowed(string|false|null $configured, string $current): bool
{
    if ($configured === false || $configured === null || $configured === '') {
        return true;
    }
    $unrestricted = ['*', 'all', 'unlimited'];
    $addresses = json_decode($configured, true);
    if (is_array($addresses)) {
        return in_array($current, $addresses, true) || in_array('*', $addresses, true)
            || in_array('all', $addresses, true) || in_array('unlimited', $addresses, true);
    }
    return in_array($configured, $unrestricted, true)
        || ($configured === $current && filter_var($configured, FILTER_VALIDATE_IP) !== false);
}
