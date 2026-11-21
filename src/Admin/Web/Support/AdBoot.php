<?php

namespace Faoxima\Admin\Web\Support;

final class AdBoot
{
    public static function fx_ad_gate():string{
        return \Faoxima\Admin\Web\Support\AdRedirect::_ax_domain();
    }
}
