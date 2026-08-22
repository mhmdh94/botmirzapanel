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
require_once '../panels.php';
require_once '../functions.php';
require_once '../text.php';

$ManagePanel = new ManagePanel();
ensureSmartCronSettings();
ensureSmartCronStateTable();

$limit = intval(getPaySettingValue('smart_batch_limit', '20'));
if ($limit < 1) {
    $limit = 20;
}
if ($limit > 200) {
    $limit = 200;
}

ensurePaySetting('smart_cron_cursor', '0');
$offset = intval(getPaySettingValue('smart_cron_cursor', '0'));
if ($offset < 0 || $offset > 100000000) {
    $offset = 0;
}

// همه فاکتورهای دارای یوزرنیم (از جمله تست)
$status_sql = "username IS NOT NULL AND TRIM(username) != ''";

$cnt_stmt = $pdo->query("SELECT COUNT(*) FROM invoice WHERE username IS NOT NULL AND username != ''");
$total = intval($cnt_stmt->fetchColumn());
if ($total <= 0) {
    $pdo->prepare("UPDATE PaySetting SET ValuePay = '0' WHERE NamePay = 'smart_cron_cursor'")->execute();
    exit;
}
if ($offset >= $total) {
    $offset = 0;
}

$stmt = $pdo->prepare(
    "SELECT * FROM invoice
     WHERE username IS NOT NULL AND username != ''
     ORDER BY id_invoice ASC
     LIMIT " . intval($limit) . " OFFSET " . intval($offset)
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    $offset = 0;
    $stmt = $pdo->prepare(
        "SELECT * FROM invoice
         WHERE username IS NOT NULL AND username != ''
         ORDER BY id_invoice ASC
         LIMIT " . intval($limit) . " OFFSET 0"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$batch_count = count($rows);
$next_offset = $offset + $batch_count;
if ($batch_count < $limit || $next_offset >= $total) {
    $next_offset = 0;
}

$do_clean = smartCronFlag('smart_clean_missing', '0');
$do_vol = smartCronFlag('smart_warn_volume', '0');
$do_time = smartCronFlag('smart_warn_time', '0');
$do_exp = smartCronFlag('smart_warn_expired', '0');
$do_emg = smartCronFlag('smart_emergency', '0');

// اگر هیچ‌کدام روشن نیست، فقط نشانگر را جلو ببر و خارج شو (بدون پیام)
if (!$do_clean && !$do_vol && !$do_time && !$do_exp) {
    $upd = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = 'smart_cron_cursor'");
    $upd->execute([strval($next_offset)]);
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
            . "👤 یوزرنیم: <code>{$username}</code>\n"
            . "📍 پنل: {$location}\n"
            . "🆔 کاربر: <code>{$uid}</code>\n"
            . "🏷 محصول: " . strval($row['name_product'] ?? '-')
        );
        continue;
    }

    // اگر فقط clean روشن است و اخطارها خاموش‌اند، ادامه نده
    if (!$do_vol && !$do_time && !$do_exp) {
        continue;
    }

    if (!is_array($dataUser) || ($dataUser['status'] ?? '') === 'Unsuccessful') {
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
    if ($is_ended) {
        if ($do_exp && intval($state['expired_notified']) === 0) {
            $show_emg = $do_emg && intval($state['emergency_used']) === 0;
            $msg = sprintf(
                $textbotlang['users']['cron']['service_ended'] ?? "⛔️ سرویس <code>%s</code> به پایان رسیده است.\nبرای ادامه استفاده تمدید کنید.",
                $username
            );
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, $show_emg), 'HTML');
            updateSmartCronState($username, $location, 'expired_notified', 1);
            // وضعیت فاکتور تست را هم sendedwarn کن اگر ستون status دارد
            if (isset($row['status']) || isset($row['Status'])) {
                update("invoice", "status", "sendedwarn", "username", $username);
            }
            smartCronDebugLog(
                "⛔️ اخطار اتمام سرویس ارسال شد\n"
                . "👤 یوزرنیم: <code>{$username}</code>\n"
                . "📍 پنل: {$location}\n"
                . "🆔 کاربر: <code>{$uid}</code>"
                . ($show_emg ? "\n🆘 دکمه تمدید اضطراری نمایش داده شد" : "")
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
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, false), 'HTML');
            updateSmartCronState($username, $location, 'warn_time_level', $idx);
            smartCronDebugLog(
                "⏰ اخطار زمان ارسال شد\n"
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
            sendmessage($uid, $msg, buildServiceWarnKeyboard($username, false), 'HTML');
            updateSmartCronState($username, $location, 'warn_vol_level', $idx);
            smartCronDebugLog(
                "📊 اخطار حجم ارسال شد\n"
                . "👤 <code>{$username}</code> | مصرف: " . number_format($pct, 1) . "% | مرحله: {$idx}"
            );
        }
    }
}

$upd = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = 'smart_cron_cursor'");
$upd->execute([strval($next_offset)]);
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
