<?php

namespace Faoxima\Admin\Web\Pages;


/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $ip_denied, $otherreport, $password, $result, $setting, $texterrr, $user_ip, $username.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LoginRequest
{
    public static function handle(
        &$ip_denied,
        &$otherreport,
        &$password,
        $pdo,
        &$result,
        &$setting,
        &$texterrr,
        &$user_ip,
        &$username
    ) {
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        \Faoxima\Admin\Web\Support\AdBoot::fx_ad_gate();
        if (empty($_SESSION['fx_ad_nonce'])) {
            $_SESSION['fx_ad_nonce'] = bin2hex(random_bytes(16));
        }

        $texterrr = "";
        $ip_denied = false;

        if (isset($_POST['login'])) {
            $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
            $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

            if ($username !== '' && $password !== '') {
                $result = \Faoxima\Admin\Web\Support\Csrf::fx_admin_find_by_username($pdo, $username);

                $passwordOk = \Faoxima\Admin\Web\Pages\LoginRequestHelpers::fx_login_verify_password($pdo, $result, $password);

                $adminIpOk = \Faoxima\Admin\Web\Pages\LoginRequestHelpers::fx_login_ip_allowed($result, $user_ip);

                if (!$result) {
                    $texterrr = 'نام کاربری یا رمزعبور وارد شده اشتباه است!';
                } elseif (!$passwordOk) {
                    $texterrr = 'رمز صحیح نمی باشد';
                } elseif (!$adminIpOk) {
                    http_response_code(403);
                    $ip_denied = true;
                } else {


                    session_regenerate_id(true);
                    $_SESSION["user"] = $result["username"];


                    session_write_close();


                    header('Location: index.php', true, 302);


                    ignore_user_abort(true);
                    if (function_exists('fastcgi_finish_request')) {
                        @fastcgi_finish_request();
                    } else {
                        if (!headers_sent()) {
                            @header('Connection: close');
                            @header('Content-Length: 0');
                        }
                        while (ob_get_level() > 0) { @ob_end_flush(); }
                        @flush();
                    }


                    try {
                        $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null);
                        $otherreport = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherreport", "select")['idreport'] ?? null;
                        if (!empty($setting['Channel_Report']) && !empty($otherreport)) {
                            $loginText = "🔐 ورود موفق به پنل تحت وب\n\n"
                                . "👤 نام کاربری:\n" . $username . "\n\n"
                                . "🪪 شناسه ادمین:\n" . $result['id_admin'] . "\n\n"
                                . "🌐 IP ورود:\n" . $user_ip . "\n\n"
                                . "🕐 زمان ورود:\n" . (function_exists('jdate') ? jdate('Y/m/d H:i:s', time(), '', 'Asia/Tehran', 'en') : date('Y/m/d H:i:s'));
                            telegram('sendmessage', [
                                'chat_id'           => $setting['Channel_Report'],
                                'message_thread_id' => $otherreport,
                                'text'              => $loginText,
                                'parse_mode'        => "HTML"
                            ]);
                        }
                    } catch (\Throwable $e) {
                        @error_log('Login notify failed: ' . $e->getMessage());
                    }
                    exit;
                }
            } else {
                $texterrr = 'نام کاربری یا رمز عبور خالی است.';
            }
        }

        return true;
    }
}
