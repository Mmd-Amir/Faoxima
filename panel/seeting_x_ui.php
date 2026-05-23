<?php
session_start();
require_once __DIR__ . '/../config.php';

function ensureXuiTableExists(PDO $pdo)
{
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'x_ui'");
        if ($result === false || $result->rowCount() === 0) {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `x_ui` (" .
                "`codepanel` VARCHAR(100) NOT NULL," .
                "`setting` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
                "`protocol` VARCHAR(100) DEFAULT NULL," .
                "PRIMARY KEY (`codepanel`)" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (PDOException $e) {
        error_log('Failed to ensure x_ui table: ' . $e->getMessage());
        throw $e;
    }
}

ensureXuiTableExists($pdo);

function synchronizeXuiPanels(PDO $pdo)
{
    try {
        $panels = $pdo->query("SELECT code_panel FROM marzban_panel");
        if ($panels !== false) {
            $insert = $pdo->prepare("INSERT INTO x_ui (codepanel, setting) VALUES (:codepanel, '{}') ON DUPLICATE KEY UPDATE codepanel = codepanel");
            while ($panel = $panels->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($panel['code_panel'])) {
                    $insert->execute([':codepanel' => $panel['code_panel']]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Failed to synchronize x_ui table with marzban_panel: ' . $e->getMessage());
        throw $e;
    }
}

synchronizeXuiPanels($pdo);

function update($table, $field, $newValue, $whereField = null, $whereValue = null) {
    global $pdo,$user;

    if ($whereField !== null) {
        $stmt = $pdo->prepare("SELECT $field FROM $table WHERE $whereField = ? FOR UPDATE");
        $stmt->execute([$whereValue]);
        $currentValue = $stmt->fetchColumn();
        $stmt = $pdo->prepare("UPDATE $table SET $field = ? WHERE $whereField = ?");
        $stmt->execute([$newValue, $whereValue]);
    } else {
        $stmt = $pdo->prepare("UPDATE $table SET $field = ?");
        $stmt->execute([$newValue]);
    }
    $date = date("Y-m-d");
    $logss = "{$table}_{$field}_{$newValue}_{$whereField}_{$whereValue}_{$user['step']}_$date";
    if($field != "message_count" || $field != "last_message_time"){
        file_put_contents('log.txt',"\n".$logss,FILE_APPEND);
    }
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

$query = $pdo->prepare("SELECT code_panel, name_panel FROM marzban_panel ORDER BY name_panel");
$query->execute();
$resultpanel = $query->fetchAll(PDO::FETCH_ASSOC);

if( !isset($_SESSION["user"]) || !$result ){
    header('Location: login.php');
    return;
}

$action = $_GET['action'] ?? '';
$selectedPanelCode = '';
$settingsValue = '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $selectedPanelCode = $_POST['namepanel'] ?? '';
    $settingsValue = $_POST['settings'] ?? '';
    if ($selectedPanelCode !== '') {
        $stmt = $pdo->prepare("SELECT 1 FROM x_ui WHERE codepanel = :codepanel");
        $stmt->execute([':codepanel' => $selectedPanelCode]);
        if ($stmt->fetchColumn() === false) {
            $insert = $pdo->prepare("INSERT INTO x_ui (codepanel, setting) VALUES (:codepanel, :setting)");
            $insert->execute([
                ':codepanel' => $selectedPanelCode,
                ':setting' => $settingsValue
            ]);
        } else {
            $updateStmt = $pdo->prepare("UPDATE x_ui SET setting = :setting WHERE codepanel = :codepanel");
            $updateStmt->execute([
                ':setting' => $settingsValue,
                ':codepanel' => $selectedPanelCode
            ]);
        }
    }
    header('Location: seeting_x_ui.php');
    exit;
}

if ($action === 'change' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPanelCode = $_POST['namepanel'] ?? '';
}

$sanitizedPanelCode = htmlspecialchars($selectedPanelCode, ENT_QUOTES, 'UTF-8');

if ($action === 'change' && $selectedPanelCode !== '') {
    $stmt = $pdo->prepare("SELECT setting FROM x_ui WHERE codepanel=:codepanel");
    $stmt->execute([':codepanel' => $selectedPanelCode]);
    $getsetting = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($getsetting && isset($getsetting['setting'])) {
        $decoded = json_decode($getsetting['setting'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $settingsValue = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $settingsValue = $getsetting['setting'];
        }
    }
}

$settingsValueForTextarea = htmlspecialchars($settingsValue, ENT_NOQUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-color="blue" data-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات X-UI — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="js/theme.js" defer>

</script>
    <style>
      .xui-form { display: grid; gap: 14px; }
      .xui-field { display: grid; gap: 6px; }
      .xui-field label {
        font-size: 12px;
        color: var(--text-muted);
        font-weight: 600;
      }
      .xui-field label::before {
        content: "> ";
        color: var(--accent);
      }
      .xui-input,
      .xui-select,
      .xui-textarea {
        width: 100%;
        padding: 10px 12px;
        background: var(--surface-1);
        border: 1px solid var(--border-mid);
        border-radius: 8px;
        color: var(--text-main);
        font-family: 'Arad', system-ui, sans-serif;
        font-size: 13px;
        transition: border-color .15s ease;
      }
      .xui-textarea {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        direction: ltr;
        text-align: left;
        line-height: 1.6;
        min-height: 380px;
        resize: vertical;
      }
      .xui-input:focus, .xui-select:focus, .xui-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
      }
      .xui-actions { display: flex; gap: 10px; margin-top: 6px; flex-wrap: wrap; }
      .xui-preset-row {
        display: flex; gap: 10px; align-items: center; margin-bottom: 12px; flex-wrap: wrap;
      }
      .xui-preset-row label { color: var(--text-muted); font-size: 13px; }
      .xui-preset-row select { max-width: 280px; }
    </style>
  </head>
  <body>

  <section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
      <section class="wrapper">
        <div class="page-head">
          <div>
            <div class="page-head__title">
              <span class="symbol">[</span>
              تنظیمات کانفیگ X-UI
            </div>
            <div class="page-head__sub">تعیین تنظیمات کانفیگ‌های ساخته‌شده در پنل x-ui</div>
          </div>
        </div>

        <?php if($action != "change"){ ?>
          <div class="card">
            <div class="card__head">
              <div class="card__title"><span class="symbol">&gt;</span> انتخاب پنل</div>
              <span class="chip">step 1 / 2</span>
            </div>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 18px; line-height: 1.9;">
              <span style="color: var(--accent);">&gt;</span> در این صفحه می‌توانید تعیین کنید چه تنظیماتی برای کانفیگ ساخته شود در پنل x-ui.
              <br>
              <span style="color: var(--accent);">&gt;</span> ابتدا پنل مورد نظر را انتخاب کنید، سپس تنظیمات JSON را ویرایش نمایید.
            </p>

            <form class="xui-form" role="form" method="POST" action="seeting_x_ui.php?action=change">
              <div class="xui-field">
                <label>نام پنل</label>
                <select required name="namepanel" class="xui-select">
                  <option value="">انتخاب نشده</option>
                  <?php
                  if(!empty($resultpanel)){
                    foreach($resultpanel as $panel){
                      $optionValue = htmlspecialchars($panel['code_panel'], ENT_QUOTES, 'UTF-8');
                      $optionLabel = htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8');
                      $isSelected = $panel['code_panel'] === $selectedPanelCode ? ' selected' : '';
                      echo "<option value=\"{$optionValue}\"{$isSelected}>{$optionLabel}</option>";
                    }
                  }
                  ?>
                </select>
              </div>
              <div class="xui-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fa-solid fa-arrow-left"></i> ادامه و تغییر تنظیمات
                </button>
              </div>
            </form>
          </div>
        <?php } ?>

        <?php if($action == "change"){ ?>
          <div class="card">
            <div class="card__head">
              <div class="card__title"><span class="symbol">&lt;&gt;</span> ویرایش تنظیمات JSON</div>
              <span class="chip">step 2 / 2</span>
            </div>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
              <span style="color: var(--accent);">&gt;</span> یکی از پیش‌تنظیم‌های آماده را انتخاب کنید یا تنظیمات سفارشی خود را وارد نمایید.
            </p>

            <div class="xui-preset-row">
              <label><i class="fa-solid fa-bolt"></i> تنظیمات آماده:</label>
              <select id="mySelect" class="xui-select" onchange="updateTextarea()">
                <option value="">انتخاب کنید...</option>
                <option value="tcp_http">tcp + http</option>
                <option value="ws_tls">ws + tls</option>
                <option value="گزینه ۳">گزینه ۳</option>
              </select>
            </div>

            <form class="xui-form" role="form" method="POST" action="seeting_x_ui.php?action=save">
              <div class="xui-field">
                <label>تنظیمات (JSON)</label>
                <textarea id="settings" name="settings" class="xui-textarea" rows="22"><?php echo $settingsValueForTextarea; ?></textarea>
                <input name="namepanel" type="hidden" value="<?php echo $sanitizedPanelCode; ?>">
              </div>
              <div class="xui-actions">
                <button type="submit" class="btn btn-primary">
                  <i class="fa-solid fa-floppy-disk"></i> ذخیره
                </button>
                <a href="seeting_x_ui.php" class="btn btn-outline">
                  <i class="fa-solid fa-xmark"></i> انصراف
                </a>
              </div>
            </form>
          </div>
        <?php } ?>
      </section>
    </section>
  </section>

<script>
  function updateTextarea() {
    var selectElement = document.getElementById("mySelect");
    var textareaElement = document.getElementById("settings");
    var selectedOption = selectElement.options[selectElement.selectedIndex].value;
    if (selectedOption === "tcp_http") {
        selectedOption = `{
  "network": "tcp",
  "security": "none",
  "externalProxy": [],
  "tcpSettings": {
    "acceptProxyProtocol": false,
    "header": {
      "type": "http",
      "request": {
        "version": "1.1",
        "method": "GET",
        "path": [
          "/"
        ],
        "headers": {
          "host": [
            "zula.ir"
          ]
        }
      },
      "response": {
        "version": "1.1",
        "status": "200",
        "reason": "OK",
        "headers": {}
      }
    }
  }
}`;
    } else if (selectedOption == "") {
        selectedOption =  `{
  "network": "ws",
  "security": "none",
  "externalProxy": [],
  "wsSettings": {
    "acceptProxyProtocol": false,
    "path": "/",
    "host": "",
    "headers": {}
  }
}`;
    } else if (selectedOption == "ws_tls") {
        selectedOption = `{
  "network": "ws",
  "security": "tls",
  "externalProxy": [],
  "tlsSettings": {
    "serverName": "sni.com",
    "minVersion": "1.2",
    "maxVersion": "1.3",
    "cipherSuites": "",
    "rejectUnknownSni": true,
    "certificates": [
      {
        "certificateFile": "",
        "keyFile": "",
        "ocspStapling": 3600
      }
    ],
    "alpn": [
      "h2",
      "http/1.1"
    ],
    "settings": {
      "allowInsecure": true,
      "fingerprint": ""
    }
  },
  "wsSettings": {
    "acceptProxyProtocol": false,
    "path": "/",
    "headers": {}
  }
}`;
    }

    textareaElement.value = selectedOption;
  }
</script>

</body>
</html>


