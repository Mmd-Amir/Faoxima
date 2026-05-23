<?php
session_start();
require_once __DIR__ . '/../config.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$query = $pdo->prepare("SELECT * FROM Payment_report ORDER BY time DESC");
$query->execute();
$listpayment = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تراکنش‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('wallet', 'svg-icon svg-lg'); ?>
                        گزارش تراکنش‌ها
                    </div>
                    <div class="page-head__sub">آرشیو پرداخت‌های انجام شده</div>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table id="paymentTable" class="display app-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>آیدی کاربر</th>
                                <th>کد پیگیری</th>
                                <th>مبلغ (تومان)</th>
                                <th>زمان</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listpayment as $list):
                            $method = $list['Payment_Method'];
                            switch ($method) {
                                case 'cart to cart':         $method = 'کارت به کارت'; break;
                                case 'low balance by admin': $method = 'کسر توسط ادمین'; break;
                                case 'add balance by admin': $method = 'افزایش توسط ادمین'; break;
                                case 'Currency Rial 1':      $method = 'درگاه ریالی ۱'; break;
                                case 'Currency Rial tow':    $method = 'درگاه ریالی ۲'; break;
                                case 'Currency Rial 3':      $method = 'درگاه ریالی ۳'; break;
                                case 'aqayepardakht':        $method = 'آقای پرداخت'; break;
                                case 'zarinpal':             $method = 'زرین‌پال'; break;
                                case 'plisio':               $method = 'Plisio'; break;
                                case 'arze digital offline': $method = 'ارز دیجیتال آفلاین'; break;
                                case 'Star Telegram':        $method = 'استارز تلگرام'; break;
                                case 'nowpayment':           $method = 'NowPayment'; break;
                            }
                            $statusText = $list['payment_Status']; $badgeClass = 'badge-gray';
                            switch ($list['payment_Status']) {
                                case 'paid':    $statusText='پرداخت موفق';    $badgeClass='badge-success'; break;
                                case 'Unpaid':  $statusText='ناموفق';        $badgeClass='badge-danger';  break;
                                case 'expire':  $statusText='منقضی شده';     $badgeClass='badge-gray';    break;
                                case 'reject':  $statusText='رد شده';        $badgeClass='badge-danger';  break;
                                case 'waiting': $statusText='در انتظار تایید'; $badgeClass='badge-warning'; break;
                            }
                        ?>
                            <tr>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="کد پیگیری">
                                    <span class="track-id" onclick="copyToClipboard('<?php echo addslashes($list['id_order']); ?>')" title="کپی کردن">
                                        <?php echo htmlspecialchars($list['id_order'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td data-label="مبلغ (تومان)"><?php echo number_format($list['price']); ?></td>
                                <td data-label="زمان" style="direction:ltr; text-align:right;"><?php echo htmlspecialchars($list['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="روش پرداخت"><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="وضعیت"><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>
<script src="js/datatable.js" defer>

</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    FaoximaDT.init("#paymentTable");
});

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
            alert('کد پیگیری کپی شد: ' + text);
        });
    }
}
</script>
</body>
</html>


