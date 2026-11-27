<?php
/**
 * لاگ خطا در فایل error_log کنار ربات + error_log سیستم
 */
/**
 * لاگ یکپارچه ربات — همه بخش‌ها
 * سطح: ERROR | WARN | INFO | DEBUG
 * section: telegram | payment | panel | invoice | wallet | cron | admin | support | buy | renew | ...
 */
function botErrorLog($message, array $ctx = [], $level = 'ERROR', $section = '')
{
    $level = strtoupper(strval($level ?: 'ERROR'));
    $section = strval($section);
    if ($section === '' && isset($ctx['section'])) {
        $section = strval($ctx['section']);
        unset($ctx['section']);
    }
    // داده حساس را حذف کن
    $deny = ['token', 'APIKEY', 'api_key', 'password', 'password_panel', 'passwd', 'secret', 'Authorization', 'card_number', 'card'];
    $safe = [];
    foreach ($ctx as $k => $v) {
        $ks = strval($k);
        $kl = strtolower($ks);
        $skip = false;
        foreach ($deny as $d) {
            if ($kl === strtolower($d) || strpos($kl, 'password') !== false || strpos($kl, 'token') !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        if (is_object($v)) {
            if ($v instanceof Throwable) {
                $safe[$ks] = $v->getMessage();
            } else {
                $safe[$ks] = '[object ' . get_class($v) . ']';
            }
        } elseif (is_array($v)) {
            $safe[$ks] = $v;
        } elseif (is_bool($v)) {
            $safe[$ks] = $v ? true : false;
        } elseif (is_resource($v)) {
            $safe[$ks] = '[resource]';
        } else {
            $s = strval($v);
            if (strlen($s) > 2000) {
                $s = substr($s, 0, 2000) . '…';
            }
            $safe[$ks] = $s;
        }
    }
    // غنی‌سازی خودکار از گلوبال‌های ربات (اگر موجود باشند)
    foreach (['from_id' => 'uid', 'username' => 'tg_username', 'text' => 'user_text', 'datain' => 'callback'] as $g => $alias) {
        if (!isset($safe[$alias]) && isset($GLOBALS[$g]) && $GLOBALS[$g] !== '' && $GLOBALS[$g] !== null) {
            $val = $GLOBALS[$g];
            if (is_scalar($val)) {
                $s = strval($val);
                if ($alias === 'user_text' && strlen($s) > 120) {
                    $s = substr($s, 0, 120) . '…';
                }
                $safe[$alias] = $s;
            }
        }
    }
    if (!isset($safe['uri']) && !empty($_SERVER['REQUEST_URI'])) {
        $safe['uri'] = strval($_SERVER['REQUEST_URI']);
    }
    // محل فراخوانی
    if (!isset($safe['at'])) {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        foreach ($bt as $frame) {
            $f = strval($frame['file'] ?? '');
            if ($f === '' || substr($f, -9) === 'botapi.php') {
                continue;
            }
            $safe['at'] = basename($f) . ':' . intval($frame['line'] ?? 0);
            break;
        }
    }
    $sec_part = $section !== '' ? "[{$section}] " : '';
    $line = '[' . date('Y-m-d H:i:s') . " Asia/Tehran] [{$level}] {$sec_part}" . strval($message);
    if (!empty($safe)) {
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $line .= ' | ' . $json;
        }
    }
    $line .= "\n";
    @error_log(rtrim($line));
    $paths = [];
    if (defined('__DIR__')) {
        $paths[] = __DIR__ . '/error_log';
        $paths[] = dirname(__DIR__) . '/error_log';
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $paths[] = rtrim(strval($_SERVER['DOCUMENT_ROOT']), '/') . '/error_log';
    }
    foreach ($paths as $p) {
        if (!$p) {
            continue;
        }
        // نسخه 2.4.6: چرخش لاگ بالای ۵ مگابایت
        if (is_file($p) && @filesize($p) > 5242880) {
            @rename($p, $p . '.1');
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

/** نسخه 2.4.6: وجود جدول با کش ۱۰ دقیقه‌ای (به‌جای SHOW TABLES در هر پیام) */
if (!function_exists('botTableExists')) {
    function botTableExists($name)
    {
        global $pdo;
        static $mem = [];
        $name = strval($name);
        if (isset($mem[$name])) {
            return $mem[$name];
        }
        $f = __DIR__ . '/cron/schema_cache.json';
        $c = is_file($f) ? json_decode(strval(@file_get_contents($f)), true) : [];
        if (!is_array($c)) {
            $c = [];
        }
        if (!empty($c[$name]) && (time() - intval($c[$name])) < 600) {
            return $mem[$name] = true;
        }
        // نسخه 2.4.7: SHOW TABLES با placeholder در حالت native prepare خطا می‌دهد → query + quote
        $ok = false;
        try {
            $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($name));
            $ok = $st ? (count($st->fetchAll()) > 0) : false;
        } catch (Throwable $e) {
            // در صورت خطا فرض بر وجود جدول (رفتار امن برای منوها)
            if (function_exists('botErrorLog')) {
                botErrorLog('botTableExists failed', ['table' => $name, 'err' => $e->getMessage()], 'WARN', 'db');
            }
            return $mem[$name] = true;
        }
        if ($ok) {
            $c[$name] = time();
            @file_put_contents($f, json_encode($c), LOCK_EX);
        }
        return $mem[$name] = $ok;
    }
}

/** نسخه 2.5.4: رنگ دکمه موقتاً غیرفعال است؟ (بعد از رد شدن توسط تلگرام) */
function botNoStyleActive()
{
    static $v = null;
    if ($v === null) {
        $v = intval(@file_get_contents(__DIR__ . '/cron/nostyle.until')) > time();
    }
    return $v;
}

/** نسخه 2.4.8: روشن بودن دکمه‌های رنگی (PaySetting: button_colors، پیش‌فرض روشن) */
function botButtonColorsOn($reset = false)
{
    static $v = null;
    if ($reset) {
        $v = null;
    }
    if ($v !== null) {
        return $v;
    }
    $v = true;
    if (function_exists('getPaySettingValue')) {
        try {
            $v = strval(getPaySettingValue('button_colors', '1')) !== '0';
        } catch (Throwable $e) {
            $v = true;
        }
    }
    return $v;
}

/** نسخه 2.4.8: رنگ هر دکمه بر اساس متن — success=سبز، danger=قرمز، primary=آبی */
function botButtonStyleFor($t)
{
    // نسخه 2.6.42: رنگ فقط برای دکمه‌هایی که رنگ معنی دارد (قبلاً هر دکمه‌ای با کلمه‌هایی مثل «پرداخت/تایید/واریز/حذف» یا ایموجی 🛍/🎁 رنگی می‌شد)
    //   سبز  = روشن/تأیید/اقدام اصلی خرید و پرداخت
    //   قرمز = خاموش/لغو/حذف/مسدود
    //   آبی  = فقط دکمه‌های اصلی سرویس کاربر (سرویس‌های من، اکانت تست، لینک اشتراک، QR)
    $t = trim(strval($t));
    if ($t === '') {
        return '';
    }
    $starts = function ($pfx) use ($t) {
        foreach ((array)$pfx as $p) {
            if (strpos($t, $p) === 0) {
                return true;
            }
        }
        return false;
    };
    $has = function ($arr) use ($t) {
        foreach ((array)$arr as $w) {
            if (strpos($t, $w) !== false) {
                return true;
            }
        }
        return false;
    };
    // دکمه‌های روشن/خاموش و بله/خیر
    if ($starts(['✅'])) {
        return 'success';
    }
    if ($starts(['❌', '🗑', '⛔', '⏹'])) {
        return 'danger';
    }
    if ($has(['حذف سرویس', 'لغو ارسال', 'مسدود کردن'])) {
        return 'danger';
    }
    // برچسب‌های تنظیمات ادمین (امکان … / متن: …) و وضعیت انتظار رنگ نگیرند
    if ($starts(['امکان', 'متن:', '⏳', 'خرید حجم اضافه'])) {
        return '';
    }
    // اقدام اصلی خرید / تمدید / پرداخت
    if ($starts(['🛒 خرید حجم'])) {
        return 'success';
    }
    if ($has([
        'خرید اشتراک', 'خرید سرویس', 'خرید حجم اضافه', 'خرید دوباره همان پلن',
        'پرداخت و دریافت سرویس', 'تمدید همان پلن', 'تأیید تمدید', 'تمدید سرویس',
        'تأیید و دریافت', 'تأیید پرداخت', 'تأیید تغییر لینک', 'تأیید و اعمال', 'واریز حداقل مبلغ',
    ])) {
        return 'success';
    }
    if ($starts(['🟢'])) {
        return 'success';
    }
    // دکمه‌های اصلی سرویس کاربر
    // نسخه 2.6.43: «🛍 سرویس‌های من (3)» هم آبی شود (قبلاً عدد آخر متن مانع می‌شد)
    if ($starts(['🛍']) && $has(['سرویس']) && $has(['من'])) {
        return 'primary';
    }
    if ($starts(['🔑 اکانت تست', '🔑 دریافت اکانت تست', '🔗 دریافت لینک اشتراک', '🔗 لینک اشتراک', '📋 کپی لینک اشتراک', '📷 دریافت QR'])) {
        return 'primary';
    }
    return '';
}

/** نسخه 2.4.8: افزودن style به دکمه‌های inline و reply */
function botStyleMarkup($rm)
{
    if (!is_string($rm) || $rm === '' || strpos($rm, 'keyboard') === false) {
        return $rm;
    }
    $d = json_decode($rm, true);
    if (!is_array($d)) {
        return $rm;
    }
    $changed = false;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($d[$k]) || !is_array($d[$k])) {
            continue;
        }
        foreach ($d[$k] as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $j => $b) {
                if (!is_array($b) || isset($b['style']) || !isset($b['text'])) {
                    continue;
                }
                // نسخه 2.6.26: دکمه‌های صفحه تنظیم متن ربات بی‌رنگ می‌مانند
                if (strpos(strval($b['callback_data'] ?? ''), 'btxt_') === 0) {
                    continue;
                }
                // نسخه 2.6.53: «سرویس‌های من» زیر پیام ساخت سرویس بی‌رنگ
                if (strval($b['callback_data'] ?? '') === 'goto_services_c') {
                    continue;
                }
                // نسخه 2.6.33: ردیف عنوان لیست محصولات بی‌رنگ (دکمه نیست، فقط عنوان است)
                if (in_array(strval($b['callback_data'] ?? ''), ['prodlist_header', 'product_header'], true)) {
                    continue;
                }
                // نسخه 2.6.38: دکمه‌های صفحه «اطلاعات کاربر» (ادمین) بیرنگ؛ فقط مسدود کردن قرمز و دکمه‌های روشن/خاموش طبق وضعیت
                $__cb = strval($b['callback_data'] ?? '');
                $__plain = false;
                // نسخه 2.6.39: صفحه «مدیریت کاربران» هم بی‌رنگ (فقط حذف سرویس قرمز میماند)
                if (strpos($__cb, 'navuser_') === 0 && $__cb !== 'navuser_del') {
                    continue;
                }
                if ($__cb === 'nav_user' && strpos(strval($b['text']), '🔄') === 0) {
                    continue;
                }
                foreach (['addbalanceuser_', 'lowbalanceuser_', 'unbanuserr_', 'confirmnumber_', 'limitusertest_', 'verify_', 'verifyun_', 'vieworderall_', 'addordermanual', 'Response_', 'set_panel_admin_user_'] as $__pfx) {
                    if (strpos($__cb, $__pfx) === 0) {
                        $__plain = true;
                        break;
                    }
                }
                if ($__plain) {
                    continue;
                }
                // نسخه 2.6.31: سرویس تمام‌شده در «سرویس‌های من» قرمز نشود (شبیه دکمه حذف بود)
                if (strpos(strval($b['callback_data'] ?? ''), 'product_') === 0 && strpos(strval($b['text']), '🔴') === 0) {
                    continue;
                }
                // نسخه 2.6.44: «🩺 سلامت ربات» فقط وقتی مشکلی هست قرمز (فقط هنگام ارسال منوی ادمین بررسی می‌شود)
                if (strpos(strval($b['text']), '🩺') === 0) {
                    if (function_exists('botHealthHasIssue') && botHealthHasIssue()) {
                        $d[$k][$i][$j]['style'] = 'danger';
                        $changed = true;
                    }
                    continue;
                }
                $st = botButtonStyleFor($b['text']);
                if ($st !== '') {
                    $d[$k][$i][$j]['style'] = $st;
                    $changed = true;
                }
            }
        }
    }
    return $changed ? json_encode($d, JSON_UNESCAPED_UNICODE) : $rm;
}

/** لاگ سریع با بخش مشخص */
function botLog($section, $message, array $ctx = [], $level = 'INFO')
{
    if (!function_exists('botErrorLog')) {
        return;
    }
    botErrorLog($message, $ctx, $level, $section);
}

/** لاگ Exception کامل */
function botLogException($e, $section = 'exception', array $ctx = [])
{
    if (!($e instanceof Throwable)) {
        botErrorLog(strval($e), $ctx, 'ERROR', $section);
        return;
    }
    $ctx['exception'] = get_class($e);
    $ctx['error'] = $e->getMessage();
    $ctx['file'] = $e->getFile() . ':' . $e->getLine();
    $trace = $e->getTraceAsString();
    if (strlen($trace) > 1500) {
        $trace = substr($trace, 0, 1500) . '…';
    }
    $ctx['trace'] = $trace;
    botErrorLog($e->getMessage(), $ctx, 'ERROR', $section);
}

function telegram($method, $datas = [])
{
    global $APIKEY;
    $method = strval($method);
    // نسخه 2.6.20: ثبت پاسخ به دکمه شیشه‌ای (برای بستن خودکار چرخش دکمه در پایان درخواست)
    if (strtolower($method) === 'answercallbackquery') {
        $GLOBALS['__cb_answered'] = true;
    }
    // نسخه 2.4.8: رنگ خودکار دکمه‌ها (style) + برگشت بدون رنگ اگر تلگرام نپذیرفت
    $__styled = false;
    $__orig = $datas;
    if (empty($GLOBALS['__bot_nostyle']) && !botNoStyleActive() && is_array($datas) && isset($datas['reply_markup']) && is_string($datas['reply_markup'])
        && function_exists('botButtonColorsOn') && botButtonColorsOn()) {
        $__nm = botStyleMarkup($datas['reply_markup']);
        if ($__nm !== $datas['reply_markup']) {
            $datas['reply_markup'] = $__nm;
            $__styled = true;
        }
    }
    $url = "https://api.telegram.org/bot" . $APIKEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
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
            ], 'ERROR', 'telegram');
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
            ], 'ERROR', 'telegram');
        }
        return false;
    }

    if (empty($res['ok'])) {
        $desc = isset($res['description']) ? (string) $res['description'] : '';
        if (($__styled || (is_array($__orig) && strpos(strval($__orig['reply_markup'] ?? ''), 'copy_text') !== false)) && preg_match('/style|button|keyboard|copy/i', $desc)) {
            // نسخه 2.5.7: اگر دکمه کپی رد شد، بدون آن دوباره ارسال شود
            if (is_array($__orig) && strpos(strval($__orig['reply_markup'] ?? ''), 'copy_text') !== false && empty($GLOBALS['__bot_nocopy'])) {
                $GLOBALS['__bot_nocopy'] = true;
                $__o2 = $__orig;
                $__d = json_decode(strval($__o2['reply_markup']), true);
                if (is_array($__d) && isset($__d['inline_keyboard'])) {
                    foreach ($__d['inline_keyboard'] as $__i => $__row) {
                        $__d['inline_keyboard'][$__i] = array_values(array_filter((array)$__row, function ($b) { return !(is_array($b) && isset($b['copy_text'])); }));
                        if (!$__d['inline_keyboard'][$__i]) {
                            unset($__d['inline_keyboard'][$__i]);
                        }
                    }
                    $__d['inline_keyboard'] = array_values($__d['inline_keyboard']);
                    $__o2['reply_markup'] = json_encode($__d, JSON_UNESCAPED_UNICODE);
                    @error_log('copy_text button rejected, retry without it: ' . $desc);
                    return telegram($method, $__o2);
                }
            }
        }
        if ($__styled && preg_match('/style|button|keyboard/i', $desc)) {
            $GLOBALS['__bot_nostyle'] = true;
            // نسخه 2.5.4: تا ۲۴ ساعت دیگر رنگ امتحان نشود (هر پیام دوبار ارسال نشود)
            @file_put_contents(__DIR__ . '/cron/nostyle.until', strval(time() + 86400));
            @error_log('button style rejected by telegram, retry without colors: ' . $desc);
            return telegram($method, $__orig);
        }
        // نسخه 2.6.60: کاربری که ربات را بلاک کرده علامت بخورد (همگانی و کرون برایش نروند)
        if ($chat !== '' && ctype_digit($chat) && (stripos($desc, 'bot was blocked') !== false || stripos($desc, 'user is deactivated') !== false)
            && function_exists('botMarkBotBlocked')) {
            botMarkBotBlocked($chat, 1);
        }
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
            "message can't be edited",
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
                ], 'ERROR', 'telegram');
            } else {
                error_log(json_encode($res, JSON_UNESCAPED_UNICODE));
            }
        }
    }
    return $res;
}

function telegramRetry($method, $datas = [], $maxAttempts = 3)
{
    /**
     * تلاش مجدد روی خطای شبکه (timeout/DNS) بدون پیام اضافه به کاربر.
     * حداکثر ۳ بار؛ بین خطاهای شبکه ~۱ ثانیه صبر.
     */
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
        // خطای دائمی: retry بی‌فایده
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
            'message is not modified',
            'query is too old',
        ];
        foreach ($permanent as $pp) {
            if ($desc !== '' && strpos($desc, $pp) !== false) {
                return $last;
            }
        }
        if ($attempt >= $maxAttempts) {
            break;
        }
        // شبکه: telegram() مقدار false برمی‌گرداند
        $is_network = ($last === false || $last === null);
        if (!$is_network && is_array($last)) {
            $d = $desc;
            if (
                strpos($d, 'timeout') !== false
                || strpos($d, 'timed out') !== false
                || strpos($d, 'resolve') !== false
                || strpos($d, 'connection') !== false
                || strpos($d, 'curl') !== false
            ) {
                $is_network = true;
            }
            // 429 / flood
            if (isset($last['error_code']) && intval($last['error_code']) === 429) {
                $is_network = true;
            }
        }
        if ($is_network) {
            // ~1.0 تا 1.2 ثانیه — همان «دوباره بعد ۱–۲ ثانیه»
            $us = 1000000 + (($attempt - 1) * 200000);
            if (is_array($last) && isset($last['parameters']['retry_after'])) {
                $us = min(2000000, max(1000000, intval($last['parameters']['retry_after']) * 1000000));
            }
            @usleep($us);
        } else {
            @usleep(250000);
        }
    }
    // هیچ پیام خطایی به کاربر فرستاده نمی‌شود؛ فقط نتیجه برای caller
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
    $msgs = [];
    foreach ($admins as $aid) {
        if ($aid <= 0) {
            continue;
        }
        $data = $payload;
        $data['chat_id'] = $aid;
        $res = telegramRetry($method, $data, 3);
        if (telegramOk($res)) {
            $ok++;
            if (is_array($res) && isset($res['result']['message_id'])) {
                $msgs[$aid] = intval($res['result']['message_id']);
            }
        } else {
            $fail++;
            $results[$aid] = is_array($res) ? ($res['description'] ?? 'fail') : 'false';
        }
    }
    if ($ok === 0 && $fail > 0) {
        if (function_exists('botErrorLog')) { botErrorLog('notifyAdmins all failed', ['method' => $method, 'errs' => $results], 'ERROR', 'admin'); } else { error_log('notifyAdmins all failed method=' . $method); }
    }
    return ['ok' => $ok, 'fail' => $fail, 'results' => $results, 'msgs' => $msgs];
}

function sendmessage($chat_id, $text, $keyboard, $parse_mode)
{
    return telegramRetry('sendmessage', [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
    ], 3);
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
    return telegramRetry('sendphoto', [
        'chat_id' => $chat_id,
        'photo' => $photoid,
        'caption' => $caption,
        'parse_mode' => $parse_mode,
    ], 3);
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
    return telegramRetry('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => $keyboard,
        'parse_mode' => $parse_mode,
    ], 3);
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
/**
 * جلوگیری از پردازش دوباره یک آپدیت (خرید/تمدید دوبل وقتی تلگرام آپدیت را دوباره می‌فرستد).
 * true = آپدیت جدید است و باید پردازش شود؛ false = تکراری است.
 */
function botClaimUpdate($update_id)
{
    global $pdo;
    $update_id = intval($update_id);
    if ($update_id <= 0 || !isset($pdo) || !($pdo instanceof PDO)) {
        return true;
    }
    $sql = "INSERT IGNORE INTO processed_updates (update_id, created_at) VALUES (?, ?)";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$update_id, time()]);
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS processed_updates (
                update_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_created (created_at)
            ) ENGINE=InnoDB");
            $st = $pdo->prepare($sql);
            $st->execute([$update_id, time()]);
        } catch (Throwable $e2) {
            botErrorLog('update dedup unavailable', ['error' => $e2->getMessage()], 'WARN', 'telegram');
            return true; // اگر بررسی ممکن نبود، ربات از کار نیفتد
        }
    }
    if ($st->rowCount() === 0) {
        botLog('telegram', 'duplicate update skipped', ['update_id' => $update_id], 'INFO');
        return false;
    }
    // پاک‌سازی گاهبهگاه (قدیمی‌تر از ۲ روز)
    if (mt_rand(1, 500) === 1) {
        try {
            $pdo->prepare("DELETE FROM processed_updates WHERE created_at < ?")->execute([time() - 172800]);
        } catch (Throwable $e) {
        }
    }
    return true;
}

/** مقدار تنظیم‌شده و غیر placeholder است؟ ({...} یعنی نصبکننده هنوز پر نکرده) */
function botConfigValueSet($v)
{
    $v = is_string($v) ? trim($v) : '';
    return $v !== '' && $v[0] !== '{';
}

/**
 * بررسی هدر X-Telegram-Bot-Api-Secret-Token.
 * اگر $secrettoken در config تنظیم نشده باشد (نصب‌های قدیمی) چیزی رد نمی‌شود.
 */
function botVerifyWebhookSecret()
{
    global $secrettoken;
    if (!botConfigValueSet($secrettoken ?? '')) {
        return true;
    }
    $got = strval($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($got !== '' && hash_equals(strval($secrettoken), $got)) {
        return true;
    }
    botErrorLog('webhook secret mismatch', ['ip' => strval($_SERVER['REMOTE_ADDR'] ?? '')], 'WARN', 'telegram');
    return false;
}

/** کلید دسترسی کرون‌ها — از توکن ربات مشتق می‌شود، تنظیم جدیدی لازم نیست */
function botCronKey()
{
    global $APIKEY, $domainhosts;
    return substr(hash_hmac('sha256', 'mirza-cron:' . strval($domainhosts), strval($APIKEY)), 0, 32);
}

/** آدرس کامل کرون همراه کلید، برای crontab */
function botCronUrl($script)
{
    global $domainhosts;
    return 'https://' . strval($domainhosts) . '/cron/' . $script . '?key=' . botCronKey();
}

/**
 * فقط اجرای CLI، درخواست از خود سرور، یا درخواست با ?key= درست مجاز است.
 * کرون‌های قدیمی (curl از همین سرور) بدون تغییر کار می‌کنند.
 */
function botRequireCronAccess()
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $ip = strval($_SERVER['REMOTE_ADDR'] ?? '');
    $local = ['127.0.0.1', '::1'];
    if (!empty($_SERVER['SERVER_ADDR'])) {
        $local[] = strval($_SERVER['SERVER_ADDR']);
    }
    if ($ip !== '' && in_array($ip, $local, true)) {
        return;
    }
    $key = strval($_GET['key'] ?? '');
    if ($key !== '' && hash_equals(botCronKey(), $key)) {
        return;
    }
    botErrorLog('cron access denied', [
        'ip' => $ip,
        'script' => basename(strval($_SERVER['SCRIPT_NAME'] ?? '')),
    ], 'WARN', 'cron');
    http_response_code(403);
    exit;
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
        // نسخه 2.4.6: هشدار خطای جدی به کانال (هر خطای تکراری حداکثر هر ۳۰ دقیقه)
        try {
            if (function_exists('botTechAlert')) {
                $__fl = basename(strval($e['file'] ?? '')) . ':' . intval($e['line'] ?? 0);
                $__act = strval(($GLOBALS['datain'] ?? '') !== '' ? $GLOBALS['datain'] : ($GLOBALS['text'] ?? ''));
                botTechAlert('fatal:' . $__fl, "🚨 <b>خطای جدی PHP</b>\nربات به این درخواست جواب نداد.\n\n📄 <code>" . htmlspecialchars($__fl, ENT_QUOTES, 'UTF-8') . "</code>\n<code>" . htmlspecialchars(mb_substr(strval($e['message']), 0, 400), ENT_QUOTES, 'UTF-8') . "</code>\n🆔 کاربر: <code>" . intval($GLOBALS['from_id'] ?? 0) . "</code>\n🔘 " . htmlspecialchars(mb_substr($__act, 0, 60), ENT_QUOTES, 'UTF-8'));
            }
        } catch (Throwable $__t) {
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
