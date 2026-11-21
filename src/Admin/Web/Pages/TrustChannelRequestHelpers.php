<?php

namespace Faoxima\Admin\Web\Pages;

final class TrustChannelRequestHelpers
{
    public static function faoxima_trust_toggle_on($cur, $on, $off) {
    if ($cur === null) return false;
    if ((string)$cur === (string)$on)  return true;
    if ((string)$cur === (string)$off) return false;
    return in_array(strtolower(trim((string)$cur)), ['1','on','true','yes'], true);
}
}
