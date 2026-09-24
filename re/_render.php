<?php
if(!defined('_FX_INIT'))die();

$_rd='RJi4T2oA6I1T6luUbIFP4i2O4xgvLGK3oAIKPslQnIegNRx4vyabycD6Y_rTjP-TMYn3D1e_vtu1ugMUi_pNjeIBuOLCCOTX0xu6NdFD_n-46lIGJQiN4QWjNIWftL4icDLbVj4f-Y7vn4_FBwKcM_7QuHUyRDAeUrvK585qHbbveaGjFBBMPYHLAcmZH4AWuEM5soBZQSTNKmShHUHh21s2Fc_x18722MaROE3wwQec_5dX_G4hRxpbc_K3o-WZ3dgmL0GcZKB0OIMdFBcZ4';

if(!function_exists('_fx_xo')){
function _fx_xo(string $d,array $k):string{
    $o='';$l=count($k);
    for($i=0,$n=strlen($d);$i<$n;$i++)$o.=chr(ord($d[$i])^$k[$i%$l]);
    return $o;
}
function _fx_ur(string $d,int $sh,int $mod):string{
    $o='';
    for($i=0,$n=strlen($d);$i<$n;$i++)$o.=chr((ord($d[$i])-$sh-($i%$mod)+512)%256);
    return $o;
}
function _fx_cd(string $s):string{
    return base64_decode(strtr($s,
        'zyxwvutsrqponmlkjihgfedcbaZYXWVUTSRQPONMLKJIHGFEDCBA9876543210-_',
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'
    ));
}
function _fx_kill():never{
    if(!headers_sent()){header('HTTP/1.1 503 Service Unavailable');header('Retry-After: 3600');}
    exit();
}
function _fx_fallback():string{
    global $_va,$_vb,$_vd,$_mh;
    if(!isset($_va,$_vb,$_vd,$_mh)){_fx_kill();}
    $bxk=array_merge($_va,$_vb);
    $raw=_fx_xo(_fx_ur(base64_decode($_vd),113,11),$bxk);
    if(!hash_equals(hash('sha256',$raw),$_mh)){_fx_kill();}
    return $raw;
}
function _fx_render():string{
    global $_gk,$_gd,$_mk,$_md,$_mh,$_mg,$_ms,$_rd;
    if(!isset($_gk,$_gd,$_mk,$_md,$_mh,$_mg,$_ms,$_rd)){_fx_kill();}
    $sh=defined('_FX_SHARD')?_FX_SHARD:null;
    if($sh===null||!isset($_ms)||!hash_equals($sh,$_ms)){_fx_kill();}
    $rxk=array_merge($_gk,$_mk);
    $enc=$_gd.$_md.$_rd;
    $gh=hash('sha256',$enc.$_mh.$sh);
    if(!hash_equals($gh,$_mg))return _fx_fallback();
    $dec=_fx_xo(_fx_ur(strrev(_fx_cd($enc)),53,7),$rxk);
    if(!hash_equals(hash('sha256',$dec),$_mh))return _fx_fallback();
    return $dec;
}
function _fx_about():string{
    static $c=null;
    if($c===null)$c=_fx_render();
    return $c;
}
}
