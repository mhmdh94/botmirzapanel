<?php
/**
 * مینی‌اپ — مرحله ۱: فقط نمایش (سرویس‌ها، جزئیات زنده، لینک و QR، کیف پول و تراکنش‌ها)
 * هیچ پولی جابه‌جا نمی‌شود. نسخه 2.7.0
 */
$__root = dirname(__DIR__);
chdir($__root);
ini_set('display_errors', '0');
ini_set('error_log', $__root . '/error_log');
date_default_timezone_set('Asia/Tehran');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function miniOut(array $arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function miniErr($msg, $code = 200)
{
    miniOut(['ok' => false, 'msg' => $msg], $code);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    miniErr('method not allowed', 405);
}

require_once $__root . '/config.php';
require_once $__root . '/botapi.php';
require_once $__root . '/jdf.php';
require_once $__root . '/text.php';
require_once $__root . '/functions.php';
require_once $__root . '/panels.php';
require_once __DIR__ . '/auth.php';

$tg = miniVerifyInitData(strval($_POST['initData'] ?? ''), strval($APIKEY));
if ($tg === null) {
    miniErr('🔒 نشست نامعتبر است. مینی‌اپ را دوباره از داخل ربات باز کنید.', 401);
}

$setting = select("setting", "*");
if (is_array($setting) && (strval($setting['status_miniapp'] ?? '1') === '0' || strval($setting['Bot_Status'] ?? '1') === '0')) {
    miniErr('⛔️ مینی‌اپ فعلاً غیرفعال است.');
}

$uid = intval($tg['id']);
$user = select("user", "*", "id", $uid, "select");
if (!is_array($user)) {
    miniErr('ابتدا ربات را با /start استارت کنید.');
}
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
$is_admin = in_array(strval($uid), array_map('strval', is_array($admin_ids) ? $admin_ids : []), true);
if (strval($user['User_Status'] ?? '') === 'block' && !$is_admin) {
    miniErr('🚫 حساب شما مسدود است.');
}

function miniLeftLabel(array $row)
{
    $amount = intval($row['Service_time'] ?? 0);
    $mul = (strval($row['name_product'] ?? '') === 'usertest') ? 3600 : 86400;
    $ts = $row['time_sell'] ?? '';
    if ($amount <= 0 || $ts === '' || $ts === null) {
        return '';
    }
    $start = (is_numeric($ts) && intval($ts) > 100000) ? intval($ts) : strtotime(strval($ts));
    if (!$start) {
        return '';
    }
    $left = ($start + $amount * $mul) - time();
    if ($left <= 0) {
        return 'تمام‌شده';
    }
    return $left < 86400 ? (intval(ceil($left / 3600)) . ' ساعت') : (intval(ceil($left / 86400)) . ' روز');
}
function miniProductName(array $row)
{
    $n = strval($row['name_product'] ?? '');
    return $n === 'usertest' ? 'اکانت تست' : $n;
}
function miniGb($bytes)
{
    $gb = floatval($bytes) / 1073741824;
    return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.') . ' گیگ';
}
function miniOwnedInvoice($uid, $username)
{
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id AND username = :u AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn') LIMIT 1");
    $st->execute([':id' => strval($uid), ':u' => $username]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

$action = strval($_POST['action'] ?? '');

try {
    if ($action === 'me') {
        $st = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT 100");
        $st->execute([':id' => strval($uid)]);
        $services = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $services[] = [
                'username' => strval($row['username']),
                'emoji' => function_exists('serviceListEmojiFromInvoice') ? serviceListEmojiFromInvoice($row) : '🟢',
                'left' => miniLeftLabel($row),
                'product' => miniProductName($row),
                'location' => strval($row['Service_location'] ?? ''),
            ];
        }
        miniOut([
            'ok' => true,
            'name' => strval($tg['first_name'] ?? ''),
            'balance' => intval($user['Balance'] ?? 0),
            'services' => $services,
            'version' => strval($version ?? ''),
        ]);
    }

    if ($action === 'service') {
        $un = strval($_POST['username'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.\-]{1,64}$/', $un)) {
            miniErr('نام کاربری نامعتبر است.');
        }
        $inv = miniOwnedInvoice($uid, $un);
        if ($inv === null) {
            miniErr('❌ این سرویس پیدا نشد.');
        }
        $out = [
            'username' => $un,
            'emoji' => function_exists('serviceListEmojiFromInvoice') ? serviceListEmojiFromInvoice($inv) : '🟢',
            'live' => false,
            'status_label' => '',
            'lines' => [],
            'usage_pct' => null,
            'sub_url' => '',
            'qr' => '',
        ];
        $d = null;
        try {
            $mp = new ManagePanel();
            $d = $mp->DataUser(strval($inv['Service_location']), $un);
        } catch (Throwable $e) {
            $d = null;
        }
        $lines = [];
        if (is_array($d) && ($d['status'] ?? '') !== 'Unsuccessful' && !empty($d['username'])) {
            $out['live'] = true;
            $labels = [
                'active' => $textbotlang['users']['status']['active'] ?? '✅ فعال',
                'limited' => $textbotlang['users']['status']['limited'] ?? '🚫 پایان حجم',
                'expired' => $textbotlang['users']['status']['expired'] ?? '🔚 پایان زمان',
                'on_hold' => $textbotlang['users']['status']['onhold'] ?? '⏳ متصل نشده',
                'disabled' => $textbotlang['users']['status']['disabled'] ?? '❌ غیرفعال',
            ];
            $s = strval($d['status'] ?? '');
            $out['status_label'] = $labels[$s] ?? $s;
            $used = floatval($d['used_traffic'] ?? 0);
            $limit = floatval($d['data_limit'] ?? 0);
            $lines[] = ['📦 محصول', miniProductName($inv)];
            $lines[] = ['📍 لوکیشن', strval($inv['Service_location'] ?? '')];
            $lines[] = ['📊 حجم کل', $limit > 0 ? miniGb($limit) : 'نامحدود'];
            $lines[] = ['📥 مصرف‌شده', miniGb($used)];
            if ($limit > 0) {
                $lines[] = ['🪫 باقی‌مانده', miniGb(max(0, $limit - $used))];
                $out['usage_pct'] = intval(round(max(0, min(100, $used / $limit * 100))));
            }
            $exp = intval($d['expire'] ?? 0);
            if ($exp > 0) {
                $left = $exp - time();
                $tail = $left > 0 ? (' (' . ($left < 86400 ? intval(ceil($left / 3600)) . ' ساعت' : intval(ceil($left / 86400)) . ' روز') . ')') : ' (تمام‌شده)';
                $lines[] = ['📅 تاریخ پایان', jdate('Y/m/d', $exp) . $tail];
            } elseif ($s === 'on_hold') {
                $lines[] = ['📅 تاریخ پایان', 'از اولین اتصال شروع می‌شود'];
            } else {
                $lines[] = ['📅 تاریخ پایان', 'نامحدود'];
            }
            if (!empty($d['online_at'])) {
                $on = is_numeric($d['online_at']) ? intval($d['online_at']) : strtotime(strval($d['online_at']));
                if ($on) {
                    $lines[] = ['🕐 آخرین اتصال', jdate('Y/m/d H:i', $on)];
                }
            }
            $out['sub_url'] = strval($d['subscription_url'] ?? '');
        } else {
            $lines[] = ['📦 محصول', miniProductName($inv)];
            $lines[] = ['📍 لوکیشن', strval($inv['Service_location'] ?? '')];
            $vol = strval($inv['Volume'] ?? '');
            $lines[] = ['📊 حجم', ($vol === '' || $vol === '0') ? 'نامحدود' : ($vol . ' گیگ')];
            $left = miniLeftLabel($inv);
            if ($left !== '') {
                $lines[] = ['⏳ زمان باقی‌مانده', $left];
            }
        }
        $out['lines'] = $lines;
        if ($out['sub_url'] !== '' && class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {
            try {
                $qr = \Endroid\QrCode\QrCode::create($out['sub_url'])
                    ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
                    ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::Low)
                    ->setSize(360)
                    ->setMargin(0)
                    ->setRoundBlockSizeMode(\Endroid\QrCode\RoundBlockSizeMode::Margin);
                $res = (new \Endroid\QrCode\Writer\PngWriter())->write($qr, null, null);
                $out['qr'] = 'data:image/png;base64,' . base64_encode($res->getString());
            } catch (Throwable $e) {
                $out['qr'] = '';
            }
        }
        miniOut(['ok' => true, 'service' => $out]);
    }

    if ($action === 'tx') {
        if (function_exists('ensureWalletLog')) {
            ensureWalletLog();
        }
        $st = $pdo->prepare("SELECT type, amount, balance_after, detail, created_at FROM wallet_log WHERE id_user = ? ORDER BY id DESC LIMIT 30");
        $st->execute([$uid]);
        $minus = ['buy', 'renew', 'extra_volume', 'admin_low', 'reseller_volume'];
        $names = [
            'deposit' => 'افزایش موجودی', 'admin_add' => 'افزایش توسط مدیریت', 'admin_low' => 'کاهش توسط مدیریت',
            'buy' => 'خرید سرویس', 'renew' => 'تمدید سرویس', 'extra_volume' => 'حجم اضافه',
            'refund' => 'برگشت وجه', 'affiliate' => 'پورسانت', 'gift' => 'کد هدیه',
        ];
        $items = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $t = strval($r['type']);
            $items[] = [
                'title' => $names[$t] ?? (strval($r['detail'] ?? '') !== '' ? mb_substr(strval($r['detail']), 0, 40) : $t),
                'sign' => in_array($t, $minus, true) ? '-' : '+',
                'amount' => intval($r['amount']),
                'after' => $r['balance_after'] !== null ? intval($r['balance_after']) : null,
                'date' => jdate('Y/m/d H:i', intval($r['created_at'])),
            ];
        }
        miniOut(['ok' => true, 'balance' => intval($user['Balance'] ?? 0), 'items' => $items]);
    }
} catch (Throwable $e) {
    if (function_exists('botErrorLog')) {
        botErrorLog('miniapp: ' . $e->getMessage(), ['action' => $action], 'ERROR', 'miniapp');
    } else {
        error_log('miniapp: ' . $e->getMessage());
    }
    miniErr('⚠️ مشکلی پیش آمد. چند لحظه بعد دوباره تلاش کنید.', 500);
}

miniErr('درخواست نامعتبر است.', 400);
