<?php

namespace Faoxima\Payments\Gateways;

use Throwable;

final class GatewayRuntime
{
    public static function nowPayments($payment, $price_amount, $order_id, $order_description)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Nowpayments\Client::create($domainhosts, $payment, $price_amount, $order_id, $order_description);
    }

    public static function tonpayCreateInvoice($order_id, $amount, $buyer_chat_id = null)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Tonpay\Client::createInvoice($domainhosts, $order_id, $amount, $buyer_chat_id);
    }

    public static function blupalCreateInvoice($order_id, $amountToman)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Blupal\Client::createInvoice($domainhosts, $order_id, $amountToman);
    }

    /**
 * کارمزد کیوب‌پی از کیف پول فروشنده کم می‌شود. این تابع اجازه می‌دهد آن هزینه
 * روی فاکتور گذاشته شود تا کاربر پرداختش کند:
 *
 *   عدد 0 تا 100  → درصدی که به مبلغ فاکتور اضافه می‌شود (اعشار مجاز، مثلاً 9.9)
 *   عدد بالای 100 → مبلغ ثابت به تومان
 *   صفر (پیش‌فرض) → غیرفعال؛ کارمزد را خود فروشنده می‌پردازد
 *
 * فقط مبلغ فاکتور درگاه بزرگ‌تر می‌شود. اعتباری که به کاربر داده می‌شود عوض
 * نمی‌شود، چون payment/cubepay.php با Payment_report.price کار می‌کند که همان
 * مبلغ درخواستی کاربر است.
 */
    public static function cubepayCreatePayment($order_id, $amount_toman, $customer_user_id = null, $description = '', $inlineInBot = false)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Cubepay\Client::createPayment($domainhosts, $order_id, $amount_toman, $customer_user_id, $description, $inlineInBot);
    }

    public static function cubepayBotInvoice($orderId, $baseToman, array $payment, $paymentLabel)
    {
        $escape = static fn($value) => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $paymentLink = (string) ($payment['payment_link'] ?? '');
        $inline = !empty($payment['show_card_in_bot']);
        $rial = 0;
        $hasExactRial = false;
        $rialMalformed = false;

        if (
            array_key_exists('pay_amount_rial', $payment)
            && $payment['pay_amount_rial'] !== null
            && trim((string) $payment['pay_amount_rial']) !== ''
        ) {
            $rawRial = trim((string) $payment['pay_amount_rial']);
            if (preg_match('/\A[1-9][0-9]{0,17}\z/D', $rawRial)) {
                $rial = (int) $rawRial;
                $hasExactRial = true;
            } else {
                $rialMalformed = true;
            }
        }

        if (!$hasExactRial && !$rialMalformed && array_key_exists('pay_amount_toman', $payment)) {
            $rawToman = trim((string) $payment['pay_amount_toman']);
            if (preg_match('/\A[1-9][0-9]{0,15}\z/D', $rawToman)) {
                $rial = (int) $rawToman * 10;
                $hasExactRial = true;
            }
        }

        $cardNumber = '';
        $cardHolder = '';
        if ($inline) {
            $card = isset($payment['card']) && is_array($payment['card']) ? $payment['card'] : [];
            $rawNumber = isset($card['number']) && is_scalar($card['number'])
                ? trim((string) $card['number']) : '';
            $normalizedNumber = preg_replace('/[\s\-]+/', '', $rawNumber);
            if (
                is_string($normalizedNumber)
                && preg_match('/\A[0-9]{16}\z/D', $normalizedNumber)
                && $hasExactRial
                && $rial > 0
            ) {
                $cardNumber = $normalizedNumber;
                $cardHolder = isset($card['holder']) && is_scalar($card['holder'])
                    ? trim((string) $card['holder']) : '';
            } else {
                $inline = false;
            }
        }

        $minutes = 0;
        if (isset($payment['expires_in_minutes']) && is_scalar($payment['expires_in_minutes'])) {
            $rawMinutes = trim((string) $payment['expires_in_minutes']);
            if (preg_match('/\A[1-9][0-9]{0,4}\z/D', $rawMinutes)) {
                $minutes = (int) $rawMinutes;
            }
        }

        if ($inline) {
            $tomanText = number_format(intdiv($rial, 10), 0, '.', ',')
                . ($rial % 10 ? '.' . ($rial % 10) : '');
            $rialText = number_format($rial, 0, '.', ',');

            $lines = [];
            $lines[] = '✅ فاکتور پرداخت شما';
            $lines[] = '';
            $lines[] = '🧾 شماره سفارش: <code>' . $escape($orderId) . '</code>';
            $lines[] = '';
            $lines[] = '💳 پرداخت کارت‌به‌کارت';
            $lines[] = '';
            $lines[] = '🔢 شماره کارت:';
            $lines[] = '<code>' . $cardNumber . '</code>';
            $lines[] = '';
            if ($cardHolder !== '') {
                $lines[] = '👤 به نام: ' . $escape($cardHolder);
                $lines[] = '';
            }
            $lines[] = '⚠️ مبلغ دقیق به تومان: <code>' . $tomanText . '</code>';
            $lines[] = '⚠️ مبلغ دقیق به ریال: <code>' . $rialText . '</code>';
            $lines[] = '';
            if ($minutes > 0) {
                $lines[] = '⏳ مهلت پرداخت: ' . $minutes . ' دقیقه';
                $lines[] = '';
            }
            $lines[] = 'مبلغ را دقیقاً مطابق واحد انتخاب‌شده';
            $lines[] = 'در برنامه بانکی وارد کنید.';
            $lines[] = '';
            $lines[] = 'تأیید خودکار با همین مبلغ انجام می‌شود.';
            $text = implode("\n", $lines);
        } else {
            $pricetoman = number_format((int) $baseToman, 0);
            $text = "✅ تراکنش شما ایجاد شد

🛒 کد پیگیری:  <code>" . $escape($orderId) . "</code>
💲 مبلغ تراکنش به تومان  : <code>$pricetoman</code>

💢 لطفا به این نکات قبل از پرداخت توجه کنید 👇

🔹 تراکنش تا ۳۰ دقیقه اعتبار و پس از آن در صورت پرداخت تایید نخواهد شد .

✅ در صورت مشکل میتوانید با پشتیبانی در ارتباط باشید";
        }

        $buttons = [];
        if ($inline && !empty($payment['crypto_payment_url']) && is_string($payment['crypto_payment_url'])) {
            $cryptoUrl = trim($payment['crypto_payment_url']);
            $parts = parse_url($cryptoUrl);

            if (
                filter_var($cryptoUrl, FILTER_VALIDATE_URL)
                && is_array($parts)
                && ($parts['scheme'] ?? '') === 'https'
                && strtolower($parts['host'] ?? '') === 'cubevps.ir'
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !isset($parts['port'])
            ) {
                $buttons[] = [
                    [
                        'text' => '💎 پرداخت با کریپتو',
                        'url' => $cryptoUrl
                    ]
                ];
            }
        }

        $buttons[] = [
            [
                'text' => (string) $paymentLabel,
                'url' => $paymentLink
            ]
        ];

        $keyboard = json_encode([
            'inline_keyboard' => $buttons
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'text' => $text,
            'reply_markup' => $keyboard
        ];
    }

    /**
 * Variza — automated card-to-card gateway (https://variza.ir).
 *
 * Same role as cubepayCreatePayment(): create a payment link for an order and
 * return a normalized array. Verification is push-only (payment/variza_webhook.php),
 * so there is no verify/status counterpart here.
 */
    public static function varizaCreatePayment($order_id, $amount_toman)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Variza\Client::createPayment($domainhosts, $order_id, $amount_toman);
    }

    /**
 * On, with an https address and a key. The buyer's button and the mini app's
 * method list both ask this, so a half-configured gateway is never offered.
 */
    public static function abangatewayIsReady()
    {
        return \Faoxima\Payments\Gateways\Abangateway\Client::isReady();
    }

    /**
 * The buyer's payment page for an authority this bot was given, or null.
 *
 * The page lives on the gateway's own origin at /pay/{invoice}, and the
 * authority is "abn_" + that invoice id, so the link can be rebuilt from the
 * stored authority without keeping a second column for it.
 */
    public static function abangatewayCreatePayment($order_id, $amount_toman, $user_id = null)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Abangateway\Client::createPayment($domainhosts, $order_id, $amount_toman, $user_id);
    }

    public static function abangatewayCardInvoice(array $pay, $order_id)
    {
        if (empty($pay['card_in_bot'])) {
            return null;
        }

        $card = is_array($pay['card'] ?? null) ? $pay['card'] : [];
        $number = preg_replace('/\D+/', '', trim((string) ($card['number'] ?? '')));
        if ($number === null || strlen($number) !== 16) {
            return null;
        }

        $holder = trim((string) ($card['holder'] ?? ''));
        if ($holder === '') {
            return null;
        }
        $bank = trim((string) ($card['bank'] ?? ''));

        $rial = (int) ($pay['payable_rial'] ?? 0);
        $toman = (int) ($pay['payable_toman'] ?? 0);
        if ($rial <= 0 || $toman <= 0 || $rial !== $toman * 10) {
            return null;
        }

        $minutes = (int) ($pay['minutes_left'] ?? 0);
        if ($minutes <= 0) {
            return null;
        }

        $payUrl = trim((string) ($pay['pay_url'] ?? ''));
        $parts = parse_url($payUrl);
        if (
            !is_array($parts) ||
            strtolower((string) ($parts['scheme'] ?? '')) !== 'https' ||
            empty($parts['host'])
        ) {
            return null;
        }

        global $textbotlang;

        $order = htmlspecialchars((string) $order_id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $holderEsc = htmlspecialchars($holder, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bankEsc = htmlspecialchars($bank, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $defaultTemplate = "✅ تراکنش شما ایجاد شد\n\n"
            . "🛒 کد پیگیری: {order}\n\n"
            . "💳 شماره کارت:\n{card}\n\n"
            . "👤 به نام: {holder}\n\n"
            . "{bank}"
            . "💰 مبلغ دقیق: {amount} تومان\n"
            . "به ریال: {rial}\n"
            . "به تومان: {toman}\n\n"
            . "⏳ مهلت پرداخت: {minutes} دقیقه\n\n"
            . "⚠️ دقیقاً همین مبلغ را کارت به کارت کنید، نه بیشتر و نه کمتر؛ تأیید خودکار با همین عدد انجام می‌شود.\n\n"
            . "بعد از واریز نیازی به ارسال رسید نیست. نتیجه پرداخت از طریق ربات اطلاع‌رسانی خواهد شد.\n\n"
            . "💡 در صورت تمایل می‌توانید از دکمه پرداخت برای ورود به صفحه پرداخت استفاده کنید.";

        $template = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_abangateway_card_invoice', $defaultTemplate);

        $text = \Faoxima\Telegram\Support\Texts::faoxima_render_text($template, [
            'order' => '<code>' . $order . '</code>',
            'card' => '<code>' . $number . '</code>',
            'holder' => $holderEsc,
            'bank' => $bankEsc !== '' ? "🏦 بانک: " . $bankEsc . "\n\n" : '',
            'amount' => '<code>' . $toman . '</code>',
            'rial' => '<code>' . $rial . '</code>',
            'toman' => '<code>' . $toman . '</code>',
            'minutes' => (string) $minutes,
        ]);

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    [
                        'text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_abangateway_copy_card', '📋 کپی شماره کارت'),
                        'copy_text' => [
                            'text' => $number
                        ]
                    ]
                ],
                [
                    [
                        'text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_abangateway_copy_rial', '📋 کپی مبلغ به ریال'),
                        'copy_text' => [
                            'text' => (string) $rial
                        ]
                    ],
                    [
                        'text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_abangateway_copy_toman', '📋 کپی مبلغ به تومان'),
                        'copy_text' => [
                            'text' => (string) $toman
                        ]
                    ]
                ],
                [
                    [
                        'text' => $textbotlang['users']['Balance']['payments'] ?? 'پرداخت',
                        'url' => $payUrl
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);

        return [
            'text' => $text,
            'keyboard' => $keyboard,
        ];
    }

    public static function rxEnsurePaymentRuntime()
    {
        global $setting, $textbotlang, $datatextbot;

        try {
            if (!is_array($setting) && \Faoxima\Core\Database\Db::runtimeReady()) {
                $rxSetting = \Faoxima\Core\Database\Db::select("setting", "*");
                if (is_array($rxSetting)) {
                    $setting = $rxSetting;
                }
            }

            if ((!is_array($textbotlang) || $textbotlang === []) && method_exists(\Faoxima\Telegram\Conversation\ConversationData::class, 'languagechange')) {
                $rxLang = \Faoxima\Telegram\Conversation\ConversationData::languagechange(REFACTORED_LEGACY_ROOT . '/text.json');
                if (is_array($rxLang) && $rxLang !== []) {
                    $textbotlang = $rxLang;
                }
            }

            if (!is_array($datatextbot)) {
                $datatextbot = [];
            }
            $rxMissingKeys = array_diff(['textafterpay', 'textaftertext', 'textmanual', 'textselectlocation', 'text_wgdashboard'], array_keys($datatextbot));
            if ($rxMissingKeys !== []) {
                $rxTextRows = isset($GLOBALS['_rx_textbot_rows']) && is_array($GLOBALS['_rx_textbot_rows'])
                    ? $GLOBALS['_rx_textbot_rows']
                    : (\Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll") : []);
                foreach ($rxMissingKeys as $rxKey) {
                    $datatextbot[$rxKey] = '';
                }
                foreach ((array) $rxTextRows as $rxRow) {
                    $rxId = (string) ($rxRow['id_text'] ?? '');
                    if (in_array($rxId, $rxMissingKeys, true)) {
                        $datatextbot[$rxId] = (string) ($rxRow['text'] ?? '');
                    } elseif ($rxId !== '' && !array_key_exists($rxId, $datatextbot) && trim((string) ($rxRow['text'] ?? '')) !== '') {
                        $datatextbot[$rxId] = (string) $rxRow['text'];
                    }
                }
            }
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('PAYMENT_RUNTIME_INIT_FAILED', $e->getMessage(), [
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    public static function DirectPayment($order_id, $image = 'images.jpg', $rxToken = null)
    {
        global $pdo, $ManagePanel, $setting, $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot, $update;
        self::rxEnsurePaymentRuntime();
        $telegram = new \Faoxima\Telegram\Support\PaymentPresentation(
            $textbotlang, $keyboardextendfnished, $keyboard, $Confirm_pay, $from_id, $message_id, $datatextbot, $update
        );
        return \Faoxima\Services\Fulfillment\PaymentDelivery::deliver($pdo, $ManagePanel, $telegram, $order_id, $image, $rxToken, $setting);
    }

    public static function plisio($order_id, $price)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Plisio\Client::create($domainhosts, $order_id, $price);
    }

    public static function CreatePaymentNv($invoice_id, $amount)
    {
        global $domainhosts;
        $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "marchentpaynotverify", "select")['ValuePay'];
        $data = [
            'api_key' => $PaySetting,
            'amount' => $amount,
            'callback_url' => "https://" . $domainhosts . "/payment/paymentnv/back.php",
            'desc' => $invoice_id
        ];
        $data = json_encode($data);
        $ch = curl_init("https://donatekon.com/pay/api/dargah/create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data)
            )
        );
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    public static function createPayZarinpal($price, $order_id)
    {
        global $domainhosts;
        return \Faoxima\Payments\Gateways\Zarinpal\Client::createPayZarinpal($domainhosts, $price, $order_id);
    }
}
