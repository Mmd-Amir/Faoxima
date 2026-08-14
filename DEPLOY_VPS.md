# راهنمای استقرار فاکسیما روی سرور مجازی (VPS) — نسخه‌ی وب‌محور

فاکسیما در این نسخه **بدون ربات تلگرام** است؛ فروش، مدیریت و نمایندگی همگی از طریق
پنل وب انجام می‌شود. این راهنما مخصوص اجرای نسخه‌ی وب‌محور روی یک VPS است.

> پیش‌نیازها: سرور اوبونتو/دبیان با دسترسی root، دامنه (HTTPS الزامی است)،
> پورت‌های 80 و 443 باز.

---

## ۱) نصب پیش‌نیازها

```bash
apt update && apt install -y nginx mariadb-server php8.2-fpm php8.2-mysql \
  php8.2-curl php8.2-gd php8.2-zip php8.2-intl php8.2-mbstring php8.2-sodium \
  php8.2-xml php8.2-bcmath php8.2-cli unzip curl
```

> اگر PHP 8.2 در مخزن پیش‌فرض نیست، از مخزن ondrej استفاده کنید:
> `add-apt-repository ppa:ondrej/php`

## ۲) نصب 3x-ui (پنل سرویس)

```bash
bash <(curl -Ls https://raw.githubusercontent.com/mhsanaei/3x-ui/master/install.sh)
```

بعد از نصب، در پنل 3x-ui:

1. یک یا چند **Inbound** با پروتکل دلخواه (مثلاً VLESS + Reality) بسازید.
2. از مسیر **Settings ← Security ← API Token** یک توکن API بسازید و کپی کنید.
   (این توکن برای اتصال فاکسیما به 3x-ui نسخه‌های جدید ضروری است؛ ورود با
   یوزرنیم/پسورد روی 3x-ui نسخه‌ی 3.1 به بالا دیگر برای ساخت کاربر کار نمی‌کند.)
3. در **Subscription Settings** لینک ساب‌اسکریپشن پنل را فعال/یادداشت کنید.

## ۳) نصب فاکسیما

```bash
mkdir -p /var/www/faoxima && cd /var/www/faoxima
# آپلود یا clone سورس در همین مسیر (بدون پوشه‌ی installer در حالت نهایی)
composer install --no-dev --no-interaction --prefer-dist
```

- به فایل `config.php` اطلاعات دیتابیس MySQL را بدهید (جدول‌ها هنگام اولین اجرا
  به‌صورت خودکار ساخته می‌شوند).
- سپس `https://your-domain/installer` را باز کنید و مراحل نصب وب را تکمیل کنید.
- **بعد از نصب، پوشه‌ی `installer` را حذف کنید:**
  ```bash
  rm -rf /var/www/faoxima/installer
  ```

### اتصال به 3x-ui در پنل ادمین فاکسیما

در پنل ادمین ← **پنل‌ها** یک پنل از نوع **x-ui_single (Sanaei single)** بسازید:

| فیلد | مقدار |
|------|-------|
| آدرس پنل | `https://your-vps:port/` (مسیر پنل 3x-ui) |
| یوزرنیم/پسورد | اختیاری (برای حالت قدیمی) |
| توکن API | توکنی که از 3x-ui گرفتید (**حالت توکنی — پیشنهادی**) |
| شناسه اینباند | شناسه عددی Inbound ساخته‌شده در 3x-ui |
| لینک ساب | آدرس ساب‌اسکریپشن 3x-ui (مثلاً `https://your-vps:port/sub/`) |

با دکمه‌ی «تست» اتصال را بررسی کنید.

## ۴) کانفیگ Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    root /var/www/faoxima;
    index index.php;

    # ssl_certificate / ssl_certificate_key — از certbot

    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~* ^/(vendor|storage)/ {
        deny all;
        return 404;
    }
}
```

SSL:

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d your-domain.com
```

## ۵) کرون جاب (اجباری)

فاکسیما برای کارهای دوره‌ای (بررسی انقضا سرویس‌ها، اعلان‌ها، وضعیت پنل‌ها) به یک
کرون نیاز دارد. در `crontab -e`:

```cron
* * * * * php /var/www/faoxima/cron/cron.php >/dev/null 2>&1
```

می‌توانید برای تشخیص مشکلات، خروجی را در یک فایل لاگ بگیرید:

```cron
* * * * * php /var/www/faoxima/cron/cron.php >> /var/www/faoxima/logs/cron.log 2>&1
```

## ۶) بهینه‌سازی PHP-FPM و OPcache

فایل `/etc/php/8.2/fpm/php.ini`:

```ini
memory_limit = 256M
max_execution_time = 120
post_max_size = 32M
upload_max_filesize = 32M
date.timezone = Asia/Tehran

[opcache]
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
```

بعد از تغییر: `systemctl restart php8.2-fpm`

## ۷) امنیت و نکات

- **HTTPS الزامی است**؛ همه‌ی لینک‌ها و کال‌بک‌های درگاه پرداخت باید https باشند.
- پوشه‌های `installer`، `vendor` و `storage` نباید از بیرون قابل دسترسی باشند.
- پورت پنل 3x-ui را به‌جز از IP خودتان ببندید (یا فقط روی IP سرور فاکسیما باز باشد):
  ```bash
  ufw allow from YOUR_IP to any port 2053 proto tcp
  ```
- برای سرورهای ایران، در صورت نیاز از خروجی پروکسی (HTTP/SOCKS) پیکربندی‌شده در
  پنل ادمین استفاده کنید تا ارتباط فاکسیما با 3x-ui و درگاه‌ها قطع نشود.
- بکاپ منظم دیتابیس:
  ```cron
  0 3 * * * mysqldump -u faoxima -p'PASSWORD' faoxima | gzip > /root/backups/faoxima_$(date +\%F).sql.gz
  ```
