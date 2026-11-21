<?php

namespace Faoxima\Telegram\Keyboard;

/**
 * Handler stage. Inherited by value: $_rx_pay_styles, $datatextbot, $from_id, $pdo, $users.
 * Inherited or exported by reference: $step_payment, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class PaymentMethods
{
    public static function handle(
        $_rx_pay_styles,
        $datatextbot,
        $from_id,
        $pdo,
        &$step_payment,
        &$stmt,
        $users
    ) {
        $PaySettingcard = \Faoxima\Core\Database\Db::getPaySettingValue("Cartstatus");
        $PaySettingnow = \Faoxima\Core\Database\Db::getPaySettingValue("nowpaymentstatus");
        $PaySettingpv = \Faoxima\Core\Database\Db::getPaySettingValue("Cartstatuspv");
        $usernamecart = \Faoxima\Core\Database\Db::getPaySettingValue("CartDirect");
        $trnadoo = \Faoxima\Core\Database\Db::getPaySettingValue("statustarnado");
        $tonpayStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statustonpay");
        $rialitStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statusrialit");
        $cubepayStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statuscubepay");
        $blupalStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statusblupal");
        $varizaStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statusvariza");
        $abangatewayStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statusabangateway");
        $abangatewayUrl = trim((string) \Faoxima\Core\Database\Db::getPaySettingValue("urlabangateway"));
        $abangatewayKey = trim((string) \Faoxima\Core\Database\Db::getPaySettingValue("apiabangateway"));
        $atlaspayStatus = \Faoxima\Core\Database\Db::getPaySettingValue("statusatlaspay");
        $paymentverify = \Faoxima\Core\Database\Db::getPaySettingValue("checkpaycartfirst");
        $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = '$from_id' AND payment_Status = 'paid' ");
        $stmt->execute();
        $paymentexits = $stmt->rowCount();
        $zarinpal = \Faoxima\Core\Database\Db::getPaySettingValue("zarinpalstatus");
        $affilnecurrency = \Faoxima\Core\Database\Db::getPaySettingValue("digistatus");
        $paymentstatussnotverify = \Faoxima\Core\Database\Db::getPaySettingValue("paymentstatussnotverify");
        $paymentsstartelegram = \Faoxima\Core\Database\Db::getPaySettingValue("statusstar");
        $payment_status_nowpayment = \Faoxima\Core\Database\Db::getPaySettingValue("statusnowpayment");
        $step_payment = [
            'inline_keyboard' => []
            ];
           if($PaySettingcard == "oncard" && intval($users['cardpayment']) == 1){
                if($PaySettingpv == "oncardpv"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'url' => "https://t.me/$usernamecart"], 'cart_to_offline', $_rx_pay_styles),
            ];
                }else{
                    $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['carttocart'] ?: '💳 کارت به کارت', 'callback_data' => "cart_to_offline"], 'cart_to_offline', $_rx_pay_styles),
            ];
                }
            }
            if(($paymentexits == 0 && $paymentverify == "onpayverify"))unset($step_payment['inline_keyboard']);
           if($PaySettingnow == "onnowpayment"){
                $step_payment['inline_keyboard'][] = [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['textnowpayment'] ?: '💵 پرداخت ارزی (Plisio)', 'callback_data' => "plisio"], 'plisio', $_rx_pay_styles)
            ];
            }
            if($payment_status_nowpayment == "1"){
                $step_payment['inline_keyboard'][] = [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['textsnowpayment'] ?: 'NowPayments', 'callback_data' => "nowpayment"], 'nowpayment', $_rx_pay_styles)
            ];
            }
           if($affilnecurrency == "ondigi"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['textnowpaymenttron'] ?: '💵 واریز رمزارز ترون', 'callback_data' => "digitaltron"], 'digitaltron', $_rx_pay_styles)
            ];
            }
           if($trnadoo == "onternado"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['iranpay3'] ?: 'ترونادو', 'callback_data' => "iranpay2"], 'iranpay2', $_rx_pay_styles)
            ];
            }
           if($tonpayStatus == "ontonpay"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['tonpay'] ?: '💠 تون‌پی', 'callback_data' => "tonpay"], 'tonpay', $_rx_pay_styles)
            ];
            }
           if($rialitStatus == "onrialit"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['rialit'] ?: '💚 ریالیت', 'callback_data' => "rialit"], 'rialit', $_rx_pay_styles)
            ];
            }
           if($cubepayStatus == "oncubepay"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['cubepay'] ?: '🟦 کیوب‌پی', 'callback_data' => "cubepay"], 'cubepay', $_rx_pay_styles)
            ];
            }
           if($blupalStatus == "onblupal"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['blupal'] ?: '💙 بلوپال', 'callback_data' => "blupal"], 'blupal', $_rx_pay_styles)
            ];
            }
           if($varizaStatus == "onvariza"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['variza'] ?: '💳 واریزا', 'callback_data' => "variza"], 'variza', $_rx_pay_styles)
            ];
            }
           // Offered only when it can take money: on, with an https address and a key.
           if($abangatewayStatus == "onabangateway" && stripos($abangatewayUrl, 'https://') === 0 && $abangatewayKey !== '' && $abangatewayKey !== '0'){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['abangateway'] ?: '💳 آبان گیت وی', 'callback_data' => "abangateway"], 'abangateway', $_rx_pay_styles)
            ];
            }
           if($atlaspayStatus == "onatlaspay"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['atlaspay'] ?: '🌐 اطلس‌پی', 'callback_data' => "atlaspay"], 'atlaspay', $_rx_pay_styles)
            ];
            }
            if($zarinpal == "onzarinpal"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['zarinpal'] ?: '🟡 زرین پال', 'callback_data' => "zarinpal"], 'zarinpal', $_rx_pay_styles)
            ];
            }
            if($paymentstatussnotverify == "onverifypay"){
                $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['textpaymentnotverify'] ?: 'درگاه ریالی', 'callback_data' => "paymentnotverify"], 'paymentnotverify', $_rx_pay_styles)
            ];
            }
            if(intval($paymentsstartelegram) == 1){
             $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $datatextbot['text_star_telegram'] ?: '💫 Star Telegram', 'callback_data' => "startelegrams"], 'startelegrams', $_rx_pay_styles)
            ];
            }
            $step_payment['inline_keyboard'][] = [
                    \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "❌ بستن لیست", 'callback_data' => "colselist"], 'colselist', $_rx_pay_styles)
            ];
            $step_payment = json_encode($step_payment);

        return 1;
    }
}
