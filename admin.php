<?php

#----------------[  admin section  ]------------------#
$textadmin = ["panel", "/panel", $textbotlang['Admin']['commendadminmanagment'], $textbotlang['Admin']['commendadmin'], "ادمین"]; // «ادمین» برای کیبوردهای قدیمی
if (!in_array($from_id, $admin_ids))
    return;

# ----- منوهای اینلاین بخش‌ها: کیبورد پایین عوض نشود -----
# keyboard.php قبل از functions.php لود می‌شود؛ اینجا بعد از لود توابع دوباره می‌سازیم
if (function_exists('buildAdminShopInlineKeyboard')) {
    $shopkeyboard = buildAdminShopInlineKeyboard();
}
if (function_exists('buildAdminUserServicesInlineKeyboard')) {
    $User_Services = buildAdminUserServicesInlineKeyboard();
}
if (function_exists('buildAdminBroadcastInlineKeyboard')) {
    $sendmessageuser = buildAdminBroadcastInlineKeyboard();
}

if (isset($datain) && is_string($datain) && $datain === 'nav_sec_close') {
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, '✅ بسته شد.', null);
    }
    return;
}

# هندل صریح دکمه‌های اینلاین بخش‌ها — همان پیام ویرایش شود + بازگشت
if (isset($datain) && is_string($datain) && (preg_match('/^(navshop_|navuser_|navcast_|navset_)/', $datain) || in_array($datain, ['nav_settings','nav_finance','nav_panel','nav_admin'], true))) {
    // هر کلیک ناوبری، step قبلی را خنثی می‌کند تا هندلر ورودی عددی/متنی گیر ندهد
    if (isset($user['step']) && is_string($user['step']) && $user['step'] !== '' && $user['step'] !== 'home') {
        step('home', $from_id);
        $user['step'] = 'home';
    }
    // ویرایش همان پیام اگر از اینلاین آمده؛ وگرنه پیام جدید
    $__nav_edit = !empty($message_id);
    $__nav_show = function ($t, $k) use ($from_id, $message_id, $__nav_edit) {
        if ($__nav_edit) {
            Editmessagetext($from_id, $message_id, $t, $k);
        } else {
            sendmessage($from_id, $t, $k, 'HTML');
        }
    };

    // ----- فروشگاه -----
    if ($datain === 'navshop_prod') {
        $__nav_show(
            function_exists('shopProductListTitle') ? shopProductListTitle() : '🏬 مدیریت محصولات',
            function_exists('shopProductListKeyboard') ? shopProductListKeyboard() : null
        );
        return;
    }
    if ($datain === 'navshop_cat') {
        if (function_exists('adminCategoriesListKeyboard')) {
            list($__tx, $__kb) = adminCategoriesListKeyboard();
            $__nav_show($__tx, $__kb);
        } else {
            sendmessage($from_id, $textbotlang['Admin']['category']['getname'] ?? 'نام دسته را بفرستید', $backadmin, 'HTML');
            step('getremarkcategory', $from_id);
        }
        return;
    }
    if ($datain === 'navshop_vol') {
        // نیاز به ورودی عددی → پیام جدا با کیبورد بازگشت
        sendmessage($from_id, "💰 <b>قیمت حجم اضافه</b>\n\nقیمت فعلی هر گیگ: <b>" . number_format(intval($setting['Extra_volume'] ?? 0)) . "</b> تومان\n\nقیمت جدید هر گیگ را به تومان بفرستید:", $backadmin, 'HTML');
        step('GetPriceExtra', $from_id);
        return;
    }
    if ($datain === 'navshop_codes') {
        if (function_exists('codesAdminHubText')) {
            $__nav_show(codesAdminHubText(), codesAdminHubKeyboard());
        } else {
            $__nav_show('🎟 مدیریت کد تخفیف و هدیه', null);
        }
        return;
    }
    if ($datain === 'navshop_test') {
        if (function_exists('adminUsertestPanel')) {
            list($__tx, $__kb) = adminUsertestPanel();
            $__nav_show($__tx, $__kb);
        } else {
            sendmessage($from_id, $textbotlang['users']['selectoption'] ?? 'انتخاب کنید', $keyboard_usertest, 'HTML');
        }
        return;
    }

    // ----- خدمات کاربر -----
    if ($datain === 'navuser_search') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ViewOrder'] ?? 'آیدی/یوزرنیم سفارش را بفرستید', $backadmin, 'HTML');
        step('getidfororder', $from_id);
        return;
    }
    if ($datain === 'navuser_del') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemoveService'] ?? 'یوزرنیم سرویس را بفرستید', $backadmin, 'HTML');
        step('removeservice', $from_id);
        return;
    }
    if ($datain === 'navuser_ball') {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['addallbalance'] ?? 'مبلغ شارژ همگانی را بفرستید', $backadmin, 'HTML');
        step('add_Balance_all', $from_id);
        return;
    }
    if ($datain === 'navuser_block') {
        // نسخه 2.4.2: لیست صفحهبندیشده + رفع مسدودی سریع
        list($__tx, $__kb) = botBlockedListPage(0);
        $__nav_show($__tx, $__kb);
        return;
    }
    if ($datain === 'navuser_rcpt') {
        // رسیدها چند پیام می‌فرستد؛ فقط وضعیت را روی همان صفحه نشان بده
        $res = function_exists('resendWaitingPaymentReceipts')
            ? resendWaitingPaymentReceipts($from_id, 40)
            : ['ok' => false, 'count' => 0, 'msg' => 'تابع موجود نیست'];
        $msg = $res['msg'] ?? '';
        if (intval($res['count'] ?? 0) === 0 && !empty($res['ok'])) {
            $msg = $msg !== '' ? $msg : 'هیچ رسید در انتظار تأییدی نیست.';
        }
        $kb_back = json_encode([
            'inline_keyboard' => [
                [['text' => '🔙 بازگشت به مدیریت کاربران', 'callback_data' => 'nav_user']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $__nav_show($msg, $kb_back);
        return;
    }
    if ($datain === 'navuser_rich') {
        global $pdo;
        $limit = 80;
        try {
            $stmt = $pdo->query("SELECT id, username, Balance FROM user WHERE CAST(Balance AS SIGNED) >= 500000 ORDER BY CAST(Balance AS SIGNED) DESC LIMIT {$limit}");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            $rows = [];
        }
        $kb_back = json_encode([
            'inline_keyboard' => [
                [['text' => '🔙 بازگشت به مدیریت کاربران', 'callback_data' => 'nav_user']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if (empty($rows)) {
            $__nav_show($textbotlang['Admin']['ManageUser']['balance_gt1_empty'] ?? 'هیچ کاربری با موجودی بالای ۵۰۰٬۰۰۰ تومان نیست.', $kb_back);
        } else {
            $total = count($rows);
            $sum_all = 0;
            try {
                $stc = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(CAST(Balance AS SIGNED)),0) AS s FROM user WHERE CAST(Balance AS SIGNED) >= 500000");
                $rc = $stc ? $stc->fetch(PDO::FETCH_ASSOC) : null;
                if ($rc) {
                    $total = intval($rc['c'] ?? $total);
                    $sum_all = intval($rc['s'] ?? 0);
                }
            } catch (Exception $e) {
                foreach ($rows as $r) {
                    $sum_all += intval($r['Balance'] ?? 0);
                }
            }
            $lines = [];
            foreach ($rows as $r) {
                $uid = $r['id'] ?? '-';
                $un = trim(strval($r['username'] ?? ''));
                if ($un === '') {
                    $un = '-';
                }
                $bal = number_format(intval($r['Balance'] ?? 0));
                $lines[] = "🆔 <code>{$uid}</code> | @{$un}\n💰 {$bal} تومان";
            }
            $msg = "💰 <b>موجودی بالای ۵۰۰ هزار</b>\nتعداد: <b>{$total}</b> | مجموع: <b>" . number_format($sum_all) . "</b>\n\n" . implode("\n\n", $lines);
            $__nav_show($msg, $kb_back);
        }
        return;
    }

    // ----- پیام همگانی (نیاز به ورودی متن/فوروارد → پیام جدا) -----
    if ($datain === 'navcast_text') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetText'] ?? 'متن پیام همگانی را بفرستید', $backadmin, 'HTML');
        step('getconfirmsendall', $from_id);
        return;
    }
    if ($datain === 'navcast_fwd') {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ForwardGetext'] ?? 'پیام را فوروارد کنید', $backadmin, 'HTML');
        step('gettextforwardMessage', $from_id);
        return;
    }

    // ----- تنظیمات / مالی / پنل / ادمین -----
    if ($datain === 'nav_settings') {
        $__nav_show(
            function_exists('buildAdminSettingsSectionText') ? buildAdminSettingsSectionText() : '⚙️ تنظیمات',
            function_exists('buildAdminSettingsInlineKeyboard') ? buildAdminSettingsInlineKeyboard() : null
        );
        return;
    }
    if ($datain === 'nav_finance') {
        $__tx = "💳 <b>تنظیمات مالی</b>\n\nاز این بخش درگاه‌ها، حداقل/حداکثر واریز، پکیج شارژ و تأیید خودکار رسید را مدیریت کنید.";
        $__kb = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : json_encode(['inline_keyboard' => []]);
        $__nav_show($__tx, $__kb);
        return;
    }
    if ($datain === 'nav_panel') {
        if (function_exists('adminPanelsListKeyboard')) {
            list($__tx, $__kb) = adminPanelsListKeyboard();
            $__nav_show($__tx, $__kb);
        } else {
            $__nav_show('🖥 مدیریت پنل', null);
        }
        return;
    }
    if ($datain === 'nav_admin') {
        if (function_exists('adminAdminsListKeyboard')) {
            list($__tx, $__kb) = adminAdminsListKeyboard($from_id);
            $__nav_show($__tx, $__kb);
        } else {
            $__nav_show('👮 بخش ادمین', null);
        }
        return;
    }
    if ($datain === 'navset_features') {
        // صفحه وضعیت قابلیت‌ها بزرگ است؛ با فلگ edit در همان پیام
        $GLOBALS['__admin_nav_edit_mid'] = $message_id;
        $text = $textbotlang['Admin']['keyboardadmin']['seetingstatus'];
        // بدون return — هندلر قدیمی seetingstatus اجرا می‌شود
    } elseif ($datain === 'navset_cron') {
        if (function_exists('ensureSmartCronSettings')) { ensureSmartCronSettings(); }
        if (function_exists('ensureSmartCronStateTable')) { ensureSmartCronStateTable(); }
        $__tx = botSmartCronMenuText();
        $__kb = function_exists('buildSmartCronAdminKeyboard') ? buildSmartCronAdminKeyboard() : null;
        $__nav_show($__tx, $__kb);
        return;
    } elseif ($datain === 'navset_chreport') {
        // نسخه 2.4.3
        list($__tx, $__kb) = botReportChannelPage();
        $__nav_show($__tx, $__kb);
        return;
    } elseif ($datain === 'navset_chjoin') {
        // نسخه 2.4.1: صفحه کانال عضویت اجباری
        list($__tx, $__kb) = botChannelJoinPage();
        $__nav_show($__tx, $__kb);
        return;
    } elseif ($datain === 'navset_aff') {
        if (function_exists('buildAffiliatesAdminPanel')) {
            list($__tx, $__kb) = buildAffiliatesAdminPanel();
            $__nav_show($__tx, $__kb);
        } else {
            $__nav_show('👥 <b>تنظیمات مشارکت در فروش</b>', null);
        }
        return;
    } elseif ($datain === 'navset_btxt') {
        if (function_exists('ensureEditableStatusTexts')) {
            @ensureEditableStatusTexts();
        }
        if (function_exists('buildBotTextSettingsPanel')) {
            $__bt_pack = buildBotTextSettingsPanel();
            $__tx = is_array($__bt_pack) ? ($__bt_pack[0] ?? '📝 تنظیم متن ربات') : '📝 تنظیم متن ربات';
            $__kb = is_array($__bt_pack) ? ($__bt_pack[1] ?? null) : null;
            if (!is_string($__kb) || $__kb === '') {
                $__kb = null;
            }
        } else {
            $__tx = '📝 تنظیم متن ربات';
            $__kb = null;
        }
        $__nav_show($__tx, $__kb);
        return;
    } elseif ($datain === 'navset_update') {
        // نسخه 2.4.3: فقط ادمین اصلی + نمایش نسخه فعلی و نسخه گیتهاب
        if (function_exists('isMainBotAdmin') && !isMainBotAdmin($from_id)) {
            $__nav_show("❌ آپدیت ربات فقط برای ادمین اصلی است.", json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به تنظیمات', 'callback_data' => 'nav_settings']]]], JSON_UNESCAPED_UNICODE));
            return;
        }
        $__rv = botRemoteVersion();
        $__cv = strval($version ?? '-');
        if ($__rv === '') {
            $__vs = "⚠️ دریافت نسخه گیتهاب ممکن نشد (اتصال سرور به گیتهاب).";
        } elseif (version_compare($__rv, $__cv, '<=')) {
            $__vs = "✅ ربات بهروز است.";
        } else {
            $__vs = "🆕 نسخه جدید موجود است.";
        }
        $kb = json_encode(['inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['bot_update']['yes'] ?? '✅ بله', 'callback_data' => 'bot_update_yes'],
                ['text' => $textbotlang['Admin']['bot_update']['no'] ?? '❌ خیر', 'callback_data' => 'nav_settings'],
            ],
            [['text' => '🔙 بازگشت به تنظیمات', 'callback_data' => 'nav_settings']],
        ]], JSON_UNESCAPED_UNICODE);
        $__nav_show("🔄 <b>آپدیت ربات</b>\n\n🌐 منبع: <code>github.com/mhmdh94/botmirzapanel</code> (شاخه main)\n📦 نسخه فعلی: <b>" . htmlspecialchars($__cv, ENT_QUOTES, 'UTF-8') . "</b>\n🆕 نسخه گیتهاب: <b>" . ($__rv !== '' ? htmlspecialchars($__rv, ENT_QUOTES, 'UTF-8') : '?') . "</b>\n\n{$__vs}\n\nقبل از آپدیت از فایلها بکاپ گرفته میشود و config.php حفظ میشود.\nآپدیت انجام شود؟", $kb);
        return;
    } elseif ($datain === 'navset_backup') {
        if (function_exists('backupSettingsText') && function_exists('buildBackupSettingsKeyboard')) {
            $__nav_show(backupSettingsText(), buildBackupSettingsKeyboard());
        } else {
            $__nav_show('💾 پشتیبان‌گیری', null);
        }
        return;
    } elseif (strpos(strval($datain), 'navset_') === 0 || in_array($datain, ['nav_settings','nav_finance','nav_panel','nav_admin'], true)) {
        // callback ناشناخته در این خانواده
        return;
    }
}


# نسخه 2.4.3: توضیح قابلیتها و تأیید تغییرهای حساس
if (isset($datain) && is_string($datain) && in_array($from_id, $admin_ids)) {
    if (preg_match('/^ftinfo:(\w+)$/', $datain, $__fi)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => botFeatureInfo($__fi[1]), 'show_alert' => true]);
        return;
    }
    if (preg_match('/^ftok:(\w+):([01])$/', $datain, $__fo)) {
        $datain = "editstsuts-{$__fo[1]}-{$__fo[2]}";
    } elseif (preg_match('/^editstsuts-(\w+)-([01])$/', $datain, $__fd)) {
        $__fw = botFeatureDangerWarning($__fd[1], $__fd[2]);
        if ($__fw !== '') {
            Editmessagetext($from_id, $message_id, $__fw, json_encode(['inline_keyboard' => [
                [['text' => '✅ بله، تغییر کن', 'callback_data' => "ftok:{$__fd[1]}:{$__fd[2]}"]],
                [['text' => '❌ انصراف', 'callback_data' => 'navset_features']],
            ]], JSON_UNESCAPED_UNICODE));
            return;
        }
    }
}

if (in_array($text, $textadmin) || $datain == "PANEL") {
    if (!(function_exists('shell_exec') && is_callable('shell_exec'))) {
        $cronCommandsendmessage = "*/1 * * * * curl -s " . botCronUrl('sendmessage.php') . " >/dev/null 2>&1";
        sendmessage($from_id, sprintf($textbotlang['Admin']['cron']['active_manual_sendmessage'], $cronCommandsendmessage), null, 'HTML');
    }
    $text_admin = sprintf($textbotlang['Admin']['login-admin'], $version);
    sendmessage($from_id, $text_admin, function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
}
if ($text == $textbotlang['Admin']['Back-Adminment'] || $datain == "back_admin") {
    if ($datain == "back_admin")
        deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['Admin']['Back-Admin'], function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
    step('home', $from_id);
    return;
}

#----- پاسخ / ارسال پیام به کاربر (دکمه Response_) -----
if (isset($datain) && is_string($datain) && preg_match('/^Response_(\d+)$/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "Processing_value", $iduser, "id", $from_id);
    step('getmessageAsAdmin', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'] ?? 'متن پاسخ را ارسال کنید:', $backadmin, 'HTML');
} elseif (isset($user['step']) && $user['step'] == 'getmessageAsAdmin') {
    // انصراف با بازگشت
    if (isset($text) && is_string($text) && (
        $text === ($textbotlang['Admin']['Back-Adminment'] ?? '')
        || $text === ($textbotlang['users']['backhome'] ?? '')
        || (function_exists('mb_strpos') && mb_strpos($text, 'بازگشت') !== false)
    )) {
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['Back-Admin'] ?? 'بازگشت', function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
        return;
    }
    $target_uid = $user['Processing_value'] ?? '';
    if ($target_uid === '' || $target_uid === null) {
        step('home', $from_id);
        sendmessage($from_id, '❌ کاربر هدف مشخص نیست.', function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
        return;
    }
    $reply_kb = json_encode([
        'inline_keyboard' => [
            [[
                'text' => $textbotlang['users']['support']['reply_btn'] ?? '💬 پاسخ به پشتیبانی',
                'callback_data' => 'support',
            ]],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $sent_ok = false;
    if (!empty($text) && is_string($text) && trim($text) !== '') {
        $textSendAdminToUser = sprintf(
            $textbotlang['Admin']['systemsms']['sendedmessagetouser'] ?? "📩 یک پیام از طرف مدیریت ارسال شده است\n\n%s",
            $text
        );
        sendmessage($target_uid, $textSendAdminToUser, $reply_kb, 'HTML');
        $sent_ok = true;
    }
    if (!empty($photo)) {
        $cap = sprintf(
            $textbotlang['Admin']['systemsms']['sendedmessagetouser'] ?? "📩 یک پیام از طرف مدیریت ارسال شده است\n\n%s",
            isset($caption) && $caption !== null && $caption !== '' ? $caption : ''
        );
        telegram('sendphoto', [
            'chat_id' => $target_uid,
            'photo' => $photoid,
            'caption' => $cap,
            'reply_markup' => $reply_kb,
            'parse_mode' => 'HTML',
        ]);
        $sent_ok = true;
    }
    if (!$sent_ok) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['GetTextResponse'] ?? 'متن یا عکس پاسخ را ارسال کنید.', $backadmin, 'HTML');
        return;
    }
    $__to_uid = htmlspecialchars(strval($user['Processing_value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $__sent_msg = ($textbotlang['Admin']['ManageUser']['SendMessageuser'] ?? '✅ پیام برای کاربر ارسال شد.')
        . ($__to_uid !== '' ? "\n🆔 کاربر: <code>{$__to_uid}</code>" : '');
    sendmessage($from_id, $__sent_msg, function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
    step('home', $from_id);
}
 elseif ($text == $textbotlang['Admin']['channel']['changechannelbtn']) {
    sendmessage($from_id, $textbotlang['Admin']['channel']['changechannel'] . $channels['link'], $backadmin, 'HTML');
    step('addchannel', $from_id);
} elseif ($user['step'] == "addchannel" && empty($datain)) {
    // نسخه 2.4.1: اعتبارسنجی آیدی + بررسی ادمین بودن ربات (بدون تغییر ساختار جدول)
    $__ch = botNormalizeChannelInput($text);
    if ($__ch === '') {
        sendmessage($from_id, "❌ آیدی کانال نامعتبر است.\nنمونه: <code>mychannel</code> یا <code>@mychannel</code> یا <code>https://t.me/mychannel</code>\n(کانال باید عمومی باشد.) دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    list($__okc, $__msgc) = botCheckBotChannelAdmin($__ch);
    if ($__okc === false) {
        sendmessage($from_id, "❌ " . $__msgc . "\n\nاول ربات را در کانال @{$__ch} ادمین کنید، بعد دوباره آیدی را بفرستید.", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $channels_ch = select("channels", "link", null, null, "count");
    if ($channels_ch == 0) {
        $pdo->prepare("INSERT INTO channels (link) VALUES (?)")->execute([$__ch]);
    } else {
        update("channels", "link", $__ch);
    }
    list($__tx, $__kb) = botChannelJoinPage("✅ کانال @{$__ch} ثبت شد." . ($__okc === null ? "\n⚠️ " . $__msgc . " بعداً با «🔍 بررسی» دوباره امتحان کنید." : ''));
    sendmessage($from_id, $__tx, $__kb, 'HTML');
}
if ($text == $textbotlang['Admin']['Addedadmin']) {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('addadmin', $from_id);
}
if ($user['step'] == "addadmin" && empty($datain)) {
    // نسخه 2.4.1: اعتبارسنجی + تأیید قبل از افزودن ادمین
    $__aid = botAdmInt($text, 1, 9999999999999);
    if ($__aid === null) {
        sendmessage($from_id, "❌ آیدی عددی معتبر بفرستید (فقط عدد، مثل <code>123456789</code>).\nبرای لغو «بازگشت» را بزنید.", $backadmin, 'HTML');
        return;
    }
    $__aid = strval($__aid);
    if (in_array($__aid, array_map('strval', (array)($admin_ids ?? [])), true)) {
        sendmessage($from_id, "⚠️ این کاربر از قبل ادمین است. آیدی دیگری بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__au = select("user", "*", "id", $__aid, "select");
    step('home', $from_id);
    $__un = is_array($__au) ? trim(strval($__au['username'] ?? '')) : '';
    $__info = is_array($__au)
        ? "👤 یوزرنیم: " . (($__un !== '' && $__un !== 'none' && $__un !== 'NOT_USERNAME') ? '@' . htmlspecialchars($__un, ENT_QUOTES, 'UTF-8') : 'ندارد')
        : "⚠️ این کاربر هنوز ربات را استارت نکرده است.";
    $__kb = json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، ادمین شود', 'callback_data' => 'admadd_ok_' . $__aid]],
        [['text' => '❌ انصراف', 'callback_data' => 'adm_adm_list']],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "➕ <b>افزودن ادمین</b>\n\n🆔 آیدی: <code>{$__aid}</code>\n{$__info}\n\n⚠️ ادمین به همه بخشهای مدیریت (موجودی کاربران، رسیدها، پیام همگانی) دسترسی کامل دارد.\nاضافه شود؟", $__kb, 'HTML');
    return;
}
if ($text == $textbotlang['Admin']['Removeedadmin']) {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'], $backadmin, 'HTML');
    step('deleteadmin', $from_id);
} elseif ($user['step'] == "deleteadmin" && empty($datain)) {
    // نسخه 2.4.1: پیام خطا + تأیید حذف
    $__did = botAdmInt($text, 1, 9999999999999);
    if ($__did === null) {
        sendmessage($from_id, "❌ آیدی عددی معتبر بفرستید. برای لغو «بازگشت» را بزنید.", $backadmin, 'HTML');
        return;
    }
    $__did = strval($__did);
    if ($__did === strval($adminnumber)) {
        sendmessage($from_id, $textbotlang['Admin']['manageadmin']['InfoAdd'], $backadmin, 'HTML');
        return;
    }
    if ($__did === strval($from_id)) {
        sendmessage($from_id, "❌ نمیتوانید خودتان را حذف کنید. آیدی دیگری بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (!in_array($__did, array_map('strval', (array)($admin_ids ?? [])), true)) {
        sendmessage($from_id, "❌ این آیدی در لیست ادمینها نیست. دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    sendmessage($from_id, "⚠️ ادمین <code>{$__did}</code> حذف شود؟", json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، حذف شود', 'callback_data' => 'adm_adm_delok_' . $__did]],
        [['text' => '❌ انصراف', 'callback_data' => 'adm_adm_list']],
    ]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif (preg_match('/limitusertest_(.*)/', $datain, $dataget)) {
    $id_user = $dataget[1];
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['getid'], $backadmin, 'HTML');
    update("user", "Processing_value", $id_user, "id", $from_id);
    step('get_number_limit', $from_id);
} elseif ($user['step'] == "get_number_limit" && empty($datain)) {
    // نسخه 2.4.1: فقط عدد ۰ تا ۱۰۰۰
    $__n = botAdmInt($text, 0, 1000);
    if ($__n === null) {
        sendmessage($from_id, "❌ یک عدد بین ۰ تا ۱۰۰۰ بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__tu = strval($user['Processing_value'] ?? '');
    step('home', $from_id);
    if ($__tu === '' || !is_array(select("user", "*", "id", $__tu, "select"))) {
        sendmessage($from_id, "❌ کاربر پیدا نشد.", $keyboardadmin, 'HTML');
        return;
    }
    update("user", "limit_usertest", strval($__n), "id", $__tu);
    sendmessage($from_id, "✅ سهمیه تست کاربر <code>" . htmlspecialchars($__tu, ENT_QUOTES, 'UTF-8') . "</code> روی <b>{$__n}</b> تنظیم شد.", $keyboardadmin, 'HTML');
}
if ($text == $textbotlang['Admin']['getlimitusertest']['setlimitallbtn']) {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'], $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($user['step'] == "limit_usertest_allusers" && empty($datain)) {
    // نسخه 2.4.1: اعتبارسنجی + پیشنمایش تعداد + تأیید
    $__n = botAdmInt($text, 0, 1000);
    if ($__n === null) {
        sendmessage($from_id, "❌ یک عدد بین ۰ تا ۱۰۰۰ بفرستید:", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $__chg = 0;
    try {
        $__st = $pdo->prepare("SELECT COUNT(*) FROM user WHERE CAST(limit_usertest AS SIGNED) <> ?");
        $__st->execute([$__n]);
        $__chg = intval($__st->fetchColumn());
    } catch (Throwable $e) {}
    $__old = strval($setting['limit_usertest_all'] ?? '-');
    sendmessage($from_id, "⚠️ <b>تغییر سقف تست همه کاربران</b>\n\nسقف فعلی: <b>{$__old}</b>\nسقف جدید: <b>{$__n}</b>\n👥 کاربرانی که سهمیهشان تغییر میکند: <b>" . number_format($__chg) . "</b>\n\nسهمیه باقیمانده همه کاربران روی <b>{$__n}</b> گذاشته میشود (حتی کسانی که قبلاً تست گرفتهاند).\nاعمال شود؟", json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، اعمال شود', 'callback_data' => 'utlim_ok_' . $__n]],
        [['text' => '❌ انصراف', 'callback_data' => 'adm_ut_panel']],
    ]], JSON_UNESCAPED_UNICODE), 'HTML');
}
if ($text == $textbotlang['Admin']['channel']['setting']) {
    list($__tx, $__kb) = botChannelJoinPage();
    sendmessage($from_id, $__tx, $__kb, 'HTML');
}
# نسخه 2.4.1: تأییدهای ادمین/سقف تست/کانال
if (isset($datain) && preg_match('/^admadd_ok_(\d+)$/', strval($datain), $__m)) {
    $__aid = strval($__m[1]);
    try {
        $__st = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE id_admin = ?");
        $__st->execute([$__aid]);
        if (intval($__st->fetchColumn()) === 0) {
            $pdo->prepare("INSERT INTO admin (id_admin) VALUES (?)")->execute([$__aid]);
        }
    } catch (Throwable $e) {
        error_log('add admin: ' . $e->getMessage());
    }
    botAdminActionReport('افزودن ادمین', "🆔 ادمین جدید: <code>{$__aid}</code>");
    list($__tx, $__kb) = adminAdminsListKeyboard($from_id);
    Editmessagetext($from_id, $message_id, "✅ ادمین <code>{$__aid}</code> اضافه شد.\n\n" . $__tx, $__kb);
    return;
}
if (isset($datain) && preg_match('/^adm_adm_delok_(\d+)$/', strval($datain), $__m)) {
    $__did = strval($__m[1]);
    if ($__did === strval($adminnumber) || $__did === strval($from_id)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این ادمین قابل حذف نیست', 'show_alert' => true]);
        return;
    }
    try {
        $pdo->prepare("DELETE FROM admin WHERE id_admin = ?")->execute([$__did]);
    } catch (Throwable $e) {}
    botAdminActionReport('حذف ادمین', "🆔 ادمین حذفشده: <code>{$__did}</code>");
    list($__tx, $__kb) = adminAdminsListKeyboard($from_id);
    Editmessagetext($from_id, $message_id, "✅ ادمین <code>{$__did}</code> حذف شد.\n\n" . $__tx, $__kb);
    return;
}
if (isset($datain) && preg_match('/^utlim_ok_(\d+)$/', strval($datain), $__m)) {
    $__n = intval($__m[1]);
    if ($__n > 1000) {
        return;
    }
    update("setting", "limit_usertest_all", strval($__n));
    update("user", "limit_usertest", strval($__n));
    list($__tx, $__kb) = adminUsertestPanel();
    Editmessagetext($from_id, $message_id, "✅ سقف تست همه کاربران روی <b>{$__n}</b> تنظیم شد.\n\n" . $__tx, $__kb);
    return;
}
if (isset($datain) && $datain === 'adm_ut_resetok') {
    $settingrow = select("setting", "*", null, null, "select");
    $lim = max(0, intval($settingrow['limit_usertest_all'] ?? 1));
    update("user", "limit_usertest", strval($lim));
    $msg = sprintf($textbotlang['Admin']['Usertest']['reset_all_limits_ok'] ?? '✅ سقف تست همه کاربران به %s ریست شد', $lim);
    list($__tx, $__kb) = adminUsertestPanel();
    Editmessagetext($from_id, $message_id, $msg . "\n\n" . $__tx, $__kb);
    return;
}
if (isset($datain) && in_array($datain, ['chjoin_set', 'chjoin_test', 'chjoin_del', 'chjoin_delok'], true)) {
    if ($datain === 'chjoin_set') {
        sendmessage($from_id, "📢 آیدی کانال عمومی را بفرستید.\nنمونه: <code>mychannel</code> یا <code>@mychannel</code> یا <code>https://t.me/mychannel</code>\n\n⚠️ قبلش ربات را در کانال <b>ادمین</b> کنید.", $backadmin, 'HTML');
        step('addchannel', $from_id);
        return;
    }
    $__row = select('channels', 'link', null, null, 'select');
    $__link = is_array($__row) ? trim(strval($__row['link'] ?? '')) : '';
    if ($datain === 'chjoin_test') {
        if ($__link === '') {
            list($__tx, $__kb) = botChannelJoinPage();
        } else {
            list($__okc, $__msgc) = botCheckBotChannelAdmin($__link);
            list($__tx, $__kb) = botChannelJoinPage(($__okc === true ? '✅ ' : '❌ ') . $__msgc);
        }
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
        return;
    }
    if ($datain === 'chjoin_del') {
        Editmessagetext($from_id, $message_id, "⚠️ قفل کانال برداشته شود؟\nبعد از آن کاربران بدون عضویت در کانال از ربات استفاده میکنند.", json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، برداشته شود', 'callback_data' => 'chjoin_delok']],
            [['text' => '❌ انصراف', 'callback_data' => 'navset_chjoin']],
        ]], JSON_UNESCAPED_UNICODE));
        return;
    }
    update("channels", "link", "");
    list($__tx, $__kb) = botChannelJoinPage("✅ قفل کانال برداشته شد.");
    Editmessagetext($from_id, $message_id, $__tx, $__kb);
    return;
}
#-------------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['bot_statistics'] || (isset($datain) && $datain === 'stats_refresh')) {
    global $pdo;
    $now = time();
    // بازه تقویمی به وقت تهران
    // امروز از 00:00 | دیروز | این هفته از شنبه | هفته پیش | این ماه از اول ماه
    try {
        $tz = new DateTimeZone('Asia/Tehran');
        $now_dt = new DateTime('now', $tz);
        $day_start = (clone $now_dt)->setTime(0, 0, 0);
        $yest_start = (clone $day_start)->modify('-1 day');
        // شنبه = شروع هفته ایرانی؛ format('w'): 0=یکشنبه ... 6=شنبه
        $w = intval($now_dt->format('w'));
        $days_since_sat = ($w + 1) % 7; // شنبه=0 ، یکشنبه=1 ، ... جمعه=6
        $week_start = (clone $now_dt)->setTime(0, 0, 0)->modify('-' . $days_since_sat . ' days');
        $prev_week_start = (clone $week_start)->modify('-7 days');
        $month_start = (clone $now_dt)->modify('first day of this month')->setTime(0, 0, 0);
        $prev_month_start = (clone $now_dt)->modify('first day of last month')->setTime(0, 0, 0);
        // salesLedgerSum شرط > دارد؛ یک ثانیه کم تا از خود 00:00 حساب شود
        $day_ago = $day_start->getTimestamp() - 1;
        $yest_from = $yest_start->getTimestamp() - 1;
        $yest_until = $day_start->getTimestamp() - 1; // تا قبل از امروز 00:00
        $week_ago = $week_start->getTimestamp() - 1;
        $prev_week_from = $prev_week_start->getTimestamp() - 1;
        $prev_week_until = $week_start->getTimestamp() - 1;
        $month_ago = $month_start->getTimestamp() - 1;
        $prev_month_from = $prev_month_start->getTimestamp() - 1;
        $prev_month_until = $month_start->getTimestamp() - 1;
    } catch (Exception $e) {
        $day_ago = $now - 86400;
        $yest_from = $now - 2 * 86400;
        $yest_until = $now - 86400;
        $week_ago = $now - 604800;
        $prev_week_from = $now - 2 * 604800;
        $prev_week_until = $now - 604800;
        $month_ago = $now - 2592000;
        $prev_month_from = $now - 60 * 86400;
        $prev_month_until = $month_ago;
    }
    $status_ok = "(Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn' OR status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn')";
    $not_test = "name_product != 'usertest'";

    $statistics = intval(select("user", "*", null, null, "count"));
    $sumpanel = intval(select("marzban_panel", "*", null, null, "count"));
    $count_usertest = intval(select("invoice", "*", "name_product", "usertest", "count"));
    $Balanceall = select("user", "SUM(Balance)", null, null, "select");
    $bal = is_array($Balanceall) ? intval($Balanceall['SUM(Balance)'] ?? 0) : 0;

    // کل فروش از فاکتورهای موجود (مثل نسخه اصلی)
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(price_product),0) AS sm FROM invoice WHERE {$status_ok} AND {$not_test}");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $invoice_cnt = intval($row['cnt'] ?? 0);
    $invoice_sum = intval($row['sm'] ?? 0);

    // بازه‌های زمانی از sales_ledger (شامل خرید + تمدید + حجم اضافه)
    // جدول invoice فقط خرید اولیه را دارد و time_sell تمدید آپدیت نمی‌شود
    $periodStats = function ($from_ts, $until_ts = null) use ($pdo, $status_ok, $not_test) {
        $from_ts = intval($from_ts);
        // اولویت: دفتر فروش (تمدید و خرید هر دو ثبت می‌شوند)
        if (function_exists('salesLedgerSum') && function_exists('ensureSalesLedger')) {
            try {
                ensureSalesLedger();
                $led = salesLedgerSum($from_ts, $until_ts);
                return ['cnt' => intval($led['cnt'] ?? 0), 'sum' => intval($led['sum'] ?? 0)];
            } catch (Exception $e) {
                // fallback به invoice
            }
        }
        // fallback: فاکتورهای موجود (فقط خریدهای اولیه با time_sell)
        try {
            $from_a = date('Y/m/d H:i:s', $from_ts);
            $from_b = date('Y-m-d H:i:s', $from_ts);
            $sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(price_product),0) AS sm FROM invoice
                WHERE {$status_ok} AND {$not_test}
                AND (
                    time_sell > :ts
                    OR time_sell > :ta
                    OR time_sell > :tb
                    OR (UNIX_TIMESTAMP(STR_TO_DATE(time_sell, '%Y/%m/%d %H:%i:%s')) > :u1)
                    OR (UNIX_TIMESTAMP(STR_TO_DATE(time_sell, '%Y-%m-%d %H:%i:%s')) > :u2)
                )";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':ts' => $from_ts,
                ':ta' => $from_a,
                ':tb' => $from_b,
                ':u1' => $from_ts,
                ':u2' => $from_ts,
            ]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            return ['cnt' => intval($r['cnt'] ?? 0), 'sum' => intval($r['sm'] ?? 0)];
        } catch (Exception $e) {
            return ['cnt' => 0, 'sum' => 0];
        }
    };

    $day = $periodStats($day_ago);
    $yest = $periodStats($yest_from, $yest_until);
    $week = $periodStats($week_ago);
    $prev_week = $periodStats($prev_week_from, $prev_week_until);
    $month = $periodStats($month_ago);
    $prev_month = $periodStats($prev_month_from, $prev_month_until);

    // رکورد بیشترین فروش روزانه در ماه جاری (به وقت تهران)
    $best_day_cnt = 0;
    $best_day_sum = 0;
    $best_day_label = '—';
    try {
        if (function_exists('ensureSalesLedger')) {
            ensureSalesLedger();
        }
        global $pdo;
        $st_bd = $pdo->prepare("SELECT created_at, price FROM sales_ledger WHERE created_at > ? AND created_at <= ?");
        $st_bd->execute([intval($month_ago), time() + 10]);
        $by_day = [];
        $tz_bd = new DateTimeZone('Asia/Tehran');
        while ($row_bd = $st_bd->fetch(PDO::FETCH_ASSOC)) {
            $ts_bd = intval($row_bd['created_at'] ?? 0);
            if ($ts_bd <= 0) {
                continue;
            }
            $dt_bd = (new DateTime('@' . $ts_bd))->setTimezone($tz_bd);
            $key_bd = $dt_bd->format('Y-m-d');
            if (!isset($by_day[$key_bd])) {
                $by_day[$key_bd] = ['cnt' => 0, 'sum' => 0];
            }
            $by_day[$key_bd]['cnt']++;
            $by_day[$key_bd]['sum'] += intval($row_bd['price'] ?? 0);
        }
        foreach ($by_day as $d_key => $d_val) {
            if (intval($d_val['sum']) > $best_day_sum
                || (intval($d_val['sum']) === $best_day_sum && intval($d_val['cnt']) > $best_day_cnt)
            ) {
                $best_day_sum = intval($d_val['sum']);
                $best_day_cnt = intval($d_val['cnt']);
                $best_day_label = $d_key;
            }
        }
        if ($best_day_label !== '—') {
            try {
                $ts_lab = (new DateTime($best_day_label . ' 12:00:00', $tz_bd))->getTimestamp();
                $best_day_label = function_exists('jdate') ? jdate('Y/m/d', $ts_lab) : $best_day_label;
            } catch (Exception $e_lab) {
            }
        }
    } catch (Exception $e_bd) {
        $best_day_cnt = 0;
        $best_day_sum = 0;
        $best_day_label = '—';
    }

    // واریزی‌های تأییدشده — پارس تاریخ در PHP به وقت تهران (مقایسه رشته‌ای MySQL باگ مرز نیمه‌شب دارد)
    $parsePayTime = function ($raw) {
        $raw = trim(strval($raw));
        if ($raw === '') {
            return null;
        }
        // یونیکس عددی
        if (ctype_digit($raw) && strlen($raw) >= 10) {
            return intval(substr($raw, 0, 10));
        }
        // 2026/09/30 12:34:56 یا 2026-09-30 12:34:56
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})[\sT]+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $raw, $m)) {
            try {
                $tz = new DateTimeZone('Asia/Tehran');
                $sec = isset($m[6]) && $m[6] !== '' ? intval($m[6]) : 0;
                $dt = new DateTime(sprintf(
                    '%04d-%02d-%02d %02d:%02d:%02d',
                    intval($m[1]), intval($m[2]), intval($m[3]),
                    intval($m[4]), intval($m[5]), $sec
                ), $tz);
                return $dt->getTimestamp();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    };

    // یک‌بار paidهای اخیر را می‌خوانیم و در PHP بر اساس بازه فیلتر می‌کنیم
    $paid_rows = [];
    try {
        // فقط از اول ماه قبل به بعد کافی است (برای هفته پیش / این ماه)
        $tz = new DateTimeZone('Asia/Tehran');
        $scan_from = (new DateTime('now', $tz))->modify('first day of last month')->setTime(0, 0, 0);
        $scan_slash = $scan_from->format('Y/m/d') . ' 00:00:00';
        $scan_dash = $scan_from->format('Y-m-d') . ' 00:00:00';
        $st = $pdo->prepare("SELECT price, `time` FROM Payment_report WHERE payment_Status = 'paid' AND (
            (`time` >= :fs AND `time` REGEXP '^[0-9]{4}/')
            OR (`time` >= :fd AND `time` REGEXP '^[0-9]{4}-')
            OR (`time` REGEXP '^[0-9]{10,}')
        )");
        $st->execute([':fs' => $scan_slash, ':fd' => $scan_dash]);
        $paid_rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('deposit load: ' . $e->getMessage());
        $paid_rows = [];
    }

    $depositStats = function ($from_ts, $until_ts = null) use ($paid_rows, $parsePayTime) {
        // from_ts در آمار = start-1 ؛ until = end_inclusive (معمولاً next_start-1)
        $start = intval($from_ts) + 1;
        $end = $until_ts === null ? (time() + 60) : intval($until_ts);
        $cnt = 0;
        $sum = 0;
        foreach ($paid_rows as $row) {
            $ts = $parsePayTime($row['time'] ?? '');
            if ($ts === null) {
                continue;
            }
            if ($ts < $start || $ts > $end) {
                continue;
            }
            $pr = preg_replace('/[^0-9]/', '', strval($row['price'] ?? ''));
            if ($pr === '') {
                continue;
            }
            $cnt++;
            $sum += intval($pr);
        }
        return ['cnt' => $cnt, 'sum' => $sum];
    };
    $dep_day = $depositStats($day_ago);
    $dep_yest = $depositStats($yest_from, $yest_until);
    $dep_week = $depositStats($week_ago);
    $dep_prev_week = $depositStats($prev_week_from, $prev_week_until);
    $dep_month = $depositStats($month_ago);
    $dep_prev_month = $depositStats($prev_month_from, $prev_month_until);

    // مجموع پورسانت‌های پرداخت‌شده (ستون affiliates_balance)
    $aff_paid = 0;
    try {
        if (function_exists('ensureAffiliatesBalanceColumn')) {
            ensureAffiliatesBalanceColumn();
        }
        $st_aff = $pdo->query("SELECT COALESCE(SUM(affiliates_balance),0) AS sm FROM user");
        if ($st_aff) {
            $aff_paid = intval($st_aff->fetchColumn());
        }
    } catch (Exception $e) {
        $aff_paid = 0;
    }

    // تعداد کاربرانی که از طریق لینک مشارکت در فروش آمده‌اند
    $aff_joined = 0;
    try {
        $st_aj = $pdo->query("SELECT COUNT(*) FROM user WHERE affiliates IS NOT NULL AND affiliates != '' AND affiliates != '0' AND CAST(affiliates AS UNSIGNED) > 0");
        if ($st_aj) {
            $aff_joined = intval($st_aj->fetchColumn());
        }
    } catch (Exception $e) {
        try {
            $st_aj = $pdo->query("SELECT COUNT(*) FROM user WHERE affiliates IS NOT NULL AND affiliates != 0 AND affiliates != '0'");
            if ($st_aj) {
                $aff_joined = intval($st_aj->fetchColumn());
            }
        } catch (Exception $e2) {
            $aff_joined = 0;
        }
    }

    // نسخه 2.2.9: سرویس واقعاً فعال + سرویسهایی که تا ۳ روز آینده منقضی میشوند (بدون تست)
    $svc_active = 0;
    $svc_expiring = 0;
    try {
        $__st_a = $pdo->query("SELECT COUNT(*) FROM invoice WHERE (Status = 'active' OR Status = 'sendedwarn') AND name_product != 'usertest'");
        $svc_active = $__st_a ? intval($__st_a->fetchColumn()) : 0;
        $__ts = "(CASE WHEN time_sell REGEXP '^[0-9]{9,}$' THEN CAST(time_sell AS UNSIGNED) ELSE UNIX_TIMESTAMP(time_sell) END)";
        $__st_e = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE (Status = 'active' OR Status = 'sendedwarn') AND name_product != 'usertest'
            AND CAST(Service_time AS UNSIGNED) > 0 AND {$__ts} > 0
            AND ({$__ts} + CAST(Service_time AS UNSIGNED) * 86400) BETWEEN ? AND ?");
        $__st_e->execute([time(), time() + 3 * 86400]);
        $svc_expiring = intval($__st_e->fetchColumn());
    } catch (Throwable $e) {
    }
    $now_label = function_exists('jdate') ? jdate('Y/m/d H:i:s') : date('Y-m-d H:i:s');

    $statisticsall = sprintf(
        $textbotlang['Admin']['Statistics']['info'],
        $now_label,
        number_format($statistics),
        number_format($bal),
        number_format($day['cnt']),
        number_format($day['sum']),
        number_format($yest['cnt']),
        number_format($yest['sum']),
        number_format($week['cnt']),
        number_format($week['sum']),
        number_format($prev_week['cnt']),
        number_format($prev_week['sum']),
        number_format($month['cnt']),
        number_format($month['sum']),
        number_format($best_day_cnt),
        number_format($best_day_sum),
        $best_day_label,
        number_format($prev_month['cnt']),
        number_format($prev_month['sum']),
        number_format($invoice_cnt),
        number_format($invoice_sum),
        number_format($dep_day['cnt']),
        number_format($dep_day['sum']),
        number_format($dep_yest['cnt']),
        number_format($dep_yest['sum']),
        number_format($dep_week['cnt']),
        number_format($dep_week['sum']),
        number_format($dep_prev_week['cnt']),
        number_format($dep_prev_week['sum']),
        number_format($dep_month['cnt']),
        number_format($dep_month['sum']),
        number_format($dep_prev_month['cnt']),
        number_format($dep_prev_month['sum']),
        number_format($aff_paid),
        number_format($aff_joined),
        number_format($svc_active),
        number_format($svc_expiring),
        number_format($count_usertest),
        number_format($sumpanel)
    );
    $stats_excel_kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '📥 اکسل این ماه', 'callback_data' => 'stats_excel_cur'],
                ['text' => '📥 اکسل ماه قبل', 'callback_data' => 'stats_excel_prev'],
            ],
            [
                ['text' => '🔄 بروزرسانی', 'callback_data' => 'stats_refresh'],
            ],
        ],
    ]);
    if (isset($datain) && $datain === 'stats_refresh' && !empty($message_id)) {
        try {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id ?? '', 'text' => '🔄 بروزرسانی شد', 'show_alert' => false]);
        } catch (Throwable $e) {
        }
        Editmessagetext($from_id, $message_id, $statisticsall, $stats_excel_kb);
    } else {
        sendmessage($from_id, $statisticsall, $stats_excel_kb, 'HTML');
    }
}

#----[ خروجی اکسل آمار ماهانه ]----#
if ($datain == 'stats_excel_cur' || $datain == 'stats_excel_prev') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    if (function_exists('answerCallbackQuery') || true) {
        try {
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id ?? ($update['callback_query']['id'] ?? ''),
                'text' => '⏳ در حال آماده‌سازی گزارش...',
                'show_alert' => false,
            ]);
        } catch (Throwable $e) {
        }
    }
    $which = ($datain == 'stats_excel_prev') ? 'prev' : 'current';
    if (!function_exists('buildMonthlyStatsExcel')) {
        sendmessage($from_id, '❌ تابع ساخت گزارش در دسترس نیست.', null, 'HTML');
        return;
    }
    $res = buildMonthlyStatsExcel($which);
    if (!empty($res['error'])) {
        sendmessage($from_id, '❌ ' . $res['error'], null, 'HTML');
        return;
    }
    $path = $res['path'] ?? '';
    if ($path === '' || !is_file($path)) {
        sendmessage($from_id, '❌ فایل گزارش ساخته نشد.', null, 'HTML');
        return;
    }
    if (function_exists('sendDocument')) {
        sendDocument($from_id, $path, $res['caption'] ?? 'گزارش ماهانه');
    } else {
        sendmessage($from_id, '❌ امکان ارسال فایل نیست.', null, 'HTML');
    }
    @unlink($path);
}


#----[ گروه‌های پنل پاسارگارد ]----#
if ($text == ($textbotlang['Admin']['managepanel']['show_groups'] ?? '📂 گروه‌های پنل')) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!$panel || (!in_array(($panel['type'] ?? ''), ['marzban', 'pasarguard'], true))) {
        sendmessage($from_id, "این گزینه فقط برای پنل پاسارگارد است.", $optionMarzban, 'HTML');
        return;
    }
    if (function_exists('sendTyping')) { /* noop */ }
    $g_resp = get_groups($user['Processing_value']);
    $g_list = function_exists('_extract_groups_array') ? _extract_groups_array($g_resp) : (is_array($g_resp) ? $g_resp : []);
    if (!is_array($g_list) || count($g_list) === 0) {
        sendmessage($from_id, "❌ گروهی از پنل خوانده نشد.\n\nآدرس و یوزر/پسورد را چک کنید و در پاسارگارد برای این ادمین گروه بسازید.", $optionMarzban, 'HTML');
        return;
    }
    $lines = ["📂 <b>گروه‌های پنل</b> <code>{$panel['name_panel']}</code>\n"];
    foreach ($g_list as $g) {
        if (!is_array($g)) continue;
        $gid = $g['id'] ?? $g['group_id'] ?? '?';
        $gn = htmlspecialchars(strval($g['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dis = (!empty($g['is_disabled']) || !empty($g['disabled'])) ? ' (غیرفعال)' : '';
        $lines[] = "• <code>{$gid}</code> — {$gn}{$dis}";
    }
    $lines[] = "\n✅ ساخت کاربر با این گروه‌ها انجام می‌شود.";
    sendmessage($from_id, implode("\n", $lines), $optionMarzban, 'HTML');
}

if ($text == $textbotlang['Admin']['managepanel']['btnshowconnect']) {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (($marzban_list_get['type'] == "marzban" || $marzban_list_get['type'] == "pasarguard")) {
        $t0 = microtime(true);
        $Check_token = token_panel($marzban_list_get['id']);
        $ms_token = (int) round((microtime(true) - $t0) * 1000);
        if (isset($Check_token['access_token'])) {
            $t1 = microtime(true);
            $System_Stats = Get_System_Stats($user['Processing_value']);
            $ms_stats = (int) round((microtime(true) - $t1) * 1000);
            $active_users = is_array($System_Stats) ? ($System_Stats['users_active'] ?? $System_Stats['active_users'] ?? '-') : '-';
            $total_user = is_array($System_Stats) ? ($System_Stats['total_user'] ?? $System_Stats['total_users'] ?? '-') : '-';
            $version = is_array($System_Stats) ? ($System_Stats['version'] ?? '-') : '-';
            $mem_total = (is_array($System_Stats) && isset($System_Stats['mem_total'])) ? formatBytes($System_Stats['mem_total']) : '-';
            $mem_used = (is_array($System_Stats) && isset($System_Stats['mem_used'])) ? formatBytes($System_Stats['mem_used']) : '-';
            $bw = '-';
            if (is_array($System_Stats) && isset($System_Stats['outgoing_bandwidth'], $System_Stats['incoming_bandwidth'])) {
                $bw = formatBytes(intval($System_Stats['outgoing_bandwidth']) + intval($System_Stats['incoming_bandwidth']));
            }
            // گروه‌ها
            $groups_line = "—";
            $g_count = 0;
            try {
                $t2 = microtime(true);
                $g_resp = function_exists('get_groups') ? get_groups($user['Processing_value']) : null;
                $ms_groups = (int) round((microtime(true) - $t2) * 1000);
                $g_list = function_exists('_extract_groups_array') ? _extract_groups_array($g_resp) : (is_array($g_resp) ? $g_resp : []);
                if (is_array($g_list) && count($g_list) > 0) {
                    $names = [];
                    foreach ($g_list as $g) {
                        if (!is_array($g)) continue;
                        $gid = $g['id'] ?? $g['group_id'] ?? '?';
                        $gn = $g['name'] ?? '';
                        $names[] = ($gn !== '' ? "{$gn} (#{$gid})" : "#{$gid}");
                    }
                    $g_count = count($names);
                    $groups_line = implode("، ", array_slice($names, 0, 15));
                    if (count($names) > 15) {
                        $groups_line .= " …";
                    }
                } else {
                    $ms_groups = $ms_groups ?? 0;
                    $groups_line = "❌ گروهی خوانده نشد";
                }
            } catch (Throwable $e) {
                $ms_groups = 0;
                $groups_line = "⚠️ خطا در خواندن گروه";
            }
            $panel_name = $marzban_list_get['name_panel'] ?? '';
            $panel_url = $marzban_list_get['url_panel'] ?? '';
            $text_marzban = "🖥 <b>وضعیت اتصال — پاسارگارد</b>\n\n";
            $text_marzban .= "✅ اتصال برقرار است\n";
            $text_marzban .= "🏷 پنل: <code>{$panel_name}</code>\n";
            $text_marzban .= "🔗 آدرس: <code>{$panel_url}</code>\n\n";
            $text_marzban .= "👥 کل کاربران: <code>{$total_user}</code>\n";
            $text_marzban .= "✅ فعال: <code>{$active_users}</code>\n";
            $text_marzban .= "📦 نسخه: <code>{$version}</code>\n";
            $text_marzban .= "💾 رم: {$mem_used} / {$mem_total}\n";
            $text_marzban .= "🌐 پهنای باند: {$bw}\n\n";
            $text_marzban .= "📂 گروه‌ها ({$g_count}):\n{$groups_line}\n\n";
            $text_marzban .= "⏱ توکن: {$ms_token}ms | آمار: {$ms_stats}ms | گروه: " . intval($ms_groups ?? 0) . "ms";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        } elseif (isset($Check_token['detail']) && (stripos(strval($Check_token['detail']), 'Incorrect') !== false || stripos(strval($Check_token['detail']), 'password') !== false)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['Incorrectinfo'], $optionMarzban, 'HTML');
        } else {
            $err = is_array($Check_token) ? json_encode($Check_token, JSON_UNESCAPED_UNICODE) : strval($Check_token);
            $text_marzban = ($textbotlang['Admin']['managepanel']['errorstatuspanel'] ?? '❌ خطا:') . "\n<code>" . htmlspecialchars(substr($err, 0, 500), ENT_QUOTES, 'UTF-8') . "</code>";
            sendmessage($from_id, $text_marzban, $optionMarzban, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $Check_token = token_panelm($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($Check_token['access_token'])) {
            $System_Stats = Get_System_Statsm($user['Processing_value']);
            $active_users = $System_Stats['active'];
            $total_user = $System_Stats['total'];
            $text_marzban = sprintf($textbotlang['Admin']['managepanel']['infomarzneshin'], $total_user, $active_users);
            sendmessage($from_id, $text_marzban, null, 'HTML');
        } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
            $text_marzban = $textbotlang['Admin']['managepanel']['Incorrectinfo'];
            sendmessage($from_id, $text_marzban, null, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstatuspanel'] . json_encode($Check_token);
            sendmessage($from_id, $text_marzban, null, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $x_ui_check_connect = login($marzban_list_get['id'], false);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], null, 'HTML');
        } elseif ($x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = $textbotlang['Admin']['managepanel']['Incorrectinfo'];
            sendmessage($from_id, $text_marzban, null, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstatuspanel'];
            sendmessage($from_id, $text_marzban, null, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "alireza") {
        $x_ui_check_connect = loginalireza($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if ($x_ui_check_connect['success']) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], null, 'HTML');
        } elseif ($x_ui_check_connect['msg'] == "Invalid username or password.") {
            $text_marzban = $textbotlang['Admin']['managepanel']['Incorrectinfo'];
            sendmessage($from_id, $text_marzban, null, 'HTML');
        } else {
            $text_marzban = $textbotlang['Admin']['managepanel']['errorstatuspanel'];
            sendmessage($from_id, $text_marzban, null, 'HTML');
        }
    } elseif ($marzban_list_get['type'] == "wgdashboard") {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $optionwgdashboard, 'HTML');
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $result = login_mikrotik($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
        if (isset($result['error'])) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['notconnect'], $optionmikrotik, 'HTML');
        } else {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionmikrotik, 'HTML');
        }
    }
    step('home', $from_id);
}
if ($text == $textbotlang['Admin']['manageadmin']['showlistbtn']) {
    if (!function_exists('isMainBotAdmin') || !isMainBotAdmin($from_id)) {
        sendmessage($from_id, "❌ این بخش فقط برای ادمین اصلی قابل دسترسی است.", function_exists('getAdminSectionPanelKeyboard') ? getAdminSectionPanelKeyboard($from_id) : $admin_section_panel, 'HTML');
        return;
    }
    list($__tx, $__kb) = adminAdminsListKeyboard($from_id);
    sendmessage($from_id, $__tx, $__kb, 'HTML');
}
if ($text == $textbotlang['Admin']['keyboardadmin']['add_panel']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['selecttypepanel'], $typepanel, 'HTML');
} elseif (preg_match('/typepanel%(.*)/', $datain, $dataget)) {
    $type = $dataget[1];
    savedata("clear", "type", $type);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $backadmin, 'HTML');
    step('add_name_panel', $from_id);
} elseif ($user['step'] == "add_name_panel") {
    if (in_array($text, $marzban_list)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Repeatpanel'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
    savedata("save", "name", $text);
    step('add_link_panel', $from_id);
} elseif ($user['step'] == "add_link_panel") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    savedata("save", "url_panel", $text);
    $userdata = json_decode($user['Processing_value'], true);
    if ($userdata['type'] == "s_ui" || $userdata['type'] == "wgdashboard") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['settoken'], $backadmin, 'HTML');
        step('add_password_panel', $from_id);
        savedata("save", "username_panel", "none");
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
    step('add_username_panel', $from_id);
} elseif ($user['step'] == "add_username_panel") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
    step('add_password_panel', $from_id);
    savedata("save", "username_panel", $text);
} elseif ($user['step'] == "add_password_panel") {
    $userdata = json_decode($user['Processing_value'], true);
    $inboundid = "0";
    $sublink = "onsublink";
    $config = "offconfig";
    $valueteststatus = "ontestshowpanel";
    $stauts = "activepanel";
    $on_hold = "offonhold";
    $stmt = $pdo->prepare("INSERT INTO marzban_panel (name_panel,url_panel,username_panel,password_panel,type,inboundid,sublink,configManual,MethodUsername,statusTest,status,onholdstatus) VALUES (?, ?, ?, ?, ?,?,?,?,?,?,?,?)");
    $stmt->execute([$userdata['name'], $userdata['url_panel'], $userdata['username_panel'], $text, $userdata['type'], $inboundid, $sublink, $config, $textbotlang['users']['customidAndRandom'], $valueteststatus, $stauts, $on_hold]);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['addedpanel'], $keyboardadmin, 'HTML');
    // نسخه 2.3.8: صفحه شیشهای پنل جدید + تست اتصال خودکار
    try {
        $__np_row = select("marzban_panel", "*", "name_panel", $userdata['name'], "select");
        if (is_array($__np_row) && function_exists('adminPanelDetailKeyboard')) {
            update("user", "Processing_value", $__np_row['name_panel'], "id", $from_id);
            $__np_test = function_exists('botPanelAutoTest') ? botPanelAutoTest($__np_row) : '';
            list($__np_tx, $__np_kb) = adminPanelDetailKeyboard($__np_row, '✅ پنل اضافه شد.' . $__np_test);
            sendmessage($from_id, $__np_tx, $__np_kb, 'HTML');
        }
    } catch (Throwable $e) {
        error_log('add panel page: ' . $e->getMessage());
    }
    if ($userdata['type'] == "x-ui_single" or $userdata['type'] == "alireza") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['notex-ui'], null, 'HTML');
    } elseif ($userdata['type'] == "pasarguard") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['notemarzban'] ?? "📌 پس از افزودن پاسارگارد، از مدیریت پنل «تست اتصال» و «گروه‌ها» را چک کنید.", null, 'HTML');
    } elseif ($userdata['type'] == "marzban") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['notemarzban_inbound'] ?? "📌 پس از افزودن مرزبان، از مدیریت پنل «تنظیم پروتکل و اینباند» را حتماً انجام دهید.", null, 'HTML');
    } elseif ($userdata['type'] == "s_ui" || $userdata['type'] == "marzneshin") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['notemarzban'], null, 'HTML');
    } elseif ($userdata['type'] == "wgdashboard") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['wgdashboard'], null, 'HTML');
    } elseif ($userdata['type'] == "mikrotik") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['mikrotik'], null, 'HTML');
    }
    if (function_exists('adminPanelsListKeyboard')) {
        list($__tx, $__kb) = adminPanelsListKeyboard();
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    }
    step('home', $from_id);
}
if ($text == $textbotlang['Admin']['keyboardadmin']['send_message'] || $datain === 'nav_cast') {
    $__tx = function_exists('buildAdminBroadcastText') ? buildAdminBroadcastText() : '📨 ارسال پیام همگانی';
    $__kb = function_exists('buildAdminBroadcastInlineKeyboard') ? buildAdminBroadcastInlineKeyboard() : $sendmessageuser;
    if ($datain === 'nav_cast' && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
    } else {
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['systemsms']['sendbulkbtn']) {
    sendmessage($from_id, "✉️ متن پیام همگانی را بفرستید.\nمیتوانید <b>عکس با کپشن</b> هم بفرستید. قالب HTML (مثل <code>&lt;b&gt;</code>) پشتیبانی میشود.", $backadmin, 'HTML');
    step('getconfirmsendall', $from_id);
} elseif ($user['step'] == "getconfirmsendall" || $user['step'] == "gettextforwardMessage") {
    // نسخه 2.3.9: پیشنمایش + تأیید + صف کرون (برای متن، عکس و فوروارد)
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    if (is_file('cron/info')) {
        step('home', $from_id);
        sendmessage($from_id, "⏳ یک ارسال همگانی هنوز در حال انجام است. صبر کنید تمام شود یا آن را لغو کنید.", $keyboardadmin, 'HTML');
        return;
    }
    $__bc = ['type' => 'text', 'id_admin' => $from_id, 'sent' => 0, 'fail' => 0];
    if ($user['step'] == "gettextforwardMessage") {
        if (empty($message_id)) {
            return;
        }
        $__bc['type'] = 'forward';
        $__bc['from_chat'] = $from_id;
        $__bc['msg_id'] = intval($message_id);
        $__pv = telegram('forwardMessage', ['chat_id' => $from_id, 'from_chat_id' => $from_id, 'message_id' => intval($message_id)]);
    } elseif (!empty($photo) && !empty($photoid)) {
        $__bc['type'] = 'photo';
        $__bc['photo'] = $photoid;
        $__bc['text'] = strval($caption ?? '');
        $__pv = telegram('sendphoto', ['chat_id' => $from_id, 'photo' => $photoid, 'caption' => $__bc['text'], 'parse_mode' => 'HTML']);
    } elseif (isset($text) && is_string($text) && trim($text) !== '') {
        $__bc['text'] = $text;
        $__pv = telegram('sendmessage', ['chat_id' => $from_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
    } else {
        sendmessage($from_id, "❌ فقط متن یا عکس (با کپشن) بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__pv_ok = function_exists('telegramOk') ? telegramOk($__pv) : (is_array($__pv) && !empty($__pv['ok']));
    if (!$__pv_ok) {
        sendmessage($from_id, "❌ این پیام قابل ارسال نیست؛ احتمالاً قالب HTML آن اشتباه است (مثلاً تگ بسته نشده).\nاصلاح کنید و دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    // نسخه 2.4.2: انتخاب گروه گیرندهها
    $__sets = botCastRecipientSets();
    $__bc['cast_pending'] = 1;
    update("user", "Processing_value", json_encode($__bc, JSON_UNESCAPED_UNICODE), "id", $from_id);
    step('home', $from_id);
    $__ckb = json_encode(['inline_keyboard' => [
        [['text' => '👥 همه کاربران (' . number_format(count($__sets['all'])) . ')', 'callback_data' => 'cast_ok']],
        [['text' => '📦 دارای سرویس فعال (' . number_format(count($__sets['act'])) . ')', 'callback_data' => 'cast_ok_act']],
        [['text' => '🆕 بدون خرید (' . number_format(count($__sets['nobuy'])) . ')', 'callback_data' => 'cast_ok_nobuy']],
        [['text' => '⏳ انقضا تا ۷ روز آینده (' . number_format(count($__sets['exp7'])) . ')', 'callback_data' => 'cast_ok_exp7']],
        [['text' => '❌ انصراف', 'callback_data' => 'cast_no']],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "👆 پیشنمایش پیام بالاست.\n\n🎯 <b>گیرندهها را انتخاب کنید</b> (عدد داخل پرانتز = تعداد گیرنده).\nکاربران مسدود در هیچ گروهی نیستند.\n\nبا زدن هر گروه، ارسال شروع میشود.", $__ckb, 'HTML');
} elseif (isset($datain) && preg_match('/^cast_(ok|no)(?:_(act|nobuy|exp7))?$/', strval($datain), $__cm)) {
    $__bc = json_decode(strval($user['Processing_value'] ?? ''), true);
    if ($__cm[1] === 'no' || !is_array($__bc) || empty($__bc['cast_pending'])) {
        if (is_array($__bc) && !empty($__bc['cast_pending'])) {
            update("user", "Processing_value", "0", "id", $from_id);
        }
        Editmessagetext($from_id, $message_id, ($__cm[1] === 'no' ? "❌ ارسال همگانی لغو شد." : "⚠️ این درخواست منقضی شده است. دوباره شروع کنید."), json_encode(['inline_keyboard' => []]));
        return;
    }
    if (is_file('cron/info')) {
        Editmessagetext($from_id, $message_id, "⏳ یک ارسال همگانی دیگر در حال انجام است.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $__tg = (isset($__cm[2]) && $__cm[2] !== '') ? $__cm[2] : 'all';
    $__labels = ['all' => 'همه کاربران', 'act' => 'دارای سرویس فعال', 'nobuy' => 'بدون خرید', 'exp7' => 'انقضا تا ۷ روز آینده'];
    $__sets = botCastRecipientSets();
    $result = $__sets[$__tg] ?? [];
    if (count($result) === 0) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این گروه هیچ گیرندهای ندارد. گروه دیگری انتخاب کنید.', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value", "0", "id", $from_id);
    unset($__bc['cast_pending']);
    $__bc['target'] = $__tg;
    $__bc['target_label'] = $__labels[$__tg];
    $__bc['total'] = count($result);
    $__bc['started'] = time();
    $__cancel_kb = json_encode(['inline_keyboard' => [[['text' => '⏹ توقف ارسال', 'callback_data' => 'cancel_sendmessage']]]], JSON_UNESCAPED_UNICODE);
    $__pm = sendmessage($from_id, "📨 <b>ارسال همگانی در صف قرار گرفت</b>\n\n🎯 گروه: <b>{$__labels[$__tg]}</b>\n👥 گیرندهها: <b>" . number_format($__bc['total']) . "</b>\n✅ ارسالشده: 0 | ❌ ناموفق: 0\n\n⏳ ارسال با کرون انجام میشود و این پیام خودکار بهروز میشود.", $__cancel_kb, 'HTML');
    if (is_array($__pm) && isset($__pm['result']['message_id'])) {
        $__bc['progress_mid'] = intval($__pm['result']['message_id']);
    }
    file_put_contents('cron/users.json', json_encode(array_values($result)));
    file_put_contents('cron/info', json_encode($__bc, JSON_UNESCAPED_UNICODE));
    if (function_exists('sendChannelReport')) {
        $__tn = ['text' => 'متن', 'photo' => 'عکس', 'forward' => 'فوروارد'];
        sendChannelReport('rpt_broadcast', "📨 <b>شروع پیام همگانی</b>\n\n👨‍💼 ادمین: <code>{$from_id}</code>\n📝 نوع: " . ($__tn[$__bc['type'] ?? 'text'] ?? 'متن') . "\n🎯 گروه: {$__labels[$__tg]}\n👥 گیرندهها: " . number_format($__bc['total']));
    }
    Editmessagetext($from_id, $message_id, "✅ تأیید شد. گروه: <b>{$__labels[$__tg]}</b>", json_encode(['inline_keyboard' => []]));
} elseif ($datain == "cancel_sendmessage") {
    // نسخه 2.4.2: توقف + ثبت گزارش (کرون بعد از توقف فایلها را دوباره نمیسازد)
    $__ci = is_file('cron/info') ? json_decode(strval(@file_get_contents('cron/info')), true) : null;
    @unlink('cron/info');
    @unlink('cron/users.json');
    if (is_array($__ci)) {
        botCastSaveLast($__ci, 'cancelled');
        if (function_exists('sendChannelReport')) {
            sendChannelReport('rpt_broadcast', "⏹ <b>پیام همگانی متوقف شد</b>\n\n👨‍💼 ادمین: <code>{$from_id}</code>\n👥 گیرندهها: " . number_format(intval($__ci['total'] ?? 0)) . "\n✅ ارسالشده: " . number_format(intval($__ci['sent'] ?? 0)) . " | ❌ ناموفق: " . number_format(intval($__ci['fail'] ?? 0)));
        }
        $__tot = intval($__ci['total'] ?? 0);
        $__s = intval($__ci['sent'] ?? 0);
        $__f = intval($__ci['fail'] ?? 0);
        $__msg = "⏹ <b>ارسال همگانی متوقف شد</b>\n\n👥 گیرندهها: <b>" . number_format($__tot) . "</b>\n✅ ارسالشده: <b>" . number_format($__s) . "</b> | ❌ ناموفق: <b>" . number_format($__f) . "</b>\n⏳ ارسالنشده: <b>" . number_format(max(0, $__tot - $__s - $__f)) . "</b>";
    } else {
        $__msg = "ℹ️ ارسال همگانی فعالی وجود ندارد (احتمالاً تمام شده است).";
    }
    Editmessagetext($from_id, $message_id, $__msg, json_encode(['inline_keyboard' => [[['text' => '📋 گزارش آخرین ارسال', 'callback_data' => 'cast_last']]]], JSON_UNESCAPED_UNICODE));
} elseif ($datain == "cast_last") {
    Editmessagetext($from_id, $message_id, botCastLastText(), json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به پیام همگانی', 'callback_data' => 'nav_cast']]]], JSON_UNESCAPED_UNICODE));
} elseif ($text == $textbotlang['Admin']['systemsms']['forwardbulkbtn']) {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ForwardGetext'] ?? 'پیام را فوروارد کنید', $backadmin, 'HTML');
    step('gettextforwardMessage', $from_id);
}
//_________________________________________________
if ($text == $textbotlang['Admin']['keyboardadmin']['bot_text_settings'] || $datain === 'btxt_menu') {
    if (function_exists('ensureEditableStatusTexts')) {
        @ensureEditableStatusTexts();
    }
    if (function_exists('buildBotTextSettingsPanel')) {
        $__bt_pack = buildBotTextSettingsPanel();
        $__bt_title = is_array($__bt_pack) ? $__bt_pack[0] : '📝 تنظیم متن ربات';
        $__bt_kb = is_array($__bt_pack) ? $__bt_pack[1] : null;
    } else {
        $__bt_title = '📝 تنظیم متن ربات';
        $__bt_kb = null;
    }
    if (!is_string($__bt_kb) || $__bt_kb === '') {
        // contingency: دکمه‌های حداقلی
        $__bt_kb = json_encode(['inline_keyboard' => [
            [['text' => 'متن شروع', 'callback_data' => 'btxt_text_start'], ['text' => 'متن قانون', 'callback_data' => 'btxt_text_rules']],
            [['text' => 'دکمه خرید', 'callback_data' => 'btxt_text_sell'], ['text' => 'دکمه پشتیبانی', 'callback_data' => 'btxt_text_support']],
            [['text' => '🔄 بروزرسانی', 'callback_data' => 'btxt_menu'], ['text' => '🔙 بازگشت به تنظیمات', 'callback_data' => 'nav_settings']],
        ]], JSON_UNESCAPED_UNICODE);
    }
    if ($datain === 'btxt_menu' && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__bt_title, $__bt_kb);
    } else {
        sendmessage($from_id, $__bt_title, $__bt_kb, 'HTML');
    }
    step('home', $from_id);
    return;
}

if (isset($datain) && is_string($datain) && $datain === 'btxt_close') {
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, '✅ تنظیم متن بسته شد.', null);
    }
    step('home', $from_id);
    return;
}

if (isset($datain) && is_string($datain) && preg_match('/^btxt_([a-zA-Z0-9_]+)$/', $datain, $m_btxt) && $datain !== 'btxt_menu' && $datain !== 'btxt_close') {
    $__key = $m_btxt[1];
    $__matched = null;
    $__id = $__key;
    $__fb = '';
    $__label = $__key;
    if (function_exists('getBotTextSettingsCatalog')) {
        $__cat = getBotTextSettingsCatalog();
        if (isset($__cat[$__key])) {
            $__matched = $__cat[$__key];
        } else {
            foreach ($__cat as $__k => $__meta) {
                if (strval($__meta['id'] ?? $__k) === $__key) {
                    $__matched = $__meta;
                    $__key = $__k;
                    break;
                }
            }
        }
    }
    if ($__matched !== null) {
        $__id = strval($__matched['id'] ?? $__key);
        $__fb = strval($__matched['fallback'] ?? '');
        $__label = strval($__matched['label'] ?? $__id);
    }
    $__cur = function_exists('getEditableBotText') ? getEditableBotText($__id, $__fb) : $__fb;
    if ($__cur === '' && isset($datatextbot[$__id])) {
        $__cur = strval($datatextbot[$__id]);
    }
    if ($__cur === '') {
        $__cur = $__fb !== '' ? $__fb : '—';
    }
    $__hint = "📝 <b>" . htmlspecialchars($__label, ENT_QUOTES, 'UTF-8') . "</b>\n\n";
    $__hint .= ($textbotlang['Admin']['ManageUser']['ChangeTextGet'] ?? "متن فعلی:\n\n");
    $__hint .= $__cur . "\n\n✍️ متن جدید را ارسال کنید.";
    $cancel_kb = json_encode([
        'inline_keyboard' => [
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'btxt_menu']],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__hint, $cancel_kb);
    } else {
        sendmessage($from_id, $__hint, $cancel_kb, 'HTML');
    }
    step('edit_bottext_' . $__id, $from_id);
    return;
}

// ذخیره متن پس از انتخاب از منوی اینلاین
if (isset($user['step']) && is_string($user['step']) && strpos($user['step'], 'edit_bottext_') === 0) {
    $__id = substr($user['step'], strlen('edit_bottext_'));
    if ($__id !== '') {
        // اگر کاربر روی دکمه اینلاین زد (بازگشت به لیست و ...) متن خالی است — ذخیره نکن و خطا نده
        if (isset($datain) && is_string($datain) && $datain !== '') {
            // callback جداگانه هندل می‌شود
            return;
        }

        // بازگشت ادمین
        if (isset($text) && is_string($text) && (
            $text === ($textbotlang['Admin']['Back-Adminment'] ?? '')
            || (function_exists('mb_strpos') && mb_strpos($text, 'بازگشت') !== false)
        )) {
            step('home', $from_id);
            if (function_exists('buildBotTextSettingsPanel')) {
                list($__bt_title, $__bt_kb) = buildBotTextSettingsPanel();
                sendmessage($from_id, $__bt_title, $__bt_kb, 'HTML');
            }
            return;
        }
        if (!isset($text) || !is_string($text) || trim($text) === '') {
            sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ErrorText'] ?? 'متن نامعتبر است. دوباره ارسال کنید.', $backadmin, 'HTML');
            return;
        }
        if (function_exists('upsertEditableBotText')) {
            upsertEditableBotText($__id, $text);
        } else {
            if (function_exists('ensureEditableStatusTexts')) {
                ensureEditableStatusTexts();
            }
            try {
                global $pdo;
                $st = $pdo->prepare("SELECT id_text FROM textbot WHERE id_text = ? LIMIT 1");
                $st->execute([$__id]);
                if ($st->fetch()) {
                    update("textbot", "text", $text, "id_text", $__id);
                } else {
                    $ins = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (?, ?)");
                    $ins->execute([$__id, $text]);
                }
            } catch (Throwable $e) {
                update("textbot", "text", $text, "id_text", $__id);
            }
        }
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'] ?? '✅ ذخیره شد.', null, 'HTML');
        if (function_exists('buildBotTextSettingsPanel')) {
            list($__bt_title, $__bt_kb) = buildBotTextSettingsPanel();
            sendmessage($from_id, $__bt_title, $__bt_kb, 'HTML');
        }
        return;
    }
}


//_________________________________________________
if ($text == $textbotlang['Admin']['managepanel']['showpanelbtn']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['status'], 'callback_data' => $panel['status']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['showpaneldec'], $view_Status, 'HTML');
}
if ($datain == "activepanel") {
    update("marzban_panel", "status", "disablepanel", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['status'], 'callback_data' => $panel['status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['managepanel']['offpanel'], $view_Status);
} elseif ($datain == "disablepanel") {
    update("marzban_panel", "status", "activepanel", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['status'], 'callback_data' => $panel['status']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['managepanel']['onpanel'], $view_Status);
}
//_________________________________________________
if ($text == $textbotlang['Admin']['managepanel']['showpaneltestbtn']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['statusTest'], 'callback_data' => $panel['statusTest']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['showpaneldec'], $view_Status, 'HTML');
}
if ($datain == "ontestshowpanel") {
    update("marzban_panel", "statusTest", "offtestshowpanel", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['statusTest'], 'callback_data' => $panel['statusTest']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['managepanel']['offpanel'], $view_Status);
} elseif ($datain == "offtestshowpanel") {
    update("marzban_panel", "statusTest", "ontestshowpanel", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $view_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['statusTest'], 'callback_data' => $panel['statusTest']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['managepanel']['onpanel'], $view_Status);
}
//_________________________________________________
elseif (preg_match('/banuserlist_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userblock = select("user", "*", "id", $iduser, "select");
    global $admin_ids;
    if (in_array(strval($iduser), array_map('strval', (array)($admin_ids ?? [])), true)) {
        sendmessage($from_id, "❌ امکان مسدود کردن ادمین وجود ندارد.", $backadmin, 'HTML');
        return;
    }
    if ($userblock['User_Status'] == "block") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['BlockedUser'], $backadmin, 'HTML');
        return;
    }
    // نسخه 2.4.0: اول دلیل، بعد مسدود (انصراف = مسدود نمیشود)
    update("user", "Processing_value", $iduser, "id", $from_id);
    sendmessage($from_id, ($textbotlang['Admin']['ManageUser']['BlockUser'] ?? 'دلیل مسدودی را بفرستید:') . "\n\nℹ️ کاربر هنوز مسدود نشده؛ بعد از فرستادن دلیل مسدود میشود. برای دلیل پیشفرض <code>-</code> بفرستید. با «بازگشت» لغو میشود.", $backadmin, 'HTML');
    step('adddecriptionblock', $from_id);
} elseif ($user['step'] == "adddecriptionblock") {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        sendmessage($from_id, "❌ مسدود کردن لغو شد. کاربر مسدود نشد.", $keyboardadmin, 'HTML');
        return;
    }
    $blocked_id = $user['Processing_value'];
    if (in_array(strval($blocked_id), array_map('strval', (array)($admin_ids ?? [])), true) || !is_array(select("user", "*", "id", $blocked_id, "select"))) {
        step('home', $from_id);
        sendmessage($from_id, "❌ این کاربر قابل مسدود کردن نیست.", $keyboardadmin, 'HTML');
        return;
    }
    $reason_text = is_string($text) ? trim($text) : '';
    if ($reason_text === '' || $reason_text === '-') {
        $reason_text = 'مسدود توسط مدیریت';
    }
    if (mb_strlen($reason_text) > 500) {
        $reason_text = mb_substr($reason_text, 0, 500);
    }
    update("user", "User_Status", "block", "id", $blocked_id);
    update("user", "description_blocking", $reason_text, "id", $blocked_id);
    $__blk_id = htmlspecialchars(strval($blocked_id), ENT_QUOTES, 'UTF-8');
    $__blk_reason = htmlspecialchars($reason_text, ENT_QUOTES, 'UTF-8');
    $__blk_msg = ($textbotlang['Admin']['ManageUser']['DescriptionBlock'] ?? '✅ کاربر مسدود شد')
        . "\n🆔 کاربر: <code>{$__blk_id}</code>"
        . "\n📝 دلیل: {$__blk_reason}";
    sendmessage($from_id, $__blk_msg, $keyboardadmin, 'HTML');
    // به کاربر پیام فوری ارسال نمی‌شود؛ وقتی خودش با ربات کار کند پیام مسدودی + پشتیبانی می‌بیند
    $bu = select("user", "*", "id", $blocked_id, "select");
    $bu_name = is_array($bu) ? ($bu['username'] ?? '-') : '-';
    if (function_exists('notifyUserBlocked')) {
        notifyUserBlocked($blocked_id, $bu_name, $reason_text, 'admin', $from_id);
    } elseif (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['block_user'])) {
        $rep = sprintf(
            $textbotlang['Admin']['Report']['block_user'],
            $from_id,
            $blocked_id,
            $bu_name,
            $reason_text
        );
        sendChannelReport('rpt_block', $rep);
    }
    step('home', $from_id);
} elseif (preg_match('/unbanuserr_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userunblock = select("user", "*", "id", $iduser, "select");
    if ($userunblock['User_Status'] == "Active") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['UserNotBlock'], $backadmin, 'HTML');
        return;
    }
    update("user", "User_Status", "Active", "id", $iduser);
    update("user", "description_blocking", "", "id", $iduser);
    update("user", "message_count", "0", "id", $iduser);
    $__ub_id = htmlspecialchars(strval($iduser), ENT_QUOTES, 'UTF-8');
    $__ub_msg = ($textbotlang['Admin']['ManageUser']['UserUnblocked'] ?? '✅ مسدودی کاربر برداشته شد')
        . "\n🆔 کاربر: <code>{$__ub_id}</code>";
    sendmessage($from_id, $__ub_msg, $keyboardadmin, 'HTML');
    if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['unblock_user'])) {
        $uu_name = is_array($userunblock) ? ($userunblock['username'] ?? '-') : '-';
        $rep = sprintf(
            $textbotlang['Admin']['Report']['unblock_user'],
            $from_id,
            $iduser,
            $uu_name
        );
        sendChannelReport('rpt_unblock', $rep);
    }
    step('home', $from_id);
}
//_________________________________________________
elseif ($text == $textbotlang['Admin']['changetext']['ruletext']) {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ChangeTextGet'] . $datatextbot['text_roll'], $backadmin, 'HTML');
    step('text_roll', $from_id);
} elseif ($user['step'] == "text_roll") {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SaveText'], $textbot, 'HTML');
    update("textbot", "text", $text, "id_text", "text_roll");
    step('home', $from_id);
}
//_________________________________________________

# ——— بخش آموزش/Help حذف شده ———
if (isset($textbotlang['Admin']['Help']['addhelp']) && (
    $text == ($textbotlang['Admin']['Help']['addhelp'] ?? '') ||
    $text == ($textbotlang['Admin']['Help']['removehelpbtn'] ?? '') ||
    $text == ($textbotlang['Admin']['Help']['edithelp'] ?? '') ||
    $text == ($textbotlang['Admin']['Help']['change']['name'] ?? '') ||
    $text == ($textbotlang['Admin']['Help']['change']['dec'] ?? '') ||
    $text == ($textbotlang['Admin']['Help']['change']['editmedia'] ?? '')
)) {
    sendmessage($from_id, "این بخش حذف شده است.", $keyboardadmin, 'HTML');
    return;
}

if ($text == $textbotlang['Admin']['keyboardadmin']['user_services'] || $datain === 'nav_user') {
    $__tx = function_exists('buildAdminUserServicesText') ? buildAdminUserServicesText() : '👤 خدمات کاربر';
    $__kb = function_exists('buildAdminUserServicesInlineKeyboard') ? buildAdminUserServicesInlineKeyboard() : $User_Services;
    if ($datain === 'nav_user' && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
    } else {
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    }
}

# رسیدهای تایید نشده (waiting)
if ($text == ($textbotlang['Admin']['ManageUser']['pending_receipts'] ?? '___')) {
    // نسخه 2.4.2: همان پیام «در حال بررسی» به نتیجه تبدیل میشود
    $__pm = sendmessage($from_id, "⏳ در حال بررسی رسیدهای در انتظار تأیید…", null, 'HTML');
    $res = function_exists('resendWaitingPaymentReceipts')
        ? resendWaitingPaymentReceipts($from_id, 40)
        : ['ok' => false, 'count' => 0, 'msg' => 'تابع موجود نیست'];
    $msg = strval($res['msg'] ?? '');
    if ($msg === '') {
        $msg = intval($res['count'] ?? 0) === 0 ? 'هیچ رسید در انتظار تأییدی نیست.' : '—';
    }
    $__kb_r = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به مدیریت کاربران', 'callback_data' => 'nav_user']]]], JSON_UNESCAPED_UNICODE);
    $__mid_r = is_array($__pm) ? intval($__pm['result']['message_id'] ?? 0) : 0;
    if ($__mid_r > 0) {
        Editmessagetext($from_id, $__mid_r, $msg, $__kb_r);
    } else {
        sendmessage($from_id, $msg, $__kb_r, 'HTML');
    }
}



#----------- list blocked users ------------#
if ($text == ($textbotlang['Admin']['ManageUser']['list_blocked'] ?? '___')) {
    // نسخه 2.4.2
    list($__tx, $__kb) = botBlockedListPage(0);
    sendmessage($from_id, $__tx, $__kb, 'HTML');
}
#----------- users with balance > 1 toman ------------#
if ($text == ($textbotlang['Admin']['ManageUser']['list_balance_gt1'] ?? '💰 موجودی بالای ۵۰۰ هزار')) {
    global $pdo;
    $limit = 80;
    try {
        $stmt = $pdo->query("SELECT id, username, Balance FROM user WHERE CAST(Balance AS SIGNED) >= 500000 ORDER BY CAST(Balance AS SIGNED) DESC LIMIT {$limit}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $rows = [];
    }
    if (empty($rows)) {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['balance_gt1_empty'] ?? 'هیچ کاربری با موجودی بالای ۵۰۰٬۰۰۰ تومان نیست.', $User_Services, 'HTML');
        return;
    }
    $total = count($rows);
    $sum_all = 0;
    try {
        $stc = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(CAST(Balance AS SIGNED)),0) AS s FROM user WHERE CAST(Balance AS SIGNED) >= 500000");
        $rc = $stc ? $stc->fetch(PDO::FETCH_ASSOC) : null;
        if ($rc) {
            $total = intval($rc['c'] ?? $total);
            $sum_all = intval($rc['s'] ?? 0);
        }
    } catch (Exception $e) {
        foreach ($rows as $r) {
            $sum_all += intval($r['Balance'] ?? 0);
        }
    }
    $lines = [];
    foreach ($rows as $r) {
        $uid = $r['id'] ?? '-';
        $un = trim(strval($r['username'] ?? ''));
        if ($un === '') {
            $un = '-';
        }
        $bal = number_format(intval($r['Balance'] ?? 0));
        $lines[] = "🆔 <code>{$uid}</code> | @{$un}\n💰 {$bal} تومان";
    }
    $note = ($total > $limit) ? " (نمایش {$limit} نفر با بیشترین موجودی)" : "";
    $body = implode("\n\n", $lines);
    $tpl = $textbotlang['Admin']['ManageUser']['balance_gt1_header']
        ?? "💰 <b>کاربران با موجودی بالای ۵۰۰ هزار تومان</b>\nتعداد: <b>%s</b> | جمع موجودی: <b>%s</b> تومان%s\n\n%s";
    $msg = sprintf($tpl, number_format($total), number_format($sum_all), $note, $body);
    if (strlen($msg) > 3900) {
        $msg = substr($msg, 0, 3900) . "\n…";
    }
    sendmessage($from_id, $msg, $User_Services, 'HTML');
}

if ($datain == 'unblock_all_users_confirm') {
    $kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ بله، همه آزاد شوند', 'callback_data' => 'unblock_all_users_yes'],
                ['text' => '❌ انصراف', 'callback_data' => 'unblock_all_users_no'],
            ],
        ],
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['ManageUser']['unblock_all_confirm'] ?? 'آیا مطمئن هستید؟', $kb);
}
if ($datain == 'unblock_all_users_no') {
    list($__tx, $__kb) = botBlockedListPage(0);
    Editmessagetext($from_id, $message_id, $__tx, $__kb);
}
# نسخه 2.4.2: صفحهبندی مسدودها و رفع مسدودی سریع
if (in_array($from_id, $admin_ids) && isset($datain) && preg_match('/^blk_(pg|ub)_(\d+)(?:_(\d+))?$/', strval($datain), $__bm)) {
    if ($__bm[1] === 'pg') {
        list($__tx, $__kb) = botBlockedListPage(intval($__bm[2]));
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
        return;
    }
    $__uid_ub = strval($__bm[2]);
    $__pg_ub = intval($__bm[3] ?? 0);
    $__ub = select("user", "*", "id", $__uid_ub, "select");
    if (is_array($__ub) && strval($__ub['User_Status'] ?? '') === 'block') {
        update("user", "User_Status", "Active", "id", $__uid_ub);
        update("user", "description_blocking", "", "id", $__uid_ub);
        update("user", "message_count", "0", "id", $__uid_ub);
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "✅ کاربر {$__uid_ub} آزاد شد", 'show_alert' => false]);
        if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['unblock_user'])) {
            sendChannelReport('rpt_unblock', sprintf($textbotlang['Admin']['Report']['unblock_user'], $from_id, $__uid_ub, strval($__ub['username'] ?? '-')));
        }
    } else {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این کاربر مسدود نیست', 'show_alert' => false]);
    }
    list($__tx, $__kb) = botBlockedListPage($__pg_ub);
    Editmessagetext($from_id, $message_id, $__tx, $__kb);
    return;
}
if ($datain == 'unblock_all_users_yes') {
    global $pdo;
    $n = 0;
    try {
        $stmt = $pdo->prepare("UPDATE user SET User_Status = 'Active', description_blocking = '', message_count = 0 WHERE User_Status = 'block'");
        $stmt->execute();
        $n = $stmt->rowCount();
    } catch (Exception $e) {
        $n = 0;
    }
    Editmessagetext($from_id, $message_id, sprintf($textbotlang['Admin']['ManageUser']['unblock_all_done'] ?? '✅ %s کاربر آزاد شدند.', $n), null);
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_unblock', "🔓 <b>آزادسازی گروهی مسدودها</b>\n👤 توسط ادمین: <code>{$from_id}</code>\n✅ تعداد: <b>{$n}</b>");
    }
}

#-------------------------#
elseif (preg_match('/confirmnumber_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    update("user", "number", "confrim number by admin", "id", $iduser);
    step('home', $iduser);
    // نه منوی خدمات کاربر — فقط کیبورد ادمین
    $__ph_id = htmlspecialchars(strval($iduser), ENT_QUOTES, 'UTF-8');
    $__ph_msg = ($textbotlang['Admin']['phone']['active'] ?? 'شماره موبایل کاربر تایید شده است ✅🎉')
        . "\n🆔 کاربر: <code>{$__ph_id}</code>";
    sendmessage($from_id, $__ph_msg, function_exists('buildAdminKeyboard') ? buildAdminKeyboard() : $keyboardadmin, 'HTML');
}
if ($text == $textbotlang['Admin']['channel']['channelreport']) {
    $ch_id = $setting['Channel_Report'] ?? '-';
    $kb = json_encode(['inline_keyboard' => [
        [['text' => '📡 تنظیم آیدی کانال گزارش', 'callback_data' => 'rpch_setid']],
        [['text' => '⚙️ فعالیت‌های گزارش (روشن/خاموش)', 'callback_data' => 'rpch_toggles']],
        [['text' => '📨 ارسال پیام تست', 'callback_data' => 'rpch_test']],
    ]]);
    sendmessage($from_id, "📣 <b>تنظیمات کانال گزارش</b>

کانال فعلی: <code>{$ch_id}</code>

از دکمه‌های زیر می‌توانید آیدی کانال را تنظیم کنید و مشخص کنید برای کدام فعالیت‌ها پیام ارسال شود.", $kb, 'HTML');
} elseif ($datain == "rpch_setid") {
    // نسخه 2.4.3: اعتبارسنجی + پیام تست واقعی قبل از ذخیره
    sendmessage($from_id, "📡 آیدی کانال گزارش را بفرستید.\nنمونه: <code>@mychannel</code> یا <code>https://t.me/mychannel</code> یا آیدی عددی <code>-1001234567890</code> (برای کانال خصوصی).\n\n⚠️ اول ربات را در کانال ادمین کنید (با اجازه ارسال پیام).", $backadmin, 'HTML');
    step('addchannelid', $from_id);
} elseif ($user['step'] == "addchannelid" && empty($datain)) {
    $__rc = botNormalizeReportChannel($text);
    if ($__rc === '') {
        sendmessage($from_id, "❌ آیدی نامعتبر است.\nنمونه: <code>@mychannel</code> یا <code>-1001234567890</code>\nدوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__r = telegram('sendmessage', ['chat_id' => $__rc, 'text' => ($textbotlang['Admin']['Channel']['TestChannel'] ?? '✅ پیام تست کانال گزارش'), 'parse_mode' => 'HTML']);
    if (!(is_array($__r) && !empty($__r['ok']))) {
        $__d = is_array($__r) ? strval($__r['description'] ?? '') : '';
        sendmessage($from_id, "❌ ارسال پیام تست به این کانال ناموفق بود؛ کانال ذخیره <b>نشد</b>." . ($__d !== '' ? "\n<code>" . htmlspecialchars($__d, ENT_QUOTES, 'UTF-8') . "</code>" : '') . "\n\nربات را در کانال ادمین کنید و دوباره آیدی را بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__rc_id = strval($__r['result']['chat']['id'] ?? $__rc);
    update("setting", "Channel_Report", $__rc_id);
    step('home', $from_id);
    list($__tx, $__kb) = botReportChannelPage("✅ کانال گزارش ثبت شد و پیام تست در آن ارسال شد.");
    sendmessage($from_id, $__tx, $__kb, 'HTML');
} elseif ($datain == "rpch_toggles") {
    if (function_exists('ensureReportChannelSettings')) ensureReportChannelSettings();
    Editmessagetext($from_id, $message_id, "⚙️ <b>فعال/غیرفعال کردن گزارش‌ها</b>

روی هر مورد بزنید تا وضعیتش عوض شود:
✅ = ارسال می‌شود
❌ = ارسال نمی‌شود", function_exists('buildReportChannelKeyboard') ? buildReportChannelKeyboard() : null);
} elseif ($datain == "rpch_test") {
    // نسخه 2.4.3: نتیجه واقعی ارسال
    $st = select("setting", "*", null, null, "select");
    $__ch = is_array($st) ? trim(strval($st['Channel_Report'] ?? '')) : '';
    if ($__ch === '' || $__ch === '0') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'ابتدا آیدی کانال گزارش را تنظیم کنید.', 'show_alert' => true]);
    } else {
        $__r = telegram('sendmessage', ['chat_id' => $__ch, 'text' => ($textbotlang['Admin']['Channel']['TestChannel'] ?? '✅ پیام تست'), 'parse_mode' => 'HTML']);
        if (is_array($__r) && !empty($__r['ok'])) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ پیام تست در کانال ارسال شد.', 'show_alert' => false]);
        } else {
            $__d = is_array($__r) ? strval($__r['description'] ?? '') : 'اتصال به تلگرام';
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ ارسال ناموفق: ' . mb_substr($__d, 0, 150), 'show_alert' => true]);
        }
    }
} elseif ($datain == "rpch_menu") {
    list($__tx, $__kb) = botReportChannelPage();
    Editmessagetext($from_id, $message_id, $__tx, $__kb);
} elseif (preg_match('/^rptoggle_(rpt_[a-z_]+)$/', strval($datain), $m_rt)) {
    $key = $m_rt[1];
    if (function_exists('ensureReportChannelSettings')) ensureReportChannelSettings();
    $cur = function_exists('getPaySettingValue') ? getPaySettingValue($key, '1') : '1';
    $newv = ($cur === '1') ? '0' : '1';
    update("PaySetting", "ValuePay", $newv, "NamePay", $key);
    telegram('answerCallbackQuery', [
        'callback_query_id' => ($update['callback_query']['id'] ?? ''),
        'text' => ($newv === '1' ? 'فعال شد' : 'غیرفعال شد'),
        'show_alert' => false
    ]);
    Editmessagetext($from_id, $message_id, "⚙️ <b>فعال/غیرفعال کردن گزارش‌ها</b>

✅ = ارسال می‌شود
❌ = ارسال نمی‌شود", function_exists('buildReportChannelKeyboard') ? buildReportChannelKeyboard() : null);
}
#-------------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['shop_section'] || $datain === 'nav_shop') {
    $__tx = function_exists('buildAdminShopSectionText') ? buildAdminShopSectionText() : '🏬 بخش فروشگاه';
    $__kb = function_exists('buildAdminShopInlineKeyboard') ? buildAdminShopInlineKeyboard() : $shopkeyboard;
    if ($datain === 'nav_shop' && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
    } else {
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    }
}

#------------------------- مدیریت محصولات اینلاین (فروشگاه) -------------------------#
if (isset($text) && $text === '📦 مدیریت محصولات') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    sendmessage($from_id, function_exists('shopProductListTitle') ? shopProductListTitle() : "🏬 مدیریت محصولات", function_exists('shopProductListKeyboard') ? shopProductListKeyboard() : null, 'HTML');
    return;
}
# عنوان ویرایش قدیمی → همان لیست اینلاین
if (isset($text) && isset($textbotlang['Admin']['Product']['titlebtnedit']) && $text == $textbotlang['Admin']['Product']['titlebtnedit']) {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    sendmessage($from_id, function_exists('shopProductListTitle') ? shopProductListTitle() : "🏬 مدیریت محصولات", function_exists('shopProductListKeyboard') ? shopProductListKeyboard() : null, 'HTML');
    return;
}

if (!isset($datain)) {
    $datain = '';
}

if (in_array($from_id, $admin_ids) && ($datain === 'shop_list' || $datain === 'shop_noop')) {
    if ($datain === 'shop_noop') {
        return;
    }
    Editmessagetext($from_id, $message_id, shopProductListTitle(), shopProductListKeyboard());
    return;
}

if (in_array($from_id, $admin_ids) && preg_match('/^shop_loc_([a-f0-9]{12})$/', strval($datain), $m_loc)) {
    $loc = shopResolveLocationKey($m_loc[1]);
    if ($loc === null) {
        Editmessagetext($from_id, $message_id, "❌ موقعیت یافت نشد.

" . shopProductListTitle(), shopProductListKeyboard());
        return;
    }
    Editmessagetext($from_id, $message_id, shopProductListTitle($loc), shopProductListKeyboard($loc));
    return;
}

if (in_array($from_id, $admin_ids) && $datain === 'shop_add') {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
    step('get_limit', $from_id);
    return;
}

if (in_array($from_id, $admin_ids) && preg_match('/^shop_p_(.+)$/', strval($datain), $m_sp)) {
    $code = $m_sp[1];
    $p = shopGetProductByCode($code);
    if (!$p) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'محصول یافت نشد', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value", strval($p['name_product'] ?? ''), "id", $from_id);
    update("user", "Processing_value_one", strval($p['Location'] ?? ''), "id", $from_id);
    update("user", "Processing_value_tow", $code, "id", $from_id);
    Editmessagetext($from_id, $message_id, shopProductDetailText($p), shopProductDetailKeyboard($code));
    return;
}

# نسخه 2.3.0: تأیید قبل از حذف محصول
if (in_array($from_id, $admin_ids) && preg_match('/^shop_delask_([A-Za-z0-9]+)$/', strval($datain), $m_da)) {
    $p = shopGetProductByCode($m_da[1]);
    if (!$p) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'محصول یافت نشد', 'show_alert' => true]);
        return;
    }
    $__dn = htmlspecialchars(strval($p['name_product'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $__kb_da = json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، حذف شود', 'callback_data' => 'shop_ep_del_' . $m_da[1]]],
        [['text' => '❌ خیر، بازگشت', 'callback_data' => 'shop_p_' . $m_da[1]]],
    ]], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, "⚠️ <b>حذف محصول «{$__dn}»؟</b>\n\nاین کار برگشتپذیر نیست. سرویسهایی که قبلاً با این محصول خریده شدهاند سالم میمانند.\n\n💡 اگر فقط میخواهید موقتاً به کاربران نشان داده نشود، «🙈 مخفی کردن» را بزنید.", $__kb_da);
    return;
}

# نسخه 2.2.9: مخفی کردن / کپی محصول
if (in_array($from_id, $admin_ids) && preg_match('/^shop_(hide|copy)_([A-Za-z0-9]+)$/', strval($datain), $m_hc)) {
    $code = $m_hc[2];
    $p = shopGetProductByCode($code);
    if (!$p) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'محصول یافت نشد', 'show_alert' => true]);
        return;
    }
    if (!function_exists('botEnsureProductHiddenColumn') || !botEnsureProductHiddenColumn()) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ امکان ساخت ستون «مخفی» در دیتابیس نبود. لاگ را چک کنید.', 'show_alert' => true]);
        return;
    }
    global $pdo;
    if ($m_hc[1] === 'hide') {
        $new_h = (intval($p['is_hidden'] ?? 0) === 1) ? 0 : 1;
        try {
            $pdo->prepare("UPDATE product SET is_hidden = ? WHERE code_product = ?")->execute([$new_h, $code]);
        } catch (Throwable $e) {
            error_log('shop hide: ' . $e->getMessage());
        }
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => ($new_h ? '🙈 محصول از کاربران مخفی شد' : '👁 محصول به کاربران نمایش داده میشود'), 'show_alert' => false]);
        $p = shopGetProductByCode($code);
        Editmessagetext($from_id, $message_id, shopProductDetailText($p), shopProductDetailKeyboard($code));
        return;
    }
    // کپی: محصول جدید با همان مشخصات، بهصورت مخفی (تا بعد از ویرایش نمایش داده شود)
    $new_code = '';
    for ($__i = 0; $__i < 15; $__i++) {
        $__c = bin2hex(random_bytes(2));
        if (!shopGetProductByCode($__c)) {
            $new_code = $__c;
            break;
        }
    }
    if ($new_code === '') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ ساخت کد جدید ناموفق بود، دوباره بزنید.', 'show_alert' => true]);
        return;
    }
    try {
        $pdo->prepare("INSERT INTO product (code_product, name_product, price_product, Volume_constraint, Location, Service_time, Category, is_hidden) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")->execute([
            $new_code,
            strval($p['name_product'] ?? '') . ' (کپی)',
            $p['price_product'] ?? null,
            $p['Volume_constraint'] ?? null,
            $p['Location'] ?? null,
            $p['Service_time'] ?? null,
            $p['Category'] ?? ($p['category'] ?? null),
        ]);
    } catch (Throwable $e) {
        error_log('shop copy: ' . $e->getMessage());
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ کپی محصول ناموفق بود.', 'show_alert' => true]);
        return;
    }
    $np = shopGetProductByCode($new_code);
    update("user", "Processing_value", strval($np['name_product'] ?? ''), "id", $from_id);
    update("user", "Processing_value_one", strval($np['Location'] ?? ''), "id", $from_id);
    update("user", "Processing_value_tow", $new_code, "id", $from_id);
    Editmessagetext($from_id, $message_id, "📄 <b>کپی ساخته شد</b> و فعلاً از کاربران مخفی است.\nبعد از ویرایش، «👁 نمایش به کاربران» را بزنید.\n\n" . shopProductDetailText($np), shopProductDetailKeyboard($new_code));
    return;
}

if (in_array($from_id, $admin_ids) && preg_match('/^shop_ep_(price|vol|time|name|cat|del)_(.+)$/', strval($datain), $m_se)) {
    $action = $m_se[1];
    $code = $m_se[2];
    $p = shopGetProductByCode($code);
    if (!$p) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'محصول یافت نشد', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value", strval($p['name_product'] ?? ''), "id", $from_id);
    update("user", "Processing_value_one", strval($p['Location'] ?? ''), "id", $from_id);
    update("user", "Processing_value_tow", $code, "id", $from_id);

    if ($action === 'del') {
        global $pdo;
        try {
            $pdo->prepare("DELETE FROM product WHERE code_product = ?")->execute([$code]);
            botAdminActionReport('حذف محصول', '📦 ' . htmlspecialchars(strval($p['name_product'] ?? '-'), ENT_QUOTES, 'UTF-8') . ' | 💵 ' . number_format(intval($p['price_product'] ?? 0)) . ' تومان | 📍 ' . htmlspecialchars(strval($p['Location'] ?? '-'), ENT_QUOTES, 'UTF-8'));
        } catch (Throwable $e) {
            error_log('shop del: ' . $e->getMessage());
        }
        $del_loc = strval($p['Location'] ?? '');
        if ($del_loc === '') {
            $del_loc = '(بدون موقعیت)';
        }
        Editmessagetext($from_id, $message_id, "🗑 محصول حذف شد.

" . shopProductListTitle($del_loc), shopProductListKeyboard($del_loc));
        return;
    }

    $map = [
        'price' => ['step' => 'shop_change_price', 'msg' => $textbotlang['Admin']['Product']['sendnewprice'] ?? 'قیمت جدید را به تومان بفرستید:'],
        'vol' => ['step' => 'shop_change_vol', 'msg' => $textbotlang['Admin']['Product']['sendnewvolume'] ?? 'حجم جدید را به گیگ بفرستید:'],
        'time' => ['step' => 'shop_change_time', 'msg' => $textbotlang['Admin']['Product']['NewTime'] ?? 'زمان جدید را به روز بفرستید:'],
        'name' => ['step' => 'shop_change_name', 'msg' => $textbotlang['Admin']['Product']['sendnewname'] ?? 'نام جدید محصول را بفرستید:'],
        'cat' => ['step' => 'shop_change_cat', 'msg' => $textbotlang['Admin']['Product']['Getcategory'] ?? 'دسته را انتخاب کنید:'],
    ];
    if (!isset($map[$action])) {
        return;
    }
    step($map[$action]['step'], $from_id);
    if ($action === 'cat') {
        sendmessage($from_id, $map[$action]['msg'], function_exists('KeyboardCategory') ? KeyboardCategory() : $backadmin, 'HTML');
    } else {
        sendmessage($from_id, $map[$action]['msg'], $backadmin, 'HTML');
    }
    return;
}

# --- step handlers برای ویرایش از لیست اینلاین ---
$__shop_step = strval($user['step'] ?? '');
if (in_array($from_id, $admin_ids) && $__shop_step === 'shop_change_price' && !empty($text) && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) { step('home', $from_id); return; }
    if (!ctype_digit(preg_replace('/\D/', '', $text)) && !ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'] ?? 'قیمت نامعتبر', $backadmin, 'HTML');
        return;
    }
    $price = intval(preg_replace('/\D/', '', function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text)));
    $code = strval($user['Processing_value_tow'] ?? '');
    $name = strval($user['Processing_value'] ?? '');
    $loc = strval($user['Processing_value_one'] ?? '');
    global $pdo;
    if ($code !== '') {
        $pdo->prepare("UPDATE product SET price_product = ? WHERE code_product = ?")->execute([$price, $code]);
    } else {
        $pdo->prepare("UPDATE product SET price_product = ? WHERE name_product = ? AND Location = ?")->execute([$price, $name, $loc]);
    }
    step('home', $from_id);
    $p = $code !== '' ? shopGetProductByCode($code) : null;
    $kb = shopProductListKeyboard();
    sendmessage($from_id, "✅ قیمت به‌روزرسانی شد: <b>" . number_format($price) . "</b> تومان

" . shopProductListTitle($loc !== '' ? $loc : null), shopProductListKeyboard($loc !== '' ? $loc : null), 'HTML');
    return;
}
if (in_array($from_id, $admin_ids) && $__shop_step === 'shop_change_vol' && !empty($text) && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) { step('home', $from_id); return; }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'] ?? 'حجم نامعتبر', $backadmin, 'HTML');
        return;
    }
    $code = strval($user['Processing_value_tow'] ?? '');
    $name = strval($user['Processing_value'] ?? '');
    $loc = strval($user['Processing_value_one'] ?? '');
    global $pdo;
    if ($code !== '') {
        $pdo->prepare("UPDATE product SET Volume_constraint = ? WHERE code_product = ?")->execute([$text, $code]);
    } else {
        $pdo->prepare("UPDATE product SET Volume_constraint = ? WHERE name_product = ? AND Location = ?")->execute([$text, $name, $loc]);
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ حجم به‌روزرسانی شد: <b>{$text}</b> گیگ

" . shopProductListTitle($loc !== '' ? $loc : null), shopProductListKeyboard($loc !== '' ? $loc : null), 'HTML');
    return;
}
if (in_array($from_id, $admin_ids) && $__shop_step === 'shop_change_time' && !empty($text) && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) { step('home', $from_id); return; }
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'] ?? 'زمان نامعتبر', $backadmin, 'HTML');
        return;
    }
    $code = strval($user['Processing_value_tow'] ?? '');
    $name = strval($user['Processing_value'] ?? '');
    $loc = strval($user['Processing_value_one'] ?? '');
    global $pdo;
    if ($code !== '') {
        $pdo->prepare("UPDATE product SET Service_time = ? WHERE code_product = ?")->execute([$text, $code]);
    } else {
        $pdo->prepare("UPDATE product SET Service_time = ? WHERE name_product = ? AND Location = ?")->execute([$text, $name, $loc]);
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ زمان به‌روزرسانی شد: <b>{$text}</b> روز

" . shopProductListTitle($loc !== '' ? $loc : null), shopProductListKeyboard($loc !== '' ? $loc : null), 'HTML');
    return;
}
if (in_array($from_id, $admin_ids) && $__shop_step === 'shop_change_name' && !empty($text) && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) { step('home', $from_id); return; }
    $code = strval($user['Processing_value_tow'] ?? '');
    $oldname = strval($user['Processing_value'] ?? '');
    $loc = strval($user['Processing_value_one'] ?? '');
    global $pdo;
    if ($code !== '') {
        $pdo->prepare("UPDATE product SET name_product = ? WHERE code_product = ?")->execute([$text, $code]);
    } else {
        $pdo->prepare("UPDATE product SET name_product = ? WHERE name_product = ? AND Location = ?")->execute([$text, $oldname, $loc]);
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ نام به‌روزرسانی شد: <b>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</b>\n\n🏬 <b>مدیریت محصولات</b>", shopProductListKeyboard(), 'HTML');
    return;
}
if (in_array($from_id, $admin_ids) && $__shop_step === 'shop_change_cat' && !empty($text) && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) { step('home', $from_id); return; }
    $category = select("category", "*", "remark", $text, "select");
    if ($category == false) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['invalidcategory'] ?? 'دسته نامعتبر', $backadmin, 'HTML');
        return;
    }
    $code = strval($user['Processing_value_tow'] ?? '');
    $name = strval($user['Processing_value'] ?? '');
    $loc = strval($user['Processing_value_one'] ?? '');
    $cid = $category['id'];
    global $pdo;
    if ($code !== '') {
        $pdo->prepare("UPDATE product SET Category = ? WHERE code_product = ?")->execute([$cid, $code]);
    } else {
        $pdo->prepare("UPDATE product SET Category = ? WHERE name_product = ? AND Location = ?")->execute([$cid, $name, $loc]);
    }
    step('home', $from_id);
    sendmessage($from_id, "✅ دسته به‌روزرسانی شد.

" . shopProductListTitle(), shopProductListKeyboard(), 'HTML');
    return;
}

# بعد از ذخیره محصول جدید → لیست اینلاین
 elseif ($text == $textbotlang['Admin']['Product']['addproduct']) {
    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpaneladmin'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
    step('get_limit', $from_id);
} elseif ($user['step'] == "get_limit") {
    $randomString = bin2hex(random_bytes(2));
    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product, code_product) VALUES (?, ?)");
    $stmt->bindParam(1, $text);
    $stmt->bindParam(2, $randomString);

    $stmt->execute();
    update("user", "Processing_value", $randomString, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
    step('get_location', $from_id);
} elseif ($user['step'] == "get_location") {
    update("product", "Location", $text, "code_product", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Product']['Getcategory'], KeyboardCategory(), 'HTML');
    step('get_category', $from_id);
} elseif ($user['step'] == "get_category") {
    $category = select("category", "*", "remark", $text, "select");
    if ($category == false) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['invalidcategory'], $backadmin, 'HTML');
        return;
    }
    update("product", "category", $category['id'], "code_product", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
    step('get_time', $from_id);
} elseif ($user['step'] == "get_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    update("product", "Volume_constraint", $text, "code_product", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
    step('get_price', $from_id);
} elseif ($user['step'] == "get_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    update("product", "Service_time", $text, "code_product", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
    step('endstep', $from_id);
} elseif ($user['step'] == "endstep") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    update("product", "price_product", $text, "code_product", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Product']['SaveProduct'], $shopkeyboard, 'HTML');
    if (function_exists('shopProductListKeyboard')) {
        sendmessage($from_id, "🏬 <b>مدیریت محصولات</b>", shopProductListKeyboard(), 'HTML');
    }
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['admin_section'] || $datain === 'nav_admin') {
    if (function_exists('adminAdminsListKeyboard')) {
        list($__tx, $__kb) = adminAdminsListKeyboard($from_id);
        if ($datain === 'nav_admin' && !empty($message_id)) {
            Editmessagetext($from_id, $message_id, $__tx, $__kb);
        } else {
            sendmessage($from_id, $__tx, $__kb, 'HTML');
        }
    } else {
        $kb_admin_sec = function_exists('getAdminSectionPanelKeyboard') ? getAdminSectionPanelKeyboard($from_id) : $admin_section_panel;
        sendmessage($from_id, $textbotlang['users']['selectoption'], $kb_admin_sec, 'HTML');
    }
}
#-------------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['settings'] || $datain === 'nav_settings') {
    $__tx = function_exists('buildAdminSettingsSectionText') ? buildAdminSettingsSectionText() : '⚙️ تنظیمات';
    $__kb = function_exists('buildAdminSettingsInlineKeyboard') ? buildAdminSettingsInlineKeyboard() : $setting_panel;
    if ($datain === 'nav_settings' && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__tx, $__kb);
    } else {
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    }
}


#----------------[ پشتیبان‌گیری دیتابیس ]------------------#
if ($text == ($textbotlang['Admin']['keyboardadmin']['db_backup'] ?? '💾 پشتیبان‌گیری')) {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    if (function_exists('ensureDatabaseBackupSettings')) {
        ensureDatabaseBackupSettings();
    }
    $txt = function_exists('backupSettingsText') ? backupSettingsText() : '💾 پشتیبان‌گیری';
    $kb = function_exists('buildBackupSettingsKeyboard') ? buildBackupSettingsKeyboard() : null;
    sendmessage($from_id, $txt, $kb, 'HTML');
    return;
}

if ($datain === 'backup_back') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    Editmessagetext($from_id, $message_id,
        function_exists('buildAdminSettingsSectionText') ? buildAdminSettingsSectionText() : '⚙️ تنظیمات',
        function_exists('buildAdminSettingsInlineKeyboard') ? buildAdminSettingsInlineKeyboard() : json_encode(['inline_keyboard' => []]));
    return;
}

if ($datain === 'backup_now') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    Editmessagetext($from_id, $message_id, "⏳ در حال تهیه بک‌آپ...", json_encode(['inline_keyboard' => []]));
    $bres = function_exists('runDatabaseBackupAndSend')
        ? runDatabaseBackupAndSend($from_id, true)
        : (function_exists('runManualDatabaseBackupAndSend') ? runManualDatabaseBackupAndSend($from_id) : ['ok' => false, 'message' => 'تابع موجود نیست']);
    $msg = !empty($bres['ok']) ? ($bres['message'] ?? '✅ انجام شد') : ('❌ ' . strval($bres['message'] ?? 'خطا'));
    // نسخه 2.4.3: نتیجه در همان پیام
    Editmessagetext($from_id, $message_id, $msg . "\n\n" . (function_exists('backupSettingsText') ? backupSettingsText() : ''), function_exists('buildBackupSettingsKeyboard') ? buildBackupSettingsKeyboard() : json_encode(['inline_keyboard' => []]));
    return;
}

if ($datain === 'backup_tog_admin') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    ensureDatabaseBackupSettings();
    $cur = getPaySettingValue('backup_send_admin', '0') === '1';
    update('PaySetting', 'ValuePay', $cur ? '0' : '1', 'NamePay', 'backup_send_admin');
    Editmessagetext($from_id, $message_id, backupSettingsText(), buildBackupSettingsKeyboard());
    return;
}

if ($datain === 'backup_tog_channel') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    ensureDatabaseBackupSettings();
    $cur = getPaySettingValue('backup_send_channel', '0') === '1';
    update('PaySetting', 'ValuePay', $cur ? '0' : '1', 'NamePay', 'backup_send_channel');
    Editmessagetext($from_id, $message_id, backupSettingsText(), buildBackupSettingsKeyboard());
    return;
}

if ($datain === 'backup_tog_cron') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    ensureDatabaseBackupSettings();
    $cur = getPaySettingValue('backup_cron_enabled', '0') === '1';
    $new = $cur ? '0' : '1';
    update('PaySetting', 'ValuePay', $new, 'NamePay', 'backup_cron_enabled');
    $cron_msg = '';
    if (function_exists('syncBackupCron')) {
        $sr = syncBackupCron($new === '1');
        $cron_msg = "\n\n" . strval($sr['message'] ?? '');
    }
    Editmessagetext($from_id, $message_id, backupSettingsText() . $cron_msg, buildBackupSettingsKeyboard());
    return;
}

if ($datain === 'backup_set_hours') {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    step('backup_set_hours', $from_id);
    sendmessage($from_id, "تعداد ساعت بین هر بک‌آپ خودکار را بفرستید (۱ تا ۱۶۸).\nمثال: <code>24</code> = هر روز", $backadmin, 'HTML');
    return;
}

if (($user['step'] ?? '') === 'backup_set_hours' && !empty($text) && empty($datain)) {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '') || $text === ($textbotlang['users']['backhome'] ?? '')) {
        step('home', $from_id);
        sendmessage($from_id, function_exists('backupSettingsText') ? backupSettingsText() : '💾', function_exists('buildBackupSettingsKeyboard') ? buildBackupSettingsKeyboard() : null, 'HTML');
        return;
    }
    $h = intval(preg_replace('/\D/', '', $text));
    if ($h < 1 || $h > 168) {
        sendmessage($from_id, "❌ عدد بین ۱ تا ۱۶۸ وارد کنید.", $backadmin, 'HTML');
        return;
    }
    ensureDatabaseBackupSettings();
    update('PaySetting', 'ValuePay', strval($h), 'NamePay', 'backup_cron_hours');
    step('home', $from_id);
    $extra = '';
    if (getPaySettingValue('backup_cron_enabled', '0') === '1' && function_exists('syncBackupCron')) {
        $sr = syncBackupCron(true);
        $extra = "\n" . strval($sr['message'] ?? '');
    }
    sendmessage($from_id, "✅ فاصله بک‌آپ: هر {$h} ساعت." . $extra, $setting_panel, 'HTML');
    sendmessage($from_id, backupSettingsText(), buildBackupSettingsKeyboard(), 'HTML');
    return;
}


#----------------[ آپدیت ربات از گیتهاب ]------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['bot_update']) {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    if (!function_exists('isMainBotAdmin') || !isMainBotAdmin($from_id)) {
        sendmessage($from_id, $textbotlang['Admin']['bot_update']['only_admin'], $setting_panel, 'HTML');
        return;
    }
    $kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['bot_update']['yes'], 'callback_data' => 'bot_update_yes'],
                ['text' => $textbotlang['Admin']['bot_update']['no'], 'callback_data' => 'bot_update_no'],
            ],
        ],
    ]);
    sendmessage($from_id, $textbotlang['Admin']['bot_update']['confirm'], $kb, 'HTML');
}
if ($datain == "bot_update_no") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['bot_update']['cancelled'], json_encode(['inline_keyboard' => []]));
}
if ($datain == "bot_update_yes") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    if (!function_exists('isMainBotAdmin') || !isMainBotAdmin($from_id)) {
        Editmessagetext($from_id, $message_id, $textbotlang['Admin']['bot_update']['only_admin'], json_encode(['inline_keyboard' => []]));
        return;
    }
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['bot_update']['start'], json_encode(['inline_keyboard' => []]));
    if (!function_exists('runBotSelfUpdate')) {
        sendmessage($from_id, sprintf($textbotlang['Admin']['bot_update']['fail'], 'تابع آپدیت موجود نیست.'), $setting_panel, 'HTML');
        return;
    }
    $result = runBotSelfUpdate();
    if (!empty($result['ok'])) {
        $n = isset($result['files']) ? intval($result['files']) : 0;
        $msg = sprintf($textbotlang['Admin']['bot_update']['success'], $n);
        if (!empty($result['backup'])) {
            $msg .= "\n\n📦 بک‌آپ: <code>" . htmlspecialchars(strval($result['backup']), ENT_QUOTES, 'UTF-8') . "</code>";
        }
        if (!empty($result['failed']) && intval($result['failed']) > 0) {
            $msg .= "\n\n⚠️ تعداد ناموفق: " . intval($result['failed']);
            if (!empty($result['message']) && $result['message'] !== 'ok') {
                $msg .= "\n" . htmlspecialchars(strval($result['message']), ENT_QUOTES, 'UTF-8');
            }
        }
        sendmessage($from_id, $msg, $setting_panel, 'HTML');
    } else {
        $err = isset($result['message']) ? $result['message'] : 'نامشخص';
        sendmessage($from_id, sprintf($textbotlang['Admin']['bot_update']['fail'], $err), $setting_panel, 'HTML');
    }
}


#-------------------------#
if ($text == $textbotlang['Admin']['keyboardadmin']['test_account_settings']) {
    if (function_exists('adminUsertestPanel')) {
        list($__tx, $__kb) = adminUsertestPanel();
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard_usertest, 'HTML');
    }
}

if ($text == $textbotlang['Admin']['Usertest']['reset_all_limits']) {
    list($__tx, $__kb) = botUsertestResetConfirm();
    sendmessage($from_id, $__tx, $__kb, 'HTML');
}

#-------------------------#
if (preg_match('/Confirm_pay_(\w+)/', $datain, $dataget)) {
    $order_id = $dataget[1];
    $Payment_report = select("Payment_report", "*", "id_order", $order_id, "select");
    if (!$Payment_report || !is_array($Payment_report)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'سفارش یافت نشد',
            'show_alert' => true,
            'cache_time' => 5,
        ]);
        return;
    }
    if (($Payment_report['payment_Status'] ?? '') == "paid" || ($Payment_report['payment_Status'] ?? '') == "reject") {
        telegram(
            'answerCallbackQuery',
            array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                'show_alert' => true,
                'cache_time' => 5,
            )
        );
        return;
    }
    // قفل اتمیک: فقط اگر هنوز waiting باشد paid می‌شود — جلوگیری از تأیید دوبار
    global $pdo;
    try {
        $stmt_lock = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'waiting'");
        $stmt_lock->execute([$order_id]);
        if ($stmt_lock->rowCount() < 1) {
            telegram(
                'answerCallbackQuery',
                array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 5,
                )
            );
            return;
        }
    } catch (Exception $e) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'خطا در قفل سفارش',
            'show_alert' => true,
        ]);
        return;
    }
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    $GLOBALS['defer_post_payment_reports'] = true;
    $GLOBALS['deferred_channel_reports'] = [];
    $GLOBALS['payment_user_notified'] = false;
    DirectPayment($order_id);
    // فقط اگر DirectPayment پیامی نفرستاده بود
    if (function_exists('notifyUserReceiptAccepted')) {
        notifyUserReceiptAccepted(
            $Payment_report['id_user'],
            $order_id,
            intval($Payment_report['price'] ?? 0)
        );
    }
    $id_user_pay = $Payment_report['id_user'];
    $keyboard_accept = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['moeny']['paymentaccepted'], 'callback_data' => "none"],
            ],
            [
                ['text' => '👤 اطلاعات کاربر', 'callback_data' => "userinfo_pay_{$id_user_pay}"],
            ],
        ]
    ]);
    telegram('editMessageCaption',[
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'caption' => $caption,
        'reply_markup' => $keyboard_accept
    ]);
    // نسخه 2.3.3: نسخه همین رسید نزد ادمینهای دیگر هم «تأیید شد» شود
    if (function_exists('botMarkReceiptMsgs')) {
        botMarkReceiptMsgs($order_id, '✅ تأیید شد (ادمین ' . $from_id . ')', $from_id, $message_id, intval($id_user_pay));
    }
    if (is_array($Balance_id)) {
        update("user", "Processing_value", "0", "id", $Balance_id['id']);
        update("user", "Processing_value_one", "0", "id", $Balance_id['id']);
        update("user", "Processing_value_tow", "0", "id", $Balance_id['id']);
    }
    if (function_exists('sendChannelReport')) {
        $u_pay = select("user", "*", "id", $Payment_report['id_user'], "select");
        $bal_after = number_format(intval(str_replace(',', '', strval($u_pay['Balance'] ?? 0))));
        $uname_tg = $u_pay['username'] ?? '-';
        $pd = function_exists('describePaymentReport') ? describePaymentReport($Payment_report) : [
            'method_label' => '💳 کارت‌به‌کارت',
            'type_label' => 'افزایش موجودی',
            'pay_fmt' => number_format(intval($Payment_report['price'])),
            'credit_fmt' => number_format(intval($Payment_report['price'])),
        ];
        if (!empty($pd['is_package'])) {
            $tpl = $textbotlang['Admin']['Report']['acceptcartresid_pkg'] ?? $textbotlang['Admin']['Report']['acceptcartresid'];
            sendChannelReport('rpt_cart_accept', sprintf(
                $tpl,
                $from_id,
                $Payment_report['id_user'],
                $uname_tg,
                $pd['method_label'],
                $pd['type_label'],
                $pd['pay_fmt'],
                $pd['credit_fmt'],
                $bal_after,
                $Payment_report['id_order']
            ));
        } else {
            sendChannelReport('rpt_cart_accept', sprintf(
                $textbotlang['Admin']['Report']['acceptcartresid'],
                $from_id,
                $Payment_report['id_user'],
                $uname_tg,
                $pd['method_label'],
                $pd['type_label'],
                $pd['pay_fmt'],
                $bal_after,
                $Payment_report['id_order']
            ));
        }
    }
    // بعد از پیام تأیید رسید، گزارش خرید/تمدید ارسال شود
    if (function_exists('flushDeferredChannelReports')) {
        flushDeferredChannelReports();
    } else {
        $GLOBALS['defer_post_payment_reports'] = false;
    }
}
#-------------------------#
# نسخه 2.3.2: انتخاب دلیل آماده رد رسید
if (in_array($from_id, $admin_ids) && isset($datain) && preg_match('/^rjr_(\d+)$/', strval($datain), $__m_rjr)) {
    $__rr_all = function_exists('botRejectReasons') ? botRejectReasons() : [];
    $__rr_n = intval($__m_rjr[1]);
    if (strval($user['step'] ?? '') !== 'reject-dec' || !isset($__rr_all[$__rr_n])) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'این رسید قبلاً بررسی شده یا دلیل نامعتبر است.', 'show_alert' => true]);
        return;
    }
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ ثبت شد', 'show_alert' => false]);
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, "✅ دلیل انتخاب شد:\n" . htmlspecialchars($__rr_all[$__rr_n], ENT_QUOTES, 'UTF-8'), json_encode(['inline_keyboard' => []]));
    }
    $text = $__rr_all[$__rr_n];
    $datain = '';
}
if (preg_match('/reject_pay_(\w+)/', $datain, $datagetr)) {
    $id_order = $datagetr[1];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if (!$Payment_report || !is_array($Payment_report)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'سفارش یافت نشد',
            'show_alert' => true,
        ]);
        return;
    }
    update("user", "Processing_value", $Payment_report['id_user'], "id", $from_id);
    update("user", "Processing_value_one", $id_order, "id", $from_id);
    if (($Payment_report['payment_Status'] ?? '') == "reject" || ($Payment_report['payment_Status'] ?? '') == "paid") {
        telegram(
            'answerCallbackQuery',
            array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                'show_alert' => true,
                'cache_time' => 5,
            )
        );
        return;
    }
    global $pdo;
    try {
        $stmt_rj = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'reject' WHERE id_order = ? AND payment_Status = 'waiting'");
        $stmt_rj->execute([$id_order]);
        if ($stmt_rj->rowCount() < 1) {
            telegram(
                'answerCallbackQuery',
                array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['Payment']['reviewedpayment'],
                    'show_alert' => true,
                    'cache_time' => 5,
                )
            );
            return;
        }
    } catch (Exception $e) {
        return;
    }
    if (!empty($message_id)) {
        update("user", "Processing_value_tow", strval($message_id), "id", $from_id);
    }
    // نسخه 2.3.2: دلیلهای آماده (یا تایپ دلیل دلخواه)
    $__rr_kb = ['inline_keyboard' => []];
    foreach ((function_exists('botRejectReasons') ? botRejectReasons() : []) as $__rr_i => $__rr_t) {
        $__rr_kb['inline_keyboard'][] = [['text' => $__rr_t, 'callback_data' => 'rjr_' . $__rr_i]];
    }
    sendmessage($from_id, "❌ <b>دلیل رد رسید</b>\n\nیکی از دلیلهای زیر را بزنید، یا دلیل دلخواه را تایپ کنید:", $__rr_kb['inline_keyboard'] ? json_encode($__rr_kb, JSON_UNESCAPED_UNICODE) : $backadmin, 'HTML');
    step('reject-dec', $from_id);
    // رسید معمولاً عکس است — text خالی است و editMessageText خطای "message text is empty" می‌دهد
    $__rj_note = "❌ <b>در حال رد پرداخت</b>\nکد: <code>" . htmlspecialchars(strval($id_order), ENT_QUOTES, 'UTF-8') . "</code>\nمنتظر دلیل رد...";
    $__cb_msg = $update['callback_query']['message'] ?? [];
    $__empty_kb = json_encode(['inline_keyboard' => []]);
    if (!empty($__cb_msg['photo']) || (isset($__cb_msg['caption']) && strval($__cb_msg['caption']) !== '')) {
        $__cap = strval($__cb_msg['caption'] ?? $caption ?? '');
        $__new_cap = ($__cap !== '' ? $__cap . "\n\n" : '') . $__rj_note;
        telegram('editMessageCaption', [
            'chat_id' => $from_id,
            'message_id' => $message_id,
            'caption' => $__new_cap,
            'parse_mode' => 'HTML',
            'reply_markup' => $__empty_kb,
        ]);
    } else {
        $__base = trim(strval($text_callback ?? ''));
        $__new_txt = ($__base !== '' ? $__base . "\n\n" : '') . $__rj_note;
        if ($__new_txt !== '') {
            Editmessagetext($from_id, $message_id, $__new_txt, $__empty_kb);
        } else {
            // فقط دکمه‌ها را بردار
            telegram('editMessageReplyMarkup', [
                'chat_id' => $from_id,
                'message_id' => $message_id,
                'reply_markup' => $__empty_kb,
            ]);
        }
    }
} elseif ($user['step'] == "reject-dec") {
    $rj_uid = $user['Processing_value'] ?? '';
    $rj_order = $user['Processing_value_one'] ?? '';
    $rj_msgid = $user['Processing_value_tow'] ?? '';
    if ($rj_order !== '') {
        update("Payment_report", "dec_not_confirmed", $text, "id_order", $rj_order);
    }
    // به‌روزرسانی پیام رسید: از «در حال رد» به «رد شد»
    $__reason_safe = htmlspecialchars(strval($text), ENT_QUOTES, 'UTF-8');
    $__order_safe = htmlspecialchars(strval($rj_order), ENT_QUOTES, 'UTF-8');
    $__final = "❌ <b>پرداخت رد شد</b>\n"
        . "🧾 کد: <code>{$__order_safe}</code>\n"
        . "✍️ دلیل: {$__reason_safe}";
    $__empty_kb = json_encode(['inline_keyboard' => []]);
    if ($rj_msgid !== '' && $rj_msgid !== '0' && $rj_msgid !== null) {
        $__mid = intval($rj_msgid);
        // اول کپشن (رسید عکسی)؛ اگر نشد متن
        $__cap_res = telegram('editMessageCaption', [
            'chat_id' => $from_id,
            'message_id' => $__mid,
            'caption' => $__final,
            'parse_mode' => 'HTML',
            'reply_markup' => $__empty_kb,
        ]);
        $__cap_ok = is_array($__cap_res) && !empty($__cap_res['ok']);
        if (!$__cap_ok) {
            Editmessagetext($from_id, $__mid, $__final, $__empty_kb);
        }
    }
    if ($rj_order !== '' && function_exists('botMarkReceiptMsgs')) {
        botMarkReceiptMsgs($rj_order, '❌ رد شد (ادمین ' . $from_id . ')', $from_id, intval($rj_msgid), intval($rj_uid));
    }
    $__rj_show = htmlspecialchars(strval($rj_order), ENT_QUOTES, 'UTF-8');
    $__rj_uid_show = htmlspecialchars(strval($rj_uid), ENT_QUOTES, 'UTF-8');
    $__rj_admin_msg = ($textbotlang['Admin']['Payment']['Rejected'] ?? '⭕️ پرداخت رد شد')
        . "\n🆔 کاربر: <code>{$__rj_uid_show}</code>"
        . "\n🧾 سفارش: <code>{$__rj_show}</code>";
    sendmessage($from_id, $__rj_admin_msg, $keyboardadmin, 'HTML');
    try {
        $rj_msg = sprintf(
            $textbotlang['users']['moeny']['rejectresid'] ?? "❌ <b>پرداخت رد شد</b>\n✍️ دلیل: %s\n🧾 کد: <code>%s</code>",
            $text,
            $rj_order
        );
        if ($rj_uid !== '') {
            sendmessage($rj_uid, $rj_msg, null, 'HTML');
        }
    } catch (Throwable $e) {
        error_log('reject notify fail: ' . $e->getMessage());
        try {
            if ($rj_uid !== '') {
                sendmessage($rj_uid, "❌ رسید پرداخت شما رد شد.\n🛒 کد: " . $rj_order, null, 'HTML');
            }
        } catch (Throwable $e2) {}
    }
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_reject', "❌ <b>رد رسید پرداخت</b>\n\n👨‍💼 ادمین: <code>{$from_id}</code>\n🆔 کاربر: <code>{$rj_uid}</code>\n🧾 سفارش: <code>{$rj_order}</code>\n📝 دلیل: {$text}");
    }
    // پاک‌سازی مقادیر موقت
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    step('home', $from_id);
    return;
}

#-------------------------#
if ($text == $textbotlang['Admin']['Product']['titlebtnremove']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectloc', $from_id);
} elseif ($user['step'] == "selectloc") {
    update("user", "Processing_value", $text, "id", $from_id);
    step('remove-product', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
} elseif ($user['step'] == "remove-product") {
    if (!in_array($text, $name_product)) {
        sendmessage($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    $ydf = '/all';
    $stmt = $pdo->prepare("DELETE FROM product WHERE name_product = ? AND (Location = ? OR Location = ?)");
    $stmt->execute([$text, $user['Processing_value'], $ydf]);
    sendmessage($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if (false && $text == $textbotlang['Admin']['Product']['titlebtnedit']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
    step('selectlocedite', $from_id);
} elseif ($user['step'] == "selectlocedite") {
    update("user", "Processing_value_one", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin, 'HTML');
    step('change_filde', $from_id);
} elseif ($user['step'] == "change_filde") {
    if (!in_array($text, $name_product)) {
        sendmessage($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['Product']['selectfieldProduct'], $change_product, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Product']['editprice']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['sendnewprice'], $backadmin, 'HTML');
    step('change_price', $from_id);
} elseif ($user['step'] == "change_price") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
        return;
    }
    $location = '/all';
    $stmtFirst = $pdo->prepare("UPDATE product SET price_product = ? WHERE name_product = ? AND (Location = ? OR Location = ?)");
    $stmtFirst->execute([$text, $user['Processing_value'], $user['Processing_value_one'], $location]);
    $stmtSecond = $pdo->prepare("UPDATE invoice SET price_product = ? WHERE name_product = ? AND Service_location = ?");
    $stmtSecond->execute([$text, $user['Processing_value'], $user['Processing_value_one']]);
    sendmessage($from_id, $textbotlang['Admin']['Product']['updatedprice'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Product']['editcategory']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['sendnewcategory'], KeyboardCategory(), 'HTML');
    step('change_category', $from_id);
} elseif ($user['step'] == "change_category") {
    $category = select("category", "*", "remark", $text, "select");
    if ($category == false) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['invalidcategory'], $backadmin, 'HTML');
        return;
    }
    $location = "/all";
    $stmtFirst = $pdo->prepare("UPDATE product SET category = ? WHERE name_product = ? AND (Location = ? OR Location = ?)");
    $stmtFirst->execute([$category['id'], $user['Processing_value'], $user['Processing_value_one'], $location]);
    sendmessage($from_id, $textbotlang['Admin']['Product']['updatedcategory'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Product']['editname']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['sendnewname'], $backadmin, 'HTML');
    step('change_name', $from_id);
} elseif ($user['step'] == "change_name") {
    $value = "/all";
    $stmtFirst = $pdo->prepare("UPDATE product SET name_product = ? WHERE name_product = ? AND (Location = ? OR Location = ?)");
    $stmtFirst->execute([$text, $user['Processing_value'], $user['Processing_value_one'], $value]);
    $sqlSecond = "UPDATE invoice SET name_product = ? WHERE name_product = ? AND Service_location = ?";
    $stmtSecond = $pdo->prepare($sqlSecond);
    $stmtSecond->execute([$text, $user['Processing_value'], $user['Processing_value_one']]);
    sendmessage($from_id, $textbotlang['Admin']['Product']['updatedname'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Product']['editvolume']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['sendnewvolume'], $backadmin, 'HTML');
    step('change_val', $from_id);
} elseif ($user['step'] == "change_val") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
        return;
    }
    $sqlInvoice = "UPDATE invoice SET Volume = ? WHERE name_product = ? AND Service_location = ?";
    $stmtInvoice = $pdo->prepare($sqlInvoice);
    $stmtInvoice->execute([$text, $user['Processing_value'], $user['Processing_value_one']]);
    $sqlProduct = "UPDATE product SET Volume_constraint = ? WHERE name_product = ? AND Location = ?";
    $stmtProduct = $pdo->prepare($sqlProduct);
    $stmtProduct->execute([$text, $user['Processing_value'], $user['Processing_value_one']]);
    sendmessage($from_id, $textbotlang['Admin']['Product']['updatedvolume'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Product']['edittime']) {
    sendmessage($from_id, $textbotlang['Admin']['Product']['NewTime'], $backadmin, 'HTML');
    step('change_time', $from_id);
} elseif ($user['step'] == "change_time") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['InvalidTime'], $backadmin, 'HTML');
        return;
    }
    $stmtInvoice = $pdo->prepare("UPDATE invoice SET Service_time = ? WHERE name_product = ? AND Service_location = ?");
    $stmtInvoice->bindParam(1, $text);
    $stmtInvoice->bindParam(2, $user['Processing_value']);
    $stmtInvoice->bindParam(3, $user['Processing_value_one']);
    $stmtInvoice->execute();
    $stmtProduct = $pdo->prepare("UPDATE product SET Service_time = ? WHERE name_product = ? AND Location = ?");
    $stmtProduct->bindParam(1, $text);
    $stmtProduct->bindParam(2, $user['Processing_value']);
    $stmtProduct->bindParam(3, $user['Processing_value_one']);
    $stmtProduct->execute();
    sendmessage($from_id, $textbotlang['Admin']['Product']['TimeUpdated'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Usertest']['settimeusertest']) {
    sendmessage($from_id, sprintf($textbotlang['Admin']['Usertest']['sendtimeusertest'], $setting['time_usertest']), $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($user['step'] == "updatetime" && empty($datain)) {
    $__n = botAdmInt($text, 1, 720);
    if ($__n === null) {
        sendmessage($from_id, "❌ یک عدد بین ۱ تا ۷۲۰ (ساعت) بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__old = strval($setting['time_usertest'] ?? '-');
    update("setting", "time_usertest", strval($__n));
    step('home', $from_id);
    list($__tx, $__kb) = adminUsertestPanel();
    sendmessage($from_id, "✅ زمان اکانت تست: <b>{$__old}</b> ← <b>{$__n}</b> ساعت\n\n" . $__tx, $__kb, 'HTML');
}
#-------------------------#
if ($text == $textbotlang['Admin']['Usertest']['setvolumeusertest']) {
    sendmessage($from_id, sprintf($textbotlang['Admin']['Usertest']['sendvoluemusertest'], $setting['val_usertest']), $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($user['step'] == "val_usertest" && empty($datain)) {
    $__n = botAdmInt($text, 1, 102400);
    if ($__n === null) {
        sendmessage($from_id, "❌ حجم را به مگابایت بفرستید؛ عددی بین ۱ تا ۱۰۲۴۰۰ (مثلاً ۵۰۰ یعنی ۵۰۰ مگ، ۱۰۲۴ یعنی ۱ گیگ):", $backadmin, 'HTML');
        return;
    }
    $__old = strval($setting['val_usertest'] ?? '-');
    update("setting", "val_usertest", strval($__n));
    step('home', $from_id);
    list($__tx, $__kb) = adminUsertestPanel();
    sendmessage($from_id, "✅ حجم اکانت تست: <b>{$__old}</b> ← <b>{$__n}</b> مگابایت\n\n" . $__tx, $__kb, 'HTML');
}
#-------------------------#
elseif (preg_match('/^(addbalanceuser|lowbalanceuser)_(\w+)$/', strval($datain), $dataget)) {
    // نسخه 2.4.0: افزایش/کاهش موجودی با پیشنمایش و تأیید
    $__op = ($dataget[1] === 'addbalanceuser') ? 'add' : 'low';
    $__u0 = select("user", "*", "id", $dataget[2], "select");
    if (!is_array($__u0)) {
        sendmessage($from_id, "❌ کاربر پیدا نشد.", $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", json_encode(['bal_op' => $__op, 'uid' => strval($dataget[2])]), "id", $from_id);
    sendmessage($from_id, ($__op === 'add' ? ($textbotlang['Admin']['Balance']['PriceBalance'] ?? 'مبلغ افزایش را بفرستید:') : ($textbotlang['Admin']['Balance']['PriceBalancek'] ?? 'مبلغ کاهش را بفرستید:')) . "\n\n👛 موجودی فعلی: <b>" . number_format(intval($__u0['Balance'] ?? 0)) . "</b> تومان", $backadmin, 'HTML');
    step($__op === 'add' ? 'get_price_add' : 'get_price_Negative', $from_id);
} elseif ($user['step'] == "get_price_add" || $user['step'] == "get_price_Negative") {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__pv = json_decode(strval($user['Processing_value'] ?? ''), true);
    if (!is_array($__pv) || empty($__pv['uid'])) {
        // سازگاری: Processing_value قدیمی فقط آیدی بود
        $__pv = ['bal_op' => ($user['step'] == "get_price_add" ? 'add' : 'low'), 'uid' => strval($user['Processing_value'] ?? '')];
    }
    $__op = ($user['step'] == "get_price_add") ? 'add' : 'low';
    $__v = str_replace([',', '٬', '،', ' '], '', function_exists('botNormalizeDigits') ? botNormalizeDigits(trim(strval($text))) : trim(strval($text)));
    if (!ctype_digit($__v) || intval($__v) < 1) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'] ?? '❌ مبلغ نامعتبر است.', $backadmin, 'HTML');
        return;
    }
    if (intval($__v) > 100000000) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['maxpricebalance'] ?? '❌ مبلغ بیش از حد مجاز است.', $backadmin, 'HTML');
        return;
    }
    $__amt = intval($__v);
    $__u0 = select("user", "*", "id", $__pv['uid'], "select");
    if (!is_array($__u0)) {
        step('home', $from_id);
        sendmessage($from_id, "❌ کاربر پیدا نشد.", $keyboardadmin, 'HTML');
        return;
    }
    $__cur = intval($__u0['Balance'] ?? 0);
    $__after = ($__op === 'add') ? ($__cur + $__amt) : ($__cur - $__amt);
    update("user", "Processing_value", json_encode(['bal_op' => $__op, 'uid' => strval($__pv['uid']), 'amt' => $__amt, 'ts' => time(), 'confirm' => 1]), "id", $from_id);
    step('home', $from_id);
    $__kb = json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، اعمال شود', 'callback_data' => 'balc_ok']],
        [['text' => '❌ انصراف', 'callback_data' => 'balc_no']],
    ]], JSON_UNESCAPED_UNICODE);
    $__nm = htmlspecialchars(strval($__u0['username'] ?? '-'), ENT_QUOTES, 'UTF-8');
    sendmessage($from_id, "⚠️ <b>تأیید " . ($__op === 'add' ? 'افزایش' : 'کاهش') . " موجودی</b>\n\n🆔 کاربر: <code>" . htmlspecialchars(strval($__pv['uid']), ENT_QUOTES, 'UTF-8') . "</code> (@{$__nm})\n👛 موجودی فعلی: <b>" . number_format($__cur) . "</b> تومان\n" . ($__op === 'add' ? '➕' : '➖') . " مبلغ: <b>" . number_format($__amt) . "</b> تومان\n💳 موجودی بعد از تغییر: <b>" . number_format($__after) . "</b> تومان" . ($__after < 0 ? "\n\n❗️ <b>موجودی کاربر منفی میشود.</b>" : '') . "\n\nاعمال شود؟", $__kb, 'HTML');
}
if (isset($datain) && ($datain === 'balc_ok' || $datain === 'balc_no')) {
    $__raw = strval($user['Processing_value'] ?? '');
    $__pv = json_decode($__raw, true);
    $__valid = is_array($__pv) && !empty($__pv['confirm']) && !empty($__pv['uid']) && intval($__pv['amt'] ?? 0) > 0 && (time() - intval($__pv['ts'] ?? 0)) <= 1800;
    if ($datain === 'balc_no' || !$__valid) {
        if (is_array($__pv) && !empty($__pv['confirm'])) {
            update("user", "Processing_value", "0", "id", $from_id);
        }
        Editmessagetext($from_id, $message_id, ($datain === 'balc_no' ? "❌ تغییر موجودی لغو شد." : "⚠️ این درخواست منقضی شده یا قبلاً انجام شده است."), json_encode(['inline_keyboard' => []]));
        return;
    }
    $__claim = $pdo->prepare("UPDATE user SET Processing_value = '0' WHERE id = ? AND Processing_value = ?");
    $__claim->execute([strval($from_id), $__raw]);
    if ($__claim->rowCount() < 1) {
        Editmessagetext($from_id, $message_id, "⚠️ این تغییر قبلاً انجام شده است.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $__uid_bal = strval($__pv['uid']);
    $__amt = intval($__pv['amt']);
    $__is_add = ($__pv['bal_op'] === 'add');
    if ($__is_add) {
        if (function_exists('atomicAddBalance')) {
            atomicAddBalance($__uid_bal, $__amt);
        } else {
            $Balance_user = select("user", "*", "id", $__uid_bal, "select");
            update("user", "Balance", intval($Balance_user['Balance'] ?? 0) + $__amt, "id", $__uid_bal);
        }
        $Balance_user = select("user", "*", "id", $__uid_bal, "select");
        $__new = intval(is_array($Balance_user) ? ($Balance_user['Balance'] ?? 0) : 0);
    } else {
        if (function_exists('atomicDeductBalance')) {
            $__ded = atomicDeductBalance($__uid_bal, $__amt);
            if ($__ded === false || $__ded === null) {
                // موجودی کافی نبود — ادمین هشدار منفی شدن را دیده و تأیید کرده
                $__new = atomicAdjustBalance($__uid_bal, -$__amt);
            } else {
                $__new = intval($__ded);
            }
        } else {
            $Balance_user = select("user", "*", "id", $__uid_bal, "select");
            $__new = intval($Balance_user['Balance'] ?? 0) - $__amt;
            update("user", "Balance", $__new, "id", $__uid_bal);
        }
        $__new = intval($__new);
    }
    if (function_exists('logWalletTx')) {
        logWalletTx($__uid_bal, $__is_add ? 'admin_add' : 'admin_low', $__amt, $__new, $__is_add ? 'افزایش موجودی توسط ادمین' : 'کاهش موجودی توسط ادمین');
    }
    $amt_fmt = number_format($__amt);
    $__bal_fmt = number_format($__new);
    $__uid_show = htmlspecialchars($__uid_bal, ENT_QUOTES, 'UTF-8');
    $__msg = ($__is_add ? ($textbotlang['Admin']['Balance']['AddBalanceUser'] ?? '✅ مبلغ به موجودی کاربر اضافه شد') : ($textbotlang['Admin']['Balance']['NegativeBalanceUser'] ?? '✅ مبلغ از موجودی کاربر کسر شد'))
        . "\n🆔 کاربر: <code>{$__uid_show}</code>"
        . "\n💰 مبلغ: <b>{$amt_fmt}</b> تومان"
        . "\n👛 موجودی جدید: <b>{$__bal_fmt}</b> تومان";
    Editmessagetext($from_id, $message_id, $__msg, json_encode(['inline_keyboard' => []]));
    if ($__is_add) {
        sendmessage($__uid_bal, sprintf($textbotlang['Admin']['Balance']['AddedBalance'], $amt_fmt, $__bal_fmt), botMenuForBalance($__uid_bal), 'HTML');
    } else {
        sendmessage($__uid_bal, sprintf($textbotlang['Admin']['Balance']['ReduceBalance'], $amt_fmt) . botBalanceLine($__new), botMenuForBalance($__uid_bal), 'HTML');
    }
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_admin_balance', "💰 <b>" . ($__is_add ? 'افزایش' : 'کاهش') . " موجودی توسط ادمین</b>\n\n👨💼 ادمین: <code>{$from_id}</code>\n🆔 کاربر: <code>{$__uid_show}</code>\n\n" . ($__is_add ? '➕' : '➖') . " مبلغ: {$amt_fmt} تومان\n💳 موجودی جدید: {$__bal_fmt} تومان");
    }
    return;
}
#-------------------------#
if ($text == $textbotlang['Admin']['Discount']['titlebtn']) {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['GetCode'], $backadmin, 'HTML');
    step('get_code', $from_id);
} elseif ($user['step'] == "get_code") {
    if (!preg_match('/^[A-Za-z]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO Discount (code) VALUES (?)");
    $stmt->bindParam(1, $text);
    $stmt->execute();

    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCode'], null, 'HTML');
    step('get_price_code', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_code") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    update("Discount", "price", $text, "code", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], null, 'HTML');
    if (function_exists('codesGiftListKeyboard')) {
        list($__tx, $__kb) = codesGiftListKeyboard(0);
        sendmessage($from_id, "🎁 <b>کد هدیه ثبت شد</b>

" . $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], $keyboardadmin, 'HTML');
    }

    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['managepanel']['sublinkstatus']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['sublink'] == null) {
        update("marzban_panel", "sublink", "onsublink", "name_panel", $user['Processing_value']);
    }
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $sublinkkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['sublink'], 'callback_data' => $panel['sublink']],
            ],
        ]
    ]);
    if ($panel['configManual'] == "onconfig") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['checkoffconfig'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Status']['subTitle'], $sublinkkeyboard, 'HTML');
}
if ($datain == "onsublink") {
    update("marzban_panel", "sublink", "offsublink", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $sublinkkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['sublink'], 'callback_data' => $panel['sublink']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['subStatusOff'], $sublinkkeyboard);

} elseif ($datain == "offsublink") {
    update("marzban_panel", "sublink", "onsublink", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $sublinkkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['sublink'], 'callback_data' => $panel['sublink']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['subStatuson'], $sublinkkeyboard);
}
#-------------------------#
if ($text == $textbotlang['Admin']['managepanel']['configstatus']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['configManual'] == null) {
        update("marzban_panel", "configManual", "offconfig", "name_panel", $user['Processing_value']);
    }
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $configkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['configManual'], 'callback_data' => $panel['configManual']],
            ],
        ]
    ]);
    if ($panel['sublink'] == "onsublink") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['notoffsublink'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['Status']['configTitle'], $configkeyboard, 'HTML');
}
if ($datain == "onconfig") {
    update("marzban_panel", "configManual", "offconfig", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $configkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['configManual'], 'callback_data' => $panel['configManual']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['configStatusOff'], $configkeyboard);
} elseif ($datain == "offconfig") {
    update("marzban_panel", "configManual", "onconfig", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $configkeyboard = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['configManual'], 'callback_data' => $panel['configManual']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['configStatuson'], $configkeyboard);
}
#----------------[  view order user  ]------------------#
elseif (preg_match('/vieworderall_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $OrderUsers = select("invoice", "*", "id_user", $iduser, "fetchAll");
    foreach ($OrderUsers as $OrderUser) {
        $timeacc = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
        sendmessage($from_id, sprintf($textbotlang['Admin']['ManageUser']['Datails'], $OrderUser['id_invoice'], $OrderUser['Status'], $OrderUser['id_user'], $OrderUser['username'], $OrderUser['Service_location'], $OrderUser['name_product'], $OrderUser['price_product'], $OrderUser['Volume'], $OrderUser['Service_time'], $timeacc), null, 'HTML');
    }
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['SendOrder'], $keyboardadmin, 'HTML');
    step('home', $from_id);
}
#----------------[  remove Discount   ]------------------#
if ($text == $textbotlang['Admin']['Discount']['titlebtnremove']) {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin, 'HTML');
    step('remove-Discount', $from_id);
} elseif ($user['step'] == "remove-Discount") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
}
if ($text == $textbotlang['Admin']['ManageUser']['removeorderbtn']) {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['RemoveService'], $backadmin, 'HTML');
    step('removeservice', $from_id);
} elseif ($user['step'] == "removeservice") {
    // نسخه 2.3.9: نمایش مشخصات و تأیید قبل از حذف
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__su = trim(strval($text));
    $info_product = $__su !== '' ? select("invoice", "*", "username", $__su, "select") : null;
    if (!is_array($info_product)) {
        sendmessage($from_id, "❌ سرویسی با این یوزرنیم پیدا نشد. یوزرنیم سرویس را دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $__h = function ($v) { return htmlspecialchars(strval($v), ENT_QUOTES, 'UTF-8'); };
    $__rkb = json_encode(['inline_keyboard' => [
        [['text' => '🗑 بله، حذف شود', 'callback_data' => 'rmsvc_ok_' . strval($info_product['id_invoice'])]],
        [['text' => '❌ انصراف', 'callback_data' => 'rmsvc_no']],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "⚠️ <b>حذف سرویس</b>\n\n👤 یوزرنیم: <code>" . $__h($info_product['username']) . "</code>\n🆔 کاربر: <code>" . $__h($info_product['id_user']) . "</code>\n📦 محصول: " . $__h($info_product['name_product']) . "\n📍 پنل: " . $__h($info_product['Service_location']) . "\n📌 وضعیت: " . $__h($info_product['Status']) . "\n\n❗️ سرویس از پنل و ربات حذف میشود و <b>مبلغی به کاربر برنمیگردد</b>. مطمئنید؟", $__rkb, 'HTML');
}
if (isset($datain) && ($datain === 'rmsvc_no' || preg_match('/^rmsvc_ok_([A-Za-z0-9]+)$/', strval($datain), $__m_rs))) {
    if ($datain === 'rmsvc_no') {
        Editmessagetext($from_id, $message_id, "❌ حذف سرویس لغو شد.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $info_product = select("invoice", "*", "id_invoice", $__m_rs[1], "select");
    if (!is_array($info_product)) {
        Editmessagetext($from_id, $message_id, "⚠️ این سرویس قبلاً حذف شده یا پیدا نشد.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $__su = strval($info_product['username']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $info_product['Service_location'], "select");
    if (is_array($marzban_list_get)) {
        $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $__su);
        if (isset($DataUserOut['status'])) {
            $ManagePanel->RemoveUser($marzban_list_get['name_panel'], $__su);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id_invoice = ?");
    $stmt->execute([strval($info_product['id_invoice'])]);
    $__rm_user = htmlspecialchars($__su, ENT_QUOTES, 'UTF-8');
    Editmessagetext($from_id, $message_id, ($textbotlang['Admin']['ManageUser']['RemovedService'] ?? '✅ سرویس حذف شد') . "\n👤 یوزرنیم سرویس: <code>{$__rm_user}</code>", json_encode(['inline_keyboard' => [[['text' => '🔙 مدیریت کاربران', 'callback_data' => 'nav_user']]]], JSON_UNESCAPED_UNICODE));
    return;
}
if ($text == $textbotlang['Admin']['managepanel']['methodusername']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['decmthodusername'], $MethodUsername, 'HTML');
    step('updatemethodusername', $from_id);
} elseif ($user['step'] == "updatemethodusername") {
    update("marzban_panel", "MethodUsername", $text, "name_panel", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['AlgortimeUsername']['SaveData'], $keyboardadmin, 'HTML');
    if ($text == $textbotlang['users']['customtextandrandom']) {
        step('getnamecustom', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['customnamesend'], $backuser, 'HTML');
        return;
    }
    step('home', $from_id);
} elseif ($user['step'] == "getnamecustom") {
    if (!preg_match('/^\w{3,32}$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['invalidname'], $backadmin, 'html');
        return;
    }
    update("setting", "namecustome", $text);
    step('home', $from_id);
    $listpanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("user", "Processing_value", $text, "id", $from_id);
    outtypepanel($listpanel['type'], $textbotlang['Admin']['managepanel']['savedname']);
}
#----------------[  MANAGE PAYMENT   ]------------------#

if ($text == $textbotlang['Admin']['keyboardadmin']['finance']) {
    $keyboardmoeny = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : json_encode(['inline_keyboard' => []]);
    $fin_summary = function_exists('buildFinanceSummaryText') ? buildFinanceSummaryText() : "💳 <b>تنظیمات مالی</b>";
    sendmessage($from_id, $fin_summary, $keyboardmoeny, 'HTML');
}

# نسخه 2.2.9: رسیدهای در انتظار از صفحه مالی
if (in_array($from_id, $admin_ids) && isset($datain) && $datain === 'fin_rcpt') {
    $res = function_exists('resendWaitingPaymentReceipts') ? resendWaitingPaymentReceipts($from_id, 40) : ['ok' => false, 'count' => 0, 'msg' => 'تابع موجود نیست'];
    $msg = strval($res['msg'] ?? '');
    if (intval($res['count'] ?? 0) === 0 && !empty($res['ok']) && $msg === '') {
        $msg = 'هیچ رسید در انتظار تأییدی نیست.';
    }
    $kb_fr = json_encode(['inline_keyboard' => [[['text' => '↩️ بازگشت به مالی', 'callback_data' => 'finance_menu_back']]]], JSON_UNESCAPED_UNICODE);
    Editmessagetext($from_id, $message_id, $msg !== '' ? $msg : '—', $kb_fr);
    return;
}
#----------- finance auto cart confirm ------------#
if ($datain == "finance_menu_back") {
    $keyboardmoeny = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : null;
    Editmessagetext($from_id, $message_id, (function_exists('buildFinanceSummaryText') ? buildFinanceSummaryText() : "💳 <b>تنظیمات مالی</b>"), $keyboardmoeny);
}
if ($datain == "finance_auto_cart_settings") {
    $min = function_exists('getAutoCartIntervalMinutes') ? getAutoCartIntervalMinutes() : 4;
    $on = function_exists('isAutomaticCartConfirmEnabled') && isAutomaticCartConfirmEnabled();
    $st = $on ? '✅ فعال' : '❌ غیرفعال';
    $kb = function_exists('buildAutoCartSettingsKeyboard') ? buildAutoCartSettingsKeyboard() : null;
    $txt = "🤖 <b>تنظیمات تأیید خودکار بدون بررسی</b>\n\nوضعیت: {$st}\nفاصله فعلی: هر <b>{$min}</b> دقیقه\n\nاز این صفحه می‌توانید فاصله را تنظیم کنید.";
    Editmessagetext($from_id, $message_id, $txt, $kb);
}
if ($datain == "finance_auto_cart_info") {
    $on = function_exists('isAutomaticCartConfirmEnabled') && isAutomaticCartConfirmEnabled();
    $min = function_exists('getAutoCartIntervalMinutes') ? getAutoCartIntervalMinutes() : 4;
    $st = $on ? '✅ فعال' : '❌ غیرفعال';
    sendmessage($from_id, "🤖 <b>تأیید خودکار بدون بررسی</b>\n\nوضعیت: {$st}\nفاصله: هر <b>{$min}</b> دقیقه\n\nبا روشن بودن، رسیدهای کارت‌به‌کارت در انتظار بدون بررسی دستی تأیید می‌شوند.\nبرای کاربرهای ریسکی می‌توانید در اطلاعات کاربر این قابلیت را جداگانه خاموش کنید.", null, 'HTML');
}
if ($datain == "finance_auto_cart_toggle") {
    $cur = function_exists('isAutomaticCartConfirmEnabled') && isAutomaticCartConfirmEnabled();
    $new_state = !$cur;
    if (function_exists('setPaySettingValue')) {
        setPaySettingValue('auto_cart_confirm', $new_state ? '1' : '0');
    } else {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting('auto_cart_confirm', $new_state ? '1' : '0');
        }
        update("PaySetting", "ValuePay", $new_state ? '1' : '0', "NamePay", "auto_cart_confirm");
    }
    $cron_msg = '';
    if (function_exists('syncAutoCartCron')) {
        $cron_ok = syncAutoCartCron($new_state);
        if ($new_state) {
            if ($cron_ok) {
                $cron_msg = "✅ کرون تأیید خودکار در crontab نصب شد.";
            } else {
                $cmd = function_exists('getAutoCartCronCommand') ? getAutoCartCronCommand() : '';
                $cron_msg = "⚠️ نتوانست کرون را خودکار اضافه کند. دستی:\n<code>" . htmlspecialchars(strval($cmd), ENT_QUOTES, 'UTF-8') . "</code>";
            }
        } else {
            if ($cron_ok) {
                $cron_msg = "✅ کرون تأیید خودکار از crontab حذف شد.";
            } else {
                $cron_msg = "⚠️ نتوانست کرون را خودکار حذف کند. دستی با crontab -e خط croncard.php را پاک کنید.";
            }
        }
    } elseif ($new_state) {
        $cmd = function_exists('getAutoCartCronCommand') ? getAutoCartCronCommand() : "*/4 * * * * curl https://$domainhosts/cron/croncard.php";
        $cron_msg = "⚠️ shell_exec غیرفعال است. دستی:\n<code>" . htmlspecialchars(strval($cmd), ENT_QUOTES, 'UTF-8') . "</code>";
    } else {
        $cron_msg = "⚠️ shell_exec غیرفعال است. برای حذف، خط croncard.php را دستی از crontab پاک کنید.";
    }
    if ($cron_msg !== '') {
        sendmessage($from_id, $cron_msg, null, 'HTML');
    }
    $auto_on = $new_state;
    // رفرش همان صفحه‌ای که کاربر هست (مالی یا زیرمنوی تنظیمات)
    $from_settings = !empty($text_callback) && (
        strpos($text_callback, 'تنظیمات تأیید') !== false
        || strpos($text_callback, 'تنظیمات تایید') !== false
        || (strpos($text_callback, 'فاصله') !== false && strpos($text_callback, 'تأیید خودکار') !== false)
    );
    if ($from_settings) {
        $min = function_exists('getAutoCartIntervalMinutes') ? getAutoCartIntervalMinutes() : 4;
        $kb = function_exists('buildAutoCartSettingsKeyboard') ? buildAutoCartSettingsKeyboard() : null;
        $txt = "🤖 <b>تنظیمات تأیید خودکار بدون بررسی</b>\n\nوضعیت: " . ($auto_on ? '✅ فعال' : '❌ غیرفعال') . "\nفاصله فعلی: هر <b>{$min}</b> دقیقه";
        Editmessagetext($from_id, $message_id, $txt, $kb);
    } else {
        $kb = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : null;
        $txt = "💳 <b>تنظیمات مالی</b>\n\nتأیید خودکار: " . ($auto_on ? '✅ روشن' : '❌ خاموش');
        Editmessagetext($from_id, $message_id, $txt, $kb);
    }
}
if ($datain == "finance_auto_cart_interval") {
    $cur = function_exists('getAutoCartIntervalMinutes') ? getAutoCartIntervalMinutes() : 4;
    sendmessage($from_id, sprintf($textbotlang['Admin']['Automatic_confirmation']['interval_prompt'] ?? "عددی بین 1 تا 60 بفرستید (فعلی: %s)", $cur), $backadmin, 'HTML');
    step('set_auto_cart_interval', $from_id);
}
if (isset($user['step']) && $user['step'] == 'set_auto_cart_interval') {
    if (is_string($text) && (strpos($text, 'بازگشت') !== false || $text == ($textbotlang['Admin']['Back'] ?? ''))) {
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['Admin']['Back'] ?? 'بازگشت', $keyboardadmin, 'HTML');
        return;
    }
    if (!ctype_digit(strval($text))) {
        sendmessage($from_id, $textbotlang['Admin']['Automatic_confirmation']['interval_invalid'] ?? 'عدد نامعتبر', $backadmin, 'HTML');
        return;
    }
    $mins = intval($text);
    if ($mins < 1 || $mins > 60) {
        sendmessage($from_id, $textbotlang['Admin']['Automatic_confirmation']['interval_invalid'] ?? 'عدد نامعتبر', $backadmin, 'HTML');
        return;
    }
    if (function_exists('setPaySettingValue')) {
        setPaySettingValue('auto_cart_interval', strval($mins));
    } else {
        if (function_exists('ensurePaySetting')) {
            ensurePaySetting('auto_cart_interval', strval($mins));
        }
        update("PaySetting", "ValuePay", strval($mins), "NamePay", "auto_cart_interval");
    }
    $enabled = function_exists('isAutomaticCartConfirmEnabled') && isAutomaticCartConfirmEnabled();
    if ($enabled && function_exists('syncAutoCartCron')) {
        syncAutoCartCron(true);
    }
    sendmessage($from_id, sprintf($textbotlang['Admin']['Automatic_confirmation']['interval_saved'] ?? '✅ %s دقیقه', $mins), $keyboardadmin, 'HTML');
    step('home', $from_id);
}

if (preg_match('/^editpay-(.*)-(.*)/', $datain, $dataget)) {
    $methodpay = $dataget[1];
    $status = $dataget[2];
    if ($methodpay == "cart") {
        $value = ($status == "oncard") ? "offcard" : "oncard";
        update("PaySetting", "ValuePay", $value, "NamePay", "Cartstatus");
        botAdminActionReport('درگاه کارت به کارت', 'وضعیت: <b>' . ($value === 'oncard' ? '✅ روشن' : '❌ خاموش') . '</b>');
    } elseif ($methodpay == "iranpay") {
        $value = ($status == "ondigi") ? "offdigi" : "ondigi";
        update("PaySetting", "ValuePay", $value, "NamePay", "digistatus");
    } elseif ($methodpay == "sheba") {
        // نسخه 2.3.0: روشن کردن فقط وقتی شبای معتبر ثبت شده باشد
        $value = ($status == "onsheba") ? "offsheba" : "onsheba";
        if ($value === 'onsheba' && (!function_exists('botExtractSheba') || botExtractSheba(getPaySettingValue('ShebaDescription', '')) === '')) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ اول از «⚙️ تنظیمات» شماره شبا را ثبت کنید.', 'show_alert' => true]);
        } else {
            if (function_exists('ensurePaySetting')) {
                ensurePaySetting('shebastatus', 'offsheba');
            }
            update("PaySetting", "ValuePay", $value, "NamePay", "shebastatus");
            botAdminActionReport('درگاه واریز به شبا', 'وضعیت: <b>' . ($value === 'onsheba' ? '✅ روشن' : '❌ خاموش') . '</b>');
        }
    }
    $keyboardmoeny = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : null;
    Editmessagetext($from_id, $message_id, (function_exists('buildFinanceSummaryText') ? buildFinanceSummaryText() : ($textbotlang['users']['moeny']['settingpay'] ?? '💳 تنظیمات مالی')), $keyboardmoeny);
} elseif ($datain == "settingcart") {
    // نسخه 2.3.0: صفحه شیشهای کارت به کارت
    if (function_exists('buildCardSettingsPage')) {
        list($__ct, $__ck) = buildCardSettingsPage();
        Editmessagetext($from_id, $message_id, $__ct, $__ck);
    } else {
        sendmessage($from_id, $textbotlang['users']['selectoption'], $CartManage, 'HTML');
    }
} elseif ($datain == "settingsheba") {
    list($__st_t, $__st_k) = buildShebaSettingsPage();
    Editmessagetext($from_id, $message_id, $__st_t, $__st_k);
} elseif ($datain == "fin_card_edit") {
    // نسخه 2.3.2: افزودن کارت جدید به لیست
    sendmessage($from_id, "➕ <b>افزودن کارت</b>\n\nشماره کارت ۱۶ رقمی را بفرستید.\nمیتوانید نام صاحب کارت و بانک را هم بنویسید.", $backadmin, 'HTML');
    step('addcard', $from_id);
} elseif ($datain == "fin_card_replace") {
    $__cd = select("PaySetting", "ValuePay", "NamePay", "CartDescription", "select");
    sendmessage($from_id, "♻️ <b>جایگزینی همه کارتها</b>\n\nهمه کارتهای فعلی حذف و فقط کارتی که میفرستید ثبت میشود.\n\n" . sprintf($textbotlang['users']['moeny']['sendcart'], htmlspecialchars(strval(is_array($__cd) ? ($__cd['ValuePay'] ?? '') : ''), ENT_QUOTES, 'UTF-8')), $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif (preg_match('/^fin_card_del_(\d+)$/', strval($datain), $__m_cdel)) {
    $__cl = botGetCardList();
    $__ci = intval($__m_cdel[1]);
    if (!isset($__cl[$__ci])) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'کارت پیدا نشد؛ صفحه را تازه کنید.', 'show_alert' => true]);
    } elseif (count($__cl) <= 1) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ حداقل یک کارت لازم است. برای عوض کردن از «♻️ جایگزینی» استفاده کنید.', 'show_alert' => true]);
    } else {
        array_splice($__cl, $__ci, 1);
        botSaveCardList($__cl);
        list($__ct, $__ck) = buildCardSettingsPage();
        Editmessagetext($from_id, $message_id, "🗑 کارت حذف شد.\n\n" . $__ct, $__ck);
    }
} elseif ($datain == "fin_sheba_edit") {
    $__sd = trim(strval(getPaySettingValue('ShebaDescription', '')));
    sendmessage($from_id, "🏦 شماره شبا را بفرستید (IR + ۲۴ رقم).\nمیتوانید نام صاحب حساب و بانک را هم بنویسید.\n\nمتن فعلی: " . ($__sd !== '' ? "<code>" . htmlspecialchars($__sd, ENT_QUOTES, 'UTF-8') . "</code>" : '❗️ ثبت نشده'), $backadmin, 'HTML');
    step('changesheba', $from_id);
}
if ($text == $textbotlang['users']['moeny']['card_number_settings']) {
    $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDescription", "select");
    sendmessage($from_id, sprintf($textbotlang['users']['moeny']['sendcart'], $PaySetting['ValuePay']), $backadmin, 'HTML');
    step('changecard', $from_id);
} elseif ($user['step'] == "changecard") {
    // نسخه 2.3.0: اعتبارسنجی + برگشت به صفحه مالی شیشهای
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    if (!isset($text) || !is_string($text) || trim($text) === '') {
        sendmessage($from_id, "❌ متن شماره کارت را بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__card_txt = function_exists('botNormalizeDigits') ? botNormalizeDigits(trim($text)) : trim($text);
    if (function_exists('botSaveCardList')) {
        botSaveCardList([$__card_txt]);
    } else {
        update("PaySetting", "ValuePay", $__card_txt, "NamePay", "CartDescription");
    }
    step('home', $from_id);
    $__cn = function_exists('botExtractCardNumber') ? botExtractCardNumber($__card_txt) : '';
    if ($__cn === '') {
        $__cw = "\n\n⚠️ شماره کارت ۱۶ رقمی در متن پیدا نشد. ذخیره شد، ولی دکمه «کپی شماره کارت» به کاربر نشان داده نمیشود.";
    } elseif (function_exists('botCardLuhnOk') && !botCardLuhnOk($__cn)) {
        $__cw = "\n\n🔢 شماره کارت: <code>{$__cn}</code>\n⚠️ رقم کنترل این شماره کارت درست نیست؛ احتمالاً یک رقم اشتباه است. دوباره چک کنید.";
    } else {
        $__cw = "\n\n🔢 شماره کارت: <code>{$__cn}</code> ✅";
    }
    $__fin_t = function_exists('buildFinanceSummaryText') ? buildFinanceSummaryText() : '';
    $__fin_k = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : $CartManage;
    sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['Savacard'] . $__cw . "\n\n" . $__fin_t, $__fin_k, 'HTML');
}
# نسخه 2.3.2: افزودن کارت به لیست
if (in_array($from_id, $admin_ids) && strval($user['step'] ?? '') === 'addcard' && empty($datain)) {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__ac = (isset($text) && is_string($text)) ? botNormalizeDigits(trim($text)) : '';
    $__acn = botExtractCardNumber($__ac);
    if ($__acn === '') {
        sendmessage($from_id, "❌ شماره کارت ۱۶ رقمی در متن پیدا نشد. دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (mb_strlen($__ac) > 300) {
        sendmessage($from_id, "❌ متن خیلی طولانی است (حداکثر ۳۰۰ کاراکتر). دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__cl = botGetCardList();
    foreach ($__cl as $__c) {
        if (botExtractCardNumber($__c) === $__acn) {
            sendmessage($from_id, "❌ این کارت قبلاً ثبت شده. کارت دیگری بفرستید:", $backadmin, 'HTML');
            return;
        }
    }
    if (count($__cl) >= 10) {
        step('home', $from_id);
        sendmessage($from_id, "❌ حداکثر ۱۰ کارت میشود ثبت کرد.", $backadmin, 'HTML');
        return;
    }
    $__cl[] = $__ac;
    botSaveCardList($__cl);
    step('home', $from_id);
    $__warn = botCardLuhnOk($__acn) ? '' : "\n⚠️ رقم کنترل این کارت درست نیست؛ احتمالاً یک رقم اشتباه است. اگر اشتباه است حذفش کنید.";
    list($__ct, $__ck) = buildCardSettingsPage();
    sendmessage($from_id, "✅ کارت <code>{$__acn}</code> اضافه شد." . $__warn . "\n\n" . $__ct, $__ck, 'HTML');
    return;
}
# نسخه 2.3.0: ثبت شماره شبا
if (in_array($from_id, $admin_ids) && strval($user['step'] ?? '') === 'changesheba' && empty($datain)) {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    if (!isset($text) || !is_string($text) || trim($text) === '') {
        sendmessage($from_id, "❌ شماره شبا را بهصورت متن بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__sh_txt = botNormalizeDigits(trim($text));
    $__sh = botExtractSheba($__sh_txt);
    if ($__sh === '') {
        sendmessage($from_id, "❌ شماره شبا پیدا نشد. شبا باید <b>IR + ۲۴ رقم</b> باشد، مثل:\n<code>IR000000000000000000000000</code>\nدوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (!botShebaChecksumOk($__sh)) {
        sendmessage($from_id, "❌ شبای <code>{$__sh}</code> معتبر نیست (رقم کنترل اشتباه است). احتمالاً یک رقم اشتباه تایپ شده. دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (mb_strlen($__sh_txt) > 500) {
        sendmessage($from_id, "❌ متن خیلی طولانی است (حداکثر ۵۰۰ کاراکتر). دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    ensurePaySetting('ShebaDescription', '');
    ensurePaySetting('shebastatus', 'offsheba');
    update("PaySetting", "ValuePay", $__sh_txt, "NamePay", "ShebaDescription");
    botAdminActionReport('تغییر شماره شبا', '🏦 شبا: <code>' . botMaskTail(function_exists('botExtractSheba') ? botExtractSheba($__sh_txt) : $__sh_txt) . '</code>');
    step('home', $from_id);
    $__on_now = strval(getPaySettingValue('shebastatus', 'offsheba')) === 'onsheba';
    sendmessage($from_id, "✅ شماره شبا ذخیره شد: <code>{$__sh}</code>" . ($__on_now ? '' : "\n\nℹ️ واریز به شبا هنوز <b>خاموش</b> است. از ردیف «🏦 واریز به شبا» روشنش کنید.") . "\n\n" . buildFinanceSummaryText(), buildFinanceKeyboard(), 'HTML');
    return;
}
# نسخه 2.2.9: درگاههای NowPayments و آقای پرداخت حذف شدند
if ($text == $textbotlang['Admin']['keyboardadmin']['manage_panel'] || $datain === 'nav_panel') {
    if (function_exists('adminPanelsListKeyboard')) {
        list($__tx, $__kb) = adminPanelsListKeyboard();
        if ($datain === 'nav_panel' && !empty($message_id)) {
            Editmessagetext($from_id, $message_id, $__tx, $__kb);
        } else {
            sendmessage($from_id, $__tx, $__kb, 'HTML');
        }
    } else {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getloc'], $json_list_marzban_panel, 'HTML');
        step('GetLocationEdit', $from_id);
    }
} elseif ($user['step'] == "GetLocationEdit") {
    $listpanel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (!is_array($listpanel) || empty($listpanel['name_panel'])) {
        sendmessage($from_id, "❌ پنل یافت نشد. از لیست اینلاین انتخاب کنید.", $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    outtypepanel($listpanel['type'], $textbotlang['users']['selectoption']);
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'], $backadmin, 'HTML');
    step('GetNameNew', $from_id);
} elseif ($user['step'] == "GetNameNew") {
    // نسخه 2.3.6: اعتبارسنجی نام جدید پنل
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__nn = (isset($text) && is_string($text)) ? trim($text) : '';
    if ($__nn === '' || mb_strlen($__nn) > 100) {
        sendmessage($from_id, "❌ یک نام بین ۱ تا ۱۰۰ کاراکتر بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__old_name = strval($user['Processing_value'] ?? '');
    if (!is_array(select("marzban_panel", "*", "name_panel", $__old_name, "select"))) {
        step('home', $from_id);
        sendmessage($from_id, "❌ پنل پیدا نشد. دوباره از «✏️ مدیریت پنل» شروع کنید.", $backadmin, 'HTML');
        return;
    }
    if ($__nn === $__old_name) {
        sendmessage($from_id, "ℹ️ این همان نام فعلی است. نام دیگری بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (is_array(select("marzban_panel", "*", "name_panel", $__nn, "select"))) {
        sendmessage($from_id, "❌ پنل دیگری با همین نام وجود دارد. نام دیگری بفرستید:", $backadmin, 'HTML');
        return;
    }
    $text = $__nn;
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    update("marzban_panel", "name_panel", $text, "name_panel", $user['Processing_value']);
    update("invoice", "Service_location", $text, "Service_location", $user['Processing_value']);
    update("product", "Location", $text, "Location", $user['Processing_value']);
    update("user", "Processing_value", $text, "id", $from_id);
    $typepanel = select("marzban_panel", "*", "name_panel", $text, "select");
    if (is_array($typepanel) && function_exists('adminPanelDetailKeyboard')) {
        list($tx, $kb) = adminPanelDetailKeyboard($typepanel, $textbotlang['Admin']['managepanel']['ChangedNmaePanel'] ?? '✅ نام به‌روز شد');
        sendmessage($from_id, $tx, $kb, 'HTML');
    } else {
        outtypepanel($typepanel['type'] ?? '', $textbotlang['Admin']['managepanel']['ChangedNmaePanel']);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNew', $from_id);
} elseif ($user['step'] == "GeturlNew") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    update("marzban_panel", "url_panel", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($typepanel) && function_exists('adminPanelDetailKeyboard')) {
        list($tx, $kb) = adminPanelDetailKeyboard($typepanel, ($textbotlang['Admin']['managepanel']['ChangedurlPanel'] ?? '✅ آدرس به‌روز شد') . (function_exists('botPanelAutoTest') ? botPanelAutoTest($typepanel) : ''));
        sendmessage($from_id, $tx, $kb, 'HTML');
    } else {
        outtypepanel($typepanel['type'] ?? '', $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'], $backadmin, 'HTML');
    step('GetusernameNew', $from_id);
} elseif ($user['step'] == "GetusernameNew") {
    update("marzban_panel", "username_panel", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($typepanel) && function_exists('adminPanelDetailKeyboard')) {
        list($tx, $kb) = adminPanelDetailKeyboard($typepanel, ($textbotlang['Admin']['managepanel']['ChangedusernamePanel'] ?? '✅ یوزرنیم به‌روز شد') . (function_exists('botPanelAutoTest') ? botPanelAutoTest($typepanel) : ''));
        sendmessage($from_id, $tx, $kb, 'HTML');
    } else {
        outtypepanel($typepanel['type'] ?? '', $textbotlang['Admin']['managepanel']['ChangedusernamePanel']);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'], $backadmin, 'HTML');
    step('GetpaawordNew', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($typepanel) && function_exists('adminPanelDetailKeyboard')) {
        list($tx, $kb) = adminPanelDetailKeyboard($typepanel, ($textbotlang['Admin']['managepanel']['ChangedpasswordPanel'] ?? '✅ پسورد به‌روز شد') . (function_exists('botPanelAutoTest') ? botPanelAutoTest($typepanel) : ''));
        sendmessage($from_id, $tx, $kb, 'HTML');
    } else {
        outtypepanel($typepanel['type'] ?? '', $textbotlang['Admin']['managepanel']['ChangedpasswordPanel']);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['editinound'] || $text == $textbotlang['Admin']['managepanel']['setgroup']) {
    if ($text == $textbotlang['Admin']['managepanel']['setgroup']) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['keyboardpanel']['getgroup'], $backadmin, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['keyboardpanel']['getidinbound'], $backadmin, 'HTML');
    }
    step('getinboundiid', $from_id);
} elseif ($user['step'] == "getinboundiid") {
    $typepanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    outtypepanel($typepanel['type'], $textbotlang['Admin']['managepanel']['keyboardpanel']['setinbound']);
    update("marzban_panel", "inboundid", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['linksub']) {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['geturlnew'], $backadmin, 'HTML');
    step('GeturlNewx', $from_id);
} elseif ($user['step'] == "GeturlNewx") {
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['Invalid-domain'], $backadmin, 'HTML');
        return;
    }
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['type'] == "x-ui_single") {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $text);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['subinvalidDomain'], null, 'HTML');
            return;
        }
        if (curl_error($ch)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['subinvalidDomain'], null, 'HTML');
            return;
        }
        $protocol = ['vmess', 'vless', 'trojan', 'ss'];
        if (isBase64($response)) {
            $response = base64_decode($response);
        }
        $sub_check = explode('://', $response)[0];
        if (!in_array($sub_check, $protocol)) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['subinvalid'], null, 'HTML');
            return;
        }
        $text = dirname($text);
    }
    outtypepanel($panel['type'], $textbotlang['Admin']['managepanel']['ChangedurlPanel']);
    update("marzban_panel", "linksubx", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
} elseif ($user['step'] == "GetpaawordNew") {
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['ChangedpasswordPanel'], $optionMarzban, 'HTML');
    update("marzban_panel", "password_panel", $text, "name_panel", $user['Processing_value']);
    step('home', $from_id);
}
if ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']) {
    // نسخه 2.3.8: دکمه قدیمی دیگر مستقیم حذف نمیکند؛ همان تأیید جدید
    $__rp = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!is_array($__rp) || empty($__rp['id'])) {
        sendmessage($from_id, "❌ پنل پیدا نشد. از «✏️ مدیریت پنل» پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
    } else {
        $__rid = intval($__rp['id']);
        $__uc = function_exists('botPanelUsageCounts') ? botPanelUsageCounts() : [];
        $__sv = intval($__uc[strval($__rp['name_panel'])]['svc'] ?? 0);
        $__pr = intval($__uc[strval($__rp['name_panel'])]['prod'] ?? 0);
        $__kb_rp = json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، حذف شود', 'callback_data' => 'adm_pan_act_del_' . $__rid]],
            [['text' => '❌ انصراف', 'callback_data' => 'adm_pan_' . $__rid]],
        ]], JSON_UNESCAPED_UNICODE);
        sendmessage($from_id, "⚠️ پنل <code>" . htmlspecialchars(strval($__rp['name_panel']), ENT_QUOTES, 'UTF-8') . "</code> حذف شود؟\n\n📦 سرویس فعال: <b>{$__sv}</b> | 🛒 محصول: <b>{$__pr}</b>" . ($__sv > 0 ? "\n\n❗️ این پنل سرویس فعال دارد. بهتر است بهجای حذف، «فروش» را غیرفعال کنید." : ''), $__kb_rp, 'HTML');
    }
}
if ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['setvolume']) {
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['SetPrice'] . $setting['Extra_volume'], $backadmin, 'HTML');
    step('GetPriceExtra', $from_id);
} elseif ($user['step'] == "GetPriceExtra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    update("setting", "Extra_volume", $text);
    // نسخه 2.2.9: بعد از ثبت، برگشت به صفحه شیشهای فروشگاه
    $__ev_msg = ($textbotlang['users']['Extra_volume']['ChangedPrice'] ?? '✅ ذخیره شد.') . "\n💰 قیمت هر گیگ: <b>" . number_format(intval($text)) . "</b> تومان";
    if (function_exists('buildAdminShopSectionText') && function_exists('buildAdminShopInlineKeyboard')) {
        sendmessage($from_id, $__ev_msg . "\n\n" . buildAdminShopSectionText(), buildAdminShopInlineKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, $__ev_msg, $shopkeyboard, 'HTML');
    }
    step('home', $from_id);
}
#-------------------------#
if ($text == $textbotlang['Admin']['Balance']['SendBalanceAll']) {
    sendmessage($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
    step('add_Balance_all', $from_id);
} elseif ($user['step'] == "add_Balance_all") {
    // نسخه 2.3.9: شارژ همگانی با تأیید + ثبت در تاریخچه + بدون کاربران مسدود
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__mv = str_replace([',', '٬', '،', ' '], '', function_exists('botNormalizeDigits') ? botNormalizeDigits(trim(strval($text))) : trim(strval($text)));
    if (!ctype_digit($__mv) || intval($__mv) < 1 || intval($__mv) > 100000000) {
        sendmessage($from_id, "❌ مبلغ را فقط بهصورت عدد بفرستید (۱ تا ۱۰۰,۰۰۰,۰۰۰ تومان):", $backadmin, 'HTML');
        return;
    }
    $__mamt = intval($__mv);
    $__mcnt = 0;
    try {
        $__mcnt = intval($pdo->query("SELECT COUNT(*) FROM user WHERE User_Status IS NULL OR User_Status != 'block'")->fetchColumn());
    } catch (Throwable $e) {
    }
    update("user", "Processing_value", json_encode(['mass_charge' => $__mamt, 'ts' => time()]), "id", $from_id);
    step('home', $from_id);
    $__mkb = json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، شارژ شود', 'callback_data' => 'massc_ok']],
        [['text' => '❌ انصراف', 'callback_data' => 'massc_no']],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, "⚠️ <b>تأیید شارژ همگانی</b>\n\n💰 مبلغ برای هر کاربر: <b>" . number_format($__mamt) . "</b> تومان\n👥 تعداد کاربران (بدون مسدودها): <b>" . number_format($__mcnt) . "</b>\n💸 جمع کل: <b>" . number_format($__mamt * $__mcnt) . "</b> تومان\n\nاین کار قابل برگشت خودکار نیست. مطمئنید؟", $__mkb, 'HTML');
}
if (isset($datain) && ($datain === 'massc_ok' || $datain === 'massc_no')) {
    $__mpv = json_decode(strval($user['Processing_value'] ?? ''), true);
    $__mamt = (is_array($__mpv) && isset($__mpv['mass_charge'])) ? intval($__mpv['mass_charge']) : 0;
    if ($datain === 'massc_no' || $__mamt <= 0 || (time() - intval($__mpv['ts'] ?? 0)) > 1800) {
        if ($__mamt > 0) {
            update("user", "Processing_value", "0", "id", $from_id);
        }
        Editmessagetext($from_id, $message_id, ($datain === 'massc_no' ? "❌ شارژ همگانی لغو شد." : "⚠️ این درخواست منقضی شده یا قبلاً انجام شده است. دوباره از «شارژ همگانی» شروع کنید."), json_encode(['inline_keyboard' => []]));
        return;
    }
    // قفل: فقط یکبار اجرا شود (ضد دابلکلیک)
    $__claim = $pdo->prepare("UPDATE user SET Processing_value = '0' WHERE id = ? AND Processing_value = ?");
    $__claim->execute([strval($from_id), strval($user['Processing_value'])]);
    if ($__claim->rowCount() < 1) {
        Editmessagetext($from_id, $message_id, "⚠️ این شارژ قبلاً انجام شده است.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $__aff = 0;
    try {
        if (function_exists('ensureWalletLog')) {
            ensureWalletLog();
        }
        $pdo->beginTransaction();
        $__up = $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE User_Status IS NULL OR User_Status != 'block'");
        $__up->execute([$__mamt]);
        $__aff = intval($__up->rowCount());
        try {
            $__lg = $pdo->prepare("INSERT INTO wallet_log (id_user, type, amount, balance_after, detail, created_at) SELECT id, 'admin_add', ?, Balance, ?, ? FROM user WHERE User_Status IS NULL OR User_Status != 'block'");
            $__lg->execute([$__mamt, 'شارژ همگانی توسط ادمین', time()]);
        } catch (Throwable $e_lg) {
            error_log('mass charge log: ' . $e_lg->getMessage());
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('mass charge: ' . $e->getMessage());
        Editmessagetext($from_id, $message_id, "❌ شارژ همگانی انجام نشد (خطای دیتابیس). موجودی هیچ کاربری تغییر نکرد.", json_encode(['inline_keyboard' => []]));
        return;
    }
    $__mfmt = number_format($__mamt);
    Editmessagetext($from_id, $message_id, "✅ <b>شارژ همگانی انجام شد</b>\n💰 مبلغ هر کاربر: <b>{$__mfmt}</b> تومان\n👥 کاربران: <b>" . number_format($__aff) . "</b>\n📜 در تاریخچه تراکنش کاربران هم ثبت شد.", json_encode(['inline_keyboard' => [[['text' => '🔙 مدیریت کاربران', 'callback_data' => 'nav_user']]]], JSON_UNESCAPED_UNICODE));
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_admin_balance', "💰 <b>شارژ همگانی توسط ادمین</b>\n\n👨‍💼 ادمین: <code>{$from_id}</code>\n💰 مبلغ هر کاربر: <b>{$__mfmt}</b> تومان\n👥 کاربران: <b>" . number_format($__aff) . "</b>");
    }
    return;
}
if ($text == $textbotlang['Admin']['Discountsell']['create']) {
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['GetCode'], $backadmin, 'HTML');
    step('get_codesell', $from_id);
} elseif ($user['step'] == "get_codesell") {
    if (in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['Discountused'], $backadmin, 'HTML');
        return;
    }
    if (!preg_match('/^[A-Za-z\d]+$/', $text)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['ErrorCode'], null, 'HTML');
        return;
    }
    $values = "0";
    $stmt = $pdo->prepare("INSERT INTO DiscountSell (codeDiscount, usedDiscount, price, limitDiscount, usefirst) VALUES (?, ?, ?, ?,?)");
    $stmt->bindParam(1, $text);
    $stmt->bindParam(2, $values);
    $stmt->bindParam(3, $values);
    $stmt->bindParam(4, $values);
    $stmt->bindParam(5, $values);
    $stmt->execute();

    sendmessage($from_id, $textbotlang['Admin']['Discount']['PriceCodesell'], null, 'HTML');
    step('get_price_codesell', $from_id);
    update("user", "Processing_value", $text, "id", $from_id);
} elseif ($user['step'] == "get_price_codesell") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Balance']['Invalidprice'], $backadmin, 'HTML');
        return;
    }
    update("DiscountSell", "price", $text, "codeDiscount", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['getlimit'], $backadmin, 'HTML');
    step('getlimitcode', $from_id);
} elseif ($user['step'] == "getlimitcode") {
    update("DiscountSell", "limitDiscount", $text, "codeDiscount", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['typediscount'], $backadmin, 'HTML');
    step('getusefirst', $from_id);
} elseif ($user['step'] == "getusefirst") {
    update("DiscountSell", "usefirst", $text, "codeDiscount", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], null, 'HTML');
    if (function_exists('codesDiscountListKeyboard')) {
        list($__tx, $__kb) = codesDiscountListKeyboard(0);
        sendmessage($from_id, "🏷 <b>کد تخفیف ثبت شد</b>

" . $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['SaveCode'], $keyboardadmin, 'HTML');
    }
    step('home', $from_id);
}
if ($text == $textbotlang['Admin']['Discountsell']['remove']) {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemoveCode'], $json_list_Discount_list_admin_sell, 'HTML');
    step('remove-Discountsell', $from_id);
} elseif ($user['step'] == "remove-Discountsell") {
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['NotCode'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = ?");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['Discount']['RemovedCode'], $shopkeyboard, 'HTML');
    step('home', $from_id);
}
# نسخه 2.4.3: تأیید درصد پورسانت / مبلغ هدیه
if (in_array($from_id, $admin_ids) && isset($datain) && preg_match('/^aff(pct|gift)_ok_(\d+)$/', strval($datain), $__am)) {
    $__n = intval($__am[2]);
    if ($__am[1] === 'pct') {
        if ($__n > 100) {
            return;
        }
        update("affiliates", "affiliatespercentage", strval($__n));
        $__note = "✅ درصد پورسانت روی <b>{$__n}٪</b> تنظیم شد.";
    } else {
        if ($__n > 10000000) {
            return;
        }
        update("affiliates", "price_Discount", strval($__n));
        $__note = "✅ مبلغ هدیه استارت: <b>" . number_format($__n) . "</b> تومان";
    }
    list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
    Editmessagetext($from_id, $message_id, $__note . "\n\n" . $aff_txt, $aff_kb);
    return;
}
if ($text == $textbotlang['Admin']['keyboardadmin']['affiliate_settings']) {
    // فقط صفحه اینلاین — کیبورد پایین (reply) عوض نشود
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        sendmessage($from_id, $aff_txt, $aff_kb, 'HTML');
    }
} elseif ($datain == "affpanel_noop") {
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => 'برای تغییر، روی وضعیت (روشن/خاموش) بزنید',
        'show_alert' => false,
    ]);
} elseif ($datain == "affpanel_refresh") {
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "affpanel_active_users" || preg_match('/^affpanel_active_users_(\d+)$/', strval($datain), $__aff_pg)) {
    $__page = ($datain === 'affpanel_active_users') ? 0 : intval($__aff_pg[1] ?? 0);
    if (function_exists('listAffiliatesActiveUsersPanel')) {
        list($aff_txt, $aff_kb) = listAffiliatesActiveUsersPanel($__page);
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'تابع لیست در دسترس نیست',
            'show_alert' => true,
        ]);
    }
} elseif ($datain == "affpanel_toggle_system") {
    $aff = select("affiliates", "*", null, null, "select");
    $cur = $aff['affiliatesstatus'] ?? 'offaffiliates';
    $new = ($cur === 'onaffiliates') ? 'offaffiliates' : 'onaffiliates';
    update("affiliates", "affiliatesstatus", $new);
    // همگام‌سازی دکمه منوی کاربر
    if (function_exists('ensureFeatureSettingsColumns')) {
        ensureFeatureSettingsColumns();
    }
    update("setting", "status_affiliates_btn", ($new === 'onaffiliates') ? '1' : '0');
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "affpanel_toggle_commission") {
    $aff = select("affiliates", "*", null, null, "select");
    $cur = $aff['status_commission'] ?? 'offcommission';
    $new = ($cur === 'oncommission') ? 'offcommission' : 'oncommission';
    update("affiliates", "status_commission", $new);
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "affpanel_toggle_gift") {
    $aff = select("affiliates", "*", null, null, "select");
    $cur = $aff['Discount'] ?? 'offDiscountaffiliates';
    $new = ($cur === 'onDiscountaffiliates') ? 'offDiscountaffiliates' : 'onDiscountaffiliates';
    update("affiliates", "Discount", $new);
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "affpanel_set_pct") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($user['step'] == "setpercentage" && empty($datain)) {
    // نسخه 2.4.3: ۰ تا ۱۰۰ + تأیید
    $__n = botAdmInt($text, 0, 100);
    if ($__n === null) {
        sendmessage($from_id, "❌ یک عدد بین ۰ تا ۱۰۰ بفرستید (درصد):", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $__aff = select("affiliates", "*", null, null, "select");
    $__old = intval(is_array($__aff) ? ($__aff['affiliatespercentage'] ?? 0) : 0);
    sendmessage($from_id, "⚠️ <b>تغییر درصد پورسانت</b>\n\nفعلی: <b>{$__old}٪</b>\nجدید: <b>{$__n}٪</b>\n\nبعد از هر خرید زیرمجموعه، {$__n}٪ مبلغ به معرف پورسانت داده میشود.\nذخیره شود؟", json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، ذخیره شود', 'callback_data' => 'affpct_ok_' . $__n]],
        [['text' => '❌ انصراف', 'callback_data' => 'affpanel_refresh']],
    ]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif ($datain == "affpanel_set_giftprice") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($user['step'] == "getdiscont" && empty($datain)) {
    // نسخه 2.4.3: سقف ۱۰ میلیون + تأیید
    $__n = botAdmInt($text, 0, 10000000);
    if ($__n === null) {
        sendmessage($from_id, "❌ مبلغ را به تومان بفرستید؛ عددی بین ۰ تا ۱۰٬۰۰۰٬۰۰۰:", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    $__aff = select("affiliates", "*", null, null, "select");
    $__old = intval(is_array($__aff) ? ($__aff['price_Discount'] ?? 0) : 0);
    sendmessage($from_id, "⚠️ <b>تغییر مبلغ هدیه استارت</b>\n\nفعلی: <b>" . number_format($__old) . "</b> تومان\nجدید: <b>" . number_format($__n) . "</b> تومان\n\nذخیره شود؟", json_encode(['inline_keyboard' => [
        [['text' => '✅ بله، ذخیره شود', 'callback_data' => 'affgift_ok_' . $__n]],
        [['text' => '❌ انصراف', 'callback_data' => 'affpanel_refresh']],
    ]], JSON_UNESCAPED_UNICODE), 'HTML');
} elseif ($datain == "affpanel_set_banner") {
    sendmessage($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($user['step'] == "setbanner") {
    if (!$photo) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['invalidbanner'], $backadmin, 'HTML');
        return;
    }
    update("affiliates", "description", $caption);
    update("affiliates", "id_media", $photoid);
    sendmessage($from_id, $textbotlang['users']['affiliates']['insertbanner'], function_exists('buildAdminSettingsInlineKeyboard') ? buildAdminSettingsInlineKeyboard() : $setting_panel, 'HTML');
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        sendmessage($from_id, $aff_txt, $aff_kb, 'HTML');
    }
    step('home', $from_id);
}


if ($text == $textbotlang['Admin']['affiliate']['status']
    || $text == $textbotlang['Admin']['affiliate']['porsantafterbuy']
    || $text == $textbotlang['Admin']['affiliate']['gift']) {
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        sendmessage($from_id, $aff_txt, $aff_kb, 'HTML');
    }
} elseif ($text == $textbotlang['Admin']['affiliate']['Percentageset']) {
    sendmessage($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
    step('setpercentage', $from_id);
} elseif ($text == $textbotlang['Admin']['affiliate']['giftstart']) {
    sendmessage($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
    step('getdiscont', $from_id);
} elseif ($text == $textbotlang['Admin']['affiliate']['setbaner']) {
    sendmessage($from_id, $textbotlang['users']['affiliates']['banner'], $backadmin, 'HTML');
    step('setbanner', $from_id);
} elseif ($datain == "onaffiliates" || $datain == "offaffiliates") {
    $new = ($datain == "onaffiliates") ? "offaffiliates" : "onaffiliates";
    update("affiliates", "affiliatesstatus", $new);
    update("setting", "status_affiliates_btn", ($new === 'onaffiliates') ? '1' : '0');
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "oncommission" || $datain == "offcommission") {
    $new = ($datain == "oncommission") ? "offcommission" : "oncommission";
    update("affiliates", "status_commission", $new);
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
} elseif ($datain == "onDiscountaffiliates" || $datain == "offDiscountaffiliates") {
    $new = ($datain == "onDiscountaffiliates") ? "offDiscountaffiliates" : "onDiscountaffiliates";
    update("affiliates", "Discount", $new);
    if (function_exists('buildAffiliatesAdminPanel')) {
        list($aff_txt, $aff_kb) = buildAffiliatesAdminPanel();
        Editmessagetext($from_id, $message_id, $aff_txt, $aff_kb);
    }
}

if (preg_match('/rejectremoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $requestcheck = select("cancel_service", "*", "username", $usernamepanel, "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram(
            'answerCallbackQuery',
            array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['status']['residaccepted'],
                'show_alert' => true,
                'cache_time' => 5,
            )
        );
        return;
    }
    step("descriptionsrequsts", $from_id);
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['status']['rejectrequest'], $backuser, 'HTML');

} elseif ($user['step'] == "descriptionsrequsts") {
    sendmessage($from_id, $textbotlang['users']['status']['acceptrequestnote'], $keyboardadmin, 'HTML');
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    update("cancel_service", "status", "reject", "username", $user['Processing_value']);
    update("cancel_service", "description", $text, "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], sprintf($textbotlang['users']['status']['rejectsendtouser'], $user['Processing_value'], $text), null, 'HTML');

} elseif (preg_match('/remoceserviceadmin-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $requestcheck = select("cancel_service", "*", "username", $username, "select");
    if ($requestcheck['status'] == "accept" || $requestcheck['status'] == "reject") {
        telegram(
            'answerCallbackQuery',
            array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['status']['residaccepted'],
                'show_alert' => true,
                'cache_time' => 5,
            )
        );
        return;
    }
    step("getpricerequests", $from_id);
    update("user", "Processing_value", $username, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['status']['getpriceforadd'], $backuser, 'HTML');

} elseif ($user['step'] == "getpricerequests") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['invalidvalue'], null, 'HTML');
    }
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    if ($nameloc['price_product'] < $text) {
        sendmessage($from_id, $textbotlang['Admin']['maxvalue'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['status']['acceptrequestnote'], $keyboardadmin, 'HTML');
    step("home", $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if (isset($DataUserOut['status'])) {
        $ManagePanel->RemoveUser($marzban_list_get['name_panel'], $user['Processing_value']);
    }
    update("cancel_service", "status", "accept", "username", $user['Processing_value']);
    update("invoice", "status", "removedbyadmin", "username", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($nameloc['id_user'], sprintf($textbotlang['users']['status']['acceptrequest'], $user['Processing_value']), null, 'HTML');
    $pricecancel = number_format(intval($text));
    if (intval($text) != 0) {
        $Balance_id_cancel_fee = atomicAdjustBalance($nameloc['id_user'], intval($text));
        sendmessage($nameloc['id_user'], sprintf($textbotlang['users']['status']['addedbalanceremove'], $pricecancel, number_format(intval($Balance_id_cancel_fee))), botMenuForBalance($nameloc['id_user']), 'HTML');
    }
    if (function_exists('sendChannelReport')) {
        // کانفیگ = نام کاربری سرویس (نه یوزرنیم تلگرام ادمین/کاربر)
        $config_user = $nameloc['username'] ?? ($user['Processing_value'] ?? '');
        sendChannelReport('rpt_remove', sprintf(
            $textbotlang['Admin']['Report']['reportremove'],
            $from_id,
            $pricecancel,
            $config_user,
            $nameloc['id_user']
        ));
    }
}
if ($text == $textbotlang['Admin']['managepanel']['keyboardpanel']['on_hold_status']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panel['onholdstatus'] == null) {
        update("marzban_panel", "onholdstatus", "offonhold", "name_panel", $user['Processing_value']);
    }
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $onhold_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['onholdstatus'], 'callback_data' => $panel['onholdstatus']],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['Admin']['Status']['onhold'], $onhold_Status, 'HTML');
}
if ($datain == "ononhold") {
    update("marzban_panel", "onholdstatus", "offonhold", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $onhold_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['onholdstatus'], 'callback_data' => $panel['onholdstatus']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['offstatus'], $onhold_Status);
} elseif ($datain == "offonhold") {
    update("marzban_panel", "onholdstatus", "ononhold", "name_panel", $user['Processing_value']);
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $onhold_Status = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $panel['onholdstatus'], 'callback_data' => $panel['onholdstatus']],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['onstatus'], $onhold_Status);
}
if ($text == $textbotlang['Admin']['keyboardadmin']['settingscron']) {
    ensureSmartCronSettings();
    ensureSmartCronStateTable();
    sendmessage($from_id, botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
}
if ($text == $textbotlang['Admin']['cron']['test']['active']) {
    sendmessage($from_id, "⚠️ کرون‌های قدیمی غیرفعال شده‌اند.\nاز «زمانبندیها (کرون)» فقط <b>کرون هوشمند</b> را فعال و تنظیم کنید.", null, 'HTML');
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['test']['disable']) {
    // نسخه 2.4.1: crontab سرور دستکاری نمیشود؛ همه زمانبندیها با کرون هوشمند است
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند و چیزی برای غیرفعال کردن نیست.\nهمه زمانبندیها از اینجا مدیریت میشوند:", buildSmartCronAdminKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند.", $keyboardadmin, 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['volume']['active']) {
    sendmessage($from_id, "⚠️ کرون حجم قدیمی حذف شده است.\nاخطار حجم از طریق <b>کرون هوشمند</b> مدیریت می‌شود.", null, 'HTML');
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['volume']['disable']) {
    // نسخه 2.4.1: crontab سرور دستکاری نمیشود؛ همه زمانبندیها با کرون هوشمند است
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند و چیزی برای غیرفعال کردن نیست.\nهمه زمانبندیها از اینجا مدیریت میشوند:", buildSmartCronAdminKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند.", $keyboardadmin, 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['time']['active']) {
    sendmessage($from_id, "⚠️ کرون زمان قدیمی حذف شده است.\nاخطار زمان از طریق <b>کرون هوشمند</b> مدیریت می‌شود.", null, 'HTML');
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['time']['disable']) {
    // نسخه 2.4.1: crontab سرور دستکاری نمیشود؛ همه زمانبندیها با کرون هوشمند است
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند و چیزی برای غیرفعال کردن نیست.\nهمه زمانبندیها از اینجا مدیریت میشوند:", buildSmartCronAdminKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند.", $keyboardadmin, 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['remove']['active']) {
    sendmessage($from_id, "⚠️ کرون حذف قدیمی حذف شده است.\nحذف فاکتور غایب از طریق <b>کرون هوشمند</b> مدیریت می‌شود.", null, 'HTML');
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
    }
}
if ($text == $textbotlang['Admin']['cron']['remove']['disable']) {
    // نسخه 2.4.1: crontab سرور دستکاری نمیشود؛ همه زمانبندیها با کرون هوشمند است
    if (function_exists('buildSmartCronAdminKeyboard')) {
        ensureSmartCronSettings();
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند و چیزی برای غیرفعال کردن نیست.\nهمه زمانبندیها از اینجا مدیریت میشوند:", buildSmartCronAdminKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, "ℹ️ کرونهای قدیمی دیگر استفاده نمیشوند.", $keyboardadmin, 'HTML');
    }
}
if ($text == $textbotlang['Admin']['keyboardadmin']['user_search']) {
    // نسخه 2.3.9: جستجو با آیدی عددی، یوزرنیم تلگرام یا یوزرنیم سرویس
    sendmessage($from_id, "🔍 <b>جستجوی کاربر</b>\n\nیکی از اینها را بفرستید:\n• آیدی عددی (مثلاً <code>123456789</code>)\n• یوزرنیم تلگرام (مثلاً <code>@username</code>)\n• یوزرنیم سرویس (مثلاً <code>ali_123</code>)", $backadmin, 'HTML');
    step('show_infos', $from_id);
} elseif ($user['step'] == "show_infos") {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__q = trim(function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text));
    $__found = null;
    $__how = '';
    if ($__q !== '' && ctype_digit($__q)) {
        $__r = select("user", "id", "id", $__q, "select");
        if (is_array($__r) && !empty($__r['id'])) {
            $__found = $__r['id'];
            $__how = 'آیدی عددی';
        }
    }
    if ($__found === null && $__q !== '') {
        $__r = select("user", "id", "username", ltrim($__q, '@'), "select");
        if (is_array($__r) && !empty($__r['id'])) {
            $__found = $__r['id'];
            $__how = 'یوزرنیم تلگرام';
        }
    }
    if ($__found === null && $__q !== '' && strpos($__q, '@') !== 0) {
        $__r = select("invoice", "id_user", "username", $__q, "select");
        if (is_array($__r) && !empty($__r['id_user'])) {
            $__found = $__r['id_user'];
            $__how = 'یوزرنیم سرویس';
        }
    }
    if ($__found === null) {
        sendmessage($from_id, "❌ کاربری پیدا نشد.\nآیدی عددی، یوزرنیم تلگرام (@...) یا یوزرنیم سرویس را دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    step('home', $from_id);
    if ($__how !== 'آیدی عددی') {
        sendmessage($from_id, "🔎 پیدا شد با {$__how} → آیدی <code>" . htmlspecialchars(strval($__found), ENT_QUOTES, 'UTF-8') . "</code>", $keyboardadmin, 'HTML');
    }
    if (function_exists('sendAdminUserInfo')) {
        sendAdminUserInfo($from_id, $__found);
    }
}
if ($text == $textbotlang['Admin']['cron']['remove']['timeset']) {
    sendmessage($from_id, $textbotlang['Admin']['cron']['remove']['dectime'], $backadmin, 'HTML');
    step("gettimeremove", $from_id);
} elseif ($user['step'] == "gettimeremove") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['cron']['remove']['invalidtime'], $backadmin, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['Admin']['cron']['remove']['timeseted'], $keyboardcronjob, 'HTML');
    step("home", $from_id);
    update("setting", "removedayc", $text, null, null);
}
if ($text == $textbotlang['users']['status']['manageService']) {
    sendmessage($from_id, $textbotlang['users']['status']['manageServicedec'], $backadmin, 'HTML');
    step('getservceid', $from_id);
} elseif ($user['step'] == "getservceid") {
    $userdata = getuserm($text, $user['Processing_value']);
    if (isset($userdata['detail']) and $userdata['detail'] == "User not found") {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['keyboardpanel']['usernotfount'], null, 'HTML');
        return;
    }
    update("marzban_panel", "proxies", json_encode($userdata['service_ids']), "name_panel", $user['Processing_value']);
    step("home", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['setsetting'], $optionMarzneshin, 'HTML');
} elseif ($text == $textbotlang['Admin']['Help']['edithelp']) {
    sendmessage($from_id, $textbotlang['Admin']['Help']['selecthelpforedit'], $json_list_help, 'HTML');
    step("getnameforedite", $from_id);
} elseif ($user['step'] == "getnameforedite") {
    sendmessage($from_id, $textbotlang['users']['selectoption'], $helpedit, 'HTML');
    update("user", "Processing_value", $text, "id", $from_id);
    step("home", $from_id);

} elseif ($text == $textbotlang['Admin']['Help']['change']['name']) {
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['sendnewname'], $backadmin, 'HTML');
    step('changenamehelp', $from_id);
} elseif ($user['step'] == "changenamehelp") {
    if (strlen($text) >= 150) {
        sendmessage($from_id, $textbotlang['Admin']['Help']['change']['namemax'], null, 'HTML');
        return;
    }
    update("help", "name_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['updated'], $json_list_helpkey, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['Help']['change']['dec']) {
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['newdec'], $backadmin, 'HTML');
    step('changedeshelp', $from_id);
} elseif ($user['step'] == "changedeshelp") {
    update("help", "Description_os", $text, "name_os", $user['Processing_value']);
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['updated'], $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['Help']['change']['editmedia']) {
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['editmedianew'], $backadmin, 'HTML');
    step('changemedia', $from_id);
} elseif ($user['step'] == "changemedia") {
    if ($photo) {
        if (isset($photoid))
            update("help", "Media_os", $photoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "photo", "name_os", $user['Processing_value']);
    } elseif ($video) {
        if (isset($videoid))
            update("help", "Media_os", $videoid, "name_os", $user['Processing_value']);
        update("help", "type_Media_os", "video", "name_os", $user['Processing_value']);
    }
    sendmessage($from_id, $textbotlang['Admin']['Help']['change']['updated'], $helpedit, 'HTML');
    step('home', $from_id);
} elseif ($text == $textbotlang['Admin']['managepanel']['setinbound']) {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (is_array($panel) && strval($panel['type'] ?? '') === 'pasarguard') {
        sendmessage($from_id, "✅ برای <b>پاسارگارد</b> تنظیم اینباند/پروتکل لازم نیست.\nگروه‌ها مستقیم از پنل خوانده می‌شوند.\nاز دکمه «گروه‌های پنل» می‌توانید لیست را ببینید.", $optionMarzban, 'HTML');
        step('home', $from_id);
    } else {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['setinbounddec'], $backadmin, 'HTML');
        step("setinboundandprotocol", $from_id);
    }
} elseif ($user['step'] == "setinboundandprotocol") {
    $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (($panel['type'] == "marzban" || $panel['type'] == "pasarguard")) {
        $DataUserOut = getuser($text, $user['Processing_value']);
        if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
            sendmessage($from_id, $textbotlang['users']['status']['usernotfound'], null, 'html');
            return;
        }
        foreach ($DataUserOut['proxies'] as $key => &$value) {
            if ($key == "shadowsocks") {
                unset($DataUserOut['proxies'][$key]['password']);
            } elseif ($key == "trojan") {
                unset($DataUserOut['proxies'][$key]['password']);
            } else {
                unset($DataUserOut['proxies'][$key]['id']);
            }
            if (count($DataUserOut['proxies'][$key]) == 0) {
                $DataUserOut['proxies'][$key] = new stdClass();
            }
        }
        update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
        update("marzban_panel", "proxies", json_encode($DataUserOut['proxies']), "name_panel", $user['Processing_value']);
    } else {
        $data = GetClientsS_UI($text, $panel['name_panel']);
        if (count($data) == 0) {
            sendmessage($from_id, $textbotlang['Admin']['managepanel']['keyboardpanel']['usernotfount'], $options_ui, 'HTML');
            return;
        }
        $servies = [];
        foreach ($data['inbounds'] as $service) {
            $servies[] = $service;
        }
        update("marzban_panel", "proxies", json_encode($servies, true), "name_panel", $user['Processing_value']);
    }
    $__kb_after_inb = $optionMarzban;
    if (isset($panel['type']) && $panel['type'] === 'marzban' && isset($optionMarzbanPanel)) {
        $__kb_after_inb = $optionMarzbanPanel;
    } elseif (isset($panel['type']) && $panel['type'] === 'pasarguard' && isset($optionPasarguard)) {
        $__kb_after_inb = $optionPasarguard;
    }
    sendmessage($from_id, $textbotlang['Admin']['managepanel']['setedinbound'], $__kb_after_inb, 'HTML');
    step("home", $from_id);
} elseif ($text == $textbotlang['Admin']['keyboardadmin']['seetingstatus']) {
    if (function_exists('ensureFeatureSettingsColumns')) { ensureFeatureSettingsColumns(); }
    if ($setting['Bot_Status'] == "✅  ربات روشن است") {
        update("setting", "Bot_Status", "1");
    } elseif ($setting['Bot_Status'] == "❌ ربات خاموش است") {
        update("setting", "Bot_Status", "0");
    }

    if ($setting['roll_Status'] == "✅ تایید قانون روشن است") {
        update("setting", "roll_Status", "1");
    } elseif ($setting['roll_Status'] == "❌ تایید قوانین خاموش است") {
        update("setting", "roll_Status", "0");
    }

    if ($setting['NotUser'] == "onnotuser") {
        update("setting", "NotUser", "1");
    } elseif ($setting['NotUser'] == "offnotuser") {
        update("setting", "NotUser", "0");
    }

    if ($setting['help_Status'] == "✅ آموزش فعال است") {
        update("setting", "help_Status", "1");
    } elseif ($setting['help_Status'] == "❌ آموزش غیرفعال است") {
        update("setting", "help_Status", "0");
    }

    if ($setting['get_number'] == "✅ تایید شماره موبایل روشن است") {
        update("setting", "get_number", "1");
    } elseif ($setting['get_number'] == "❌ احرازهویت شماره تماس غیرفعال است") {
        update("setting", "get_number", "0");
    }

    if ($setting['iran_number'] == "✅ احرازشماره ایرانی روشن است") {
        update("setting", "iran_number", "1");
    } elseif ($setting['iran_number'] == "❌ بررسی شماره ایرانی غیرفعال است") {
        update("setting", "iran_number", "0");
    }
    // نسخه 2.4.3: صفحه گروهبندیشده قابلیتها (بدون بررسی crontab)
    list($__ftx, $__fkb) = botFeaturesPage();
    if (!empty($GLOBALS['__admin_nav_edit_mid'])) {
        $__mid_feat = intval($GLOBALS['__admin_nav_edit_mid']);
        unset($GLOBALS['__admin_nav_edit_mid']);
        Editmessagetext($from_id, $__mid_feat, $__ftx, $__fkb);
    } else {
        sendmessage($from_id, $__ftx, $__fkb, 'HTML');
    }
} elseif (preg_match('/^editstsuts-(.*)-(.*)/', $datain, $dataget)) {
    if (function_exists('ensureFeatureSettingsColumns')) { ensureFeatureSettingsColumns(); }
    $type = $dataget[1];
    $value = $dataget[2];
    if ($type == "statusbot") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "Bot_Status", $valuenew);
    } elseif ($type == "roll_Status") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "roll_Status", $valuenew);
    } elseif ($type == "NotUser") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "NotUser", $valuenew);
    } elseif ($type == "help_Status") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "help_Status", $valuenew);
    } elseif ($type == "get_number") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "get_number", $valuenew);
    } elseif ($type == "iran_number") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "iran_number", $valuenew);
    } elseif ($type == "verify") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "status_verify", $valuenew);
    } elseif ($type == "category") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "statuscategory", $valuenew);
    } elseif ($type == "copycart") {
        if ($value == "1") {
            $valuenew = "0";
        } else {
            $valuenew = "1";
        }
        update("setting", "copy_cart", $valuenew);
    
    } elseif ($type == "force_channel") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "force_channel", $valuenew);
    } elseif ($type == "show_balance") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "show_balance", $valuenew);
    } elseif ($type == "status_usertest") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_usertest", $valuenew);
    } elseif ($type == "status_buy") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_buy", $valuenew);
    } elseif ($type == "status_extend") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_extend", $valuenew);
    } elseif ($type == "status_search_service") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_search_service", $valuenew);
    } elseif ($type == "status_affiliates_btn") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_affiliates_btn", $valuenew);
    } elseif ($type == "status_tariff_list") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_tariff_list", $valuenew);
    } elseif ($type == "status_extra_volume") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_extra_volume", $valuenew);
    } elseif ($type == "status_deposit") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_deposit", $valuenew);
    } elseif ($type == "status_support") {
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_support", $valuenew);
    } elseif ($type == "status_reseller_panel") {
        if (function_exists('ensureResellerFeatureSetting')) { ensureResellerFeatureSetting(); }
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_reseller_panel", $valuenew);
    } elseif ($type == "status_reseller_request") {
        if (function_exists('ensureFeatureSettingsColumns')) { ensureFeatureSettingsColumns(); }
        $valuenew = ($value == "1") ? "0" : "1";
        update("setting", "status_reseller_request", $valuenew);

    } elseif ($type == "Automatic_confirmation") {
        // سازگاری قدیمی — تنظیم اصلی در بخش مالی است
        $enable = ($value != "1");
        if (function_exists('setPaySettingValue')) {
            setPaySettingValue('auto_cart_confirm', $enable ? '1' : '0');
        }
        if (function_exists('syncAutoCartCron')) {
            $ok = syncAutoCartCron($enable);
            if ($enable && $ok) {
                sendmessage($from_id, "✅ تأیید خودکار روشن شد و کرون در crontab نصب شد.", null, 'HTML');
            } elseif (!$enable && $ok) {
                sendmessage($from_id, "✅ تأیید خودکار خاموش شد و کرون از crontab حذف شد.", null, 'HTML');
            } elseif ($enable && !$ok) {
                $cronCommand = function_exists('getAutoCartCronCommand') ? getAutoCartCronCommand() : "*/4 * * * * curl https://$domainhosts/cron/croncard.php";
                sendmessage($from_id, sprintf($textbotlang['Admin']['cron']['active_manual_card'], $cronCommand), null, 'HTML');
            } elseif (!$enable && !$ok) {
                sendmessage($from_id, "⚠️ تأیید خودکار خاموش شد؛ حذف کرون از crontab ممکن نشد — دستی خط croncard.php را پاک کنید.", null, 'HTML');
            }
        } elseif ($enable && !(function_exists('shell_exec') && is_callable('shell_exec'))) {
            $cronCommand = function_exists('getAutoCartCronCommand') ? getAutoCartCronCommand() : "*/4 * * * * curl https://$domainhosts/cron/croncard.php";
            sendmessage($from_id, sprintf($textbotlang['Admin']['cron']['active_manual_card'], $cronCommand), null, 'HTML');
        }
    }
    if (function_exists('botFeatureToggleReport') && isset($type)) {
        botFeatureToggleReport(strval($type), isset($valuenew) ? strval($valuenew) : null);
    }
    list($__ftx, $__fkb) = botFeaturesPage();
    Editmessagetext($from_id, $message_id, $__ftx, $__fkb);
} elseif (preg_match('/verify_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userunverify = select("user", "*", "id", $iduser, "select");
    if ($userunverify['verify'] == "1") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['alreadyverified'] ?? 'کاربر از قبل احراز شده است', $backadmin, 'HTML');
        return;
    }
    update("user", "verify", "1", "id", $iduser);
    $__vf_id = htmlspecialchars(strval($iduser), ENT_QUOTES, 'UTF-8');
    $__vf_msg = ($textbotlang['Admin']['ManageUser']['verifyeduser'] ?? '✅ کاربر احراز شد')
        . "\n🆔 کاربر: <code>{$__vf_id}</code>";
    sendmessage($from_id, $__vf_msg, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/verifyun_(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    $userunverify = select("user", "*", "id", $iduser, "select");
    if ($userunblock['verify'] == "0") {
        sendmessage($from_id, $textbotlang['Admin']['ManageUser']['verifyed'], $backadmin, 'HTML');
        return;
    }
    update("user", "verify", "0", "id", $iduser);
    $__uv_id = htmlspecialchars(strval($iduser), ENT_QUOTES, 'UTF-8');
    $__uv_msg = ($textbotlang['Admin']['ManageUser']['unverifyed'] ?? '✅ احراز هویت برداشته شد')
        . "\n🆔 کاربر: <code>{$__uv_id}</code>";
    sendmessage($from_id, $__uv_msg, $keyboardadmin, 'HTML');
    step('home', $from_id);
} elseif ($text == '📁 مدیریت دسته‌بندی' || $text == $textbotlang['Admin']['category']['add'] || $text == $textbotlang['Admin']['category']['remove']) {
    if (function_exists('adminCategoriesListKeyboard')) {
        list($__tx, $__kb) = adminCategoriesListKeyboard();
        sendmessage($from_id, $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['category']['getname'], $backadmin, 'HTML');
        step("getremarkcategory", $from_id);
    }
} elseif ($user['step'] == "getremarkcategory") {
    $stmt = $pdo->prepare("INSERT INTO category (remark) VALUES (?)");
    $stmt->bindParam(1, $text);
    $stmt->execute();
    if (function_exists('adminCategoriesListKeyboard')) {
        list($__tx, $__kb) = adminCategoriesListKeyboard();
        sendmessage($from_id, ($textbotlang['Admin']['category']['addedcategry'] ?? '✅') . "

" . $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['category']['addedcategry'], $shopkeyboard, 'HTML');
    }
    step("home", $from_id);
} elseif ($user['step'] == "removecategory") {
    $stmt = $pdo->prepare("DELETE FROM category WHERE remark = :remark ");
    $stmt->bindParam(':remark', $text);
    $stmt->execute();
    if (function_exists('adminCategoriesListKeyboard')) {
        list($__tx, $__kb) = adminCategoriesListKeyboard();
        sendmessage($from_id, ($textbotlang['Admin']['category']['removedcategory'] ?? '✅') . "

" . $__tx, $__kb, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['Admin']['category']['removedcategory'], $shopkeyboard, 'HTML');
    }
    step("home", $from_id);
} elseif ($text == $textbotlang['Admin']['ManageUser']['searchorder']) {
    sendmessage($from_id, $textbotlang['Admin']['ManageUser']['ViewOrder'], $backadmin, 'HTML');
    step("getidfororder", $from_id);
} elseif ($user['step'] == "getidfororder") {
    // نسخه 2.3.9: بازگشت + پیام خطا با کیبورد
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $OrderUser = select("invoice", "*", "username", trim(strval($text)), "select");
    if (!$OrderUser) {
        sendmessage($from_id, ($textbotlang['Admin']['ManageUser']['OrderNotFound'] ?? '❌ سفارش پیدا نشد.') . "\nیوزرنیم سرویس را دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    $timeacc = jdate('Y/m/d H:i:s', $OrderUser['time_sell']);
    sendmessage($from_id, sprintf($textbotlang['Admin']['ManageUser']['Datails'], $OrderUser['id_invoice'], $OrderUser['Status'], $OrderUser['id_user'], $OrderUser['username'], $OrderUser['Service_location'], $OrderUser['name_product'], $OrderUser['price_product'], $OrderUser['Volume'], $OrderUser['Service_time'], $timeacc), $User_Services, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/addordermanualـ(\w+)/', $datain, $dataget)) {
    $iduser = $dataget[1];
    savedata("clear", "userid", $iduser);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['onestep'], $backadmin, 'HTML');
    step('getusernameconfig', $from_id);
} elseif ($user['step'] == "getusernameconfig") {
    $text = strtolower($text);
    if (!preg_match('/^\w{3,32}$/', $text)) {
        sendmessage($from_id, $textbotlang['users']['status']['Invalidusername'], $backuser, 'html');
        return;
    }
    if (in_array($text, $usernameinvoice)) {
        sendmessage($from_id, $textbotlang['Admin']['addorder']['user_exits'], null, 'HTML');
        return;
    }
    savedata("save", "username", $text);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['getname_panel'], $json_list_marzban_panel, 'HTML');
    step('getnamepanelconfig', $from_id);
} elseif ($user['step'] == "getnamepanelconfig") {
    savedata("save", "name_panel", $text);
    sendmessage($from_id, $textbotlang['Admin']['addorder']['get_product'], $json_list_product_list_admin, 'HTML');
    step('stependforaddorder', $from_id);
} elseif ($user['step'] == "stependforaddorder") {
    $userdata = json_decode($user['Processing_value'], true);
    $sql = "SELECT * FROM product  WHERE name_product = :name_product AND (Location = :location OR Location = '/all') LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
    $stmt->bindParam(':location', $userdata['name_panel'], PDO::PARAM_STR);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    $panel = select("marzban_panel", "*", "name_panel", $userdata['name_panel'], "select");
    $date = time();
    $randomString = bin2hex(random_bytes(2));
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status) VALUES (:id_user, :id_invoice, :username, :time_sell, :Service_location, :name_product, :price_product, :Volume, :Service_time, :Status)");
    $Status = "active";
    $stmt->bindParam(':id_user', $userdata['userid'], PDO::PARAM_STR);
    $stmt->bindParam(':id_invoice', $randomString, PDO::PARAM_STR);
    $stmt->bindParam(':username', $userdata['username'], PDO::PARAM_STR);
    $stmt->bindParam(':time_sell', $date, PDO::PARAM_STR);
    $stmt->bindParam(':Service_location', $userdata['name_panel'], PDO::PARAM_STR);
    $stmt->bindParam(':name_product', $info_product['name_product'], PDO::PARAM_STR);
    $stmt->bindParam(':price_product', $info_product['price_product'], PDO::PARAM_STR);
    $stmt->bindParam(':Volume', $info_product['Volume_constraint'], PDO::PARAM_STR);
    $stmt->bindParam(':Service_time', $info_product['Service_time'], PDO::PARAM_STR);
    $stmt->bindParam(':Status', $Status, PDO::PARAM_STR);
    $stmt->execute();
    sendmessage($from_id, $textbotlang['Admin']['addorder']['added_order'], $keyboardadmin, 'HTML');
    step('home', $from_id);
}

#----------------[ Deposit min/max ]------------------#
if ($text == $textbotlang['Admin']['deposit']['menu'] || $datain == "deposit_limits_settings") {
    // نسخه 2.3.3: صفحه شیشهای
    ensurePaySetting('min_deposit', '100000');
    ensurePaySetting('max_deposit', '10000000');
    step('home', $from_id);
    list($__dt, $__dk) = buildDepositLimitsPage();
    if ($datain == "deposit_limits_settings" && !empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__dt, $__dk);
    } else {
        sendmessage($from_id, $__dt, $__dk, 'HTML');
    }
} elseif ($datain == 'dep_set_min' || $datain == 'dep_set_max') {
    $lim = getDepositLimits();
    $__is_min = ($datain == 'dep_set_min');
    sendmessage($from_id, ($__is_min ? "🔻 حداقل واریز فعلی: <b>" . number_format(intval($lim['min'])) . "</b> تومان\n\nحداقل جدید را به تومان بفرستید (فقط عدد):" : "🔺 حداکثر واریز فعلی: <b>" . number_format(intval($lim['max'])) . "</b> تومان\n\nحداکثر جدید را به تومان بفرستید (فقط عدد):"), $backadmin, 'HTML');
    step($__is_min ? 'deposit_set_min' : 'deposit_set_max', $from_id);
} elseif ($user['step'] == 'deposit_set_min' || $user['step'] == 'deposit_set_max') {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__v_raw = function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text);
    $__v_raw = str_replace([',', '٬', '،', ' '], '', trim($__v_raw));
    if (!ctype_digit($__v_raw) || intval($__v_raw) < 1000 || intval($__v_raw) > 1000000000) {
        sendmessage($from_id, "❌ یک عدد بین ۱,۰۰۰ تا ۱,۰۰۰,۰۰۰,۰۰۰ تومان بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__v = intval($__v_raw);
    $lim = getDepositLimits();
    if ($user['step'] == 'deposit_set_min') {
        if ($__v > intval($lim['max'])) {
            sendmessage($from_id, "❌ حداقل نمیتواند از حداکثر فعلی (" . number_format(intval($lim['max'])) . " تومان) بیشتر باشد. دوباره بفرستید:", $backadmin, 'HTML');
            return;
        }
        ensurePaySetting('min_deposit', '100000');
        update("PaySetting", "ValuePay", strval($__v), "NamePay", "min_deposit");
    } else {
        if ($__v < intval($lim['min'])) {
            sendmessage($from_id, "❌ حداکثر نمیتواند از حداقل فعلی (" . number_format(intval($lim['min'])) . " تومان) کمتر باشد. دوباره بفرستید:", $backadmin, 'HTML');
            return;
        }
        ensurePaySetting('max_deposit', '10000000');
        update("PaySetting", "ValuePay", strval($__v), "NamePay", "max_deposit");
    }
    step('home', $from_id);
    list($__dt, $__dk) = buildDepositLimitsPage("✅ ذخیره شد.");
    sendmessage($from_id, $__dt, $__dk, 'HTML');
}




#----------------[ Currency wallets ]------------------#
if ($datain == "setting_currency_wallets") {
    ensureCurrencyPaySettings();
    sendmessage(
        $from_id,
        ($textbotlang['Admin']['currency']['menu'] ?? 'تنظیم کیف‌پول ارزی') . "\n\n" . ($textbotlang['Admin']['currency']['toggle_hint'] ?? ''),
        buildCurrencyAdminKeyboard(),
        'HTML'
    );
}
if (preg_match('/^cur_toggle_(\w+)$/', strval($datain), $mcur)) {
    $coin = $mcur[1];
    $meta = getCurrencyCoinsMeta()[$coin] ?? null;
    if ($meta) {
        ensurePaySetting($meta['enabled_key'], '1');
        $cur = isCurrencyCoinEnabled($coin) ? '0' : '1';
        update("PaySetting", "ValuePay", $cur, "NamePay", $meta['enabled_key']);
        telegram('editMessageReplyMarkup', [
            'chat_id' => $from_id,
            'message_id' => $message_id,
            'reply_markup' => buildCurrencyAdminKeyboard(),
        ]);
    }
}
if (preg_match('/^cur_setaddr_(\w+)$/', strval($datain), $mcur)) {
    $coin = $mcur[1];
    $meta = getCurrencyCoinsMeta()[$coin] ?? null;
    if ($meta) {
        update("user", "Processing_value", $coin, "id", $from_id);
        $cur = getCurrencyWalletAddress($coin);
        if ($cur === '') $cur = '-';
        sendmessage($from_id, sprintf($textbotlang['Admin']['currency']['get_address'], $cur), $backadmin, 'HTML');
        step('set_wallet_coin', $from_id);
    }
} elseif ($user['step'] == 'set_wallet_coin') {
    $coin = $user['Processing_value'];
    $meta = getCurrencyCoinsMeta()[$coin] ?? null;
    if (!$meta) {
        step('home', $from_id);
        return;
    }
    $val = ($text === '-') ? '' : trim($text);
    ensurePaySetting($meta['wallet_key'], '');
    update("PaySetting", "ValuePay", $val, "NamePay", $meta['wallet_key']);
    sendmessage($from_id, $textbotlang['Admin']['currency']['saved'], buildCurrencyAdminKeyboard(), 'HTML');
    step('home', $from_id);
}



if ($datain == "cur_discount_toggle") {
    ensureCurrencyPaySettings();
    $cur = getPaySettingValue('currency_discount_status', '0');
    $new = ($cur === '1' || $cur === 'on') ? '0' : '1';
    update("PaySetting", "ValuePay", $new, "NamePay", "currency_discount_status");
    telegram('editMessageReplyMarkup', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'reply_markup' => buildCurrencyAdminKeyboard(),
    ]);
}
if ($datain == "cur_discount_set") {
    ensureCurrencyPaySettings();
    $p = getCurrencyDiscountPercent();
    sendmessage($from_id, sprintf($textbotlang['Admin']['currency']['discount_get'], $p), $backadmin, 'HTML');
    step('set_currency_discount', $from_id);
} elseif ($user['step'] == 'set_currency_discount') {
    if (!is_numeric($text) || floatval($text) < 0 || floatval($text) > 90) {
        sendmessage($from_id, "❌ عدد بین 0 تا 90 وارد کنید.", $backadmin, 'HTML');
        return;
    }
    ensureCurrencyPaySettings();
    $p = floatval($text);
    update("PaySetting", "ValuePay", strval($p), "NamePay", "currency_discount_percent");
    if ($p > 0) {
        update("PaySetting", "ValuePay", '1', "NamePay", "currency_discount_status");
    }
    $pshow = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.');
    sendmessage($from_id, sprintf($textbotlang['Admin']['currency']['discount_saved'], $pshow), buildCurrencyAdminKeyboard(), 'HTML');
    step('home', $from_id);
}


#----------------[ Balance packages ]------------------#
if ($datain == "balance_packages_settings") {
    $list = getBalancePackages();
    $msg = $textbotlang['Admin']['balance_pkg']['list'];
    if (count($list) == 0) {
        $msg .= "\n\n" . ($textbotlang['Admin']['balance_pkg']['empty'] ?? '');
    }
    sendmessage($from_id, $msg, buildBalancePackageAdminKeyboard(), 'HTML');
}
if ($datain == "balpkgadm_back") {
    $__fin_tx = "💳 <b>تنظیمات مالی</b>

از این بخش درگاه‌ها، حداقل/حداکثر واریز، پکیج شارژ و تأیید خودکار رسید را مدیریت کنید.";
    $__fin_kb = function_exists('buildFinanceKeyboard') ? buildFinanceKeyboard() : null;
    if (!empty($message_id)) {
        Editmessagetext($from_id, $message_id, $__fin_tx, $__fin_kb);
    } else {
        sendmessage($from_id, $__fin_tx, $__fin_kb, 'HTML');
    }
    step('home', $from_id);
}
if ($datain == "balpkgadm_add") {

    sendmessage($from_id, $textbotlang['Admin']['balance_pkg']['get_amount'], $backadmin, 'HTML');
    step('balpkg_add_amount', $from_id);
} elseif ($user['step'] == 'balpkg_add_amount') {
    if (!is_numeric($text) || intval($text) < 1000) {
        sendmessage($from_id, "❌ مبلغ معتبر وارد کنید.", $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", strval(intval($text)), "id", $from_id);
    sendmessage($from_id, $textbotlang['Admin']['balance_pkg']['get_discount'], $backadmin, 'HTML');
    step('balpkg_add_discount', $from_id);
} elseif ($user['step'] == 'balpkg_add_discount') {
    if (!is_numeric($text) || floatval($text) < 0 || floatval($text) > 90) {
        sendmessage($from_id, "❌ درصد بین 0 تا 90 باشد.", $backadmin, 'HTML');
        return;
    }
    $amount = intval($user['Processing_value']);
    $discount = floatval($text);
    $list = getBalancePackages();
    $list[] = [
        'id' => bin2hex(random_bytes(4)),
        'amount' => $amount,
        'discount' => $discount,
        'title' => '',
    ];
    saveBalancePackages($list);
    sendmessage($from_id, $textbotlang['Admin']['balance_pkg']['saved'], buildBalancePackageAdminKeyboard(), 'HTML');
    step('home', $from_id);
}
if (preg_match('/^balpkgadm_del_(.+)$/', strval($datain), $m_del)) {
    $id = $m_del[1];
    $list = array_values(array_filter(getBalancePackages(), function ($p) use ($id) {
        return $p['id'] !== $id;
    }));
    saveBalancePackages($list);
    telegram('editMessageReplyMarkup', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'reply_markup' => buildBalancePackageAdminKeyboard(),
    ]);
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => $textbotlang['Admin']['balance_pkg']['deleted'] ?? 'حذف شد',
        'show_alert' => false,
    ]);
}


#----------------[ Smart cron settings ]------------------#
if (preg_match('/^smartcron_toggle_(clean|vol|time|exp|emg|dbg)$/', strval($datain), $m_sc)) {
    ensureSmartCronSettings();
    $map = [
        'clean' => 'smart_clean_missing',
        'vol' => 'smart_warn_volume',
        'time' => 'smart_warn_time',
        'exp' => 'smart_warn_expired',
        'emg' => 'smart_emergency',
        'dbg' => 'smart_debug',
    ];
    $key = $map[$m_sc[1]];
    $cur = smartCronFlag($key, '0') ? '0' : '1';
    update("PaySetting", "ValuePay", $cur, "NamePay", $key);
    telegram('editMessageReplyMarkup', [
        'chat_id' => $from_id,
        'message_id' => $message_id,
        'reply_markup' => buildSmartCronAdminKeyboard(),
    ]);
}
if ($datain == "smartcron_set_vol") {
    sendmessage($from_id, $textbotlang['Admin']['smartcron']['set_vol'], $backadmin, 'HTML');
    step('smartcron_set_vol', $from_id);
} elseif ($user['step'] == 'smartcron_set_vol' && empty($datain)) {
    // نسخه 2.4.3: فقط درصدهای ۱ تا ۱۰۰
    $__lv = botParseNumList($text, 1, 100, 5);
    if ($__lv === '') {
        sendmessage($from_id, "❌ نامعتبر است. ۱ تا ۵ عدد بین ۱ تا ۱۰۰ را با کاما بفرستید.\nمثال: <code>90,95,99</code>", $backadmin, 'HTML');
        return;
    }
    ensureSmartCronSettings();
    update("PaySetting", "ValuePay", $__lv, "NamePay", "smart_vol_levels");
    step('home', $from_id);
    sendmessage($from_id, "✅ درصدهای اخطار حجم: <code>{$__lv}</code>\n\n" . botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
}
if ($datain == "smartcron_set_time") {
    sendmessage($from_id, $textbotlang['Admin']['smartcron']['set_time'], $backadmin, 'HTML');
    step('smartcron_set_time', $from_id);
} elseif ($user['step'] == 'smartcron_set_time' && empty($datain)) {
    // نسخه 2.4.3: فقط روزهای ۱ تا ۶۰
    $__lv = botParseNumList($text, 1, 60, 5);
    if ($__lv === '') {
        sendmessage($from_id, "❌ نامعتبر است. ۱ تا ۵ عدد بین ۱ تا ۶۰ (روز) را با کاما بفرستید.\nمثال: <code>7,3,1</code>", $backadmin, 'HTML');
        return;
    }
    ensureSmartCronSettings();
    update("PaySetting", "ValuePay", $__lv, "NamePay", "smart_time_days");
    step('home', $from_id);
    sendmessage($from_id, "✅ روزهای اخطار زمان: <code>{$__lv}</code>\n\n" . botSmartCronMenuText(), buildSmartCronAdminKeyboard(), 'HTML');
}
if ($datain == "smartcron_show_cmd") {
    global $domainhosts;
    sendmessage($from_id, sprintf($textbotlang['Admin']['smartcron']['cmd'], $domainhosts), buildSmartCronAdminKeyboard(), 'HTML');
}
if ($datain == "smartcron_install_crontab") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    $res = function_exists('syncBotCrontabs') ? syncBotCrontabs() : ['ok' => false, 'message' => 'تابع syncBotCrontabs موجود نیست.', 'lines' => [], 'installed' => []];
    $lines_txt = '';
    if (!empty($res['lines'])) {
        foreach ($res['lines'] as $ln) {
            $lines_txt .= "\n<code>" . htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') . "</code>";
        }
    }
    if (!empty($res['ok'])) {
        $msg = "✅ <b>نصب کرون انجام شد</b>\n\n" . htmlspecialchars($res['message'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($lines_txt !== '') {
            $msg .= "\n\n📌 خطوط نصب‌شده:" . $lines_txt;
        }
        $msg .= "\n\nبرای اطمینان روی سرور بزنید:\n<code>crontab -l</code>";
    } else {
        $msg = "⚠️ <b>نصب خودکار ممکن نشد</b>\n\n" . htmlspecialchars($res['message'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($lines_txt !== '') {
            $msg .= "\n\nاین خطوط را دستی در crontab بگذارید:" . $lines_txt;
            $msg .= "\n\nدستور:\n<code>crontab -e</code>";
        }
    }
    sendmessage($from_id, $msg, buildSmartCronAdminKeyboard(), 'HTML');
}


if ($datain == "smartcron_remove_crontab") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    $kb_confirm = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '✅ بله، حذف شود', 'callback_data' => 'smartcron_remove_crontab_yes'],
                ['text' => '❌ انصراف', 'callback_data' => 'smartcron_remove_crontab_no'],
            ],
        ],
    ]);
    sendmessage($from_id, "⚠️ <b>حذف کرون‌های ربات</b>\n\nهمه خطوط مربوط به ربات (smart_cron، croncard و کرون‌های قدیمی) از crontab پاک می‌شوند.\nکرون‌های دیگر سیستم دست نمی‌خورند.\n\nمطمئن هستید؟", $kb_confirm, 'HTML');
}
if ($datain == "smartcron_remove_crontab_no") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    Editmessagetext($from_id, $message_id, "❌ حذف کرون لغو شد.", buildSmartCronAdminKeyboard());
}
if ($datain == "smartcron_remove_crontab_yes") {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    $res = function_exists('removeBotCrontabs') ? removeBotCrontabs() : ['ok' => false, 'message' => 'تابع removeBotCrontabs موجود نیست.', 'removed' => []];
    if (!empty($res['ok'])) {
        $msg = "✅ <b>حذف انجام شد</b>\n\n" . htmlspecialchars($res['message'] ?? '', ENT_QUOTES, 'UTF-8');
        if (!empty($res['removed'])) {
            $msg .= "\n\nخطوط حذف‌شده:";
            foreach (array_slice($res['removed'], 0, 15) as $ln) {
                $msg .= "\n<code>" . htmlspecialchars($ln, ENT_QUOTES, 'UTF-8') . "</code>";
            }
        }
        $msg .= "\n\nبرای نصب دوباره از دکمه «نصب خودکار کرون» استفاده کنید.";
    } else {
        $msg = "⚠️ <b>حذف خودکار ممکن نشد</b>\n\n" . htmlspecialchars($res['message'] ?? '', ENT_QUOTES, 'UTF-8');
        $msg .= "\n\nدستی:\n<code>crontab -e</code>\nو خطوط دارای smart_cron.php یا croncard.php را پاک کنید.";
    }
    sendmessage($from_id, $msg, buildSmartCronAdminKeyboard(), 'HTML');
}

if ($datain == "smartcron_set_limit") {
    sendmessage($from_id, "🔢 تعداد فاکتوری که در هر اجرای کرون بررسی می‌شود را بفرستید (عدد بین ۱ تا ۲۰۰، پیش‌فرض ۲۰):", $backadmin, 'HTML');
    step('smartcron_set_limit', $from_id);
} elseif ($user['step'] == 'smartcron_set_limit') {
    $n = intval($text);
    if ($n < 1 || $n > 200) {
        sendmessage($from_id, "❌ عدد باید بین ۱ تا ۲۰۰ باشد.", $backadmin, 'HTML');
        return;
    }
    ensurePaySetting('smart_batch_limit', '20');
    update("PaySetting", "ValuePay", strval($n), "NamePay", "smart_batch_limit");
    sendmessage($from_id, "✅ تعداد هر اجرا: <code>{$n}</code>", buildSmartCronAdminKeyboard(), 'HTML');
    step('home', $from_id);
}


if ($text == ($textbotlang['Admin']['keyboardadmin']['codes_section'] ?? '🎟 کد تخفیف و هدیه')) {
    if (function_exists('codesAdminHubText')) {
        sendmessage($from_id, codesAdminHubText(), codesAdminHubKeyboard(), 'HTML');
    } else {
        sendmessage($from_id, "🎟 مدیریت کد تخفیف و هدیه", null, 'HTML');
    }
}

# --- پنل اینلاین کد تخفیف / هدیه ---

# ——— اینلاین: پنل‌ها / دسته / ادمین / اکانت تست ———
if ($datain == "adm_noop") {
    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '', 'show_alert' => false]);
} elseif ($datain == "adm_pan_list") {
    if (function_exists('adminPanelsListKeyboard')) {
        list($tx, $kb) = adminPanelsListKeyboard();
        Editmessagetext($from_id, $message_id, $tx, $kb);
    }
} elseif ($datain == "adm_pan_add") {
    global $typepanel, $textbotlang;
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => '',
        'show_alert' => false,
    ]);
    sendmessage(
        $from_id,
        $textbotlang['Admin']['managepanel']['selecttypepanel'] ?? 'نوع پنل را انتخاب کنید:',
        $typepanel,
        'HTML'
    );
} elseif (preg_match('/^adm_pan_(\d+)$/', strval($datain), $m_ap)) {
    $panel = function_exists('adminPanelGetById') ? adminPanelGetById($m_ap[1]) : null;
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        list($tx, $kb) = adminPanelDetailKeyboard($panel);
        Editmessagetext($from_id, $message_id, $tx, $kb);
    } else {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'پنل یافت نشد', 'show_alert' => true]);
    }
} elseif (preg_match('/^adm_pan_tg_(status|test|sub|cfg)_(\d+)$/', strval($datain), $m_tg)) {
    // نسخه 2.3.6: روشن/خاموش تنظیمات پنل از صفحه شیشهای
    $panel = adminPanelGetById($m_tg[2]);
    if (!$panel) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'پنل یافت نشد', 'show_alert' => true]);
        return;
    }
    $__pid = intval($m_tg[2]);
    $__col = null;
    $__val = null;
    if ($m_tg[1] === 'status') {
        $__col = 'status';
        $__val = (strval($panel['status'] ?? '') === 'disablepanel') ? 'activepanel' : 'disablepanel';
    } elseif ($m_tg[1] === 'test') {
        $__col = 'statusTest';
        $__val = (strval($panel['statusTest'] ?? '') === 'ontestshowpanel') ? 'offtestshowpanel' : 'ontestshowpanel';
    } elseif ($m_tg[1] === 'sub') {
        if (strval($panel['configManual'] ?? '') === 'onconfig') {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ اول «کانفیگ دستی» را خاموش کنید.', 'show_alert' => true]);
            return;
        }
        $__col = 'sublink';
        $__val = (strval($panel['sublink'] ?? '') === 'onsublink') ? 'offsublink' : 'onsublink';
    } elseif ($m_tg[1] === 'cfg') {
        if (strval($panel['sublink'] ?? '') === 'onsublink') {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '⚠️ اول «لینک اشتراک» را خاموش کنید.', 'show_alert' => true]);
            return;
        }
        $__col = 'configManual';
        $__val = (strval($panel['configManual'] ?? '') === 'onconfig') ? 'offconfig' : 'onconfig';
    }
    if ($__col !== null) {
        try {
            $pdo->prepare("UPDATE marzban_panel SET {$__col} = ? WHERE id = ?")->execute([$__val, $__pid]);
        } catch (Throwable $e) {
            error_log('panel toggle: ' . $e->getMessage());
        }
    }
    $panel = adminPanelGetById($__pid);
    update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
    list($tx, $kb) = adminPanelDetailKeyboard($panel, '✅ ذخیره شد.');
    Editmessagetext($from_id, $message_id, $tx, $kb);
} elseif (preg_match('/^adm_pan_act_method_(\d+)$/', strval($datain), $m_mt)) {
    $panel = adminPanelGetById($m_mt[1]);
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['decmthodusername'] ?? 'روش ساخت نام کاربری را انتخاب کنید:', $MethodUsername, 'HTML');
        step('updatemethodusername', $from_id);
    }
} elseif (preg_match('/^adm_pan_act_name_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['GetNameNew'] ?? 'نام جدید را بفرستید:', $backadmin, 'HTML');
        step('GetNameNew', $from_id);
    }
} elseif (preg_match('/^adm_pan_act_url_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['geturlnew'] ?? 'آدرس جدید را بفرستید:', $backadmin, 'HTML');
        step('GeturlNew', $from_id);
    }
} elseif (preg_match('/^adm_pan_act_user_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getusernamenew'] ?? 'یوزرنیم جدید:', $backadmin, 'HTML');
        step('GetusernameNew', $from_id);
    }
} elseif (preg_match('/^adm_pan_act_pass_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['getpasswordnew'] ?? $textbotlang['Admin']['managepanel']['getpassword'] ?? 'پسورد جدید:', $backadmin, 'HTML');
        step('GetpaawordNew', $from_id);
    }
} elseif (preg_match('/^adm_pan_act_delask_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        $id = intval($m[1]);
        $nm = htmlspecialchars($panel['name_panel'] ?? '', ENT_QUOTES, 'UTF-8');
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، حذف شود', 'callback_data' => 'adm_pan_act_del_' . $id]],
            [['text' => '❌ انصراف', 'callback_data' => 'adm_pan_' . $id]],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, "⚠️ پنل <code>{$nm}</code> حذف شود؟", $kb);
    }
} elseif (preg_match('/^adm_pan_act_del_(\d+)$/', strval($datain), $m)) {
    $panel = adminPanelGetById($m[1]);
    if ($panel) {
        try {
            $pdo->prepare("DELETE FROM marzban_panel WHERE id = ?")->execute([intval($m[1])]);
        } catch (Throwable $e) {}
        list($tx, $kb) = adminPanelsListKeyboard();
        Editmessagetext($from_id, $message_id, "✅ پنل حذف شد.\n\n" . $tx, $kb);
    }
} elseif (preg_match('/^adm_pan_act_connect_(\d+)$/', strval($datain), $m)) {
    $panel = function_exists('adminPanelGetById') ? adminPanelGetById($m[1]) : null;
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'در حال تست اتصال…',
            'show_alert' => false,
        ]);
        $result = function_exists('adminPanelTestConnectionText')
            ? adminPanelTestConnectionText($panel)
            : '❌ تابع تست موجود نیست';
        // کارت پنل بدون نتیجه؛ نتیجه در پیام جدا
        if (function_exists('adminPanelDetailKeyboard')) {
            list($tx, $kb) = adminPanelDetailKeyboard($panel, '');
            Editmessagetext($from_id, $message_id, $tx, $kb);
        }
        sendmessage($from_id, $result, null, 'HTML');
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'پنل یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif (preg_match('/^adm_pan_act_groups_(\d+)$/', strval($datain), $m)) {
    $panel = function_exists('adminPanelGetById') ? adminPanelGetById($m[1]) : null;
    if ($panel) {
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id);
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'در حال دریافت گروه…',
            'show_alert' => false,
        ]);
        $result = function_exists('adminPanelGroupsText')
            ? adminPanelGroupsText($panel)
            : '❌ تابع گروه موجود نیست';
        if (function_exists('adminPanelDetailKeyboard')) {
            list($tx, $kb) = adminPanelDetailKeyboard($panel, '');
            Editmessagetext($from_id, $message_id, $tx, $kb);
        }
        sendmessage($from_id, $result, null, 'HTML');
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'پنل یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif ($datain == "adm_cat_list") {
    list($tx, $kb) = adminCategoriesListKeyboard();
    Editmessagetext($from_id, $message_id, $tx, $kb);
} elseif ($datain == "adm_cat_add") {
    sendmessage($from_id, $textbotlang['Admin']['category']['getname'] ?? 'نام دسته را بفرستید:', $backadmin, 'HTML');
    step("getremarkcategory", $from_id);
} elseif (preg_match('/^adm_cat_(del|delok)_(\d+)$/', strval($datain), $m)) {
    // نسخه 2.3.8: دستهای که محصول دارد حذف نمیشود؛ بقیه با تأیید
    $__cid = intval($m[2]);
    $__cat = select("category", "*", "id", $__cid, "select");
    if (!is_array($__cat)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'دسته یافت نشد', 'show_alert' => true]);
        list($tx, $kb) = adminCategoriesListKeyboard();
        Editmessagetext($from_id, $message_id, $tx, $kb);
        return;
    }
    $__np = 0;
    try {
        $__st = $pdo->prepare("SELECT COUNT(*) FROM product WHERE Category = ?");
        $__st->execute([strval($__cid)]);
        $__np = intval($__st->fetchColumn());
    } catch (Throwable $e) {
    }
    $__cn = htmlspecialchars(strval($__cat['remark'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($__np > 0) {
        list($tx, $kb) = adminCategoriesListKeyboard();
        Editmessagetext($from_id, $message_id, "❌ دسته «{$__cn}» <b>{$__np}</b> محصول دارد و حذف نشد.\nاگر حذف شود، این محصولات از منوی خرید کاربران ناپدید میشوند.\n\nاول از «📦 مدیریت محصولات» دسته این محصولات را عوض کنید (دکمه «📁 دسته»)، بعد دسته را حذف کنید.\n\n" . $tx, $kb);
        return;
    }
    if ($m[1] === 'del') {
        $__kb_cd = json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، حذف شود', 'callback_data' => 'adm_cat_delok_' . $__cid]],
            [['text' => '❌ خیر، بازگشت', 'callback_data' => 'adm_cat_list']],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, "⚠️ دسته «{$__cn}» حذف شود؟\n(این دسته محصولی ندارد.)", $__kb_cd);
        return;
    }
    try {
        $pdo->prepare("DELETE FROM category WHERE id = ?")->execute([$__cid]);
    } catch (Throwable $e) {}
    list($tx, $kb) = adminCategoriesListKeyboard();
    Editmessagetext($from_id, $message_id, "✅ دسته حذف شد.\n\n" . $tx, $kb);
} elseif ($datain == "adm_adm_list") {
    if (function_exists('isMainBotAdmin') && !isMainBotAdmin($from_id)) {
        // همه ادمین‌ها می‌توانند لیست را ببینند اما حذف فقط غیر اصلی
    }
    list($tx, $kb) = adminAdminsListKeyboard($from_id);
    Editmessagetext($from_id, $message_id, $tx, $kb);
} elseif ($datain == "adm_adm_add") {
    sendmessage($from_id, $textbotlang['Admin']['manageadmin']['getid'] ?? 'آیدی عددی ادمین را بفرستید:', $backadmin, 'HTML');
    step('addadmin', $from_id);
} elseif (preg_match('/^adm_adm_del_(\d+)$/', strval($datain), $m)) {
    $del_id = strval($m[1]);
    if (strval($adminnumber) === $del_id || strval($from_id) === $del_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => (strval($adminnumber) === $del_id ? 'ادمین اصلی قابل حذف نیست' : 'نمیتوانید خودتان را حذف کنید'), 'show_alert' => true]);
    } else {
        Editmessagetext($from_id, $message_id, "⚠️ ادمین <code>{$del_id}</code> حذف شود؟", json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، حذف شود', 'callback_data' => 'adm_adm_delok_' . $del_id]],
            [['text' => '❌ انصراف', 'callback_data' => 'adm_adm_list']],
        ]], JSON_UNESCAPED_UNICODE));
    }
} elseif ($datain == "adm_ut_panel") {
    list($tx, $kb) = adminUsertestPanel();
    Editmessagetext($from_id, $message_id, $tx, $kb);
} elseif ($datain == "adm_ut_time") {
    sendmessage($from_id, sprintf($textbotlang['Admin']['Usertest']['sendtimeusertest'] ?? 'زمان تست (ساعت) را بفرستید: فعلی %s', $setting['time_usertest'] ?? ''), $backadmin, 'HTML');
    step('updatetime', $from_id);
} elseif ($datain == "adm_ut_vol") {
    sendmessage($from_id, sprintf($textbotlang['Admin']['Usertest']['sendvoluemusertest'] ?? 'حجم تست (گیگ) را بفرستید: فعلی %s', $setting['val_usertest'] ?? ''), $backadmin, 'HTML');
    step('val_usertest', $from_id);
} elseif ($datain == "adm_ut_limit") {
    sendmessage($from_id, $textbotlang['Admin']['getlimitusertest']['limitall'] ?? 'سقف تعداد تست برای همه کاربران را بفرستید:', $backadmin, 'HTML');
    step('limit_usertest_allusers', $from_id);
} elseif ($datain == "adm_ut_reset") {
    list($__tx, $__kb) = botUsertestResetConfirm();
    Editmessagetext($from_id, $message_id, $__tx, $__kb);
}


# نسخه 2.3.0: تغییر نام دستهبندی
if (in_array($from_id, $admin_ids) && isset($datain) && preg_match('/^adm_cat_ren_(\d+)$/', strval($datain), $m_cr)) {
    $__cat = select("category", "*", "id", intval($m_cr[1]), "select");
    if (!is_array($__cat)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'دسته یافت نشد', 'show_alert' => true]);
        return;
    }
    update("user", "Processing_value", strval(intval($m_cr[1])), "id", $from_id);
    sendmessage($from_id, "✏️ نام فعلی: <b>" . htmlspecialchars(strval($__cat['remark'] ?? ''), ENT_QUOTES, 'UTF-8') . "</b>\n\nنام جدید دسته را بفرستید:", $backadmin, 'HTML');
    step('adm_cat_ren', $from_id);
    return;
}
if (in_array($from_id, $admin_ids) && strval($user['step'] ?? '') === 'adm_cat_ren' && empty($datain)) {
    if (isset($text) && is_string($text) && $text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__new_cn = (isset($text) && is_string($text)) ? trim($text) : '';
    if ($__new_cn === '' || mb_strlen($__new_cn) > 100) {
        sendmessage($from_id, "❌ یک نام بین ۱ تا ۱۰۰ کاراکتر بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__cid = intval($user['Processing_value'] ?? 0);
    $__dup = select("category", "*", "remark", $__new_cn, "select");
    if (is_array($__dup) && intval($__dup['id'] ?? 0) !== $__cid) {
        sendmessage($from_id, "❌ دستهای با همین نام وجود دارد. نام دیگری بفرستید:", $backadmin, 'HTML');
        return;
    }
    if ($__cid <= 0 || !is_array(select("category", "*", "id", $__cid, "select"))) {
        step('home', $from_id);
        sendmessage($from_id, "❌ دسته پیدا نشد. دوباره از لیست دستهها شروع کنید.", $backadmin, 'HTML');
        return;
    }
    update("category", "remark", $__new_cn, "id", $__cid);
    step('home', $from_id);
    list($tx, $kb) = adminCategoriesListKeyboard();
    sendmessage($from_id, "✅ نام دسته عوض شد.\n\n" . $tx, $kb, 'HTML');
    return;
}

# نسخه 2.2.9: روشن/خاموش اکانت تست و سهمیه یک کاربر — از صفحه فروشگاه
if (in_array($from_id, $admin_ids) && isset($datain) && $datain === 'adm_ut_toggle') {
    $__st_ut = select("setting", "*");
    $__cur_ut = (is_array($__st_ut) && isset($__st_ut['status_usertest']) && strval($__st_ut['status_usertest']) === '0') ? '0' : '1';
    update("setting", "status_usertest", $__cur_ut === '1' ? '0' : '1');
    list($tx, $kb) = adminUsertestPanel();
    Editmessagetext($from_id, $message_id, $tx, $kb);
    return;
}
if (in_array($from_id, $admin_ids) && isset($datain) && $datain === 'adm_ut_user') {
    sendmessage($from_id, "👤 آیدی عددی کاربر را بفرستید:", $backadmin, 'HTML');
    step('adm_ut_user_id', $from_id);
    return;
}
if (in_array($from_id, $admin_ids) && strval($user['step'] ?? '') === 'adm_ut_user_id' && isset($text) && is_string($text) && $text !== '' && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__uid_ut = trim($text);
    if (!ctype_digit($__uid_ut)) {
        sendmessage($from_id, "❌ آیدی باید فقط عدد باشد. دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__u_ut = select("user", "*", "id", $__uid_ut, "select");
    if (!is_array($__u_ut)) {
        sendmessage($from_id, "❌ کاربری با این آیدی پیدا نشد. دوباره بفرستید:", $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $__uid_ut, "id", $from_id);
    sendmessage($from_id, "👤 کاربر: <code>{$__uid_ut}</code>\n🔢 سهمیه فعلی تست: <b>" . intval($__u_ut['limit_usertest'] ?? 0) . "</b>\n\nتعداد جدید را بفرستید (۰ = بدون تست):", $backadmin, 'HTML');
    step('adm_ut_user_lim', $from_id);
    return;
}
if (in_array($from_id, $admin_ids) && strval($user['step'] ?? '') === 'adm_ut_user_lim' && isset($text) && is_string($text) && $text !== '' && empty($datain)) {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '___')) {
        step('home', $from_id);
        return;
    }
    $__n_ut = trim($text);
    if (!ctype_digit($__n_ut) || intval($__n_ut) > 1000) {
        sendmessage($from_id, "❌ یک عدد بین ۰ تا ۱۰۰۰ بفرستید:", $backadmin, 'HTML');
        return;
    }
    $__uid_ut = strval($user['Processing_value'] ?? '');
    if ($__uid_ut === '' || !ctype_digit($__uid_ut)) {
        step('home', $from_id);
        sendmessage($from_id, "❌ کاربر مشخص نیست. دوباره از «👤 سهمیه یک کاربر» شروع کنید.", $backadmin, 'HTML');
        return;
    }
    update("user", "limit_usertest", strval(intval($__n_ut)), "id", $__uid_ut);
    step('home', $from_id);
    list($tx, $kb) = adminUsertestPanel();
    sendmessage($from_id, "✅ سهمیه تست کاربر <code>{$__uid_ut}</code> روی <b>" . intval($__n_ut) . "</b> تنظیم شد.\n\n" . $tx, $kb, 'HTML');
    return;
}

if ($datain == "codes_noop") {
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => '',
        'show_alert' => false,
    ]);
} elseif ($datain == "codes_hub") {
    if (function_exists('codesAdminHubText')) {
        Editmessagetext($from_id, $message_id, codesAdminHubText(), codesAdminHubKeyboard());
    }
} elseif ($datain == "codes_disc_list" || preg_match('/^codes_disc_list_(\d+)$/', strval($datain), $m_cdl)) {
    $pg = ($datain === 'codes_disc_list') ? 0 : intval($m_cdl[1] ?? 0);
    if (function_exists('codesDiscountListKeyboard')) {
        list($tx, $kb) = codesDiscountListKeyboard($pg);
        Editmessagetext($from_id, $message_id, $tx, $kb);
    }
} elseif ($datain == "codes_gift_list" || preg_match('/^codes_gift_list_(\d+)$/', strval($datain), $m_cgl)) {
    $pg = ($datain === 'codes_gift_list') ? 0 : intval($m_cgl[1] ?? 0);
    if (function_exists('codesGiftListKeyboard')) {
        list($tx, $kb) = codesGiftListKeyboard($pg);
        Editmessagetext($from_id, $message_id, $tx, $kb);
    }
} elseif (strpos(strval($datain), 'codes_disc_v_') === 0) {
    $tok = substr(strval($datain), strlen('codes_disc_v_'));
    $row = function_exists('codesResolveDiscountCode') ? codesResolveDiscountCode($tok) : null;
    if ($row && function_exists('codesDiscountDetail')) {
        list($tx, $kb) = codesDiscountDetail($row);
        Editmessagetext($from_id, $message_id, $tx, $kb);
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'کد یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif (strpos(strval($datain), 'codes_gift_v_') === 0) {
    $tok = substr(strval($datain), strlen('codes_gift_v_'));
    $row = function_exists('codesResolveGiftCode') ? codesResolveGiftCode($tok) : null;
    if ($row && function_exists('codesGiftDetail')) {
        list($tx, $kb) = codesGiftDetail($row);
        Editmessagetext($from_id, $message_id, $tx, $kb);
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'کد یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif (strpos(strval($datain), 'codes_disc_del_') === 0) {
    $tok = substr(strval($datain), strlen('codes_disc_del_'));
    $row = function_exists('codesResolveDiscountCode') ? codesResolveDiscountCode($tok) : null;
    if ($row) {
        $code = strval($row['codeDiscount'] ?? '');
        try {
            $stmt = $pdo->prepare("DELETE FROM DiscountSell WHERE codeDiscount = ?");
            $stmt->execute([$code]);
        } catch (Throwable $e) {}
        if (function_exists('codesDiscountListKeyboard')) {
            list($tx, $kb) = codesDiscountListKeyboard(0);
            Editmessagetext($from_id, $message_id, "✅ کد تخفیف حذف شد.

" . $tx, $kb);
        }
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'کد یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif (strpos(strval($datain), 'codes_gift_del_') === 0) {
    $tok = substr(strval($datain), strlen('codes_gift_del_'));
    $row = function_exists('codesResolveGiftCode') ? codesResolveGiftCode($tok) : null;
    if ($row) {
        $code = strval($row['code'] ?? '');
        try {
            $stmt = $pdo->prepare("DELETE FROM Discount WHERE code = ?");
            $stmt->execute([$code]);
        } catch (Throwable $e) {}
        if (function_exists('codesGiftListKeyboard')) {
            list($tx, $kb) = codesGiftListKeyboard(0);
            Editmessagetext($from_id, $message_id, "✅ کد هدیه حذف شد.

" . $tx, $kb);
        }
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => 'کد یافت نشد',
            'show_alert' => true,
        ]);
    }
} elseif ($datain == "codes_disc_add") {
    sendmessage($from_id, $textbotlang['Admin']['Discountsell']['GetCode'] ?? 'کد تخفیف را ارسال کنید:', $backadmin, 'HTML');
    step('get_codesell', $from_id);
} elseif ($datain == "codes_gift_add") {
    sendmessage($from_id, $textbotlang['Admin']['Discount']['GetCode'] ?? 'کد هدیه را ارسال کنید:', $backadmin, 'HTML');
    step('get_code', $from_id);
}




if (preg_match('/^toggle_cart_auto_(\d+)$/', strval($datain), $m_ca)) {
    $cb_id = $update['callback_query']['id'] ?? '';
    if (function_exists('isAutomaticCartConfirmEnabled') && !isAutomaticCartConfirmEnabled()) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text' => 'تأیید خودکار بدون بررسی سراسری خاموش است',
            'show_alert' => true
        ]);
        return;
    }
    if (function_exists('ensureUserCartAutoColumn')) ensureUserCartAutoColumn();
    $uid = $m_ca[1];
    $u = select("user", "*", "id", $uid, "select");
    if (!$u) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text' => 'کاربر یافت نشد',
            'show_alert' => true
        ]);
        return;
    }
    $newv = intval($u['cart_auto_off'] ?? 0) ? 0 : 1;
    update("user", "cart_auto_off", strval($newv), "id", $uid);
    $btn_txt = $newv
        ? ($textbotlang['Admin']['ManageUser']['cart_auto_off'] ?? '❌ تأیید خودکار رسید کاربر: غیرفعال')
        : ($textbotlang['Admin']['ManageUser']['cart_auto_on'] ?? '✅ تأیید خودکار رسید کاربر: فعال');
    telegram('answerCallbackQuery', [
        'callback_query_id' => $cb_id,
        'text' => $newv ? 'غیرفعال شد' : 'فعال شد',
        'show_alert' => false
    ]);
    // فقط دکمه را عوض کن — متن پیام دست نخورد
    $old_kb = $update['callback_query']['message']['reply_markup']['inline_keyboard'] ?? [];
    if (is_array($old_kb) && count($old_kb) > 0) {
        foreach ($old_kb as &$row) {
            if (!is_array($row)) continue;
            foreach ($row as &$btn) {
                if (isset($btn['callback_data']) && strpos($btn['callback_data'], 'toggle_cart_auto_') === 0) {
                    $btn['text'] = $btn_txt;
                }
            }
            unset($btn);
        }
        unset($row);
        telegram('editMessageReplyMarkup', [
            'chat_id' => $from_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode(['inline_keyboard' => $old_kb])
        ]);
    }
}



/* =====================================================================
 * Reseller Panel — فقط ادمین | بدون تأثیر روی خرید اشتراک عادی
 * جداول فقط CREATE IF NOT EXISTS / ADD COLUMN — بدون DROP
 * ===================================================================== */

if (!isset($datain)) {
    $datain = '';
}

/** آیا کاربر ادمین ربات است؟ */
$__rv_is_admin = (isset($admin_ids) && is_array($admin_ids) && in_array($from_id, $admin_ids));

/** لود ماژول ریسلر */
$__rv_load = function () {
    if (function_exists('ensurePanelAdminSchema')) {
        ensurePanelAdminSchema();
    }
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) {
        require_once $f;
    }
    $f2 = __DIR__ . '/pasarguard.php';
    if (is_file($f2)) {
        require_once $f2;
    }
};

// ----- منوی اصلی ادمین (دکمه کیبورد) -----
$__rv_admin_btn = isset($textbotlang['Admin']['keyboardadmin']['reseller_volume'])
    ? $textbotlang['Admin']['keyboardadmin']['reseller_volume']
    : '⚙️ مدیریت پنل نمایندگان';

if ($__rv_is_admin && isset($text) && $text === $__rv_admin_btn) {
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        sendmessage($from_id, '❌ قابلیت پنل نمایندگان خاموش است.\nاز «قابلیت‌های ربات» آن را روشن کنید.', $keyboardadmin, 'HTML');
        return;
    }
    $__rv_load();
    $kb = function_exists('resellerVolumeAdminKeyboard')
        ? resellerVolumeAdminKeyboard()
        : json_encode(['inline_keyboard' => [
            [['text' => '📋 تنظیم پلن‌های پنل', 'callback_data' => 'rv_admin_plans']],
            [['text' => '🖥 تنظیم اطلاعات پنل', 'callback_data' => 'rv_admin_panel']],
        ]]);
    sendmessage($from_id, "🛠 <b>مدیریت پنل نمایندگان</b>\n\nتنظیمات پنل و پلن‌های حجم ریسلر.", $kb, 'HTML');
    return;
}

// ----- فقط اگر قابلیت روشن و ادمین -----
if ($__rv_is_admin && function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled()) {

    // بازگشت به هاب
    if ($datain === 'rv_hub_back' || $datain === 'rv_admin_hub') {
        $__rv_load();
        $kb = function_exists('resellerVolumeAdminKeyboard') ? resellerVolumeAdminKeyboard() : null;
        Editmessagetext($from_id, $message_id, "🛠 <b>مدیریت پنل نمایندگان</b>\n\nتنظیمات پنل و پلن‌های حجم ریسلر.", $kb);
        return;
    }

    // لیست پلن‌ها
    
    // وضعیت حجم همه ادمین‌های پنل‌دار
    if ($datain === 'rv_admin_volume_status') {
        $__rv_load();
        if (function_exists('telegram')) {
            try {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'در حال دریافت از پنل…',
                    'show_alert' => false,
                ]);
            } catch (Throwable $e) {
            }
        }
        $report = function_exists('buildResellerAdminsVolumeReport')
            ? buildResellerAdminsVolumeReport()
            : '❌ تابع گزارش در دسترس نیست.';
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '🔄 بروزرسانی', 'callback_data' => 'rv_admin_volume_status']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'rv_admin_hub']],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $report, $kb);
        return;
    }


    if ($datain === 'rv_admin_plans' || $datain === 'rv_menu_plans') {
        $__rv_load();
        $kb = function_exists('resellerVolumePlansKeyboard') ? resellerVolumePlansKeyboard() : null;
        $plans = function_exists('resellerListPlans') ? resellerListPlans(false) : [];
        $n = count($plans);
        Editmessagetext($from_id, $message_id, "📋 <b>پلن‌های حجم ریسلر</b>\nتعداد: <b>{$n}</b>\n\nبرای ویرایش روی یک پلن بزنید.", $kb);
        return;
    }

    // اطلاعات پنل
    if ($datain === 'rv_admin_panel' || $datain === 'rv_menu_panel') {
        $__rv_load();
        $panel = function_exists('getResellerPanel') ? getResellerPanel() : null;
        $url = is_array($panel) ? strval($panel['url_panel'] ?? '') : '';
        $user = is_array($panel) ? strval($panel['username_panel'] ?? '') : '';
        $has = ($url !== '' && $user !== '');
        $info = $has
            ? "🖥 <b>اطلاعات پنل ریسلر</b>\n\nآدرس: <code>" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "</code>\nیوزرنیم: <code>" . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . "</code>\n\nبرای تغییر، دکمه زیر را بزنید."
            : "🖥 <b>اطلاعات پنل ریسلر</b>\n\nهنوز تنظیم نشده است.\nدکمه زیر را بزنید و آدرس / یوزر / رمز را وارد کنید.";
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '✏️ تنظیم / ویرایش پنل', 'callback_data' => 'rv_panel_edit']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']],
        ]]);
        Editmessagetext($from_id, $message_id, $info, $kb);
        return;
    }

    // شروع ویرایش پنل
    if ($datain === 'rv_panel_edit') {
        step('rv_panel_url', $from_id);
        sendmessage($from_id, "آدرس پنل را بفرستید:\nمثال: <code>https://panel.example.com</code>", $backadmin, 'HTML');
        return;
    }

    // افزودن پلن
    if ($datain === 'rv_admin_add') {
        step('rv_add_volume', $from_id);
        sendmessage($from_id, "حجم پلن را به <b>گیگابایت</b> بفرستید:\nمثال: <code>500</code>", $backadmin, 'HTML');
        return;
    }

    // ویرایش پلن
    if (preg_match('/^rv_edit_(\d+)$/', strval($datain), $m_ed)) {
        $__rv_load();
        $plan = function_exists('resellerGetPlan') ? resellerGetPlan(intval($m_ed[1])) : null;
        if (!$plan) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'پلن یافت نشد', 'show_alert' => true]);
            return;
        }
        $pid = intval($plan['id']);
        $gb_raw = intval($plan['volume_gb'] ?? 0);
        $gb = number_format($gb_raw);
        $gb_label = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb_raw) : ($gb . ' گیگ');
        $pr = number_format(intval($plan['price']));
        $st = intval($plan['status'] ?? 1) === 1 ? 'فعال' : 'غیرفعال';
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '✏️ تغییر قیمت', 'callback_data' => 'rv_setprice_' . $pid], ['text' => '📊 تغییر حجم', 'callback_data' => 'rv_setvol_' . $pid]],
            [['text' => (intval($plan['status'] ?? 1) === 1 ? '🔴 غیرفعال کردن' : '🟢 فعال کردن'), 'callback_data' => 'rv_toggle_' . $pid]],
            [['text' => '🗑 حذف پلن', 'callback_data' => 'rv_delask_' . $pid]],
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'rv_admin_plans']],
        ]]);
        Editmessagetext($from_id, $message_id, "📦 <b>ویرایش پلن</b>\n\n📊 حجم: <b>{$gb_label}</b>\n💵 قیمت: <b>{$pr}</b> تومان\nوضعیت: {$st}", $kb);
        return;
    }

    if (preg_match('/^rv_toggle_(\d+)$/', strval($datain), $m_tg)) {
        global $pdo;
        $__rv_load();
        $pid = intval($m_tg[1]);
        $plan = resellerGetPlan($pid);
        if ($plan) {
            $new = intval($plan['status'] ?? 1) === 1 ? 0 : 1;
            try {
                $pdo->prepare("UPDATE reseller_volume_plan SET status = ? WHERE id = ?")->execute([$new, $pid]);
            } catch (Throwable $e) {
                error_log('rv_toggle: ' . $e->getMessage());
            }
        }
        // refresh edit view
        $datain = 'rv_edit_' . $pid;
        $plan = resellerGetPlan($pid);
        $gb_raw = intval($plan['volume_gb'] ?? 0);
        $gb = number_format($gb_raw);
        $gb_label = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb_raw) : ($gb . ' گیگ');
        $pr = number_format(intval($plan['price'] ?? 0));
        $st = intval($plan['status'] ?? 1) === 1 ? 'فعال' : 'غیرفعال';
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '✏️ تغییر قیمت', 'callback_data' => 'rv_setprice_' . $pid], ['text' => '📊 تغییر حجم', 'callback_data' => 'rv_setvol_' . $pid]],
            [['text' => (intval($plan['status'] ?? 1) === 1 ? '🔴 غیرفعال کردن' : '🟢 فعال کردن'), 'callback_data' => 'rv_toggle_' . $pid]],
            [['text' => '🗑 حذف پلن', 'callback_data' => 'rv_delask_' . $pid]],
            [['text' => '🔙 بازگشت به لیست', 'callback_data' => 'rv_admin_plans']],
        ]]);
        Editmessagetext($from_id, $message_id, "📦 <b>ویرایش پلن</b>\n\n📊 حجم: <b>{$gb_label}</b>\n💵 قیمت: <b>{$pr}</b> تومان\nوضعیت: {$st}", $kb);
        return;
    }

    // نسخه 2.3.6: تأیید قبل از حذف پلن ریسلر
    if (preg_match('/^rv_delask_(\d+)$/', strval($datain), $m_da)) {
        $__rv_load();
        $__pl = function_exists('resellerGetPlan') ? resellerGetPlan(intval($m_da[1])) : null;
        if (!$__pl) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'پلن یافت نشد', 'show_alert' => true]);
            return;
        }
        $__gbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel(intval($__pl['volume_gb'] ?? 0)) : (number_format(intval($__pl['volume_gb'] ?? 0)) . ' گیگ');
        $kb = json_encode(['inline_keyboard' => [
            [['text' => '✅ بله، حذف شود', 'callback_data' => 'rv_del_' . intval($__pl['id'])]],
            [['text' => '❌ خیر، بازگشت', 'callback_data' => 'rv_edit_' . intval($__pl['id'])]],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, "⚠️ <b>حذف پلن {$__gbl} — " . number_format(intval($__pl['price'] ?? 0)) . " تومان؟</b>\n\nاین کار برگشتپذیر نیست.\n💡 اگر فقط نمیخواهید فعلاً فروخته شود، «🔴 غیرفعال کردن» را بزنید.", $kb);
        return;
    }

    if (preg_match('/^rv_del_(\d+)$/', strval($datain), $m_dl)) {
        global $pdo;
        $pid = intval($m_dl[1]);
        try {
            $pdo->prepare("DELETE FROM reseller_volume_plan WHERE id = ?")->execute([$pid]);
        } catch (Throwable $e) {
            error_log('rv_del: ' . $e->getMessage());
        }
        $__rv_load();
        $kb = function_exists('resellerVolumePlansKeyboard') ? resellerVolumePlansKeyboard() : null;
        Editmessagetext($from_id, $message_id, "🗑 پلن حذف شد.\n\n📋 <b>پلن‌های حجم ریسلر</b>", $kb);
        return;
    }


    // ----- تنظیمات کرون هشدار حجم ریسلر (داخل منوی Reseller) -----
    if ($datain === 'rv_cron_settings') {
        if (function_exists('ensureSmartCronSettings')) {
            ensureSmartCronSettings();
        }
        $on = (function_exists('smartCronFlag') && smartCronFlag('smart_reseller_warn', '0'));
        $levels = function_exists('getPaySettingValue') ? getPaySettingValue('smart_reseller_vol_levels', '80,90,95') : '80,90,95';
        $st = $on ? '✅ روشن' : '❌ خاموش';
        $txt = "⏱ <b>کرون هشدار حجم Reseller Panel</b>\n\n"
            . "وضعیت: <b>{$st}</b>\n"
            . "درصدهای هشدار: <code>{$levels}</code>\n\n"
            . "این کرون همراه «کرون هوشمند» اجرا می‌شود.\n"
            . "فقط وقتی قابلیت Reseller Panel در «قابلیت‌های ربات» روشن باشد کار می‌کند.\n"
            . "با رسیدن مصرف ادمین پنل به هر درصد، یک‌بار به کاربر پیام + دکمه خرید حجم ارسال می‌شود.";
        $kb = json_encode(['inline_keyboard' => [
            [['text' => ($on ? '🔴 خاموش کردن هشدار' : '🟢 روشن کردن هشدار'), 'callback_data' => 'rv_cron_toggle']],
            [['text' => '⚙️ تنظیم درصدها', 'callback_data' => 'rv_cron_set_levels']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $txt, $kb);
        return;
    }

    if ($datain === 'rv_cron_toggle') {
        if (function_exists('ensureSmartCronSettings')) {
            ensureSmartCronSettings();
        }
        $cur = (function_exists('smartCronFlag') && smartCronFlag('smart_reseller_warn', '0')) ? '1' : '0';
        if (function_exists('setPaySettingValue')) {
            setPaySettingValue('smart_reseller_warn', $cur === '1' ? '0' : '1');
        }
        // refresh same screen
        $datain = 'rv_cron_settings';
        $on = (function_exists('smartCronFlag') && smartCronFlag('smart_reseller_warn', '0'));
        $levels = function_exists('getPaySettingValue') ? getPaySettingValue('smart_reseller_vol_levels', '80,90,95') : '80,90,95';
        $st = $on ? '✅ روشن' : '❌ خاموش';
        $txt = "⏱ <b>کرون هشدار حجم Reseller Panel</b>\n\nوضعیت: <b>{$st}</b>\nدرصدهای هشدار: <code>{$levels}</code>";
        $kb = json_encode(['inline_keyboard' => [
            [['text' => ($on ? '🔴 خاموش کردن هشدار' : '🟢 روشن کردن هشدار'), 'callback_data' => 'rv_cron_toggle']],
            [['text' => '⚙️ تنظیم درصدها', 'callback_data' => 'rv_cron_set_levels']],
            [['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']],
        ]], JSON_UNESCAPED_UNICODE);
        Editmessagetext($from_id, $message_id, $txt, $kb);
        return;
    }

    if ($datain === 'rv_cron_set_levels') {
        step('rv_cron_levels', $from_id);
        sendmessage($from_id, "درصدهای هشدار را با ویرگول بفرستید.\nمثال: <code>80,90,95</code>\n\nبا رسیدن مصرف به هر درصد (یک‌بار برای هر سطح) پیام ارسال می‌شود.", $backadmin, 'HTML');
        return;
    }

    if (preg_match('/^rv_setprice_(\d+)$/', strval($datain), $m_sp)) {
        step('rv_setprice_' . intval($m_sp[1]), $from_id);
        sendmessage($from_id, "قیمت جدید را به <b>تومان</b> بفرستید:", $backadmin, 'HTML');
        return;
    }
    if (preg_match('/^rv_setvol_(\d+)$/', strval($datain), $m_sv)) {
        step('rv_setvol_' . intval($m_sv[1]), $from_id);
        sendmessage($from_id, "حجم جدید را به <b>گیگابایت</b> بفرستید:", $backadmin, 'HTML');
        return;
    }
}

// ----- step handlers (متن) — فقط ادمین -----
$__rv_step = strval($user['step'] ?? '');

if ($__rv_is_admin && $__rv_step === 'rv_panel_url' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $url = rtrim(trim($text), '/');
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || !parse_url($url, PHP_URL_HOST)) {
        sendmessage($from_id, '❌ آدرس معتبر نیست. باید با http:// یا https:// شروع شود، مثل: <code>https://panel.example.com</code>', $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", $url, "id", $from_id);
    step('rv_panel_user', $from_id);
    sendmessage($from_id, "نام کاربری ادمین پنل را بفرستید:", $backadmin, 'HTML');
    return;
}

if ($__rv_is_admin && $__rv_step === 'rv_panel_user' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    update("user", "Processing_value_one", trim($text), "id", $from_id);
    step('rv_panel_pass', $from_id);
    sendmessage($from_id, "رمز عبور ادمین پنل را بفرستید:", $backadmin, 'HTML');
    return;
}

if ($__rv_is_admin && $__rv_step === 'rv_panel_pass' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $url = strval($user['Processing_value'] ?? '');
    $uname = strval($user['Processing_value_one'] ?? '');
    $pass = trim($text);
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) {
        require_once $f;
    }
    if (!function_exists('saveResellerPanelCredentials')) {
        sendmessage($from_id, '❌ ماژول ریسلر در دسترس نیست.', $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $res = saveResellerPanelCredentials($url, $uname, $pass);
    step('home', $from_id);
    if (empty($res['ok'])) {
        sendmessage($from_id, '❌ خطا در ذخیره: ' . strval($res['message'] ?? ''), $keyboardadmin, 'HTML');
        return;
    }
    $url_s = strval($res['url'] ?? $url);
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) { require_once $f; }
    // نسخه 2.3.6: تست اتصال بعد از ذخیره
    $__rv_test = '';
    try {
        if (!function_exists('token_reseller_standalone') && is_file(__DIR__ . '/pasarguard.php')) {
            require_once __DIR__ . '/pasarguard.php';
        }
        $__rp = function_exists('getResellerPanel') ? getResellerPanel() : null;
        if (is_array($__rp) && function_exists('token_reseller_standalone')) {
            $__tk = token_reseller_standalone($__rp);
            $__rv_test = (is_array($__tk) && !empty($__tk['access_token']))
                ? "\n\n🔗 تست اتصال: ✅ ورود به پنل موفق بود."
                : "\n\n🔗 تست اتصال: ❌ ورود ناموفق. آدرس، یوزرنیم یا رمز را دوباره چک کنید.";
        }
    } catch (Throwable $e) {
        $__rv_test = "\n\n🔗 تست اتصال: ❌ خطا در اتصال به پنل.";
    }
    $kb = function_exists('resellerVolumeAdminKeyboard') ? resellerVolumeAdminKeyboard() : null;
    sendmessage($from_id, "✅ اطلاعات پنل ریسلر ذخیره شد.\n\n🖥 <code>" . htmlspecialchars($url_s, ENT_QUOTES, 'UTF-8') . "</code>\n👤 <code>" . htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') . "</code>" . $__rv_test . "\n\n🛠 <b>مدیریت پنل نمایندگان</b>", $kb ?: $keyboardadmin, 'HTML');
    return;
}

if ($__rv_is_admin && $__rv_step === 'rv_add_volume' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $gb = intval(preg_replace('/\D/', '', function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text)));
    if ($gb < 1) {
        sendmessage($from_id, '❌ حجم نامعتبر است.', $backadmin, 'HTML');
        return;
    }
    update("user", "Processing_value", strval($gb), "id", $from_id);
    step('rv_add_price', $from_id);
    $__gb_lbl = function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb) : ($gb . ' گیگ');
    sendmessage($from_id, "قیمت این پلن ({$__gb_lbl}) را به <b>تومان</b> بفرستید:", $backadmin, 'HTML');
    return;
}

if ($__rv_is_admin && $__rv_step === 'rv_add_price' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    global $pdo;
    $gb = intval($user['Processing_value'] ?? 0);
    $price = intval(preg_replace('/\D/', '', function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text)));
    if ($gb < 1 || $price < 1) {
        sendmessage($from_id, '❌ مقادیر نامعتبر.', $backadmin, 'HTML');
        return;
    }
if (function_exists('ensurePanelAdminSchema')) {
        ensurePanelAdminSchema();
    }
    if (function_exists('ensureResellerPlanTables')) {
        ensureResellerPlanTables();
    }
    if (function_exists('resellerInsertPlan')) {
        $ins = resellerInsertPlan($gb, $price);
        if (empty($ins['ok'])) {
            sendmessage($from_id, '❌ خطا در ثبت پلن: ' . strval($ins['message'] ?? ''), $keyboardadmin, 'HTML');
            step('home', $from_id);
            return;
        }
    } else {
        global $pdo;
        try {
            ensureResellerPlanTables();
            $st = $pdo->prepare("INSERT INTO reseller_volume_plan (volume_gb, price, status, sort_order, created_at) VALUES (?,?,1,?,?)");
            $st->execute([$gb, $price, $gb, time()]);
        } catch (Throwable $e) {
            sendmessage($from_id, '❌ خطا در ثبت پلن: ' . $e->getMessage(), $keyboardadmin, 'HTML');
            step('home', $from_id);
            return;
        }
    }
    step('home', $from_id);
    $per = intval(round($price / max(1, $gb)));
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) { require_once $f; }
    $kb = function_exists('resellerVolumeAdminKeyboard') ? resellerVolumeAdminKeyboard() : null;
    sendmessage($from_id, "✅ پلن ثبت شد.\n📊 حجم: <b>" . (function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb) : (number_format($gb) . ' گیگ')) . "</b>\n💵 قیمت: <b>" . number_format($price) . "</b> تومان\n📌 هر گیگ: <b>" . number_format($per) . "</b> تومان\n\n🛠 <b>مدیریت پنل نمایندگان</b>", $kb ?: $keyboardadmin, 'HTML');
    return;
}

if ($__rv_is_admin && preg_match('/^rv_setprice_(\d+)$/', $__rv_step, $m_sp2) && !empty($text) && $datain === '') {
    global $pdo;
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $price = intval(preg_replace('/\D/', '', function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text)));
    $pid = intval($m_sp2[1]);
    if ($price < 1) {
        sendmessage($from_id, '❌ قیمت نامعتبر.', $backadmin, 'HTML');
        return;
    }
    try {
        $pdo->prepare("UPDATE reseller_volume_plan SET price = ? WHERE id = ?")->execute([$price, $pid]);
    } catch (Throwable $e) {
        error_log('rv_setprice: ' . $e->getMessage());
    }
    step('home', $from_id);
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) { require_once $f; }
    $kb = function_exists('resellerVolumeAdminKeyboard') ? resellerVolumeAdminKeyboard() : null;
    sendmessage($from_id, '✅ قیمت به‌روزرسانی شد: ' . number_format($price) . ' تومان' . "\n\n🛠 <b>مدیریت پنل نمایندگان</b>", $kb ?: $keyboardadmin, 'HTML');
    return;
}

if ($__rv_is_admin && preg_match('/^rv_setvol_(\d+)$/', $__rv_step, $m_sv2) && !empty($text) && $datain === '') {
    global $pdo;
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $gb = intval(preg_replace('/\D/', '', function_exists('botNormalizeDigits') ? botNormalizeDigits(strval($text)) : strval($text)));
    $pid = intval($m_sv2[1]);
    if ($gb < 1) {
        sendmessage($from_id, '❌ حجم نامعتبر.', $backadmin, 'HTML');
        return;
    }
    try {
        $pdo->prepare("UPDATE reseller_volume_plan SET volume_gb = ?, sort_order = ? WHERE id = ?")->execute([$gb, $gb, $pid]);
    } catch (Throwable $e) {
        error_log('rv_setvol: ' . $e->getMessage());
    }
    step('home', $from_id);
    $f = __DIR__ . '/reseller_volume.php';
    if (is_file($f)) { require_once $f; }
    $kb = function_exists('resellerVolumeAdminKeyboard') ? resellerVolumeAdminKeyboard() : null;
    sendmessage($from_id, '✅ حجم به‌روزرسانی شد: ' . (function_exists('formatResellerVolumeLabel') ? formatResellerVolumeLabel($gb) : (number_format($gb) . ' گیگ')) . "\n\n🛠 <b>مدیریت پنل نمایندگان</b>", $kb ?: $keyboardadmin, 'HTML');
    return;
}

// toggle_panel_admin + set username (از اطلاعات کاربر)
if ($__rv_is_admin && preg_match('/^toggle_panel_admin_(\d+)$/', strval($datain), $m_tpa)) {
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        return;
    }
    if (function_exists('ensurePanelAdminSchema')) {
        ensurePanelAdminSchema();
    }
    $uid = intval($m_tpa[1]);
    $u = select("user", "*", "id", $uid, "select");
    $cur = intval($u['panel_admin_enabled'] ?? 0);
    update("user", "panel_admin_enabled", $cur ? "0" : "1", "id", $uid);
    if (function_exists('sendAdminUserInfo')) {
        // پیام جدید بفرست
        sendAdminUserInfo($from_id, $uid);
    }
    return;
}

if ($__rv_is_admin && preg_match('/^set_panel_admin_user_(\d+)$/', strval($datain), $m_spa)) {
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        return;
    }
    step('set_panel_admin_user_' . intval($m_spa[1]), $from_id);
    sendmessage($from_id, "نام کاربری ادمین پنل را بفرستید.\nدر پنل ریسلر چک می‌شود (حداکثر " . (defined('PANEL_ADMIN_MAX_PER_USER') ? PANEL_ADMIN_MAX_PER_USER : 5) . " ادمین).", $backadmin, 'HTML');
    return;
}

if ($__rv_is_admin && preg_match('/^pa_rm_(\d+)_(\d+)$/', strval($datain), $m_rm)) {
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        return;
    }
    $uid = intval($m_rm[1]);
    $idx = intval($m_rm[2]);
    $list = function_exists('getUserPanelAdminUsernames') ? getUserPanelAdminUsernames($uid) : [];
    if (isset($list[$idx])) {
        removeUserPanelAdminUsername($uid, $list[$idx]);
    }
    if (function_exists('sendAdminUserInfo')) {
        sendAdminUserInfo($from_id, $uid);
    }
    return;
}

if ($__rv_is_admin && preg_match('/^set_panel_admin_user_(\d+)$/', $__rv_step, $m_ps) && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        step('home', $from_id);
        return;
    }
    $uid = intval($m_ps[1]);
    $uname_clean = trim(strval($text));
    if (!function_exists('addUserPanelAdminUsername')) {
        sendmessage($from_id, '❌ تابع ثبت ادمین موجود نیست.', $keyboardadmin, 'HTML');
        step('home', $from_id);
        return;
    }
    $res = addUserPanelAdminUsername($uid, $uname_clean, true);
    if (empty($res['ok'])) {
        sendmessage($from_id, '❌ ' . strval($res['message'] ?? 'خطا'), $backadmin, 'HTML');
        return; // step stays so can retry
    }
    step('home', $from_id);
    $list = $res['list'] ?? getUserPanelAdminUsernames($uid);
    sendmessage($from_id, "✅ " . strval($res['message'] ?? 'ثبت شد') . "\nادمین‌ها: <code>" . htmlspecialchars(implode(', ', $list), ENT_QUOTES, 'UTF-8') . "</code>", $keyboardadmin, 'HTML');
    if (function_exists('sendAdminUserInfo')) {
        sendAdminUserInfo($from_id, $uid);
    }
    return;
}

if ($__rv_is_admin && $datain === 'rv_list_users') {
    if (!function_exists('isResellerFeatureEnabled') || !isResellerFeatureEnabled()) {
        return;
    }
    $rows = function_exists('listResellerPanelUsers') ? listResellerPanelUsers(50) : [];
    if (!$rows) {
        $txt = "👥 <b>کاربران پنل‌دار</b>\n\nهنوز کاربری ثبت نشده.";
        $kb = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']]]]);
        Editmessagetext($from_id, $message_id, $txt, $kb);
        return;
    }
    $txt = "👥 <b>کاربران پنل‌دار</b> (" . count($rows) . ")\n\n";
    $ik = [];
    foreach ($rows as $r) {
        $uid = intval($r['id']);
        $un = strval($r['username'] ?? '');
        if ($un === '' || $un === 'none') {
            $un = strval($uid);
        }
        $pas = function_exists('parsePanelAdminUsernames') ? parsePanelAdminUsernames($r['panel_admin_username'] ?? '') : [];
        $pa_s = $pas ? implode(', ', $pas) : '-';
        $txt .= "• <code>{$uid}</code> @{$un}\n  🛠 {$pa_s}\n";
        $ik[] = [['text' => "👤 {$un} ({$uid})", 'callback_data' => 'manageuser_' . $uid]];
    }
    $ik[] = [['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']];
    # اگر manageuser callback وجود ندارد از info ساده استفاده کن
    Editmessagetext($from_id, $message_id, $txt, json_encode(['inline_keyboard' => array_merge(
        [[['text' => '🔄 بروزرسانی', 'callback_data' => 'rv_list_users']]],
        [[['text' => '🔙 بازگشت', 'callback_data' => 'rv_hub_back']]]
    )], JSON_UNESCAPED_UNICODE));
    return;
}


if ($__rv_is_admin && $__rv_step === 'rv_cron_levels' && !empty($text) && $datain === '') {
    if ($text === ($textbotlang['Admin']['Back-Adminment'] ?? '')) {
        step('home', $from_id);
        return;
    }
    $parts = [];
    foreach (explode(',', $text) as $p) {
        $p = intval(trim($p));
        if ($p >= 1 && $p <= 100) {
            $parts[] = $p;
        }
    }
    $parts = array_values(array_unique($parts));
    sort($parts);
    if (empty($parts)) {
        sendmessage($from_id, '❌ فرمت نامعتبر. مثال: 80,90,95', $backadmin, 'HTML');
        return;
    }
    $val = implode(',', $parts);
    if (function_exists('setPaySettingValue')) {
        setPaySettingValue('smart_reseller_vol_levels', $val);
    }
    step('home', $from_id);
    sendmessage($from_id, '✅ درصدهای هشدار ذخیره شد: <code>' . $val . '</code>', $keyboardadmin, 'HTML');
    return;
}



if ($datain == 'smartcron_toggle_reseller') {
    if (function_exists('ensureSmartCronSettings')) ensureSmartCronSettings();
    $cur = (function_exists('smartCronFlag') && smartCronFlag('smart_reseller_warn', '0')) ? '1' : '0';
    if (function_exists('setPaySettingValue')) setPaySettingValue('smart_reseller_warn', $cur === '1' ? '0' : '1');
    if (function_exists('buildSmartCronAdminKeyboard')) {
        Editmessagetext($from_id, $message_id, '⚙️ کرون هوشمند', buildSmartCronAdminKeyboard());
    }
    return;
}
if ($datain == 'smartcron_set_reseller_vol') {
    step('rv_cron_levels', $from_id);
    sendmessage($from_id, "درصدهای هشدار حجم Reseller را با ویرگول بفرستید.\nمثال: <code>80,90,95</code>", $backadmin, 'HTML');
    return;
}
