<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once '../config.php';
require_once '../botapi.php';
botRequireCronAccess();
require_once '../functions.php';
require_once '../text.php';
// نسخه 2.3.9: صف ارسال همگانی (متن / عکس / فوروارد) + گزارش پیشرفت
if (!is_file('info') || !is_file('users.json')) return;
$__lock = @fopen(__DIR__ . '/sendmessage.lock', 'c');
if (!$__lock || !flock($__lock, LOCK_EX | LOCK_NB)) return;

$userid = json_decode(file_get_contents('users.json'), true);
$info = json_decode(file_get_contents('info'), true);
if (!is_array($info)) {
    @unlink('info');
    @unlink('users.json');
    return;
}
if (!is_array($userid)) {
    $userid = [];
}
$type = strval($info['type'] ?? 'text');
$admin = $info['id_admin'] ?? 0;
$okf = function ($r) {
    return function_exists('telegramOk') ? telegramOk($r) : (is_array($r) && !empty($r['ok']));
};
$batch = array_splice($userid, 0, 20);
foreach ($batch as $row) {
    $uid = is_array($row) ? ($row['id'] ?? 0) : (is_object($row) ? ($row->id ?? 0) : $row);
    if (!$uid) {
        continue;
    }
    if ($type === 'forward') {
        $r = telegram('forwardMessage', ['chat_id' => $uid, 'from_chat_id' => $info['from_chat'] ?? $admin, 'message_id' => intval($info['msg_id'] ?? 0)]);
    } elseif ($type === 'photo') {
        $r = telegram('sendphoto', ['chat_id' => $uid, 'photo' => $info['photo'] ?? '', 'caption' => strval($info['text'] ?? ''), 'parse_mode' => 'HTML']);
    } else {
        $r = telegram('sendmessage', ['chat_id' => $uid, 'text' => strval($info['text'] ?? ''), 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
    }
    if ($okf($r)) {
        $info['sent'] = intval($info['sent'] ?? 0) + 1;
    } else {
        $info['fail'] = intval($info['fail'] ?? 0) + 1;
    }
    usleep(50000);
}
file_put_contents('users.json', json_encode(array_values($userid)));
file_put_contents('info', json_encode($info, JSON_UNESCAPED_UNICODE));

$total = intval($info['total'] ?? 0);
$sent = intval($info['sent'] ?? 0);
$fail = intval($info['fail'] ?? 0);
$done = (count($userid) === 0);
$status = ($done ? "✅ <b>ارسال همگانی تمام شد</b>" : "📨 <b>در حال ارسال همگانی…</b>")
    . "\n\n👥 گیرندهها: <b>" . number_format($total) . "</b>"
    . "\n✅ ارسالشده: <b>" . number_format($sent) . "</b> | ❌ ناموفق: <b>" . number_format($fail) . "</b>"
    . ($done ? "\n\nℹ️ ناموفقها معمولاً کاربرانی هستند که ربات را بلاک کردهاند." : "\n⏳ باقیمانده: <b>" . number_format(count($userid)) . "</b>");
if (!empty($info['progress_mid']) && $admin) {
    $kb = $done ? json_encode(['inline_keyboard' => []]) : json_encode(['inline_keyboard' => [[['text' => 'لغو ارسال', 'callback_data' => 'cancel_sendmessage']]]], JSON_UNESCAPED_UNICODE);
    telegram('editMessageText', ['chat_id' => $admin, 'message_id' => intval($info['progress_mid']), 'text' => $status, 'parse_mode' => 'HTML', 'reply_markup' => $kb]);
} elseif ($done && $admin) {
    sendmessage($admin, $status, null, 'HTML');
}
if ($done) {
    @unlink('info');
    @unlink('users.json');
}
