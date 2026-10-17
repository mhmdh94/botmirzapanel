# botmirzapanel (فورک mhmdh94)

ربات فروشگاهی VPN روی میرزا + **PasarGuard/مرزبان** با امکانات اضافه این فورک.

## نصب / آپدیت

```bash
bash <(curl -sSL https://raw.githubusercontent.com/mhmdh94/botmirzapanel/main/install.sh)
```

**آپدیت دستی:** بک‌آپ `config.php` و دیتابیس → فقط فایل‌های PHP را جایگزین کن → **`config.php` را عوض نکن**.

### فایل‌های مهم
`index.php` · `admin.php` · `functions.php` · `text.php` · `keyboard.php` · `pasarguard.php` · `reseller_volume.php` · `cron/smart_cron.php` · `cron/croncard.php` · `table.php`

---

## امکانات فورک

| بخش | خلاصه |
|-----|--------|
| **پرداخت** | حداقل/حداکثر واریز، پکیج تخفیف‌دار، کریپتو، قفل اتمیک رسید، برگشت وجه در خطای ساخت |
| **سرویس** | جستجو، فعال/غیرفعال، تمدید، حجم اضافه، نام خودکار، راهنمای Happ |
| **کرون هوشمند** | اخطار حجم/زمان/اتمام، تمدید اضطراری، حذف فقط فاکتور غایب (نه یوزر پنل) |
| **پورسانت** | تنظیمات یکپارچه + لیست کاربران فعال در طرح |
| **پنل نمایندگان** | جدا از فروش اکانت؛ خرید حجم ادمین؛ هشدار آستانه (ربات یا دستی پنل) — پیش‌فرض خاموش |
| **گزارش / امنیت** | کانال گزارش قابل تنظیم، ضد اسپم، قابلیت‌های خاموش‌روشن منو |

### نمونه crontab
```cron
*/5 * * * * php /var/www/html/YOURBOT/cron/smart_cron.php >/dev/null 2>&1
*/3 * * * * php /var/www/html/YOURBOT/cron/croncard.php >/dev/null 2>&1
```
کرون‌های قدیمی (`removeexpire` و …) را اگر smart دارید خاموش کنید.

---

## بعد از دیپلوی
`/start` · شارژ + تأیید رسید · خرید/تمدید · در صورت نیاز روشن کردن ریسلر از قابلیت‌ها

**ریپو:** https://github.com/mhmdh94/botmirzapanel  
