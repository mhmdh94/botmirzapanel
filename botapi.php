<?php
function telegram($method, $datas = [])
{
    global $APIKEY;
    $url = "https://api.telegram.org/bot" . $APIKEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $raw = curl_exec($ch);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($cerr) {
        // SSL / timeoutهای شبکه را لاگ نکن (تکراری و بی‌فایده)
        $c = strtolower($cerr);
        if (strpos($c, 'ssl') === false && strpos($c, 'timeout') === false && strpos($c, 'timed out') === false) {
            error_log($cerr);
        }
        return false;
    }
    $res = json_decode($raw, true);
    if (!is_array($res)) {
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
            'bot can\'t initiate conversation',
            'GROUP_DEACTIVATED',
        ];
        $skip_log = false;
        foreach ($ignore as $needle) {
            if ($desc !== '' && stripos($desc, $needle) !== false) {
                $skip_log = true;
                break;
            }
        }
        if (!$skip_log) {
            error_log(json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
    return $res;
}

/**
 * ارسال به تلگرام با تلاش مجدد برای خطاهای موقت شبکه/سرور
 * @return array|false
 */
function telegramRetry($method, $datas = [], $maxAttempts = 3)
{
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
        // خطاهای دائمی — تکرار بی‌فایده
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
        $isPermanent = false;
        foreach ($permanent as $p) {
            if ($desc !== '' && strpos($desc, $p) !== false) {
                $isPermanent = true;
                break;
            }
        }
        if ($isPermanent) {
            return $last;
        }
        // Too Many Requests — صبر بیشتر
        $wait = 1;
        if (is_array($last) && isset($last['parameters']['retry_after'])) {
            $wait = min(10, max(1, intval($last['parameters']['retry_after'])));
        } elseif ($desc !== '' && (strpos($desc, 'too many') !== false || strpos($desc, 'retry') !== false)) {
            $wait = 2 * $attempt;
        } else {
            $wait = $attempt; // 1s, 2s, 3s
        }
        if ($attempt < $maxAttempts) {
            @sleep($wait);
        }
    }
    return $last;
}

/** آیا پاسخ تلگرام موفق بوده؟ */
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
        $res = telegramRetry($method, $data, 3);
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
    return telegram('deletemessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
    ]);
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
$from_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? 0;
$Chat_type = $update["message"]["chat"]["type"] ?? $update['callback_query']['message']['chat']['type'] ?? '';
$text = $update["message"]["text"] ?? '';
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
