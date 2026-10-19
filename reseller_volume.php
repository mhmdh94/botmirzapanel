<?php
/**
 * خرید حجم پنل (Reseller) — کاملاً مجزا از فروش اکانت و حجم سرویس
 */

if (!function_exists('ensurePanelAdminSchema')) {
    // functions.php باید قبل از این لود شده باشد
}

/**
 * کیبورد اصلی مدیریت پلن ریسلر: لیست پلن‌ها (مرتب بر اساس حجم) + افزودن
 */

/**
 * پنل اختصاصی ریسلر (فقط برای شارژ حجم ادمین — جدا از فروش اکانت)
 */

/**
 * پنل ریسلر — جدول جدا، بدون وابستگی به marzban_panel
 */
function ensureResellerPanelTable()
{
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reseller_panel_cfg (
            id INT PRIMARY KEY DEFAULT 1,
            url VARCHAR(500) NOT NULL DEFAULT '',
            username VARCHAR(191) NOT NULL DEFAULT '',
            password VARCHAR(500) NOT NULL DEFAULT '',
            token TEXT NULL,
            token_time INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $st = $pdo->query("SELECT id FROM reseller_panel_cfg WHERE id = 1");
        if (!$st || !$st->fetch()) {
            $pdo->exec("INSERT INTO reseller_panel_cfg (id, url, username, password, token_time, updated_at) VALUES (1, '', '', '', 0, 0)");
        }
    } catch (Throwable $e) {
        error_log('ensureResellerPanelTable: ' . $e->getMessage());
    }
}

/** @deprecated — نگه داشته شده برای سازگاری؛ همیشه 1 */
function getResellerPanelId()
{
    return 1;
}

function setResellerPanelId($panel_id)
{
    return true;
}

/**
 * @return array|null شکل سازگار با API: url_panel, username_panel, password_panel, is_reseller_cfg
 */
function getResellerPanel()
{
    global $pdo;
    ensureResellerPanelTable();
    try {
        $st = $pdo->query("SELECT * FROM reseller_panel_cfg WHERE id = 1 LIMIT 1");
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $e) {
        return null;
    }
    if (!$row || trim(strval($row['url'] ?? '')) === '' || trim(strval($row['username'] ?? '')) === '') {
        return null;
    }
    return [
        'id' => 1,
        'name_panel' => 'Reseller Panel',
        'url_panel' => rtrim(strval($row['url']), '/'),
        'username_panel' => strval($row['username']),
        'password_panel' => strval($row['password']),
        'token' => $row['token'] ?? null,
        'token_time' => intval($row['token_time'] ?? 0),
        'is_reseller_cfg' => true,
        'type' => 'pasarguard',
    ];
}

function listPanelsForReseller()
{
    // دیگر از marzban_panel استفاده نمی‌شود
    return [];
}

function findPanelAdminOnReseller($username)
{
    $panel = getResellerPanel();
    if (!$panel) {
        return [
            'ok' => false,
            'message' => 'هنوز اطلاعات پنل ریسلر تنظیم نشده است. از منوی Reseller Panel → تنظیم اطلاعات پنل، آدرس و یوزر و رمز را وارد کنید.',
        ];
    }
    if (!function_exists('getPanelAdmin')) {
        require_once __DIR__ . '/pasarguard.php';
    }
    $res = getPanelAdmin($username, $panel);
    if (is_array($res) && empty($res['error']) && !empty($res['username'])) {
        return ['ok' => true, 'admin' => $res, 'panel' => $panel];
    }
    $detail = is_array($res) ? strval($res['detail'] ?? '') : '';
    return [
        'ok' => false,
        'message' => 'ادمین «' . $username . '» روی پنل ریسلر پیدا نشد.' . ($detail ? " ($detail)" : ''),
    ];
}

/**
 * ذخیره اعتبارنامه پنل ریسلر — فقط جدول اختصاصی
 */
function saveResellerPanelCredentials($url, $username, $password)
{
    global $pdo;
    ensureResellerPanelTable();
    $url = rtrim(trim(strval($url)), '/');
    $username = trim(strval($username));
    $password = strval($password);
    if ($url === '' || $username === '' || $password === '') {
        return ['ok' => false, 'message' => 'اطلاعات ناقص است.'];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    try {
        $st = $pdo->prepare("UPDATE reseller_panel_cfg SET url = ?, username = ?, password = ?, token = NULL, token_time = 0, updated_at = ? WHERE id = 1");
        $st->execute([$url, $username, $password, time()]);
        return ['ok' => true, 'panel_id' => 1, 'url' => $url, 'username' => $username];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}



/**
 * منوی اصلی Reseller Panel — دو گزینه ثابت
 * فقط UI ادمین؛ هیچ تاثیری روی فروش اکانت / marzban_panel ندارد
 */
function resellerVolumeAdminKeyboard()
{
    global $textbotlang;
    $warn_on = (function_exists('smartCronFlag') && smartCronFlag('smart_reseller_warn', '0'));
    $levels = function_exists('getPaySettingValue') ? getPaySettingValue('smart_reseller_vol_levels', '80,90,95') : '80,90,95';
    $mark = $warn_on ? '✅' : '❌';
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['reseller_volume']['btn_plans'] ?? '📋 تنظیم پلن‌های پنل', 'callback_data' => 'rv_admin_plans'],
            ],
            [
                ['text' => $textbotlang['Admin']['reseller_volume']['btn_panel'] ?? '🖥 تنظیم اطلاعات پنل', 'callback_data' => 'rv_admin_panel'],
            ],
            [
                ['text' => '👥 کاربران پنل‌دار', 'callback_data' => 'rv_list_users'],
            ],
            [
                ['text' => "⏱ کرون هشدار حجم {$mark}", 'callback_data' => 'rv_cron_settings'],
            ],
            [
                ['text' => "⚙️ درصدها: {$levels}", 'callback_data' => 'rv_cron_set_levels'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * لیست پلن‌ها + افزودن (مرتب بر اساس حجم)
 */
function resellerVolumePlansKeyboard()
{
    global $textbotlang;
    $plans = resellerListPlans(false);
    usort($plans, function ($a, $b) {
        return intval($a['volume_gb'] ?? 0) <=> intval($b['volume_gb'] ?? 0);
    });
    $kb = ['inline_keyboard' => []];
    if (empty($plans)) {
        $kb['inline_keyboard'][] = [
            ['text' => $textbotlang['Admin']['reseller_volume']['empty_plans'] ?? 'هنوز پلنی تعریف نشده', 'callback_data' => 'rv_noop'],
        ];
    } else {
        $kb['inline_keyboard'][] = [
            ['text' => '💵 قیمت', 'callback_data' => 'rv_noop'],
            ['text' => '📊 حجم', 'callback_data' => 'rv_noop'],
        ];
        foreach ($plans as $pl) {
            $gb = intval($pl['volume_gb'] ?? 0);
            $pr = intval($pl['price'] ?? 0);
            $cb = 'rv_edit_' . intval($pl['id']);
            $kb['inline_keyboard'][] = [
                ['text' => number_format($pr) . ' تومان', 'callback_data' => $cb],
                ['text' => (function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb) : (number_format($gb) . ' گیگ')), 'callback_data' => $cb],
            ];
        }
    }
    $kb['inline_keyboard'][] = [
        ['text' => $textbotlang['Admin']['reseller_volume']['add_plan'] ?? '➕ افزودن پلن جدید', 'callback_data' => 'rv_admin_add'],
    ];
    $kb['inline_keyboard'][] = [
        ['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back'],
    ];
    return json_encode($kb);
}

function resellerListPlans($only_active = false)
{
    global $pdo;
    if (function_exists('ensurePanelAdminSchema')) {
        ensurePanelAdminSchema();
    }
    if (function_exists('ensureResellerPlanTables')) {
        ensureResellerPlanTables();
    }
    try {
        $sql = "SELECT * FROM reseller_volume_plan";
        if ($only_active) {
            $sql .= " WHERE (status = 1 OR status = '1')";
        }
        $sql .= " ORDER BY sort_order ASC, volume_gb ASC";
        $st = $pdo->query($sql);
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function resellerGetPlan($id)
{
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT * FROM reseller_volume_plan WHERE id = ? LIMIT 1");
        $st->execute([intval($id)]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * خرید حجم برای ادمین پنل کاربر — کسر کیف پول + API
 */

/** کیبورد هاب کاربر ریسلر */
function buildResellerUserHubKeyboard()
{
    global $textbotlang;
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['reseller_volume']['btn_buy'] ?? '🛒 خرید حجم', 'callback_data' => 'rv_user_buy'],
                ['text' => $textbotlang['users']['reseller_volume']['btn_my_panel'] ?? '🖥 پنل من', 'callback_data' => 'rv_user_my_panel'],
            ],
            [
                ['text' => '🔐 تغییر رمز', 'callback_data' => 'rv_user_pass'],
            ],
            [['text' => $textbotlang['users']['backhome'] ?? '🏠 منوی اصلی', 'callback_data' => 'backuser']],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/** متن وضعیت پنل برای یک یا چند ادمین کاربر */
function buildResellerMyPanelStatusText($user_id)
{
    global $textbotlang;
    if (!function_exists('getResellerPanel')) {
        return '❌ پنل پیکربندی نشده است.';
    }
    $panel = getResellerPanel();
    if (!$panel) {
        return '❌ پنل ریسلر تنظیم نشده است. با پشتیبانی تماس بگیرید.';
    }
    if (!function_exists('getPanelAdmin')) {
        $f = __DIR__ . '/pasarguard.php';
        if (is_file($f)) {
            require_once $f;
        }
    }
    $list = function_exists('getUserPanelAdminUsernames') ? getUserPanelAdminUsernames($user_id) : [];
    if (!$list) {
        return "❌ ادمین پنلی برای شما ثبت نشده است.";
    }
    $out = [];
    foreach ($list as $pa) {
        $block = "👤 ادمین پنل:\n<code>" . htmlspecialchars($pa, ENT_QUOTES, 'UTF-8') . "</code>\n";
        if (!function_exists('getPanelAdmin')) {
            $block .= "\n❌ ارتباط با پنل ممکن نیست.";
            $out[] = $block;
            continue;
        }
        $adm = getPanelAdmin($pa, $panel);
        if (!is_array($adm) || !empty($adm['error'])) {
            $block .= "\n❌ خواندن اطلاعات از پنل ممکن نشد.";
            $out[] = $block;
            continue;
        }
        $status = strval($adm['status'] ?? $adm['is_active'] ?? 'active');
        if ($status === '1' || $status === 'true' || $status === true) {
            $status = 'active';
        }
        $used = floatval($adm['used_traffic'] ?? 0);
        $limit = floatval($adm['data_limit'] ?? 0);
        $used_gb = $used > 0 ? round($used / (1024 ** 3), 2) : 0;
        if ($limit <= 0) {
            $block .= "📌 وضعیت: <b>" . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "</b>\n";
            $block .= "📊 حجم کل: <b>نامحدود</b>\n";
            $block .= "📉 مصرف: <b>" . number_format($used_gb, 2) . "</b> گیگ\n";
        } else {
            $limit_gb = round($limit / (1024 ** 3), 2);
            $remain_gb = max(0, round(($limit - $used) / (1024 ** 3), 2));
            $pct = $limit > 0 ? round(($used / $limit) * 100, 1) : 0;
            $limit_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($limit_gb) : (number_format($limit_gb, 1) . ' گیگ');
            $used_lbl = number_format($used_gb, 2) . ' گیگ';
            $remain_lbl = number_format($remain_gb, 2) . ' گیگ';
            # برای اعداد زیر 1024 گیگ همان گیگ بماند؛ formatReseller هم همین کار را می‌کند
            if (function_exists('formatResellerVolumeLabel')) {
                $limit_lbl = formatResellerVolumeLabel($limit_gb);
                # مصرف و مانده معمولاً گیگ دقیق‌تر است
                $used_lbl = ($used_gb >= 1024) ? formatResellerVolumeLabel($used_gb) : (number_format($used_gb, 2) . ' گیگ');
                $remain_lbl = ($remain_gb >= 1024) ? formatResellerVolumeLabel($remain_gb) : (number_format($remain_gb, 2) . ' گیگ');
            }
            $block .= "📌 وضعیت: <b>" . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "</b>\n";
            $block .= "📊 حجم کل: <b>{$limit_lbl}</b>\n";
            $block .= "📉 مصرف: <b>{$used_lbl}</b> ({$pct}%)\n";
            $block .= "✅ مانده: <b>{$remain_lbl}</b>";
        }
        $out[] = $block;
    }
    return implode("\n\n──────────────\n\n", $out);
}


function resellerPurchaseVolume($user_id, $plan_id, $admin_username = null)
{
    global $pdo, $textbotlang;
    $user_id = intval($user_id);
    $plan_id = intval($plan_id);
    if (function_exists('ensurePanelAdminSchema')) {
        ensurePanelAdminSchema();
    }
    // قفل ضد دابل‌کلیک / webhook تکراری
    $lock_path = sys_get_temp_dir() . '/mirza_rv_buy_' . $user_id . '.lock';
    $lock_fp = @fopen($lock_path, 'c+');
    $lock_held = false;
    if ($lock_fp) {
        if (!@flock($lock_fp, LOCK_EX | LOCK_NB)) {
            @fclose($lock_fp);
            return ['ok' => false, 'message' => '⏳ درخواست قبلی در حال پردازش است؛ چند لحظه صبر کنید.'];
        }
        $lock_held = true;
    }
    try {
    if (!function_exists('isUserPanelAdmin') || !isUserPanelAdmin($user_id)) {
        return ['ok' => false, 'message' => $textbotlang['users']['reseller_volume']['no_access'] ?? 'دسترسی ندارید'];
    }
    $list = function_exists('getUserPanelAdminUsernames') ? getUserPanelAdminUsernames($user_id) : [];
    $pa_user = $admin_username !== null && $admin_username !== '' ? trim(strval($admin_username)) : ($list[0] ?? '');
    if ($pa_user === '' || ($list && !in_array($pa_user, $list, true) && $admin_username !== null)) {
        // اگر لیست خالی یا نام نامعتبر
        if ($pa_user === '') {
            return ['ok' => false, 'message' => $textbotlang['users']['reseller_volume']['no_username'] ?? 'نام ادمین ثبت نشده'];
        }
        if ($list && !in_array($pa_user, $list, true)) {
            return ['ok' => false, 'message' => 'این ادمین برای شما ثبت نشده است.'];
        }
    }
    if ($pa_user === '') {
        return ['ok' => false, 'message' => $textbotlang['users']['reseller_volume']['no_username'] ?? 'نام ادمین ثبت نشده'];
    }
    $plan = resellerGetPlan($plan_id);
    if (!$plan || (intval($plan['status'] ?? $plan['is_active'] ?? 0) !== 1)) {
        return ['ok' => false, 'message' => 'پلن یافت نشد یا غیرفعال است.'];
    }
    $price = intval($plan['price']);
    $volume_gb = intval($plan['volume_gb']);
    if ($price <= 0 || $volume_gb <= 0) {
        return ['ok' => false, 'message' => 'پلن نامعتبر است.'];
    }

    $u = select("user", "*", "id", $user_id, "select");
    $balance = intval($u['Balance'] ?? 0);
    if ($balance < $price) {
        return [
            'ok' => false,
            'need_payment' => true,
            'plan_id' => intval($plan['id']),
            'price' => $price,
            'balance' => $balance,
            'shortfall' => max(1, $price - $balance),
            'message' => sprintf(
                $textbotlang['users']['reseller_volume']['low_balance'] ?? 'موجودی کافی نیست.\nمبلغ پلن: %s تومان\nموجودی: %s تومان\nقابل پرداخت: %s تومان',
                number_format($price),
                number_format($balance),
                number_format($price - $balance)
            ),
        ];
    }

    // فقط پنل اختصاصی ریسلر
    $panel = getResellerPanel();
    if (!$panel || !is_array($panel)) {
        return ['ok' => false, 'message' => 'پنل ریسلر تنظیم نشده است. با پشتیبانی تماس بگیرید.'];
    }

    if (!function_exists('addVolumeToPanelAdmin')) {
        require_once __DIR__ . '/pasarguard.php';
    }

    // کسر موجودی (اتمی)
    $balance_before = $balance;
    if (function_exists('atomicDeductBalance')) {
        $deducted = atomicDeductBalance($user_id, $price);
        if ($deducted === false) {
            return ['ok' => false, 'message' => sprintf(
                $textbotlang['users']['reseller_volume']['low_balance'] ?? 'موجودی کافی نیست. قیمت: %s موجودی: %s',
                number_format($price),
                number_format($balance_before)
            )];
        }
        $balance_after = intval($deducted);
    } else {
        update("user", "Balance", $balance_before - $price, "id", $user_id);
        $balance_after = $balance_before - $price;
    }

    $api = addVolumeToPanelAdmin($pa_user, $volume_gb, $panel);
    if (empty($api['success'])) {
        // برگشت پول
        if (function_exists('atomicAddBalance')) {
            atomicAddBalance($user_id, $price);
        } else {
            $cur = select("user", "Balance", "id", $user_id, "select");
            $c = is_array($cur) ? intval($cur['Balance'] ?? 0) : intval($cur);
            update("user", "Balance", $c + $price, "id", $user_id);
        }
        $detail = $api['message'] ?? 'خطای پنل';
        return [
            'ok' => false,
            'message' => sprintf(
                $textbotlang['users']['reseller_volume']['fail_refund'] ?? "ناموفق. %s تومان برگشت. %s",
                number_format($price),
                $detail
            ),
        ];
    }

    // ثبت سفارش
    try {
        $st = $pdo->prepare("INSERT INTO reseller_volume_order (id_user, plan_id, volume_gb, price, panel_admin_username, status, created_at) VALUES (?,?,?,?,?,?,?)");
        $st->execute([
            $user_id,
            intval($plan['id']),
            $volume_gb,
            $price,
            $pa_user,
            'paid',
            time(),
        ]);
    } catch (Throwable $e) {
        error_log('reseller order log: ' . $e->getMessage());
    }

    // ریست سطح هشدار بر اساس درصد جدید (حجم به قبلی اضافه می‌شود؛ مصرف ثابت → درصد پایین می‌آید)
    if (function_exists('resetResellerWarnState')) {
        $__hit = 0;
        $__lim = 0;
        try {
            $__adm = function_exists('getPanelAdmin') ? getPanelAdmin($pa_user, $panel) : null;
            if (is_array($__adm) && empty($__adm['error'])) {
                $__used = floatval($__adm['used_traffic'] ?? 0);
                $__lim = floatval($__adm['data_limit'] ?? 0);
                if ($__lim > 0) {
                    $__pct = intval(floor(($__used / $__lim) * 100));
                    if ($__pct > 100) {
                        $__pct = 100;
                    }
                    $__hit = function_exists('resellerWarnHitForPercent')
                        ? resellerWarnHitForPercent($__pct)
                        : 0;
                }
            }
        } catch (Throwable $e) {
            $__hit = 0;
            $__lim = 0;
        }
        // حجم از پنل/ربات اضافه شده — سطح هشدار با درصد جدید هم‌تراز می‌شود
        resetResellerWarnState($pa_user, $__hit, $__lim > 0 ? intval($__lim) : null);
    }
    if (function_exists('logWalletTx')) {
        logWalletTx($user_id, 'reseller_volume', $price, $balance_after, 'خرید حجم پنل: ' . $pa_user . ' +' . $volume_gb . 'G');
    }
    if (function_exists('recordSale')) {
        recordSale($user_id, $price, 'reseller_volume', $pa_user, null);
    }

    $u4 = select("user", "Balance", "id", $user_id, "select");
    $bal_final = is_array($u4) ? intval($u4['Balance'] ?? 0) : intval($u4);

    if (function_exists('sendChannelReport')) {
        $vol_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($volume_gb) : (number_format($volume_gb) . ' گیگ');
        sendChannelReport(
            'rpt_reseller_volume',
            "📦 <b>خرید حجم پنل نمایندگی</b>

"
            . "👤 کاربر: <code>{$user_id}</code>
"
            . "🛠 ادمین پنل: <code>" . htmlspecialchars($pa_user, ENT_QUOTES, 'UTF-8') . "</code>
"
            . "📊 حجم اضافه‌شده: <b>{$vol_lbl}</b>
"
            . "💰 مبلغ: <b>" . number_format($price) . "</b> تومان
"
            . "💳 موجودی مانده: <b>" . number_format($bal_final) . "</b> تومان"
        );
    }

    return [
        'ok' => true,
        'message' => sprintf(
            $textbotlang['users']['reseller_volume']['success'] ?? "✅ حجم اضافه شد\nادمین: %s\nحجم: %s\nمبلغ: %s\nموجودی: %s",
            htmlspecialchars($pa_user, ENT_QUOTES, 'UTF-8'),
            (function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($volume_gb) : number_format($volume_gb)),
            number_format($price),
            number_format($bal_final)
        ),
        'api' => $api,
    ];
    } finally {
        if (!empty($lock_held) && $lock_fp) {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink($lock_path);
        }
    }

}


/* ===================== رمز عبور پنل ===================== */

function validateResellerPanelPassword($password)
{
    $password = strval($password);
    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => '❌ رمز باید حداقل ۸ کاراکتر باشد.'];
    }
    if (strlen($password) > 64) {
        return ['ok' => false, 'message' => '❌ رمز نباید بیشتر از ۶۴ کاراکتر باشد.'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['ok' => false, 'message' => '❌ رمز باید حداقل یک حرف کوچک انگلیسی داشته باشد.'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['ok' => false, 'message' => '❌ رمز باید حداقل یک حرف بزرگ انگلیسی داشته باشد.'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['ok' => false, 'message' => '❌ رمز باید حداقل یک عدد داشته باشد.'];
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return ['ok' => false, 'message' => '❌ رمز باید حداقل یک کاراکتر خاص داشته باشد (مثل !@#$%).'];
    }
    return ['ok' => true, 'message' => 'ok'];
}

function generateResellerPanelPassword($length = 12)
{
    $length = max(10, min(24, intval($length)));
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $special = '!@#$%&*';
    $all = $lower . $upper . $digits . $special;
    $pass = [
        $lower[random_int(0, strlen($lower) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];
    for ($i = count($pass); $i < $length; $i++) {
        $pass[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($pass) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $pass[$i];
        $pass[$i] = $pass[$j];
        $pass[$j] = $tmp;
    }
    return implode('', $pass);
}

function resellerPasswordRulesText()
{
    return "🔐 <b>شرایط رمز عبور جدید</b>\n\n"
        . "• حداقل <b>۸</b> کاراکتر\n"
        . "• حداقل یک حرف <b>کوچک</b> انگلیسی (a-z)\n"
        . "• حداقل یک حرف <b>بزرگ</b> انگلیسی (A-Z)\n"
        . "• حداقل یک <b>عدد</b> (0-9)\n"
        . "• حداقل یک کاراکتر <b>خاص</b> مثل ! @ # $ %\n\n"
        . "رمز را ارسال کنید یا از دکمه تولید خودکار استفاده کنید.";
}

/**
 * انتخاب ادمین وقتی کاربر چند پنل دارد
 * $action = pass | usage
 */
function buildResellerAdminPickKeyboard($user_id, $action = 'pass')
{
    $list = function_exists('getUserPanelAdminUsernames') ? getUserPanelAdminUsernames($user_id) : [];
    $kb = ['inline_keyboard' => []];
    $prefix = 'rv_pass_sel_';
    foreach ($list as $i => $name) {
        $kb['inline_keyboard'][] = [[
            'text' => '👤 ' . $name,
            'callback_data' => $prefix . intval($i),
        ]];
    }
    $kb['inline_keyboard'][] = [['text' => '🔙 بازگشت', 'callback_data' => 'rv_open_menu']];
    return json_encode($kb, JSON_UNESCAPED_UNICODE);
}

function buildResellerPassOptionsKeyboard($admin_index)
{
    $admin_index = intval($admin_index);
    return json_encode([
        'inline_keyboard' => [
            [['text' => '🎲 تولید رمز تصادفی', 'callback_data' => 'rv_pass_rand_' . $admin_index]],
            [['text' => '🔙 انصراف', 'callback_data' => 'rv_open_menu']],
        ],
    ], JSON_UNESCAPED_UNICODE);
}
