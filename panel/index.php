<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err) return;
    $fatal = [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) return;

    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars(
        $err['message'] . ' @ ' . basename((string)$err['file']) . ':' . (int)$err['line'],
        ENT_QUOTES, 'UTF-8'
    );
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8">'
       . '<title>خطای سرور</title>'
       . '<body style="font-family:sans-serif;background:#0a0a0f;color:#f1f3f8;padding:32px;">'
       . '<h2>خطای داخلی سرور</h2><pre style="white-space:pre-wrap">' . $msg . '</pre>'
       . '<p><a style="color:#3b82f6" href="login.php">بازگشت به ورود</a></p>'
       . '</body></html>';
});

session_start();

if (empty($_SESSION['_session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['_session_regenerated'] = true;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../jdf.php';


$sessionUser = isset($_SESSION["user"]) && is_string($_SESSION["user"]) && $_SESSION["user"] !== ''
    ? $_SESSION["user"]
    : null;

if ($sessionUser === null) {
    header('Location: login.php');
    exit;
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username = :username LIMIT 1");
$query->bindValue(':username', $sessionUser, PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}


function faoxima_chart_data(\PDO $pdo, string $range, int $customDays = 7): array
{
    $now = time();

    $bucketHours = 0;
    $bucketCount = 0;
    $labelFmt    = '';

    switch ($range) {
        case '24h':
            $bucketHours = 1;
            $bucketCount = 24;
            $labelFmt    = 'H:00';
            break;
        case '7d':
            $bucketHours = 24;
            $bucketCount = 7;
            $labelFmt    = 'm/d';
            break;
        case '3m':

            $bucketHours = 24 * 7;
            $bucketCount = 13;
            $labelFmt    = 'm/d';
            break;
        case 'custom':
            $customDays = max(1, min(365, $customDays));
            if ($customDays <= 30) {
                $bucketHours = 24;
                $bucketCount = $customDays;
                $labelFmt    = 'm/d';
            } else {

                $bucketHours = 24 * 7;
                $bucketCount = (int)ceil($customDays / 7);
                $labelFmt    = 'm/d';
            }
            break;
        default:
            return ['labels' => [], 'data' => []];
    }

    $bucketSec = $bucketHours * 3600;
    $earliest  = $now - $bucketCount * $bucketSec;


    $stmt = $pdo->prepare(
        "SELECT time_sell, price_product
           FROM invoice
          WHERE time_sell >= :since
            AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold')
            AND name_product != 'سرویس تست'"
    );
    $stmt->bindValue(':since', $earliest, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $buckets = array_fill(0, $bucketCount, 0);
    foreach ($rows as $r) {
        $t = (int)$r['time_sell'];
        if ($t < $earliest || $t > $now) continue;
        $idx = (int)floor(($t - $earliest) / $bucketSec);
        if ($idx < 0 || $idx >= $bucketCount) continue;
        $buckets[$idx] += (int)$r['price_product'];
    }


    $labels = [];
    for ($i = 0; $i < $bucketCount; $i++) {
        $bucketStart = $earliest + $i * $bucketSec;
        $labels[] = function_exists('jdate')
            ? jdate($labelFmt, $bucketStart)
            : date($labelFmt, $bucketStart);
    }

    return ['labels' => $labels, 'data' => $buckets];
}


if (isset($_GET['ajax']) && $_GET['ajax'] === 'chart') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $range = $_GET['range'] ?? '7d';
    if (!in_array($range, ['24h', '7d', '3m', 'custom'], true)) $range = '7d';
    $days  = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    try {
        $payload = faoxima_chart_data($pdo, $range, $days);
        echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


$datefirstday = time() - 86400;


@set_time_limit(20);


$query = $pdo->prepare("SELECT SUM(price_product) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'");
$query->execute();
$subinvoice = $query->fetch(PDO::FETCH_ASSOC);
$total_income = $subinvoice['SUM(price_product)'] ?? 0;


$resultcount = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();


$stmt = $pdo->prepare("SELECT COUNT(*) FROM user WHERE register > :time_register AND register != 'none'");
$stmt->bindValue(':time_register', $datefirstday, PDO::PARAM_INT);
$stmt->execute();
$resultcountday = (int)$stmt->fetchColumn();


$resultcontsell = (int)$pdo->query("SELECT COUNT(*) FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'")->fetchColumn();


$initialChart = faoxima_chart_data($pdo, '7d');
$json_labels  = json_encode($initialChart['labels'], JSON_UNESCAPED_UNICODE);
$json_data    = json_encode($initialChart['data']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>پنل مدیریت ربات فاکسیما</title>
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
                        <?php echo icon('home', 'svg-icon svg-lg'); ?>
                        داشبورد مدیریت
                    </div>
                    <div class="page-head__sub">آمار سریع از وضعیت ربات و فروش</div>
                </div>
                <div class="chip-row">
                    <a href="users.php" class="chip"><?php echo icon('users', 'svg-icon svg-sm'); ?><span>کاربران</span></a>
                    <a href="invoice.php" class="chip"><?php echo icon('dollar-sign', 'svg-icon svg-sm'); ?><span>سفارشات</span></a>
                    <a href="payment.php" class="chip"><?php echo icon('wallet', 'svg-icon svg-sm'); ?><span>تراکنش‌ها</span></a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__icon icon-blue"><?php echo icon('users', 'svg-icon'); ?></div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?php echo number_format($resultcount); ?></span>
                        <span class="stat-card__label">تعداد کل کاربران</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card__icon icon-rose"><?php echo icon('cart-shopping', 'svg-icon'); ?></div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?php echo number_format($resultcontsell); ?></span>
                        <span class="stat-card__label">تعداد کل سفارشات</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card__icon icon-green"><?php echo icon('money-bill', 'svg-icon'); ?></div>
                    <div class="stat-card__info">
                        <span class="stat-card__value">
                            <?php echo number_format($total_income); ?> <small>تومان</small>
                        </span>
                        <span class="stat-card__label">جمع کل فروش</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card__icon icon-purple"><?php echo icon('user-plus', 'svg-icon'); ?></div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?php echo number_format($resultcountday); ?></span>
                        <span class="stat-card__label">کاربران جدید (24 ساعت)</span>
                    </div>
                </div>
            </div>

            <?php if ($resultcontsell != 0): ?>
            <div class="terminal">
                <div class="terminal__bar">
                    <span class="terminal__lights"><i></i><i></i><i></i></span>
                </div>
                <div class="terminal__body">
                    <div class="card__head" style="border:0; padding:0; margin-bottom:14px;">
                        <div class="card__title">
                            <?php echo icon('chart-line', 'svg-icon svg-md'); ?>
                            <span id="salesChartTitle">نمودار فروش ۷ روز اخیر</span>
                        </div>
                    </div>

                    
                    <div class="chart-toolbar">
                        <button class="chart-range-btn" data-range="24h">۲۴ ساعت اخیر</button>
                        <button class="chart-range-btn active" data-range="7d">۱ هفته اخیر</button>
                        <button class="chart-range-btn" data-range="3m">۳ ماه اخیر</button>
                        <div class="grow"></div>
                        <form id="customRangeForm" class="chart-custom-wrap">
                            <input type="number" min="1" max="365" id="customDays" placeholder="N" aria-label="تعداد روز">
                            <span>روز</span>
                            <button type="submit"><?php echo icon('check', 'svg-icon svg-xs'); ?></button>
                        </form>
                    </div>

                    <div style="position:relative;">
                        <div id="salesChart" class="mc-chart" style="height: 380px; width: 100%;"></div>
                        <div id="salesChartLoading" class="chart-loading">
                            <?php echo icon('refresh', 'svg-icon svg-md spin'); ?>
                            <span style="margin-inline-start:10px;">در حال بارگذاری…</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
</section>

<?php if ($resultcontsell != 0): ?>
<script src="js/chart.js" defer>

</script>
<script>
(function () {
    var currentLabels = <?php echo $json_labels; ?>;
    var currentData   = <?php echo $json_data; ?>;
    var currentRange  = '7d';
    var titleEl       = document.getElementById('salesChartTitle');
    var loadingEl     = document.getElementById('salesChartLoading');
    var rangeBtns     = document.querySelectorAll('.chart-range-btn');
    var customForm    = document.getElementById('customRangeForm');
    var customInput   = document.getElementById('customDays');

    var TITLES = {
        '24h': 'نمودار فروش ۲۴ ساعت اخیر',
        '7d':  'نمودار فروش ۷ روز اخیر',
        '3m':  'نمودار فروش ۳ ماه اخیر'
    };

    function persianNum(n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    }

    function renderChart() {
        FaoximaChart.render('#salesChart', {
            labels: currentLabels,
            data:   currentData,
            valueFormatter: function (v) {
                return new Intl.NumberFormat('fa-IR').format(v) + ' تومان';
            }
        });
    }

    function setActive(rangeOrNull) {
        rangeBtns.forEach(function (b) {
            b.classList.toggle('active', rangeOrNull && b.dataset.range === rangeOrNull);
        });
    }

    function fetchRange(range, days) {
        loadingEl.classList.add('show');
        var url = 'index.php?ajax=chart&range=' + encodeURIComponent(range);
        if (range === 'custom' && days) url += '&days=' + encodeURIComponent(days);

        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (j) {
                if (!j.ok) throw new Error(j.error || 'bad response');
                currentLabels = j.labels;
                currentData   = j.data;
                currentRange  = range;
                if (range === 'custom') {
                    titleEl.textContent = 'نمودار فروش ' + persianNum(days) + ' روز اخیر';
                    setActive(null);
                } else {
                    titleEl.textContent = TITLES[range] || titleEl.textContent;
                    setActive(range);
                }
                renderChart();
            })
            .catch(function (err) {
                console.error('chart fetch failed:', err);
                titleEl.textContent = 'خطا در بارگذاری نمودار';
            })
            .finally(function () {
                loadingEl.classList.remove('show');
            });
    }


    window.addEventListener('load', function () { renderChart(); });


    rangeBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            var r = b.dataset.range;
            if (r === currentRange) return;
            fetchRange(r);
        });
    });


    customForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var n = parseInt(customInput.value, 10);
        if (!n || n < 1) return;
        if (n > 365) n = 365;
        fetchRange('custom', n);
    });


    document.addEventListener('faoxima:themechange', function () {
        if (typeof FaoximaChart !== 'undefined') renderChart();
    });
})();
</script>
<?php endif; ?>

</body>
</html>


