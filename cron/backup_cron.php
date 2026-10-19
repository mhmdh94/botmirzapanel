<?php
/**
 * کرون بک‌آپ دیتابیس — فقط اگر backup_cron_enabled=1
 * مقصد: طبق تنظیمات backup_send_admin / backup_send_channel
 */
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../text.php';

if (!function_exists('ensureDatabaseBackupSettings')) {
    exit;
}
ensureDatabaseBackupSettings();
if (getPaySettingValue('backup_cron_enabled', '0') !== '1') {
    exit;
}

$to_admin = getPaySettingValue('backup_send_admin', '0') === '1';
$to_ch = getPaySettingValue('backup_send_channel', '0') === '1';
if (!$to_admin && !$to_ch) {
    // هیچ مقصدی روشن نیست
    exit;
}

if (!function_exists('runDatabaseBackupAndSend')) {
    exit;
}
$res = runDatabaseBackupAndSend(null, false);
if (empty($res['ok'])) {
    error_log('backup_cron: ' . strval($res['message'] ?? 'fail'));
}
