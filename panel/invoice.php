<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';


$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$query = $pdo->prepare("SELECT * FROM invoice ORDER BY id_invoice DESC");
$query->execute();
$listinvoice = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت سفارشات | ربات فاکسیما</title>
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
                        <?php echo icon('dollar-sign', 'svg-icon svg-lg'); ?>
                        لیست سفارشات
                    </div>
                    <div class="page-head__sub">آرشیو کامل سفارشات کاربران</div>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table id="invoiceTable" class="display app-table" style="width:100%" data-mdt-filter="3,7">
                        <thead>
                            <tr>
                                <th>شناسه سفارش</th>
                                <th>آیدی کاربر</th>
                                <th>نام کانفیگ</th>
                                <th>لوکیشن</th>
                                <th>محصول</th>
                                <th>تاریخ سفارش</th>
                                <th>مبلغ (تومان)</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listinvoice as $list):
                            $time_sell = $list['time_sell'];
                            if (is_numeric($time_sell)) $time_sell = jdate('Y/m/d | H:i', $time_sell);
                            $statusText = 'نامشخص';
                            $statusClass = 'badge-gray';
                            switch ($list['Status']) {
                                case 'unpaid':         $statusText = 'در انتظار پرداخت'; $statusClass = 'badge-unpaid'; break;
                                case 'active':         $statusText = 'فعال';            $statusClass = 'badge-active'; break;
                                case 'disabledn':      $statusText = 'ناموجود در پنل';   $statusClass = 'badge-gray';   break;
                                case 'end_of_time':    $statusText = 'اتمام زمان';      $statusClass = 'badge-danger'; break;
                                case 'end_of_volume':  $statusText = 'اتمام حجم';       $statusClass = 'badge-danger'; break;
                                case 'sendedwarn':     $statusText = 'هشدار پایانی';    $statusClass = 'badge-warning';break;
                                case 'send_on_hold':   $statusText = 'خطای اتصال';      $statusClass = 'badge-info';   break;
                                case 'removebyuser':   $statusText = 'حذف توسط کاربر';   $statusClass = 'badge-gray';   break;
                                case 'removebyadmin':  $statusText = 'حذف توسط ادمین';   $statusClass = 'badge-danger'; break;
                                default:               $statusText = htmlspecialchars($list['Status'], ENT_QUOTES, 'UTF-8');
                            }
                            $price = ($list['price_product'] == 0) ? 'رایگان' : number_format($list['price_product']);
                        ?>
                            <tr>
                                <td data-label="شناسه سفارش"><?php echo $list['id_invoice']; ?></td>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="نام کانفیگ" style="direction:ltr; text-align:right;">
                                    <?php echo htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td data-label="لوکیشن"><?php echo htmlspecialchars($list['Service_location'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="محصول"><?php echo htmlspecialchars($list['name_product'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="تاریخ سفارش" style="direction:ltr; text-align:right;"><?php echo $time_sell; ?></td>
                                <td data-label="مبلغ (تومان)"><?php echo $price; ?></td>
                                <td data-label="وضعیت"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
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
    FaoximaDT.init("#invoiceTable");
  });
</script>
</body>
</html>


