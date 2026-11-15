
        'msg_reseller_request_info' => [
            'label' => $textbotlang['Admin']['changetext']['msg_reseller_request_info'] ?? 'متن: درخواست پنل نمایندگی',
            'fallback' => $textbotlang['users']['reseller_request']['info'] ?? '',
            'type' => 'message',
        ],
        'msg_affiliates_info' => [
            'label' => $textbotlang['Admin']['changetext']['msg_affiliates_info'] ?? 'متن: مشارکت در فروش',
            'fallback' => $textbotlang['users']['affiliates']['infotext'] ?? '',
            'type' => 'message',
        ],
        'msg_phone_verify' => [
            'label' => $textbotlang['Admin']['changetext']['msg_phone_verify'] ?? 'متن: احراز هویت شماره',
            'fallback' => $textbotlang['users']['number']['Confirming'] ?? '',
            'type' => 'message',
        ],
        'text_bot_off' => [
            'label' => $textbotlang['Admin']['changetext']['text_bot_off'] ?? 'متن: ربات خاموش',
            'fallback' => $textbotlang['users']['updatingbot'] ?? '❌ ربات در حال حاضر غیرفعال است.',
            'type' => 'message',
        ],
<?php
require_once 'vendor/autoload.php';
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

function ActiveVoucher($ev_number, $ev_code)
{
    // PerfectMoney حذف شده — غیرفعال
    return false;
}
function update($table, $field, $newValue, $whereField = null, $whereValue = null)
{
    global $pdo, $user;
    $tables = [
        "user",
        "help",
        "setting",
        "admin",
        "channels",
        "marzban_panel",
        "product",
        "invoice",
        "Payment_report",
        "Discount",
        "Giftcodeconsumed",
        "textbot",
        "PaySetting",
        "DiscountSell",
        "affiliates",
        "cancel_service",
        "category"
    ];
    if(!in_array($table, $tables))return;
    if ($whereField !== null) {
        $sql = "UPDATE $table SET $field = ? WHERE $whereField = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newValue, $whereValue]);
    } else {
        $sql = "UPDATE $table SET $field = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newValue]);
    }
}
function step($step, $from_id)
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
    $stmt->execute([$step, $from_id]);


}
function select($table, $field, $whereField = null, $whereValue = null, $type = "select")
{
    global $pdo;

    $query = "SELECT $field FROM $table";

    if ($whereField !== null) {
        $query .= " WHERE $whereField = :whereValue";
    }

    try {
        $stmt = $pdo->prepare($query);

        if ($whereField !== null) {
            $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_STR);
        }

        $stmt->execute();

        if ($type == "count") {
            return $stmt->rowCount();
        } elseif ($type == "FETCH_COLUMN") {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($type == "fetchAll") {
            return $stmt->fetchAll();
        } else {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}

function generateUUID()
{
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    return $uuid;
}
function tronratee()
{
    $tronrate = [];
    $requeststron = json_decode(file_get_contents('https://api.diadata.org/v1/assetQuotation/Tron/0x0000000000000000000000000000000000000000'), true);
    $requestsusd = json_decode(file_get_contents('https://api.wallex.ir/v1/markets'), true);
    $tronrate['result']['USD'] = intval($requestsusd['result']['symbols']['USDTTMN']['stats']['lastPrice']);
    $tronrate['result']['TRX'] = intval($requeststron['Price'] * $tronrate['result']['USD']);
    return $tronrate;
}

/**
 * نرخ‌های تومانی از نوبیتکس (قیمت هر واحد ارز به تومان)
 * کلیدها: usdt, trx, ton  — در صورت خطا null
 */

/**
 * نرخ تومانی هر واحد ارز از والکس
 * @return array{usdt:?float,trx:?float,ton:?float}
 */

/**
 * تنظیمات ارزهای قابل واریز (ارزی ریالی)
 * enabled_key / wallet_key در PaySetting
 */
function getCurrencyCoinsMeta()
{
    return [
        'trx' => [
            'title' => '🔶 TRON (TRX)',
            'unit' => 'TRX',
            'wallet_key' => 'wallet_tron',
            'enabled_key' => 'currency_show_trx',
            'rate_symbols' => ['TRXTMN', 'TRXIRT', 'TRX-TMN'],
        ],
        'gram' => [
            'title' => '💎 GRAM (گرام)',
            'unit' => 'GRAM',
            'wallet_key' => 'wallet_gram',
            'enabled_key' => 'currency_show_gram',
            // گرام جایگزین TON در والکس — نمادهای احتمالی
            'rate_symbols' => ['GRAMTMN', 'GRAMIRT', 'TONTMN', 'TONIRT', 'TON-TMN'],
            'legacy_wallet_key' => 'wallet_ton',
        ],
        'usdt' => [
            'title' => '🟢 USDT (BEP20)',
            'unit' => 'USDT',
            'wallet_key' => 'wallet_usdt_bep20',
            'enabled_key' => 'currency_show_usdt',
            'rate_symbols' => ['USDTTMN', 'USDTIRT', 'USDT-TMN'],
        ],
        'bnb' => [
            'title' => '🟡 BNB (BEP20)',
            'unit' => 'BNB',
            'wallet_key' => 'wallet_bnb',
            'enabled_key' => 'currency_show_bnb',
            'rate_symbols' => ['BNBTMN', 'BNBIRT', 'BNB-TMN'],
        ],
    ];
}

function ensureCurrencyPaySettings()
{
    foreach (getCurrencyCoinsMeta() as $coin => $meta) {
        ensurePaySetting($meta['wallet_key'], '');
        ensurePaySetting($meta['enabled_key'], '1'); // پیش‌فرض روشن
        if (!empty($meta['legacy_wallet_key'])) {
            ensurePaySetting($meta['legacy_wallet_key'], '');
        }
    }
    ensurePaySetting('currency_discount_status', '0');
    ensurePaySetting('currency_discount_percent', '10');
}

function isCurrencyDiscountEnabled()
{
    ensureCurrencyPaySettings();
    $v = getPaySettingValue('currency_discount_status', '0');
    $p = floatval(getPaySettingValue('currency_discount_percent', '0'));
    return ($v === '1' || $v === 'on') && $p > 0;
}

function getCurrencyDiscountPercent()
{
    ensureCurrencyPaySettings();
    $p = floatval(getPaySettingValue('currency_discount_percent', '0'));
    if ($p < 0) {
        $p = 0;
    }
    if ($p > 90) {
        $p = 90;
    }
    return $p;
}

/** مبلغ قابل پرداخت کریپتو پس از تخفیف (تومان) */
function getCurrencyPayableAmount($amount_toman)
{
    $amount = floatval($amount_toman);
    if (isCurrencyDiscountEnabled()) {
        $p = getCurrencyDiscountPercent();
        $amount = $amount * (100.0 - $p) / 100.0;
    }
    return max(1, intval(round($amount)));
}

/** متن دکمه روش پرداخت کریپتو */
function getCurrencyPaymentButtonText()
{
    global $textbotlang;
    $base = $textbotlang['users']['moeny']['currency_rial_gateway'] ?? '💎 کریپتو';
    if (isCurrencyDiscountEnabled()) {
        $p = getCurrencyDiscountPercent();
        $pshow = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.');
        return $base . " | 🎁 {$pshow}٪ تخفیف";
    }
    return $base;
}


function getCurrencyWalletAddress($coin)
{
    $meta = getCurrencyCoinsMeta()[$coin] ?? null;
    if (!$meta) {
        return '';
    }
    $addr = trim((string) getPaySettingValue($meta['wallet_key'], ''));
    if ($addr === '' && !empty($meta['legacy_wallet_key'])) {
        $addr = trim((string) getPaySettingValue($meta['legacy_wallet_key'], ''));
    }
    return $addr;
}

function isCurrencyCoinEnabled($coin)
{
    $meta = getCurrencyCoinsMeta()[$coin] ?? null;
    if (!$meta) {
        return false;
    }
    ensurePaySetting($meta['enabled_key'], '1');
    $v = getPaySettingValue($meta['enabled_key'], '1');
    return ($v === '1' || $v === 'on' || $v === 'true');
}

/**
 * نرخ تومانی از والکس
 * @return array<string,?float>
 */
function getCryptoRatesToman()
{
    $meta = getCurrencyCoinsMeta();
    $out = [];
    foreach ($meta as $coin => $_) {
        $out[$coin] = null;
    }
    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.wallex.ir/v1/markets');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: MirzaBot'],
        ]);
        $raw = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            $raw = null;
        }
    }
    if (!$raw) {
        $raw = @file_get_contents('https://api.wallex.ir/v1/markets', false, stream_context_create([
            'http' => ['timeout' => 10, 'header' => "Accept: application/json\r\nUser-Agent: MirzaBot\r\n"],
        ]));
    }
    if (!$raw) {
        return $out;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $out;
    }
    $symbols = $data['result']['symbols'] ?? $data['symbols'] ?? null;
    if (!is_array($symbols)) {
        return $out;
    }
    foreach ($meta as $coin => $info) {
        foreach ($info['rate_symbols'] as $sym) {
            if (!isset($symbols[$sym]) || !is_array($symbols[$sym])) {
                continue;
            }
            $st = $symbols[$sym]['stats'] ?? $symbols[$sym];
            foreach (['lastPrice', 'bidPrice', 'askPrice'] as $fk) {
                if (isset($st[$fk]) && is_numeric($st[$fk]) && floatval($st[$fk]) > 0) {
                    $out[$coin] = floatval($st[$fk]);
                    break 2;
                }
            }
        }
    }
    return $out;
}


function buildCurrencyAdminKeyboard()
{
    global $textbotlang;
    ensureCurrencyPaySettings();
    $rows = [];
    foreach (getCurrencyCoinsMeta() as $coin => $meta) {
        $enabled = isCurrencyCoinEnabled($coin);
        $status = $enabled ? '✅ نمایش' : '❌ مخفی';
        $addr = getCurrencyWalletAddress($coin);
        $addr_short = $addr !== '' ? (mb_substr($addr, 0, 10) . '…') : 'بدون آدرس';
        $rows[] = [
            ['text' => $meta['title'] . ' | ' . $addr_short, 'callback_data' => 'cur_setaddr_' . $coin],
            ['text' => $status, 'callback_data' => 'cur_toggle_' . $coin],
        ];
    }
    $disc_on = isCurrencyDiscountEnabled();
    $p = getCurrencyDiscountPercent();
    $pshow = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.');
    $rows[] = [
        ['text' => '🎁 تخفیف کریپتو: ' . ($disc_on ? "✅ {$pshow}٪" : '❌ خاموش'), 'callback_data' => 'cur_discount_toggle'],
    ];
    $rows[] = [
        ['text' => '✏️ تنظیم درصد تخفیف', 'callback_data' => 'cur_discount_set'],
    ];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function getNobitexRates()
{
    return getCryptoRatesToman();
}

function formatCryptoAmount($tomanAmount, $rateToman)
{
    if ($rateToman === null || $rateToman <= 0) {
        return '—';
    }
    $amt = floatval($tomanAmount) / floatval($rateToman);
    if ($amt >= 100) {
        return rtrim(rtrim(number_format($amt, 2, '.', ''), '0'), '.');
    }
    if ($amt >= 1) {
        return rtrim(rtrim(number_format($amt, 4, '.', ''), '0'), '.');
    }
    return rtrim(rtrim(number_format($amt, 6, '.', ''), '0'), '.');
}

/**
 * ساخت متن پرداخت ارزی برای کاربر
 */
function buildCurrencyPaymentText($amount_toman)
{
    global $textbotlang;
    ensureCurrencyPaySettings();
    $original = intval($amount_toman);
    $payable = getCurrencyPayableAmount($original);
    $rates = getCryptoRatesToman();
    $lines = [];
    $any = false;
    $has_rate = false;
    foreach (getCurrencyCoinsMeta() as $coin => $meta) {
        if (!isCurrencyCoinEnabled($coin)) {
            continue;
        }
        $addr = getCurrencyWalletAddress($coin);
        if ($addr === '') {
            continue;
        }
        $any = true;
        $rate = $rates[$coin] ?? null;
        if ($rate !== null) {
            $has_rate = true;
        }
        $amt = formatCryptoAmount($payable, $rate);
        $lines[] = $meta['title']
            . "\nمبلغ واریزی: <code>{$amt}</code> {$meta['unit']}"
            . "\nآدرس:\n<code>{$addr}</code>";
    }
    if (!$any) {
        return ['ok' => false, 'error' => 'empty'];
    }
    if (!$has_rate) {
        return ['ok' => false, 'error' => 'rate'];
    }
    if (isCurrencyDiscountEnabled()) {
        $p = getCurrencyDiscountPercent();
        $pshow = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.');
        $header = sprintf(
            $textbotlang['users']['moeny']['currency_text_header_discount'] ?? "🎁 با پرداخت کریپتو <b>%s٪</b> تخفیف دارید!\nاعتبار درخواستی: <b>%s</b> تومان\nمبلغ قابل پرداخت: <b>%s</b> تومان\n\nیکی از ارزهای زیر را واریز کنید:\n",
            $pshow,
            number_format($original, 0),
            number_format($payable, 0)
        );
    } else {
        $header = sprintf(
            $textbotlang['users']['moeny']['currency_text_header'] ?? "برای افزایش موجودی معادل <b>%s</b> تومان، یکی از ارزهای زیر را واریز کنید:\n",
            number_format($original, 0)
        );
    }
    $footer = $textbotlang['users']['moeny']['currency_text_footer'] ?? "\n\n⚠️ مبلغ را دقیقاً مطابق عدد بالا واریز کنید.\n🌅 بعد از واریز، <b>عکس رسید</b> را همینجا ارسال کنید.";
    return ['ok' => true, 'text' => $header . "\n" . implode("\n\n", $lines) . $footer];
}

function nowPayments()
{
    return null; // NowPayments removed
}
function StatusPayment($paymentid)
{
    // NowPayments حذف شده
    return null;
}
function formatBytes($bytes, $precision = 2): string
{
    global $textbotlang;
    $base = log($bytes, 1024);
    $power = $bytes > 0 ? floor($base) : 0;
    $suffixes = [$textbotlang['users']['format']['byte'], $textbotlang['users']['format']['kilobyte'], $textbotlang['users']['format']['MBbyte'], $textbotlang['users']['format']['GBbyte'], $textbotlang['users']['format']['TBbyte']];
    return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
}
#---------------------[ ]--------------------------#
function generateUsername($from_id, $Metode, $username, $randomString, $text)
{
    global $connect, $textbotlang;
    $setting = select("setting", "*");
    global $connect;
    if ($Metode == $textbotlang['users']['customidAndRandom']) {
        return $from_id . "_" . $randomString;
    } elseif ($Metode == $textbotlang['users']['customusernameandorder']) {
        return $username . "_" . $randomString;
    } elseif ($Metode == $textbotlang['users']['customusernameorder']) {
        $statistics = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(id_user)  FROM invoice WHERE id_user = '$from_id'"));
        $countInvoice = intval($statistics['COUNT(id_user)']) + 1;
        return $username . "_" . $countInvoice;
    } elseif ($Metode == $textbotlang['users']['customusername'])
        return $text;
    elseif ($Metode == $textbotlang['users']['customtextandrandom'])
        return $setting['namecustome'] . "_" . $randomString;
}

/**
 * ساخت نام کاربری رندوم که در پنل و جدول invoice موجود نباشد
 */
function generateAvailableUsername($panel_name)
{
    global $ManagePanel, $usernameinvoice;
    // فرمت: ۵ حرف کوچک + ۲ رقم (مثلاً kxmqp47) — کوتاه و خوانا
    $make = function () {
        $letters = '';
        for ($j = 0; $j < 5; $j++) {
            $letters .= chr(random_int(97, 122)); // a-z
        }
        return $letters . strval(random_int(10, 99));
    };
    for ($i = 0; $i < 20; $i++) {
        $candidate = $make();
        if (is_array($usernameinvoice) && in_array($candidate, $usernameinvoice)) {
            continue;
        }
        $row = select("invoice", "username", "username", $candidate, "select");
        if ($row) {
            continue;
        }
        if ($panel_name && isset($ManagePanel) && is_object($ManagePanel)) {
            $DataUserOut = $ManagePanel->DataUser($panel_name, $candidate);
            if (isset($DataUserOut['username']) && $DataUserOut['username'] && ($DataUserOut['msg'] ?? '') !== 'User not found') {
                if (($DataUserOut['status'] ?? '') === 'Unsuccessful' && ($DataUserOut['msg'] ?? '') === 'User not found') {
                    return $candidate;
                }
                if (($DataUserOut['status'] ?? '') !== 'Unsuccessful') {
                    continue;
                }
                if (($DataUserOut['msg'] ?? '') === 'User not found') {
                    return $candidate;
                }
                continue;
            }
        }
        return $candidate;
    }
    // آخرین تلاش
    return $make() . strval(random_int(0, 9));
}


/**
 * تلاش مجدد فقط برای دو حالت:
 * 1) Panel Not Found → تا ۳ بار با همان یوزرنیم
 * 2) یوزرنیم تکراری → دو رقم رندوم به انتها + ساخت دوباره (تا ۳ بار)
 * سایر ارورها: بدون تلاش اضافه (مسیر برگشت پول و ... مثل قبل می‌ماند)
 */

/**
 * نرمال‌سازی پیام خطای پنل برای نمایش به ادمین/کاربر
 */
function formatPanelErrorMsg($msg)
{
    if (is_array($msg) || is_object($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    }
    if ($msg === null || $msg === '' || $msg === false) {
        return 'پاسخ نامعتبر از پنل (بدون متن خطا)';
    }
    $msg = trim((string) $msg);
    if ($msg === '' || strtolower($msg) === 'null' || $msg === '[]' || $msg === '{}') {
        return 'پاسخ نامعتبر از پنل (بدون متن خطا)';
    }
    // جلوگیری از پیام خیلی بلند
    if (mb_strlen($msg, 'UTF-8') > 500) {
        $msg = mb_substr($msg, 0, 500, 'UTF-8') . '…';
    }
    return $msg;
}

/**
 * برگشت مبلغ به کیف پول فقط اگر واقعاً از موجودی کم شده باشد
 * (با مقایسه موجودی قبل/بعد یا مبلغ مشخص)
 * @return int مبلغ برگشتی (0 اگر برنگشت)
 */

/**
 * قفل فایل کوتاه‌مدت برای عملیات پولی (خرید/تمدید/حجم) — بدون تداخل با فیلدهای user
 * @return array{0:resource,1:string}|null
 */
function paymentAcquireLock($user_id, $kind = 'pay')
{
    $kind = preg_replace('/[^a-z0-9_]/', '', strtolower(strval($kind)));
    if ($kind === '') {
        $kind = 'pay';
    }
    $path = sys_get_temp_dir() . '/mirza_' . $kind . '_' . intval($user_id) . '.lock';
    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return null;
    }
    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        $stale = false;
        if (is_file($path) && (time() - intval(@filemtime($path))) > 90) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @unlink($path);
            $stale = true;
            $fp = @fopen($path, 'c+');
            if ($fp && @flock($fp, LOCK_EX | LOCK_NB)) {
                // ok after stale reclaim
            } else {
                if ($fp) {
                    @fclose($fp);
                }
                return null;
            }
        } else {
            @fclose($fp);
            return null;
        }
    }
    @ftruncate($fp, 0);
    @fwrite($fp, strval(time()));
    @fflush($fp);
    return [$fp, $path];
}

function paymentReleaseLock($lock)
{
    if (!is_array($lock) || count($lock) < 2) {
        return;
    }
    $fp = $lock[0];
    $path = $lock[1];
    if (is_resource($fp)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
    if (!empty($path) && is_file($path)) {
        @unlink($path);
    }
}

/**
 * کسر اتمیک موجودی. در موفقیت موجودی جدید، در شکست false
 */
function atomicDeductBalance($user_id, $amount)
{
    global $pdo;
    $amount = intval($amount);
    if ($amount <= 0) {
        $row = select("user", "Balance", "id", $user_id, "select");
        return is_array($row) ? intval($row['Balance'] ?? 0) : 0;
    }
    try {
        $st = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND CAST(Balance AS SIGNED) >= :p2");
        $st->execute([':p' => $amount, ':id' => $user_id, ':p2' => $amount]);
        if ($st->rowCount() < 1) {
            return false;
        }
    } catch (Throwable $e) {
        return false;
    }
    $row = select("user", "Balance", "id", $user_id, "select");
    return is_array($row) ? intval($row['Balance'] ?? 0) : 0;
}

/**
 * افزایش اتمیک موجودی (برگشت وجه)
 */
function atomicAddBalance($user_id, $amount)
{
    global $pdo;
    $amount = intval($amount);
    if ($amount <= 0) {
        return 0;
    }
    try {
        $st = $pdo->prepare("UPDATE user SET Balance = Balance + :p WHERE id = :id");
        $st->execute([':p' => $amount, ':id' => $user_id]);
    } catch (Throwable $e) {
        $row = select("user", "Balance", "id", $user_id, "select");
        $cur = is_array($row) ? intval($row['Balance'] ?? 0) : 0;
        update("user", "Balance", $cur + $amount, "id", $user_id);
    }
    return $amount;
}



/**
 * متن قابل ویرایش از جدول textbot با fallback
 */
function ensureEditableStatusTexts()
{
    global $pdo, $textbotlang;
    $defaults = [
        'msg_buy_disabled' => $textbotlang['users']['sell']['buy_disabled'] ?? "⛔️ فعلا امکان خرید سرویس جدید وجود ندارد.\nلطفا بعداً دوباره تلاش کنید.",
        'msg_deposit_closed' => $textbotlang['users']['Balance']['deposit_closed'] ?? "⛔️ واریز به حساب بسته است.\nلطفاً بعداً تلاش کنید.",
        'msg_extend_disabled' => $textbotlang['users']['extend']['disabled'] ?? "⛔️ تمدید سرویس فعلاً غیرفعال است.",
        'msg_support_disabled' => $textbotlang['users']['support']['disabled'] ?? "⛔️ پشتیبانی فعلاً در دسترس نیست.\nلطفاً بعداً تلاش کنید.",
        'msg_support_prompt' => $textbotlang['users']['support']['sendmessageuser'] ?? "📨 پیام خود را برای پشتیبانی بنویسید و ارسال کنید.",
        'msg_support_sent' => $textbotlang['users']['support']['sendmessageadmin'] ?? "✅ پیام شما برای پشتیبانی ارسال شد.\nبه‌زودی از همین ربات پاسخ می‌گیرید.",
        'msg_receipt_sent' => $textbotlang['users']['Balance']['Send-receipt'] ?? "🚀 رسید ارسال شد\n\nپس از تأیید مدیریت، مبلغ به کیف پول واریز می‌شود.\nلطفاً صبور باشید و از ارسال دوباره رسید خودداری کنید.\n\nفقط در صورت عدم تایید تا یک ساعت، به پشتیبانی پیام بدید.",
        'msg_receipt_need_photo' => "📸 لطفاً <b>عکس رسید</b> را ارسال کنید.\nمتن پذیرفته نمی‌شود؛ فقط تصویر رسید را بفرستید.",
        'msg_reseller_request_info' => $textbotlang['users']['reseller_request']['info'] ?? "📋 <b>درخواست پنل نمایندگی</b>\n\nدر صورتی که ماهانه بیش از <b>۱۰۰ کاربر فعال</b> دارید، می‌توانید درخواست پنل نمایندگی ثبت کنید.\n\nبا دریافت پنل نمایندگی، امکان راه‌اندازی ربات فروشگاهی و اتصال آن به پنل فراهم می‌شود.\n\nبرای ثبت درخواست، از دکمه زیر پیام خود را برای پشتیبانی ارسال کنید.",
        'msg_affiliates_info' => $textbotlang['users']['affiliates']['infotext'] ?? "🤝 <b>مشارکت در فروش</b>\n\nلینک دعوت خود را برای دیگران بفرستید.\nبا خرید آن‌ها، طبق موارد زیر پورسانت به کیف پول شما واریز می‌شود.\n\n🎁 هدیه دعوت: <b>%s</b>\n📊 درصد پورسانت: <b>%s</b>\n\nاز دکمه زیر لینک دعوت خود را بگیرید.",
        'msg_phone_verify' => $textbotlang['users']['number']['Confirming'] ?? "📞 برای ادامه باید شماره موبایل خود را تأیید کنید.\n\nلطفاً این دو مرحله را انجام دهید:\n\n1️⃣ در پایین صفحه روی دکمه <b>☎️ ارسال شماره تلفن</b> بزنید\n2️⃣ در پنجره تلگرام روی <b>Share</b> / <b>اشتراک‌گذاری</b> بزنید\n\n⚠️ شماره را دستی تایپ نکنید؛ فقط از همان دکمه استفاده کنید.",
        'text_bot_off' => $textbotlang['users']['updatingbot'] ?? "❌ ربات در حال حاضر غیرفعال است.\nلطفاً بعداً مراجعه کنید.",
    ];
    foreach ($defaults as $id => $def) {
        try {
            $st = $pdo->prepare("SELECT id_text FROM textbot WHERE id_text = ? LIMIT 1");
            $st->execute([$id]);
            if (!$st->fetch()) {
                $ins = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (?, ?)");
                $ins->execute([$id, $def]);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}


