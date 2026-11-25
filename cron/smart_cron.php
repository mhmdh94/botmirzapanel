<?php
/**
 * کرون هوشمند یکپارچه
 * - هیچ کاربری را از پنل پاک نمی‌کند
 * - حذف فاکتور از دیتابیس ربات فقط اگر پنل صریحاً User not found بدهد و گزینه مربوط روشن باشد
 * - اخطار حجم/زمان/اتمام فقط اگر همان گزینه در تنظیمات روشن باشد
 * - تمدید اضطراری یک‌بارمصرف (اگر روشن باشد)
 * - شامل فاکتورهای تست هم می‌شود
 */
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once '../config.php';
require_once '../botapi.php';
botRequireCronAccess();
require_once '../panels.php';
require_once '../functions.php';
require_once '../text.php';

function saveSmartCronCursor($value)
{
    global $pdo;
    $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = 'smart_cron_cursor_id'")
        ->execute([strval($value)]);
}

$ManagePanel = new ManagePanel();
ensureSmartCronSettings();
ensureSmartCronStateTable();
// نسخه 2.4.3: زمان آخرین اجرا (برای نمایش در پنل)
@file_put_contents(__DIR__ . '/smart_cron.last', strval(time()));
// نسخه 2.4.6: چرخش لاگ کرون بالای ۵ مگابایت
if (is_file(__DIR__ . '/error_log') && @filesize(__DIR__ . '/error_log') > 5242880) {
    @rename(__DIR__ . '/error_log', __DIR__ . '/error_log.1');
}
// نسخه 2.4.4: خلاصه روزانه کانال گزارش
if (function_exists('botDailySummaryTick')) {
    try {
        botDailySummaryTick();
    } catch (Throwable $e) {
        error_log('daily summary: ' . $e->getMessage());
    }
}

// نسخه 2.6.44: کش نسخه گیتهاب (هر ۱ ساعت) برای سبز شدن دکمه «آپدیت ربات»
if (function_exists('botRemoteVersionRefresh')) {
    try {
        botRemoteVersionRefresh(3600);
    } catch (Throwable $e) {
    }
}

// نسخه 2.6.41: حذف خودکار کدهای تخفیف منقضی
if (function_exists('botPurgeExpiredDiscounts')) {
    $n_dsc = botPurgeExpiredDiscounts();
    if ($n_dsc > 0 && function_exists('smartCronDebugLog')) {
        smartCronDebugLog("🏷 حذف {$n_dsc} کد تخفیف منقضی");
    }
}

// حذف خودکار فاکتورهای unpaid منقضی (TTL قابل تنظیم، پیش‌فرض ۴۵ دقیقه)
if (function_exists('cleanupExpiredUnpaidInvoices')) {
    $n_unpaid = cleanupExpiredUnpaidInvoices(null);
    if ($n_unpaid > 0 && function_exists('smartCronDebugLog')) {
        smartCronDebugLog("🧹 حذف {$n_unpaid} فاکتور unpaid منقضی");
    }
}


// نسخه 2.6.48: یادآوری رسید کارتبهکارتی که 30 دقیقه بررسی نشده
if (smartCronFlag('smart_rcpt_remind', '1') && function_exists('botReceiptRemindTick')) {
    $n_rr = botReceiptRemindTick();
    if ($n_rr > 0 && function_exists('smartCronDebugLog')) {
        smartCronDebugLog("🔔 یادآوری رسید برای {$n_rr} کاربر ارسال شد");
    }
}

$limit = intval(getPaySettingValue('smart_batch_limit', '20'));
if ($limit < 1) {
    $limit = 20;
}
if ($limit > 200) {
    $limit = 200;
}

// نشانگر بر اساس id_invoice (keyset) به جای OFFSET:
// اگر وسط اجرا فاکتوری حذف شود، ردیف‌های بعدی جابه‌جا نمی‌شوند و کاربری جا نمی‌ماند.
ensurePaySetting('smart_cron_cursor_id', '');
$cursor = strval(getPaySettingValue('smart_cron_cursor_id', ''));

$cnt_stmt = $pdo->query("SELECT COUNT(*) FROM invoice WHERE username IS NOT NULL AND username != ''");
$total = intval($cnt_stmt->fetchColumn());
if ($total <= 0) {
    saveSmartCronCursor('');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT * FROM invoice
     WHERE username IS NOT NULL AND username != '' AND id_invoice > ?
     ORDER BY id_invoice ASC
     LIMIT " . intval($limit)
);
$stmt->execute([$cursor]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0 && $cursor !== '') {
    // به انتهای جدول رسیدیم — دور بعدی از اول
    $cursor = '';
    $stmt->execute([$cursor]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$batch_count = count($rows);
$next_cursor = '';
if ($batch_count >= $limit) {
    $next_cursor = strval($rows[$batch_count - 1]['id_invoice']);
}
// فقط برای پیام دیباگ
$offset = $cursor === '' ? '(start)' : $cursor;
$next_offset = $next_cursor === '' ? '(start)' : $next_cursor;

$do_clean = smartCronFlag('smart_clean_missing', '0');
$do_vol = smartCronFlag('smart_warn_volume', '0');
$do_time = smartCronFlag('smart_warn_time', '0');
$do_exp = smartCronFlag('smart_warn_expired', '0');
$do_emg = smartCronFlag('smart_emergency', '0');
$do_tf = smartCronFlag('smart_test_followup', '0');
$do_og = smartCronFlag('smart_onhold_guide', '1');

// اگر هیچ‌کدام روشن نیست، فقط نشانگر را جلو ببر و خارج شو (بدون پیام)
if (!$do_clean && !$do_vol && !$do_time && !$do_exp && !$do_tf && !$do_og) {
    saveSmartCronCursor($next_cursor);
    exit;
}

foreach ($rows as $row) {
    $username = trim(strval($row['username'] ?? ''));
    $location = strval($row['Service_location'] ?? '');
    $uid = $row['id_user'] ?? null;
    if ($username === '' || $location === '' || !$uid) {
        continue;
    }

    $panel = select("marzban_panel", "*", "name_panel", $location, "select");
    if ($panel == false) {
        continue;
    }

    $dataUser = $ManagePanel->DataUser($location, $username);

    // --- فقط پاک‌سازی فاکتور غایب (بدون پیام اخطار حجم/زمان) ---
    if ($do_clean && isPanelUserMissing($dataUser)) {
        $del = $pdo->prepare("DELETE FROM invoice WHERE username = ? AND Service_location = ? LIMIT 1");
        $del->execute([$username, $location]);
        $pdo->prepare("DELETE FROM smart_cron_state WHERE username = ? AND service_location = ?")->execute([$username, $location]);
        smartCronDebugLog(
            "🗑 فاکتور حذف شد (یوزر در پنل نبود)\n"
            . "👤 یوزرنیم: <code>{$username}</code>
"
            . "📍 پنل: {$location}
"
            . "🆔 کاربر: <code>{$uid}</code>\n"
            . "🏷 محصول: " . strval($row['name_product'] ?? '-')
        );
        continue;
    }

    // همگام‌سازی Status حتی اگر اخطارها خاموش باشند (بعد از DataUser معتبر)
    if (!is_array($dataUser) || ($dataUser['status'] ?? '') === 'Unsuccessful') {
        continue;
    }

    // همیشه Status فاکتور را از پنل همگام کن (برای ایموجی لیست سرویس‌ها)
    // مستقل از روشن/خاموش بودن اخطارها — سرویس را در پنل دست نمی‌زند
    if (function_exists('applySmartCronInvoiceStatus')) {
        applySmartCronInvoiceStatus($row, $dataUser);
    }

    // اگر فقط clean روشن است و اخطارها خاموش‌اند، بعد از sync برو بعدی
    if (!$do_vol && !$do_time && !$do_exp && !$do_tf && !$do_og) {
        continue;
    }

    $state = getSmartCronState($username, $location);
    $status = strval($dataUser['status'] ?? '');
    $expire = intval($dataUser['expire'] ?? 0);
    $data_limit = floatval($dataUser['data_limit'] ?? 0);
    $used = floatval($dataUser['used_traffic'] ?? 0);

    $is_ended = in_array($status, ['expired', 'limited'], true)
        || ($expire > 0 && $expire <= time())
        || ($data_limit > 0 && $used >= $data_limit);

    // اخطار اتمام + دکمه تمدید (+ اضطراری اگر روشن و یک‌بار)
    // نسخه 2.6.48: سرویس پولی که 24 ساعت تا 7 روز بعد از خرید هنوز وصل نشده → یک بار راهنمای اتصال
    if ($do_og && $status === 'on_hold' && strval($row['name_product'] ?? '') !== 'usertest') {
        $__ts_raw = $row['time_sell'] ?? '';
        $__ts = (is_numeric($__ts_raw) && intval($__ts_raw) > 100000) ? intval($__ts_raw) : intval(strtotime(strval($__ts_raw)));
        $__age = $__ts > 0 ? time() - $__ts : 0;
        if ($__age >= 86400 && $__age <= 7 * 86400 && function_exists('botOnholdGuide') && botOnholdGuide($uid, $username, $location)) {
            smartCronDebugLog("📖 راهنمای اتصال ارسال شد (سرویس وصل نشده)\n👤 <code>{$username}</code> | 🆔 <code>{$uid}</code>");
        }
    }
    // نسخه 2.6.46: اکانت تست تمامشده → فقط پیام پیگیری خرید (نه اخطار تمدید)
    if (strval($row['name_product'] ?? '') === 'usertest' && $is_ended) {
        if ($do_tf && function_exists('botTestFollowup') && botTestFollowup($uid, $username, $location)) {
            smartCronDebugLog("🧪 پیام بعد از پایان تست ارسال شد\n👤 <code>{$username}</code> | 🆔 <code>{$uid}</code>");
        }
        continue;
    }
    if (!$do_vol && !$do_time && !$do_exp) {
        continue;
    }
    if ($is_ended) {
        if ($do_exp && intval($state['expired_notified']) === 0) {
            $show_emg = $do_emg && intval($state['emergency_used']) === 0;
            $msg = sprintf(
                $textbotlang['users']['cron']['service_ended'] ?? "⛔️ سرویس <code>%s</code> به پایان رسیده است.
برای ادامه استفاده تمدید کنید.",
                $username
            );
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, $show_emg), 'HTML');
            updateSmartCronState($username, $location, 'expired_notified', 1);
            smartCronDebugLog(
                "⛔️ اخطار اتمام سرویس ارسال شد
"
                . "👤 یوزرنیم: <code>{$username}</code>
"
                . "📍 پنل: {$location}
"
                . "🆔 کاربر: <code>{$uid}</code>"
                . ($show_emg ? "
🆘 دکمه تمدید اضطراری نمایش داده شد" : "")
            );
        }
        continue;
    }

    // اخطار زمان — فقط اگر روشن باشد
    if ($do_time && $expire > time()) {
        $days_left = floor(($expire - time()) / 86400);
        $levels = smartCronLevelsTime();
        $current_level = intval($state['warn_time_level']);
        $idx = 0;
        foreach ($levels as $i => $day_threshold) {
            if ($days_left <= $day_threshold) {
                $idx = $i + 1;
            }
        }
        if ($idx > $current_level && $idx > 0) {
            $msg = sprintf(
                $textbotlang['users']['cron']['warn_time'] ?? "⏰ از زمان سرویس <code>%s</code> حدود <b>%s</b> روز باقی مانده است.",
                $username,
                max(0, $days_left)
            );
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, false, true), 'HTML');
            updateSmartCronState($username, $location, 'warn_time_level', $idx);
            smartCronDebugLog(
                "⏰ اخطار زمان ارسال شد
"
                . "👤 <code>{$username}</code> | روز مانده: {$days_left} | مرحله: {$idx}"
            );
        }
    }

    // اخطار حجم — فقط اگر روشن باشد
    if ($do_vol && $data_limit > 0) {
        $pct = ($used / $data_limit) * 100.0;
        $levels = smartCronLevelsVolume();
        $current_level = intval($state['warn_vol_level']);
        $idx = 0;
        foreach ($levels as $i => $th) {
            if ($pct >= $th) {
                $idx = $i + 1;
            }
        }
        if ($idx > $current_level && $idx > 0) {
            $msg = sprintf(
                $textbotlang['users']['cron']['warn_volume'] ?? "📊 از حجم سرویس <code>%s</code> حدود <b>%s%%</b> مصرف شده است.",
                $username,
                number_format($pct, 1)
            );
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, false, true), 'HTML');
            updateSmartCronState($username, $location, 'warn_vol_level', $idx);
            smartCronDebugLog(
                "📊 اخطار حجم ارسال شد
"
                . "👤 <code>{$username}</code> | مصرف: " . number_format($pct, 1) . "% | مرحله: {$idx}"
            );
        }
    }
}

saveSmartCronCursor($next_cursor);
if (function_exists('smartCronDebugLog') && smartCronFlag('smart_debug', '0')) {
    smartCronDebugLog(
        "ℹ️ اجرای کرون هوشمند\n"
        . "📦 این اجرا: <b>{$batch_count}</b> | offset: <code>{$offset}</code> → <code>{$next_offset}</code>\n"
        . "📊 کل فاکتور: <code>{$total}</code> | لیمیت: <code>{$limit}</code>\n"
        . "⚙️ clean=" . ($do_clean ? 'on' : 'off')
        . " vol=" . ($do_vol ? 'on' : 'off')
        . " time=" . ($do_time ? 'on' : 'off')
        . " exp=" . ($do_exp ? 'on' : 'off')
    );
}

if (function_exists('processResellerVolumeWarnings')) {
    processResellerVolumeWarnings();
}
