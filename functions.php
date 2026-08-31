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
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND Status = 'unpaid' LIMIT 1");
        $stmt->bindParam(':username', $steppay[1], PDO::PARAM_STR);
        $stmt->execute();
        $get_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
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
        }
        $output_config_link = "";
        $config = "";
        $Shoppinginfo = null;
        if ($marzban_list_get['sublink'] == "onsublink") {
            $output_config_link = $dataoutput['subscription_url'];
        }
        $configqr = "";
        if ($marzban_list_get['configManual'] == "onconfig") {
            if (isset($dataoutput['configs']) and count($dataoutput['configs']) != 0) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= "\n" . $configs;
                    $configqr .= $configs;
                }
            } else {
                $config .= "";
                $configqr .= "";
            }
        }
        $Shoppinginfo = $Shoppinginfo ? json_encode($Shoppinginfo) : null;
        if ($marzban_list_get['type'] == "wgdashboard") {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservicewgbuy'], $dataoutput['username'], $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume']);
        }
        if ($marzban_list_get['type'] == "mikrotik") {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservice_mikrotik_buy'], $dataoutput['username'], $dataoutput['subscription_url'], $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume']);
        } else {
            $textcreatuser = sprintf($textbotlang['users']['buy']['createservice'], $dataoutput['username'], $get_invoice['name_product'], $marzban_list_get['name_panel'], $get_invoice['Service_time'], $get_invoice['Volume'], $config, $output_config_link);
        }
        if ($marzban_list_get['type'] == "mikrotik") {
            sendmessage($Balance_id['id'], $textcreatuser, $Shoppinginfo, 'HTML');
            sendmessage($Balance_id['id'], $textbotlang['users']['selectoption'], $keyboard, 'HTML');
        } else {
            if ($marzban_list_get['configManual'] == "onconfig") {
                if (count($dataoutput['configs']) == 1) {
                    $urlimage = "{$get_invoice['id_user']}$randomString.png";
                    $writer = new PngWriter();
                    $qrCode = QrCode::create($configqr)
                        ->setEncoding(new Encoding('UTF-8'))
                        ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
                        ->setSize(400)
                        ->setMargin(0)
                        ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);
                    $result = $writer->write($qrCode, null, null);
                    $result->saveToFile($urlimage);
                    telegram('sendphoto', [
                        'chat_id' => $get_invoice['id_user'],
                        'photo' => new CURLFile($urlimage),
                        'reply_markup' => $Shoppinginfo,
                        'caption' => $textcreatuser,
                        'parse_mode' => "HTML",
                    ]);
                    unlink($urlimage);
                } else {
                    sendmessage($get_invoice['id_user'], $textcreatuser, $Shoppinginfo, 'HTML');
                }
            } elseif ($marzban_list_get['sublink'] == "onsublink") {
                $urlimage = "{$get_invoice['id_user']}$randomString.png";
                $writer = new PngWriter();
                $qrCode = QrCode::create($output_config_link)
                    ->setEncoding(new Encoding('UTF-8'))
                    ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
                    ->setSize(400)
                    ->setMargin(0)
                    ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);
                $result = $writer->write($qrCode, null, null);
                $result->saveToFile($urlimage);
                telegram('sendphoto', [
                    'chat_id' => $get_invoice['id_user'],
                    'photo' => new CURLFile($urlimage),
                    'reply_markup' => $Shoppinginfo,
                    'caption' => $textcreatuser,
                    'parse_mode' => "HTML",
                ]);
                if ($marzban_list_get['type'] == "wgdashboard") {
                    $urldocs = "{$marzban_list_get['inboundid']}_{$get_invoice['id_invoice']}.conf";
                    file_put_contents($urldocs, $output_config_link);
                    sendDocument($get_invoice['id_user'], $urldocs, $textbotlang['users']['buy']['configwg']);
                    unlink($urlimage);
                }
                unlink($urlimage);
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
        if ($affiliatescommission['status_commission'] == "oncommission" && ($Balance_id['affiliates'] !== null || $Balance_id['affiliates'] != 0)) {
            if ($pricediscount == null) {
                $result = ($get_invoice['price_product'] * $affiliatescommission['affiliatespercentage']) / 100;
            } else {
                $result = ($pricediscount * $affiliatescommission['affiliatespercentage']) / 100;
            }
            $user_Balance = select("user", "*", "id", $Balance_id['affiliates'], "select");
            if (isset($user_Balance)) {
                $Balance_prim = $user_Balance['Balance'] + $result;
                update("user", "Balance", $Balance_prim, "id", $Balance_id['affiliates']);
                if (function_exists("addAffiliatesBalance")) {
                    addAffiliatesBalance($Balance_id['affiliates'], $result);
                }
                $result_fmt = number_format($result);
                $textadd = sprintf($textbotlang['users']['affiliates']['porsantuser'], $result_fmt);
                sendmessage($Balance_id['affiliates'], $textadd, null, 'HTML');
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
        update("invoice", "status", "active", "username", $get_invoice['username']);
        if ($Payment_report['Payment_Method'] == "cart to cart") {
            update("invoice", "Status", "active", "id_invoice", $get_invoice['id_invoice']);
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
        if ($message_id > 0) {
            Editmessagetext($from_id, $message_id, $built['text'], $kb_crypto);
        } else {
            sendmessage($from_id, $built['text'], $kb_crypto, 'HTML');
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
    foreach (getBalancePackages() as $p) {
        $pay = getBalancePackagePayAmount($p['amount'], $p['discount']);
        $disc = rtrim(rtrim(number_format($p['discount'], 1, '.', ''), '0'), '.');
        $label = $p['title'] !== '' ? $p['title'] . ' — ' : '';
        $label .= formatToman($p['amount']) . ' ت';
        if ($p['discount'] > 0) {
            $label .= " | 🎁 {$disc}٪ | پرداخت " . formatToman($pay);
        }
        $rows[] = [['text' => $label, 'callback_data' => 'balpkg_' . $p['id']]];
    }
    $rows[] = [['text' => $textbotlang['users']['Balance']['custom_amount_btn'] ?? '✍️ مبلغ دلخواه', 'callback_data' => 'balpkg_custom']];
    $rows[] = [['text' => $textbotlang['users']['backhome'] ?? '🏠', 'callback_data' => 'backuser']];
    return json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE);
}

function buildBalancePackageAdminKeyboard()
{
    global $textbotlang;
    $rows = [];
    foreach (getBalancePackages() as $p) {
        $disc = rtrim(rtrim(number_format($p['discount'], 1, '.', ''), '0'), '.');
        $rows[] = [[
            'text' => formatToman($p['amount']) . " ت | {$disc}٪ تخفیف",
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
        $dayListSell,
        number_format(intval($balanceall)),
        number_format(intval($subbuyuser)),
        $u['affiliatescount'] ?? '0',
        $u['affiliates'] ?? '0',
        $u['verify'] ?? '0',
        number_format(intval($aff_earn)),
        $cart_label
    );
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

/** حذف هر کرون croncard و در صورت فعال بودن، نصب با فاصله جدید */
function syncAutoCartCron($enabled)
{
    global $domainhosts;
    if (!(function_exists('shell_exec') && is_callable('shell_exec'))) {
        return false;
    }
    $existing = @shell_exec('crontab -l 2>/dev/null');
    if (!is_string($existing)) {
        $existing = '';
    }
    $lines = preg_split("/\r\n|\n|\r/", $existing);
    $kept = [];
    foreach ($lines as $line) {
        if ($line === '' || $line === false) {
            continue;
        }
        if (strpos($line, 'croncard.php') !== false) {
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
    $tmp = '/tmp/crontab_mirza_autocart.txt';
    @file_put_contents($tmp, $content);
    @shell_exec('crontab ' . escapeshellarg($tmp));
    @unlink($tmp);
    return true;
}

function isAutomaticCartConfirmEnabled()
{
    // منبع اصلی: PaySetting
    if (function_exists('getPaySettingValue')) {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting('auto_cart_confirm', '0');
        }
        if (getPaySettingValue('auto_cart_confirm', '0') === '1') {
            return true;
        }
        // سازگاری با نصب‌های قدیمی که فقط کرون دارند
        if (function_exists('shell_exec') && is_callable('shell_exec')) {
            $existing = @shell_exec('crontab -l 2>/dev/null');
            if (is_string($existing) && strpos($existing, 'croncard.php') !== false) {
                return true;
            }
        }
        return false;
    }
    if (function_exists('shell_exec') && is_callable('shell_exec')) {
        $existing = @shell_exec('crontab -l 2>/dev/null');
        return is_string($existing) && strpos($existing, 'croncard.php') !== false;
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
    if (!isReportChannelEnabled($key)) {
        return;
    }
    $setting = select("setting", "*", null, null, "select");
    if (!$setting || empty($setting['Channel_Report'])) {
        return;
    }
    sendmessage($setting['Channel_Report'], $text, null, 'HTML');
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
    if (!is_array($dataUser)) {
        return false;
    }
    $status = strval($dataUser['status'] ?? '');
    $msg = $dataUser['msg'] ?? ($dataUser['detail'] ?? '');
    if (is_array($msg) || is_object($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    }
    $msg = mb_strtolower(trim((string) $msg), 'UTF-8');
    if ($status === 'Unsuccessful') {
        if (
            strpos($msg, 'not found') !== false
            || strpos($msg, 'does not exist') !== false
            || strpos($msg, 'یافت نشد') !== false
            || strpos($msg, 'وجود ندارد') !== false
        ) {
            return true;
        }
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
        [['text' => '🔢 تعداد هر اجرا: ' . getPaySettingValue('smart_batch_limit', '20'), 'callback_data' => 'smartcron_set_limit']],
        [['text' => '⚙️ تنظیم درصد حجم: ' . getPaySettingValue('smart_vol_levels', '90,95,99'), 'callback_data' => 'smartcron_set_vol']],
        [['text' => '⚙️ تنظیم روز زمان: ' . getPaySettingValue('smart_time_days', '7,3,1'), 'callback_data' => 'smartcron_set_time']],
        [['text' => '📋 دستور کرون سیستم', 'callback_data' => 'smartcron_show_cmd']],
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


