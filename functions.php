<?php
require_once 'vendor/autoload.php';
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

function ActiveVoucher($ev_number, $ev_code)
{
    global $connect;
    $Payer_Account = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_Payer_Account', "select")['ValuePay'];
    $AccountID = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_AccountID', "select")['ValuePay'];
    $PassPhrase = select("PaySetting", "ValuePay", "NamePay", 'perfectmoney_PassPhrase', "select")['ValuePay'];
    $opts = array(
        'socket' => array(
            'bindto' => 'ip',
        )
    );

    $context = stream_context_create($opts);

    $voucher = file_get_contents("https://perfectmoney.com/acct/ev_activate.asp?AccountID=" . $AccountID . "&PassPhrase=" . $PassPhrase . "&Payee_Account=" . $Payer_Account . "&ev_number=" . $ev_number . "&ev_code=" . $ev_code);
    return $voucher;
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

function nowPayments($payment, $price_amount, $order_id, $order_description)
{
    global $domainhosts;
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", 'apinowpayment', "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/' . $payment,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 4500,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => 1,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments,
            'Content-Type: application/json'
        ),
    ));
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'price_amount' => $price_amount,
        'price_currency' => 'usd',
        'pay_currency' => 'trx',
        'order_id' => $order_id,
        'order_description' => $order_description,
        'ipn_callback_url' => "https://" . $domainhosts . "/payment/nowpayments/back.php"
    ]));

    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}
function StatusPayment($paymentid)
{
    $apinowpayments = select("PaySetting", "ValuePay", "NamePay", 'apinowpayment', "select")['ValuePay'];
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.nowpayments.io/v1/payment/' . $paymentid,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'x-api-key:' . $apinowpayments
        ),
    ));
    $response = curl_exec($curl);
    $response = json_decode($response, true);
    curl_close($curl);
    return $response;
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

function getEditableBotText($id_text, $fallback = '')
{
    global $pdo;
    static $cache = [];
    $id_text = strval($id_text);
    if (array_key_exists($id_text, $cache)) {
        return $cache[$id_text];
    }
    try {
        $st = $pdo->prepare("SELECT text FROM textbot WHERE id_text = ? LIMIT 1");
        $st->execute([$id_text]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['text']) && strval($row['text']) !== '') {
            $cache[$id_text] = strval($row['text']);
            return $cache[$id_text];
        }
    } catch (Throwable $e) {
    }
    $cache[$id_text] = strval($fallback);
    return $cache[$id_text];
}


function refundBalanceIfDeducted($user_id, $amount, $balance_before = null)
{
    $amount = intval($amount);
    if ($amount <= 0) {
        return 0;
    }
    $row = select("user", "Balance", "id", $user_id, "select");
    if (!is_array($row)) {
        return 0;
    }
    $current = intval($row['Balance'] ?? 0);
    // اگر موجودی قبل داده شده و کم نشده → چیزی برنگردان
    if ($balance_before !== null) {
        $before = intval($balance_before);
        if ($current >= $before) {
            return 0;
        }
        // فقط به اندازه کسری واقعی (حداکثر amount)
        $need = min($amount, $before - $current);
        if ($need <= 0) {
            return 0;
        }
        update("user", "Balance", $current + $need, "id", $user_id);
        return $need;
    }
    // بدون balance_before: فرض بر این است که caller مطمئن است باید برگردد
    update("user", "Balance", $current + $amount, "id", $user_id);
    return $amount;
}

function createUserWithRetry($panel_name, $username_ac, array $datac, $is_test = false)
{
    global $ManagePanel;
    $max_panel_tries = 3;
    $max_username_tries = 3;
    $panel_try = 0;
    $username_try = 0;
    $current_username = $username_ac;
    $dataoutput = ['username' => null, 'msg' => '', 'status' => 'Unsuccessful'];

    while ($panel_try < $max_panel_tries && $username_try < $max_username_tries) {
        $dataoutput = $ManagePanel->createUser($panel_name, $current_username, $datac, $is_test);
        if (!is_array($dataoutput)) {
            $dataoutput = ['username' => null, 'msg' => 'Invalid panel response', 'status' => 'Unsuccessful'];
        }

        if (!empty($dataoutput['username'])) {
            $dataoutput['username_final'] = $dataoutput['username'];
            return $dataoutput;
        }

        $msg = $dataoutput['msg'] ?? '';
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $msg = (string) $msg;
        $msg_l = mb_strtolower($msg, 'UTF-8');

        // خطاهای موقتی پنل/اتصال → تا ۳ بار تلاش
        $is_panel_retryable = (
            stripos($msg, 'Panel Not Found') !== false
            || stripos($msg_l, 'panel not found') !== false
            || stripos($msg_l, 'پاسخ خالی') !== false
            || stripos($msg_l, 'پاسخ نامعتبر') !== false
            || stripos($msg_l, 'timeout') !== false
            || stripos($msg_l, 'timed out') !== false
            || stripos($msg_l, 'could not resolve') !== false
            || stripos($msg_l, 'connection') !== false
            || stripos($msg_l, 'curl') !== false
            || stripos($msg_l, 'failed to connect') !== false
            || stripos($msg_l, 'empty reply') !== false
            || stripos($msg, 'خطا در ارتباط') !== false
        );

        // فقط تکراری بودن یوزرنیم
        $is_user_duplicate = (
            strpos($msg_l, 'already') !== false
            || strpos($msg_l, 'exist') !== false
            || strpos($msg_l, 'duplicate') !== false
            || strpos($msg_l, 'taken') !== false
            || strpos($msg_l, 'in use') !== false
            || strpos($msg, 'وجود') !== false
            || strpos($msg, 'تکراری') !== false
            || strpos($msg, 'قبلا') !== false
        );

        if ($is_panel_retryable) {
            $panel_try++;
            if ($panel_try >= $max_panel_tries) {
                break;
            }
            // رفرش توکن پنل و ۵ ثانیه صبر قبل از تلاش بعدی (حداکثر ۳ بار)
            try {
                update("marzban_panel", "datelogin", null, "name_panel", $panel_name);
            } catch (Exception $e) {}
            usleep(800000); // ~0.8s instead of 5s — avoid worker freeze
            continue;
        }

        if ($is_user_duplicate) {
            $username_try++;
            if ($username_try >= $max_username_tries) {
                break;
            }
            $current_username = $current_username . random_int(10, 99);
            continue;
        }

        // هر ارور دیگری → همان‌جا قطع؛ caller مثل قبل (برگشت پول و ...)
        break;
    }

    $dataoutput['username_final'] = $current_username;
    return $dataoutput;
}

function removeReplyKeyboard($chat_id)
{
    // باید با sendmessage و remove_keyboard باشد تا کیبورد پایین تلگرام واقعاً حذف شود
    sendmessage($chat_id, "✅", json_encode(['remove_keyboard' => true]), 'HTML');
}


function outputlink($text)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $text);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        return "";
    } else {
        return $response;
    }

    curl_close($ch);
}

/**
 * پیام قطعی به کاربر بعد از تأیید رسید (دستی یا خودکار)
 * مستقل از مسیر خرید/شارژ — تا اگر پیام جزئیات fail شد حداقل تأیید برسد.
 */
function notifyUserReceiptAccepted($user_id, $order_id, $price = 0, $extra = '')
{
    // اگر DirectPayment قبلاً به کاربر پیام داده، دوباره نفرست
    if (!empty($GLOBALS['payment_user_notified'])) {
        return true;
    }
    global $textbotlang, $keyboard;
    $uid = intval($user_id);
    if ($uid <= 0) {
        return false;
    }
    $price_fmt = number_format(intval($price));
    $order_s = htmlspecialchars(strval($order_id), ENT_QUOTES, 'UTF-8');
    $tpl = $textbotlang['users']['moeny']['receipt_accepted'] ?? null;
    if (is_string($tpl) && strpos($tpl, '%s') !== false) {
        $msg = sprintf($tpl, $price_fmt, $order_s);
    } else {
        $msg = "✅ <b>رسید پرداخت شما تأیید شد</b>\n\n"
            . "💰 مبلغ: <b>{$price_fmt}</b> تومان\n"
            . "🛒 کد پیگیری: <code>{$order_s}</code>";
    }
    if (is_string($extra) && $extra !== '') {
        $msg .= "\n\n" . $extra;
    }
    try {
        $kb = isset($keyboard) ? $keyboard : null;
        sendmessage($uid, $msg, $kb, 'HTML');
        $GLOBALS['payment_user_notified'] = true;
        return true;
    } catch (Throwable $e) {
        error_log('notifyUserReceiptAccepted fail uid=' . $uid . ' order=' . $order_id . ' err=' . $e->getMessage());
        try {
            sendmessage($uid, $msg, null, 'HTML');
            $GLOBALS['payment_user_notified'] = true;
            return true;
        } catch (Throwable $e2) {
            error_log('notifyUserReceiptAccepted retry fail: ' . $e2->getMessage());
            return false;
        }
    }
}

function DirectPayment($order_id)
{
    global $pdo, $ManagePanel, $textbotlang, $keyboard, $from_id, $message_id, $callback_query_id;
    $setting = select("setting", "*");
    $admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    $format_price_cart = number_format($Payment_report['price']);
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $steppay = explode("|", $Payment_report['invoice']);
    if ($steppay[0] == "getconfigafterpay") {
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND (Status = 'unpaid' OR status = 'unpaid') LIMIT 1");
        $stmt->bindParam(':username', $steppay[1], PDO::PARAM_STR);
        $stmt->execute();
        $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$get_invoice || !is_array($get_invoice)) {
            // فاکتور unpaid پیدا نشد — فقط شارژ کیف (جلوگیری از fatal)
            error_log("DirectPayment getconfigafterpay: invoice not found for " . ($steppay[1] ?? ''));
            $uid = intval($Payment_report['id_user']);
            $credit_amount = function_exists('getPaymentCreditAmount') ? intval(getPaymentCreditAmount($Payment_report)) : intval($Payment_report['price']);
            $Balance_id = select("user", "*", "id", $uid, "select");
            $bal_now = intval($Balance_id['Balance'] ?? 0) + $credit_amount;
            update("user", "Balance", $bal_now, "id", $uid);
            update("Payment_report", "payment_Status", "paid", "id_order", $order_id);
            sendmessage($uid, "💰 مبلغ " . number_format($credit_amount) . " تومان به کیف پول واریز شد.\nسرویس به‌صورت خودکار ساخته نشد؛ از پشتیبانی پیگیری کنید.\n🛒 " . $order_id, null, 'HTML');
            $GLOBALS['payment_user_notified'] = true;
            return;
        }
        $username_ac = $get_invoice['username'];
        $randomString = bin2hex(random_bytes(2));
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $get_invoice['Service_location'], "select");
        $date = strtotime("+" . $get_invoice['Service_time'] . "days");
        if (intval($get_invoice['Service_time']) == 0) {
            $timestamp = 0;
        } else {
            $timestamp = strtotime(date("Y-m-d H:i:s", $date));
        }
        $datac = array(
            'expire' => $timestamp,
            'data_limit' => $get_invoice['Volume'] * pow(1024, 3),
        );
        $dataoutput = createUserWithRetry($marzban_list_get['name_panel'], $username_ac, $datac);
        if (!empty($dataoutput['username_final'])) {
            $username_ac = $dataoutput['username_final'];
        }

        if ($dataoutput['username'] == null) {
            $err_msg = formatPanelErrorMsg($dataoutput['msg'] ?? null);
            $pay_amount = intval($Payment_report['price']);
            $refunded = refundBalanceIfDeducted($Payment_report['id_user'], $pay_amount, null);
            update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
            $price_fmt = number_format($pay_amount);
            if ($refunded > 0) {
                $msg_user = "❌ در ساخت سرویس خطایی رخ داد.

"
                    . "💰 مبلغ {$price_fmt} تومان به کیف پول شما برگشت داده شد.
"
                    . "لطفاً دوباره از منو مراحل خرید را انجام دهید.

"
                    . "🛒 کد پیگیری: {$order_id}";
                $admin_extra = "

💰 مبلغ {$price_fmt} تومان به کیف پول کاربر برگشت داده شد.";
            } else {
                $msg_user = "❌ در ساخت سرویس خطایی رخ داد.

"
                    . "لطفاً دوباره تلاش کنید یا با پشتیبانی در ارتباط باشید.

"
                    . "🛒 کد پیگیری: {$order_id}";
                $admin_extra = "

ℹ️ مبلغی به کیف پول اضافه نشد.";
            }
            sendmessage($Balance_id['id'], $msg_user, $keyboard, 'HTML');
            $GLOBALS['payment_user_notified'] = true;
            $texterros = sprintf($textbotlang['users']['buy']['errorInCreate'], $err_msg, $Balance_id['id'], $Balance_id['username']);
            $texterros .= $admin_extra;
            foreach ($admin_ids as $admin) {
                sendmessage($admin, $texterros, null, 'HTML');
            }
            if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['create_error'])) {
                $refund_txt = ($refunded > 0) ? (number_format($refunded) . ' تومان برگشت شد') : 'برگشت وجه نداشت';
                $panel_n = $get_invoice['Service_location'] ?? '-';
                $uname_try = $get_invoice['username'] ?? ($dataoutput['username_final'] ?? '-');
                $ch_err = sprintf(
                    $textbotlang['Admin']['Report']['create_error'],
                    $Balance_id['id'],
                    $Balance_id['username'] ?? '-',
                    $uname_try,
                    $panel_n,
                    $err_msg,
                    $refund_txt,
                    'خرید پس از پرداخت / DirectPayment'
                );
                sendChannelReport('rpt_create_error', $ch_err);
            }
            return;
        }
        if (!empty($get_invoice['username']) && $get_invoice['username'] !== $username_ac) {
            update("invoice", "username", $username_ac, "id_invoice", $get_invoice['id_invoice']);
            $get_invoice['username'] = $username_ac;
        }
        $uid_buyer = intval($get_invoice['id_user'] ?? ($Balance_id['id'] ?? 0));
        $output_config_link = "";
        $config = "";
        $configqr = "";
        if (($marzban_list_get['sublink'] ?? '') == "onsublink") {
            $output_config_link = strval($dataoutput['subscription_url'] ?? '');
        }
        if (($marzban_list_get['configManual'] ?? '') == "onconfig") {
            if (isset($dataoutput['configs']) && is_array($dataoutput['configs']) && count($dataoutput['configs']) != 0) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= "\n" . $configs;
                    $configqr .= $configs;
                }
            }
        }
        if (($marzban_list_get['type'] ?? '') == "wgdashboard") {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservicewgbuy'], $dataoutput['username'], $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume']);
        } elseif (($marzban_list_get['type'] ?? '') == "mikrotik") {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservice_mikrotik_buy'], $dataoutput['username'], $dataoutput['subscription_url'] ?? '', $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume']);
        } else {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservice'], $dataoutput['username'], $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume'], $config, $output_config_link);
        }
        if ($output_config_link === '' && $config === '' && ($marzban_list_get['type'] ?? '') !== 'mikrotik') {
            $textcreatuser .= "\n\n⚠️ لینک اشتراک از پنل دریافت نشد. از «سرویس‌های من» وضعیت را بررسی کنید یا با پشتیبانی در تماس باشید.";
        }

        // ارسال کانفیگ به کاربر — همیشه با fallback متنی
        $__sent_cfg = false;
        try {
            if (($marzban_list_get['type'] ?? '') == "mikrotik") {
                sendmessage($uid_buyer, $textcreatuser, null, 'HTML');
                if (isset($keyboard)) {
                    sendmessage($uid_buyer, $textbotlang['users']['selectoption'] ?? 'منوی اصلی', $keyboard, 'HTML');
                }
                $__sent_cfg = true;
            } else {
                $qr_payload = '';
                if (($marzban_list_get['configManual'] ?? '') == "onconfig" && $configqr !== '') {
                    $qr_payload = $configqr;
                } elseif (($marzban_list_get['sublink'] ?? '') == "onsublink" && $output_config_link !== '') {
                    $qr_payload = $output_config_link;
                }
                if ($qr_payload !== '' && class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {
                    $urlimage = sys_get_temp_dir() . "/mirza_cfg_{$uid_buyer}_{$randomString}.png";
                    try {
                        $writer = new PngWriter();
                        $qrCode = QrCode::create($qr_payload)
                            ->setEncoding(new Encoding('UTF-8'))
                            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
                            ->setSize(400)
                            ->setMargin(0)
                            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);
                        $result = $writer->write($qrCode, null, null);
                        $result->saveToFile($urlimage);
                        $photo_res = telegram('sendphoto', [
                            'chat_id' => $uid_buyer,
                            'photo' => new CURLFile($urlimage),
                            'caption' => mb_substr($textcreatuser, 0, 1024),
                            'parse_mode' => "HTML",
                        ]);
                        @unlink($urlimage);
                        if (is_array($photo_res) && !empty($photo_res['ok'])) {
                            $__sent_cfg = true;
                        }
                        if (($marzban_list_get['type'] ?? '') == "wgdashboard" && $output_config_link !== '') {
                            $urldocs = sys_get_temp_dir() . "/wg_{$get_invoice['id_invoice']}.conf";
                            file_put_contents($urldocs, $output_config_link);
                            if (function_exists('sendDocument')) {
                                sendDocument($uid_buyer, $urldocs, $textbotlang['users']['buy']['configwg'] ?? 'config');
                            }
                            @unlink($urldocs);
                        }
                    } catch (Throwable $e_qr) {
                        error_log('DirectPayment QR: ' . $e_qr->getMessage());
                        if (isset($urlimage)) {
                            @unlink($urlimage);
                        }
                    }
                }
                if (!$__sent_cfg) {
                    sendmessage($uid_buyer, $textcreatuser, null, 'HTML');
                    $__sent_cfg = true;
                }
            }
        } catch (Throwable $e_send) {
            error_log('DirectPayment send config: ' . $e_send->getMessage());
            try {
                sendmessage($uid_buyer, $textcreatuser, null, 'HTML');
                $__sent_cfg = true;
            } catch (Throwable $e2) {
                error_log('DirectPayment sendmessage fallback failed: ' . $e2->getMessage());
            }
        }
        if ($__sent_cfg) {
            $GLOBALS['payment_user_notified'] = true;
        }
        if (!$__sent_cfg) {
            error_log("DirectPayment: config NOT sent to user {$uid_buyer} order={$order_id}");
            try {
                foreach ($admin_ids as $admin) {
                    sendmessage($admin, "⚠️ سرویس ساخته شد ولی پیام به کاربر <code>{$uid_buyer}</code> ارسال نشد.\n🔑 <code>{$username_ac}</code>\n🛒 {$order_id}", null, 'HTML');
                }
            } catch (Throwable $e) {
            }
        }
        $partsdic = explode("_", $Balance_id['Processing_value_four']);

        if ($partsdic[0] == "dis") {
            $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
            $value = intval($SellDiscountlimit['usedDiscount']) + 1;
            update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[1]);
            $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user,code) VALUES (:id_user,:code)");
            $stmt->bindParam(':id_user', $Balance_id['id']);
            $stmt->bindParam(':code', $partsdic[1]);
            $stmt->execute();
            $result = ($SellDiscountlimit['price'] / 100) * $get_invoice['price_product'];
            $pricediscount = $get_invoice['price_product'] - $result;
            $text_report = sprintf($textbotlang['users']['Report']['discountused'], $Balance_id['username'], $Balance_id['id'], $partsdic[1]);
            if (function_exists('sendChannelReport')) {
                sendChannelReport('rpt_discount', $text_report);
            } elseif (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'text' => $text_report,
                ]);
            }
        } else {
            $pricediscount = null;
        }
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        if (is_array($affiliatescommission)
            && (($affiliatescommission['status_commission'] ?? '') == "oncommission")
            && !empty($Balance_id['affiliates'])
            && intval($Balance_id['affiliates']) > 0
        ) {
            $aff_pct = floatval($affiliatescommission['affiliatespercentage'] ?? 0);
            $base_price = ($pricediscount === null)
                ? floatval($get_invoice['price_product'] ?? 0)
                : floatval($pricediscount);
            $result = ($base_price * $aff_pct) / 100.0;
            $result_int = intval(round($result));
            $aff_uid = intval($Balance_id['affiliates']);
            if ($result_int > 0 && $aff_uid > 0 && $aff_uid !== intval($Balance_id['id'] ?? 0)) {
                $user_Balance = select("user", "*", "id", $aff_uid, "select");
                if (is_array($user_Balance)) {
                    if (function_exists('atomicAddBalance')) {
                        atomicAddBalance($aff_uid, $result_int);
                    } else {
                        $Balance_prim = intval($user_Balance['Balance'] ?? 0) + $result_int;
                        update("user", "Balance", $Balance_prim, "id", $aff_uid);
                    }
                    if (function_exists("addAffiliatesBalance")) {
                        addAffiliatesBalance($aff_uid, $result_int);
                    }
                    $result_fmt = number_format($result_int);
                    $textadd = sprintf(
                        $textbotlang['users']['affiliates']['porsantuser'] ?? "🎁 پورسانت %s تومان واریز شد.",
                        $result_fmt
                    );
                    sendmessage($aff_uid, $textadd, null, 'HTML');
                    if (function_exists('sendChannelReport')) {
                        sendChannelReport(
                            'rpt_buy',
                            "🎁 <b>پورسانت مشارکت</b>
"
                            . "👤 معرف: <code>{$aff_uid}</code>
"
                            . "🛒 خریدار: <code>" . intval($Balance_id['id'] ?? 0) . "</code>
"
                            . "💰 مبلغ پورسانت: <b>{$result_fmt}</b> تومان
"
                            . "🔑 سرویس: <code>" . htmlspecialchars(strval($get_invoice['username'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code>"
                        );
                    }
                }
            }
        }
        $Balance_prims = intval($Balance_id['Balance']) - intval($get_invoice['price_product']);
        if ($Balance_prims <= 0)
            $Balance_prims = 0;
        update("user", "Balance", $Balance_prims, "id", $Balance_id['id']);
        $balanceformatsell = number_format($Balance_prims, 0);
        if (function_exists('logWalletTx')) {
            logWalletTx($Balance_id['id'], 'buy', intval($get_invoice['price_product']), $Balance_prims, 'خرید سرویس: ' . ($get_invoice['username'] ?? ''));
        }
        if (function_exists('recordSale')) {
            recordSale($get_invoice['id_user'], $get_invoice['price_product'], 'buy', $get_invoice['username'], $get_invoice['id_invoice'] ?? null);
        }
        $pay_amt_fmt = number_format(intval($Payment_report['price'] ?? 0));
        $buy_amt_fmt = number_format(intval($get_invoice['price_product']));
        $order_id_rep = $Payment_report['id_order'] ?? $order_id ?? '-';
        $active_svc_ap = function_exists('countUserActiveServices') ? countUserActiveServices($get_invoice['id_user']) : 0;
        $text_report = sprintf(
            $textbotlang['users']['Report']['reportbuyafterpay'],
            $get_invoice['username'],
            $buy_amt_fmt,
            $pay_amt_fmt,
            $get_invoice['Volume'],
            $get_invoice['id_user'],
            $Balance_id['username'] ?? '-',
            $active_svc_ap,
            $get_invoice['Service_location'],
            $balanceformatsell,
            $order_id_rep
        );
        if (function_exists('sendChannelReport')) {
            sendChannelReport('rpt_buy_after_pay', $text_report);
        } elseif (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        // همیشه با id_invoice تا بعد از تغییر یوزرنیم گم نشود
        if (!empty($get_invoice['id_invoice'])) {
            update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
            update("invoice", "status", "active", "id_invoice", $get_invoice['id_invoice']);
        } else {
            update("invoice", "Status", "active", "username", $username_ac);
            update("invoice", "status", "active", "username", $username_ac);
        }
    } elseif (($steppay[0] ?? '') === 'extendafterpay') {
        // تمدید بعد از تأیید رسید
        $ext_username = $steppay[1] ?? '';
        $ext_code = $steppay[2] ?? '';
        $uid = intval($Payment_report['id_user']);
        $credit_amount = function_exists('getPaymentCreditAmount') ? intval(getPaymentCreditAmount($Payment_report)) : intval($Payment_report['price']);
        // ۱) واریز مبلغ پرداختی
        $Balance_id = select("user", "*", "id", $uid, "select");
        $bal_now = intval($Balance_id['Balance'] ?? 0) + $credit_amount;
        update("user", "Balance", $bal_now, "id", $uid);
        if (function_exists('logWalletTx')) {
            logWalletTx($uid, 'deposit', $credit_amount, $bal_now, 'واریز برای تمدید سرویس: ' . $ext_username);
        }
        $nameloc = select("invoice", "*", "username", $ext_username, "select");
        $product = false;
        if ($nameloc && $ext_code !== '') {
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :Location OR location = '/all') AND code_product = :code_product LIMIT 1");
            $stmt->bindValue(':Location', $nameloc['Service_location']);
            $stmt->bindValue(':code_product', $ext_code);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$nameloc || !$product) {
            update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
            sendmessage($uid, "✅ مبلغ " . number_format($credit_amount) . " تومان به کیف پول واریز شد.\n⚠️ تمدید خودکار انجام نشد؛ از «سرویس‌های من» دوباره تمدید کنید.", null, 'HTML');
            return;
        }
        $price_ext = intval($product['price_product']);
        $bal_row = select("user", "Balance", "id", $uid, "select");
        $bal_now = is_array($bal_row) ? intval($bal_row['Balance'] ?? 0) : intval($bal_row);
        if ($bal_now < $price_ext) {
            update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
            sendmessage($uid, "✅ مبلغ " . number_format($credit_amount) . " تومان واریز شد.\n⚠️ موجودی برای تکمیل تمدید کافی نیست. از «سرویس‌های من» تمدید را دوباره بزنید.", null, 'HTML');
            return;
        }
        // ۲) کسر هزینه تمدید
        if (function_exists('atomicDeductBalance')) {
            $bal_after = atomicDeductBalance($uid, $price_ext);
            if ($bal_after === false) {
                update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
                sendmessage($uid, "✅ مبلغ واریز شد ولی کسر تمدید ممکن نشد. از «سرویس‌های من» تمدید کنید.", null, 'HTML');
                return;
            }
        } else {
            $bal_after = $bal_now - $price_ext;
            update("user", "Balance", $bal_after, "id", $uid);
        }
        if (function_exists('logWalletTx')) {
            logWalletTx($uid, 'renew', $price_ext, $bal_after, 'تمدید سرویس (پس از پرداخت): ' . $ext_username);
        }
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        try {
            if ($ManagePanel && $marzban_list_get) {
                $ManagePanel->ResetUserDataUsage($nameloc['Service_location'], $ext_username);
                $ptype = $marzban_list_get['type'] ?? '';
                if (intval($product['Service_time']) == 0) {
                    $newDate = 0;
                } else {
                    $newDate = strtotime(date("Y-m-d H:i:s", strtotime("+" . $product['Service_time'] . "day")));
                }
                $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
                if (in_array($ptype, ['marzban', 'pasarguard', 'marzneshin', 'Pasarguard', 'passtguard'], true) || true) {
                    $datam = ["expire" => $newDate, "data_limit" => $data_limit];
                    $ManagePanel->Modifyuser($ext_username, $nameloc['Service_location'], $datam);
                }
            }
            update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
            update("invoice", "Service_time", $product['Service_time'], "username", $ext_username);
            update("invoice", "Volume", $product['Volume_constraint'], "username", $ext_username);
            update("invoice", "price_product", $product['price_product'], "username", $ext_username);
            if (function_exists('resetSmartCronWarnings')) {
                resetSmartCronWarnings($ext_username, $nameloc['Service_location']);
            }
            if (function_exists('recordSale')) {
                recordSale($uid, $price_ext, 'renew', $ext_username, $nameloc['id_invoice'] ?? null);
            }
            $keyb = json_encode([
                'inline_keyboard' => [
                    [['text' => $textbotlang['users']['status']['backlist'] ?? 'سرویس‌های من', 'callback_data' => 'backorder']],
                    [['text' => $textbotlang['users']['status']['backservice'] ?? 'جزئیات سرویس', 'callback_data' => 'product_' . $ext_username]],
                ]
            ]);
            sendmessage($uid, $textbotlang['users']['extend']['thanks'] ?? '✅ تمدید با موفقیت انجام شد.', $keyb, 'HTML');
            $GLOBALS['payment_user_notified'] = true;
            $tg_name = $Balance_id['username'] ?? '-';
            $active_svc = function_exists('countUserActiveServices') ? countUserActiveServices($uid) : 0;
            $bal_fmt = number_format(intval(select("user", "Balance", "id", $uid, "select")['Balance'] ?? $bal_after));
            $text_report = sprintf(
                $textbotlang['Admin']['Report']['extend'],
                $ext_username,
                number_format($price_ext),
                $product['name_product'],
                $uid,
                $tg_name,
                $active_svc,
                $nameloc['Service_location'],
                $bal_fmt
            );
            if (function_exists('sendChannelReport')) {
                sendChannelReport('rpt_extend', $text_report);
            }
        } catch (Throwable $e) {
            // برگرداندن هزینه تمدید در صورت خطای پنل
            if (function_exists('atomicAddBalance')) {
                atomicAddBalance($uid, $price_ext);
            } else {
                $br = select("user", "Balance", "id", $uid, "select");
                $bv = is_array($br) ? intval($br['Balance'] ?? 0) : intval($br);
                update("user", "Balance", $bv + $price_ext, "id", $uid);
            }
            sendmessage($uid, "❌ تمدید با خطا مواجه شد. مبلغ تمدید به کیف پول برگشت.\nاز «سرویس‌های من» دوباره تلاش کنید.", null, 'HTML');
            error_log('extendafterpay fail: ' . $e->getMessage());
        }
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        if ($Payment_report['Payment_Method'] == "cart to cart" && !empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['moeny']['acceptedcart'] ?? 'تأیید شد',
                'show_alert' => true,
                'cache_time' => 5,
            ]);
        }
        // پاک کردن state کاربر
        update("user", "Processing_value_tow", "0", "id", $uid);
        update("user", "Processing_value", "0", "id", $uid);
    } elseif (($steppay[0] ?? '') === 'reseller_volume') {
        // خرید حجم پنل ریسلر بعد از تأیید رسید
        $uid = intval($Payment_report['id_user']);
        $plan_id = intval($steppay[1] ?? 0);
        $admin_name = isset($steppay[2]) ? trim(strval($steppay[2])) : null;
        if ($admin_name === '') {
            $admin_name = null;
        }
        $credit_amount = function_exists('getPaymentCreditAmount') ? intval(getPaymentCreditAmount($Payment_report)) : intval($Payment_report['price']);
        $Balance_id = select("user", "*", "id", $uid, "select");
        $bal_now = intval($Balance_id['Balance'] ?? 0) + $credit_amount;
        update("user", "Balance", $bal_now, "id", $uid);
        if (function_exists('logWalletTx')) {
            logWalletTx($uid, 'deposit', $credit_amount, $bal_now, 'واریز برای خرید حجم پنل ریسلر');
        }
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        if ($Payment_report['Payment_Method'] == "cart to cart" && !empty($callback_query_id)) {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['moeny']['acceptedcart'] ?? 'تأیید شد',
                'show_alert' => true,
                'cache_time' => 5,
            ]);
        }
        $credit_fmt = number_format($credit_amount);
        sendmessage($uid, sprintf($textbotlang['users']['moeny']['Charged.'] ?? "✅ مبلغ %s تومان به کیف پول واریز شد.\nکد: %s", $credit_fmt, $Payment_report['id_order']), null, 'HTML');
        $GLOBALS['payment_user_notified'] = true;
        // سپس خرید حجم
        if (!function_exists('resellerPurchaseVolume')) {
            $f = __DIR__ . '/reseller_volume.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (function_exists('resellerPurchaseVolume') && $plan_id > 0) {
            $result = resellerPurchaseVolume($uid, $plan_id, $admin_name);
            $msg = is_array($result) ? strval($result['message'] ?? '') : '❌ خطا در افزودن حجم';
            sendmessage($uid, $msg !== '' ? $msg : ( (!empty($result['ok'])) ? '✅ حجم اضافه شد.' : '❌ افزودن حجم ناموفق بود. موجودی در کیف پول است؛ دوباره خرید را انجام دهید.'), null, 'HTML');
            if (!empty($result['ok']) && function_exists('sendChannelReport')) {
                $pa = $admin_name ?: (function_exists('getUserPanelAdminUsername') ? getUserPanelAdminUsername($uid) : '');
                sendChannelReport(
                    'rpt_reseller_volume',
                    "📦 <b>خرید حجم پنل نمایندگی</b> (پس از پرداخت)\n\n"
                    . "👤 کاربر: <code>{$uid}</code>\n"
                    . "🛠 ادمین پنل: <code>" . htmlspecialchars(strval($pa), ENT_QUOTES, 'UTF-8') . "</code>\n"
                    . $msg
                );
            }
        } else {
            sendmessage($uid, '💰 موجودی شارژ شد. لطفاً دوباره از Reseller Panel حجم بخرید.', null, 'HTML');
        }
        update("user", "Processing_value_tow", "0", "id", $uid);
        update("user", "Processing_value", "0", "id", $uid);
    } else {
        $credit_amount = getPaymentCreditAmount($Payment_report);
        $Balance_confrim = intval($Balance_id['Balance']) + intval($credit_amount);
        update("user", "Balance", $Balance_confrim, "id", $Payment_report['id_user']);
        if (function_exists('logWalletTx')) {
            logWalletTx($Payment_report['id_user'], 'deposit', intval($credit_amount), $Balance_confrim, 'شارژ کیف پول');
        }
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        $credit_fmt = number_format($credit_amount, 0);
        $pay_fmt = number_format(intval($Payment_report['price']), 0);
        $format_price_cart = $credit_fmt;
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            telegram(
                'answerCallbackQuery',
                array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['moeny']['acceptedcart'],
                    'show_alert' => true,
                    'cache_time' => 5,
                )
            );
        }
        $textpay = sprintf($textbotlang['users']['moeny']['Charged.'], $credit_fmt, $Payment_report['id_order']);
        if ($credit_amount > intval($Payment_report['price'])) {
            $textpay .= "\n🎁 مبلغ پرداختی: {$pay_fmt} تومان — اعتبار واریزی: {$credit_fmt} تومان";
        }
        sendmessage($Payment_report['id_user'], $textpay, null, 'HTML');
        $GLOBALS['payment_user_notified'] = true;
    }
}
function savedata($type, $namefiled, $valuefiled)
{
    global $from_id;
    if ($type == "clear") {
        $datauser = [];
        $datauser[$namefiled] = $valuefiled;
        $data = json_encode($datauser);
        update("user", "Processing_value", $data, "id", $from_id);
    } elseif ($type == "save") {
        $userdata = select("user", "*", "id", $from_id, "select");
        $dataperevieos = json_decode($userdata['Processing_value'], true);
        $dataperevieos[$namefiled] = $valuefiled;
        update("user", "Processing_value", json_encode($dataperevieos), "id", $from_id);
    }
}
function sanitizeUserName($string)
{
    $forbiddenCharacters = ["'", "\"", "<", ">", "--", "#", ";", "\\", "%", "(", ")"];
    return str_replace($forbiddenCharacters, "", $string);
}
function checktelegramip()
{

    $telegram_ip_ranges = [
        ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
        ['lower' => '91.108.4.0', 'upper' => '91.108.7.255']
    ];
    $ip_dec = (float) sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
    $ok = false;
    foreach ($telegram_ip_ranges as $telegram_ip_range)
        if (!$ok) {
            $lower_dec = (float) sprintf("%u", ip2long($telegram_ip_range['lower']));
            $upper_dec = (float) sprintf("%u", ip2long($telegram_ip_range['upper']));
            if ($ip_dec >= $lower_dec and $ip_dec <= $upper_dec)
                $ok = true;
        }
    return $ok;

}
function generateAuthStr($length = 10)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
}
function channel($id_channel)
{
    global $from_id, $APIKEY;
    $channel_link = array();
    if(!$id_channel)return [];
    $response = telegram('getChatMember', [
        "chat_id" => "@$id_channel",
        "user_id" => $from_id,
    ]);
    // telegram() ممکن است false برگرداند (timeout/SSL)
    if (is_array($response) && !empty($response['ok'])) {
        $status = $response['result']['status'] ?? '';
        if (!in_array($status, ['member', 'creator', 'administrator'])) {
            $channel_link[] = $id_channel;
        }
    }
    if (count($channel_link) == 0) {
        return [];
    } else {
        return $channel_link;
    }
}


/** پکیج‌های افزایش موجودی: [{id, amount, discount, title}] */
function getBalancePackages()
{
    ensurePaySetting('balance_packages', '[]');
    $raw = getPaySettingValue('balance_packages', '[]');
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $amount = intval($row['amount'] ?? 0);
        $discount = floatval($row['discount'] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        if ($discount < 0) {
            $discount = 0;
        }
        if ($discount > 90) {
            $discount = 90;
        }
        $out[] = [
            'id' => strval($row['id']),
            'amount' => $amount,
            'discount' => $discount,
            'title' => strval($row['title'] ?? ''),
        ];
    }
    return $out;
}

function saveBalancePackages(array $list)
{
    ensurePaySetting('balance_packages', '[]');
    update("PaySetting", "ValuePay", json_encode(array_values($list), JSON_UNESCAPED_UNICODE), "NamePay", "balance_packages");
}

function getBalancePackageById($id)
{
    foreach (getBalancePackages() as $p) {
        if ($p['id'] === strval($id)) {
            return $p;
        }
    }
    return null;
}

/** مبلغ پرداختی پکیج پس از تخفیف */
function getBalancePackagePayAmount($amount, $discount)
{
    $amount = floatval($amount);
    $discount = floatval($discount);
    if ($discount < 0) {
        $discount = 0;
    }
    if ($discount > 90) {
        $discount = 90;
    }
    return max(1, intval(round($amount * (100.0 - $discount) / 100.0)));
}

/** مبلغ اعتبار نهایی که باید به کیف پول اضافه شود */


/**
 * ارسال مجدد رسیدهای payment_Status=waiting برای ادمین
 * @return array{ok:bool,count:int,msg:string}
 */
function resendWaitingPaymentReceipts($admin_id, $limit = 40)
{
    global $pdo, $textbotlang, $username;
    $admin_id = intval($admin_id);
    $limit = max(1, min(80, intval($limit)));
    if ($admin_id <= 0) {
        return ['ok' => false, 'count' => 0, 'msg' => 'ادمین نامعتبر'];
    }
    try {
        $st = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' ORDER BY id ASC LIMIT " . intval($limit));
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['ok' => false, 'count' => 0, 'msg' => 'خطا در خواندن دیتابیس: ' . $e->getMessage()];
    }
    if (!$rows) {
        return ['ok' => true, 'count' => 0, 'msg' => 'هیچ رسید در انتظار تأییدی نیست.'];
    }
    $sent = 0;
    foreach ($rows as $pr) {
        if (!is_array($pr)) {
            continue;
        }
        $order = strval($pr['id_order'] ?? '');
        $uid = intval($pr['id_user'] ?? 0);
        $price = intval($pr['price'] ?? 0);
        $price_fmt = number_format($price);
        $method = strval($pr['Payment_Method'] ?? 'cart to cart');
        $time = strval($pr['time'] ?? '-');
        $invoice = strval($pr['invoice'] ?? '');
        $u = select("user", "*", "id", $uid, "select");
        $uname = '';
        if (is_array($u) && !empty($u['username'])) {
            $uname = '@' . $u['username'];
        }
        $active_svc = function_exists('countUserActiveServices') ? countUserActiveServices($uid) : 0;
        $desc = function_exists('describePaymentReport') ? describePaymentReport($pr) : null;
        $type_line = is_array($desc) ? strval($desc['type_label'] ?? 'پرداخت') : 'پرداخت';
        $method_lbl = is_array($desc) ? strval($desc['method_label'] ?? $method) : $method;
        $credit_fmt = is_array($desc) ? strval($desc['credit_fmt'] ?? $price_fmt) : $price_fmt;

        $text = "🧾 <b>رسید در انتظار تأیید</b> (ارسال مجدد)\n\n"
            . "💰 مبلغ رسید: <b>{$price_fmt}</b> تومان\n"
            . "💎 اعتبار: <b>{$credit_fmt}</b> تومان\n"
            . "👤 کاربر: <a href=\"tg://user?id={$uid}\">{$uid}</a>"
            . ($uname !== '' ? " ({$uname})" : '') . "\n"
            . "📦 سرویس فعال: <b>{$active_svc}</b>\n"
            . "🔖 کد: <code>{$order}</code>\n"
            . "💳 روش: {$method_lbl}\n"
            . "🕐 زمان: <code>{$time}</code>\n"
            . "📌 {$type_line}";

        $kb = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => ($textbotlang['users']['Balance']['Confirmpaying'] ?? '✅ تأیید'), 'callback_data' => "Confirm_pay_{$order}"],
                    ['text' => ($textbotlang['users']['Balance']['reject_pay'] ?? '❌ رد'), 'callback_data' => "reject_pay_{$order}"],
                ],
                [
                    ['text' => '👤 اطلاعات کاربر', 'callback_data' => "userinfo_pay_{$uid}"],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        try {
            sendmessage($admin_id, $text, $kb, 'HTML');
            $sent++;
            // کمی فاصله برای جلوگیری از flood تلگرام
            if ($sent % 5 === 0) {
                usleep(300000);
            }
        } catch (Throwable $e) {
            error_log('resendWaitingPaymentReceipts: ' . $e->getMessage());
        }
    }
    return [
        'ok' => true,
        'count' => $sent,
        'msg' => "✅ تعداد <b>{$sent}</b> رسید در انتظار تأیید دوباره ارسال شد.",
    ];
}

function describePaymentReport($Payment_report)
{
    $method_raw = strval($Payment_report['Payment_Method'] ?? 'cart to cart');
    $method_low = mb_strtolower($method_raw);
    if ($method_low === 'crypto' || strpos($method_low, 'crypto') !== false || strpos($method_low, 'iranpay') !== false || strpos($method_low, 'currency') !== false) {
        $method_label = '🪙 کریپتو';
    } else {
        $method_label = '💳 کارت‌به‌کارت';
    }
    $invoice = strval($Payment_report['invoice'] ?? '');
    $parts = explode('|', $invoice);
    $type_label = 'افزایش موجودی (مبلغ دلخواه)';
    $pay_amount = intval($Payment_report['price'] ?? 0);
    $credit_amount = $pay_amount;
    $is_package = false;
    if (($parts[0] ?? '') === 'balpkg' && isset($parts[1]) && intval($parts[1]) > 0) {
        $is_package = true;
        $credit_amount = intval($parts[1]);
        $disc = 0;
        if ($pay_amount > 0 && $credit_amount > $pay_amount) {
            $disc = round((1 - ($pay_amount / $credit_amount)) * 100, 1);
        }
        $type_label = '🎁 پکیج افزایش موجودی';
        if ($disc > 0) {
            $type_label .= " (تخفیف {$disc}٪)";
        }
    } elseif (($parts[0] ?? '') === 'getconfigafterpay') {
        $uname = $parts[1] ?? '-';
        $type_label = "🛒 پرداخت برای خرید سرویس\n   └ نام کاربری سرویس: <code>{$uname}</code>";
    }
    if (function_exists('getPaymentCreditAmount')) {
        $credit_amount = intval(getPaymentCreditAmount($Payment_report));
    }
    return [
        'method_label' => $method_label,
        'type_label' => $type_label,
        'pay_amount' => $pay_amount,
        'credit_amount' => $credit_amount,
        'pay_fmt' => number_format($pay_amount),
        'credit_fmt' => number_format($credit_amount),
        'is_package' => $is_package,
    ];
}

function getPaymentCreditAmount($Payment_report)
{
    $invoice = strval($Payment_report['invoice'] ?? '');
    $parts = explode('|', $invoice);
    if (($parts[0] ?? '') === 'balpkg' && isset($parts[1]) && intval($parts[1]) > 0) {
        return intval($parts[1]);
    }
    return intval($Payment_report['price'] ?? 0);
}


/**
 * تعداد سرویس‌های فعال/نمایش‌داده‌شده کاربر در «سرویس‌های من»
 */
function countUserActiveServices($user_id)
{
    global $pdo;
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return 0;
    }
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn')");
        $st->execute([$user_id]);
        return intval($st->fetchColumn());
    } catch (Throwable $e) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND status = 'active'");
            $st->execute([$user_id]);
            return intval($st->fetchColumn());
        } catch (Throwable $e2) {
            return 0;
        }
    }
}



/**
 * لیست روش‌های پرداخت فعال (کارت / کریپتو)
 * @return array [['id'=>'cart_to_offline','label'=>...], ...]
 */
function getActivePaymentMethods()
{
    global $textbotlang;
    $methods = [];
    $card = select("PaySetting", "ValuePay", "NamePay", 'Cartstatus', "select");
    $card_v = is_array($card) ? ($card['ValuePay'] ?? '') : '';
    if ($card_v === 'oncard') {
        $methods[] = [
            'id' => 'cart_to_offline',
            'label' => $textbotlang['users']['moeny']['cart_to_Cart_btn'] ?? '💳 کارت به کارت',
        ];
    }
    $digi = select("PaySetting", "ValuePay", "NamePay", 'digistatus', "select");
    $digi_v = is_array($digi) ? ($digi['ValuePay'] ?? '') : '';
    if ($digi_v === 'ondigi') {
        $label = function_exists('getCurrencyPaymentButtonText')
            ? getCurrencyPaymentButtonText()
            : ($textbotlang['users']['moeny']['currency_rial_gateway'] ?? '💎 کریپتو');
        $methods[] = [
            'id' => 'iranpay',
            'label' => $label,
        ];
    }
    return $methods;
}

/**
 * کیبورد انتخاب روش پرداخت بر اساس درگاه‌های روشن
 */
function buildPaymentMethodsKeyboard()
{
    global $textbotlang;
    $kb = ['inline_keyboard' => []];
    foreach (getActivePaymentMethods() as $m) {
        $kb['inline_keyboard'][] = [
            ['text' => $m['label'], 'callback_data' => $m['id']],
        ];
    }
    $kb['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['closelist'] ?? '❌ بستن', 'callback_data' => 'closelist'],
    ];
    return json_encode($kb);
}

/**
 * اجرای مستقیم یک روش پرداخت (کارت یا کریپتو)
 */

/**
 * جزئیات مبلغ قابل پرداخت وقتی کمبود موجودی (ریسلر و مشابه)
 * برای نمایش زیر/بالای فاکتور کارت یا کریپتو
 */
function buildPaymentAmountContextNote($user)
{
    if (!is_array($user)) {
        return '';
    }
    $tow = strval($user['Processing_value_tow'] ?? '');
    $pay = intval($user['Processing_value'] ?? 0);
    $bal = intval($user['Balance'] ?? 0);
    if ($pay <= 0) {
        return '';
    }
    // خرید حجم پنل ریسلر با کمبود موجودی
    if (strpos($tow, 'reseller_volume|') === 0) {
        $parts = explode('|', $tow);
        $plan_id = intval($parts[1] ?? 0);
        $admin = trim(strval($parts[2] ?? ''));
        $plan_price = 0;
        $vol_lbl = '';
        if ($plan_id > 0) {
            if (!function_exists('resellerGetPlan')) {
                $f = __DIR__ . '/reseller_volume.php';
                if (is_file($f)) {
                    require_once $f;
                }
            }
            if (function_exists('resellerGetPlan')) {
                $pl = resellerGetPlan($plan_id);
                if ($pl) {
                    $plan_price = intval($pl['price'] ?? 0);
                    $gb = intval($pl['volume_gb'] ?? 0);
                    $vol_lbl = function_exists('formatResellerVolumeLabel')
                        ? formatResellerVolumeLabel($gb)
                        : (number_format($gb) . ' گیگ');
                }
            }
        }
        if ($plan_price <= 0) {
            $plan_price = $bal + $pay; // تقریب
        }
        $lines = ["📦 <b>جزئیات پرداخت — خرید حجم پنل</b>"];
        if ($vol_lbl !== '') {
            $lines[] = "📊 حجم: <b>{$vol_lbl}</b>";
        }
        if ($admin !== '') {
            $lines[] = "👤 ادمین پنل: <code>" . htmlspecialchars($admin, ENT_QUOTES, 'UTF-8') . "</code>";
        } elseif (function_exists('getUserPanelAdminUsernames')) {
            $all = getUserPanelAdminUsernames(intval($user['id'] ?? 0));
            if ($all) {
                $lines[] = "👤 ادمین(های) پنل: <code>" . htmlspecialchars(implode(', ', $all), ENT_QUOTES, 'UTF-8') . "</code>";
            }
        }
        $lines[] = "💵 مبلغ کل پلن: <b>" . number_format($plan_price) . "</b> تومان";
        $lines[] = "💰 موجودی فعلی: <b>" . number_format($bal) . "</b> تومان";
        $lines[] = "💳 مبلغ قابل پرداخت: <b>" . number_format($pay) . "</b> تومان";
        $lines[] = "";
        return implode("\n", $lines);
    }
    return '';
}

function executePaymentMethod($from_id, $method_id, $message_id = null)
{
    global $textbotlang, $keyboard, $backuser, $setting, $user;

    $user = select("user", "*", "id", $from_id, "select");
    if (!is_array($user)) {
        return false;
    }
    $message_id = $message_id ? intval($message_id) : 0;

    if ($method_id === 'cart_to_offline') {
        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDescription", "select");
        $PaySetting = is_array($PaySetting) ? ($PaySetting['ValuePay'] ?? '') : '';
        $Processing_value = number_format(intval($user['Processing_value'] ?? 0));
        $textcart = sprintf($textbotlang['users']['moeny']['carttext'], $Processing_value, $PaySetting);
        $ctx = function_exists('buildPaymentAmountContextNote') ? buildPaymentAmountContextNote($user) : '';
        if ($ctx !== '') {
            $textcart = $ctx . $textcart;
        }
        preg_match_all('/\d+/', $PaySetting, $Matches);
        if (!empty($Matches[0]) && intval($setting['copy_cart'] ?? 0) == 1) {
            $card_number = implode('', $Matches[0]);
            $KEYBOARD = json_encode([
                "inline_keyboard" => [
                    [
                        ['text' => $textbotlang['users']['moeny']['copy_card_number'], 'copy_text' => ['text' => $card_number]],
                        ['text' => $textbotlang['users']['moeny']['copy_price'], 'copy_text' => ['text' => strval($user['Processing_value'])]],
                    ],
                    [
                        ['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت به منوی اصلی', 'callback_data' => 'backuser'],
                    ],
                ],
            ]);
        } else {
            $KEYBOARD = json_encode([
                "inline_keyboard" => [
                    [['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت به منوی اصلی', 'callback_data' => 'backuser']],
                ],
            ]);
        }
        if ($message_id > 0) {
            Editmessagetext($from_id, $message_id, $textcart, $KEYBOARD);
        } else {
            sendmessage($from_id, $textcart, $KEYBOARD, 'HTML');
        }
        step('cart_to_cart_user', $from_id);
        return true;
    }

    if ($method_id === 'iranpay') {
        $amount_toman = intval($user['Processing_value'] ?? 0);
        $built = buildCurrencyPaymentText($amount_toman);
        if (!$built['ok']) {
            $err = (($built['error'] ?? '') === 'rate')
                ? ($textbotlang['users']['moeny']['currency_rate_error'] ?? 'خطا در دریافت نرخ')
                : ($textbotlang['users']['moeny']['currency_empty'] ?? 'ارز فعال نیست');
            $kb_err = json_encode([
                "inline_keyboard" => [
                    [['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت', 'callback_data' => 'backuser']],
                ],
            ]);
            if ($message_id > 0) {
                Editmessagetext($from_id, $message_id, $err, $kb_err);
            } else {
                sendmessage($from_id, $err, $keyboard, 'HTML');
            }
            step('home', $from_id);
            return false;
        }
        $kb_crypto = json_encode([
            "inline_keyboard" => [
                [['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت به منوی اصلی', 'callback_data' => 'backuser']],
            ],
        ]);
        $ctx = function_exists('buildPaymentAmountContextNote') ? buildPaymentAmountContextNote($user) : '';
        $crypto_body = ($ctx !== '' ? $ctx : '') . $built['text'];
        if ($message_id > 0) {
            Editmessagetext($from_id, $message_id, $crypto_body, $kb_crypto);
        } else {
            sendmessage($from_id, $crypto_body, $kb_crypto, 'HTML');
        }
        step('crypto_receipt_user', $from_id);
        return true;
    }
    return false;
}

/**
 * شروع جریان پرداخت:
 * 0 روش → پیام خطا
 * 1 روش → مستقیم همان
 * 2+ → کیبورد انتخاب
 *
 * @param string $intro_text متن راهنما وقتی چند روش هست / کمبود موجودی و ...
 * @return string 'none'|'single'|'multi'
 */
function presentPaymentMethods($from_id, $intro_text = null, $message_id = null)
{
    global $textbotlang, $keyboard;

    $methods = getActivePaymentMethods();
    $n = count($methods);
    $message_id = $message_id ? intval($message_id) : 0;

    if ($n === 0) {
        $msg = $textbotlang['users']['moeny']['no_payment_method']
            ?? "⚠️ در حال حاضر هیچ روش پرداختی فعال نیست.\nلطفاً با پشتیبانی در تماس باشید.";
        $kb_home = json_encode([
            'inline_keyboard' => [
                [['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت', 'callback_data' => 'backuser']],
            ],
        ]);
        if ($message_id > 0) {
            Editmessagetext($from_id, $message_id, $msg, $kb_home);
        } else {
            sendmessage($from_id, $msg, $keyboard, 'HTML');
        }
        step('home', $from_id);
        return 'none';
    }

    if ($n === 1) {
        // مستقیم همان روش — جایگزین همان پیام (پیش‌فاکتور)
        executePaymentMethod($from_id, $methods[0]['id'], $message_id > 0 ? $message_id : null);
        return 'single';
    }

    // چند روش → فقط ویرایش همان پیام
    $text = $intro_text;
    if ($text === null || $text === '') {
        $text = $textbotlang['users']['Balance']['selectPatment'] ?? '💵 روش پرداخت خود را انتخاب نمایید';
    }
    $kb = buildPaymentMethodsKeyboard();
    if ($message_id > 0) {
        Editmessagetext($from_id, $message_id, $text, $kb);
    } else {
        sendmessage($from_id, $text, $kb, 'HTML');
    }
    step('get_step_payment', $from_id);
    return 'multi';
}


function buildBalancePackageUserKeyboard()
{
    global $textbotlang;
    $rows = [];
    $packages = getBalancePackages();
    // صفحه اول: فقط مبلغ دلخواه + ورود به لیست پکیج‌ها
    $rows[] = [['text' => $textbotlang['users']['Balance']['custom_amount_btn'] ?? '✍️ مبلغ دلخواه', 'callback_data' => 'balpkg_custom']];
    if (count($packages) > 0) {
        $rows[] = [['text' => $textbotlang['users']['Balance']['packages_btn'] ?? '🎁 پکیج‌های واریزی تخفیف‌دار', 'callback_data' => 'balpkg_list']];
    }
    $rows[] = [['text' => $textbotlang['users']['backhome'] ?? '🏠 منوی اصلی', 'callback_data' => 'backuser']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

/** لیست پکیج‌ها (بعد از زدن دکمه پکیج‌های تخفیف‌دار) */
function buildBalancePackageListKeyboard()
{
    global $textbotlang;
    $rows = [];
    foreach (getBalancePackages() as $p) {
        $pay = getBalancePackagePayAmount($p['amount'], $p['discount']);
        $disc = rtrim(rtrim(number_format($p['discount'], 1, '.', ''), '0'), '.');
        $label = $p['title'] !== '' ? $p['title'] . ' — ' : '';
        $label .= formatToman($p['amount']) . 'ت';
        if ($p['discount'] > 0) {
            $label .= " | 🎁 {$disc}٪ | پرداخت " . formatToman($pay) . 'ت';
        }
        $rows[] = [['text' => $label, 'callback_data' => 'balpkg_' . $p['id']]];
    }
    $rows[] = [['text' => $textbotlang['users']['Balance']['back_to_deposit'] ?? '🔙 بازگشت', 'callback_data' => 'balpkg_menu']];
    $rows[] = [['text' => $textbotlang['users']['backhome'] ?? '🏠 منوی اصلی', 'callback_data' => 'backuser']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function buildBalancePackageAdminKeyboard()
{
    global $textbotlang;
    $rows = [];
    foreach (getBalancePackages() as $p) {
        $disc = rtrim(rtrim(number_format($p['discount'], 1, '.', ''), '0'), '.');
        $rows[] = [[
            'text' => formatToman($p['amount']) . "ت | {$disc}٪ تخفیف",
            'callback_data' => 'balpkgadm_del_' . $p['id'],
        ]];
    }
    $rows[] = [['text' => '➕ افزودن پکیج', 'callback_data' => 'balpkgadm_add']];
    $rows[] = [['text' => $textbotlang['Admin']['Back-Adminment'] ?? 'بازگشت', 'callback_data' => 'balpkgadm_back']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}


/** تنظیمات کرون هوشمند */


/**
 * جدول لاگ تغییرات کیف پول
 */

/**
 * پنل یکپارچه تنظیمات مشارکت در فروش (ادمین)
 */
function buildAffiliatesAdminPanel()
{
    global $textbotlang;
    $aff = select("affiliates", "*", null, null, "select");
    if (!is_array($aff)) {
        $aff = [];
    }
    $on_lbl = '✅ روشن';
    $off_lbl = '❌ خاموش';
    $sys_on = (($aff['affiliatesstatus'] ?? '') === 'onaffiliates');
    $com_on = (($aff['status_commission'] ?? '') === 'oncommission');
    $gift_on = (($aff['Discount'] ?? '') === 'onDiscountaffiliates');
    $pct = intval($aff['affiliatespercentage'] ?? 0);
    $gift_price = number_format(intval($aff['price_Discount'] ?? 0));
    $has_banner = !empty($aff['id_media']) && $aff['id_media'] !== 'none';

    $text = "👥 <b>تنظیمات مشارکت در فروش</b>\n\n"
        . "از این صفحه همه گزینه‌ها را یکجا مدیریت کنید.\n\n"
        . "📊 <b>وضعیت فعلی</b>\n"
        . "• سیستم مشارکت در فروش: " . ($sys_on ? $on_lbl : $off_lbl) . "\n"
        . "• پورسانت بعد از خرید: " . ($com_on ? $on_lbl : $off_lbl) . "\n"
        . "• هدیه استارت برای معرف: " . ($gift_on ? $on_lbl : $off_lbl) . "\n"
        . "• درصد پورسانت: <b>{$pct}٪</b>\n"
        . "• مبلغ هدیه استارت: <b>{$gift_price}</b> تومان\n"
        . "• بنر: " . ($has_banner ? '✅ تنظیم شده' : '❌ تنظیم نشده');

    $kb = [
        'inline_keyboard' => [
            [
                ['text' => $sys_on ? $on_lbl : $off_lbl, 'callback_data' => 'affpanel_toggle_system'],
                ['text' => 'سیستم مشارکت در فروش', 'callback_data' => 'affpanel_noop'],
            ],
            [
                ['text' => $com_on ? $on_lbl : $off_lbl, 'callback_data' => 'affpanel_toggle_commission'],
                ['text' => 'پورسانت بعد خرید', 'callback_data' => 'affpanel_noop'],
            ],
            [
                ['text' => $gift_on ? $on_lbl : $off_lbl, 'callback_data' => 'affpanel_toggle_gift'],
                ['text' => 'هدیه استارت معرف', 'callback_data' => 'affpanel_noop'],
            ],
            [
                ['text' => "🧮 درصد پورسانت ({$pct}٪)", 'callback_data' => 'affpanel_set_pct'],
            ],
            [
                ['text' => "🌟 مبلغ هدیه استارت ({$gift_price})", 'callback_data' => 'affpanel_set_giftprice'],
            ],
            [
                ['text' => $has_banner ? '🏞 تغییر بنر مشارکت در فروش' : '🏞 تنظیم بنر مشارکت در فروش', 'callback_data' => 'affpanel_set_banner'],
            ],
            [
                ['text' => '👥 کاربران فعال در طرح', 'callback_data' => 'affpanel_active_users'],
            ],
            [
                ['text' => '🔄 بروزرسانی', 'callback_data' => 'affpanel_refresh'],
            ],
        ]
    ];
    return [$text, json_encode($kb)];
}


function ensureWalletLog()
{
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_user BIGINT NOT NULL,
            type VARCHAR(32) NOT NULL,
            amount INT NOT NULL DEFAULT 0,
            balance_after INT DEFAULT NULL,
            detail VARCHAR(500) DEFAULT NULL,
            created_at INT NOT NULL,
            INDEX (id_user),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log('ensureWalletLog: ' . $e->getMessage());
    }
}

/**
 * ثبت تراکنش کیف پول
 * type: deposit | admin_add | admin_low | buy | renew | extra_volume | refund | affiliate
 * amount: همیشه مثبت؛ جهت از روی type مشخص می‌شود
 */
function logWalletTx($id_user, $type, $amount, $balance_after = null, $detail = '')
{
    global $pdo;
    ensureWalletLog();
    $id_user = intval($id_user);
    $amount = abs(intval($amount));
    if ($id_user <= 0 || $amount <= 0) {
        return;
    }
    try {
        $st = $pdo->prepare("INSERT INTO wallet_log (id_user, type, amount, balance_after, detail, created_at) VALUES (?,?,?,?,?,?)");
        $st->execute([
            $id_user,
            strval($type),
            $amount,
            $balance_after !== null ? intval($balance_after) : null,
            mb_substr(strval($detail), 0, 500),
            time()
        ]);
    } catch (Exception $e) {
        error_log('logWalletTx: ' . $e->getMessage());
    }
}


function ensureSalesLedger()
{
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sales_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_user BIGINT NOT NULL,
            username VARCHAR(191) DEFAULT NULL,
            price INT NOT NULL DEFAULT 0,
            sale_type VARCHAR(32) NOT NULL DEFAULT 'buy',
            id_invoice VARCHAR(64) DEFAULT NULL,
            created_at INT NOT NULL,
            INDEX (created_at),
            INDEX (id_user),
            INDEX (sale_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log('ensureSalesLedger: ' . $e->getMessage());
    }
}

function recordSale($id_user, $price, $sale_type = 'buy', $username = null, $id_invoice = null)
{
    global $pdo;
    ensureSalesLedger();
    $price = intval($price);
    if ($price <= 0) {
        return;
    }
    // یکسان‌سازی نوع فروش برای جلوگیری از ردیف‌های هم‌معنی با نام متفاوت
    $sale_type = strval($sale_type);
    if ($sale_type === 'extend') {
        $sale_type = 'renew';
    } elseif ($sale_type === 'extra') {
        $sale_type = 'extra_volume';
    }
    try {
        $st = $pdo->prepare("INSERT INTO sales_ledger (id_user, username, price, sale_type, id_invoice, created_at) VALUES (?,?,?,?,?,?)");
        $st->execute([intval($id_user), $username, $price, $sale_type, $id_invoice, time()]);
    } catch (Exception $e) {
        error_log('recordSale: ' . $e->getMessage());
    }
}

function salesLedgerSum($since_ts = 0, $until_ts = null)
{
    global $pdo;
    ensureSalesLedger();
    if ($until_ts === null) {
        $until_ts = time() + 10;
    }
    $st = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(price),0) AS sm FROM sales_ledger WHERE created_at > ? AND created_at <= ?");
    $st->execute([intval($since_ts), intval($until_ts)]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['cnt' => intval($r['cnt'] ?? 0), 'sum' => intval($r['sm'] ?? 0)];
}

function ensureAffiliatesBalanceColumn()
{
    global $pdo;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM user LIKE 'affiliates_balance'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD COLUMN affiliates_balance INT NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {}
}


/**
 * لیست کاربرانی که از مشارکت در فروش پورسانت گرفته‌اند
 * @return array [text, keyboard_json]
 */
function listAffiliatesActiveUsersPanel($page = 0)
{
    global $pdo;
    ensureAffiliatesBalanceColumn();
    $page = max(0, intval($page));
    $per = 15;
    $offset = $page * $per;

    $total = 0;
    $rows = [];
    try {
        $total = intval($pdo->query("SELECT COUNT(*) FROM user WHERE affiliates_balance > 0")->fetchColumn());
        $st = $pdo->prepare(
            "SELECT id, username, Balance, affiliates_balance, affiliates
             FROM user
             WHERE affiliates_balance > 0
             ORDER BY affiliates_balance DESC
             LIMIT " . intval($per) . " OFFSET " . intval($offset)
        );
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [
            "❌ خطا در دریافت لیست:\n<code>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</code>",
            json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'affpanel_refresh']]]], JSON_UNESCAPED_UNICODE),
        ];
    }

    if ($total <= 0) {
        $text = "👥 <b>کاربران فعال در طرح</b>\n\n"
            . "هنوز هیچ کاربری پورسانت دریافت نکرده است.";
        $kb = json_encode([
            'inline_keyboard' => [
                [['text' => '🔙 بازگشت به تنظیمات', 'callback_data' => 'affpanel_refresh']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        return [$text, $kb];
    }

    $pages = max(1, intval(ceil($total / $per)));
    if ($page >= $pages) {
        $page = $pages - 1;
        $offset = $page * $per;
        $st = $pdo->prepare(
            "SELECT id, username, Balance, affiliates_balance, affiliates
             FROM user
             WHERE affiliates_balance > 0
             ORDER BY affiliates_balance DESC
             LIMIT " . intval($per) . " OFFSET " . intval($offset)
        );
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $sum = 0;
    try {
        $sum = intval($pdo->query("SELECT COALESCE(SUM(affiliates_balance),0) FROM user WHERE affiliates_balance > 0")->fetchColumn());
    } catch (Throwable $e) {}

    $text = "👥 <b>کاربران فعال در طرح</b>\n"
        . "کاربرانی که حداقل یک‌بار پورسانت گرفته‌اند.\n\n"
        . "📊 تعداد: <b>" . number_format($total) . "</b> نفر\n"
        . "💰 مجموع پورسانت پرداخت‌شده: <b>" . number_format($sum) . "</b> تومان\n"
        . "📄 صفحه " . ($page + 1) . " از {$pages}\n\n";

    $n = $offset + 1;
    foreach ($rows as $row) {
        $uid = intval($row['id'] ?? 0);
        $uname = trim(strval($row['username'] ?? ''));
        if ($uname === '' || $uname === '0') {
            $uname = '-';
        } else {
            $uname = '@' . ltrim($uname, '@');
        }
        $earned = number_format(intval($row['affiliates_balance'] ?? 0));
        $bal = number_format(intval(str_replace(',', '', strval($row['Balance'] ?? 0))));
        $text .= "{$n}. <code>{$uid}</code> | {$uname}\n"
            . "    🎁 پورسانت: <b>{$earned}</b> ت | 💳 موجودی: {$bal} ت\n";
        $n++;
    }

    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '◀️ قبلی', 'callback_data' => 'affpanel_active_users_' . ($page - 1)];
    }
    if ($page < $pages - 1) {
        $nav[] = ['text' => 'بعدی ▶️', 'callback_data' => 'affpanel_active_users_' . ($page + 1)];
    }
    $kb_rows = [];
    if ($nav) {
        $kb_rows[] = $nav;
    }
    $kb_rows[] = [['text' => '🔙 بازگشت به تنظیمات', 'callback_data' => 'affpanel_refresh']];
    $kb = json_encode(['inline_keyboard' => $kb_rows], JSON_UNESCAPED_UNICODE);
    return [$text, $kb];
}

function addAffiliatesBalance($user_id, $amount)
{
    global $pdo;
    ensureAffiliatesBalanceColumn();
    $amount = intval($amount);
    $user_id = intval($user_id);
    if ($amount <= 0 || $user_id <= 0) return;
    try {
        $pdo->prepare("UPDATE user SET affiliates_balance = affiliates_balance + ? WHERE id = ?")->execute([$amount, $user_id]);
    } catch (Exception $e) {}
}

/**
 * مجموع پورسانت دریافتی از مشارکت در فروش
 * affiliates_balance ثبت‌شده را با تخمین از خرید مشارکت‌کنندگان مقایسه می‌کند
 */
function getAffiliatesEarned($user_id)
{
    global $pdo;
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return 0;
    }
    ensureAffiliatesBalanceColumn();
    $tracked = 0;
    try {
        $st = $pdo->prepare("SELECT affiliates_balance FROM user WHERE id = ? LIMIT 1");
        $st->execute([$user_id]);
        $tracked = intval($st->fetchColumn());
    } catch (Exception $e) {
        $tracked = 0;
    }
    $estimated = 0;
    try {
        $aff = select("affiliates", "*", null, null, "select");
        $pct = floatval(is_array($aff) ? ($aff['affiliatespercentage'] ?? 0) : 0);
        if ($pct > 0) {
            $sql = "SELECT COALESCE(SUM(CAST(i.price_product AS DECIMAL(18,0))),0) FROM invoice i
                INNER JOIN user u ON u.id = i.id_user
                WHERE CAST(u.affiliates AS CHAR) = ?
                AND (
                    i.status = 'active' OR i.status = 'end_of_time' OR i.status = 'end_of_volume' OR i.status = 'sendedwarn'
                    OR i.Status = 'active' OR i.Status = 'end_of_time' OR i.Status = 'end_of_volume' OR i.Status = 'sendedwarn'
                )
                AND (i.name_product IS NULL OR i.name_product != 'usertest')";
            $st = $pdo->prepare($sql);
            $st->execute([strval($user_id)]);
            $sum = floatval($st->fetchColumn());
            $estimated = intval(($sum * $pct) / 100);
        }
    } catch (Exception $e) {
        $estimated = 0;
    }
    return max($tracked, $estimated);
}


/**
 * نمایش کامل اطلاعات کاربر برای ادمین (متن + دکمه‌های مدیریت)
 * همان صفحه‌ای که از «جستجوی کاربر» می‌آید
 */
function sendAdminUserInfo($admin_id, $target_user_id)
{
    global $pdo, $textbotlang, $keyboardadmin;
    $target_user_id = intval($target_user_id);
    $admin_id = intval($admin_id);
    if ($target_user_id <= 0 || $admin_id <= 0) {
        return false;
    }
    $u = select("user", "*", "id", $target_user_id, "select");
    if (!$u || !is_array($u)) {
        sendmessage($admin_id, $textbotlang['Admin']['not-user'] ?? '❌ کاربر یافت نشد.', null, 'HTML');
        return false;
    }

    $status_ok = "(status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn')";
    $dayListSell = 0;
    $balanceall = 0;
    $subbuyuser = 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE {$status_ok} AND id_user = :id");
        $st->execute([':id' => $target_user_id]);
        $dayListSell = intval($st->fetchColumn());
    } catch (Exception $e) {}
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(price),0) FROM Payment_report WHERE payment_Status = 'paid' AND id_user = :id");
        $st->execute([':id' => $target_user_id]);
        $balanceall = $st->fetchColumn();
    } catch (Exception $e) {}
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE {$status_ok} AND id_user = :id");
        $st->execute([':id' => $target_user_id]);
        $subbuyuser = $st->fetchColumn();
    } catch (Exception $e) {}
    if ($subbuyuser === null || $subbuyuser === false) {
        $subbuyuser = 0;
    }
    if ($balanceall === null || $balanceall === false) {
        $balanceall = 0;
    }

    $roll_Status = [
        '1' => $textbotlang['Admin']['ManageUser']['Acceptedphone'] ?? 'تایید شده',
        '0' => $textbotlang['Admin']['ManageUser']['Failedphone'] ?? 'تایید نشده',
    ][strval($u['roll_Status'] ?? '0')] ?? ($textbotlang['Admin']['ManageUser']['Failedphone'] ?? 'تایید نشده');

    $uid = strval($target_user_id);
    $keyboardmanage = [
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['ManageUser']['addbalanceuser'] ?? '⬆️ افزایش موجودی', 'callback_data' => "addbalanceuser_" . $uid],
                ['text' => $textbotlang['Admin']['ManageUser']['lowbalanceuser'] ?? '⬇️ کم کردن موجودی', 'callback_data' => "lowbalanceuser_" . $uid],
            ],
            [
                ['text' => $textbotlang['Admin']['ManageUser']['banuserlist'] ?? '🔒 مسدود کردن کاربر', 'callback_data' => "banuserlist_" . $uid],
                ['text' => $textbotlang['Admin']['ManageUser']['unbanuserlist'] ?? '🔓 رفع مسدودی کاربر', 'callback_data' => "unbanuserr_" . $uid],
            ],
            [
                ['text' => $textbotlang['Admin']['ManageUser']['confirmnumber'] ?? 'تایید دستی شماره تلفن', 'callback_data' => "confirmnumber_" . $uid],
            ],
            [
                ['text' => $textbotlang['Admin']['getlimitusertest']['setlimitbtn'] ?? '➕ محدودیت ساخت اکانت تست', 'callback_data' => "limitusertest_" . $uid],
            ],
            [
                ['text' => $textbotlang['Admin']['ManageUser']['verify'] ?? 'احراز هویت', 'callback_data' => "verify_" . $uid],
                ['text' => $textbotlang['Admin']['ManageUser']['removeverify'] ?? 'حذف احراز هویت', 'callback_data' => "verifyun_" . $uid],
            ],
            [
                ['text' => $textbotlang['Admin']['ManageUser']['vieworderuser'] ?? '🛍 مشاهده سفارشات کاربر', 'callback_data' => "vieworderall_" . $uid],
                ['text' => $textbotlang['Admin']['ManageUser']['addorder'] ?? '🛒 افزودن دستی سفارش', 'callback_data' => "addordermanualـ" . $uid],
            ],
            [
                ['text' => '✉️ ارسال پیام به کاربر', 'callback_data' => "Response_" . $uid],
            ],
        ]
    ];

    if (function_exists('ensureUserCartAutoColumn')) {
        ensureUserCartAutoColumn();
    }
    $aff_earn = function_exists('getAffiliatesEarned') ? getAffiliatesEarned($target_user_id) : intval($u['affiliates_balance'] ?? 0);
    $cart_auto_off = intval($u['cart_auto_off'] ?? 0);
    $global_auto = function_exists('isAutomaticCartConfirmEnabled') ? isAutomaticCartConfirmEnabled() : false;
    $cart_label = !$global_auto
        ? '—'
        : ($cart_auto_off
            ? ($textbotlang['Admin']['ManageUser']['cart_auto_status_off'] ?? '❌ غیرفعال')
            : ($textbotlang['Admin']['ManageUser']['cart_auto_status_on'] ?? '✅ فعال'));
    if ($global_auto) {
        $keyboardmanage['inline_keyboard'][] = [[
            'text' => $cart_auto_off
                ? ($textbotlang['Admin']['ManageUser']['cart_auto_off'] ?? '❌ تأیید خودکار رسید کاربر: غیرفعال')
                : ($textbotlang['Admin']['ManageUser']['cart_auto_on'] ?? '✅ تأیید خودکار رسید کاربر: فعال'),
            'callback_data' => 'toggle_cart_auto_' . $uid
        ]];
    }


    // Reseller Panel — فقط اگر قابلیت روشن باشد
    $pa_en = 0;
    $pa_user = '';
    $pa_vol_bought = 0;
    try {
        if (function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled()) {
            if (function_exists('ensurePanelAdminSchema')) {
                ensurePanelAdminSchema();
            }
            $pa_en = intval($u['panel_admin_enabled'] ?? 0);
            $pa_list = function_exists('getUserPanelAdminUsernames') ? getUserPanelAdminUsernames($uid) : parsePanelAdminUsernames($u['panel_admin_username'] ?? '');
            $pa_user = implode(', ', $pa_list);
            $keyboardmanage['inline_keyboard'][] = [[
                'text' => $pa_en
                    ? (isset($textbotlang['Admin']['ManageUser']['panel_admin_on']) ? $textbotlang['Admin']['ManageUser']['panel_admin_on'] : '🛠 پنل‌دار: فعال ✅')
                    : (isset($textbotlang['Admin']['ManageUser']['panel_admin_off']) ? $textbotlang['Admin']['ManageUser']['panel_admin_off'] : '🛠 پنل‌دار: غیرفعال ❌'),
                'callback_data' => 'toggle_panel_admin_' . $uid
            ]];
            foreach ($pa_list as $idx => $paname) {
                $keyboardmanage['inline_keyboard'][] = [[
                    'text' => '👤 ' . $paname . '  — 🗑 حذف',
                    'callback_data' => 'pa_rm_' . $uid . '_' . $idx
                ]];
            }
            if (count($pa_list) < 3) {
                $keyboardmanage['inline_keyboard'][] = [[
                    'text' => '➕ افزودن ادمین پنل' . (count($pa_list) ? ' (' . count($pa_list) . '/3)' : ''),
                    'callback_data' => 'set_panel_admin_user_' . $uid
                ]];
            }
            if ($pa_en && $pa_list && function_exists('getResellerVolumePurchasedGb')) {
                $pa_vol_bought = getResellerVolumePurchasedGb($target_user_id);
            }
        }
    } catch (Throwable $e) {}

    $Balance_fmt = number_format(intval($u['Balance'] ?? 0));
    $lmt = intval($u['last_message_time'] ?? 0);
    $lastmessage = $lmt > 0
        ? (function_exists('jdate') ? jdate('Y/m/d H:i:s', $lmt) : date('Y-m-d H:i:s', $lmt))
        : '-';
    $username_disp = $u['username'] ?? '';
    if ($username_disp === 'none' || $username_disp === null) {
        $username_disp = '';
    }
    $number_disp = $u['number'] ?? 'none';
    if ($number_disp === '' || $number_disp === null) {
        $number_disp = 'none';
    }
    $active_svc_cnt = function_exists('countUserActiveServices')
        ? countUserActiveServices($target_user_id)
        : intval($dayListSell);
    $text_msg = sprintf(
        $textbotlang['Admin']['ManageUser']['infouser'],
        $u['User_Status'] ?? '-',
        $username_disp,
        $uid,
        $uid,
        $lastmessage,
        $u['limit_usertest'] ?? '0',
        $roll_Status,
        $number_disp,
        $Balance_fmt,
        $active_svc_cnt,
        number_format(intval($balanceall)),
        number_format(intval($subbuyuser)),
        $u['affiliatescount'] ?? '0',
        $u['affiliates'] ?? '0',
        $u['verify'] ?? '0',
        number_format(intval($aff_earn)),
        $cart_label
    );
        try {
        if (function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled() && !empty($pa_en) && $pa_user !== '') {
            $text_msg .= "\n🛠 ادمین(های) پنل: <code>" . htmlspecialchars($pa_user, ENT_QUOTES, 'UTF-8') . "</code>";
            $vol_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($pa_vol_bought) : (number_format(intval($pa_vol_bought)) . ' گیگ');
            $text_msg .= "\n📊 حجم خریداری‌شده پنل: <b>" . $vol_lbl . "</b>";
        }
    } catch (Throwable $e) {}
sendmessage($admin_id, $text_msg, json_encode($keyboardmanage), 'HTML');
    return true;
}


function setPaySettingValue($name, $value)
{
    global $pdo;
    if (!function_exists('ensurePaySetting')) {
        return false;
    }
    ensurePaySetting($name, $value);
    try {
        $stmt = $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?");
        $stmt->execute([strval($value), $name]);
        return true;
    } catch (Throwable $e) {
        error_log('setPaySettingValue: ' . $e->getMessage());
        return false;
    }
}

/** فاصله تأیید خودکار کارت‌به‌کارت (دقیقه) — پیش‌فرض ۴ */

/**
 * کیبورد تنظیمات مالی (کارت / کریپتو / تأیید خودکار / محدودیت / پکیج)
 */
function buildFinanceKeyboard()
{
    global $textbotlang;
    $sqlstatus_cart = select("PaySetting", "ValuePay", "NamePay", "Cartstatus", "select");
    $sqlstatus_cart = is_array($sqlstatus_cart) ? ($sqlstatus_cart['ValuePay'] ?? 'offcard') : 'offcard';
    $sqlstatus_iranpay = select("PaySetting", "ValuePay", "NamePay", "digistatus", "select");
    $sqlstatus_iranpay = is_array($sqlstatus_iranpay) ? ($sqlstatus_iranpay['ValuePay'] ?? 'offdigi') : 'offdigi';
    $status_cart = [
        'oncard' => $textbotlang['Admin']['turnon'] ?? 'روشن ✅',
        'offcard' => $textbotlang['Admin']['turnoff'] ?? 'خاموش ❌',
    ][$sqlstatus_cart] ?? $sqlstatus_cart;
    $status_iranpay = [
        'ondigi' => $textbotlang['Admin']['turnon'] ?? 'روشن ✅',
        'offdigi' => $textbotlang['Admin']['turnoff'] ?? 'خاموش ❌',
    ][$sqlstatus_iranpay] ?? $sqlstatus_iranpay;
    if (function_exists('ensurePaySetting')) {
        ensurePaySetting('auto_cart_confirm', '0');
        ensurePaySetting('auto_cart_interval', '4');
    }
    $auto_on = function_exists('isAutomaticCartConfirmEnabled') ? isAutomaticCartConfirmEnabled() : false;
    $auto_status_txt = $auto_on
        ? ($textbotlang['Admin']['turnon'] ?? 'روشن ✅')
        : ($textbotlang['Admin']['turnoff'] ?? 'خاموش ❌');
    $rows = [
        [
            ['text' => $textbotlang['users']['moeny']['setting'] ?? '⚙️ تنظیمات', 'callback_data' => 'settingcart'],
            ['text' => $status_cart, 'callback_data' => 'editpay-cart-' . $sqlstatus_cart],
            ['text' => $textbotlang['users']['moeny']['cart_to_Cart_btn'] ?? '💳 کارت به کارت', 'callback_data' => 'none'],
        ],
        [
            ['text' => $textbotlang['users']['moeny']['setting'] ?? '⚙️ تنظیمات', 'callback_data' => 'setting_currency_wallets'],
            ['text' => $status_iranpay, 'callback_data' => 'editpay-iranpay-' . $sqlstatus_iranpay],
            ['text' => $textbotlang['users']['moeny']['currency_rial_gateway'] ?? '💎 کریپتو', 'callback_data' => 'none'],
        ],
        [
            ['text' => $textbotlang['users']['moeny']['setting'] ?? '⚙️ تنظیمات', 'callback_data' => 'finance_auto_cart_settings'],
            ['text' => $auto_status_txt, 'callback_data' => 'finance_auto_cart_toggle'],
            ['text' => ($textbotlang['Admin']['Automatic_confirmation']['finance_line'] ?? '🤖 تأیید خودکار بدون بررسی'), 'callback_data' => 'finance_auto_cart_settings'],
        ],
        [
            ['text' => $textbotlang['Admin']['deposit']['menu'] ?? '💰 حداقل / حداکثر واریز', 'callback_data' => 'deposit_limits_settings'],
        ],
        [
            ['text' => $textbotlang['Admin']['balance_pkg']['menu'] ?? '🎁 پکیج افزایش موجودی', 'callback_data' => 'balance_packages_settings'],
        ],
    ];
    return json_encode(['inline_keyboard' => $rows]);
}

/**
 * زیرمنوی تنظیمات تأیید خودکار (فاصله دقیقه)
 */
function buildAutoCartSettingsKeyboard()
{
    global $textbotlang;
    $auto_min = function_exists('getAutoCartIntervalMinutes') ? getAutoCartIntervalMinutes() : 4;
    $auto_on = function_exists('isAutomaticCartConfirmEnabled') ? isAutomaticCartConfirmEnabled() : false;
    $st = $auto_on
        ? ($textbotlang['Admin']['turnon'] ?? 'روشن ✅')
        : ($textbotlang['Admin']['turnoff'] ?? 'خاموش ❌');
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => $st, 'callback_data' => 'finance_auto_cart_toggle'],
                ['text' => ($textbotlang['Admin']['Automatic_confirmation']['finance_line'] ?? 'تأیید خودکار'), 'callback_data' => 'finance_auto_cart_info'],
            ],
            [
                ['text' => "هر {$auto_min} دقیقه", 'callback_data' => 'finance_auto_cart_interval'],
                ['text' => ($textbotlang['Admin']['Automatic_confirmation']['interval_btn'] ?? '⏱ فاصله تأیید خودکار'), 'callback_data' => 'finance_auto_cart_interval'],
            ],
            [
                ['text' => '↩️ بازگشت به مالی', 'callback_data' => 'finance_menu_back'],
            ],
        ],
    ]);
}


function getAutoCartIntervalMinutes()
{
    if (function_exists('ensurePaySetting')) {
        ensurePaySetting('auto_cart_interval', '4');
    }
    $m = intval(function_exists('getPaySettingValue') ? getPaySettingValue('auto_cart_interval', '4') : 4);
    if ($m < 1) {
        $m = 1;
    }
    if ($m > 60) {
        $m = 60;
    }
    return $m;
}

function getAutoCartCronCommand($minutes = null)
{
    global $domainhosts;
    $m = $minutes !== null ? intval($minutes) : getAutoCartIntervalMinutes();
    if ($m < 1) {
        $m = 1;
    }
    if ($m > 60) {
        $m = 60;
    }
    $host = strval($domainhosts ?? '');
    return "*/{$m} * * * * curl https://{$host}/cron/croncard.php";
}

/** دامنه این ربات برای تطبیق خطوط crontab */
function getBotCronHostKey()
{
    global $domainhosts;
    $host = trim(strval($domainhosts ?? ''));
    $host = preg_replace('#^https?://#i', '', $host);
    $host = rtrim($host, '/');
    if (strpos($host, '/') !== false) {
        $host = explode('/', $host, 2)[0];
    }
    return strtolower($host);
}

/**
 * خط crontab متعلق به همین ربات است؟ (دامنه + نام فایل کرون)
 */
function isCronLineForThisBot($line, $patterns = null)
{
    $host = getBotCronHostKey();
    if ($host === '') {
        return false;
    }
    if (strpos(strtolower(strval($line)), $host) === false) {
        return false;
    }
    if ($patterns === null) {
        $patterns = getBotManagedCronPatterns();
    }
    foreach ($patterns as $pat) {
        if (strpos($line, $pat) !== false) {
            return true;
        }
    }
    return false;
}

/** حذف/نصب کرون croncard فقط برای دامنه همین ربات */
function syncAutoCartCron($enabled)
{
    $enabled = (bool) $enabled;
    if (!(function_exists('shell_exec') && is_callable('shell_exec'))) {
        return false;
    }
    if (getBotCronHostKey() === '') {
        return false;
    }
    $existing = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($existing) || stripos($existing, 'no crontab') !== false) {
        $existing = '';
    }
    $kept = [];
    foreach (preg_split("/\r\n|\n|\r/", $existing) as $line) {
        if ($line === '' || $line === false) {
            continue;
        }
        if (isCronLineForThisBot($line, ['croncard.php'])) {
            continue;
        }
        $kept[] = $line;
    }
    if ($enabled) {
        $kept[] = getAutoCartCronCommand();
    }
    $content = implode("\n", $kept);
    if ($content !== '' && substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $tmp = '/tmp/crontab_mirza_autocart_' . getmypid() . '.txt';
    if (@file_put_contents($tmp, $content) === false) {
        return false;
    }
    if (trim($content) === '') {
        @shell_exec('crontab -r 2>/dev/null');
    } else {
        @shell_exec('crontab ' . escapeshellarg($tmp) . ' 2>/dev/null');
    }
    @unlink($tmp);
    $after = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($after)) {
        $after = '';
    }
    $has = false;
    foreach (preg_split("/\r\n|\n|\r/", $after) as $ln) {
        if (isCronLineForThisBot($ln, ['croncard.php'])) {
            $has = true;
            break;
        }
    }
    return $enabled ? $has : !$has;
}

function botCronShellAvailable()
{
    return function_exists('shell_exec') && is_callable('shell_exec');
}

function getBotManagedCronPatterns()
{
    return [
        'smart_cron.php',
        'croncard.php',
        'cronvolume.php',
        'cronday.php',
        'removeexpire.php',
        'configtest.php',
        'cleanorphan.php',
        'smartwarn.php',
    ];
}

function getSmartCronCommandLine()
{
    global $domainhosts;
    $host = trim(strval($domainhosts ?? ''));
    return "*/30 * * * * curl -s https://{$host}/cron/smart_cron.php >/dev/null 2>&1";
}

/**
 * همگام‌سازی crontab فقط برای دامنه همین ربات
 */
function syncBotCrontabs()
{
    global $domainhosts;
    $wanted = [];
    $wanted[] = getSmartCronCommandLine();
    $auto_on = false;
    if (function_exists('getPaySettingValue')) {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting('auto_cart_confirm', '0');
        }
        $auto_on = (getPaySettingValue('auto_cart_confirm', '0') === '1');
    }
    if ($auto_on && function_exists('getAutoCartCronCommand')) {
        $wanted[] = getAutoCartCronCommand();
    }

    if (!botCronShellAvailable()) {
        return [
            'ok' => false,
            'message' => 'shell_exec روی سرور غیرفعال است؛ نمی‌توان از داخل ربات crontab را تغییر داد.',
            'lines' => $wanted,
            'installed' => [],
        ];
    }
    if (getBotCronHostKey() === '') {
        return [
            'ok' => false,
            'message' => 'دامنه ربات (domainhosts) خالی است؛ برای جلوگیری از حذف اشتباه کرون‌های دیگر متوقف شد.',
            'lines' => $wanted,
            'installed' => [],
        ];
    }

    $existing = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($existing) || stripos($existing, 'no crontab') !== false) {
        $existing = '';
    }

    $patterns = getBotManagedCronPatterns();
    $kept = [];
    foreach (preg_split("/\r\n|\n|\r/", $existing) as $line) {
        if ($line === '' || $line === false) {
            continue;
        }
        if (isCronLineForThisBot($line, $patterns)) {
            continue;
        }
        $kept[] = $line;
    }
    foreach ($wanted as $w) {
        $kept[] = $w;
    }

    $content = implode("\n", $kept);
    if ($content !== '' && substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $tmp = '/tmp/crontab_mirza_bot_' . getmypid() . '.txt';
    if (@file_put_contents($tmp, $content) === false) {
        return [
            'ok' => false,
            'message' => 'نوشتن فایل موقت crontab ناموفق بود.',
            'lines' => $wanted,
            'installed' => [],
        ];
    }
    @shell_exec('crontab ' . escapeshellarg($tmp) . ' 2>/dev/null');
    @unlink($tmp);

    $after = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($after)) {
        $after = '';
    }
    $installed = [];
    $host = getBotCronHostKey();
    foreach ($wanted as $w) {
        $needle = '';
        if (strpos($w, 'smart_cron.php') !== false) {
            $needle = 'smart_cron.php';
        } elseif (strpos($w, 'croncard.php') !== false) {
            $needle = 'croncard.php';
        }
        if ($needle === '') {
            continue;
        }
        foreach (preg_split("/\r\n|\n|\r/", $after) as $ln) {
            if (strpos($ln, $needle) !== false && stripos($ln, $host) !== false) {
                $installed[] = $w;
                break;
            }
        }
    }
    $ok = (count($installed) === count($wanted));
    return [
        'ok' => $ok,
        'message' => $ok
            ? 'کرون‌جاب‌های این ربات (دامنه: ' . $host . ') با موفقیت نصب/همگام شدند.'
            : 'دستور crontab اجرا شد ولی همه خطوط این دامنه تأیید نشدند.',
        'lines' => $wanted,
        'installed' => $installed,
    ];
}

/**
 * حذف کرون‌های فقط همین ربات (بر اساس دامنه) از crontab
 */
function removeBotCrontabs()
{
    if (!botCronShellAvailable()) {
        return [
            'ok' => false,
            'message' => 'shell_exec غیرفعال است؛ خطوط دامنه این ربات را دستی از crontab حذف کنید.',
            'removed' => [],
        ];
    }
    $host = getBotCronHostKey();
    if ($host === '') {
        return [
            'ok' => false,
            'message' => 'دامنه ربات خالی است؛ حذف انجام نشد تا کرون ربات‌های دیگر آسیب نبیند.',
            'removed' => [],
        ];
    }
    $existing = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($existing) || stripos($existing, 'no crontab') !== false) {
        $existing = '';
    }
    $patterns = getBotManagedCronPatterns();
    $kept = [];
    $removed = [];
    foreach (preg_split("/\r\n|\n|\r/", $existing) as $line) {
        if ($line === '' || $line === false) {
            continue;
        }
        if (isCronLineForThisBot($line, $patterns)) {
            $removed[] = $line;
            continue;
        }
        $kept[] = $line;
    }
    $content = implode("\n", $kept);
    if ($content !== '' && substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $tmp = '/tmp/crontab_mirza_bot_rm_' . getmypid() . '.txt';
    if (@file_put_contents($tmp, $content) === false) {
        return ['ok' => false, 'message' => 'نوشتن فایل موقت ناموفق بود.', 'removed' => []];
    }
    if (trim($content) === '') {
        @shell_exec('crontab -r 2>/dev/null');
    } else {
        @shell_exec('crontab ' . escapeshellarg($tmp) . ' 2>/dev/null');
    }
    @unlink($tmp);
    $n = count($removed);
    return [
        'ok' => true,
        'message' => $n > 0
            ? "تعداد {$n} خط کرون مربوط به دامنه {$host} حذف شد."
            : "هیچ خط کرونی برای دامنه {$host} در crontab نبود.",
        'removed' => $removed,
    ];
}

function isAutomaticCartConfirmEnabled()
{
    // فقط تنظیم دیتابیس — نه وجود خط در crontab (تا روشن/خاموش از ربات دقیق باشد)
    if (function_exists('getPaySettingValue')) {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting('auto_cart_confirm', '0');
        }
        return getPaySettingValue('auto_cart_confirm', '0') === '1';
    }
    $row = select("PaySetting", "ValuePay", "NamePay", "auto_cart_confirm", "select");
    if (is_array($row)) {
        return strval($row['ValuePay'] ?? '0') === '1';
    }
    if (is_string($row) || is_numeric($row)) {
        return strval($row) === '1';
    }
    return false;
}

function ensureUserCartAutoColumn()
{
    global $pdo;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM user LIKE 'cart_auto_off'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD COLUMN cart_auto_off TINYINT NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
        error_log('ensureUserCartAutoColumn: ' . $e->getMessage());
    }
}

function ensureSupportPendingTable()
{
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS support_pending (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_user BIGINT NOT NULL,
            username VARCHAR(191) DEFAULT NULL,
            message_text TEXT,
            created_at INT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'waiting',
            INDEX (status),
            INDEX (id_user)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        error_log('ensureSupportPendingTable: ' . $e->getMessage());
    }
}

function ensureSmartCronSettings()
{
    // پیش‌فرض همه قابلیت‌ها خاموش — ادمین از ربات روشن می‌کند
    ensurePaySetting('smart_clean_missing', '0');
    ensurePaySetting('smart_warn_volume', '0');
    ensurePaySetting('smart_warn_time', '0');
    ensurePaySetting('smart_warn_expired', '0');
    ensurePaySetting('smart_emergency', '0');
    ensurePaySetting('smart_vol_levels', '90,95,99');
    ensurePaySetting('smart_time_days', '7,3,1');
    ensurePaySetting('smart_emergency_gb', '1');
    ensurePaySetting('smart_emergency_days', '1');
    ensurePaySetting('smart_debug', '0');
    ensurePaySetting('smart_cron_cursor', '0');
    ensurePaySetting('smart_batch_limit', '20');
    // هشدار حجم پنل ریسلر (زیرمجموعه اسمارت کرون)
    ensurePaySetting('smart_reseller_warn', '0');
    ensurePaySetting('smart_reseller_vol_levels', '80,90,95');
}

function smartCronFlag($name, $default = '0')
{
    ensureSmartCronSettings();
    $v = getPaySettingValue($name, $default);
    return ($v === '1' || $v === 'on' || $v === 'true');
}

function smartCronLevelsVolume()
{
    ensureSmartCronSettings();
    $raw = getPaySettingValue('smart_vol_levels', '90,95,99');
    $out = [];
    foreach (explode(',', $raw) as $p) {
        $p = floatval(trim($p));
        if ($p > 0 && $p <= 100) {
            $out[] = $p;
        }
    }
    sort($out);
    return $out ?: [90, 95, 99];
}

function smartCronLevelsTime()
{
    ensureSmartCronSettings();
    $raw = getPaySettingValue('smart_time_days', '7,3,1');
    $out = [];
    foreach (explode(',', $raw) as $p) {
        $p = intval(trim($p));
        if ($p > 0) {
            $out[] = $p;
        }
    }
    rsort($out); // 7,3,1
    return $out ?: [7, 3, 1];
}

function ensureSmartCronStateTable()
{
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS smart_cron_state (
        username VARCHAR(191) NOT NULL,
        service_location VARCHAR(191) NOT NULL,
        warn_time_level INT NOT NULL DEFAULT 0,
        warn_vol_level INT NOT NULL DEFAULT 0,
        expired_notified TINYINT NOT NULL DEFAULT 0,
        emergency_used TINYINT NOT NULL DEFAULT 0,
        PRIMARY KEY (username, service_location)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function getSmartCronState($username, $location)
{
    global $pdo;
    ensureSmartCronStateTable();
    $st = $pdo->prepare("SELECT * FROM smart_cron_state WHERE username = ? AND service_location = ? LIMIT 1");
    $st->execute([$username, $location]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $ins = $pdo->prepare("INSERT IGNORE INTO smart_cron_state (username, service_location) VALUES (?, ?)");
    $ins->execute([$username, $location]);
    return [
        'username' => $username,
        'service_location' => $location,
        'warn_time_level' => 0,
        'warn_vol_level' => 0,
        'expired_notified' => 0,
        'emergency_used' => 0,
    ];
}

function updateSmartCronState($username, $location, $field, $value)
{
    global $pdo;
    ensureSmartCronStateTable();
    getSmartCronState($username, $location);
    $allowed = ['warn_time_level', 'warn_vol_level', 'expired_notified', 'emergency_used'];
    if (!in_array($field, $allowed, true)) {
        return;
    }
    $st = $pdo->prepare("UPDATE smart_cron_state SET {$field} = ? WHERE username = ? AND service_location = ?");
    $st->execute([$value, $username, $location]);
}


function smartCronDebugLog($message)
{
    if (!smartCronFlag('smart_debug', '0')) {
        return;
    }
    sendChannelReport('rpt_smart_debug', "🛠 <b>دیباگ کرون هوشمند</b>
" . $message);
}


function resetSmartCronWarnings($username, $location)
{
    if (!function_exists('updateSmartCronState')) {
        return;
    }
    updateSmartCronState($username, $location, 'warn_time_level', 0);
    updateSmartCronState($username, $location, 'warn_vol_level', 0);
    updateSmartCronState($username, $location, 'expired_notified', 0);
}


function reportChannelTypes()
{
    return [
        'rpt_buy' => '🛍 خرید سرویس',
        'rpt_buy_after_pay' => '🛍 واریز + خرید',
        'rpt_extend' => '🔄 تمدید سرویس',
        'rpt_extra_volume' => '➕ حجم اضافه',
        'rpt_test' => '🧪 اکانت تست',
        'rpt_discount' => '🏷 کد تخفیف',
        'rpt_gift' => '🎁 کد هدیه',
        'rpt_cart_accept' => '✅ تأیید رسید (ادمین)',
        'rpt_cart_auto' => '🤖 تأیید خودکار رسید',
        'rpt_reject' => '❌ رد رسید',
        'rpt_remove' => '🗑 حذف سرویس + بازگشت وجه',
        'rpt_remove_user' => '🗑 حذف سرویس توسط کاربر',
        'rpt_remove_cron' => '⏰ حذف سرویس (کرون)',
        'rpt_emergency' => '🚨 تمدید اضطراری',
        'rpt_admin_balance' => '💰 تغییر موجودی توسط ادمین',
        'rpt_new_user' => '👤 کاربر جدید / استارت',
        'rpt_smart_debug' => '🛠 دیباگ کرون هوشمند',
        'rpt_block' => '🔒 مسدودسازی کاربر',
        'rpt_unblock' => '🔓 رفع مسدودی',
        'rpt_spam_block' => '⛔ مسدود خودکار اسپم',
        'rpt_create_error' => '⚠️ خطای ساخت سرویس',
        'rpt_reseller_warn' => '⚠️ هشدار حجم پنل ریسلر',
        'rpt_reseller_volume' => '📦 خرید حجم پنل ریسلر',
    ];
}

function ensureReportChannelSettings()
{
    foreach (array_keys(reportChannelTypes()) as $k) {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting($k, '1');
        }
    }
}

function isReportChannelEnabled($key)
{
    if (!function_exists('getPaySettingValue')) {
        return true;
    }
    ensureReportChannelSettings();
    return strval(getPaySettingValue($key, '1')) === '1';
}


/**
 * اطلاع‌رسانی مسدود شدن کاربر به ادمین‌ها (داخل ربات) و کانال گزارش
 * @param int|string $user_id
 * @param string $username بدون @
 * @param string $reason
 * @param string $source auto_spam | admin
 * @param int|string|null $admin_id اگر توسط ادمین باشد
 */
function notifyUserBlocked($user_id, $username = '-', $reason = '', $source = 'auto_spam', $admin_id = null)
{
    global $textbotlang, $admin_ids;
    $user_id = strval($user_id);
    $username = $username ? ltrim(strval($username), '@') : '-';
    if ($username === '') {
        $username = '-';
    }
    $reason = trim(strval($reason));
    if ($reason === '') {
        $reason = $textbotlang['users']['spamtext'] ?? 'مسدود';
    }

    if ($source === 'admin') {
        $txt = sprintf(
            $textbotlang['Admin']['Report']['block_user']
                ?? "🔒 مسدودسازی\nمدیر: %s\nکاربر: %s\n@%s\nدلیل: %s",
            strval($admin_id ?? '-'),
            $user_id,
            $username,
            $reason
        );
        $channel_key = 'rpt_block';
    } else {
        $txt = sprintf(
            $textbotlang['Admin']['Report']['spam_block']
                ?? "⛔ مسدود خودکار (اسپم)\n🆔 %s\n👤 @%s\n✍️ %s",
            $user_id,
            $username,
            $reason
        );
        $channel_key = 'rpt_spam_block';
    }

    // مسدود دستی ادمین: فقط کانال گزارش
    // مسدود اسپم: هم ربات ادمین هم کانال گزارش
    if ($source !== 'admin') {
        $kb = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '👤 اطلاعات کاربر', 'callback_data' => 'userinfo_pay_' . $user_id],
                ],
            ],
        ]);
        $admins = is_array($admin_ids ?? null) ? $admin_ids : [];
        if (function_exists('notifyAdmins') && count($admins) > 0) {
            try {
                notifyAdmins('sendmessage', [
                    'text' => $txt,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $kb,
                    'disable_web_page_preview' => true,
                ], $admins);
            } catch (Throwable $e) {
                foreach ($admins as $aid) {
                    if (function_exists('sendmessage')) {
                        sendmessage($aid, $txt, $kb, 'HTML');
                    }
                }
            }
        } else {
            foreach ($admins as $aid) {
                if (function_exists('sendmessage')) {
                    sendmessage($aid, $txt, $kb, 'HTML');
                }
            }
        }
    }

    // کانال گزارش (هر دو حالت)
    if (function_exists('sendChannelReport')) {
        sendChannelReport($channel_key, $txt);
    }
}


function sendChannelReport($key, $text)
{
    if ($text === null || $text === '') {
        return;
    }
    // اگر تأیید رسید در جریان است، گزارش خرید/تمدید را نگه دار تا بعد از پیام تأیید برسد
    $defer_keys = ['rpt_buy', 'rpt_buy_after_pay', 'rpt_extend', 'rpt_extra_volume'];
    if (!empty($GLOBALS['defer_post_payment_reports']) && in_array($key, $defer_keys, true)) {
        if (!isset($GLOBALS['deferred_channel_reports']) || !is_array($GLOBALS['deferred_channel_reports'])) {
            $GLOBALS['deferred_channel_reports'] = [];
        }
        $GLOBALS['deferred_channel_reports'][] = ['key' => $key, 'text' => $text];
        return;
    }
    if (!isReportChannelEnabled($key)) {
        return;
    }
    $setting = select("setting", "*", null, null, "select");
    if (!$setting || empty($setting['Channel_Report'])) {
        return;
    }
    sendmessage($setting['Channel_Report'], $text, null, 'HTML');
}

/** ارسال گزارش‌های به‌تعویق‌افتاده خرید بعد از پیام تأیید رسید */
function flushDeferredChannelReports()
{
    $list = $GLOBALS['deferred_channel_reports'] ?? [];
    $GLOBALS['deferred_channel_reports'] = [];
    $GLOBALS['defer_post_payment_reports'] = false;
    if (!is_array($list) || count($list) === 0) {
        return;
    }
    foreach ($list as $item) {
        if (!is_array($item) || empty($item['text'])) {
            continue;
        }
        $key = $item['key'] ?? 'rpt_buy';
        // مستقیم ارسال (بدون defer دوباره)
        if (function_exists('isReportChannelEnabled') && !isReportChannelEnabled($key)) {
            continue;
        }
        $setting = select("setting", "*", null, null, "select");
        if (!$setting || empty($setting['Channel_Report'])) {
            continue;
        }
        sendmessage($setting['Channel_Report'], $item['text'], null, 'HTML');
    }
}

function buildReportChannelKeyboard()
{
    global $textbotlang;
    ensureReportChannelSettings();
    $types = reportChannelTypes();
    $rows = [];
    foreach ($types as $key => $label) {
        $on = isReportChannelEnabled($key);
        $mark = $on ? '✅' : '❌';
        $rows[] = [[
            'text' => "{$mark} {$label}",
            'callback_data' => "rptoggle_{$key}"
        ]];
    }
    $rows[] = [['text' => '🔙 بازگشت', 'callback_data' => 'rpch_menu']];
    return json_encode(['inline_keyboard' => $rows]);
}


function logChannelReport($message)
{
    sendChannelReport('rpt_smart_debug', $message);
}

function isPanelUserMissing($dataUser)
{
    // فقط وقتی پنل صریحاً «یوزر نیست» بگوید — خطای شبکه/توکن/timeout هرگز حذف نمی‌کند
    if (!is_array($dataUser) || $dataUser === []) {
        return false;
    }
    $status = strval($dataUser['status'] ?? '');
    if ($status !== 'Unsuccessful') {
        return false;
    }
    $msg = $dataUser['msg'] ?? ($dataUser['detail'] ?? ($dataUser['message'] ?? ''));
    if (is_array($msg) || is_object($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    }
    $msg = mb_strtolower(trim((string) $msg), 'UTF-8');
    if ($msg === '') {
        return false; // بدون متن مشخص حذف نکن
    }
    // خطاهای دسترسی/شبکه را با «not found» اشتباه نگیر
    foreach (['permission', 'denied', 'timeout', 'timed out', 'connection', 'curl', 'ssl', 'unauthorized', '401', '403', '500', '502', '503'] as $bad) {
        if (strpos($msg, $bad) !== false) {
            return false;
        }
    }
    if (
        strpos($msg, 'not found') !== false
        || strpos($msg, 'does not exist') !== false
        || strpos($msg, 'user not found') !== false
        || strpos($msg, 'یافت نشد') !== false
        || strpos($msg, 'وجود ندارد') !== false
    ) {
        return true;
    }
    return false;
}

function buildServiceWarnKeyboard($username, $show_emergency = false)
{
    global $textbotlang;
    $rows = [
        [['text' => $textbotlang['users']['extend']['title'] ?? '🔄 تمدید سرویس', 'callback_data' => 'extend_' . $username]],
    ];
    if ($show_emergency) {
        $rows[] = [['text' => $textbotlang['users']['cron']['emergency_btn'] ?? '🆘 تمدید اضطراری (۱روز / ۱گیگ)', 'callback_data' => 'emergency_ext_' . $username]];
    }
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function buildSmartCronAdminKeyboard()
{
    global $textbotlang;
    ensureSmartCronSettings();
    $on = '✅';
    $off = '❌';
    $rows = [
        [['text' => (smartCronFlag('smart_clean_missing', '0') ? $on : $off) . ' حذف فاکتور یوزر غایب پنل', 'callback_data' => 'smartcron_toggle_clean']],
        [['text' => (smartCronFlag('smart_warn_volume', '0') ? $on : $off) . ' اخطار حجم', 'callback_data' => 'smartcron_toggle_vol']],
        [['text' => (smartCronFlag('smart_warn_time', '0') ? $on : $off) . ' اخطار زمان', 'callback_data' => 'smartcron_toggle_time']],
        [['text' => (smartCronFlag('smart_warn_expired', '0') ? $on : $off) . ' اخطار اتمام سرویس', 'callback_data' => 'smartcron_toggle_exp']],
        [['text' => (smartCronFlag('smart_emergency', '0') ? $on : $off) . ' تمدید اضطراری', 'callback_data' => 'smartcron_toggle_emg']],
        [['text' => (smartCronFlag('smart_debug', '0') ? $on : $off) . ' دیباگ لاگ کانال', 'callback_data' => 'smartcron_toggle_dbg']],
        [['text' => (smartCronFlag('smart_reseller_warn', '0') ? $on : $off) . ' اخطار حجم Reseller Panel', 'callback_data' => 'smartcron_toggle_reseller']],
        [['text' => '⚙️ درصد حجم Reseller: ' . getPaySettingValue('smart_reseller_vol_levels', '80,90,95'), 'callback_data' => 'smartcron_set_reseller_vol']],
        [['text' => '🔢 تعداد هر اجرا: ' . getPaySettingValue('smart_batch_limit', '20'), 'callback_data' => 'smartcron_set_limit']],
        [['text' => '⚙️ تنظیم درصد حجم: ' . getPaySettingValue('smart_vol_levels', '90,95,99'), 'callback_data' => 'smartcron_set_vol']],
        [['text' => '⚙️ تنظیم روز زمان: ' . getPaySettingValue('smart_time_days', '7,3,1'), 'callback_data' => 'smartcron_set_time']],
        [['text' => '📋 دستور کرون سیستم', 'callback_data' => 'smartcron_show_cmd']],
        [['text' => '🛠 نصب خودکار کرون در سرور', 'callback_data' => 'smartcron_install_crontab']],
        [['text' => '🗑 حذف کرون‌های ربات از سرور', 'callback_data' => 'smartcron_remove_crontab']],
    ];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function ensurePaySetting($name, $value)
{
    global $pdo;
    $row = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if ($row === false || $row === null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)");
            $stmt->execute([$name, $value]);
        } catch (Throwable $e) {
        }
    }
}

function getPaySettingValue($name, $default = '')
{
    $row = select("PaySetting", "ValuePay", "NamePay", $name, "select");
    if ($row === false || $row === null || !isset($row['ValuePay']) || $row['ValuePay'] === '') {
        return $default;
    }
    return $row['ValuePay'];
}

/** حداقل و حداکثر مبلغ واریز/فاکتور (تومان) */
function getDepositLimits()
{
    ensurePaySetting('min_deposit', '100000');
    ensurePaySetting('max_deposit', '10000000');
    $min = intval(getPaySettingValue('min_deposit', '100000'));
    $max = intval(getPaySettingValue('max_deposit', '10000000'));
    if ($min < 1000) {
        $min = 1000;
    }
    if ($max < $min) {
        $max = $min;
    }
    return ['min' => $min, 'max' => $max];
}

function formatToman($amount)
{
    return number_format(intval($amount), 0);
}

/**
 * مبلغ برای ساخت فاکتور پرداخت مجاز است؟
 * @return true|string  true یا متن خطا
 */
function validateDepositAmount($amount)
{
    $lim = getDepositLimits();
    $amount = intval($amount);
    if ($amount < $lim['min'] || $amount > $lim['max']) {
        return sprintf(
            "❌ مبلغ باید حداقل %s و حداکثر %s تومان باشد.",
            formatToman($lim['min']),
            formatToman($lim['max'])
        );
    }
    return true;
}

/**
 * وقتی کسری موجودی کمتر از حداقل واریز است — نمی‌توان فاکتور ساخت
 */
function msgShortfallBelowMin($context = 'buy')
{
    $lim = getDepositLimits();
    $minf = formatToman($lim['min']);
    $map = [
        'buy' => "سپس دوباره برای خرید سرویس اقدام نمایید.",
        'extend' => "سپس دوباره برای تمدید اقدام نمایید.",
        'extra' => "سپس دوباره حجم اضافه بخرید.",
        'pay' => "لطفاً از بخش افزایش اعتبار حداقل این مبلغ را واریز کنید.",
    ];
    $tail = $map[$context] ?? $map['pay'];
    return "❌ مبلغ پرداختی این خرید کمتر از {$minf} تومان است.\n\nابتدا از بخش افزایش اعتبار حداقل {$minf} تومان واریز کنید، {$tail}";
}

function addFieldToTable($tableName, $fieldName, $defaultValue = null, $datatype = "VARCHAR(500)")
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists || intval($tableExists['count'] ?? 0) == 0)
        return;
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$pdo->query("SELECT DATABASE()")->fetchColumn(), $tableName, $fieldName]);
    $filedExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$filedExists || intval($filedExists['count'] ?? 0) != 0)
        return;
    $query = "ALTER TABLE $tableName ADD $fieldName $datatype";
    $statement = $pdo->prepare($query);
    $statement->execute();
    if ($defaultValue != null) {
        $stmt = $pdo->prepare("UPDATE $tableName SET $fieldName= ?");
        $stmt->bindParam(1, $defaultValue);
        $stmt->execute();
    }
    echo "The $fieldName field was added ✅";
}

function publickey()
{
    $privateKey = sodium_crypto_box_keypair();
    $privateKeyEncoded = base64_encode(sodium_crypto_box_secretkey($privateKey));
    $publicKey = sodium_crypto_box_publickey($privateKey);
    $publicKeyEncoded = base64_encode($publicKey);
    $presharedKey = base64_encode(random_bytes(32));
    return [
        'private_key' => $privateKeyEncoded,
        'public_key' => $publicKeyEncoded,
        'preshared_key' => $presharedKey
    ];

}
function deleteFolder($folderPath)
{
    if (!is_dir($folderPath))
        return false;

    $files = array_diff(scandir($folderPath), ['.', '..']);

    foreach ($files as $file) {
        $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteFolder($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($folderPath);
}
function outtypepanel($typepanel, $message)
{
    global $from_id, $optionMarzban, $optionPasarguard, $optionMarzbanPanel, $optionX_ui_single, $optionMarzneshin, $optionmikrotik, $options_ui, $optionwgdashboard;
    $typepanel = strval($typepanel);
    // پاسارگارد: بدون اینباند
    if ($typepanel === 'pasarguard') {
        $kb = isset($optionPasarguard) ? $optionPasarguard : $optionMarzban;
        sendmessage($from_id, $message, $kb, 'HTML');
        return;
    }
    // مرزبان: با تنظیم اینباند/پروتکل
    // توجه: پنل‌های قدیمی با type=marzban که در عمل پاسارگارد بودند هم این منو را می‌بینند؛
    // API همان است و بدون تنظیم اینباند همچنان با گروه پنل کار می‌کند.
    if ($typepanel === 'marzban') {
        $kb = isset($optionMarzbanPanel) ? $optionMarzbanPanel : $optionMarzban;
        sendmessage($from_id, $message, $kb, 'HTML');
        return;
    }
    if ($typepanel == "x-ui_single" || $typepanel == "alireza") {
        sendmessage($from_id, $message, isset($optionX_ui_single) ? $optionX_ui_single : $optionMarzban, 'HTML');
    } elseif ($typepanel == "marzneshin") {
        sendmessage($from_id, $message, isset($optionMarzneshin) ? $optionMarzneshin : $optionMarzban, 'HTML');
    } elseif ($typepanel == "wgdashboard") {
        sendmessage($from_id, $message, isset($optionwgdashboard) ? $optionwgdashboard : $optionMarzban, 'HTML');
    } elseif ($typepanel == "s_ui") {
        sendmessage($from_id, $message, isset($options_ui) ? $options_ui : $optionMarzban, 'HTML');
    } elseif ($typepanel == "mikrotik") {
        sendmessage($from_id, $message, isset($optionmikrotik) ? $optionmikrotik : $optionMarzban, 'HTML');
    } else {
        sendmessage($from_id, $message, $optionMarzban, 'HTML');
    }
}



function isBase64($string)
{
    if (base64_encode(base64_decode($string, true)) === $string) {
        return true;
    }
    return false;
}


/**
 * آپدیت فایل‌های ربات از گیتهاب فورک (بدون دست زدن به config و فایل‌های حساس)
 * @return array{ok:bool,msg:string,updated:int,skipped:int,errors:array}
 */

/**
 * ادمین اصلی = adminnumber در config.php
 * اگر خالی/placeholder باشد، اولین رکورد جدول admin
 */
function isMainBotAdmin($user_id)
{
    global $adminnumber, $pdo;
    $uid = intval($user_id);
    if ($uid <= 0) {
        return false;
    }
    // از config
    if (isset($adminnumber)) {
        $main = trim(strval($adminnumber));
        // placeholder نصب را نادیده بگیر
        if ($main !== '' && $main !== '{ADMIN_#ID}' && ctype_digit(str_replace(['-', ' '], '', $main))) {
            if (intval($main) === $uid) {
                return true;
            }
        }
    }
    // fallback: اولین ادمین ثبت‌شده در جدول
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $st = $pdo->query("SELECT id_admin FROM admin ORDER BY id_admin ASC LIMIT 1");
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
            if ($row && intval($row['id_admin']) === $uid) {
                return true;
            }
        }
    } catch (Throwable $e) {
    }
    // اگر فقط یک ادمین در لیست هست، همان را اصلی بدان
    try {
        $ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
        if (is_array($ids) && count($ids) === 1 && intval($ids[0]) === $uid) {
            return true;
        }
        // اگر کاربر در جدول admin است و adminnumber ست نشده
        if (is_array($ids) && in_array($uid, array_map('intval', $ids), true)) {
            $main = isset($adminnumber) ? trim(strval($adminnumber)) : '';
            if ($main === '' || $main === '{ADMIN_#ID}') {
                return true;
            }
        }
    } catch (Throwable $e) {
    }
    return false;
}


/**
 * کیبورد بخش ادمین‌ها — دکمه «مشاهده لیست» فقط برای ادمین اصلی
 */
function getAdminSectionPanelKeyboard($user_id = null)
{
    global $textbotlang, $from_id;
    if ($user_id === null) {
        $user_id = $from_id ?? 0;
    }
    $rows = [
        [
            ['text' => $textbotlang['Admin']['Addedadmin']],
            ['text' => $textbotlang['Admin']['Removeedadmin']],
        ],
    ];
    if (function_exists('isMainBotAdmin') && isMainBotAdmin($user_id)) {
        $rows[] = [
            ['text' => $textbotlang['Admin']['manageadmin']['showlistbtn']],
        ];
    }
    $rows[] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    return json_encode([
        'keyboard' => $rows,
        'resize_keyboard' => true,
    ], JSON_UNESCAPED_UNICODE);
}

function updateBotFromGithub()
{
    // سازگاری با نام قدیمی — همان منطق اسکریپت نصب
    if (function_exists('runBotSelfUpdate')) {
        $r = runBotSelfUpdate();
        return [
            'ok' => !empty($r['ok']),
            'msg' => $r['message'] ?? '',
            'updated' => intval($r['files'] ?? 0),
            'skipped' => 0,
            'errors' => empty($r['ok']) ? [$r['message'] ?? 'fail'] : [],
            'backup' => $r['backup'] ?? '',
        ];
    }
    return ['ok' => false, 'msg' => 'تابع runBotSelfUpdate موجود نیست.', 'updated' => 0, 'skipped' => 0, 'errors' => []];
}


function botUpdateProtectedBasenames()
{
    return [
        'config.php',
        'config.local.php',
        '.env',
        'error_log',
    ];
}

function isBotUpdateProtectedFile($basename)
{
    $basename = strval($basename);
    $list = botUpdateProtectedBasenames();
    if (in_array($basename, $list, true)) {
        return true;
    }
    // config.php.* بک‌آپ‌های محلی
    if (strpos($basename, 'config.php.') === 0) {
        return true;
    }
    return false;
}


/**
 * آپدیت امن کد ربات از گیتهاب فورک — فقط فایل‌های غیرحساس
 * - Zip Slip و symlink مسدود می‌شود
 * - فقط از آدرس ثابت گیتهاب فورک دانلود می‌شود
 * @return array{ok:bool, message:string, files?:int}
 */
function runBotSelfUpdate($repoZipUrl = null)
{
    $root = realpath(__DIR__);
    if ($root === false || !is_dir($root)) {
        return ['ok' => false, 'message' => 'مسیر ربات یافت نشد.'];
    }
    if (!is_writable($root)) {
        return ['ok' => false, 'message' => 'پوشه ربات قابل نوشتن نیست (مجوز www-data/apache را چک کنید).'];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'افزونه ZipArchive روی سرور فعال نیست.'];
    }

    $url = 'https://github.com/mhmdh94/botmirzapanel/archive/refs/heads/main.zip';

    @set_time_limit(300);
    @ini_set('max_execution_time', '300');
    @ini_set('memory_limit', '512M');

    $lockFile = $root . DIRECTORY_SEPARATOR . '.update_lock';
    $lockFp = @fopen($lockFile, 'c+');
    if (!$lockFp || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
        if ($lockFp) {
            @fclose($lockFp);
        }
        return ['ok' => false, 'message' => 'آپدیت دیگری در حال اجراست یا قفل آزاد نیست.'];
    }

    $stamp = date('Ymd_His');
    $tmpBase = rtrim(sys_get_temp_dir(), '/\\') . '/mirza_upd_' . getmypid() . '_' . $stamp;
    $zipPath = $tmpBase . '.zip';
    $extractDir = $tmpBase . '_ex';
    $bakDir = $root . DIRECTORY_SEPARATOR . '.update_backups' . DIRECTORY_SEPARATOR . $stamp;
    $filesUpdated = 0;
    $copyFail = 0;
    $copyErrors = [];

    $rmTree = function ($p) {
        if ($p === '' || $p === '/' || $p === '.') {
            return;
        }
        if (is_file($p) || is_link($p)) {
            @unlink($p);
            return;
        }
        if (!is_dir($p)) {
            return;
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $path = $f->getPathname();
                if ($f->isLink() || $f->isFile()) {
                    @unlink($path);
                } elseif ($f->isDir()) {
                    @rmdir($path);
                }
            }
        } catch (Throwable $e) {
        }
        @rmdir($p);
    };

    $cleanup = function () use ($zipPath, $extractDir, $lockFp, $lockFile, $rmTree) {
        $rmTree($zipPath);
        $rmTree($extractDir);
        if (is_resource($lockFp)) {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
        @unlink($lockFile);
    };

    $safeRel = function ($rel) {
        $rel = str_replace('\\', '/', strval($rel));
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, "\0") !== false) {
            return false;
        }
        $parts = [];
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                return false;
            }
            $parts[] = $seg;
        }
        if (count($parts) === 0) {
            return false;
        }
        return implode('/', $parts);
    };

    try {
        // ---- دانلود روی دیسک (نه حافظه) ----
        $fp = @fopen($zipPath, 'wb');
        if (!$fp) {
            // اگر tmp سیستم مشکل داشت داخل پوشه ربات
            $zipPath = $root . DIRECTORY_SEPARATOR . '.update_tmp_' . $stamp . '.zip';
            $extractDir = $root . DIRECTORY_SEPARATOR . '.update_tmp_' . $stamp . '_ex';
            $fp = @fopen($zipPath, 'wb');
        }
        if (!$fp) {
            $cleanup();
            return ['ok' => false, 'message' => 'نمی‌توان فایل zip موقت ساخت (مجوز نوشتن).'];
        }

        $downloadOk = false;
        $lastErr = '';
        foreach ([true, false] as $verifySsl) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                CURLOPT_USERAGENT => 'MirzaBot-SelfUpdate/1.1',
            ]);
            // بعد از تلاش اول فایل را از نو بنویس
            if (!$verifySsl) {
                ftruncate($fp, 0);
                rewind($fp);
            }
            $ok = curl_exec($ch);
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $lastErr = curl_error($ch);
            curl_close($ch);
            if ($ok && $code >= 200 && $code < 300 && filesize($zipPath) > 5000) {
                $downloadOk = true;
                break;
            }
            $lastErr = "HTTP $code " . $lastErr;
        }
        fclose($fp);

        if (!$downloadOk) {
            $cleanup();
            return ['ok' => false, 'message' => 'دانلود از گیتهاب ناموفق: ' . $lastErr];
        }

        @mkdir($extractDir, 0755, true);
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $cleanup();
            return ['ok' => false, 'message' => 'باز کردن zip ناموفق بود.'];
        }
        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            $cleanup();
            return ['ok' => false, 'message' => 'استخراج zip ناموفق بود.'];
        }
        $zip->close();
        @unlink($zipPath);

        // پیدا کردن ریشه کد (botmirzapanel-main)
        $srcRoot = null;
        $entries = @scandir($extractDir) ?: [];
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $extractDir . DIRECTORY_SEPARATOR . $e;
            if (is_dir($p) && is_file($p . '/index.php') && is_file($p . '/functions.php')) {
                $srcRoot = realpath($p);
                break;
            }
        }
        if ($srcRoot === null && is_file($extractDir . '/index.php')) {
            $srcRoot = realpath($extractDir);
        }
        if ($srcRoot === null || !is_file($srcRoot . '/index.php') || !is_file($srcRoot . '/functions.php')) {
            $cleanup();
            return ['ok' => false, 'message' => 'ساختار zip نامعتبر است (index/functions پیدا نشد).'];
        }

        // ---- بک‌آپ فایل‌های حساس ----
        if (!@mkdir($bakDir, 0755, true) && !is_dir($bakDir)) {
            $cleanup();
            return ['ok' => false, 'message' => 'ساخت پوشه بک‌آپ ناموفق: ' . $bakDir];
        }
        foreach (botUpdateProtectedBasenames() as $prot) {
            $cur = $root . DIRECTORY_SEPARATOR . $prot;
            if (is_file($cur) && !is_link($cur)) {
                if (!@copy($cur, $bakDir . DIRECTORY_SEPARATOR . $prot)) {
                    $cleanup();
                    return ['ok' => false, 'message' => 'بک‌آپ ناموفق: ' . $prot];
                }
            }
        }
        foreach (glob($root . '/config.php.*') ?: [] as $cf) {
            if (is_file($cf) && !is_link($cf)) {
                @copy($cf, $bakDir . DIRECTORY_SEPARATOR . basename($cf));
            }
        }
        if (is_file($root . '/config.php') && !is_file($bakDir . '/config.php')) {
            $cleanup();
            return ['ok' => false, 'message' => 'بک‌آپ config.php انجام نشد — آپدیت لغو شد.'];
        }

        // ---- کپی فایل‌ها ----
        $srcRootLen = strlen($srcRoot);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isLink()) {
                continue;
            }
            $full = $item->getPathname();
            if (strpos($full, $srcRoot) !== 0) {
                continue;
            }
            $rel = substr($full, $srcRootLen + 1);
            $rel = $safeRel($rel);
            if ($rel === false) {
                continue;
            }
            $base = basename($rel);
            $top = explode('/', $rel)[0];

            if ($top === '.git' || $top === '.update_backups' || $top === '.github') {
                continue;
            }
            // vendor را از گیتهاب همگام کن اگر در ریپو هست؛ اگر نه، دست نزن
            // (اسکریپت هم vendor را کپی می‌کند مگر protected)

            if (isBotUpdateProtectedFile($base)) {
                continue;
            }

            $dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0755, true);
                }
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }

            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            if (is_link($dest)) {
                @unlink($dest);
            }

            // نوشتن قوی: chmod → temp+rename → copy → put_contents
            $copied = false;
            $data = @file_get_contents($full);
            if ($data === false) {
                $copyFail++;
                if (count($copyErrors) < 12) {
                    $copyErrors[] = $rel . ' (خواندن منبع)';
                }
                continue;
            }
            if (is_file($dest)) {
                @chmod($dest, 0666);
            }
            $tmpWrite = $dest . '.upd.' . getmypid();
            if (@file_put_contents($tmpWrite, $data) !== false) {
                if (@rename($tmpWrite, $dest)) {
                    $copied = true;
                } else {
                    // rename بین فایل‌سیستم‌ها گاهی fail — unlink+copy
                    @unlink($dest);
                    $copied = @rename($tmpWrite, $dest);
                    if (!$copied) {
                        $copied = @copy($tmpWrite, $dest);
                        @unlink($tmpWrite);
                    }
                }
            }
            if (!$copied) {
                @unlink($tmpWrite);
                $copied = @copy($full, $dest);
            }
            if (!$copied) {
                $copied = (@file_put_contents($dest, $data) !== false);
            }
            if ($copied) {
                @chmod($dest, 0644);
                $filesUpdated++;
            } else {
                $copyFail++;
                $why = is_writable(dirname($dest)) ? (is_file($dest) && !is_writable($dest) ? 'مجوز فایل' : 'نوشتن ناموفق') : 'مجوز پوشه';
                if (count($copyErrors) < 12) {
                    $copyErrors[] = $rel . " ($why)";
                }
            }
        }

        // بازگردانی فایل‌های حساس از بک‌آپ
        foreach (scandir($bakDir) ?: [] as $bf) {
            if ($bf === '.' || $bf === '..') {
                continue;
            }
            if (!isBotUpdateProtectedFile($bf) && strpos($bf, 'config.php.') !== 0) {
                continue;
            }
            $srcB = $bakDir . DIRECTORY_SEPARATOR . $bf;
            if (is_file($srcB) && !is_link($srcB)) {
                @copy($srcB, $root . DIRECTORY_SEPARATOR . $bf);
            }
        }

        if (!is_file($root . '/config.php')) {
            $cleanup();
            return ['ok' => false, 'message' => 'خطای بحرانی: config.php بعد از آپدیت نیست.'];
        }
        foreach (['index.php', 'admin.php', 'functions.php'] as $need) {
            if (!is_file($root . DIRECTORY_SEPARATOR . $need)) {
                $cleanup();
                return ['ok' => false, 'message' => 'فایل ضروری کم است: ' . $need];
            }
        }

        $cleanup();

        $critical = ['index.php', 'admin.php', 'functions.php', 'keyboard.php', 'text.php', 'marzban.php', 'panels.php', 'botapi.php'];
        $criticalFailed = [];
        foreach ($copyErrors as $ce) {
            $bn = explode(' ', $ce)[0];
            $bn = basename(str_replace('\\', '/', $bn));
            if (in_array($bn, $critical, true)) {
                $criticalFailed[] = $bn;
            }
        }

        if ($filesUpdated < 1) {
            $hint = $copyFail > 0
                ? ('هیچ فایلی کپی نشد: ' . implode(', ', $copyErrors) . '\nروی سرور اجرا کنید:\nchown -R www-data:www-data ' . $root . '\nchmod -R u+w ' . $root)
                : 'هیچ فایلی برای کپی پیدا نشد.';
            return ['ok' => false, 'message' => $hint, 'files' => 0, 'backup' => $bakDir];
        }

        if (count($criticalFailed) > 0) {
            return [
                'ok' => false,
                'message' => 'فایل‌های حیاتی جایگزین نشدند: ' . implode(', ', array_unique($criticalFailed)) .
                    '\nعلت معمولاً مالکیت root است. روی سرور:\n' .
                    'chown -R www-data:www-data ' . $root . '\n' .
                    'chmod -R u+w ' . $root . '\n' .
                    'بعد دوباره از ربات آپدیت کنید.\nجزئیات: ' . implode(', ', $copyErrors),
                'files' => $filesUpdated,
                'backup' => $bakDir,
                'failed' => $copyFail,
            ];
        }

        $msg = 'ok';
        if ($copyFail > 0) {
            $msg = "بخشی از فایل‌ها کپی نشد ($copyFail): " . implode(', ', $copyErrors);
        }

        return [
            'ok' => true,
            'message' => $msg,
            'files' => $filesUpdated,
            'backup' => $bakDir,
            'failed' => $copyFail,
        ];
    } catch (Throwable $e) {
        if (is_dir($bakDir)) {
            foreach (scandir($bakDir) ?: [] as $bf) {
                if ($bf === '.' || $bf === '..') {
                    continue;
                }
                $srcB = $bakDir . DIRECTORY_SEPARATOR . $bf;
                if (is_file($srcB)) {
                    @copy($srcB, $root . DIRECTORY_SEPARATOR . $bf);
                }
            }
        }
        $cleanup();
        return ['ok' => false, 'message' => 'خطا: ' . $e->getMessage()];
    }
}

/**
 * ثبت دستورات منوی تلگرام (Bot Commands)
 * فقط در صورت تغییر یا نبود کش، یک‌بار به API زده می‌شود.
 */
function ensureBotCommands($force = false)
{
    $cache = __DIR__ . '/.bot_commands_ok';
    $signature = 'v2_start_new_renew_only';
    if (!$force && is_file($cache) && trim(@file_get_contents($cache)) === $signature) {
        return true;
    }
    if (!function_exists('telegram')) {
        return false;
    }
    $commands = [
        ['command' => 'start', 'description' => 'شروع مجدد'],
        ['command' => 'new', 'description' => 'خرید سرویس جدید'],
        ['command' => 'renew', 'description' => 'تمدید سرویس'],
    ];
    $res = telegram('setMyCommands', [
        'commands' => json_encode($commands, JSON_UNESCAPED_UNICODE),
    ]);
    if (is_array($res) && !empty($res['ok'])) {
        @file_put_contents($cache, $signature);
        return true;
    }
    return false;
}

/**
 * کیبورد حالت خالی «سرویس‌های من»: دکمه خرید (+ اختیاری افزودن دستی)
 */
function emptyServicesKeyboard()
{
    global $setting, $textbotlang, $datatextbot;
    $rows = [];
    $buy_label = $datatextbot['text_sell'] ?? ($textbotlang['users']['buybtn'] ?? '🛒 خرید سرویس');
    if (function_exists('isBuyEnabled') && !isBuyEnabled()) {
        // اگر خرید خاموش است دکمه را نگذار
    } else {
        $rows[] = [['text' => $buy_label, 'callback_data' => 'buy']];
    }
    if (($setting['NotUser'] ?? '') == "1" || ($setting['NotUser'] ?? '') === 1) {
        $rows[] = [[
            'text' => $textbotlang['Admin']['Status']['notusenameinbot'] ?? 'افزودن سرویس',
            'callback_data' => 'usernotlist',
        ]];
    }
    if (!$rows) {
        return null;
    }
    return json_encode(['inline_keyboard' => $rows]);
}


/**
 * بازه‌های هفته‌های شمسی‌وار (شنبه تا جمعه) داخل یک ماه میلادی — به وقت تهران
 * @return array[] each: n, from_ts, to_ts, from_label, to_label
 */
function statsMonthWeekRanges($year, $month)
{
    $tz = new DateTimeZone('Asia/Tehran');
    $month = intval($month);
    $year = intval($year);
    $start = new DateTime(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
    $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);
    $w = intval($start->format('w')); // 0=Sun ... 6=Sat
    $days_since_sat = ($w + 1) % 7;
    $cursor = (clone $start)->modify('-' . $days_since_sat . ' days')->setTime(0, 0, 0);
    $weeks = [];
    $n = 1;
    while ($cursor->getTimestamp() <= $end->getTimestamp()) {
        $w_end = (clone $cursor)->modify('+6 days')->setTime(23, 59, 59);
        $from_ts = max($cursor->getTimestamp(), $start->getTimestamp());
        $to_ts = min($w_end->getTimestamp(), $end->getTimestamp());
        if ($from_ts <= $to_ts) {
            $from_dt = (new DateTime('@' . $from_ts))->setTimezone($tz);
            $to_dt = (new DateTime('@' . $to_ts))->setTimezone($tz);
            $weeks[] = [
                'n' => $n,
                'from_ts' => $from_ts,
                'to_ts' => $to_ts,
                'from_label' => $from_dt->format('Y-m-d'),
                'to_label' => $to_dt->format('Y-m-d'),
            ];
            $n++;
        }
        $cursor->modify('+7 days');
    }
    return $weeks;
}

/**
 * ساخت فایل CSV گزارش ماهانه (فروش + واریزی) با تفکیک هفته — قابل باز شدن در Excel
 * @param string $which 'current' | 'prev'
 * @return array{path:string,caption:string}|array{error:string}
 */
function buildMonthlyStatsExcel($which = 'current')
{
    global $pdo;
    if (function_exists('ensureSalesLedger')) {
        ensureSalesLedger();
    }
    $tz = new DateTimeZone('Asia/Tehran');
    $now = new DateTime('now', $tz);
    if ($which === 'prev') {
        $now->modify('first day of last month');
    } else {
        $now->modify('first day of this month');
    }
    $year = intval($now->format('Y'));
    $month = intval($now->format('m'));
    $month_start = new DateTime(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
    $month_end = (clone $month_start)->modify('last day of this month')->setTime(23, 59, 59);
    $from_ts = $month_start->getTimestamp();
    $to_ts = $month_end->getTimestamp();
    $month_label = $month_start->format('Y-m');
    if (function_exists('jdate')) {
        $month_label_fa = jdate('Y/m', $from_ts);
    } else {
        $month_label_fa = $month_label;
    }

    $weeks = statsMonthWeekRanges($year, $month);

    // فروش از sales_ledger
    $sales = [];
    try {
        $st = $pdo->prepare("SELECT id_user, username, price, sale_type, id_invoice, created_at FROM sales_ledger WHERE created_at >= ? AND created_at <= ? ORDER BY created_at ASC");
        $st->execute([$from_ts, $to_ts]);
        $sales = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $sales = [];
    }

    // واریزی paid
    $deps = [];
    try {
        $from_a = $month_start->format('Y/m/d H:i:s');
        $to_a = $month_end->format('Y/m/d H:i:s');
        $from_b = $month_start->format('Y-m-d H:i:s');
        $to_b = $month_end->format('Y-m-d H:i:s');
        $sql = "SELECT id_user, price, Payment_Method, `time`, invoice, id_order
            FROM Payment_report
            WHERE payment_Status = 'paid'
            AND (
                (`time` >= :fa AND `time` <= :ta AND `time` REGEXP '^[0-9]{4}/[0-9]{2}/')
                OR (`time` >= :fb AND `time` <= :tb AND `time` REGEXP '^[0-9]{4}-[0-9]{2}-')
            )
            ORDER BY id ASC";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':fa' => $from_a, ':ta' => $to_a,
            ':fb' => $from_b, ':tb' => $to_b,
        ]);
        $deps = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $deps = [];
    }

    $type_label = function ($t) {
        $t = strval($t);
        if ($t === 'buy') return 'خرید';
        if ($t === 'renew' || $t === 'extend') return 'تمدید';
        if ($t === 'extra_volume' || $t === 'extra') return 'حجم اضافه';
        return $t !== '' ? $t : 'سایر';
    };

    $lines = [];
    $lines[] = ["گزارش ماهانه فروش و واریزی"];
    $lines[] = ["ماه", $month_label, $month_label_fa];
    $lines[] = ["از", $month_start->format('Y-m-d 00:00'), "تا", $month_end->format('Y-m-d 23:59'), "وقت تهران"];
    $lines[] = [];

    // خلاصه فروش
    $sale_sum = 0;
    $sale_cnt = count($sales);
    foreach ($sales as $r) {
        $sale_sum += intval($r['price'] ?? 0);
    }
    $dep_sum = 0;
    $dep_cnt = 0;
    foreach ($deps as $r) {
        $pr = preg_replace('/[^0-9]/', '', strval($r['price'] ?? ''));
        if ($pr === '') continue;
        $dep_cnt++;
        $dep_sum += intval($pr);
    }
    $lines[] = ["خلاصه ماه"];
    $lines[] = ["تعداد فروش", $sale_cnt, "مبلغ فروش (تومان)", $sale_sum];
    $lines[] = ["تعداد واریزی", $dep_cnt, "مبلغ واریزی (تومان)", $dep_sum];
    $lines[] = [];

    // تفکیک هفته‌ای فروش
    $lines[] = ["تفکیک فروش بر اساس هفته (شنبه تا جمعه)"];
    $lines[] = ["هفته", "از", "تا", "تعداد", "مبلغ", "خرید", "تمدید", "حجم اضافه"];
    foreach ($weeks as $w) {
        $c = $s = 0;
        $by = ['buy' => 0, 'renew' => 0, 'extra_volume' => 0];
        foreach ($sales as $r) {
            $ts = intval($r['created_at'] ?? 0);
            if ($ts < $w['from_ts'] || $ts > $w['to_ts']) continue;
            $p = intval($r['price'] ?? 0);
            $c++;
            $s += $p;
            $t = strval($r['sale_type'] ?? 'buy');
            if ($t === 'extend') $t = 'renew';
            if ($t === 'extra') $t = 'extra_volume';
            if (!isset($by[$t])) $by[$t] = 0;
            $by[$t] += $p;
        }
        $lines[] = [
            'هفته ' . $w['n'],
            $w['from_label'],
            $w['to_label'],
            $c,
            $s,
            $by['buy'] ?? 0,
            $by['renew'] ?? 0,
            $by['extra_volume'] ?? 0,
        ];
    }
    $lines[] = [];

    // تفکیک هفته‌ای واریزی
    $lines[] = ["تفکیک واریزی بر اساس هفته"];
    $lines[] = ["هفته", "از", "تا", "تعداد", "مبلغ"];
    foreach ($weeks as $w) {
        $c = $s = 0;
        foreach ($deps as $r) {
            $ts = null;
            $tm = strval($r['time'] ?? '');
            if (preg_match('/^\d{4}\/\d{2}\/\d{2}/', $tm)) {
                $ts = strtotime(str_replace('/', '-', $tm));
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $tm)) {
                $ts = strtotime($tm);
            }
            if ($ts === false || $ts === null) continue;
            if ($ts < $w['from_ts'] || $ts > $w['to_ts']) continue;
            $pr = preg_replace('/[^0-9]/', '', strval($r['price'] ?? ''));
            if ($pr === '') continue;
            $c++;
            $s += intval($pr);
        }
        $lines[] = ['هفته ' . $w['n'], $w['from_label'], $w['to_label'], $c, $s];
    }
    $lines[] = [];

    // جزئیات فروش
    $lines[] = ["جزئیات فروش"];
    $lines[] = ["تاریخ", "نوع", "آیدی کاربر", "یوزرنیم سرویس", "مبلغ", "شناسه فاکتور"];
    foreach ($sales as $r) {
        $ts = intval($r['created_at'] ?? 0);
        $dt = $ts > 0 ? (new DateTime('@' . $ts))->setTimezone($tz)->format('Y-m-d H:i:s') : '';
        $lines[] = [
            $dt,
            $type_label($r['sale_type'] ?? ''),
            $r['id_user'] ?? '',
            $r['username'] ?? '',
            intval($r['price'] ?? 0),
            $r['id_invoice'] ?? '',
        ];
    }
    $lines[] = [];

    // جزئیات واریزی
    $lines[] = ["جزئیات واریزی"];
    $lines[] = ["زمان", "آیدی کاربر", "مبلغ", "روش", "سفارش", "invoice"];
    foreach ($deps as $r) {
        $pr = preg_replace('/[^0-9]/', '', strval($r['price'] ?? ''));
        if ($pr === '') continue;
        $lines[] = [
            $r['time'] ?? '',
            $r['id_user'] ?? '',
            intval($pr),
            $r['Payment_Method'] ?? '',
            $r['id_order'] ?? '',
            $r['invoice'] ?? '',
        ];
    }

    // نوشتن CSV با BOM برای Excel
    $dir = sys_get_temp_dir();
    $fname = 'report_' . $month_label . '_' . ($which === 'prev' ? 'prev' : 'cur') . '_' . time() . '.csv';
    $path = rtrim($dir, '/') . '/' . $fname;
    $fh = fopen($path, 'w');
    if (!$fh) {
        return ['error' => 'ساخت فایل ممکن نشد'];
    }
    // UTF-8 BOM
    fwrite($fh, "\xEF\xBB\xBF");
    foreach ($lines as $row) {
        if (!is_array($row)) {
            $row = [$row];
        }
        // تبدیل به رشته و جلوگیری از فرمول اکسل
        $out = [];
        foreach ($row as $cell) {
            $cell = strval($cell);
            if (isset($cell[0]) && in_array($cell[0], ['=', '+', '-', '@'], true)) {
                $cell = "'" . $cell;
            }
            $out[] = $cell;
        }
        fputcsv($fh, $out);
    }
    fclose($fh);

    $caption = "📊 گزارش " . ($which === 'prev' ? 'ماه قبل' : 'این ماه') . " ({$month_label_fa})\n";
    $caption .= "🛒 فروش: " . number_format($sale_cnt) . " عدد | " . number_format($sale_sum) . " تومان\n";
    $caption .= "💳 واریزی: " . number_format($dep_cnt) . " عدد | " . number_format($dep_sum) . " تومان\n";
    $caption .= "📁 فایل CSV — در Excel باز کنید";

    return ['path' => $path, 'caption' => $caption, 'month' => $month_label];
}




/* ===================== Reseller Panel (isolated, default OFF) ===================== */


/**
 * همه ستون‌های setting که ربات استفاده می‌کند — اگر نبود ساخته می‌شود
 * برای نصب جدید و آپدیت؛ جلوی Fatal error ستون گم‌شده را می‌گیرد
 */
function ensureFeatureSettingsColumns()
{
    global $pdo, $connect;
    static $done = false;
    if ($done) {
        return true;
    }
    // col => default
    $cols = [
        // هسته قدیمی
        'Bot_Status' => '1',
        'roll_Status' => '1',
        'get_number' => '0',
        'iran_number' => '0',
        'help_Status' => '0',
        'NotUser' => '0',
        'limit_usertest_all' => '1',
        'time_usertest' => '1',
        'val_usertest' => '100',
        'namecustome' => '0',
        'removedayc' => '1',
        'Extra_volume' => '0',
        'Channel_Report' => '0',
        'copy_cart' => '0',
        'status_verify' => '0',
        'statuscategory' => '1',
        // قابلیت‌های فورک
        'force_channel' => '1',
        'show_balance' => '0',
        'status_usertest' => '1',
        'status_buy' => '1',
        'status_search_service' => '1',
        'status_affiliates_btn' => '1',
        'status_tariff_list' => '1',
        'status_extra_volume' => '1',
        'status_deposit' => '1',
        'status_support' => '1',
        'status_extend' => '1',
        'status_reseller_panel' => '0',
    ];
    try {
        foreach ($cols as $col => $default) {
            $exists = false;
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $st = $pdo->query("SHOW COLUMNS FROM `setting` LIKE " . $pdo->quote($col));
                    $exists = ($st && $st->fetch()) ? true : false;
                } elseif (isset($connect) && $connect) {
                    $r = @$connect->query("SHOW COLUMNS FROM `setting` LIKE '" . $connect->real_escape_string($col) . "'");
                    $exists = ($r && $r->num_rows > 0);
                }
            } catch (Throwable $e) {
                $exists = false;
            }
            if ($exists) {
                continue;
            }
            $def = str_replace("'", "\\'", strval($default));
            $sql = "ALTER TABLE `setting` ADD COLUMN `{$col}` VARCHAR(50) NOT NULL DEFAULT '{$def}'";
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $pdo->exec($sql);
                } elseif (isset($connect) && $connect) {
                    @$connect->query($sql);
                }
            } catch (Throwable $e) {
                error_log('ensureFeature col ' . $col . ': ' . $e->getMessage());
            }
        }
        $done = true;
        return true;
    } catch (Throwable $e) {
        error_log('ensureFeatureSettingsColumns: ' . $e->getMessage());
        return false;
    }
}

/**
 * اسکیمای ضروری ربات در هر درخواست (یک‌بار در هر process)
 */
function ensureBotRuntimeSchema()
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ensureFeatureSettingsColumns')) {
        ensureFeatureSettingsColumns();
    }
    if (function_exists('ensurePanelAdminSchema')) {
        try { ensurePanelAdminSchema(); } catch (Throwable $e) {}
    }
    // cart_auto_off
    global $pdo;
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $chk = $pdo->query("SHOW COLUMNS FROM `user` LIKE 'cart_auto_off'");
            if (!$chk || !$chk->fetch()) {
                $pdo->exec("ALTER TABLE `user` ADD COLUMN `cart_auto_off` TINYINT NOT NULL DEFAULT 0");
            }
        }
    } catch (Throwable $e) {}
    // جداول کمکی
    foreach (['ensureWalletLog', 'ensureSalesLedger', 'ensureSmartCronStateTable', 'ensureResellerPlanTables', 'ensureResellerWarnStateTable', 'ensureResellerFeatureSetting', 'ensureSmartCronSettings'] as $fn) {
        if (function_exists($fn)) {
            try { $fn(); } catch (Throwable $e) {}
        }
    }
    $done = true;
}


function ensureResellerFeatureSetting()
{
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM setting LIKE 'status_reseller_panel'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE setting ADD COLUMN status_reseller_panel VARCHAR(10) NOT NULL DEFAULT '0'");
        }
    } catch (Throwable $e) {
        error_log('ensureResellerFeatureSetting: ' . $e->getMessage());
    }
}

/** پیش‌فرض خاموش — خرید اشتراک عادی را تحت تأثیر قرار نمی‌دهد
 *  نتیجه در همان درخواست کش می‌شود تا کیبورد چندبار SELECT نزند.
 */
function isResellerFeatureEnabled()
{
    global $pdo;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        ensureResellerFeatureSetting();
        $st = $pdo->query("SELECT status_reseller_panel FROM setting LIMIT 1");
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($row)) {
            $cached = (strval($row['status_reseller_panel'] ?? '0') === '1');
            return $cached;
        }
    } catch (Throwable $e) {
        error_log('isResellerFeatureEnabled: ' . $e->getMessage());
    }
    $cached = false;
    return false;
}


/**
 * مدیریت محصولات فروشگاه (ادمین) — لیست اینلاین؛ بدون تأثیر روی خرید کاربر
 */

/**
 * مدیریت کد تخفیف (DiscountSell) و کد هدیه (Discount) — پنل اینلاین
 */

/**
 * ——— پنل‌های مدیریتی اینلاین (ادمین) ———
 */

function adminPanelsListKeyboard()
{
    global $pdo;
    $rows = [];
    try {
        $st = $pdo->query("SELECT id, name_panel, type, status, url_panel FROM marzban_panel ORDER BY id ASC");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $rows = [];
    }
    $kb = ['inline_keyboard' => []];
    $n = count($rows);
    if (!$rows) {
        $kb['inline_keyboard'][] = [['text' => 'هنوز پنلی ثبت نشده', 'callback_data' => 'adm_noop']];
    } else {
        foreach ($rows as $p) {
            $id = intval($p['id'] ?? 0);
            $name = strval($p['name_panel'] ?? '-');
            if (mb_strlen($name) > 24) {
                $name = mb_substr($name, 0, 22) . '…';
            }
            $type = strval($p['type'] ?? '');
            $st = strval($p['status'] ?? '');
            $icon = ($st === 'activepanel' || $st === 'active' || $st === '') ? '🟢' : '🔴';
            $type_lbl = $type !== '' ? $type : 'panel';
            $kb['inline_keyboard'][] = [[
                'text' => "{$icon} {$name} ({$type_lbl})",
                'callback_data' => 'adm_pan_' . $id,
            ]];
        }
    }
    $kb['inline_keyboard'][] = [
        ['text' => '➕ افزودن پنل', 'callback_data' => 'adm_pan_add'],
        ['text' => '🔄 بروزرسانی', 'callback_data' => 'adm_pan_list'],
    ];
    $text = "🖥 <b>مدیریت پنل‌ها</b>\nتعداد: <b>{$n}</b>\n\nروی پنل بزنید تا تنظیمات باز شود.\nبرای پنل جدید «افزودن پنل» را بزنید.";
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

function adminPanelGetById($id)
{
    global $pdo;
    $id = intval($id);
    if ($id <= 0) {
        return null;
    }
    try {
        $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function adminPanelDetailKeyboard($panel, $extra_msg = '')
{
    $id = intval($panel['id'] ?? 0);
    $name = htmlspecialchars(strval($panel['name_panel'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars(strval($panel['type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars(strval($panel['url_panel'] ?? ''), ENT_QUOTES, 'UTF-8');
    $status = strval($panel['status'] ?? '');
    $st_lbl = ($status === 'disablepanel' || $status === 'inactive') ? '🔴 غیرفعال' : '🟢 فعال';
    $text = "🖥 <b>پنل:</b> <code>{$name}</code>
"
        . "نوع: <b>{$type}</b>
"
        . "وضعیت: {$st_lbl}
"
        . "آدرس: <code>{$url}</code>";
    if ($extra_msg !== '') {
        $text .= "

" . $extra_msg;
    } else {
        $text .= "

یک گزینه را انتخاب کنید:";
    }
    $kb = ['inline_keyboard' => [
        [
            ['text' => '🔗 تست اتصال', 'callback_data' => 'adm_pan_act_connect_' . $id],
            ['text' => '📂 گروه‌ها', 'callback_data' => 'adm_pan_act_groups_' . $id],
        ],
        [
            ['text' => '✏️ نام', 'callback_data' => 'adm_pan_act_name_' . $id],
            ['text' => '🌐 آدرس', 'callback_data' => 'adm_pan_act_url_' . $id],
        ],
        [
            ['text' => '👤 یوزرنیم', 'callback_data' => 'adm_pan_act_user_' . $id],
            ['text' => '🔑 پسورد', 'callback_data' => 'adm_pan_act_pass_' . $id],
        ],
        [
            ['text' => '🗑 حذف پنل', 'callback_data' => 'adm_pan_act_delask_' . $id],
        ],
        [
            ['text' => '🔙 لیست پنل‌ها', 'callback_data' => 'adm_pan_list'],
        ],
    ]];
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

/** نتیجه تست اتصال پنل — متن HTML */
function adminPanelTestConnectionText($panel)
{
    if (!is_array($panel) || empty($panel['name_panel'])) {
        return "❌ پنل نامعتبر است.";
    }
    $name = $panel['name_panel'];
    $type = strval($panel['type'] ?? '');
    $lines = ["🔗 <b>نتیجه تست اتصال</b>
پنل: <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code>"];

    try {
        if ($type === 'marzban' || $type === 'pasarguard') {
            $t0 = microtime(true);
            $Check_token = function_exists('token_panel') ? token_panel($panel['id']) : null;
            $ms_token = (int) round((microtime(true) - $t0) * 1000);
            if (is_array($Check_token) && isset($Check_token['access_token'])) {
                $t1 = microtime(true);
                $System_Stats = function_exists('Get_System_Stats') ? Get_System_Stats($name) : null;
                $ms_stats = (int) round((microtime(true) - $t1) * 1000);
                $active = is_array($System_Stats) ? ($System_Stats['users_active'] ?? $System_Stats['active_users'] ?? '-') : '-';
                $total = is_array($System_Stats) ? ($System_Stats['total_user'] ?? $System_Stats['total_users'] ?? '-') : '-';
                $ver = is_array($System_Stats) ? ($System_Stats['version'] ?? '-') : '-';
                $g_count = 0;
                try {
                    $g_resp = function_exists('get_groups') ? get_groups($name) : null;
                    $g_list = function_exists('_extract_groups_array') ? _extract_groups_array($g_resp) : [];
                    $g_count = is_array($g_list) ? count($g_list) : 0;
                } catch (Throwable $e) {}
                $lines[] = "✅ <b>اتصال موفق</b>";
                $lines[] = "نسخه: <code>{$ver}</code>";
                $lines[] = "کاربران: <b>{$active}</b> فعال از <b>{$total}</b>";
                $lines[] = "گروه‌ها: <b>{$g_count}</b>";
                $lines[] = "⏱ توکن {$ms_token}ms · آمار {$ms_stats}ms";
            } elseif (is_array($Check_token) && (($Check_token['detail'] ?? '') == 'Incorrect username or password' || stripos(strval($Check_token['detail'] ?? ''), 'password') !== false)) {
                $lines[] = "❌ یوزرنیم یا پسورد اشتباه است.";
            } else {
                $err = is_array($Check_token) ? json_encode($Check_token, JSON_UNESCAPED_UNICODE) : 'بدون پاسخ';
                $lines[] = "❌ اتصال ناموفق
<code>" . htmlspecialchars(substr(strval($err), 0, 400), ENT_QUOTES, 'UTF-8') . "</code>";
            }
        } elseif ($type === 'x-ui_single') {
            $r = function_exists('login') ? login($panel['id'], false) : null;
            if (is_array($r) && !empty($r['success'])) {
                $lines[] = "✅ اتصال به x-ui موفق بود.";
            } else {
                $lines[] = "❌ اتصال x-ui ناموفق.";
            }
        } else {
            // تلاش عمومی با token_panel اگر باشد
            if (function_exists('token_panel')) {
                $Check_token = token_panel($panel['id']);
                if (is_array($Check_token) && (isset($Check_token['access_token']) || !empty($Check_token['success']))) {
                    $lines[] = "✅ اتصال برقرار شد.";
                } else {
                    $lines[] = "❌ اتصال ناموفق برای نوع <code>{$type}</code>.";
                }
            } else {
                $lines[] = "⚠️ تست خودکار برای نوع <code>{$type}</code> پشتیبانی نشده؛ تنظیمات را دستی چک کنید.";
            }
        }
    } catch (Throwable $e) {
        $lines[] = "❌ خطا: <code>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</code>";
    }
    return implode("
", $lines);
}

/** لیست گروه‌های پنل — متن HTML */
function adminPanelGroupsText($panel)
{
    if (!is_array($panel) || empty($panel['name_panel'])) {
        return "❌ پنل نامعتبر است.";
    }
    $name = $panel['name_panel'];
    $type = strval($panel['type'] ?? '');
    if (!in_array($type, ['marzban', 'pasarguard'], true)) {
        return "📂 گروه‌ها فقط برای پاسارگارد/مرزبان در دسترس است.";
    }
    try {
        $g_resp = function_exists('get_groups') ? get_groups($name) : null;
        $g_list = function_exists('_extract_groups_array') ? _extract_groups_array($g_resp) : (is_array($g_resp) ? $g_resp : []);
        if (!is_array($g_list) || count($g_list) === 0) {
            return "❌ گروهی از پنل خوانده نشد.
آدرس و یوزر/پسورد را چک کنید.";
        }
        $lines = ["📂 <b>گروه‌های پنل</b> <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code>
"];
        foreach ($g_list as $g) {
            if (!is_array($g)) {
                continue;
            }
            $gid = $g['id'] ?? $g['group_id'] ?? '?';
            $gn = htmlspecialchars(strval($g['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $dis = (!empty($g['is_disabled']) || !empty($g['disabled'])) ? ' (غیرفعال)' : '';
            $lines[] = "• <code>{$gid}</code> — {$gn}{$dis}";
        }
        $lines[] = "
✅ ساخت کاربر با این گروه‌ها انجام می‌شود.";
        return implode("
", $lines);
    } catch (Throwable $e) {
        return "❌ خطا در خواندن گروه: <code>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</code>";
    }
}

function adminCategoriesListKeyboard()
{
    global $pdo;
    $rows = [];
    try {
        $st = $pdo->query("SELECT * FROM category ORDER BY id ASC");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $rows = [];
    }
    $kb = ['inline_keyboard' => []];
    $n = count($rows);
    if (!$rows) {
        $kb['inline_keyboard'][] = [['text' => 'هنوز دسته‌ای نیست', 'callback_data' => 'adm_noop']];
    } else {
        foreach ($rows as $c) {
            $id = intval($c['id'] ?? 0);
            $remark = strval($c['remark'] ?? '-');
            if (mb_strlen($remark) > 30) {
                $remark = mb_substr($remark, 0, 28) . '…';
            }
            $kb['inline_keyboard'][] = [
                ['text' => '🗑', 'callback_data' => 'adm_cat_del_' . $id],
                ['text' => $remark, 'callback_data' => 'adm_noop'],
            ];
        }
    }
    $kb['inline_keyboard'][] = [['text' => '➕ افزودن دسته', 'callback_data' => 'adm_cat_add']];
    $kb['inline_keyboard'][] = [['text' => '🔄 بروزرسانی', 'callback_data' => 'adm_cat_list']];
    $text = "📁 <b>مدیریت دسته‌بندی محصولات</b>\nتعداد: <b>{$n}</b>\n\n🗑 = حذف دسته";
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

function adminAdminsListKeyboard($viewer_id)
{
    global $pdo, $adminnumber;
    $ids = [];
    try {
        $st = $pdo->query("SELECT id_admin FROM admin ORDER BY id_admin ASC");
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = strval($r['id_admin']);
        }
    } catch (Throwable $e) {}
    if ($adminnumber && !in_array(strval($adminnumber), $ids, true)) {
        array_unshift($ids, strval($adminnumber));
        $ids = array_values(array_unique($ids));
    }
    $kb = ['inline_keyboard' => []];
    $n = count($ids);
    if (!$ids) {
        $kb['inline_keyboard'][] = [['text' => 'ادمینی ثبت نشده', 'callback_data' => 'adm_noop']];
    } else {
        foreach ($ids as $aid) {
            $is_main = (strval($adminnumber) === strval($aid));
            $label = $is_main ? "⭐ {$aid} (اصلی)" : $aid;
            $row = [['text' => $label, 'callback_data' => 'adm_noop']];
            if (!$is_main) {
                $row[] = ['text' => '🗑', 'callback_data' => 'adm_adm_del_' . $aid];
            }
            $kb['inline_keyboard'][] = $row;
        }
    }
    $kb['inline_keyboard'][] = [['text' => '➕ افزودن ادمین', 'callback_data' => 'adm_adm_add']];
    $kb['inline_keyboard'][] = [['text' => '🔄 بروزرسانی', 'callback_data' => 'adm_adm_list']];
    $text = "👮 <b>مدیریت ادمین‌ها</b>\nتعداد: <b>{$n}</b>\n\nادمین اصلی قابل حذف نیست.";
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

function adminUsertestPanel()
{
    global $pdo;
    $time = '-';
    $vol = '-';
    $lim = '-';
    try {
        $st = $pdo->query("SELECT time_usertest, val_usertest, limit_usertest_all FROM setting LIMIT 1");
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) {
            $time = strval($row['time_usertest'] ?? '-');
            $vol = strval($row['val_usertest'] ?? '-');
            $lim = strval($row['limit_usertest_all'] ?? '-');
        }
    } catch (Throwable $e) {}
    $text = "🧪 <b>تنظیمات اکانت تست</b>\n\n"
        . "⏱ زمان: <b>{$time}</b> ساعت\n"
        . "📊 حجم: <b>{$vol}</b> گیگ\n"
        . "🔢 سقف تست برای هر کاربر: <b>{$lim}</b>\n\n"
        . "برای تغییر، گزینه را بزنید:";
    $kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "⏱ زمان ({$time}س)", 'callback_data' => 'adm_ut_time'],
                ['text' => "📊 حجم ({$vol}گ)", 'callback_data' => 'adm_ut_vol'],
            ],
            [
                ['text' => "🔢 سقف همه ({$lim})", 'callback_data' => 'adm_ut_limit'],
            ],
            [
                ['text' => '♻️ ریست سقف تست همه کاربران', 'callback_data' => 'adm_ut_reset'],
            ],
            [
                ['text' => '🔄 بروزرسانی', 'callback_data' => 'adm_ut_panel'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
    return [$text, $kb];
}


function codesAdminHubText()
{
    global $pdo;
    $n_disc = 0;
    $n_gift = 0;
    try {
        $n_disc = intval($pdo->query("SELECT COUNT(*) FROM DiscountSell")->fetchColumn());
    } catch (Throwable $e) {}
    try {
        $n_gift = intval($pdo->query("SELECT COUNT(*) FROM Discount")->fetchColumn());
    } catch (Throwable $e) {}
    return "🎟 <b>مدیریت کد تخفیف و هدیه</b>\n\n"
        . "🏷 کد تخفیف خرید: <b>{$n_disc}</b> عدد\n"
        . "🎁 کد هدیه (شارژ کیف): <b>{$n_gift}</b> عدد\n\n"
        . "یک بخش را انتخاب کنید:";
}

function codesAdminHubKeyboard()
{
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => '🏷 کدهای تخفیف', 'callback_data' => 'codes_disc_list'],
                ['text' => '🎁 کدهای هدیه', 'callback_data' => 'codes_gift_list'],
            ],
            [
                ['text' => '🔄 بروزرسانی', 'callback_data' => 'codes_hub'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function codesDiscountListKeyboard($page = 0)
{
    global $pdo;
    $page = max(0, intval($page));
    $per = 12;
    $offset = $page * $per;
    $rows = [];
    $total = 0;
    try {
        $total = intval($pdo->query("SELECT COUNT(*) FROM DiscountSell")->fetchColumn());
        $st = $pdo->query(
            "SELECT * FROM DiscountSell ORDER BY codeDiscount ASC LIMIT " . intval($per) . " OFFSET " . intval($offset)
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $rows = [];
    }
    $pages = max(1, (int)ceil(max(1, $total) / $per));
    if ($page >= $pages) {
        $page = $pages - 1;
    }
    $kb = ['inline_keyboard' => []];
    if (!$rows) {
        $kb['inline_keyboard'][] = [['text' => 'هنوز کد تخفیفی ثبت نشده', 'callback_data' => 'codes_noop']];
    } else {
        $kb['inline_keyboard'][] = [
            ['text' => 'استفاده/سقف', 'callback_data' => 'codes_noop'],
            ['text' => 'درصد', 'callback_data' => 'codes_noop'],
            ['text' => 'کد', 'callback_data' => 'codes_noop'],
        ];
        foreach ($rows as $r) {
            $code = strval($r['codeDiscount'] ?? '');
            if ($code === '') {
                continue;
            }
            $price = strval($r['price'] ?? '0');
            $used = strval($r['usedDiscount'] ?? '0');
            $lim = strval($r['limitDiscount'] ?? '0');
            $cb = 'codes_disc_v_' . substr(md5($code), 0, 10);
            // store map via Processing not needed — use base64url short in callback max 64 bytes
            $safe = rawurlencode($code);
            if (strlen('codes_disc_v_' . $safe) > 60) {
                $safe = substr(md5($code), 0, 12);
            }
            $label_code = mb_strlen($code) > 16 ? (mb_substr($code, 0, 14) . '…') : $code;
            $kb['inline_keyboard'][] = [
                ['text' => "{$used}/{$lim}", 'callback_data' => 'codes_disc_v_' . $safe],
                ['text' => $price . '%', 'callback_data' => 'codes_disc_v_' . $safe],
                ['text' => $label_code, 'callback_data' => 'codes_disc_v_' . $safe],
            ];
        }
    }
    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '◀️', 'callback_data' => 'codes_disc_list_' . ($page - 1)];
    }
    if ($page < $pages - 1 && $total > $per) {
        $nav[] = ['text' => '▶️', 'callback_data' => 'codes_disc_list_' . ($page + 1)];
    }
    if ($nav) {
        $kb['inline_keyboard'][] = $nav;
    }
    $kb['inline_keyboard'][] = [['text' => '➕ افزودن کد تخفیف', 'callback_data' => 'codes_disc_add']];
    $kb['inline_keyboard'][] = [
        ['text' => '🔙 بازگشت', 'callback_data' => 'codes_hub'],
        ['text' => '🔄 بروزرسانی', 'callback_data' => 'codes_disc_list'],
    ];
    $text = "🏷 <b>کدهای تخفیف خرید</b>\n"
        . "تعداد: <b>{$total}</b>\n"
        . "صفحه " . ($page + 1) . ( $total > $per ? " از {$pages}" : "" ) . "\n\n"
        . "روی یک کد بزنید تا جزئیات و حذف را ببینید.";
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

function codesGiftListKeyboard($page = 0)
{
    global $pdo;
    $page = max(0, intval($page));
    $per = 12;
    $offset = $page * $per;
    $rows = [];
    $total = 0;
    try {
        $total = intval($pdo->query("SELECT COUNT(*) FROM Discount")->fetchColumn());
        $st = $pdo->query(
            "SELECT * FROM Discount ORDER BY code ASC LIMIT " . intval($per) . " OFFSET " . intval($offset)
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $rows = [];
    }
    $pages = max(1, (int)ceil(max(1, $total) / $per));
    $kb = ['inline_keyboard' => []];
    if (!$rows) {
        $kb['inline_keyboard'][] = [['text' => 'هنوز کد هدیه‌ای ثبت نشده', 'callback_data' => 'codes_noop']];
    } else {
        $kb['inline_keyboard'][] = [
            ['text' => 'مبلغ (تومان)', 'callback_data' => 'codes_noop'],
            ['text' => 'کد', 'callback_data' => 'codes_noop'],
        ];
        foreach ($rows as $r) {
            $code = strval($r['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $price = number_format(intval($r['price'] ?? 0));
            $safe = rawurlencode($code);
            if (strlen('codes_gift_v_' . $safe) > 60) {
                $safe = substr(md5($code), 0, 12);
            }
            $label_code = mb_strlen($code) > 18 ? (mb_substr($code, 0, 16) . '…') : $code;
            $kb['inline_keyboard'][] = [
                ['text' => $price, 'callback_data' => 'codes_gift_v_' . $safe],
                ['text' => $label_code, 'callback_data' => 'codes_gift_v_' . $safe],
            ];
        }
    }
    $nav = [];
    if ($page > 0) {
        $nav[] = ['text' => '◀️', 'callback_data' => 'codes_gift_list_' . ($page - 1)];
    }
    if ($page < $pages - 1 && $total > $per) {
        $nav[] = ['text' => '▶️', 'callback_data' => 'codes_gift_list_' . ($page + 1)];
    }
    if ($nav) {
        $kb['inline_keyboard'][] = $nav;
    }
    $kb['inline_keyboard'][] = [['text' => '➕ افزودن کد هدیه', 'callback_data' => 'codes_gift_add']];
    $kb['inline_keyboard'][] = [
        ['text' => '🔙 بازگشت', 'callback_data' => 'codes_hub'],
        ['text' => '🔄 بروزرسانی', 'callback_data' => 'codes_gift_list'],
    ];
    $text = "🎁 <b>کدهای هدیه (شارژ کیف)</b>\n"
        . "تعداد: <b>{$total}</b>\n"
        . "صفحه " . ($page + 1) . ($total > $per ? " از {$pages}" : "") . "\n\n"
        . "روی یک کد بزنید تا جزئیات و حذف را ببینید.";
    return [$text, json_encode($kb, JSON_UNESCAPED_UNICODE)];
}

function codesResolveDiscountCode($token)
{
    global $pdo;
    $token = strval($token);
    $decoded = rawurldecode($token);
    try {
        $st = $pdo->prepare("SELECT * FROM DiscountSell WHERE codeDiscount = ? LIMIT 1");
        $st->execute([$decoded]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        // fallback md5 short
        $st = $pdo->query("SELECT * FROM DiscountSell");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = strval($r['codeDiscount'] ?? '');
            if (substr(md5($c), 0, 12) === $token || substr(md5($c), 0, 10) === $token) {
                return $r;
            }
        }
    } catch (Throwable $e) {}
    return null;
}

function codesResolveGiftCode($token)
{
    global $pdo;
    $token = strval($token);
    $decoded = rawurldecode($token);
    try {
        $st = $pdo->prepare("SELECT * FROM Discount WHERE code = ? LIMIT 1");
        $st->execute([$decoded]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        $st = $pdo->query("SELECT * FROM Discount");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = strval($r['code'] ?? '');
            if (substr(md5($c), 0, 12) === $token || substr(md5($c), 0, 10) === $token) {
                return $r;
            }
        }
    } catch (Throwable $e) {}
    return null;
}

function codesDiscountDetail($row)
{
    $code = htmlspecialchars(strval($row['codeDiscount'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = strval($row['price'] ?? '0');
    $used = strval($row['usedDiscount'] ?? '0');
    $lim = strval($row['limitDiscount'] ?? '0');
    $first = strval($row['usefirst'] ?? '0');
    $first_lbl = ($first === '1') ? 'فقط خرید اول' : 'همه خریدها';
    $text = "🏷 <b>جزئیات کد تخفیف</b>\n\n"
        . "کد: <code>{$code}</code>\n"
        . "درصد: <b>{$price}٪</b>\n"
        . "استفاده: <b>{$used}</b> از <b>{$lim}</b>\n"
        . "نوع: {$first_lbl}";
    $raw = strval($row['codeDiscount'] ?? '');
    $safe = rawurlencode($raw);
    if (strlen('codes_disc_del_' . $safe) > 60) {
        $safe = substr(md5($raw), 0, 12);
    }
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '🗑 حذف این کد', 'callback_data' => 'codes_disc_del_' . $safe]],
            [['text' => '🔙 لیست کدهای تخفیف', 'callback_data' => 'codes_disc_list']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    return [$text, $kb];
}

function codesGiftDetail($row)
{
    $code = htmlspecialchars(strval($row['code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = number_format(intval($row['price'] ?? 0));
    $text = "🎁 <b>جزئیات کد هدیه</b>\n\n"
        . "کد: <code>{$code}</code>\n"
        . "مبلغ شارژ: <b>{$price}</b> تومان";
    $raw = strval($row['code'] ?? '');
    $safe = rawurlencode($raw);
    if (strlen('codes_gift_del_' . $safe) > 60) {
        $safe = substr(md5($raw), 0, 12);
    }
    $kb = json_encode([
        'inline_keyboard' => [
            [['text' => '🗑 حذف این کد', 'callback_data' => 'codes_gift_del_' . $safe]],
            [['text' => '🔙 لیست کدهای هدیه', 'callback_data' => 'codes_gift_list']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    return [$text, $kb];
}


function shopGetAllProducts()
{
    global $pdo;
    try {
        $st = $pdo->query("SELECT * FROM product WHERE (name_product IS NOT NULL AND name_product != '' AND name_product != 'usertest') ORDER BY id ASC");
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function shopGetProductsByLocation($location)
{
    global $pdo;
    $location = strval($location);
    try {
        $st = $pdo->prepare("SELECT * FROM product WHERE Location = ? AND (name_product IS NOT NULL AND name_product != '' AND name_product != 'usertest') ORDER BY id ASC");
        $st->execute([$location]);
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * موقعیت‌های محصول: از خود product + پنل‌های ثبت‌شده
 * @return array list of ['name'=>string,'count'=>int]
 */
function shopGetProductLocations()
{
    global $pdo;
    $map = [];
    try {
        $st = $pdo->query("SELECT Location AS loc, COUNT(*) AS cnt FROM product WHERE (name_product IS NOT NULL AND name_product != '' AND name_product != 'usertest') GROUP BY Location ORDER BY Location ASC");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $loc = strval($r['loc'] ?? '');
            if ($loc === '') {
                $loc = '(بدون موقعیت)';
            }
            $map[$loc] = intval($r['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
    }
    // پنل‌هایی که هنوز محصول ندارند هم در لیست باشند
    try {
        $st = $pdo->query("SELECT name_panel FROM marzban_panel ORDER BY id ASC");
        $panels = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($panels as $p) {
            $n = strval($p['name_panel'] ?? '');
            if ($n !== '' && !isset($map[$n])) {
                $map[$n] = 0;
            }
        }
    } catch (Throwable $e) {
    }
    $out = [];
    foreach ($map as $name => $cnt) {
        $out[] = ['name' => $name, 'count' => $cnt];
    }
    return $out;
}

function shopLocationKey($location)
{
    return substr(md5(strval($location)), 0, 12);
}

function shopResolveLocationKey($key)
{
    $key = strval($key);
    foreach (shopGetProductLocations() as $item) {
        if (shopLocationKey($item['name']) === $key) {
            return $item['name'];
        }
    }
    // fallback: جستجو در همه محصولات
    foreach (shopGetAllProducts() as $p) {
        $loc = strval($p['Location'] ?? '');
        if ($loc === '') {
            $loc = '(بدون موقعیت)';
        }
        if (shopLocationKey($loc) === $key) {
            return $loc;
        }
    }
    return null;
}

function shopGetProductByCode($code)
{
    global $pdo;
    $code = trim(strval($code));
    if ($code === '') {
        return null;
    }
    try {
        $st = $pdo->prepare("SELECT * FROM product WHERE code_product = ? LIMIT 1");
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * اگر $location خالی باشد: لیست موقعیت‌ها
 * اگر مقدار داشته باشد: محصولات همان موقعیت
 */
function shopProductListKeyboard($location = null)
{
    $kb = ['inline_keyboard' => []];

    if ($location === null || $location === '') {
        $locs = shopGetProductLocations();
        if (!$locs) {
            $kb['inline_keyboard'][] = [['text' => 'هنوز محصول یا پنلی ثبت نشده', 'callback_data' => 'shop_noop']];
        } else {
            foreach ($locs as $item) {
                $name = strval($item['name']);
                $cnt = intval($item['count']);
                $label = $name;
                if (mb_strlen($label) > 30) {
                    $label = mb_substr($label, 0, 28) . '…';
                }
                $key = shopLocationKey($name);
                $kb['inline_keyboard'][] = [[
                    'text' => "📍 {$label} ({$cnt})",
                    'callback_data' => 'shop_loc_' . $key,
                ]];
            }
        }
        $kb['inline_keyboard'][] = [['text' => '➕ افزودن محصول جدید', 'callback_data' => 'shop_add']];
        $kb['inline_keyboard'][] = [['text' => '🔄 بروزرسانی لیست', 'callback_data' => 'shop_list']];
        return json_encode($kb, JSON_UNESCAPED_UNICODE);
    }

    // محصولات یک موقعیت
    $loc_query = ($location === '(بدون موقعیت)') ? '' : $location;
    if ($location === '(بدون موقعیت)') {
        try {
            global $pdo;
            $st = $pdo->query("SELECT * FROM product WHERE (Location IS NULL OR Location = '') AND (name_product IS NOT NULL AND name_product != '' AND name_product != 'usertest') ORDER BY id ASC");
            $products = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $products = [];
        }
    } else {
        $products = shopGetProductsByLocation($location);
    }

    if (!$products) {
        $kb['inline_keyboard'][] = [['text' => 'محصولی در این موقعیت نیست', 'callback_data' => 'shop_noop']];
    } else {
        $kb['inline_keyboard'][] = [
            ['text' => '💵 قیمت', 'callback_data' => 'shop_noop'],
            ['text' => '📦 محصول', 'callback_data' => 'shop_noop'],
        ];
        foreach ($products as $p) {
            $code = strval($p['code_product'] ?? '');
            if ($code === '') {
                continue;
            }
            $name = strval($p['name_product'] ?? '-');
            if (mb_strlen($name) > 28) {
                $name = mb_substr($name, 0, 26) . '…';
            }
            $price = number_format(intval($p['price_product'] ?? 0));
            $kb['inline_keyboard'][] = [
                ['text' => $price . ' ت', 'callback_data' => 'shop_p_' . $code],
                ['text' => $name, 'callback_data' => 'shop_p_' . $code],
            ];
        }
    }
    $kb['inline_keyboard'][] = [['text' => '➕ افزودن محصول جدید', 'callback_data' => 'shop_add']];
    $kb['inline_keyboard'][] = [['text' => '🔙 همه موقعیت‌ها', 'callback_data' => 'shop_list']];
    return json_encode($kb, JSON_UNESCAPED_UNICODE);
}

function shopProductListTitle($location = null)
{
    if ($location === null || $location === '') {
        $locs = shopGetProductLocations();
        $n = count($locs);
        $total = count(shopGetAllProducts());
        return "🏬 <b>مدیریت محصولات</b>\nموقعیت‌ها: <b>{$n}</b> | کل محصولات: <b>{$total}</b>\n\nروی یک موقعیت بزنید تا محصولات همان پنل را ببینید.";
    }
    $safe = htmlspecialchars(strval($location), ENT_QUOTES, 'UTF-8');
    if ($location === '(بدون موقعیت)') {
        $products = [];
        try {
            global $pdo;
            $st = $pdo->query("SELECT * FROM product WHERE (Location IS NULL OR Location = '') AND (name_product IS NOT NULL AND name_product != '' AND name_product != 'usertest')");
            $products = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
        }
    } else {
        $products = shopGetProductsByLocation($location);
    }
    $n = count($products);
    return "🏬 <b>مدیریت محصولات</b>\n📍 موقعیت: <code>{$safe}</code>\nتعداد: <b>{$n}</b>\n\nروی محصول بزنید تا ویرایش کنید.";
}

function shopProductDetailKeyboard($code)
{
    $code = strval($code);
    return json_encode(['inline_keyboard' => [
        [
            ['text' => '💵 قیمت', 'callback_data' => 'shop_ep_price_' . $code],
            ['text' => '📊 حجم', 'callback_data' => 'shop_ep_vol_' . $code],
            ['text' => '⏳ زمان', 'callback_data' => 'shop_ep_time_' . $code],
        ],
        [
            ['text' => '✏️ نام', 'callback_data' => 'shop_ep_name_' . $code],
            ['text' => '📁 دسته', 'callback_data' => 'shop_ep_cat_' . $code],
        ],
        [
            ['text' => '🗑 حذف محصول', 'callback_data' => 'shop_ep_del_' . $code],
        ],
        [
            ['text' => '🔙 بازگشت به لیست', 'callback_data' => 'shop_list'],
        ],
    ]], JSON_UNESCAPED_UNICODE);
}

function shopProductDetailText($p)
{
    if (!is_array($p)) {
        return 'محصول یافت نشد.';
    }
    $name = htmlspecialchars(strval($p['name_product'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $price = number_format(intval($p['price_product'] ?? 0));
    $vol = htmlspecialchars(strval($p['Volume_constraint'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars(strval($p['Service_time'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $loc = htmlspecialchars(strval($p['Location'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars(strval($p['code_product'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $cat = strval($p['Category'] ?? $p['category'] ?? '-');
    return "📦 <b>جزئیات محصول</b>\n\n"
        . "🏷 نام: <b>{$name}</b>\n"
        . "💵 قیمت: <b>{$price}</b> تومان\n"
        . "📊 حجم: <b>{$vol}</b> گیگ\n"
        . "⏳ زمان: <b>{$time}</b> روز\n"
        . "📍 پنل: <code>{$loc}</code>\n"
        . "🔑 کد: <code>{$code}</code>\n\n"
        . "برای ویرایش یکی از گزینه‌ها را بزنید.";
}


function ensurePanelAdminSchema()
{
    global $pdo;
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM user LIKE 'panel_admin_enabled'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD COLUMN panel_admin_enabled TINYINT NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {}
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM user LIKE 'panel_admin_username'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD COLUMN panel_admin_username VARCHAR(512) NOT NULL DEFAULT ''");
        } else {
            try {
                $pdo->exec("ALTER TABLE user MODIFY COLUMN panel_admin_username VARCHAR(512) NOT NULL DEFAULT ''");
            } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM user LIKE 'panel_admin_panel_id'");
        if ($chk && $chk->rowCount() == 0) {
            $pdo->exec("ALTER TABLE user ADD COLUMN panel_admin_panel_id INT NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {}
    if (function_exists('ensureResellerPlanTables')) {
        ensureResellerPlanTables();
    }
}

/**
 * جداول پلن/سفارش ریسلر — CREATE + افزودن ستون‌های کم‌بوده (بدون DROP)
 * سازگار با جداول قدیمی که status نداشتند
 */
function ensureResellerPlanTables()
{
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_volume_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            volume_gb INT NOT NULL DEFAULT 0,
            price INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensureResellerPlanTables create plan: ' . $e->getMessage());
    }

    // ستون‌های ضروری را در صورت نبود اضافه کن
    $need = [
        'volume_gb' => "INT NOT NULL DEFAULT 0",
        'price' => "INT NOT NULL DEFAULT 0",
        'status' => "TINYINT NOT NULL DEFAULT 1",
        'sort_order' => "INT NOT NULL DEFAULT 0",
        'created_at' => "INT NOT NULL DEFAULT 0",
    ];
    foreach ($need as $col => $def) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM reseller_volume_plan LIKE " . $pdo->quote($col));
            if ($chk && $chk->rowCount() == 0) {
                $pdo->exec("ALTER TABLE reseller_volume_plan ADD COLUMN `{$col}` {$def}");
            }
        } catch (Throwable $e) {
            error_log("ensureResellerPlanTables add {$col}: " . $e->getMessage());
        }
    }

    // اگر جدول قدیمی is_active داشت و status تازه اضافه شده، مقدار را کپی کن
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM reseller_volume_plan LIKE 'is_active'");
        if ($chk && $chk->rowCount() > 0) {
            $pdo->exec("UPDATE reseller_volume_plan SET status = IF(is_active IN (1,'1','on'), 1, 0) WHERE status IS NULL OR status = 0 OR status = 1");
            // فقط یک‌بار همگام‌سازی ملایم: ردیف‌هایی که is_active=1
            $pdo->exec("UPDATE reseller_volume_plan SET status = 1 WHERE is_active IN (1,'1')");
            $pdo->exec("UPDATE reseller_volume_plan SET status = 0 WHERE is_active IN (0,'0')");
        }
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_volume_order (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_user BIGINT NOT NULL DEFAULT 0,
            plan_id INT NOT NULL DEFAULT 0,
            volume_gb INT NOT NULL DEFAULT 0,
            price INT NOT NULL DEFAULT 0,
            panel_admin_username VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensureResellerPlanTables create order: ' . $e->getMessage());
    }

    $need_o = [
        'id_user' => "BIGINT NOT NULL DEFAULT 0",
        'plan_id' => "INT NOT NULL DEFAULT 0",
        'volume_gb' => "INT NOT NULL DEFAULT 0",
        'price' => "INT NOT NULL DEFAULT 0",
        'panel_admin_username' => "VARCHAR(191) NOT NULL DEFAULT ''",
        'status' => "VARCHAR(32) NOT NULL DEFAULT 'pending'",
        'created_at' => "INT NOT NULL DEFAULT 0",
    ];
    foreach ($need_o as $col => $def) {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM reseller_volume_order LIKE " . $pdo->quote($col));
            if ($chk && $chk->rowCount() == 0) {
                $pdo->exec("ALTER TABLE reseller_volume_order ADD COLUMN `{$col}` {$def}");
            }
        } catch (Throwable $e) {
            error_log("ensureResellerPlanTables order {$col}: " . $e->getMessage());
        }
    }
}

/** درج امن پلن حجم ریسلر — سازگار با هر اسکیمای موجود جدول */
function resellerInsertPlan($volume_gb, $price)
{
    global $pdo;
    ensureResellerPlanTables();
    $volume_gb = intval($volume_gb);
    $price = intval($price);
    if ($volume_gb < 1 || $price < 1) {
        return ['ok' => false, 'message' => 'مقادیر نامعتبر'];
    }

    // ستون‌های واقعی جدول را بخوان
    $cols = [];
    try {
        $st = $pdo->query("SHOW COLUMNS FROM reseller_volume_plan");
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cols[strtolower($row['Field'])] = $row;
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
    if (!$cols) {
        return ['ok' => false, 'message' => 'جدول پلن یافت نشد'];
    }

    $title = $volume_gb . 'GB';
    $data = [];
    // فقط فیلدهایی که وجود دارند
    if (isset($cols['volume_gb'])) {
        $data['volume_gb'] = $volume_gb;
    } elseif (isset($cols['volume'])) {
        $data['volume'] = $volume_gb;
    } elseif (isset($cols['gb'])) {
        $data['gb'] = $volume_gb;
    }
    if (isset($cols['price'])) {
        $data['price'] = $price;
    } elseif (isset($cols['amount'])) {
        $data['amount'] = $price;
    }
    if (isset($cols['status'])) {
        $data['status'] = 1;
    }
    if (isset($cols['is_active'])) {
        $data['is_active'] = 1;
    }
    if (isset($cols['sort_order'])) {
        $data['sort_order'] = $volume_gb;
    }
    if (isset($cols['created_at'])) {
        $data['created_at'] = time();
    }
    if (isset($cols['title'])) {
        $data['title'] = $title;
    }
    if (isset($cols['name'])) {
        $data['name'] = $title;
    }
    if (isset($cols['label'])) {
        $data['label'] = $title;
    }

    // هر ستون NOT NULL بدون default که پر نشده را با مقدار امن پر کن
    foreach ($cols as $fname => $meta) {
        if ($fname === 'id') {
            continue;
        }
        if (isset($data[$fname])) {
            continue;
        }
        $null = strtoupper(strval($meta['Null'] ?? 'YES'));
        $default = $meta['Default'] ?? null;
        $extra = strtolower(strval($meta['Extra'] ?? ''));
        if (strpos($extra, 'auto_increment') !== false) {
            continue;
        }
        if ($null === 'NO' && $default === null) {
            $type = strtolower(strval($meta['Type'] ?? ''));
            if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false) {
                $data[$fname] = 0;
            } else {
                $data[$fname] = ($fname === 'title' || $fname === 'name') ? $title : '';
            }
        }
    }

    if (!$data) {
        return ['ok' => false, 'message' => 'هیچ ستونی برای درج پیدا نشد'];
    }

    $fields = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = 'INSERT INTO reseller_volume_plan (`' . implode('`,`', $fields) . '`) VALUES (' . $placeholders . ')';
    try {
        $st = $pdo->prepare($sql);
        $st->execute(array_values($data));
        return ['ok' => true, 'id' => intval($pdo->lastInsertId())];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}


function isUserPanelAdmin($user_id)
{
    static $cache = [];
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return false;
    }
    if (array_key_exists($user_id, $cache)) {
        return $cache[$user_id];
    }
    try {
        ensurePanelAdminSchema();
        $u = select("user", "panel_admin_enabled", "id", $user_id, "select");
        if (is_array($u)) {
            $cache[$user_id] = (intval($u['panel_admin_enabled'] ?? 0) === 1);
        } else {
            $cache[$user_id] = (intval($u) === 1);
        }
        return $cache[$user_id];
    } catch (Throwable $e) {
        $cache[$user_id] = false;
        return false;
    }
}


/** نمایش حجم ریسلر: از ۱۰۲۴ گیگ به بالا به ترابایت (۱ TB = ۱۰۲۴ GB) */
function formatResellerVolumeLabel($gb, $with_unit = true)
{
    $gb = floatval($gb);
    if ($gb >= 1024) {
        $tb = $gb / 1024;
        if (abs($tb - round($tb)) < 0.001) {
            $s = number_format(intval(round($tb)));
        } else {
            $s = rtrim(rtrim(number_format($tb, 2, '.', ''), '0'), '.');
        }
        return $with_unit ? ($s . ' ترابایت') : $s;
    }
    $s = number_format(intval(round($gb)));
    return $with_unit ? ($s . ' گیگ') : $s;
}

function parsePanelAdminUsernames($raw)
{
    $out = [];
    foreach (preg_split('/\s*,\s*/', trim(strval($raw))) as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (!in_array($p, $out, true)) {
            $out[] = $p;
        }
        if (count($out) >= 3) {
            break;
        }
    }
    return $out;
}

function formatPanelAdminUsernames(array $list)
{
    $list = array_values(array_unique(array_filter(array_map('trim', $list))));
    return implode(',', array_slice($list, 0, 3));
}

/** همه نام‌های ادمین پنل کاربر (حداکثر ۳) */
function getUserPanelAdminUsernames($user_id)
{
    try {
        ensurePanelAdminSchema();
        $u = select("user", "panel_admin_username", "id", intval($user_id), "select");
        if (!$u) {
            return [];
        }
        $raw = is_array($u) ? strval($u['panel_admin_username'] ?? '') : strval($u);
        return parsePanelAdminUsernames($raw);
    } catch (Throwable $e) {
        return [];
    }
}

/** سازگاری: اولین ادمین */
function getUserPanelAdminUsername($user_id)
{
    $list = getUserPanelAdminUsernames($user_id);
    return $list[0] ?? '';
}

/** افزودن ادمین با بررسی پنل — حداکثر ۳ */
function addUserPanelAdminUsername($user_id, $username, $verify_panel = true)
{
    $user_id = intval($user_id);
    $username = trim(strval($username));
    if ($user_id <= 0 || $username === '') {
        return ['ok' => false, 'message' => 'نام کاربری نامعتبر است.'];
    }
    ensurePanelAdminSchema();
    if ($verify_panel) {
        if (!function_exists('getResellerPanel')) {
            $f = __DIR__ . '/reseller_volume.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!function_exists('getPanelAdmin')) {
            $f = __DIR__ . '/pasarguard.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!function_exists('getResellerPanel') || !function_exists('getPanelAdmin')) {
            return ['ok' => false, 'message' => 'ماژول پنل در دسترس نیست.'];
        }
        $panel = getResellerPanel();
        if (!$panel) {
            return ['ok' => false, 'message' => 'اول در Reseller Panel اطلاعات پنل را تنظیم کنید.'];
        }
        $adm = getPanelAdmin($username, $panel);
        if (!is_array($adm) || !empty($adm['error'])) {
            $detail = is_array($adm) ? strval($adm['detail'] ?? $adm['message'] ?? '') : '';
            return ['ok' => false, 'message' => 'این نام کاربری در پنل یافت نشد.' . ($detail ? " ($detail)" : '')];
        }
    }
    $list = getUserPanelAdminUsernames($user_id);
    if (in_array($username, $list, true)) {
        return ['ok' => true, 'message' => 'قبلاً ثبت شده بود.', 'list' => $list];
    }
    if (count($list) >= 3) {
        return ['ok' => false, 'message' => 'حداکثر ۳ ادمین پنل برای هر کاربر مجاز است. ابتدا یکی را حذف کنید.'];
    }
    $list[] = $username;
    update("user", "panel_admin_username", formatPanelAdminUsernames($list), "id", $user_id);
    update("user", "panel_admin_enabled", "1", "id", $user_id);
    return ['ok' => true, 'message' => 'ثبت شد.', 'list' => $list];
}

function removeUserPanelAdminUsername($user_id, $username)
{
    $user_id = intval($user_id);
    $username = trim(strval($username));
    $list = getUserPanelAdminUsernames($user_id);
    $list = array_values(array_filter($list, function ($x) use ($username) {
        return $x !== $username;
    }));
    update("user", "panel_admin_username", formatPanelAdminUsernames($list), "id", $user_id);
    if (!$list) {
        // اگر هیچ ادمینی نماند، پنل‌دار را خاموش نکن — ادمین خودش تصمیم می‌گیرد
    }
    return $list;
}

function listResellerPanelUsers($limit = 40)
{
    global $pdo;
    ensurePanelAdminSchema();
    try {
        $st = $pdo->query("SELECT id, username, panel_admin_username, Balance FROM user WHERE panel_admin_enabled = 1 ORDER BY id DESC LIMIT " . intval($limit));
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function getResellerVolumePurchasedGb($user_id)
{
    global $pdo;
    $user_id = intval($user_id);
    if ($user_id <= 0) return 0;
    try {
        ensurePanelAdminSchema();
        $st = $pdo->prepare("SELECT COALESCE(SUM(volume_gb),0) FROM reseller_volume_order WHERE id_user = ? AND status = 'paid'");
        $st->execute([$user_id]);
        return intval($st->fetchColumn());
    } catch (Throwable $e) {
        return 0;
    }
}

function resetResellerWarnState($admin_username, $current_hit = null, $data_limit = null)
{
    /**
     * بعد از خرید حجم (ربات) یا تشخیص افزایش حجم در پنل:
     * last_level = آستانه‌های فعلی تا اسپم همان درصد نشود؛
     * با رسیدن دوباره به آستانه بالاتر یا بعد از کاهش درصد، پیام می‌رود.
     */
    global $pdo;
    $admin_username = trim(strval($admin_username));
    if ($admin_username === '') {
        return;
    }
    $lvl = ($current_hit !== null) ? max(0, intval($current_hit)) : 0;
    $lim = ($data_limit !== null) ? max(0, intval($data_limit)) : 0;
    try {
        ensureResellerWarnStateTable();
        $pdo->prepare(
            "INSERT INTO reseller_admin_warn_state (admin_username, last_level, last_data_limit, updated_at)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                last_level = VALUES(last_level),
                last_data_limit = IF(VALUES(last_data_limit) > 0, VALUES(last_data_limit), last_data_limit),
                updated_at = VALUES(updated_at)"
        )->execute([$admin_username, $lvl, $lim, time()]);
    } catch (Throwable $e) {
        error_log('resetResellerWarnState: ' . $e->getMessage());
    }
}

/** محاسبه بالاترین آستانه هشدار ریسلر برای درصد فعلی */
function resellerWarnHitForPercent($pct)
{
    $pct = intval($pct);
    $levels = [];
    if (function_exists('getPaySettingValue')) {
        $raw = getPaySettingValue('smart_reseller_vol_levels', '80,90,95');
        foreach (explode(',', strval($raw)) as $p) {
            $p = intval(trim($p));
            if ($p >= 1 && $p <= 100) {
                $levels[] = $p;
            }
        }
    }
    if (!$levels) {
        $levels = [80, 90, 95];
    }
    $levels = array_values(array_unique($levels));
    sort($levels);
    $hit = 0;
    foreach ($levels as $lv) {
        if ($pct >= $lv) {
            $hit = $lv;
        }
    }
    return $hit;
}

function ensureResellerWarnStateTable()
{
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_admin_warn_state (
            admin_username VARCHAR(191) NOT NULL PRIMARY KEY,
            last_level INT NOT NULL DEFAULT 0,
            last_data_limit BIGINT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // مهاجرت نصب‌های قبلی بدون ستون last_data_limit
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM reseller_admin_warn_state LIKE 'last_data_limit'");
            if ($chk && !$chk->fetch()) {
                $pdo->exec("ALTER TABLE reseller_admin_warn_state ADD COLUMN last_data_limit BIGINT NOT NULL DEFAULT 0 AFTER last_level");
            }
        } catch (Throwable $e2) {}
    } catch (Throwable $e) {}
}

function processResellerVolumeWarnings()
{
    global $pdo, $textbotlang;
    try {
        if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
            return;
        }
        if (!function_exists('smartCronFlag') || !smartCronFlag('smart_reseller_warn', '0')) {
            return;
        }
        if (!function_exists('getResellerPanel')) {
            $f = __DIR__ . '/reseller_volume.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!function_exists('getPanelAdmin')) {
            $f = __DIR__ . '/pasarguard.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!function_exists('getResellerPanel') || !function_exists('getPanelAdmin')) {
            return;
        }
        $panel = getResellerPanel();
        if (!$panel) {
            return;
        }
        ensureResellerWarnStateTable();
        ensureSmartCronSettings();

        $levels = [];
        $raw = getPaySettingValue('smart_reseller_vol_levels', '80,90,95');
        foreach (explode(',', strval($raw)) as $p) {
            $p = intval(trim($p));
            if ($p >= 1 && $p <= 100) {
                $levels[] = $p;
            }
        }
        $levels = array_values(array_unique($levels));
        sort($levels);
        if (!$levels) {
            return;
        }

        $st = $pdo->query("SELECT id, panel_admin_username FROM user WHERE panel_admin_enabled = 1 AND panel_admin_username IS NOT NULL AND TRIM(panel_admin_username) != ''");
        $users = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        $btn = $textbotlang['users']['reseller_volume']['btn_buy'] ?? 'خرید حجم برای پنل';
        $sent = 0;

        foreach ($users as $urow) {
            $uid = intval($urow['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $pa_list = function_exists('parsePanelAdminUsernames')
                ? parsePanelAdminUsernames($urow['panel_admin_username'] ?? '')
                : [trim(strval($urow['panel_admin_username'] ?? ''))];
            foreach ($pa_list as $pa) {
                if ($pa === '') {
                    continue;
                }
                $admin = getPanelAdmin($pa, $panel);
                if (!is_array($admin) || !empty($admin['error'])) {
                    continue;
                }
                $used = intval($admin['used_traffic'] ?? 0);
                $limit = intval($admin['data_limit'] ?? 0);
                if ($limit <= 0) {
                    continue;
                }
                $pct = intval(floor(($used / $limit) * 100));
                if ($pct > 100) {
                    $pct = 100;
                }

                // بالاترین آستانه فعلی + کوچک‌ترین آستانهٔ جدید برای پیام مرحله‌ای
                $hit = 0;
                foreach ($levels as $lv) {
                    if ($pct >= $lv) {
                        $hit = $lv;
                    }
                }

                $st2 = $pdo->prepare("SELECT last_level, last_data_limit FROM reseller_admin_warn_state WHERE admin_username = ? LIMIT 1");
                $st2->execute([$pa]);
                $row_st = $st2->fetch(PDO::FETCH_ASSOC);
                $prev = intval($row_st['last_level'] ?? 0);
                $prev_limit = intval($row_st['last_data_limit'] ?? 0);

                // افزایش حجم (دستی از پنل یا از ربات) → سقف هشدار را با درصد جدید هم‌تراز کن
                // تا با رسیدن دوباره به هر آستانه، اطلاع‌رسانی شود
                if ($prev_limit > 0 && $limit > intval($prev_limit * 1.001)) {
                    $prev = $hit; // آستانه‌های فعلی دیگر اسپم نشوند
                    try {
                        $pdo->prepare(
                            "INSERT INTO reseller_admin_warn_state (admin_username, last_level, last_data_limit, updated_at)
                             VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE last_level = VALUES(last_level), last_data_limit = VALUES(last_data_limit), updated_at = VALUES(updated_at)"
                        )->execute([$pa, $prev, $limit, time()]);
                    } catch (Throwable $e) {}
                    // اگر همین الان هنوز بالای آستانه بعدی نیست، پیام نده
                    // (فقط وقتی بعداً به آستانه بالاتر برسد)
                } elseif ($prev_limit <= 0) {
                    // اولین بار: فقط limit را ذخیره کن
                    try {
                        $pdo->prepare(
                            "INSERT INTO reseller_admin_warn_state (admin_username, last_level, last_data_limit, updated_at)
                             VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE last_data_limit = VALUES(last_data_limit), updated_at = VALUES(updated_at)"
                        )->execute([$pa, $prev, $limit, time()]);
                    } catch (Throwable $e) {}
                } else {
                    // limit ثابت یا کمتر: last_data_limit را به‌روز نگه دار
                    try {
                        $pdo->prepare("UPDATE reseller_admin_warn_state SET last_data_limit = ?, updated_at = ? WHERE admin_username = ?")
                            ->execute([$limit, time(), $pa]);
                    } catch (Throwable $e) {}
                }

                // کاهش درصد (مثلاً بعد از افزایش حجم که در اجرای قبل دیده شد)
                if ($hit < $prev) {
                    try {
                        $pdo->prepare(
                            "INSERT INTO reseller_admin_warn_state (admin_username, last_level, last_data_limit, updated_at)
                             VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE last_level = VALUES(last_level), last_data_limit = VALUES(last_data_limit), updated_at = VALUES(updated_at)"
                        )->execute([$pa, $hit, $limit, time()]);
                    } catch (Throwable $e) {}
                    continue;
                }

                // کوچک‌ترین آستانه‌ای که از prev بالاتر است و الان رد شده
                $notify_level = 0;
                foreach ($levels as $lv) {
                    if ($lv > $prev && $pct >= $lv) {
                        $notify_level = $lv;
                        break;
                    }
                }
                if ($notify_level <= 0) {
                    continue;
                }

                $used_gb = round($used / (1024 ** 3), 2);
                $limit_gb = round($limit / (1024 ** 3), 2);
                $remain_gb = max(0, round(($limit - $used) / (1024 ** 3), 2));
                $u_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($used_gb) : ($used_gb . ' گیگ');
                $l_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($limit_gb) : ($limit_gb . ' گیگ');
                $r_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($remain_gb) : ($remain_gb . ' گیگ');
                $msg = "⚠️ <b>هشدار مصرف حجم پنل</b>\n\n"
                    . "👤 ادمین پنل: <code>" . htmlspecialchars($pa, ENT_QUOTES, 'UTF-8') . "</code>\n"
                    . "📊 مصرف: <b>{$pct}٪</b> (آستانه {$notify_level}٪)\n"
                    . "📉 استفاده‌شده: <b>{$u_lbl}</b> از <b>{$l_lbl}</b>\n"
                    . "➕ باقی‌مانده: <b>{$r_lbl}</b>\n\n"
                    . "در صورت نیاز از دکمه زیر حجم بخرید.";
                $kb = json_encode([
                    'inline_keyboard' => [
                        [['text' => $btn, 'callback_data' => 'rv_open_menu']],
                    ],
                ], JSON_UNESCAPED_UNICODE);
                sendmessage($uid, $msg, $kb, 'HTML');

                try {
                    $pdo->prepare(
                        "INSERT INTO reseller_admin_warn_state (admin_username, last_level, last_data_limit, updated_at)
                         VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE last_level = VALUES(last_level), last_data_limit = VALUES(last_data_limit), updated_at = VALUES(updated_at)"
                    )->execute([$pa, $notify_level, $limit, time()]);
                } catch (Throwable $e) {
                    error_log('reseller warn state: ' . $e->getMessage());
                }

                if (function_exists('sendChannelReport')) {
                    sendChannelReport(
                        'rpt_reseller_warn',
                        "⚠️ <b>هشدار حجم پنل ریسلر</b>\n\n"
                        . "👤 کاربر ربات: <code>{$uid}</code>\n"
                        . "🛠 ادمین پنل: <code>" . htmlspecialchars($pa, ENT_QUOTES, 'UTF-8') . "</code>\n"
                        . "📊 مصرف: <b>{$pct}٪</b> (آستانه {$notify_level}٪)\n"
                        . "📉 {$used_gb} / {$limit_gb} گیگ — باقی: {$remain_gb} گیگ"
                    );
                }
                $sent++;
            }
        }
    } catch (Throwable $e) {
        error_log('processResellerVolumeWarnings: ' . $e->getMessage());
    }
}


