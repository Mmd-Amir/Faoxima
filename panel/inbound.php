<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'inbound.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';

if (\Faoxima\Admin\Web\Pages\InboundRequest::handle(
        listinvoice: $listinvoice,
        pdo: $pdo ?? null,
        query: $query,
        result: $result
    ) !== true) {
    unset($adminWebPage);
    return;
}

unset($adminWebPage);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
  <head>
    <script>
    (function(){try{document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('faoxima_theme','dark');
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>اینباندها — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="js/theme.js?v=dark62" defer>

</script>
  </head>
  <body>

  <section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
      <section class="wrapper">
        <div class="page-head">
          <div>
            <div class="page-head__title">
              <span class="symbol">
              لیست اینباند‌های مرزبان
            </div>
            <div class="page-head__sub">اینباند‌های ثبت‌شده در پنل‌های مرزبان</div>
          </div>
        </div>

        <div class="card">
          <div class="card__head">
            <div class="card__title"><span class="symbol">/</span> اینباند‌ها</div>
            <span class="chip">Inbounds</span>
          </div>
          <div class="table-wrap">
            <table class="display app-table" id="inboundTable" style="width:100%">
              <thead>
                <tr>
                  <th>
                  <th>نام پنل</th>
                  <th>پروتکل</th>
                  <th>نام اینباند</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i = 1;
                foreach($listinvoice as $list){
                    $location = htmlspecialchars($list['location'] ?? '', ENT_QUOTES, 'UTF-8');
                    $protocol = htmlspecialchars($list['protocol'] ?? '', ENT_QUOTES, 'UTF-8');
                    $nameInbound = htmlspecialchars($list['NameInbound'] ?? '', ENT_QUOTES, 'UTF-8');
                    echo "<tr>
                        <td>{$i}</td>
                        <td>{$location}</td>
                        <td><span class='badge badge-info'>{$protocol}</span></td>
                        <td>{$nameInbound}</td>
                    </tr>";
                    $i++;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </section>
  </section>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js">

</script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">

</script>
  <script>
    $(document).ready(function() {
      $('#inboundTable').DataTable({
        language: {
          search: "جستجو:",
          lengthMenu: "نمایش _MENU_ ردیف",
          info: "نمایش _START_ تا _END_ از _TOTAL_ ردیف",
          infoEmpty: "هیچ ردیفی یافت نشد",
          infoFiltered: "(فیلتر شده از _MAX_ ردیف کل)",
          paginate: {
            first: "اول",
            last: "آخر",
            next: "بعدی",
            previous: "قبلی"
          },
          emptyTable: "هیچ اینباندی ثبت نشده است"
        },
        order: [[0, 'asc']],
        pageLength: 25
      });
    });
</script>

</body>
</html>


