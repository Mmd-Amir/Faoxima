<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'seeting_x_ui.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }

if (\Faoxima\Admin\Web\Pages\SeetingXUiRequest::handle(
        action: $action,
        pdo: $pdo ?? null,
        query: $query,
        result: $result,
        resultpanel: $resultpanel,
        sanitizedPanelCode: $sanitizedPanelCode,
        selectedPanelCode: $selectedPanelCode,
        settingsValueForTextarea: $settingsValueForTextarea,
        stmt: $stmt
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
    <title>تنظیمات X-UI — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="js/theme.js?v=dark62" defer>

</script>
    <style>
      .xui-form { display: grid; gap: var(--space-4); }
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
        border-radius: var(--radius-md);
        color: var(--text-main);
        font-family: 'Vazirmatn', system-ui, sans-serif;
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
      .xui-actions { display: flex; gap: var(--space-2); margin-top: var(--space-2); flex-wrap: wrap; }
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
      <section class="wrapper fx-page-settings-theme">
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
              <span class="text-accent">&gt;</span> در این صفحه می‌توانید تعیین کنید چه تنظیماتی برای کانفیگ ساخته شود در پنل x-ui.
              <br>
              <span class="text-accent">&gt;</span> ابتدا پنل مورد نظر را انتخاب کنید، سپس تنظیمات JSON را ویرایش نمایید.
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
              <span class="text-accent">&gt;</span> یکی از پیش‌تنظیم‌های آماده را انتخاب کنید یا تنظیمات سفارشی خود را وارد نمایید.
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


