<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'text.php';
if (function_exists('ensureFeatureSettingsColumns')) { ensureFeatureSettingsColumns(); }
$setting = select("setting", "*");
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
//-----------------------------[  text panel  ]-------------------------------
$sql = "SHOW TABLES LIKE 'textbot'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
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
            $datatextbot[$item['id_text']] = $item['text'];
        }
    }
}
// ---- Main keyboard (dynamic by feature flags) ----
$row1 = [];
$row1[] = ['text' => $datatextbot['text_sell']];
if (!isset($setting['status_usertest']) || $setting['status_usertest'] == '1' || $setting['status_usertest'] === 1) {
    $row1[] = ['text' => $datatextbot['text_usertest']];
}
$keyboard_rows = [];
try {
    if (
        isset($from_id) && intval($from_id) > 0
        && function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled()
        && function_exists('isUserPanelAdmin') && isUserPanelAdmin($from_id)
    ) {
        $keyboard_rows[] = [[
            'text' => ($textbotlang['users']['reseller_volume']['btn'] ?? 'پنل نمایندگی')
        ]];
    }
} catch (Throwable $e) {}
if (!empty($row1)) {
    $keyboard_rows[] = $row1;
}
$row_svc = [['text' => $datatextbot['text_Purchased_services']]];
$__dep_on = true;
if (isset($setting['status_deposit']) && ($setting['status_deposit'] == '0' || $setting['status_deposit'] === 0)) {
    $__dep_on = false;
}
if ($__dep_on) {
    $row_svc[] = ['text' => $datatextbot['text_Add_Balance']];
}
$keyboard_rows[] = $row_svc;
$account_btn = $datatextbot['text_account'];
if (isset($setting['show_balance']) && ($setting['show_balance'] == '1' || $setting['show_balance'] === 1)) {
    $bal_user = 0;
    if (isset($from_id) && $from_id) {
        $u_bal = select("user", "Balance", "id", $from_id, "select");
        if ($u_bal && isset($u_bal['Balance'])) {
            $bal_user = intval($u_bal['Balance']);
        }
    }
    $account_btn = $datatextbot['text_account'] . " | " . number_format($bal_user) . " ت";
}
$row_account = [['text' => $account_btn]];
if (!isset($setting['status_tariff_list']) || $setting['status_tariff_list'] == '1' || $setting['status_tariff_list'] === 1) {
    $row_account[] = ['text' => $datatextbot['text_Tariff_list']];
}
$keyboard_rows[] = $row_account;
$row_sup = [];
$__sup_on = true;
if (isset($setting['status_support']) && ($setting['status_support'] == '0' || $setting['status_support'] === 0)) {
    $__sup_on = false;
}
if ($__sup_on) {
    $row_sup[] = ['text' => $datatextbot['text_support']];
}
$aff_on = true;
if (isset($setting['status_affiliates_btn']) && ($setting['status_affiliates_btn'] == '0' || $setting['status_affiliates_btn'] === 0)) {
    $aff_on = false;
}
try {
    $aff_row = select("affiliates", "*", null, null, "select");
    if ($aff_row && isset($aff_row['affiliatesstatus']) && $aff_row['affiliatesstatus'] == 'offaffiliates') {
        $aff_on = false;
    }
} catch (Throwable $e) {}
if ($aff_on) {
    $row_sup[] = ['text' => $textbotlang['users']['affiliates']['btn']];
}
if (!empty($row_sup)) {
    $keyboard_rows[] = $row_sup;
}
$keyboard = [
    'keyboard' => $keyboard_rows,
    'resize_keyboard' => true
];
if (isset($from_id) && is_array($admin_ids) && in_array($from_id, $admin_ids)) {
    $keyboard['keyboard'][] = [
        ['text' => $textbotlang['Admin']['commendadmin']],
    ];
}
$keyboard = json_encode($keyboard);
$keyboardPanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => '📜 تاریخچه تراکنش‌ها', 'callback_data' => 'user_tx_history'],
        ],
    ],
    'resize_keyboard' => true
]);
// منوی ادمین: بدون دکمه تکراری اضافه کردن پنل (فقط مدیریت پنل)
$__kb_admin_rows = [
    [['text' => $textbotlang['Admin']['keyboardadmin']['bot_statistics']]],
    [['text' => $textbotlang['Admin']['keyboardadmin']['shop_section']], ['text' => $textbotlang['Admin']['keyboardadmin']['finance']]],
    [['text' => $textbotlang['Admin']['keyboardadmin']['manage_panel']]],
];
try {
    if (function_exists('isResellerFeatureEnabled') && isResellerFeatureEnabled()) {
        $__kb_admin_rows[] = [[
            'text' => (isset($textbotlang['Admin']['keyboardadmin']['reseller_volume']) ? $textbotlang['Admin']['keyboardadmin']['reseller_volume'] : '⚙️ مدیریت پنل نمایندگان')
        ]];
    }
} catch (Throwable $e) {}
$__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['user_search']], ['text' => $textbotlang['Admin']['keyboardadmin']['user_services']], ['text' => $textbotlang['Admin']['keyboardadmin']['send_message']]];
$__kb_admin_rows[] = [['text' => $textbotlang['Admin']['keyboardadmin']['settings']], ['text' => $textbotlang['Admin']['keyboardadmin']['admin_section']]];
$__kb_admin_rows[] = [['text' => $textbotlang['users']['backhome']]];
$keyboardadmin = json_encode([
    'keyboard' => $__kb_admin_rows,
    'resize_keyboard' => true
]);
