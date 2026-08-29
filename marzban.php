<?php
/**
 * پنل مرزبان
 * API با پاسارگارد سازگار است؛ منطق در pasarguard.php نگهداری می‌شود
 * تا تنظیمات و پنل‌های قبلی با type=marzban خراب نشوند.
 */
if (!function_exists('token_panel')) {
    require_once __DIR__ . '/pasarguard.php';
}
