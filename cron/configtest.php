<?php
/**
 * کرون اتمام اکانت تست
 * - فقط یک‌بار پیام می‌فرستد (با قفل اتمیک روی status)
 * - اگر چند کرون همزمان اجرا شود، فقط یکی پیام می‌دهد
 */
date_default_timezone_set('Asia/Tehran');
require_once '../config.php';
require_once '../botapi.php';
require_once '../panels.php';
require_once '../functions.php';
require_once '../text.php';

$ManagePanel = new ManagePanel();

// فقط فاکتورهای تست هنوز active (حداکثر ۱۰ تا در هر اجرا)
$stmt = $pdo->prepare("SELECT * FROM invoice WHERE (status = 'active' OR Status = 'active') AND name_product = 'usertest' ORDER BY id_invoice ASC LIMIT 10");
try {
    $stmt->execute();
} catch (Throwable $e) {
    // fallback اگر ستون Status وجود نداشته باشد
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE status = 'active' AND name_product = 'usertest' ORDER BY time_sell ASC LIMIT 10");
    $stmt->execute();
}

while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $resultt = trim(strval($result['username'] ?? ''));
    $uid = intval($result['id_user'] ?? 0);
    if ($resultt === '' || $uid <= 0) {
        continue;
    }

    $marzban_list_get = select("marzban_panel", "*", "name_panel", $result['Service_location'], "select");
    if ($marzban_list_get == false) {
        continue;
    }

    $get_username_Check = $ManagePanel->DataUser($result['Service_location'], $result['username']);
    if (!is_array($get_username_Check)) {
        continue;
    }

    $panel_status = strval($get_username_Check['status'] ?? '');
    // هنوز روی پنل فعال است → پیام نده
    if (in_array($panel_status, ['active', 'on_hold', 'Unsuccessful', 'disabled'], true)) {
        continue;
    }

    // قفل اتمیک: فقط اگر هنوز active باشد به sendedwarn تغییر بده
    // اگر ردیف قبلاً توسط کرون دیگر قفل شده، rowCount=0 و پیام تکراری ارسال نمی‌شود
    $locked = false;
    try {
        $lock = $pdo->prepare("UPDATE invoice SET status = 'sendedwarn' WHERE username = :u AND name_product = 'usertest' AND (status = 'active' OR Status = 'active')");
        $lock->execute([':u' => $resultt]);
        $locked = ($lock->rowCount() > 0);
    } catch (Throwable $e) {
        try {
            $lock = $pdo->prepare("UPDATE invoice SET status = 'sendedwarn' WHERE username = :u AND name_product = 'usertest' AND status = 'active'");
            $lock->execute([':u' => $resultt]);
            $locked = ($lock->rowCount() > 0);
        } catch (Throwable $e2) {
            $locked = false;
        }
    }

    if (!$locked) {
        // قبلاً پیام داده شده یا توسط کرون موازی گرفته شده
        continue;
    }

    // حذف از پنل (اختیاری؛ خطا مانع پیام نمی‌شود چون قفل شده)
    try {
        $ManagePanel->RemoveUser($result['Service_location'], $resultt);
    } catch (Throwable $e) {
        // ignore
    }

    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['cron']['textbuy'], 'callback_data' => 'buy'],
            ],
        ],
    ]);
    $textexpire = sprintf($textbotlang['users']['cron']['crontest'], $resultt);
    sendmessage($uid, $textexpire, $Response, 'HTML');
}
