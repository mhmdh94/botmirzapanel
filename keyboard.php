<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'text.php';
if (function_exists('ensureFeatureSettingsColumns')) { ensureFeatureSettingsColumns(); }
$setting = select("setting", "*");
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
//-----------------------------[  text panel  ]-------------------------------
$table_exists = botTableExists('textbot');
$result = $table_exists ? [1] : [];
$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_dec_info' => '',
    'text_dec_usertest' => '',
    'text_fq' => '',
    'text_account' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',

);
if ($table_exists) {
    $textdatabot = select("textbot", "*", null, null, "fetchAll");
    $data_text_bot = array();
    foreach ($textdatabot as $row) {
        $data_text_bot[] = array(
            'id_text' => $row['id_text'],
            'text' => $row['text']
        );
    }
    foreach ($data_text_bot as $item) {
        if (isset($datatextbot[$item['id_text']])) {
            $datatextbot[$item['id_text']] = function_exists('botMigrateMenuLabel') ? botMigrateMenuLabel($item['id_text'], $item['text']) : $item['text'];
        }
    }
}
// ---- Main keyboard: از نسخه 2.2 در functions.php ساخته می‌شود (buildUserMainKeyboard) تا بعد از تغییر موجودی هم قابل ارسال باشد ----
$keyboard = buildUserMainKeyboard(isset($from_id) ? $from_id : 0, $datatextbot, $setting, $admin_ids);



// صفحه حساب کاربری: تاریخچه + افزایش موجودی + کد هدیه + لینک دعوت (نسخه 2.2)
$__acc_rows = [[['text' => '📜 تاریخچه تراکنش‌ها', 'callback_data' => 'user_tx_history']]];
$__acc_row2 = [];
if (!(isset($setting['status_deposit']) && ($setting['status_deposit'] == '0' || $setting['status_deposit'] === 0))) {
    $__acc_row2[] = ['text' => (($datatextbot['text_Add_Balance'] ?? '') !== '' ? $datatextbot['text_Add_Balance'] : '💰 افزایش موجودی'), 'callback_data' => 'acc_wallet'];
}
$__acc_row2[] = ['text' => '🎁 ثبت کد هدیه', 'callback_data' => 'Discount'];
$__acc_rows[] = $__acc_row2;
$__acc_aff = !(isset($setting['status_affiliates_btn']) && ($setting['status_affiliates_btn'] == '0' || $setting['status_affiliates_btn'] === 0));
if ($__acc_aff) {
    try {
        $__acc_aff_row = select("affiliates", "*", null, null, "select");
        if ($__acc_aff_row && isset($__acc_aff_row['affiliatesstatus']) && $__acc_aff_row['affiliatesstatus'] == 'offaffiliates') {
            $__acc_aff = false;
        }
    } catch (Throwable $e) {}
}
if ($__acc_aff) {
    $__acc_rows[] = [['text' => '🤝 لینک دعوت من', 'callback_data' => 'acc_invite']];
}
$keyboardPanel = json_encode(['inline_keyboard' => $__acc_rows]);
// منوی ادمین گروه‌بندی‌شده: آمار | فروش/مالی | پنل | کاربران | تنظیمات/متن
$__kb_admin_rows = [
    [['text' => $textbotlang['Admin']['keyboardadmin']['bot_statistics']], ['text' => $textbotlang['Admin']['keyboardadmin']['bot_health'] ?? '🩺 سلامت ربات']],
    [['text' => $textbotlang['Admin']['keyboardadmin']['shop_section']], ['text' => $textbotlang['Admin']['keyboardadmin']['finance']]],
];
try {
    if (function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled()) {
        $__kb_admin_rows[] = [
            ['text' => $textbotlang['Admin']['keyboardadmin']['manage_panel']],
            ['text' => (isset($textbotlang['Admin']['keyboardadmin']['reseller_volume']) ? $textbotlang['Admin']['keyboardadmin']['reseller_volume'] : '⚙️ مدیریت پنل نمایندگان')],
        ];
    } else {
        $__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['manage_panel']]];
    }
} catch (Throwable $e) {
    $__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['manage_panel']]];
}
$__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['user_search']], ['text' => $textbotlang['Admin']['keyboardadmin']['user_services']], ['text' => $textbotlang['Admin']['keyboardadmin']['send_message']]];
$__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['settings']], ['text' => $textbotlang['Admin']['keyboardadmin']['admin_section']]];
$__kb_admin_rows[] = [['text' => $textbotlang['users']['backhome']]];
$keyboardadmin = json_encode([
    'keyboard' => $__kb_admin_rows,
    'resize_keyboard' => true
]);
$CurrencyWalletManage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);

$CartManage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['moeny']['card_number_settings']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$admin_section_panel = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Addedadmin']], ['text' => $textbotlang['Admin']['Removeedadmin']]],
        [['text' => $textbotlang['Admin']['manageadmin']['showlistbtn']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]],

    ],
    'resize_keyboard' => true
]);
$keyboard_usertest = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['getlimitusertest']['setlimitallbtn']]],
        [['text' => $textbotlang['Admin']['Usertest']['settimeusertest']], ['text' => $textbotlang['Admin']['Usertest']['setvolumeusertest']]],
        [['text' => $textbotlang['Admin']['Usertest']['reset_all_limits']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
// تنظیمات: قابلیت‌ها | کرون/تست | کانال‌ها | مشارکت | آپدیت
$setting_panel = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['keyboardadmin']['seetingstatus']], ['text' => $textbotlang['Admin']['keyboardadmin']['settingscron']]],
        [['text' => $textbotlang['Admin']['channel']['channelreport']], ['text' => $textbotlang['Admin']['channel']['setting']]],
        [['text' => $textbotlang['Admin']['keyboardadmin']['affiliate_settings']], ['text' => $textbotlang['Admin']['keyboardadmin']['bot_text_settings']]],
        [['text' => $textbotlang['Admin']['keyboardadmin']['bot_update']], ['text' => ($textbotlang['Admin']['keyboardadmin']['db_backup'] ?? '💾 پشتیبان‌گیری')]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$PaySettingcard = (select("PaySetting", "ValuePay", "NamePay", 'Cartstatus', "select")['ValuePay'] ?? '');
$PaySettingdigi = (select("PaySetting", "ValuePay", "NamePay", 'digistatus', "select")['ValuePay'] ?? '');
$step_payment = [
    'inline_keyboard' => []
];
if ($PaySettingcard == "oncard") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['moeny']['cart_to_Cart_btn'], 'callback_data' => "cart_to_offline"],
    ];
}
if ($PaySettingdigi == "ondigi") {
    $step_payment['inline_keyboard'][] = [
        ['text' => getCurrencyPaymentButtonText(), 'callback_data' => "iranpay"]
    ];
}
$step_payment['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['closelist'], 'callback_data' => "closelist"]
];
$step_payment = json_encode($step_payment);
$User_Services = function_exists('buildAdminUserServicesInlineKeyboard')
    ? buildAdminUserServicesInlineKeyboard()
    : json_encode(['inline_keyboard' => [[['text' => '🔙 بستن', 'callback_data' => 'nav_sec_close']]]]);
$keyboardhelpadmin = json_encode([ // Help removed

    'keyboard' => [
        [['text' => $textbotlang['Admin']['Help']['addhelp']], ['text' => $textbotlang['Admin']['Help']['removehelpbtn']]],
        [['text' => $textbotlang['Admin']['Help']['edithelp']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$shopkeyboard = function_exists('buildAdminShopInlineKeyboard')
    ? buildAdminShopInlineKeyboard()
    : json_encode(['inline_keyboard' => [[['text' => '🔙 بستن', 'callback_data' => 'nav_sec_close']]]], JSON_UNESCAPED_UNICODE);
$confrimrolls = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['rulesaccept']]],
    ],
    'resize_keyboard' => true
]);
$request_contact = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['sendnumber'], 'request_contact' => true]],
        [['text' => $textbotlang['users']['backhome']]]
    ],
    'resize_keyboard' => true
]);
$sendmessageuser = function_exists('buildAdminBroadcastInlineKeyboard')
    ? buildAdminBroadcastInlineKeyboard()
    : json_encode(['inline_keyboard' => [[['text' => '🔙 بستن', 'callback_data' => 'nav_sec_close']]]]);
$channelkeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['channel']['changechannelbtn']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$backuser = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['backhome']]]
    ],
    'resize_keyboard' => true,
]);
// فقط بازگشت در reply — خودکار انتخاب کن اینلاین زیر پیام یوزرنیم است
$keyboard_getusername = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['backhome']]],
    ],
    'resize_keyboard' => true,
]);
$inline_getusername = json_encode([
    'inline_keyboard' => [
        [['text' => '🎲 خودکار انتخاب کن', 'callback_data' => 'auto_username']],
        [['text' => $textbotlang['users']['backhome'] ?? '🏠 بازگشت به منوی اصلی', 'callback_data' => 'backuser']],
    ],
]);
$backadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true,
]);
$table_exists = botTableExists('marzban_panel');
$result = $table_exists ? [1] : [];
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);
}
$table_exists = botTableExists('help_disabled_removed');
$result = $table_exists ? [1] : [];
if ($table_exists) {
    $help = [];
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $help[] = [$row['name_os']];
    }
    $help_arr = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($help as $button) {
        $help_arr['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $help_arr['keyboard'][] = [
        ['text' => $textbotlang['users']['backhome']],
    ];
    $json_list_help = json_encode($help_arr);
}

$users = select("user", "*", "id", $from_id, "select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
    );
}
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'activepanel'");
$stmt->execute();
$list_marzban_panel_users = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($users['step'] == "getusernameinfo") {
        $list_marzban_panel_users['inline_keyboard'][] = [
            ['text' => $result['name_panel'], 'callback_data' => "locationnotuser_{$result['id']}"]
        ];
    } else {
        $list_marzban_panel_users['inline_keyboard'][] = [
            ['text' => $result['name_panel'], 'callback_data' => "location_{$result['id']}"]
        ];
    }
}
$list_marzban_panel_users['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"],
];
$list_marzban_panel_user = json_encode($list_marzban_panel_users);

$list_marzban_panel_usertest = [
    'inline_keyboard' => [],
];
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE statusTest = 'ontestshowpanel'");
$stmt->execute();
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list_marzban_panel_usertest['inline_keyboard'][] = [
        ['text' => $result['name_panel'], 'callback_data' => "locationtests_{$result['id']}"]
    ];
}
$list_marzban_panel_usertest['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"],
];
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);
$textbot = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['changetext']['set_start_text']], ['text' => $textbotlang['users']['changetext']['law_text']]],
        [['text' => $textbotlang['users']['changetext']['mandatory_membership_description']], ['text' => $textbotlang['users']['changetext']['faq_description']]],
        [['text' => $textbotlang['users']['changetext']['buy_subscription_button']], ['text' => $textbotlang['users']['changetext']['purchased_service_button']]],
        [['text' => $textbotlang['users']['changetext']['increase_balance_button']], ['text' => $textbotlang['users']['changetext']['user_account_button']]],
        [['text' => $textbotlang['users']['changetext']['test_account_button']], ['text' => $textbotlang['users']['changetext']['support_button']]],
        [['text' => $textbotlang['users']['changetext']['tariff_list_button']], ['text' => $textbotlang['users']['changetext']['tariff_list_description']]],
        [['text' => $textbotlang['users']['changetext']['faq_button']], ['text' => $textbotlang['users']['changetext']['tutorial_button']]],
        [['text' => ($textbotlang['Admin']['changetext']['msg_support_prompt'] ?? 'متن: درخواست پیام پشتیبانی')], ['text' => ($textbotlang['Admin']['changetext']['msg_support_sent'] ?? 'متن: تأیید ارسال به پشتیبانی')]],
        [['text' => ($textbotlang['Admin']['changetext']['msg_receipt_sent'] ?? 'متن: رسید ارسال شد')], ['text' => ($textbotlang['Admin']['changetext']['msg_receipt_need_photo'] ?? 'متن: فقط عکس رسید')]],
        [['text' => $textbotlang['Admin']['changetext']['msg_buy_disabled']], ['text' => $textbotlang['Admin']['changetext']['msg_deposit_closed']]],
        [['text' => $textbotlang['Admin']['changetext']['msg_extend_disabled']], ['text' => $textbotlang['Admin']['changetext']['msg_support_disabled']]],
        [['text' => ($textbotlang['Admin']['changetext']['msg_reseller_request_info'] ?? 'متن: درخواست پنل نمایندگی')], ['text' => ($textbotlang['Admin']['changetext']['msg_affiliates_info'] ?? 'متن: مشارکت در فروش')]],
        [['text' => ($textbotlang['Admin']['changetext']['msg_phone_verify'] ?? 'متن: احراز هویت شماره')], ['text' => ($textbotlang['Admin']['changetext']['text_bot_off'] ?? 'متن: ربات خاموش')]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
], JSON_UNESCAPED_UNICODE);
//--------------------------------------------------
$table_exists = botTableExists('protocol');
$result = $table_exists ? [1] : [];
if ($table_exists) {
    $getdataprotocol = select("protocol", "*", null, null, "fetchAll");
    $protocol = [];
    foreach ($getdataprotocol as $result) {
        $protocol[] = [['text' => $result['NameProtocol']]];
    }
    $protocol[] = [['text' => $textbotlang['Admin']['Back-Adminment']]];
    $keyboardprotocollist = json_encode(['resize_keyboard' => true, 'keyboard' => $protocol]);
}
//--------------------------------------------------
$table_exists = botTableExists('product');
$result = $table_exists ? [1] : [];
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :Location OR Location = '/all'");
    $stmt->bindParam(':Location', $text, PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
}
//--------------------------------------------------
$table_exists = botTableExists('Discount');
$result = $table_exists ? [1] : [];
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin = json_encode($list_Discount);
}
//--------------------------------------------------
$table_exists = botTableExists('DiscountSell');
$result = $table_exists ? [1] : [];
$namepanel = [];
if ($table_exists) {
    $DiscountSell = [];
    $stmt = $pdo->prepare("SELECT * FROM DiscountSell");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $DiscountSell[] = [$row['codeDiscount']];
    }
    $list_Discountsell = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discountsell['keyboard'][] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    foreach ($DiscountSell as $button) {
        $list_Discountsell['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin_sell = json_encode($list_Discountsell);
}
// نسخه 2.6.2: دکمه «ثبت کد تخفیف» فقط وقتی هست که حداقل یک کد تخفیف با ظرفیت باقی‌مانده تعریف شده باشد
$__has_sell_discount = false;
if ($table_exists) {
    try {
        if (function_exists('botEnsureDiscountExpireCol')) {
            botEnsureDiscountExpireCol();
        }
        $__has_sell_discount = intval($pdo->query("SELECT COUNT(*) FROM DiscountSell WHERE CAST(usedDiscount AS SIGNED) < CAST(limitDiscount AS SIGNED) AND (expire_at = 0 OR expire_at > UNIX_TIMESTAMP())")->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $__has_sell_discount = !empty($DiscountSell);
    }
}
$__payment_rows = [[['text' => $textbotlang['users']['buy']['payandGet'], 'callback_data' => "confirmandgetservice"]]];
if ($__has_sell_discount) {
    $__payment_rows[] = [['text' => $textbotlang['users']['buy']['discount'], 'callback_data' => "aptdc"]];
}
$__payment_rows[] = [['text' => $textbotlang['users']['buy']['cancel'] ?? '❌ انصراف', 'callback_data' => "backuser"]];
$payment = json_encode(['inline_keyboard' => $__payment_rows]);
$change_product = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Product']['editprice']], ['text' => $textbotlang['Admin']['Product']['editvolume']], ['text' => $textbotlang['Admin']['Product']['edittime']]],
        [['text' => $textbotlang['Admin']['Product']['editname']], ['text' => $textbotlang['Admin']['Product']['editcategory']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['users']['customusernameorder']]],
        [['text' => $textbotlang['users']['customidAndRandom']]],
        [['text' => $textbotlang['users']['customusername']]],
        [['text' => $textbotlang['users']['customtextandrandom']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
// منوی مدیریت پاسارگارد — بدون اینباند (گروه از پنل خوانده می‌شود)
$optionPasarguard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['btnshowconnect']], ['text' => $textbotlang['Admin']['managepanel']['show_groups'] ?? '📂 گروه‌های پنل']],
        [['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']], ['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['methodusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['on_hold_status']]],
        [['text' => $textbotlang['Admin']['managepanel']['sublinkstatus']], ['text' => $textbotlang['Admin']['managepanel']['configstatus']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
// سازگاری با کدهای قدیمی که $optionMarzban می‌خواهند (پنل فعلی پاسارگارد)
$optionMarzban = $optionPasarguard;

// منوی مدیریت مرزبان — با تنظیم اینباند و پروتکل
$optionMarzbanPanel = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['btnshowconnect']], ['text' => $textbotlang['Admin']['managepanel']['show_groups'] ?? '📂 گروه‌های پنل']],
        [['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']], ['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['setinbound']], ['text' => $textbotlang['Admin']['managepanel']['methodusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['on_hold_status']]],
        [['text' => $textbotlang['Admin']['managepanel']['sublinkstatus']], ['text' => $textbotlang['Admin']['managepanel']['configstatus']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$optionmikrotik = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['btnshowconnect']], ['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']], ['text' => $textbotlang['Admin']['managepanel']['setgroup']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['Admin']['managepanel']['methodusername']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$optionwgdashboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editinound']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$options_ui = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']], ['text' => $textbotlang['Admin']['managepanel']['setinbound']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']]],
        [['text' => $textbotlang['Admin']['managepanel']['methodusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['sublinkstatus']], ['text' => $textbotlang['Admin']['managepanel']['configstatus']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$optionMarzneshin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['btnshowconnect']], ['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['users']['status']['manageService']]],
        [['text' => $textbotlang['Admin']['managepanel']['methodusername']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['on_hold_status']]],
        [['text' => $textbotlang['Admin']['managepanel']['sublinkstatus']], ['text' => $textbotlang['Admin']['managepanel']['configstatus']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$optionX_ui_single = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['managepanel']['btnshowconnect']], ['text' => $textbotlang['Admin']['managepanel']['showpanelbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['showpaneltestbtn']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['namepanel']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['removepanel']]],
        [['text' => $textbotlang['Admin']['managepanel']['methodusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editpassword']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editusername']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editurl']], ['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['editinound']]],
        [['text' => $textbotlang['Admin']['managepanel']['sublinkstatus']], ['text' => $textbotlang['Admin']['managepanel']['configstatus']]],
        [['text' => $textbotlang['Admin']['managepanel']['keyboardpanel']['linksub']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$supportoption = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"],
        ],
        [
            ['text' => $textbotlang['users']['sendmessagesupport'], 'callback_data' => "support"],
        ],
    ]
]);
// منوی مشارکت در فروش — تنظیمات اصلی داخل پنل اینلاین است
$affiliates = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
$typepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['Admin']['managepanel']['type']['pasarguard'] ?? 'پاسارگارد', 'callback_data' => "typepanel%pasarguard"],
            ['text' => $textbotlang['Admin']['managepanel']['type']['marzban'] ?? 'مرزبان', 'callback_data' => "typepanel%marzban"],
        ],
        [
            ['text' => $textbotlang['Admin']['Back-Adminment'], 'callback_data' => "back_admin"]
        ]
    ],
]);
$keyboardcronjob = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['cron']['test']['active']], ['text' => $textbotlang['Admin']['cron']['test']['disable']]],
        [['text' => $textbotlang['Admin']['cron']['volume']['active']], ['text' => $textbotlang['Admin']['cron']['volume']['disable']]],
        [['text' => $textbotlang['Admin']['cron']['time']['active']], ['text' => $textbotlang['Admin']['cron']['time']['disable']]],
        [['text' => $textbotlang['Admin']['cron']['remove']['active']], ['text' => $textbotlang['Admin']['cron']['remove']['disable']]],
        [['text' => $textbotlang['Admin']['cron']['remove']['timeset']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);

$helpedit = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Help']['change']['name']], ['text' => $textbotlang['Admin']['Help']['change']['dec']]],
        [['text' => $textbotlang['Admin']['Help']['change']['editmedia']]],
        [['text' => $textbotlang['Admin']['Back-Adminment']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategory()
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' => $row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['Back-Adminment']],
    ];
    return json_encode($list_category);
}
function KeyboardCategorybuy($callback_data, $location)
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmts = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category" . (function_exists('botProductVisibleSql') ? botProductVisibleSql() : ''));
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['id'], PDO::PARAM_STR);
        $stmts->execute();
        if ($stmts->rowCount() == 0)
            continue;
        $list_category['inline_keyboard'][] = [['text' => $row['remark'], 'callback_data' => "categorylist_" . $row['id']]];
    }
    $list_category['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backmenu'], "callback_data" => $callback_data],
    ];
    return json_encode($list_category);
}
function KeyboardProduct($location, $backdata, $MethodUsername, $categoryid = null)
{
    global $pdo, $textbotlang;
    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') " . (function_exists('botProductVisibleSql') ? botProductVisibleSql() : '');
    if ($categoryid !== null && $categoryid !== '') {
        $query .= " AND category = :category ";
    }
    $query .= " ORDER BY CAST(Volume_constraint AS UNSIGNED) ASC, CAST(price_product AS UNSIGNED) ASC, id ASC ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':location', $location, PDO::PARAM_STR);
    if ($categoryid !== null && $categoryid !== '') {
        $stmt->bindValue(':category', $categoryid, PDO::PARAM_STR);
    }
    $stmt->execute();
    $product = ['inline_keyboard' => []];
    // ردیف عنوان (مثل اسکرین دو‌ستونه: محصول | قیمت)
    // در تلگرام اندیس ۰ = چپ، اندیس ۱ = راست → قیمت چپ، محصول راست
    $product['inline_keyboard'][] = [
        ['text' => '💵 قیمت', 'callback_data' => 'product_header'],
        ['text' => '🛍 محصول', 'callback_data' => 'product_header'],
    ];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cb = ($MethodUsername == ($textbotlang['users']['customusername'] ?? ''))
            ? ("prodcutservices_" . $result['code_product'])
            : ("prodcutservice_" . $result['code_product']);
        $name = strval($result['name_product'] ?? '');
        // نسخه 2.5.0: برچسب محصول پیشنهادی
        if (function_exists('botFeaturedProductCode') && botFeaturedProductCode() !== '' && botFeaturedProductCode() === strval($result['code_product'] ?? '')) {
            // نسخه 2.5.2: فقط ستاره + دکمه رنگی (بدون کلمه «پیشنهادی» تا در گوشی جا شود)
            $name = '⭐ ' . $name;
            $__feat = true;
        } else {
            $__feat = false;
        }
        // نسخه 2.6.4: پرفروشترین محصول ۳۰ روز اخیر فقط با 🔥
        if (function_exists('botBestSellerName') && botBestSellerName() !== '' && botBestSellerName() === strval($result['name_product'] ?? '')) {
            $name = '🔥 ' . $name;
        }
        $price = number_format(intval($result['price_product'] ?? 0)) . ' تومان';
        // دو ستون: نام محصول + قیمت — هر دو همان callback انتخاب محصول
        $__b1 = ['text' => $price, 'callback_data' => $cb];
        $__b2 = ['text' => $name, 'callback_data' => $cb];
        if ($__feat && function_exists('botButtonColorsOn') && botButtonColorsOn()) {
            $__b1['style'] = 'success';
            $__b2['style'] = 'success';
        }
        $product['inline_keyboard'][] = [$__b1, $__b2];
    }
    $product['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backmenu'] ?? '↩️ بازگشت', 'callback_data' => $backdata],
    ];

    return json_encode($product);
}