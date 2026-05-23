<?php
session_start();
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/lib/icons.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}


$query = $pdo->prepare("SELECT * FROM user ORDER BY id DESC");
$query->execute();
$listusers = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت کاربران | ربات فاکسیما</title>
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
                        <?php echo icon('users', 'svg-icon svg-lg'); ?>
                        لیست کاربران
                    </div>
                    <div class="page-head__sub">مدیریت و مشاهده اطلاعات کاربران ربات</div>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table id="usersTable" class="display app-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>شناسه (ID)</th>
                                <th>نام کاربری</th>
                                <th>شماره تلفن</th>
                                <th>موجودی</th>
                                <th>زیرمجموعه</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listusers as $list):
                            $statusClass = 'badge-active';
                            $statusText  = 'فعال';
                            if (strtolower($list['User_Status']) == 'block') {
                                $statusClass = 'badge-block';
                                $statusText  = 'مسدود';
                            }
                            $number = ($list['number'] == "none") ? '<span class="text-muted">---</span>' : htmlspecialchars($list['number'], ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr>
                                <td data-label="شناسه (ID)"><?php echo $list['id']; ?></td>
                                <td data-label="نام کاربری" style="direction:ltr; text-align:right;">
                                    <?php echo htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td data-label="شماره تلفن"><?php echo $number; ?></td>
                                <td data-label="موجودی"><?php echo number_format($list['Balance']); ?> <small class="text-muted">تومان</small></td>
                                <td data-label="زیرمجموعه"><?php echo (int)$list['affiliatescount']; ?> <small class="text-muted">نفر</small></td>
                                <td data-label="وضعیت"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <a href="user.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-primary">
                                        <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                        مدیریت
                                    </a>
                                </td>
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
    FaoximaDT.init("#usersTable");
  });
</script>
</body>
</html>


