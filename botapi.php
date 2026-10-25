<?php
/**
 * لاگ خطا در فایل error_log کنار ربات + error_log سیستم
 */
function botErrorLog($message, array $ctx = [])
{
    $line = '[' . date('Y-m-d H:i:s') . ' Asia/Tehran] ' . strval($message);
    if (!empty($ctx)) {
        $safe = $ctx;
        // توکن را لاگ نکن
        unset($safe['token'], $safe['APIKEY']);
        $line .= ' | ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= "\n";
    @error_log(rtrim($line));
    $paths = [];
    if (defined('__DIR__')) {
        $paths[] = __DIR__ . '/error_log';
        $paths[] = dirname(__DIR__) . '/error_log';
    }
    $paths[] = (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/error_log' : null);
    foreach ($paths as $p) {
        if (!$p) {
            continue;
        }
        $dir = dirname($p);
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($p, $line, FILE_APPEND | LOCK_EX);
            break;
        }
        if (file_exists($p) && is_writable($p)) {
            @file_put_contents($p, $line, FILE_APPEND | LOCK_EX);
            break;
        }
    }
}

function telegram($method, $datas = [])
{
    global $APIKEY;
    $method = strval($method);
    $url = "https://api.telegram.org/bot" . $APIKEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $t0 = microtime(true);
    $raw = curl_exec($ch);
    $cerr = curl_error($ch);
    $errno = curl_errno($ch);
    $http = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);

    $chat = '';
    if (is_array($datas)) {
        if (isset($datas['chat_id'])) {
            $chat = strval($datas['chat_id']);
        }
    }

    if ($cerr) {
        // همه خطاهای شبکه (timeout/ssl/resolve/...) ثبت شوند
        if (function_exists('botErrorLog')) {
            botErrorLog('NET telegram failed', [
                'method' => $method,
                'chat_id' => $chat,
                'curl_errno' => $errno,
                'curl_error' => $cerr,
                'http' => $http,
                'ms' => $ms,
            ]);
        } else {
            error_log("NET telegram {$method}: [{$errno}] {$cerr}");
        }
        return false;
    }

    $res = json_decode($raw, true);
    if (!is_array($res)) {
        if (function_exists('botErrorLog')) {
            botErrorLog('NET telegram invalid JSON', [
                'method' => $method,
                'chat_id' => $chat,
                'http' => $http,
                'ms' => $ms,
                'raw_len' => is_string($raw) ? strlen($raw) : 0,
                'raw_head' => is_string($raw) ? substr($raw, 0, 200) : '',
            ]);
        }
        return false;
    }

    if (empty($res['ok'])) {
        $desc = isset($res['description']) ? (string) $res['description'] : '';
        $ignore = [
            'chat not found',
            'bot was blocked by the user',
            'user is deactivated',
            'PEER_ID_INVALID',
            'message is not modified',
            'query is too old',
            'response timeout expired',
            'have no rights to send a message',
            'need administrator rights',
            'Forbidden: bot was blocked',
            "bot can't initiate conversation",
            'GROUP_DEACTIVATED',
            "message can't be deleted for everyone",
            'message to delete not found',
            'message to edit not found',
        ];
        $skip_log = false;
        foreach ($ignore as $needle) {
            if ($desc !== '' && stripos($desc, $needle) !== false) {
                $skip_log = true;
                break;
            }
        }
        if (!$skip_log) {
            if (function_exists('botErrorLog')) {
                botErrorLog('API telegram error', [
                    'method' => $method,
                    'chat_id' => $chat,
                    'error_code' => $res['error_code'] ?? null,
                    'description' => $desc,
                    'http' => $http,
                    'ms' => $ms,
                ]);
            } else {
                error_log(json_encode($res, JSON_UNESCAPED_UNICODE));
            }
        }
    }
    return $res;
}

function telegramRetry($method, $datas = [], $maxAttempts = 2)
{
    // حداکثر ۲ تلاش — بدون sleep چندثانیه‌ای (جلوگیری از فریز PHP-FPM)
    $maxAttempts = max(1, min(3, intval($maxAttempts)));
    $last = false;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $last = telegram($method, $datas);
        if (is_array($last) && !empty($last['ok'])) {
            return $last;
        }
        $desc = '';
        if (is_array($last) && isset($last['description'])) {
            $desc = mb_strtolower(strval($last['description']), 'UTF-8');
        }
        $permanent = [
            'chat not found',
            'bot was blocked',
            'user is deactivated',
            'peer_id_invalid',
            'bot can\'t initiate conversation',
            'forbidden: bot was blocked',
            'have no rights',
            'need administrator rights',
            'group_deactivated',
            'chat_id is empty',
        ];
        foreach ($permanent as $p) {
            if ($desc !== '' && strpos($desc, $p) !== false) {
                return $last;
            }
        }
        if ($attempt >= $maxAttempts) {
            break;
        }
        // حداکثر ~0.4s — sleep ثانیه‌ای در webhook ممنوع
        $us = 200000;
        if (is_array($last) && isset($last['parameters']['retry_after'])) {
            $us = min(800000, max(200000, intval($last['parameters']['retry_after']) * 150000));
        }
        @usleep($us);
    }
    return $last;
}

function telegramOk($res)
{
    return is_array($res) && !empty($res['ok']);
}

/**
 * ارسال پیام/عکس به همه ادمین‌ها با retry
 * $payload بدون chat_id (یا با chat_id که جایگزین می‌شود)
 * @return array{ok:int,fail:int,results:array}
 */
function notifyAdmins($method, $payload, $admins = null)
{
    global $admin_ids;
    if ($admins === null) {
        $admins = $admin_ids ?? [];
    }
    if (!is_array($admins)) {
        $admins = $admins ? [$admins] : [];
    }
    $admins = array_values(array_unique(array_filter(array_map('intval', $admins))));
    $ok = 0;
    $fail = 0;
    $results = [];
    foreach ($admins as $aid) {
        if ($aid <= 0) {
            continue;
        }
        $data = $payload;
        $data['chat_id'] = $aid;
        $res = telegramRetry($method, $data, 2);
        if (telegramOk($res)) {
            $ok++;
        } else {
            $fail++;
            $results[$aid] = is_array($res) ? ($res['description'] ?? 'fail') : 'false';
        }
    }
    if ($ok === 0 && $fail > 0) {
        error_log('notifyAdmins all failed method=' . $method . ' errs=' . json_encode($results, JSON_UNESCAPED_UNICODE));
    }
    return ['ok' => $ok, 'fail' => $fail, 'results' => $results];
}

function sendmessage($chat_id, $text, $keyboard, $parse_mode)
{
    return telegram('sendmessage', [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
    ]);
}
function forwardMessage($chat_id, $message_id, $chat_id_user)
{
    return telegram('forwardMessage', [
        'from_chat_id' => $chat_id,
        'message_id' => $message_id,
        'chat_id' => $chat_id_user,
    ]);
}
function sendphoto($chat_id, $photoid, $caption, $parse_mode = "HTML")
{
    return telegram('sendphoto', [
        'chat_id' => $chat_id,
        'photo' => $photoid,
        'caption' => $caption,
        'parse_mode' => $parse_mode,
    ]);
}
function sendvideo($chat_id, $videoid, $caption)
{
    return telegram('sendvideo', [
        'chat_id' => $chat_id,
        'video' => $videoid,
        'caption' => $caption,
    ]);
}
function Editmessagetext($chat_id, $message_id, $text, $keyboard, $parse_mode = "html")
{
    return telegram('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
    ]);
}
function deletemessage($chat_id, $message_id)
{
    if (empty($chat_id) || empty($message_id)) {
        return false;
    }
    // خطای «message can't be deleted for everyone» عادی است (پیام قدیمی/بدون دسترسی)
    $res = telegram('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ]);
    return $res;
}
function sendDocument($chat_id, $documentPath, $caption)
{
    return telegram('sendDocument', [
        'chat_id' => $chat_id,
        'document' => new CURLFile($documentPath),
        'caption' => $caption,
    ]);
}
#-----------------------------#
$update = json_decode(file_get_contents("php://input"), true);

// ثبت خطای Fatal که باعث می‌شود ربات جواب ندهد
if (!defined('BOT_SHUTDOWN_LOG')) {
    define('BOT_SHUTDOWN_LOG', true);
    register_shutdown_function(function () {
        $e = error_get_last();
        if (!$e) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($e['type'], $fatal, true)) {
            return;
        }
        $msg = 'FATAL ' . $e['message'] . ' in ' . ($e['file'] ?? '') . ':' . ($e['line'] ?? 0);
        if (function_exists('botErrorLog')) {
            botErrorLog($msg, [
                'from_id' => $GLOBALS['from_id'] ?? null,
                'text' => isset($GLOBALS['text']) ? substr(strval($GLOBALS['text']), 0, 80) : null,
                'datain' => isset($GLOBALS['datain']) ? substr(strval($GLOBALS['datain']), 0, 80) : null,
            ]);
        } else {
            @error_log($msg);
        }
    });
}

$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? 0;
$GLOBALS['from_id'] = $from_id;
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"] ?? '';
# نرمال‌سازی دستورات منوی تلگرام: /new@MyBot → /new
if (is_string($text) && strpos($text, '/') === 0) {
    $__parts = explode(' ', $text, 2);
    if (strpos($__parts[0], '@') !== false) {
        $__parts[0] = explode('@', $__parts[0], 2)[0];
    }
    $text = isset($__parts[1]) ? ($__parts[0] . ' ' . $__parts[1]) : $__parts[0];
}
$text_callback = $update["callback_query"]["message"]["text"] ?? '';
$message_id = $update["message"]["message_id"] ?? $update["callback_query"]["message"]["message_id"] ?? 0;
$photo = $update["message"]["photo"] ?? 0;
$photoid = $photo ? end($photo)["file_id"] : '';
$caption = $update["message"]["caption"] ?? $update['callback_query']['message']["caption"] ?? '';
$video = $update["message"]["video"] ?? 0;
$videoid = $video ? $video["file_id"] : 0;
$forward_from_id = $update["message"]["reply_to_message"]["forward_from"]["id"] ?? 0;
$datain = $update["callback_query"]["data"] ?? '';
$username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? 'NOT_USERNAME';
$user_phone = $update["message"]["contact"]["phone_number"] ?? 0;
$contact_id = $update["message"]["contact"]["user_id"] ?? 0;
$first_name = $update['message']['from']['first_name'] ?? $update["callback_query"]["from"]["first_name"] ?? '';
$callback_query_id = $update["callback_query"]["id"] ?? 0;
