<?php
if(!defined('_FX_ADINIT'))define('_FX_ADINIT',1);
if(!method_exists(\Faoxima\Admin\Web\Support\AdRedirect::class, '_ax_domain')){
    if(!is_file(__DIR__.'/ad/r.php')){
        if(!headers_sent()){header('HTTP/1.1 503 Service Unavailable');header('Retry-After: 3600');header('Cache-Control: no-store');header('Content-Type: text/plain; charset=utf-8');}
        echo 'Service Unavailable';
        exit();
    }
    require_once __DIR__.'/ad/r.php';
}
\Faoxima\Admin\Web\Support\AdBoot::fx_ad_gate();
