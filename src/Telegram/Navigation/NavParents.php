<?php

namespace Faoxima\Telegram\Navigation;

final class NavParents
{
    public static function rxNavParent($step)
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'usershub'                  => 'home',
                'panels'                    => 'home',
                'finance'                   => 'home',
                'shop'                      => 'home',
                'settings'                  => 'home',
                'support'                   => 'home',
                'help'                      => 'home',
                'channelhub'                => 'home',
                'products'                  => 'shop',
                'categories'                => 'shop',
                'product_editor'            => 'products',
                'channel'                   => 'channelhub',
                'PanelMenu'                 => 'panels',
                'help_edit'                 => 'help',
                'featcat_main'              => 'settings',
                'featcat_bot'               => 'featcat_main',
                'featcat_users'             => 'featcat_main',
                'featcat_shop'              => 'featcat_main',
                'featcat_lottery'           => 'featcat_main',
                'featcat_crons'             => 'featcat_main',
                'featcat_antispam'          => 'featcat_main',
                'featcat_redis'             => 'featcat_main',
                'premium_emoji'             => 'featcat_main',
                'cronjobs'                  => 'featcat_crons',
                'admin_nav_cron_jobs'       => 'featcat_crons',
                'admin_nav_cron_settings'   => 'settings',
                'admin_nav_finance'         => 'home',
                'admin_nav_cart_settings'   => 'finance',
                'gw_cart'                   => 'finance',
                'gw_cart_auto'              => 'gw_cart',
                'gw_trnado'                 => 'finance',
                'gw_tonpay'                 => 'finance',
                'gw_rialit'                 => 'finance',
                'gw_tetraminator'           => 'finance',
                'tmset_apikey'              => 'gw_tetraminator',
                'tmset_name'                => 'gw_tetraminator',
                'tmset_cashback'            => 'gw_tetraminator',
                'tmset_min'                 => 'gw_tetraminator',
                'tmset_max'                 => 'gw_tetraminator',
                'tmset_edu'                 => 'gw_tetraminator',
                'gw_cubepay'                => 'finance',
                'gw_blupal'                 => 'finance',
                'gw_variza'                 => 'finance',
                'gw_abangateway'            => 'finance',
                'gw_atlaspay'               => 'finance',
                'gw_zarinpal'               => 'finance',
                'gw_plisio'                 => 'finance',
                'gw_iranpay'                => 'finance',
                'gw_tron'                   => 'finance',
                'gw_star'                   => 'finance',
                'gw_nowpayment'             => 'finance',
                'featnav_affiliates'        => 'featcat_lottery',
                'affiliates_antifraud_menu' => 'featnav_affiliates',
                'featnav_lottery'           => 'featcat_lottery',
                'featnav_wheel'             => 'featcat_lottery',
                'featnav_changeloc'         => 'featcat_shop',
                'featnav_randomwallet'      => 'featcat_shop',

                'add_Balance_all'           => 'usershub',
                'get_number_limit'          => 'usershub',
                'limit_usertest_allusers'   => 'usershub',
                'addbalanceuser'            => 'usershub',
                'addbalancemanual'          => 'usershub',
                'addbalanceusercurrent'     => 'usershub',
                'add_dec'                   => 'help',
                'adddecriptionblock'        => 'usershub',
                'getuserhide'               => 'usershub',
                'getuserhideforremove'      => 'usershub',
                'sendmessagetext'           => 'usershub',
                'sendmessagetid'            => 'usershub',
                'getmessageAsAdmin'         => 'usershub',
                'getmessageforward'         => 'usershub',
                'getbtnresponseforward'     => 'usershub',
                'GetusernameconfigAndOrdedrs' => 'usershub',
                'addadmin'                  => 'usershub',
                'getrule'                   => 'usershub',
                'webpanel_mgr_get_uname'    => 'settings',
                'webpanel_mgr_get_ip'       => 'settings',
                'getmeesagestatus'          => 'usershub',
                'antispam_get_count'        => 'featcat_antispam',
                'antispam_get_mute'         => 'featcat_antispam',
                'antispam_get_seconds'      => 'featcat_antispam',
                'getcountcreate'            => 'usershub',
                'getvolumesconfig'          => 'usershub',
                'getInbounddisable'         => 'usershub',
                'getusernameconfigcr'       => 'usershub',
                'removeprotocol'            => 'usershub',
                'GetPricecustomvo'          => 'PanelMenu',
                'GetPricetimeextra'         => 'PanelMenu',
                'gettime_expire_agent'      => 'usershub',
                'getlistidcart'             => 'usershub',
                'getpanelgift'              => 'usershub',
                'getvaluegift'              => 'usershub',
                'gettextday'                => 'usershub',
                'gettextSystemMessage'      => 'usershub',
                'get_time_start'            => 'home',
                'get_time_end'              => 'home',

                'getagentbalancemax'        => 'finance',
                'getagentbalancemin'        => 'finance',
                'maxbalance'                => 'finance',
                'minbalance'                => 'finance',
                'CartDirect'                => 'finance',
                'cryptowallet_set'          => 'finance',
                'cryptowallet_new_network'  => 'finance',
                'cryptowallet_new_address'  => 'finance',
                'cryptowallet_new_memo'     => 'finance',
                'nmrefamount'               => 'finance',
                'apiiranpay'                => 'gw_iranpay',
                'minbalanceiranpay'         => 'gw_iranpay',
                'maxbalanceiranpay'         => 'gw_iranpay',
                'gettextiranpay1'           => 'gw_iranpay',
                'getmainiranpay1'           => 'gw_iranpay',
                'getmaaxiranpay1'           => 'gw_iranpay',
                'getnameconfigm'            => 'PanelMenu',
                'apiternado'                => 'gw_trnado',
                'ipnsigningkeytronado'      => 'gw_trnado',
                'wageFromBusinessPercentageTronado' => 'gw_trnado',
                'getmainiranpay2'           => 'gw_trnado',
                'getmaaxiranpay2'           => 'gw_trnado',
                'getcashiranpay2'           => 'gw_trnado',
                'helpiranpay2'              => 'gw_trnado',
                'gethelpiranpay2'           => 'gw_trnado',
                'gettextiranpay3'           => 'gw_trnado',
                'getmainiranpay3'           => 'gw_trnado',
                'getmaaxiranpay3'           => 'gw_trnado',
                'gethelpiranpay3'           => 'gw_trnado',
                'getcashiranpay3'           => 'gw_trnado',
                'walletaddresssiranpay'     => 'gw_trnado',
                'apitonpay'                 => 'gw_tonpay',
                'apitonpaycustom'           => 'gw_tonpay',
                'getcashtonpay'             => 'gw_tonpay',
                'getmaintonpay'             => 'gw_tonpay',
                'getmaxtonpay'              => 'gw_tonpay',
                'helptonpay'                => 'gw_tonpay',
                'gettexttonpay'             => 'gw_tonpay',
                'apirialit'                 => 'gw_rialit',
                'getcashrialit'             => 'gw_rialit',
                'getmainrialit'             => 'gw_rialit',
                'getmaxrialit'              => 'gw_rialit',
                'helprialit'                => 'gw_rialit',
                'gettextrialit'             => 'gw_rialit',
                'rialit_trustips'           => 'gw_rialit',
                'rialit_trustproxies'       => 'gw_rialit',
                'apicubepay'                => 'gw_cubepay',
                'getcashcubepay'            => 'gw_cubepay',
                'getfeecubepay'             => 'gw_cubepay',
                'getmaincubepay'            => 'gw_cubepay',
                'getmaxcubepay'             => 'gw_cubepay',
                'helpcubepay'               => 'gw_cubepay',
                'gettextcubepay'            => 'gw_cubepay',
                'apiblupal'                 => 'gw_blupal',
                'getcashblupal'             => 'gw_blupal',
                'getmainblupal'             => 'gw_blupal',
                'getmaxblupal'              => 'gw_blupal',
                'helpblupal'                => 'gw_blupal',
                'gettextblupal'             => 'gw_blupal',
                'apivariza'                 => 'gw_variza',
                'variza_webhook_secret'     => 'gw_variza',
                'getcashvariza'             => 'gw_variza',
                'getmainvariza'             => 'gw_variza',
                'getmaxvariza'              => 'gw_variza',
                'helpvariza'                => 'gw_variza',
                'gettextvariza'             => 'gw_variza',
                'urlabangateway'            => 'gw_abangateway',
                'apiabangateway'            => 'gw_abangateway',
                'getcashabangateway'        => 'gw_abangateway',
                'getmainabangateway'        => 'gw_abangateway',
                'getmaxabangateway'         => 'gw_abangateway',
                'helpabangateway'           => 'gw_abangateway',
                'gettextabangateway'        => 'gw_abangateway',
                'apiatlaspay'               => 'gw_atlaspay',
                'getcashatlaspay'           => 'gw_atlaspay',
                'getmainatlaspay'           => 'gw_atlaspay',
                'getmaxatlaspay'            => 'gw_atlaspay',
                'helpatlaspay'              => 'gw_atlaspay',
                'gettextatlaspay'           => 'gw_atlaspay',
                'getmainaqzarinpal'         => 'gw_zarinpal',
                'getmaaxzarinpal'           => 'gw_zarinpal',
                'getcashzarinpal'           => 'gw_zarinpal',
                'helpzarinpal'              => 'gw_zarinpal',
                'gettextzarinpal'           => 'gw_zarinpal',
                'merchant_zarinpal'         => 'gw_zarinpal',
                'getmainplisio'             => 'gw_plisio',
                'getmaxplisio'              => 'gw_plisio',
                'getcashplisio'             => 'gw_plisio',
                'gethelpplisio'             => 'gw_plisio',
                'api_plisio'                => 'gw_plisio',
                'gettextnowpayment'         => 'gw_plisio',
                'getmainaqnowpayment'       => 'gw_nowpayment',
                'maxbalancenowpayment'      => 'gw_nowpayment',
                'getcashnowpayment'         => 'gw_nowpayment',
                'gethelpnowpayment'         => 'gw_nowpayment',
                'getnamenowpayment'         => 'gw_nowpayment',
                'nowpayment_ipn_secret'     => 'gw_nowpayment',
                'marchent_tronseller'       => 'gw_nowpayment',
                'apinowpayment'             => 'gw_nowpayment',
                'getmainaqstar'             => 'gw_star',
                'maxbalancestar'            => 'gw_star',
                'chashbackstar'             => 'gw_star',
                'gethelpstar'               => 'gw_star',
                'gettextstartelegram'       => 'gw_star',
                'getmaindigitaltron'        => 'gw_tron',
                'getmaxdigitaltron'         => 'gw_tron',
                'getmaindigitaltron2'       => 'gw_tron',
                'getmaxdigitaltron2'        => 'gw_tron',
                'helpofflinearze'           => 'gw_tron',
                'gettextnowpaymentTRON'     => 'gw_tron',
                'getmaincart'               => 'gw_cart',
                'getmaxcart'                => 'gw_cart',
                'get_cvmin_cart'            => 'gw_cart',
                'gethelpcart'               => 'gw_cart',
                'gethelpperfect'            => 'gw_cart',
                'getnamecarttocart'         => 'gw_cart',
                'getnamecarttopaynotverify' => 'gw_cart',
                'getcashcart'               => 'gw_cart',
                'changecard'                => 'gw_cart',
                'getnamecard'               => 'gw_cart',
                'getcardremove'             => 'gw_cart',
                'showcardallusers'          => 'gw_cart',
                'gettimeauto'               => 'gw_cart',
                'card_add_cardnumber'       => 'gw_cart',
                'card_add_cardname'         => 'gw_cart',
                'card_edit_number'          => 'gw_cart',
                'card_edit_name'            => 'gw_cart',
                'card_wl_add_users'         => 'gw_cart',
                'card_wl_remove_uid'        => 'gw_cart',
                'getidExceptio'             => 'gw_cart_auto',
                'getidExceptioremove'       => 'gw_cart_auto',
                'getidTrustAdd'             => 'gw_cart_auto',
                'getidTrustRemove'          => 'gw_cart_auto',

                'getdiscont'                => 'featnav_affiliates',
                'getfirstdiscount'          => 'shop',
                'getlimitcode'              => 'shop',
                'getlocdiscount'            => 'shop',
                'getproductdiscount'        => 'shop',
                'gettimediscount'           => 'shop',
                'gettypeagentoflist'        => 'shop',
                'gettypecodeagent'          => 'shop',
                'getuseuser'                => 'shop',
                'getmaxbuyagent'            => 'shop',
                'getpercentuser'            => 'shop',
                'setpercentage'             => 'featnav_affiliates',
                'get_price_codesell'        => 'shop',
                'get_price_Negative'        => 'shop',
                'Negative_Balance'          => 'shop',
                'getagent'                  => 'shop',
                'getpricecashback'          => 'shop',
                'stependforaddorder'        => 'shop',
                'getnameproduct'            => 'PanelMenu',
                'setbanner'                 => 'featnav_affiliates',
                'setbannerimage'            => 'featnav_affiliates',
                'setbannertext'             => 'featnav_affiliates',
                'setminaccountage'          => 'affiliates_antifraud_menu',
                'setdailycap'               => 'affiliates_antifraud_menu',
                'setmonthlycap'             => 'affiliates_antifraud_menu',
                'show_info'                 => 'usershub',
                'reject-dec'                => 'shop',
                'selectlocedite'            => 'PanelMenu',
                'GetPriceExtra'             => 'PanelMenu',
                'GetPriceExtratime'         => 'PanelMenu',
                'GetPriceexstratime'        => 'PanelMenu',
                'GetPricecustomtime'        => 'PanelMenu',
                'GetPricecustomvolume'      => 'PanelMenu',
                'minbalancebulk'            => 'shop',
                'gettypeextra'              => 'PanelMenu',
                'gettypeextracustom'        => 'PanelMenu',
                'gettypeextratime'          => 'PanelMenu',
                'gettypeextratimecustom'    => 'PanelMenu',
                'GetmaineExtra'             => 'PanelMenu',
                'gettypeextramain'          => 'PanelMenu',
                'GetmaxeExtra'              => 'PanelMenu',
                'gettypeextramax'           => 'PanelMenu',
                'Getmaintime'               => 'PanelMenu',
                'gettypeextramaintime'      => 'PanelMenu',
                'Getmaxtime'                => 'PanelMenu',
                'gettypeextramaxtime'       => 'PanelMenu',

                'get_limit'                 => 'products',
                'get_agent'                 => 'products',
                'get_location'              => 'products',
                'getcategory'               => 'products',
                'get_time'                  => 'products',
                'get_price'                 => 'products',
                'gettimereset'              => 'products',
                'getnote'                   => 'products',
                'endstep'                   => 'products',
                'selectloc'                 => 'products',
                'remove-product'            => 'products',
                'getaddpricepeoductloc'     => 'products',
                'getlowpricepeoductloc'     => 'products',
                'getaddpricepeoduct'        => 'products',
                'getagentaddpriceproduct'   => 'products',
                'getkampricepeoduct'        => 'products',
                'getkampricepeoductloc'     => 'products',
                'change_price'              => 'product_editor',
                'change_note'               => 'product_editor',
                'change_categroy'           => 'product_editor',
                'change_name'               => 'product_editor',
                'change_type_agent'         => 'product_editor',
                'change_reset_data'         => 'product_editor',
                'change_loc_data'           => 'product_editor',
                'getlistpanel'              => 'product_editor',
                'change_val'                => 'product_editor',
                'change_time'               => 'product_editor',
                'getdatainboundproduct'     => 'product_editor',
                'getremarkcategory'         => 'categories',
                'removecategory'            => 'categories',
                'editcategory_name'         => 'categories',
                'get_name_new_category'     => 'categories',

                'GetLocationEdit'           => 'panels',
                'add_name_panel'            => 'panels',
                'add_link_panel'            => 'panels',
                'add_username_panel'        => 'panels',
                'add_password_panel'        => 'panels',
                'add_remna_token_setup'     => 'panels',
                'getlimitedpanel'           => 'panels',
                'add_guard_api_key'         => 'panels',
                'add_guard_version'         => 'panels',
                'add_rebecca_api_key'       => 'panels',
                'add_pasarguard_api_key'    => 'panels',
                'add_pasarguard_auth_method' => 'panels',
                'add_pasarguard_username'   => 'panels',
                'add_pasarguard_password'   => 'panels',
                'add_xui_api_mode'          => 'panels',
                'add_xui_api_token'         => 'panels',
                'guard_svc_edit'            => 'PanelMenu',
                'guard_edit_api_key'        => 'PanelMenu',
                'rebecca_edit_api_key'      => 'PanelMenu',
                'pasarguard_edit_api_key'   => 'PanelMenu',
                'pasarguard_edit_username'  => 'PanelMenu',
                'pasarguard_edit_password'  => 'PanelMenu',
                'edit_xui_api_mode'         => 'PanelMenu',
                'edit_xui_api_token'        => 'PanelMenu',
                'confirmremovepanel'        => 'PanelMenu',
                'add_link_panel_edit'       => 'panels',
                'getlocoption'              => 'panels',
                'gettimeaccount'            => 'panels',
                'getpricef'                 => 'panels',
                'getpricnn'                 => 'panels',
                'getpricnn2'                => 'panels',
                'getpriceftime'             => 'panels',
                'getpricnntime'             => 'panels',
                'getpricnn2time'            => 'panels',
                'remna_panel_back'          => 'PanelMenu',
                'updatetime'                => 'PanelMenu',
                'val_usertest'              => 'PanelMenu',
                'tset_limit'                => 'PanelMenu',
                'getlimitnew'               => 'PanelMenu',
                'panellimit_getnew'         => 'PanelMenu',
                'GetusernameNew'            => 'PanelMenu',
                'GeturlNew'                 => 'PanelMenu',
                'protocolset'               => 'PanelMenu',
                'updatemethodusername'      => 'PanelMenu',
                'GetNameNew'                => 'PanelMenu',
                'getprotocol'               => 'PanelMenu',
                'getprotocolremove'         => 'PanelMenu',
                'GetpaawordNew'             => 'PanelMenu',
                'updateextendmethod'        => 'PanelMenu',
                'setpricechangelocation'    => 'PanelMenu',
                'getnameedit'               => 'PanelMenu',
                'getcontentedit'            => 'PanelMenu',
                'GeturlNewx'                => 'PanelMenu',
                'getuuidadmin'              => 'PanelMenu',
                'getagentpanel'             => 'PanelMenu',
                'getprotocolx_ui'           => 'PanelMenu',
                'getinboundiid'             => 'PanelMenu',
                'getnameremove'             => 'PanelMenu',
                'getusage_coefficient'      => 'PanelMenu',
                'setinboundandprotocol'     => 'PanelMenu',
                'getnamenode'               => 'PanelMenu',
                'getipnodeset'              => 'PanelMenu',
                'getnamecustom'             => 'PanelMenu',
                'getprotocoldisable'        => 'PanelMenu',
                'getconfigtext'             => 'PanelMenu',
                'switchtype_pick'           => 'PanelMenu',
                'switchtype_confirm'        => 'PanelMenu',
                'switchtype_link_panel'     => 'PanelMenu',
                'switchtype_username_panel' => 'PanelMenu',
                'switchtype_password_panel' => 'PanelMenu',
                'switchtype_guard_version'  => 'PanelMenu',
                'switchtype_guard_api_key'  => 'PanelMenu',
                'switchtype_remna_token'    => 'PanelMenu',
                'switchtype_rebecca_api_key' => 'PanelMenu',
                'switchtype_pasarguard_api_key' => 'PanelMenu',
                'switchtype_pasarguard_auth_method' => 'PanelMenu',
                'switchtype_pasarguard_username' => 'PanelMenu',
                'switchtype_pasarguard_password' => 'PanelMenu',
                'switchtype_xui_api_mode'   => 'PanelMenu',
                'switchtype_xui_token'      => 'PanelMenu',

                'getnamepanelconfig'        => 'settings',
                'getusernameconfig'         => 'settings',
                'getimagebackgroundqr'      => 'settings',
                'getiplogin'                => 'settings',
                'addchannelid'              => 'channelhub',
                'idsupportset'              => 'support',
                'getidadmindep'             => 'support',
                'getremovedep'              => 'support',
                'getdeparteman'             => 'support',
                'ticketadminreplyWait'      => 'support',
                'cronjob_set_value'         => 'cronjobs',
                'text_channel'              => 'settings',
                'edit_miniapp_suggest_text' => 'settings',
                'accountwallet'             => 'settings',

                'getnameforedite'           => 'help',
                'changenamehelp'            => 'help_edit',
                'changecategoryhelp'        => 'help_edit',
                'changedeshelp'             => 'help_edit',
                'changemedia'               => 'help_edit',
                'changeapptitlehelp'        => 'help_edit',
                'changeapplinkhelp'         => 'help_edit',
                'add_name_help'             => 'help',
                'add_app_title_help'        => 'help',
                'add_app_link_help'         => 'help',
                'getcatgoryhelp'            => 'help',
                'remove_help'               => 'help',
                'getservceid'               => 'help',

                'addchannel'                => 'channel',
                'removechannel'             => 'channel',
                'getremark'                 => 'channel',
                'getlinkjoin'               => 'channel',
                'channel_manage'            => 'channel',
                'ch_edit_remark'            => 'channel_manage',
                'ch_edit_linkjoin'          => 'channel_manage',
                'ch_edit_link'              => 'channel_manage',

                'premium_emoji_get_char'    => 'premium_emoji',
                'premium_emoji_get_id'      => 'premium_emoji',
                'premium_emoji_edit_id'     => 'premium_emoji',

                'getdaywarn'                => 'featcat_crons',
                'on_hold_day'               => 'featcat_crons',
                'getvolumewarn'             => 'featcat_crons',
                'getdaycron'                => 'featcat_crons',
                'getcronvolumere'           => 'featcat_crons',
                'get_panel_timeout'         => 'featcat_crons',
                'getpricewheel'             => 'featcat_lottery',
                'getonelotary'              => 'featcat_lottery',
                'getonelotary2'             => 'featcat_lottery',
                'getonelotary3'             => 'featcat_lottery',
                'limitchangeall'            => 'featnav_changeloc',
                'limitfreechangefree'       => 'featnav_changeloc',
                'get_randomwallet_slot'     => 'featcat_shop',

                'cm_manual_irr_input'               => 'finance',
                'cryptowallet_set_memo'             => 'finance',
                'reject_crypto_manual_reason'       => 'finance',
                'descriptionsrequsts'               => 'finance',
                'getpricereqagent'                  => 'finance',
                'rxar_vol'                          => 'finance',
                'rxar_days'                         => 'finance',
                'getpricebackremove'                => 'finance',
                'mafurefamount'                     => 'finance',
                'getpricevolumesrc'                 => 'usershub',
                'getpricetimesrc'                   => 'usershub',
                'gettokenbot'                       => 'usershub',
                'getadminidbot'                     => 'usershub',
                'getidfortransfers'                 => 'usershub',
                'getlimitchangenewbyuser'           => 'usershub',
                'getpanelhidebotsaz'                => 'usershub',
                'getremovehidepanel'                => 'usershub',
                'gettextgift'                       => 'usershub',

                'getrenewcashtarget'                => 'shop',
                'getrenewcashtargetdays'            => 'shop',
            ];
        }
        $step = (string) $step;
        if (isset($map[$step])) {
            return $map[$step];
        }
        if (strpos($step, 'get_remna_') === 0) {
            return 'PanelMenu';
        }
        if (strpos($step, 'cronjob_get_hour-') === 0) {
            return 'cronjobs';
        }
        return null;
    }

    public static function rxIsRenderableState($state)
    {
        static $set = null;
        if ($set === null) {
            $set = array_flip([
                'home', 'usershub', 'shop', 'products', 'product_editor', 'categories',
                'panels', 'PanelMenu', 'finance', 'settings', 'cronjobs',
                'help', 'help_edit', 'channel', 'channel_manage', 'channelhub', 'support', 'premium_emoji',
                'featcat_main', 'featcat_bot', 'featcat_users', 'featcat_crons', 'featcat_shop',
                'featcat_lottery', 'featcat_antispam', 'featcat_redis',
                'featnav_affiliates', 'affiliates_antifraud_menu', 'featnav_changeloc',
                'gw_cart', 'gw_cart_auto', 'gw_trnado', 'gw_tonpay', 'gw_rialit', 'gw_tetraminator', 'gw_cubepay', 'gw_blupal',
                'gw_variza', 'gw_abangateway',
                'gw_atlaspay',
                'gw_zarinpal', 'gw_plisio', 'gw_iranpay', 'gw_tron', 'gw_star', 'gw_nowpayment',
            ]);
        }
        return isset($set[(string) $state]);
    }

    public static function rxNavIsMenuStep($step)
    {
        static $extra = null;
        if ($extra === null) {
            $extra = array_flip([
                'admin_nav_finance', 'admin_nav_cart_settings', 'admin_nav_cron_jobs',
                'admin_nav_cron_settings', 'featnav_lottery', 'featnav_wheel', 'featnav_randomwallet',
            ]);
        }
        $step = (string) $step;
        if ($step === '' || $step === 'home') {
            return false;
        }
        return self::rxIsRenderableState($step) || isset($extra[$step]);
    }

    public static function rxNavMenuSignatures()
    {
        static $sig = null;
        if ($sig === null) {
            $sig = [
                'callbacks' => [
                    'admin_status'           => 'home',
                    'seller_status'          => 'home',
                    'support_users'          => 'home',
                    'admin_managepanel'      => 'panels',
                    'set_channel'            => 'channel',
                    'ch_list'                => 'channel',
                    'admin_users'            => 'usershub',
                    'set_features'           => 'settings',
                    'shop_category'          => 'shop',
                    'cat_add'                => 'categories',
                    'shopitem_add'           => 'products',
                    'help_add'               => 'help',
                    'ch_add'                 => 'channel',
                    'cartsetting'            => 'finance',
                    'cart_title'             => 'gw_cart',
                    'trnado_name'            => 'gw_trnado',
                    'tonpay_name'            => 'gw_tonpay',
                    'rialit_name'            => 'gw_rialit',
                    'cubepay_name'           => 'gw_cubepay',
                    'blupal_name'            => 'gw_blupal',
                    'atlaspay_name'          => 'gw_atlaspay',
                    'tonpay_apikey'          => 'gw_tonpay',
                    'tonpay_paymode'         => 'gw_tonpay',
                    'tonpay_customkey'       => 'gw_tonpay',
                    'rialit_apikey'          => 'gw_rialit',
                    'rialit_cashback'        => 'gw_rialit',
                    'rialit_min'             => 'gw_rialit',
                    'rialit_max'             => 'gw_rialit',
                    'rialit_edu'             => 'gw_rialit',
                    'rialit_whurl'           => 'gw_rialit',
                    'rialit_whregister'      => 'gw_rialit',
                    'rialit_whread'          => 'gw_rialit',
                    'rialit_whstatus'        => 'gw_rialit',
                    'rialit_trustips'        => 'gw_rialit',
                    'rialit_trustproxies'    => 'gw_rialit',
                    'cubepay_apikey'         => 'gw_cubepay',
                    'blupal_apikey'          => 'gw_blupal',
                    'variza_name'            => 'gw_variza',
                    'variza_apikey'          => 'gw_variza',
                    'variza_webhooksecret'   => 'gw_variza',
                    'abangateway_name'       => 'gw_abangateway',
                    'abangateway_url'        => 'gw_abangateway',
                    'abangateway_apikey'     => 'gw_abangateway',
                    'atlaspay_apikey'        => 'gw_atlaspay',
                    'atlaspay_paymode'       => 'gw_atlaspay',
                    'atlaspay_account'       => 'gw_atlaspay',
                    'zpal_name'              => 'gw_zarinpal',
                    'plisio_name'            => 'gw_plisio',
                    'cronjob_display'        => 'cronjobs',
                    'affiliates_view_banner' => 'featnav_affiliates',
                    'apn:🔄 تغییر نوع پنل'    => 'PanelMenu',
                    'apn:🔧 کانفیگ دستی'      => 'PanelMenu',
                ],
                'labels' => [
                    '📚 افزودن آموزش'          => 'help',
                    '🖼 مشاهده بنر فعال'        => 'featnav_affiliates',
                    '📅 سقف روزانه معرفی'       => 'affiliates_antifraud_menu',
                    'ویرایش رسانه'             => 'help_edit',
                    '🔼 اضافه کردن دپارتمان'    => 'support',
                    '⬇️ کف رمزارز آفلاین'       => 'gw_tron',
                    '🗂 نام درگاه ریالی سوم'    => 'gw_iranpay',
                    '🗂 نام درگاه استار'        => 'gw_star',
                    '🔐 IPN Secret nowpayment' => 'gw_nowpayment',
                    'نوع ریست حجم'             => 'product_editor',
                    '🆓 محدودیت رایگان'         => 'featnav_changeloc',
                    '🚫 استثناء کاربران'        => 'gw_cart_auto',
                ],
            ];
        }
        return $sig;
    }

    public static function rxNavGetState($from_id)
    {
        if (isset($GLOBALS['user']) && is_array($GLOBALS['user'])
            && isset($GLOBALS['user']['nav_state']) && is_string($GLOBALS['user']['nav_state'])
            && $GLOBALS['user']['nav_state'] !== '') {
            return $GLOBALS['user']['nav_state'];
        }
        $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', 'nav_state', 'id', $from_id, 'select', ['cache' => false]) : null;
        if (is_array($row) && isset($row['nav_state']) && is_string($row['nav_state']) && $row['nav_state'] !== '') {
            return $row['nav_state'];
        }
        return 'home';
    }

    public static function rxNavSetState($from_id, $state)
    {
        $state = (string) $state;
        if ($state === '' || !self::rxIsRenderableState($state)) {
            return false;
        }
        $current = null;
        if (isset($GLOBALS['user']) && is_array($GLOBALS['user']) && isset($GLOBALS['user']['nav_state'])) {
            $current = (string) $GLOBALS['user']['nav_state'];
        }
        if ($current === $state) {
            return true;
        }
        try {
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                \Faoxima\Core\Database\Db::update('user', 'nav_state', $state, 'id', $from_id);
            }
        } catch (\Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('NAV_STATE_SET_FAILED', $e->getMessage(), ['from_id' => $from_id, 'state' => $state]);
            }
            return false;
        }
        if (isset($GLOBALS['user']) && is_array($GLOBALS['user'])) {
            $GLOBALS['user']['nav_state'] = $state;
        }
        return true;
    }

    public static function rxNavTrackKeyboard($from_id, $keyboard)
    {
        if ($keyboard === null || $keyboard === '') {
            return null;
        }
        if (is_string($keyboard)) {
            static $hubKeyboards = [
                'adminPanelsMenu'          => 'panels',
                'adminUsersMenu'           => 'usershub',
                'adminChannelMenu'         => 'channelhub',
                'shopkeyboard'             => 'shop',
                'keyboard_shop_manage'     => 'products',
                'keyboard_Category_manage' => 'categories',
                'setting_panel'            => 'settings',
                'keyboardhelpadmin'        => 'help',
                'supportcenter'            => 'support',
            ];
            foreach ($hubKeyboards as $var => $state) {
                if (isset($GLOBALS[$var]) && is_string($GLOBALS[$var]) && $GLOBALS[$var] === $keyboard) {
                    self::rxNavSetState($from_id, $state);
                    return $state;
                }
            }
        }
        $decoded = is_array($keyboard) ? $keyboard : (is_string($keyboard) ? json_decode($keyboard, true) : null);
        if (!is_array($decoded)) {
            return null;
        }
        $rows = null;
        if (isset($decoded['inline_keyboard']) && is_array($decoded['inline_keyboard'])) {
            $rows = $decoded['inline_keyboard'];
        } elseif (isset($decoded['keyboard']) && is_array($decoded['keyboard'])) {
            $rows = $decoded['keyboard'];
        }
        if ($rows === null) {
            return null;
        }
        $sig = self::rxNavMenuSignatures();
        $labelHit = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $btn) {
                if (!is_array($btn)) {
                    continue;
                }
                if (isset($btn['callback_data']) && is_string($btn['callback_data'])) {
                    if (strpos($btn['callback_data'], 'ch_editremark_') === 0 || strpos($btn['callback_data'], 'ch_editjoin_') === 0 || strpos($btn['callback_data'], 'ch_editlink_') === 0) {
                        self::rxNavSetState($from_id, 'channel_manage');
                        return 'channel_manage';
                    }
                    if (isset($sig['callbacks'][$btn['callback_data']])) {
                        $state = $sig['callbacks'][$btn['callback_data']];
                        self::rxNavSetState($from_id, $state);
                        return $state;
                    }
                }
                if ($labelHit === null && isset($btn['text']) && is_string($btn['text']) && isset($sig['labels'][$btn['text']])) {
                    $labelHit = $sig['labels'][$btn['text']];
                }
            }
        }
        if ($labelHit !== null) {
            self::rxNavSetState($from_id, $labelHit);
            return $labelHit;
        }
        return null;
    }

    public static function rxRenderMenuState($state, $from_id)
    {
        global $keyboardadmin, $adminUsersMenu, $adminPanelsMenu, $adminChannelMenu,
               $shopkeyboard, $keyboard_shop_manage, $keyboard_Category_manage,
               $change_product, $setting_panel, $keyboardhelpadmin, $helpedit,
               $channelkeyboard, $supportcenter, $textbotlang, $user,
               $affiliates, $affiliatesAntiFraud, $keyboardchangelimit, $autoconfirm_advanced_keyboard,
               $CartManage, $trnado, $tonpay, $rialit, $cubepay, $blupal, $atlaspay, $keyboardzarinpal,
               $NowPaymentsManage, $iranpaykeyboard, $tronnowpayments, $Startelegram, $nowpayment_setting_keyboard,
               $backadmin;

        $state = (string) $state;

        if ($state !== 'addchannelid' && \Faoxima\Admin\Telegram\ChannelNavigation::rxAdminNavRequiresChannelReport()) {
            \Faoxima\Admin\Telegram\ChannelNavigation::rxAdminPanelEntryForceChannelReportSetup($from_id, $GLOBALS['setting'], $textbotlang, $backadmin);
            if (isset($user) && is_array($user)) { $user['step'] = 'addchannelid'; }
            return true;
        }

        $msg      = isset($textbotlang['Admin']['Back-menu']) ? $textbotlang['Admin']['Back-menu'] : 'بازگشت';
        $sel      = isset($textbotlang['users']['selectoption']) ? $textbotlang['users']['selectoption'] : $msg;
        $fallback = isset($keyboardadmin) ? $keyboardadmin : null;

        $send = function ($kb, $text) use ($from_id, $fallback) {
            $kb = ($kb !== null && $kb !== '') ? $kb : $fallback;
            if (method_exists(\Faoxima\Telegram\Support\Replies::class, 'nm_adminInstantReply')) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text, $kb, 'HTML');
            }
        };

        $gateways = [
            'gw_cart'       => [isset($CartManage) ? $CartManage : null, 'admin_nav_cart_settings'],
            'gw_cart_auto'  => [isset($autoconfirm_advanced_keyboard) ? $autoconfirm_advanced_keyboard : null, 'home'],
            'gw_trnado'     => [isset($trnado) ? $trnado : null, 'home'],
            'gw_tonpay'     => [isset($tonpay) ? $tonpay : null, 'home'],
            'gw_rialit'     => [isset($rialit) ? $rialit : null, 'home'],
            'gw_tetraminator' => [\Faoxima\Admin\Telegram\Handlers\TetraminatorSettings::menuKeyboard(), 'home'],
            'gw_cubepay'    => [isset($cubepay) ? $cubepay : null, 'home'],
            'gw_blupal'     => [isset($blupal) ? $blupal : null, 'home'],
            'gw_atlaspay'   => [isset($atlaspay) ? $atlaspay : null, 'home'],
            'gw_zarinpal'   => [isset($keyboardzarinpal) ? $keyboardzarinpal : null, 'home'],
            'gw_plisio'     => [isset($NowPaymentsManage) ? $NowPaymentsManage : null, 'home'],
            'gw_iranpay'    => [isset($iranpaykeyboard) ? $iranpaykeyboard : null, 'home'],
            'gw_tron'       => [isset($tronnowpayments) ? $tronnowpayments : null, 'home'],
            'gw_star'       => [isset($Startelegram) ? $Startelegram : null, 'home'],
            'gw_nowpayment' => [isset($nowpayment_setting_keyboard) ? $nowpayment_setting_keyboard : null, 'home'],
        ];
        if (isset($gateways[$state])) {
            if ($gateways[$state][0] === null) {
                return self::rxRenderMenuState('finance', $from_id);
            }
            \Faoxima\Telegram\Navigation\Steps::step($gateways[$state][1], $from_id);
            $send($gateways[$state][0], $sel);
            self::rxNavSetState($from_id, $state);
            return true;
        }

        switch ($state) {
            case 'home':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send($fallback, $msg);
                self::rxNavSetState($from_id, 'home');
                return true;

            case 'usershub':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($adminUsersMenu) ? $adminUsersMenu : $fallback, $sel);
                return true;

            case 'finance':
                \Faoxima\Telegram\Navigation\Steps::step('admin_nav_finance', $from_id);
                if (method_exists(\Faoxima\Admin\Keyboard\AdminBuildersHelpers::class, 'sendAdminFinanceMenu')) {
                    \Faoxima\Admin\Keyboard\AdminBuildersHelpers::sendAdminFinanceMenu($from_id, $msg);
                } else {
                    $send($fallback, $msg);
                }
                self::rxNavSetState($from_id, 'finance');
                return true;

            case 'shop':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($shopkeyboard) ? $shopkeyboard : $fallback, $msg);
                return true;

            case 'products':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($keyboard_shop_manage) ? $keyboard_shop_manage : $fallback, $sel);
                return true;

            case 'product_editor':
                \Faoxima\Telegram\Navigation\Steps::step('product_editor', $from_id);
                $send(isset($change_product) ? $change_product : (isset($keyboard_shop_manage) ? $keyboard_shop_manage : $fallback), $msg);
                return true;

            case 'categories':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($keyboard_Category_manage) ? $keyboard_Category_manage : $fallback, $sel);
                return true;

            case 'panels':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if (\Faoxima\Core\Database\Db::runtimeReady()) {
                    \Faoxima\Core\Database\Db::update('user', 'Processing_value', '0', 'id', $from_id);
                }
                $send(isset($adminPanelsMenu) ? $adminPanelsMenu : $fallback, $sel);
                return true;

            case 'PanelMenu':
                $name = '';
                if (is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser'])) {
                    $name = \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser(is_array($user) ? $user : null);
                } elseif (is_array($user) && isset($user['Processing_value'])) {
                    $name = (string) $user['Processing_value'];
                }
                if ($name !== '' && $name !== '0' && method_exists(\Faoxima\Services\Panels\PanelSetup::class, 'outtypepanel')) {
                    if (\Faoxima\Core\Database\Db::runtimeReady()) {
                        \Faoxima\Core\Database\Db::update('user', 'Processing_value', $name, 'id', $from_id);
                    }
                    $panel = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $name, 'select');
                    if (is_array($panel) && !empty($panel)) {
                        \Faoxima\Services\Panels\PanelSetup::outtypepanel($panel['type'], $msg);
                        return true;
                    }
                }
                return self::rxRenderMenuState('panels', $from_id);

            case 'settings':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($setting_panel) ? $setting_panel : $fallback, $msg);
                return true;

            case 'cronjobs':
                if (method_exists(\Faoxima\Core\Cron\Schedules::class, 'buildCronJobsKeyboard')) {
                    $send(\Faoxima\Core\Cron\Schedules::buildCronJobsKeyboard(), $msg);
                    \Faoxima\Telegram\Navigation\Steps::step('admin_nav_cron_jobs', $from_id);
                    self::rxNavSetState($from_id, 'cronjobs');
                    return true;
                }
                return self::rxRenderMenuState('featcat_crons', $from_id);

            case 'help':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($keyboardhelpadmin) ? $keyboardhelpadmin : $fallback, $msg);
                return true;

            case 'help_edit':
                $send(isset($helpedit) ? $helpedit : (isset($keyboardhelpadmin) ? $keyboardhelpadmin : $fallback), $sel);
                \Faoxima\Telegram\Navigation\Steps::step('help_edit', $from_id);
                return true;

            case 'channel_manage':
                $chanId = 0;
                if (isset($user['Processing_value']) && is_numeric($user['Processing_value'])) {
                    $chanId = (int)$user['Processing_value'];
                } else {
                    $uRow = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', 'Processing_value', 'id', $from_id, 'select', ['cache' => false]) : null;
                    if ($uRow && isset($uRow['Processing_value']) && is_numeric($uRow['Processing_value'])) {
                        $chanId = (int)$uRow['Processing_value'];
                    }
                }
                if ($chanId > 0 && method_exists(\Faoxima\Admin\Telegram\ChannelNavigation::class, 'rx_render_channel_manage')) {
                    return \Faoxima\Admin\Telegram\ChannelNavigation::rx_render_channel_manage($chanId, $from_id);
                }
                return self::rxRenderMenuState('channel', $from_id);

            case 'channel':
                \Faoxima\Telegram\Navigation\Steps::step('channel', $from_id);
                $ckb = method_exists(\Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::class, 'rx_get_channel_keyboard') ? \Faoxima\Admin\Keyboard\ChannelAndFeatureMenusHelpers::rx_get_channel_keyboard() : (isset($channelkeyboard) ? $channelkeyboard : $fallback);
                $send($ckb, isset($textbotlang['Admin']['channel']['description']) ? $textbotlang['Admin']['channel']['description'] : $msg);
                self::rxNavSetState($from_id, 'channel');
                return true;

            case 'channelhub':
                \Faoxima\Telegram\Navigation\Steps::step('channelhub', $from_id);
                $send(isset($adminChannelMenu) ? $adminChannelMenu : $fallback, $sel);
                self::rxNavSetState($from_id, 'channelhub');
                return true;

            case 'support':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                $send(isset($supportcenter) ? $supportcenter : $fallback, $sel);
                return true;

            case 'premium_emoji':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if (method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxRenderPremiumEmojiPanel')) {
                    \Faoxima\Admin\Telegram\PremiumEmoji::rxRenderPremiumEmojiPanel($from_id, 1);
                    self::rxNavSetState($from_id, 'premium_emoji');
                    return true;
                }
                return self::rxRenderMenuState('featcat_main', $from_id);

            case 'featcat_main':
            case 'featcat_bot':
            case 'featcat_users':
            case 'featcat_crons':
            case 'featcat_shop':
            case 'featcat_lottery':
            case 'featcat_antispam':
            case 'featcat_redis':
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if (method_exists(\Faoxima\Admin\Telegram\FeatureViews::class, 'rxRenderFeatureStatus')) {
                    $rxFeatView = substr($state, strlen('featcat_'));
                    if (\Faoxima\Admin\Telegram\FeatureViews::rxRenderFeatureStatus($rxFeatView, $from_id)) {
                        self::rxNavSetState($from_id, $state);
                        return true;
                    }
                    return false;
                }
                $send(isset($setting_panel) ? $setting_panel : $fallback, $msg);
                return true;

            case 'featnav_affiliates':
                \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
                $send(isset($affiliates) ? $affiliates : $fallback, $sel);
                self::rxNavSetState($from_id, 'featnav_affiliates');
                return true;

            case 'affiliates_antifraud_menu':
                \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
                $send(isset($affiliatesAntiFraud) ? $affiliatesAntiFraud : $fallback, $sel);
                self::rxNavSetState($from_id, 'affiliates_antifraud_menu');
                return true;

            case 'featnav_changeloc':
                \Faoxima\Telegram\Navigation\Steps::step('featnav_changeloc', $from_id);
                $send(isset($keyboardchangelimit) ? $keyboardchangelimit : $fallback, $sel);
                self::rxNavSetState($from_id, 'featnav_changeloc');
                return true;
        }
        return false;
    }

    public static function rxNavCashbackGatewayParent($from_id)
    {
        static $map = [
            'CartManage'                  => 'gw_cart',
            'trnado'                      => 'gw_trnado',
            'tonpay'                      => 'gw_tonpay',
            'rialit'                      => 'gw_rialit',
            'tetraminator'                => 'gw_tetraminator',
            'cubepay'                     => 'gw_cubepay',
            'blupal'                      => 'gw_blupal',
            'abangateway'                 => 'gw_abangateway',
            'atlaspay'                    => 'gw_atlaspay',
            'NowPaymentsManage'           => 'gw_plisio',
            'nowpayment_setting_keyboard' => 'gw_nowpayment',
            'keyboardzarinpal'            => 'gw_zarinpal',
        ];
        $raw = null;
        if (isset($GLOBALS['user']) && is_array($GLOBALS['user']) && isset($GLOBALS['user']['Processing_value'])) {
            $raw = $GLOBALS['user']['Processing_value'];
        } elseif (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('user', 'Processing_value', 'id', $from_id, 'select', ['cache' => false]);
            $raw = is_array($row) ? ($row['Processing_value'] ?? null) : null;
        }
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['cashback_menu']) || !is_string($data['cashback_menu'])) {
            return null;
        }
        return $map[$data['cashback_menu']] ?? null;
    }

    public static function rxNavBack($from_id, $originHint = null, $currentStep = null)
    {
        try {
            $target = null;

            if ($currentStep === null) {
                $row = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select('user', 'step', 'id', $from_id, 'select', ['cache' => false]) : null;
                $currentStep = (is_array($row) && isset($row['step'])) ? (string) $row['step'] : '';
            }
            $currentStep = (string) $currentStep;

            $cashbackSharedSteps = ['getcashtarget' => true, 'getcashtargetdays' => true, 'getcashscope' => true];

            if (is_string($originHint) && $originHint !== '') {
                $target = $originHint;
            } elseif (isset($cashbackSharedSteps[$currentStep]) && ($gwParent = self::rxNavCashbackGatewayParent($from_id)) !== null) {
                $target = $gwParent;
            } elseif (self::rxNavIsMenuStep($currentStep)) {
                $target = self::rxNavParent($currentStep);
            } elseif ($currentStep !== '' && $currentStep !== 'home') {
                $parent = self::rxNavParent($currentStep);
                if ($parent !== null && $parent !== '') {
                    $target = $parent;
                } else {
                    $navState = self::rxNavGetState($from_id);
                    if (self::rxIsRenderableState($navState) && $navState !== 'home') {
                        $target = $navState;
                    } else {
                        $target = $parent;
                    }
                }
            } else {
                $target = self::rxNavParent(self::rxNavGetState($from_id));
            }

            if ($target === null || $target === '') {
                return false;
            }

            $seen = [];
            while ($target !== null && $target !== '' && !self::rxIsRenderableState($target)) {
                if (isset($seen[$target])) {
                    return false;
                }
                $seen[$target] = true;
                $target = self::rxNavParent($target);
            }

            if ($target === null || $target === '' || !self::rxIsRenderableState($target)) {
                return false;
            }

            return self::rxRenderMenuState($target, $from_id) ? true : false;
        } catch (\Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('NAV_BACK_FAILED', $e->getMessage(), ['from_id' => $from_id, 'origin' => $originHint]);
            }
            return false;
        }
    }

    public static function rxAssertUniqueMenuCallbacks(array $keyboardJsons)
    {
        $navExempt = array_flip([
            'backmenu', 'backadmin', 'admin', 'adm_hub_main', 'adm_backmenu', 'backuser',
            'cart_backmenu', 'trnado_backmenu', 'zpal_backmenu', 'zpey_backmenu',
            'aqaye_backmenu', 'plisio_backmenu', 'help_backmenu', 'feat_backmenu',
            'ch_backmenu', 'set_backmenu', 'shop_backmenu', 'cat_back', 'shopitem_back',
            'usershub_backmenu', 'panelshub_backmenu', 'channelhub_backmenu', 'wallet_backmenu',
            'tonpay_backmenu', 'cubepay_backmenu', 'blupal_backmenu', 'variza_backmenu', 'abangateway_backmenu', 'atlaspay_backmenu',
            'rialit_backmenu',
        ]);
        $seen = [];
        $collisions = [];
        $walk = function ($node) use (&$walk, &$seen, &$collisions, $navExempt) {
            if (!is_array($node)) {
                return;
            }
            if (isset($node['callback_data']) && is_string($node['callback_data'])) {
                $cb = $node['callback_data'];
                if (!isset($navExempt[$cb])) {
                    if (isset($seen[$cb])) {
                        $collisions[$cb] = ($collisions[$cb] ?? 1) + 1;
                    }
                    $seen[$cb] = true;
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        foreach ($keyboardJsons as $json) {
            $decoded = is_array($json) ? $json : (is_string($json) && $json !== '' ? json_decode($json, true) : null);
            if (is_array($decoded)) {
                $walk($decoded);
            }
        }
        if (!empty($collisions) && function_exists('rx_log_event')) {
            rx_log_event('NAV_CALLBACK_COLLISION', json_encode($collisions, JSON_UNESCAPED_UNICODE), []);
        }
        return $collisions;
    }
}
