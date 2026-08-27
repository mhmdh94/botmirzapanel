<?php

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    error_log('Neither fastcgi_finish_request nor litespeed_finish_request is available.');
}

ini_set('error_log', 'error_log');
$version = "فورک شده";
date_default_timezone_set('Asia/Tehran');
require_once 'config.php';
require_once 'botapi.php';
require_once 'jdf.php';
require_once 'text.php';
require_once 'keyboard.php';
require_once 'functions.php';
require_once 'panels.php';
require_once 'vendor/autoload.php';

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
if (is_dir('installer')) {
    deleteFolder('installer');
}
$first_name = sanitizeUserName($first_name);
if (!in_array($Chat_type, ["private"]))
    return;
#-----------telegram_ip_ranges------------#
if (!checktelegramip())
    die("Unauthorized access");
#-------------Variable----------#
$users_ids = select("user", "id", null, null, "FETCH_COLUMN");
$setting = select("setting", "*");
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
if (!in_array($from_id, $users_ids) && intval($from_id) != 0) {
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => '👤 اطلاعات کاربر', 'callback_data' => 'userinfo_pay_' . $from_id],
            ],
        ]
    ]);
    $newuser = sprintf($textbotlang['Admin']['ManageUser']['NewUserMessage'], $first_name, $username, $from_id, $from_id);
    // اگر با لینک مشارکت در فروش آمده، فقط در این حالت معرف را بنویس
    if (isset($text) && is_string($text) && strpos($text, "/start ") === 0) {
        $token = trim(str_replace("/start ", "", $text));
        $affiliatesid = 0;
        if ($token !== '') {
            $refRow = select("user", "id", "ref_code", $token, "select");
            if ($refRow !== false && isset($refRow['id'])) {
                $affiliatesid = intval($refRow['id']);
            } elseif (ctype_digit($token)) {
                $affiliatesid = intval($token);
            }
        }
        if ($affiliatesid > 0 && $affiliatesid != intval($from_id) && in_array($affiliatesid, $users_ids)) {
            $inv = select("user", "*", "id", $affiliatesid, "select");
            $inv_user = is_array($inv) && !empty($inv['username']) ? '@' . $inv['username'] : '';
            $newuser .= "\n👥 معرف: <a href=\"tg://user?id={$affiliatesid}\">{$affiliatesid}</a>";
            if ($inv_user !== '') {
                $newuser .= " ({$inv_user})";
            }
        }
    }
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $newuser, $Response, 'html');
    }
    // گزارش استارت کاربر جدید در کانال گزارش ارسال نمی‌شود (فقط به ادمین)
}
if (intval($from_id) != 0) {
    if (intval($setting['status_verify']) == 1) {
        $verify = 0;
    } else {
        $verify = 1;
    }

    do {
        $ref_code = bin2hex(random_bytes(16));
        $stmt_check = $pdo->prepare("SELECT 1 FROM user WHERE ref_code = :ref_code");
        $stmt_check->bindParam(':ref_code', $ref_code);
        $stmt_check->execute();

    } while ($stmt_check->fetchColumn());

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO user
            (id, ref_code, step, limit_usertest, User_Status, number, Balance,
            pagenumber, username, message_count, last_message_time,
            affiliatescount, affiliates, verify)
        VALUES
            (:from_id, :ref_code, 'none', :limit_usertest_all, 'Active', 'none', '0',
            '1', :username, '0', '0', '0', '0', :verify)"
    );
    $stmt->bindParam(':ref_code', $ref_code);
    $stmt->bindParam(':verify', $verify);
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
}
$user = select("user", "*", "id", $from_id, "select");
if ($user == false) {
    $user = array();
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'username' => '',
        'limit_usertest' => '',
        'last_message_time' => '',
        'affiliates' => '',
    );
}
if (($setting['status_verify'] == "1" && intval($user['verify']) == 0) && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $textbotlang['users']['VerifyUser'], null, 'html');
    return;
}
;
$channels = array();
$helpdata = select("help", "*");
$datatextbotget = select("textbot", "*", null, null, "fetchAll");
$id_invoice = select("invoice", "id_invoice", null, null, "FETCH_COLUMN");
$channels = select("channels", "*");
$usernameinvoice = select("invoice", "username", null, null, "FETCH_COLUMN");
$code_Discount = select("Discount", "code", null, null, "FETCH_COLUMN");
$users_ids = select("user", "id", null, null, "FETCH_COLUMN");
$marzban_list = select("marzban_panel", "name_panel", null, null, "FETCH_COLUMN");
$name_product = select("product", "name_product", null, null, "FETCH_COLUMN");
$SellDiscount = select("DiscountSell", "codeDiscount", null, null, "FETCH_COLUMN");
$ManagePanel = new ManagePanel();
$datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}

$datatextbot = array(
    'text_usertest' => '',
    'text_Purchased_services' => '',
    'text_support' => '',
    'text_help' => '',
    'text_start' => '',
    'text_bot_off' => '',
    'text_roll' => '',
    'text_fq' => '',
    'text_dec_fq' => '',
    'text_account' => '',
    'text_sell' => '',
    'text_Add_Balance' => '',
    'text_channel' => '',
    'text_Discount' => '',
    'text_Tariff_list' => '',
    'text_dec_Tariff_list' => '',
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
if (function_exists('ensureEditableStatusTexts')) { ensureEditableStatusTexts(); }

if (function_exists('shell_exec') && is_callable('shell_exec')) {
    $existingCronCommands = shell_exec('crontab -l');
    $phpFilePath = "https://$domainhosts/cron/sendmessage.php";
    $cronCommand = "*/1 * * * * curl $phpFilePath";
    if (strpos($existingCronCommands, $cronCommand) === false) {
        $command = "(crontab -l ; echo '$cronCommand') | crontab -";
        shell_exec($command);
    }
}
#---------channel--------------#
if ($user['username'] == "none" || $user['username'] == null) {
    update("user", "username", $username, "id", $from_id);
}
#-----------User_Status------------#
if ($user['User_Status'] == "block") {
    $textblock = sprintf($textbotlang['Admin']['ManageUser']['BlockedUser'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}
if (strpos($text, "/start ") !== false) {
    if ($user['affiliates'] != 0) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateseduser'], null, 'html');
        return;
    }
    $affiliatesvalue = select("affiliates", "*", null, null, "select")['affiliatesstatus'];
    if ($affiliatesvalue == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
        return;
    }
    $token = str_replace("/start ", "", $text);
    $refRow = select("user", "id", "ref_code", $token, "select");
    if ($refRow !== false) {
        $affiliatesid = $refRow['id'];                 // modern link found
    }
    /*  2️⃣  fall back to legacy numeric ID  */ elseif (ctype_digit($token)) {
        $affiliatesid = (int) $token;                   // old link
    }
    /*  3️⃣  invalid token → pretend there is no referrer       */ else {
        $affiliatesid = 0;                             // will fail the in_array() test below
    }
    if (ctype_digit($affiliatesid)) {
        if (!in_array($affiliatesid, $users_ids)) {
            sendmessage($from_id, $textbotlang['users']['affiliates']['affiliatesyou'], null, 'html');
            return;
        }
        if ($affiliatesid == $from_id) {
            sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
            return;
        }
        $inviterData = select("user", "affiliates", "id", $affiliatesid, "select");
        if ($inviterData && intval($inviterData['affiliates']) === intval($from_id)) {
            sendmessage(
                $from_id,
                $textbotlang['users']['affiliates']['invalidMutual'],
                null,
                'html'
            );
            return;
        }
        $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
        if ($marzbanDiscountaffiliates['Discount'] == "onDiscountaffiliates") {
            $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
            $Balance_user = select("user", "*", "id", $affiliatesid, "select");
            $Balance_add_user = $Balance_user['Balance'] + $marzbanDiscountaffiliates['price_Discount'];
            update("user", "Balance", $Balance_add_user, "id", $affiliatesid);
            $addbalancediscount = number_format($marzbanDiscountaffiliates['price_Discount'], 0);
            sendmessage($affiliatesid, sprintf($textbotlang['users']['affiliates']['giftuser'], $addbalancediscount, $from_id), null, 'html');
        }
        sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
        $useraffiliates = select("user", "*", "id", $affiliatesid, "select");
        $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
        update("user", "affiliates", $affiliatesid, "id", $from_id);
        update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
    }
}
$timebot = time();
$TimeLastMessage = $timebot - intval($user['last_message_time']);
if (floor($TimeLastMessage / 60) >= 1) {
    update("user", "last_message_time", $timebot, "id", $from_id);
    update("user", "message_count", "1", "id", $from_id);
} else {
    if (!in_array($from_id, $admin_ids)) {
        $addmessage = intval($user['message_count']) + 1;
        update("user", "message_count", $addmessage, "id", $from_id);
        if ($user['message_count'] >= "35") {
            $User_Status = "block";
            update("user", "User_Status", $User_Status, "id", $from_id);
            update("user", "description_blocking", $textbotlang['users']['spamtext'], "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['spam']['spamedmessage'], null, 'html');
            if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['spam_block'])) {
                $spam_txt = sprintf(
                    $textbotlang['Admin']['Report']['spam_block'],
                    $from_id,
                    $username ?? ($user['username'] ?? '-'),
                    $textbotlang['users']['spamtext'] ?? 'spam'
                );
                sendChannelReport('rpt_spam_block', $spam_txt);
            }
            return;
        }
    }
    if ($setting['Bot_Status'] == "✅  ربات روشن است" and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['updatingbot'], null, 'html');
        foreach ($admin_ids as $admin) {
            sendmessage($admin, "❌ ادمین عزیز ربات فعال نیست جهت فعالسازی به منوی تنظیمات عمومی > وضعیت قابلیت ها بروید تا رباتتان فعال شود.", null, 'html');
        }
        return;
    }
} #-----------Channel------------#
$force_channel_on = !isset($setting['force_channel']) || $setting['force_channel'] == '1' || $setting['force_channel'] === 1;
$chanelcheck = ($force_channel_on && !empty($channels['link'])) ? channel($channels['link']) : [];
if ($datain == "confirmchannel") {
    if (count($chanelcheck) != 0 && !in_array($from_id, $admin_ids)) {
        telegram(
            'answerCallbackQuery',
            array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 5,
            )
        );
    } else {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['channel']['confirmed'], $keyboard, 'html');
    }
    return;
}
if (count($chanelcheck) != 0 && !in_array($from_id, $admin_ids)) {
    $link_channel = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['channel']['text_join'], 'url' => "https://t.me/" . $chanelcheck[0]],
            ],
            [
                ['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"],
            ],
        ]
    ]);
    sendmessage($from_id, $datatextbot['text_channel'], $link_channel, 'html');
    return;
}
#-----------roll------------#
if ($setting['roll_Status'] == "1" && $user['roll_Status'] == 0 && $text != $textbotlang['users']['rulesaccept'] && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_roll'], $confrimrolls, 'html');
    return;
}
if ($text == $textbotlang['users']['rulesaccept']) {
    sendmessage($from_id, $textbotlang['users']['Rules'], $keyboard, 'html');
    $confrim = true;
    update("user", "roll_Status", $confrim, "id", $from_id);
}

#-----------Bot_Status------------#
if ($setting['Bot_Status'] == "0" && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $datatextbot['text_bot_off'], null, 'html');
    return;
}
#-----------clear_data (unpaid TTL)------------#
if (function_exists('cleanupExpiredUnpaidInvoices') && intval($from_id) != 0) {
    cleanupExpiredUnpaidInvoices($from_id);
}
#-----------/start------------#
if ($text == "/start") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    sendmessage($from_id, $datatextbot['text_start'], $keyboard, 'html');
    step('home', $from_id);
    return;
}
#-----------/new (buy service)------------#
if ($text == "/new") {
    $__st_buy_new = select("setting", "*", null, null, "select");
    $__buy_flag_new = '1';
    if (is_array($__st_buy_new) && array_key_exists('status_buy', $__st_buy_new) && $__st_buy_new['status_buy'] !== null && $__st_buy_new['status_buy'] !== '') {
        $__buy_flag_new = strval($__st_buy_new['status_buy']);
    } elseif (isset($setting['status_buy']) && $setting['status_buy'] !== null && $setting['status_buy'] !== '') {
        $__buy_flag_new = strval($setting['status_buy']);
    }
    if ($__buy_flag_new === '0') {
        sendmessage($from_id, getEditableBotText('msg_buy_disabled', $textbotlang['users']['sell']['buy_disabled']), $keyboard, 'HTML');
        return;
    }
    $locationproduct = select("marzban_panel", "*", "status", "activepanel", "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($setting['get_number'] == "1" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
        return;
    }
    if ($user['number'] == "none" && $setting['get_number'] == "1")
        return;
    #-----------------------#
    if ($locationproduct == 1) {
        $panel = select("marzban_panel", "*", "status", "activepanel", "select");
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id, "select");
        if ($setting['statuscategory'] == "0") {
            $nullproduct = select("product", "*", null, null, "count");
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            $textproduct = sprintf($textbotlang['users']['buy']['selectService'], $panel['name_panel']);
            sendmessage($from_id, $textproduct, KeyboardProduct($panel['name_panel'], "backuser", $panel['MethodUsername']), 'HTML');
        } else {
            $emptycategory = select("category", "*", null, null, "count");
            if ($emptycategory == 0) {
                sendmessage($from_id, $textbotlang['users']['category']['NotFound'], null, 'HTML');
                return;
            }
            sendmessage($from_id, $textbotlang['users']['category']['selectCategory'], KeyboardCategorybuy("backuser", $panel['name_panel']), 'HTML');
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['Service']['Location'], $list_marzban_panel_user, 'HTML');
    }
    return;
}
#-----------/status (my packages)------------#
if ($text == "/status") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0 && $setting['NotUser'] == "offnotuser") {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
        return;
    }
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "🌟" . $row['username'] . "🌟",
                'callback_data' => "product_" . $row['username']
            ],
        ];
    }
    if ($setting['NotUser'] == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => $textbotlang['Admin']['Status']['notusenameinbot'],
                'callback_data' => "notusernameget"
            ],
        ];
    }
    $total_items = select("invoice", "*", "id_user", $from_id, "count");
    $total_pages = ceil($total_items / $items_per_page);
    if ($page > 1) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['page']['previous'], 'callback_data' => 'prevpage_' . ($page - 1)]
        ];
    }
    if ($page < $total_pages) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['page']['next'], 'callback_data' => 'nextpage_' . ($page + 1)]
        ];
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"],
    ];
    $keyboardlistss = json_encode($keyboardlists);
    sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboardlistss, 'HTML');
    step('userservices', $from_id);
    return;
}
#-----------/renew (renew service)------------#
if ($text == "/renew") {
    if (function_exists('isExtendEnabled') && !isExtendEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_extend_disabled', $textbotlang['users']['extend']['disabled']), $keyboard, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
        return;
    }
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "💊 " . $row['username'],
                'callback_data' => "extend_" . $row['username']
            ],
        ];
    }
    $keyboardlists['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"],
    ];
    $keyboardlistss = json_encode($keyboardlists);
    sendmessage($from_id, $textbotlang['users']['extend']['selectservice'], $keyboardlistss, 'HTML');
    return;
}
#-----------back------------#
if ($text == $textbotlang['users']['backhome'] || $datain == "backuser") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    if ($datain == "backuser")
        deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    step('home', $from_id);
    return;
}
#-----------get_number------------#
if ($user['step'] == 'get_number') {
    if (empty($user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['false'], $request_contact, 'html');
        return;
    }
    if ($contact_id != $from_id) {
        sendmessage($from_id, $textbotlang['users']['number']['Warning'], $request_contact, 'html');
        return;
    }
    if ($setting['iran_number'] == "1" && !preg_match("/989[0-9]{9}$/", $user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['erroriran'], $request_contact, 'html');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['number']['active'], $keyboard, 'html');
    update("user", "number", $user_phone, "id", $from_id);
    step('home', $from_id);
}
#-----------Purchased services------------#
if ($text == $datatextbot['text_Purchased_services'] || $datain == "backorder" || $text == "/services") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0 && $setting['NotUser'] == "offnotuser") {
        sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], null, 'html');
        return;
    }
    update("user", "pagenumber", "1", "id", $from_id);
    $page = 1;
    $items_per_page = 10;
    $start_index = ($page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "🌟" . $row['username'] . "🌟",
                'callback_data' => "product_" . $row['username']
            ],
        ];
    }
    $usernotlist = [
        [
            'text' => $textbotlang['Admin']['Status']['notusenameinbot'],
            'callback_data' => 'usernotlist'
        ]
    ];
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    if (!isset($setting['status_search_service']) || $setting['status_search_service'] == '1' || $setting['status_search_service'] === 1) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '🔎  جستجوی سرویس  🔎', 'callback_data' => 'search_myservice']
        ];
    }
    if ($setting['NotUser'] == "1") {
        $keyboardlists['inline_keyboard'][] = $usernotlist;
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json, 'html');
    }
}
if ($datain == 'next_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 10;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "🌟️" . $row['username'] . "🌟️",
                'callback_data' => "product_" . $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $usernotlist = [
        [
            'text' => $textbotlang['Admin']['Status']['notusenameinbot'],
            'callback_data' => 'usernotlist'
        ]
    ];
    if (!isset($setting['status_search_service']) || $setting['status_search_service'] == '1' || $setting['status_search_service'] === 1) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '🔎  جستجوی سرویس  🔎', 'callback_data' => 'search_myservice']
        ];
    }
    if ($setting['NotUser'] == "1") {
        $keyboardlists['inline_keyboard'][] = $usernotlist;
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $text_callback, $keyboard_json);
} elseif ($datain == 'previous_page') {
    $page = $user['pagenumber'];
    $items_per_page = 10;
    if ($user['pagenumber'] <= 1) {
        $next_page = 1;
    } else {
        $next_page = $page - 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT $start_index, $items_per_page");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyboardlists['inline_keyboard'][] = [
            [
                'text' => "🌟️" . $row['username'] . "🌟️",
                'callback_data' => "product_" . $row['username']
            ],
        ];
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page'
        ]
    ];
    $usernotlist = [
        [
            'text' => $textbotlang['Admin']['Status']['notusenameinbot'],
            'callback_data' => 'usernotlist'
        ]
    ];
    if (!isset($setting['status_search_service']) || $setting['status_search_service'] == '1' || $setting['status_search_service'] === 1) {
        $keyboardlists['inline_keyboard'][] = [
            ['text' => '🔎  جستجوی سرویس  🔎', 'callback_data' => 'search_myservice']
        ];
    }
    if ($setting['NotUser'] == "1") {
        $keyboardlists['inline_keyboard'][] = $usernotlist;
    }
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $text_callback, $keyboard_json);
}
if ($datain == "usernotlist") {
    sendmessage($from_id, $textbotlang['users']['status']['SendUsername'], $backuser, 'html');
    step('getusernameinfo', $from_id);
}
#----------- Search My Service ------------#
if ($datain == "search_myservice") {
    if (isset($setting['status_search_service']) && ($setting['status_search_service'] == '0' || $setting['status_search_service'] === 0)) {
        sendmessage($from_id, $textbotlang['users']['search']['disabled'], $keyboard, 'HTML');
        return;
    }

    sendmessage($from_id, $textbotlang['users']['search']['prompt'], $backuser, 'HTML');
    step('search_myservice', $from_id);
}
if ($user['step'] == "search_myservice") {
    if ($text == $textbotlang['users']['backhome'] || $text == "/start") {
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
        return;
    }
    $username = trim($text);
    $username = ltrim($username, '@');
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND username = :username AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn') LIMIT 1");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND username LIKE :username AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn') ORDER BY time_sell DESC LIMIT 10");
        $like = '%' . $username . '%';
        $stmt->bindParam(':id_user', $from_id);
        $stmt->bindParam(':username', $like);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) == 0) {
            sendmessage($from_id, $textbotlang['users']['search']['notfound'], $backuser, 'HTML');
            return;
        }
        $keyboardlists = ['inline_keyboard' => []];
        foreach ($results as $row) {
            $keyboardlists['inline_keyboard'][] = [
                ['text' => "🌟" . $row['username'] . "🌟", 'callback_data' => "product_" . $row['username']]
            ];
        }
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['search']['again'], 'callback_data' => 'search_myservice']
        ];
        $keyboardlists['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder']
        ];
        step('home', $from_id);
        sendmessage($from_id, $textbotlang['users']['search']['multiple'], json_encode($keyboardlists), 'HTML');
        return;
    }
    step('home', $from_id);
    $keyboardfound = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "🌟" . $invoice['username'] . "🌟", 'callback_data' => "product_" . $invoice['username']]
            ],
            [
                ['text' => $textbotlang['users']['search']['again'], 'callback_data' => 'search_myservice']
            ],
            [
                ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder']
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['search']['found'], $keyboardfound, 'HTML');
}
if ($user['step'] == "getusernameinfo") {
    if (!preg_match('/^\w{3,32}$/', $text)) {
        sendmessage($from_id, $textbotlang['users']['status']['Invalidusername'], $backuser, 'html');
        return;
    }
    update("user", "Processing_value", $text, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Service']['Location'], $list_marzban_panel_user, 'html');
    step('getdata', $from_id);
} elseif (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) {
    $locationid = $dataget[1];
    $marzban_list_get = select("marzban_panel", "name_panel", "id", $locationid, "select");
    $location = $marzban_list_get['name_panel'];
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        if ($DataUserOut['msg'] == "User not found") {
            sendmessage($from_id, $textbotlang['users']['status']['notUsernameget'], $keyboard, 'html');
            step('home', $from_id);
            return;
        }
    }
    #-------------[ status ]----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['onhold']
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['status']['Unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['Unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['users']['unlimited'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['Notconsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) + 1 . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['Unlimited'];
    #-----------------------------#


    $keyboardinfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $DataUserOut['username'], 'callback_data' => "username"],
                ['text' => $textbotlang['users']['status']['username'], 'callback_data' => 'username'],
            ],
            [
                ['text' => $status_var, 'callback_data' => 'status_var'],
                ['text' => $textbotlang['users']['status']['status'], 'callback_data' => 'status_var'],
            ],
            [
                ['text' => $expirationDate, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['status']['expirationDate'], 'callback_data' => 'expirationDate'],
            ],
            [],
            [
                ['text' => $day, 'callback_data' => 'day'],
                ['text' => $textbotlang['users']['status']['daysleft'], 'callback_data' => 'day'],
            ],
            [
                ['text' => $LastTraffic, 'callback_data' => 'LastTraffic'],
                ['text' => $textbotlang['users']['status']['LastTraffic'], 'callback_data' => 'LastTraffic'],
            ],
            [
                ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['status']['usedTrafficGb'], 'callback_data' => 'expirationDate'],
            ],
            [
                ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume'],
                ['text' => $textbotlang['users']['status']['RemainingVolume'], 'callback_data' => 'RemainingVolume'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['status']['info'], $keyboardinfo, 'html');
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
    step('home', $from_id);
}
if (preg_match('/product_(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $username);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['status']['usernotfound'], $keyboard, 'html');
        update("invoice", "Status", "disabledn", "id_invoice", $nameloc['id_invoice']);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], $keyboard, 'html');
        return;
    }
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = $textbotlang['users']['online'];
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = $textbotlang['users']['offline'];
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateString = $DataUserOut['online_at'];
            $lastonline = jdate('Y/m/d h:i:s', strtotime($dateString));
        } else {
            $lastonline = $textbotlang['users']['status']['notconnected'];
        }
    }
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['onhold']
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['status']['Unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['Unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['users']['unlimited'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['Notconsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) + 1 . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['Unlimited'];
    #-----------------------------#
    if (!in_array($status, ['active', "on_hold"])) {
        $__kb_dis = [];
        if (!isset($setting['status_extend']) || $setting['status_extend'] == '1' || $setting['status_extend'] === 1) {
            $__kb_dis[] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username]];
        }
        $__row_tog = [['text' => $textbotlang['users']['togglestatus']['enable_btn'], 'callback_data' => 'toggleserv_' . $username]];
        if (!isset($setting['status_extra_volume']) || $setting['status_extra_volume'] == '1' || $setting['status_extra_volume'] === 1) {
            $__row_tog[] = ['text' => $textbotlang['users']['Extra_volume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username];
        }
        $__kb_dis[] = $__row_tog;
        $__kb_dis[] = [['text' => $textbotlang['users']['status']['RemoveSerivecbtn'], 'callback_data' => 'removebyuser-' . $username]];
        $__kb_dis[] = [['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder']];
        $keyboardsetting = json_encode(['inline_keyboard' => $__kb_dis]);
        $textinfo = sprintf($textbotlang['users']['status']['InfoSerivceDisable'], $status_var, $DataUserOut['username'], $nameloc['Service_location'], $nameloc['id_invoice'], $LastTraffic, $usedTrafficGb, $expirationDate, $day);
    } else {
        // ترتیب دکمه‌ها: لینک/کانفیگ | تمدید/تغییر لینک | غیرفعال/حجم اضافه | بازگشت وجه | بازگشت لیست
        $keyboarddate = array(
            'linksub' => array(
                'text' => $textbotlang['users']['status']['linksub'],
                'callback_data' => "subscriptionurl_"
            ),
            'config' => array(
                'text' => $textbotlang['users']['status']['config'],
                'callback_data' => "config_"
            ),
            'extend' => array(
                'text' => $textbotlang['users']['extend']['title'],
                'callback_data' => "extend_"
            ),
            'changelink' => array(
                'text' => $textbotlang['users']['changelink']['btntitle'],
                'callback_data' => "changelink_"
            ),
            'togglestatus' => array(
                'text' => ($status === 'disabled'
                    ? $textbotlang['users']['togglestatus']['enable_btn']
                    : $textbotlang['users']['togglestatus']['disable_btn']),
                'callback_data' => "toggleserv_"
            ),
            'Extra_volume' => array(
                'text' => $textbotlang['users']['Extra_volume']['sellextra'],
                'callback_data' => "Extra_volume_"
            ),
            'removeservice' => array(
                'text' => $textbotlang['users']['removeconfig']['btnremoveuser'],
                'callback_data' => "removeserviceuserco-"
            ),
        );
        if ($marzban_list_get['type'] == "wgdashboard") {
            unset($keyboarddate['config']);
            unset($keyboarddate['changelink']);
            unset($keyboarddate['togglestatus']);
        }
        if ($marzban_list_get['type'] == "mikrotik") {
            unset($keyboarddate['Extra_volume']);
            unset($keyboarddate['linksub']);
            unset($keyboarddate['config']);
            unset($keyboarddate['extend']);
            unset($keyboarddate['changelink']);
            unset($keyboarddate['togglestatus']);
            unset($keyboarddate['Extra_volume']);
        }
        if (isset($setting['status_extra_volume']) && ($setting['status_extra_volume'] == '0' || $setting['status_extra_volume'] === 0)) {
            unset($keyboarddate['Extra_volume']);
        }
        if (isset($setting['status_extend']) && ($setting['status_extend'] == '0' || $setting['status_extend'] === 0)) {
            unset($keyboarddate['extend']);
        }
        if ($nameloc['name_product'] == "usertest") {
            unset($keyboarddate['removeservice']);
        }
        $tempArray = [];
        $keyboardsetting = ['inline_keyboard' => []];
        foreach ($keyboarddate as $keyboardtext) {
            $tempArray[] = ['text' => $keyboardtext['text'], 'callback_data' => $keyboardtext['callback_data'] . $username];
            if (count($tempArray) == 2) {
                $keyboardsetting['inline_keyboard'][] = $tempArray;
                $tempArray = [];
            }
        }
        if (count($tempArray) > 0) {
            $keyboardsetting['inline_keyboard'][] = $tempArray;
        }
        $keyboardsetting['inline_keyboard'][] = [['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder']];
        $keyboardsetting = json_encode($keyboardsetting);
        if ($marzban_list_get['type'] == "mikrotik") {
            $textinfo = sprintf($textbotlang['users']['status']['InfoSerivceActive_mikrotik'], $status_var, $DataUserOut['username'], $DataUserOut['subscription_url'], $nameloc['Service_location'], $nameloc['id_invoice'], $LastTraffic, $usedTrafficGb, $expirationDate, $day);
        } else {
            $textinfo = sprintf($textbotlang['users']['status']['InfoSerivceActive'], $status_var, $DataUserOut['username'], $nameloc['Service_location'], $nameloc['id_invoice'], $lastonline, $LastTraffic, $usedTrafficGb, $expirationDate, $day);
        }
    }
    Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
}
if (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $username);
    $subscriptionurl = $DataUserOut['subscription_url'];
    if ($marzban_list_get['type'] == "wgdashboard") {
        $textsub = "";
    } else {
        $textsub = "<code>$subscriptionurl</code>";
    }
    $randomString = bin2hex(random_bytes(2));
    $urlimage = "$from_id$randomString.png";
    $writer = new PngWriter();
    $qrCode = QrCode::create($subscriptionurl)
        ->setEncoding(new Encoding('UTF-8'))
        ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
        ->setSize(400)
        ->setMargin(0)
        ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);
    $result = $writer->write($qrCode, null, null);
    $result->saveToFile($urlimage);
    telegram('sendphoto', [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => $textsub,
        'parse_mode' => "HTML",
    ]);
    if ($marzban_list_get['type'] == "wgdashboard") {
        $urldocs = "{$marzban_list_get['inboundid']}_{$nameloc['id_invoice']}.conf";
        file_put_contents($urldocs, $DataUserOut['subscription_url']);
        sendDocument($from_id, $urldocs, $textbotlang['users']['buy']['configwg']);
        unlink($urlimage);
    }
    unlink($urlimage);
} elseif (preg_match('/config_(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $username);
    foreach ($DataUserOut['links'] as $configs) {
        $randomString = bin2hex(random_bytes(2));
        $urlimage = "$from_id$randomString.png";
        $writer = new PngWriter();
        $qrCode = QrCode::create($configs)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->setSize(400)
            ->setMargin(0)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);
        $result = $writer->write($qrCode, null, null);
        $result->saveToFile($urlimage);
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'caption' => "<code>$configs</code>",
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
    }
} elseif (preg_match('/emergency_ext_(.+)/', strval($datain), $emg)) {
    $username_emg = $emg[1];
    if (!smartCronFlag('smart_emergency', '0')) {
        sendmessage($from_id, $textbotlang['users']['cron']['emergency_off'], $keyboard, 'HTML');
        return;
    }
    $inv = null;
    $stmt_e = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :u AND username = :un AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn') LIMIT 1");
    $stmt_e->execute([':u' => $from_id, ':un' => $username_emg]);
    $inv = $stmt_e->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        sendmessage($from_id, $textbotlang['users']['cron']['emergency_fail'], $keyboard, 'HTML');
        return;
    }
    $state = getSmartCronState($username_emg, $inv['Service_location']);
    if (intval($state['emergency_used']) === 1) {
        sendmessage($from_id, $textbotlang['users']['cron']['emergency_used'], $keyboard, 'HTML');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($inv['Service_location'], $username_emg);
    if (!is_array($DataUserOut) || ($DataUserOut['status'] ?? '') === 'Unsuccessful') {
        // حتی اگر منقضی باشد بعضی پنل‌ها داده می‌دهند؛ اگر کاملاً نیست خطا
        if (isPanelUserMissing($DataUserOut)) {
            sendmessage($from_id, $textbotlang['users']['cron']['emergency_fail'], $keyboard, 'HTML');
            return;
        }
    }
    $days = intval(getPaySettingValue('smart_emergency_days', '1'));
    $gb = floatval(getPaySettingValue('smart_emergency_gb', '1'));
    if ($days < 1) $days = 1;
    if ($gb <= 0) $gb = 1;
    $add_bytes = $gb * pow(1024, 3);
    $used = floatval($DataUserOut['used_traffic'] ?? 0);
    $old_limit = floatval($DataUserOut['data_limit'] ?? 0);
    $new_limit = max($old_limit, $used) + $add_bytes;
    $base_expire = intval($DataUserOut['expire'] ?? 0);
    if ($base_expire < time()) {
        $base_expire = time();
    }
    $new_expire = $base_expire + ($days * 86400);
    $config = [
        'expire' => $new_expire,
        'data_limit' => $new_limit,
        'status' => 'active',
    ];
    $ManagePanel->Modifyuser($username_emg, $inv['Service_location'], $config);
    updateSmartCronState($username_emg, $inv['Service_location'], 'emergency_used', 1);
    updateSmartCronState($username_emg, $inv['Service_location'], 'expired_notified', 0);
    updateSmartCronState($username_emg, $inv['Service_location'], 'warn_time_level', 0);
    updateSmartCronState($username_emg, $inv['Service_location'], 'warn_vol_level', 0);
    update("invoice", "status", "active", "username", $username_emg);
    sendmessage($from_id, sprintf($textbotlang['users']['cron']['emergency_ok'], $username_emg), $keyboard, 'HTML');
    $emg_log = "🆘 <b>تمدید اضطراری</b>\n\n"
        . "🔑 کانفیگ: <code>{$username_emg}</code>\n"
        . "📍 پنل: {$inv['Service_location']}\n\n"
        . "🆔 کاربر: <code>{$from_id}</code>\n"
        . "👤 @" . strval($user['username'] ?? '-');
    if (function_exists('logChannelReport')) {
        logChannelReport($emg_log);
    } elseif (function_exists('smartCronDebugLog')) {
        smartCronDebugLog($emg_log);
    } else {
        $setting_emg = select("setting", "*", null, null, "select");
        if (function_exists('sendChannelReport')) {
            sendChannelReport('rpt_emergency', $emg_log);
        } elseif ($setting_emg && !empty($setting_emg['Channel_Report'])) {
            sendmessage($setting_emg['Channel_Report'], $emg_log, null, 'HTML');
        }
    }
    step('home', $from_id);
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {
    if (function_exists('isExtendEnabled') && !isExtendEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_extend_disabled', $textbotlang['users']['extend']['disabled']), $keyboard, 'HTML');
        return;
    }
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $username);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $textbotlang['users']['status']['error_onhold'], null, 'html');
        return;
    }
    update("user", "Processing_value", $username, "id", $from_id);
    // آزاد کردن قفل تمدید گیرکرده از نسخه قبلی
    if (isset($user['Processing_value_tow']) && preg_match('/^[0-9]{9,}$/', strval($user['Processing_value_tow']))) {
        update("user", "Processing_value_tow", "0", "id", $from_id);
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :Location OR location = '/all')");
    $stmt->bindValue(':Location', $nameloc['Service_location']);
    $stmt->execute();
    $productextend = ['inline_keyboard' => []];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productextend['inline_keyboard'][] = [
            ['text' => $result['name_product'], 'callback_data' => "serviceextendselect_" . $result['code_product']]
        ];
    }
    $productextend['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['backorder'], 'callback_data' => "product_" . $username]
    ];

    $json_list_product_lists = json_encode($productextend);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], $json_list_product_lists);
} elseif (preg_match('/serviceextendselect_(\w+)/', $datain, $dataget)) {
    if (function_exists('isExtendEnabled') && !isExtendEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_extend_disabled', $textbotlang['users']['extend']['disabled']), $keyboard, 'HTML');
        return;
    }

    $codeproduct = $dataget[1];
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error2'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :Location OR location = '/all') AND code_product = :code_product LIMIT 1");
    $stmt->bindValue(':Location', $nameloc['Service_location']);
    $stmt->bindValue(':code_product', $codeproduct);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error2'], null, 'HTML');
        return;
    }
    update("invoice", "name_product", $product['name_product'], "username", $user['Processing_value']);
    update("invoice", "Service_time", $product['Service_time'], "username", $user['Processing_value']);
    update("invoice", "Volume", $product['Volume_constraint'], "username", $user['Processing_value']);
    update("invoice", "price_product", $product['price_product'], "username", $user['Processing_value']);
    update("user", "Processing_value_one", $codeproduct, "id", $from_id);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce-" . $codeproduct],
            ],
            [
                ['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"]

            ]
        ]
    ]);
    $textextend = sprintf($textbotlang['users']['extend']['invoicExtend'], $nameloc['username'], $product['name_product'], number_format(intval($product['price_product'])), $product['Service_time'], $product['Volume_constraint']);
    Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
} elseif (preg_match('/confirmserivce-(.*)/', $datain, $dataget)) {
    if (function_exists('isExtendEnabled') && !isExtendEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_extend_disabled', $textbotlang['users']['extend']['disabled']), $keyboard, 'HTML');
        return;
    }

    $codeproduct = $dataget[1];
    deletemessage($from_id, $message_id);
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error2'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error2'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :Location OR location = '/all') AND code_product = :code_product LIMIT 1");
    $stmt->bindValue(':Location', $nameloc['Service_location']);
    $stmt->bindValue(':code_product', $codeproduct);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error2'], null, 'HTML');
        return;
    }

    // پاک‌سازی قفل قدیمی نسخه قبل (اگر روی Processing_value_tow مانده)
    if (isset($user['Processing_value_tow']) && preg_match('/^[0-9]{9,}$/', strval($user['Processing_value_tow']))) {
        update("user", "Processing_value_tow", "0", "id", $from_id);
        $user['Processing_value_tow'] = '0';
    }

    // ضد دابل‌کلیک با قفل فایل (بدون تداخل با فیلدهای دیتابیس خرید/شارژ)
    $__lock_path = sys_get_temp_dir() . '/mirza_extend_' . intval($from_id) . '.lock';
    $__lock_fp = @fopen($__lock_path, 'c+');
    $__lock_ok = ($__lock_fp && @flock($__lock_fp, LOCK_EX | LOCK_NB));
    if (!$__lock_ok) {
        // اگر قفل گیر کرده و بیش از ۹۰ ثانیه مانده، احتمالاً پروسه قبلی کرش کرده
        $__stale = false;
        if (is_file($__lock_path) && (time() - intval(@filemtime($__lock_path))) > 90) {
            @unlink($__lock_path);
            $__stale = true;
        }
        if ($__lock_fp) {
            @fclose($__lock_fp);
        }
        if ($__stale) {
            $__lock_fp = @fopen($__lock_path, 'c+');
            $__lock_ok = ($__lock_fp && @flock($__lock_fp, LOCK_EX | LOCK_NB));
        }
        if (!$__lock_ok) {
            if ($__lock_fp) {
                @fclose($__lock_fp);
            }
            sendmessage($from_id, "⏳ درخواست قبلی در حال انجام است. چند لحظه صبر کنید.", $keyboard, 'HTML');
            return;
        }
    }
    @ftruncate($__lock_fp, 0);
    @fwrite($__lock_fp, strval(time()));
    @fflush($__lock_fp);

    $__price_ext = intval($product['price_product']);
    // موجودی را زنده از دیتابیس بخوان (نه از کش ابتدای ریکوئست)
    $__bal_row = select("user", "Balance", "id", $from_id, "select");
    $__bal_now = is_array($__bal_row) ? intval($__bal_row['Balance'] ?? 0) : intval($__bal_row);

    if ($__bal_now < $__price_ext) {
        @flock($__lock_fp, LOCK_UN);
        @fclose($__lock_fp);
        @unlink($__lock_path);
        if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
            sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $Balance_prim = $__price_ext - $__bal_now;
        if ($Balance_prim < getDepositLimits()['min']) {
            sendmessage($from_id, msgShortfallBelowMin('extend'), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        update("user", "Processing_value", $Balance_prim, "id", $from_id);
        sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
        step('get_step_payment', $from_id);
        return;
    }

    // کسر اتمیک موجودی — اگر همزمان دو درخواست بیاید فقط یکی موفق می‌شود
    $Balance_Low_user = null;
    try {
        $__ded = $pdo->prepare("UPDATE user SET Balance = Balance - :p WHERE id = :id AND CAST(Balance AS SIGNED) >= :p2");
        $__ded->execute([':p' => $__price_ext, ':id' => $from_id, ':p2' => $__price_ext]);
        if ($__ded->rowCount() < 1) {
            @flock($__lock_fp, LOCK_UN);
            @fclose($__lock_fp);
            @unlink($__lock_path);
            sendmessage($from_id, "⏳ امکان انجام همزمان نیست یا موجودی کافی نیست.", $keyboard, 'HTML');
            return;
        }
        $__bal_after = select("user", "Balance", "id", $from_id, "select");
        $Balance_Low_user = is_array($__bal_after) ? intval($__bal_after['Balance'] ?? 0) : intval($__bal_after);
    } catch (Throwable $e) {
        // fallback غیراتمیک (نباید در حالت عادی برسد)
        $Balance_Low_user = $__bal_now - $__price_ext;
        update("user", "Balance", $Balance_Low_user, "id", $from_id);
    }

    $usernamepanel = $nameloc['username'];
    if (function_exists('logWalletTx')) {
        logWalletTx($from_id, 'renew', $__price_ext, $Balance_Low_user, 'تمدید سرویس: ' . ($usernamepanel ?? ($nameloc['username'] ?? '')));
    }
    if (function_exists('recordSale')) {
        recordSale($from_id, $__price_ext, 'renew', $usernamepanel ?? ($nameloc['username'] ?? null), $nameloc['id_invoice'] ?? null);
    }
    $ManagePanel->ResetUserDataUsage($nameloc['Service_location'], $user['Processing_value']);
    if ($marzban_list_get['type'] == "marzban") {
        if (intval($product['Service_time']) == 0) {
            $newDate = 0;
        } else {
            $date = strtotime("+" . $product['Service_time'] . "day");
            $newDate = strtotime(date("Y-m-d H:i:s", $date));
        }
        $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
        $datam = array(
            "expire" => $newDate,
            "data_limit" => $data_limit
        );
        $ManagePanel->Modifyuser($user['Processing_value'], $nameloc['Service_location'], $datam);
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        if (intval($product['Service_time']) == 0) {
            $newDate = 0;
        } else {
            $date = strtotime("+" . $product['Service_time'] . "day");
            $newDate = strtotime(date("Y-m-d H:i:s", $date));
        }
        $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
        $datam = array(
            "expire_date" => $newDate,
            "data_limit" => $data_limit
        );
        $ManagePanel->Modifyuser($user['Processing_value'], $nameloc['Service_location'], $datam);
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $date = strtotime("+" . $product['Service_time'] . "day");
        $newDate = strtotime(date("Y-m-d H:i:s", $date)) * 1000;
        $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
        $config = array(
            'settings' => json_encode(
                array(
                    'clients' => array(
                        array(
                            "totalGB" => $data_limit,
                            "expiryTime" => $newDate,
                            "enable" => true,
                        )
                    ),
                )
            ),
        );
        $ManagePanel->Modifyuser($user['Processing_value'], $nameloc['Service_location'], $config);
    } elseif ($marzban_list_get['type'] == "alireza") {
        $date = strtotime("+" . $product['Service_time'] . "day");
        $newDate = strtotime(date("Y-m-d H:i:s", $date)) * 1000;
        $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
        $config = array(
            'id' => intval($marzban_list_get['inboundid']),
            'settings' => json_encode(
                array(
                    'clients' => array(
                        array(
                            "totalGB" => $data_limit,
                            "expiryTime" => $newDate,
                            "enable" => true,
                        )
                    ),
                )
            ),
        );
        $ManagePanel->Modifyuser($user['Processing_value'], $nameloc['Service_location'], $config);
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $date = strtotime("+" . $product['Service_time'] . "day");
        $newDate = strtotime(date("Y-m-d H:i:s", $date));
        $data_limit = intval($product['Volume_constraint']) * pow(1024, 3);
        $config = array(
            "volume" => $data_limit,
            "expiry" => $newDate,
            "enable" => true,
        );
        $ManagePanel->Modifyuser($user['Processing_value'], $nameloc['Service_location'], $config);
    } elseif ($marzban_list_get['type'] == "wgdashboard") {
        $usernamepanel = $nameloc['username'];
        $namepanel = $nameloc['Service_location'];
        allowAccessPeers($namepanel, $usernamepanel);
        $datauser = get_userwg($usernamepanel, $namepanel);
        $count = 0;
        foreach ($datauser['jobs'] as $jobsvolume) {
            if ($jobsvolume['Field'] == "date") {
                break;
            }
            $count += 1;
        }
        $datam = array(
            "Job" => $datauser['jobs'][$count],
        );
        deletejob($namepanel, $datam);
        $count = 0;
        foreach ($datauser['jobs'] as $jobsvolume) {
            if ($jobsvolume['Field'] == "total_data") {
                break;
            }
            $count += 1;
        }
        $datam = array(
            "Job" => $datauser['jobs'][$count],
        );
        deletejob($namepanel, $datam);

        if (intval($product['Service_time']) == 0) {
            $newDate = 0;
        } else {
            $date = strtotime("+" . $product['Service_time'] . "day");
            $newDate = strtotime(date("Y-m-d H:i:s", $date));
        }
        if ($newDate != 0) {
            $newDate = date("Y-m-d H:i:s", $newDate);
            setjob($namepanel, "date", $newDate, $datauser['id']);
        }
        setjob($namepanel, "total_data", $product['Volume_constraint'], $datauser['id']);
    }
    $keyboardextendfnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => "backorder"],
            ],
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $usernamepanel],
            ]
        ]
    ]);
    $priceproductformat = number_format($product['price_product']);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance']);
    update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
    if (function_exists('resetSmartCronWarnings')) {
        resetSmartCronWarnings($nameloc['username'], $nameloc['Service_location']);
    }
    if (isset($__lock_fp) && is_resource($__lock_fp)) {
        @flock($__lock_fp, LOCK_UN);
        @fclose($__lock_fp);
    }
    if (!empty($__lock_path) && is_file($__lock_path)) {
        @unlink($__lock_path);
    }
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $keyboardextendfnished, 'HTML');
    // recordSale تمدید قبلاً در ابتدای مسیر ثبت شده — از ثبت تکراری جلوگیری می‌شود

    $tg_name = $user['username'] ?? ($username ?? '');
    $text_report = sprintf($textbotlang['Admin']['Report']['extend'], $from_id, $tg_name, $nameloc['username'], $product['name_product'], $priceproductformat, $nameloc['Service_location'], $balanceformatsell);
    if (function_exists('sendChannelReport')) { sendChannelReport('rpt_extend', $text_report); }
    elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $keyboardchange = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['changelink']['confirm'], 'callback_data' => "confirmchange_" . $username],
            ],
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $username],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['warnchange'], $keyboardchange);
} elseif (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) {
    $usernameconfig = $dataget[1];
    $nameloc = select("invoice", "*", "username", $usernameconfig, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->Revoke_sub($marzban_list_get['name_panel'], $usernameconfig);
    $keyboardchange = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $usernameconfig],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['changelink']['confirmed'], $keyboardchange);
} elseif (preg_match('/toggleserv_(\w+)/', $datain, $dataget)) {
    $usernameconfig = $dataget[1];
    $nameloc = select("invoice", "*", "username", $usernameconfig, "select");
    if ($nameloc == false || intval($nameloc['id_user']) !== intval($from_id)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['notowner'],
            'show_alert' => true,
        ]);
        return;
    }
    $invStatus = isset($nameloc['Status']) ? $nameloc['Status'] : (isset($nameloc['status']) ? $nameloc['status'] : '');
    if (!in_array($invStatus, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['notallowed'],
            'show_alert' => true,
        ]);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get == false || in_array($marzban_list_get['type'], ['mikrotik', 'wgdashboard'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['notallowed'],
            'show_alert' => true,
        ]);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernameconfig);
    if (!is_array($DataUserOut) || ($DataUserOut['status'] ?? '') === 'Unsuccessful') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['error'],
            'show_alert' => true,
        ]);
        return;
    }
    $cur = $DataUserOut['status'] ?? 'active';
    if (in_array($cur, ['expired', 'limtied', 'limited'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['notallowed'],
            'show_alert' => true,
        ]);
        return;
    }
    $ptype = $marzban_list_get['type'];
    $enabled_now = false;
    if (in_array($ptype, ['marzban', 'marzneshin'])) {
        $newstatus = ($cur === 'disabled') ? 'active' : 'disabled';
        $ManagePanel->Modifyuser($usernameconfig, $marzban_list_get['name_panel'], ['status' => $newstatus]);
        $enabled_now = ($newstatus === 'active');
    } elseif (in_array($ptype, ['x-ui_single', 'alireza'])) {
        $new_enable = ($cur === 'disabled') ? true : false;
        $config = [
            'settings' => json_encode([
                'clients' => [[
                    'enable' => $new_enable,
                ]],
            ]),
        ];
        $ManagePanel->Modifyuser($usernameconfig, $marzban_list_get['name_panel'], $config);
        $enabled_now = $new_enable;
    } elseif ($ptype == 's_ui') {
        $new_enable = ($cur === 'disabled') ? true : false;
        $ManagePanel->Modifyuser($usernameconfig, $marzban_list_get['name_panel'], ['enable' => $new_enable]);
        $enabled_now = $new_enable;
    } else {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['togglestatus']['notallowed'],
            'show_alert' => true,
        ]);
        return;
    }
    $msg = $enabled_now
        ? $textbotlang['users']['togglestatus']['enabled_ok']
        : $textbotlang['users']['togglestatus']['disabled_ok'];
    $keyboardchange = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $usernameconfig],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $msg, $keyboardchange);
} elseif (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) {
    if (function_exists('isExtraVolumeEnabled') && !isExtraVolumeEnabled()) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['disabled'], $keyboard, 'HTML');
        return;
    }

    $username = $dataget[1];
    update("user", "Processing_value", $username, "id", $from_id);
    $textextra = " .";
    sendmessage($from_id, sprintf($textbotlang['users']['Extra_volume']['VolumeValue'], $setting['Extra_volume']), $backuser, 'HTML');
    step('getvolumeextra', $from_id);
} elseif ($user['step'] == "getvolumeextra") {
    if (!ctype_digit($text)) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
        return;
    }
    if ($text < 1) {
        sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
        return;
    }
    $priceextra = $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextra_' . $text],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra * $setting['Extra_volume']);
    $setting['Extra_volume'] = number_format($setting['Extra_volume']);
    $textextra = sprintf($textbotlang['users']['Extra_volume']['invoiceExtraVolume'], $setting['Extra_volume'], $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextra_(\w+)/', $datain, $dataget)) {
    $volume = $dataget[1];
    $price_extra = intval($setting['Extra_volume']) * intval($volume);
    Editmessagetext($from_id, $message_id, $text_callback, json_encode(['inline_keyboard' => []]));
    $nameloc = select("invoice", "*", "username", $user['Processing_value'], "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }

    $__extra_lock = null;
    $Balance_Low_user = null;
    if (intval($setting['Extra_volume']) != 0 && $price_extra > 0) {
        $__extra_lock = function_exists('paymentAcquireLock') ? paymentAcquireLock($from_id, 'extra') : true;
        if ($__extra_lock === null) {
            sendmessage($from_id, "⏳ درخواست قبلی در حال انجام است. چند لحظه صبر کنید.", $keyboard, 'HTML');
            return;
        }
        $__bal_row = select("user", "Balance", "id", $from_id, "select");
        $__bal_now = is_array($__bal_row) ? intval($__bal_row['Balance'] ?? 0) : intval($user['Balance'] ?? 0);
        if ($__bal_now < $price_extra) {
            if (function_exists('paymentReleaseLock') && is_array($__extra_lock)) {
                paymentReleaseLock($__extra_lock);
            }
            if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
                sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            $Balance_prim = $price_extra - $__bal_now;
            if ($Balance_prim < getDepositLimits()['min']) {
                sendmessage($from_id, msgShortfallBelowMin('extra'), $keyboard, 'HTML');
                step('home', $from_id);
                return;
            }
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
        $Balance_Low_user = function_exists('atomicDeductBalance')
            ? atomicDeductBalance($from_id, $price_extra)
            : false;
        if ($Balance_Low_user === false) {
            if (function_exists('paymentReleaseLock') && is_array($__extra_lock)) {
                paymentReleaseLock($__extra_lock);
            }
            sendmessage($from_id, "⏳ امکان انجام همزمان نیست یا موجودی کافی نیست.", $keyboard, 'HTML');
            return;
        }
        if (function_exists('logWalletTx')) {
            logWalletTx($from_id, 'extra_volume', intval($price_extra), $Balance_Low_user, 'خرید حجم اضافه: ' . ($nameloc['username'] ?? ''));
        }
        if (function_exists('recordSale')) {
            recordSale($from_id, $price_extra, 'extra_volume', $nameloc['username'] ?? ($user['Processing_value'] ?? null), $nameloc['id_invoice'] ?? null);
        }
    }
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + ($volume * pow(1024, 3));
    if ($marzban_list_get['type'] == "marzban") {
        $datam = array(
            "data_limit" => $data_limit
        );
    } elseif ($marzban_list_get['type'] == "marzneshin") {
        $datam = array(
            "data_limit" => $data_limit
        );
    } elseif ($marzban_list_get['type'] == "x-ui_single") {
        $datam = array(
            'settings' => json_encode(
                array(
                    'clients' => array(
                        array(
                            "totalGB" => $data_limit,
                        )
                    ),
                )
            ),
        );
    } elseif ($marzban_list_get['type'] == "alireza") {
        $datam = array(
            'id' => intval($marzban_list_get['inboundid']),
            'settings' => json_encode(
                array(
                    'clients' => array(
                        array(
                            "totalGB" => $data_limit,
                        )
                    ),
                )
            ),
        );
    } elseif ($marzban_list_get['type'] == "s_ui") {
        $datam = array(
            "volume" => $data_limit,
        );
    } elseif ($marzban_list_get['type'] == "wgdashboard") {
        $data_limit = ($DataUserOut['data_limit'] / pow(1024, 3)) + ($volume / $setting['Extra_volume']);
        $datauser = get_userwg($nameloc['username'], $nameloc['Service_location']);
        $count = 0;
        foreach ($datauser['jobs'] as $jobsvolume) {
            if ($jobsvolume['Field'] == "total_data") {
                break;
            }
            $count += 1;
        }
        allowAccessPeers($nameloc['Service_location'], $nameloc['username']);
        if (isset($datauser['jobs'][$count])) {
            $datam = array(
                "Job" => $datauser['jobs'][$count],
            );
            deletejob($nameloc['Service_location'], $datam);
        } else {
            ResetUserDataUsagewg($datauser['id'], $nameloc['Service_location']);
        }
        setjob($nameloc['Service_location'], "total_data", $data_limit, $datauser['id']);
    }
    $ManagePanel->Modifyuser($nameloc['username'], $marzban_list_get['name_panel'], $datam);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    if (function_exists('resetSmartCronWarnings')) {
        // فقط سطح حجم را صفر می‌کنیم؛ زمان را دست نمی‌زنیم — یا همه هشدارها
        resetSmartCronWarnings($nameloc['username'], $nameloc['Service_location']);
    }
    if (function_exists('paymentReleaseLock') && isset($__extra_lock) && is_array($__extra_lock)) {
        paymentReleaseLock($__extra_lock);
    }
    sendmessage($from_id, $textbotlang['users']['Extra_volume']['extraadded'], $keyboardextrafnished, 'HTML');
    $volumes = $volume;
    $price_extra_fmt = number_format($price_extra);
    $bal_after = select("user", "Balance", "id", $from_id, "select");
    $bal_after = is_array($bal_after) ? ($bal_after['Balance'] ?? $bal_after) : $bal_after;
    $tg_user = $username ?? ($user['username'] ?? '');
    // recordSale حجم اضافه قبلاً ثبت شده — جلوگیری از ردیف تکراری در sales_ledger
    $text_report = sprintf(
        $textbotlang['Admin']['Report']['Extra_volume'],
        $from_id,
        $nameloc['username'],
        $volumes,
        $price_extra_fmt,
        $nameloc['Service_location'],
        $tg_user,
        number_format(intval($bal_after))
    );
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_extra_volume', $text_report);
    } elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
} elseif (preg_match('/removeserviceuserco-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['status']['notusername'], null, 'html');
        return;
    }
    $requestcheck = select("cancel_service", "*", "username", $username, "count");
    if ($requestcheck != 0) {
        sendmessage($from_id, $textbotlang['users']['status']['errorexits'], null, 'html');
        return;
    }
    $confirmremove = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['RequestRemove'], 'callback_data' => "confirmremoveservices-$username"],
            ],
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['descriptions_removeservice'], $confirmremove);
} elseif (preg_match('/removebyuser-(\w+)/', $datain, $dataget)) {
    $username = $dataget[1];
    $nameloc = select("invoice", "*", "username", $username, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    update('invoice', 'status', 'removebyuser', 'id_invoice', $nameloc['id_invoice']);
    $tetremove = sprintf(
        $textbotlang['Admin']['Report']['NotifRemoveByUser'],
        $nameloc['username'],
        $nameloc['id_user'],
        $nameloc['Service_location']
    );
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_remove_user', $tetremove);
    } elseif (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'text' => $tetremove,
            'parse_mode' => "HTML"
        ]);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['status']['RemovedService'], null, 'html');
} elseif (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) {
    $checkcancelservice = mysqli_query($connect, "SELECT * FROM cancel_service WHERE id_user = '$from_id' AND status = 'waiting'");
    if (mysqli_num_rows($checkcancelservice) != 0) {
        sendmessage($from_id, $textbotlang['users']['status']['exitsrequsts'], null, 'HTML');
        return;
    }
    $usernamepanel = $dataget[1];
    $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $stmt = $connect->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
    $descriptions = "0";
    $Status = "waiting";
    $stmt->bind_param("ssss", $from_id, $usernamepanel, $descriptions, $Status);
    $stmt->execute();
    $stmt->close();
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamepanel);
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['onhold']
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? jdate('Y/m/d', $DataUserOut['expire']) : $textbotlang['users']['status']['Unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['Unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['users']['unlimited'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['Notconsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['Unlimited'];
    #-----------------------------#
    $textinfoadmin = sprintf($textbotlang['users']['status']['RequestInfoRemove'], $from_id, $username, $nameloc['username'], $status_var, $nameloc['Service_location'], $nameloc['id_invoice'], $usedTrafficGb, $LastTraffic, $RemainingVolume, $expirationDate, $day);
    $confirmremoveadmin = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['removeconfig']['btnremoveuser'], 'callback_data' => "remoceserviceadmin-$usernamepanel"],
                ['text' => $textbotlang['users']['removeconfig']['rejectremove'], 'callback_data' => "rejectremoceserviceadmin-$usernamepanel"],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
        step('home', $admin);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['removeconfig']['accepetrequest'], $keyboard, 'html');
}
#-----------usertest------------#
if ($text == $datatextbot['text_usertest']) {
    // وضعیت تست را زنده از دیتابیس بخوان (برای همه، حتی ادمین — هم‌راستا با خرید)
    $__st_test = select("setting", "*", null, null, "select");
    $__test_flag = '1';
    if (is_array($__st_test) && array_key_exists('status_usertest', $__st_test) && $__st_test['status_usertest'] !== null && $__st_test['status_usertest'] !== '') {
        $__test_flag = strval($__st_test['status_usertest']);
    } elseif (isset($setting['status_usertest']) && $setting['status_usertest'] !== null && $setting['status_usertest'] !== '') {
        $__test_flag = strval($setting['status_usertest']);
    }
    if ($__test_flag === '0') {
        sendmessage($from_id, $textbotlang['users']['usertest']['disabled'], $keyboard, 'HTML');
        return;
    }

    $locationproduct = select("marzban_panel", "*", null, null, "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($setting['get_number'] == "1" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "1")
        return;
    if ($user['limit_usertest'] <= 0) {
        sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard, 'html');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['Service']['Location'], $list_marzban_usertest, 'html');
}
if ($user['step'] == "createusertest" || preg_match('/locationtests_(.*)/', $datain, $dataget)) {
    if ($user['limit_usertest'] <= 0) {
        sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard, 'html');
        return;
    }
    if ($user['step'] == "createusertest") {
        $name_panel = $user['Processing_value_one'];
        // دکمه خودکار انتخاب کن نباید با اعتبارسنجی نام کاربری رد شود
        $is_auto_username = ($text === '🎲 خودکار انتخاب کن' || $text === 'خودکار انتخاب کن');
        if (!$is_auto_username && !preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $keyboard_getusername, 'HTML');
            return;
        }
    } else {
        deletemessage($from_id, $message_id);
        $id_panel = $dataget[1];
        $marzban_list_get = select("marzban_panel", "*", "id", $id_panel, "select");
        $name_panel = $marzban_list_get['name_panel'];
    }
    $randomString = bin2hex(random_bytes(2));
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $name_panel, "select");

    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername']) {
        if ($user['step'] != "createusertest") {
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $name_panel, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['selectusername'], $keyboard_getusername, 'html');
            return;
        }
    }
        if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] && ($text === '🎲 خودکار انتخاب کن' || $text === 'خودکار انتخاب کن')) {
        $text = generateAvailableUsername($marzban_list_get['name_panel']);
    }
    $username_ac = strtolower(generateUsername($from_id, $marzban_list_get['MethodUsername'], $user['username'], $randomString, $text));
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $random_number = random_int(1000000, 9999999);
        $username_ac = $username_ac . $random_number;
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", strtotime("+" . $setting['time_usertest'] . "hours"))),
        'data_limit' => $setting['val_usertest'] * 1048576,
    );
    $dataoutput = createUserWithRetry($name_panel, $username_ac, $datac, true);
    if (!empty($dataoutput['username_final'])) {
        $username_ac = $dataoutput['username_final'];
    }
    if ($dataoutput['username'] == null) {
        $dataoutput['msg'] = json_encode($dataoutput['msg']);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $texterros = sprintf($textbotlang['users']['buy']['errorInCreate'], $dataoutput['msg'], $from_id, $username);
        foreach ($admin_ids as $admin) {
            sendmessage($admin, $texterros, null, 'html');
        }
        if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['create_error'])) {
            $ch_err = sprintf(
                $textbotlang['Admin']['Report']['create_error'],
                $from_id,
                $username ?? '-',
                $username_ac ?? '-',
                $name_panel ?? ($marzban_list_get['name_panel'] ?? '-'),
                is_string($dataoutput['msg']) ? $dataoutput['msg'] : json_encode($dataoutput['msg']),
                'بدون کسر موجودی (تست)',
                'اکانت تست'
            );
            sendChannelReport('rpt_create_error', $ch_err);
        }
        step('home', $from_id);
        return;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $sql = "INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $Status = "active";
    $usertest = "usertest";
    $price = "0";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $from_id);
    $stmt->bindParam(2, $randomString);
    $stmt->bindParam(3, $username_ac, PDO::PARAM_STR);
    $stmt->bindParam(4, $date);
    $stmt->bindParam(5, $name_panel, PDO::PARAM_STR);
    $stmt->bindParam(6, $usertest, PDO::PARAM_STR);
    $stmt->bindParam(7, $price);
    $stmt->bindParam(8, $setting['val_usertest']);
    $stmt->bindParam(9, $setting['time_usertest']);
    $stmt->bindParam(10, $Status);
    $stmt->execute();
    $config = "";
    $text_config = "";
    $output_config_link = "";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get['configManual'] == "onconfig") {
        if (is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $configs) {
                $config .= "\n" . $configs;
            }
        }
        $text_config = $config;
    }
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    if ($marzban_list_get['type'] == "wgdashboard") {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservicewg'], $username_ac, $marzban_list_get['name_panel'], $setting['time_usertest'], $setting['val_usertest']);
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservice_mikrotik_test'], $username_ac, $dataoutput['subscription_url'], $marzban_list_get['name_panel'], $setting['time_usertest'], $setting['val_usertest']);
    } else {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservicetest'], $username_ac, $marzban_list_get['name_panel'], $setting['time_usertest'], $setting['val_usertest'], $output_config_link, $text_config);
    }
    if ($marzban_list_get['sublink'] == "onsublink" && $marzban_list_get['type'] != "mikrotik") {
        $urlimage = "$from_id$randomString.png";
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
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'reply_markup' => $Shoppinginfo,
            'caption' => $textcreatuser,
            'parse_mode' => "HTML",
        ]);
        if ($marzban_list_get['type'] == "wgdashboard") {
            $urldocs = "{$marzban_list_get['inboundid']}_{$randomString}.conf";
            file_put_contents($urldocs, $output_config_link);
            sendDocument($from_id, $urldocs, $textbotlang['users']['buy']['configwg']);
            unlink($urlimage);
        }
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
        unlink($urlimage);
    } else {
        sendmessage($from_id, $textcreatuser, $Shoppinginfo, 'HTML');
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    }
    step('home', $from_id);
    $limit_usertest = $user['limit_usertest'] - 1;
    update("user", "limit_usertest", $limit_usertest, "id", $from_id);
    step('home', $from_id);
    $text_report = sprintf($textbotlang['Admin']['Report']['ReportTestCreate'], $from_id, $username, $username_ac, $first_name, $marzban_list_get['name_panel'], $user['number']);
    if (function_exists('sendChannelReport')) { sendChannelReport('rpt_test', $text_report); }
    elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
}
#-----------help------------#
if ($text == $datatextbot['text_help'] || $datain == "helpbtn" || $text == "/help") {
    if ($setting['help_Status'] == "0") {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
    step('sendhelp', $from_id);
} elseif ($user['step'] == "sendhelp") {
    $helpdata = select("help", "*", "name_os", $text, "select");
    if (strlen($helpdata['Media_os']) != 0) {
        if ($helpdata['type_Media_os'] == "video") {
            sendvideo($from_id, $helpdata['Media_os'], $helpdata['Description_os']);
        } elseif ($helpdata['type_Media_os'] == "photo")
            sendphoto($from_id, $helpdata['Media_os'], $helpdata['Description_os']);
    } else {
        sendmessage($from_id, $helpdata['Description_os'], $json_list_help, 'HTML');
    }
}

#-----------support------------#
if ($text == $datatextbot['text_support'] || $text == "/support" || $datain == "support") {
    if (function_exists('isSupportEnabled') && !isSupportEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_support_disabled', $textbotlang['users']['support']['disabled']), $keyboard, 'HTML');
        return;
    }
    // مستقیم درخواست پیام پشتیبانی — بدون منوی وسط سوالات متداول
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageuser'], $backuser, 'HTML');
    step('gettextpm', $from_id);
} elseif ($user['step'] == 'gettextpm') {
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    if (function_exists('ensureSupportPendingTable')) {
        ensureSupportPendingTable();
        global $pdo;
        try {
            $st = $pdo->prepare("INSERT INTO support_pending (id_user, username, message_text, created_at, status) VALUES (?,?,?,?, 'waiting')");
            $st->execute([$from_id, $username ?? '', isset($text) ? $text : ($caption ?? ''), time()]);
        } catch (Exception $e) {}
    }

    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
            [
                ['text' => '👤 اطلاعات کاربر', 'callback_data' => 'userinfo_pay_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        if ($text) {
            $textsendadmin = sprintf($textbotlang['users']['support']['GetMessageOfUser'], $from_id, $username, $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['users']['support']['GetMessageOfUser'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
}
#-----------fq------------#
if ($datain == "fqQuestions") {
    sendmessage($from_id, $datatextbot['text_dec_fq'], null, 'HTML');
}
if ($text == $datatextbot['text_account'] || (isset($setting['show_balance']) && ($setting['show_balance']=='1'||$setting['show_balance']===1) && strpos(strval($text), strval($datatextbot['text_account'])) === 0)) {
    $dateacc = jdate('Y/m/d');
    $timeacc = jdate('H:i:s');
    $countorder = select("invoice", "*", "id_user", $from_id, "count");
    $aff_earn = number_format(function_exists('getAffiliatesEarned') ? getAffiliatesEarned($from_id) : 0);
    $Balanceuser = number_format($user['Balance'], 0);
    $text_account = sprintf($textbotlang['users']['account'], $first_name, $from_id, $Balanceuser, $countorder, $user['affiliatescount'], $aff_earn, $dateacc, $timeacc);
    sendmessage($from_id, $text_account, $keyboardPanel, 'HTML');
}

#----------------[ user transaction history ]------------------#
if ($datain == "user_tx_history") {
    global $pdo;
    if (function_exists('ensureWalletLog')) {
        ensureWalletLog();
    }
    if (function_exists('ensureSalesLedger')) {
        ensureSalesLedger();
    }
    $uid = strval($from_id);
    $uid_i = intval($from_id);
    $items = [];
    $fps = []; // fingerprint برای جلوگیری از تکرار

    $addItem = function ($ts, $text, $fp) use (&$items, &$fps) {
        $fp = mb_strtolower(trim(strval($fp)));
        if ($fp !== '' && isset($fps[$fp])) {
            return;
        }
        if ($fp !== '') {
            $fps[$fp] = true;
        }
        $items[] = ['ts' => intval($ts), 'text' => $text];
    };

    // 1) لاگ کیف پول — منبع اصلی
    try {
        $st = $pdo->prepare("SELECT * FROM wallet_log WHERE id_user = :u ORDER BY created_at DESC, id DESC LIMIT 30");
        $st->execute([':u' => $uid_i]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = strval($row['type'] ?? '');
            $amount = intval($row['amount'] ?? 0);
            $amt = number_format($amount);
            $bal = isset($row['balance_after']) && $row['balance_after'] !== null ? number_format(intval($row['balance_after'])) : '-';
            $tm = !empty($row['created_at']) ? date('Y/m/d H:i', intval($row['created_at'])) : '-';
            $detail = htmlspecialchars(strval($row['detail'] ?? ''), ENT_QUOTES, 'UTF-8');
            $map = [
                'deposit' => ['🟢', 'شارژ کیف پول', '+'],
                'admin_add' => ['🟢', 'افزایش موجودی توسط ادمین', '+'],
                'admin_low' => ['🔴', 'کاهش موجودی توسط ادمین', '-'],
                'buy' => ['🔴', 'خرید سرویس', '-'],
                'renew' => ['🔴', 'تمدید سرویس', '-'],
                'extra_volume' => ['🔴', 'خرید حجم اضافه', '-'],
                'refund' => ['🟢', 'بازگشت وجه', '+'],
                'affiliate' => ['🟢', 'پورسانت مشارکت در فروش', '+'],
            ];
            $icon = $map[$type][0] ?? '⚪';
            $title = $map[$type][1] ?? $type;
            $sign = $map[$type][2] ?? '';
            $text = "{$icon} <b>{$title}</b>\n💰 مبلغ: {$sign}{$amt} تومان\n💳 موجودی بعد: {$bal} تومان";
            if ($detail !== '') {
                $text .= "\n📝 {$detail}";
            }
            $text .= "\n🕐 {$tm}";
            // fingerprint: type + amount + username از detail
            $uname = '';
            if (preg_match('/user[a-z0-9_\-]+/i', strval($row['detail'] ?? ''), $m)) {
                $uname = $m[0];
            }
            $fp = $type . '|' . $amount . '|' . mb_strtolower($uname);
            $addItem(intval($row['created_at'] ?? 0), $text, $fp);
        }
    } catch (Throwable $e) {}

    // 2) واریزهای تاییدشده — فقط اگر در wallet_log نبود
    try {
        $st = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :u AND payment_Status = 'paid' ORDER BY id DESC LIMIT 30");
        $st->execute([':u' => $uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $credit = intval($p['price'] ?? 0);
            if (function_exists('getPaymentCreditAmount')) {
                $credit = intval(getPaymentCreditAmount($p));
            }
            $fp = 'deposit|' . $credit . '|';
            $method = $p['Payment_Method'] ?? '';
            $ml = (stripos(strval($method), 'crypto') !== false) ? 'کریپتو' : 'کارت‌به‌کارت';
            $tm_raw = strval($p['time'] ?? '');
            $text = "🟢 <b>شارژ کیف پول</b>\n💳 روش: {$ml}\n➕ اعتبار: " . number_format($credit) . " تومان\n💵 پرداختی: " . number_format(intval($p['price'] ?? 0)) . " تومان\n🕐 " . ($tm_raw !== '' ? $tm_raw : '-');
            $addItem(intval($p['id'] ?? 0), $text, $fp);
        }
    } catch (Throwable $e) {}

    // 3) sales_ledger — فقط اگر همان خرید در wallet_log نبود
    try {
        $st = $pdo->prepare("SELECT * FROM sales_ledger WHERE id_user = :u ORDER BY created_at DESC LIMIT 30");
        $st->execute([':u' => $uid_i]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $stype = strval($s['sale_type'] ?? 'buy');
            if ($stype === 'extend') {
                $stype = 'renew';
            } elseif ($stype === 'extra') {
                $stype = 'extra_volume';
            }
            if (!in_array($stype, ['buy', 'renew', 'extra_volume'], true)) {
                $stype = 'buy';
            }
            $amount = intval($s['price'] ?? 0);
            $un = strval($s['username'] ?? '');
            $fp = $stype . '|' . $amount . '|' . mb_strtolower($un);
            $titles = ['buy' => 'خرید سرویس', 'renew' => 'تمدید سرویس', 'extra_volume' => 'حجم اضافه'];
            $title = $titles[$stype] ?? 'خرید';
            $un_h = htmlspecialchars($un !== '' ? $un : '-', ENT_QUOTES, 'UTF-8');
            $tm = !empty($s['created_at']) ? date('Y/m/d H:i', intval($s['created_at'])) : '-';
            $text = "🔴 <b>{$title}</b>\n👤 سرویس: <code>{$un_h}</code>\n➖ مبلغ: " . number_format($amount) . " تومان\n🕐 {$tm}";
            $addItem(intval($s['created_at'] ?? 0), $text, $fp);
        }
    } catch (Throwable $e) {}

    // 4) فاکتور — فقط اگر خرید معادل در منابع بالا نبود
    try {
        $st = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :u AND (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn') AND CAST(price_product AS UNSIGNED) > 0 ORDER BY time_sell DESC LIMIT 30");
        $st->execute([':u' => $uid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            $amount = intval($inv['price_product'] ?? 0);
            $un = strval($inv['username'] ?? '');
            $fp = 'buy|' . $amount . '|' . mb_strtolower($un);
            // اگر تمدید در نام محصول باشد
            $np = strval($inv['name_product'] ?? '');
            if (mb_stripos($np, 'تمدید') !== false || mb_stripos($np, 'renew') !== false) {
                $fp = 'renew|' . $amount . '|' . mb_strtolower($un);
            }
            $un_h = htmlspecialchars($un !== '' ? $un : '-', ENT_QUOTES, 'UTF-8');
            $np_h = htmlspecialchars($np !== '' ? $np : '-', ENT_QUOTES, 'UTF-8');
            $tm = $inv['time_sell'] ?? '-';
            $text = "🔴 <b>خرید / تمدید</b>\n👤 <code>{$un_h}</code>\n📦 {$np_h}\n➖ مبلغ: " . number_format($amount) . " تومان\n🕐 {$tm}";
            $addItem(0, $text, $fp);
        }
    } catch (Throwable $e) {}

    usort($items, function ($a, $b) {
        if ($a['ts'] == $b['ts']) {
            return 0;
        }
        return ($a['ts'] > $b['ts']) ? -1 : 1;
    });
    $final = array_slice($items, 0, 10);

    if (empty($final)) {
        $msg = "📜 <b>تاریخچه تراکنش‌ها</b>\n\nهنوز تراکنشی ثبت نشده است.";
    } else {
        $bal = number_format(intval($user['Balance'] ?? 0));
        $body = [];
        $i = 1;
        foreach ($final as $L) {
            $body[] = "<b>#{$i}</b>\n" . $L['text'];
            $i++;
        }
        $msg = "📜 <b>۱۰ تراکنش اخیر شما</b>\n💰 موجودی فعلی: <b>{$bal}</b> تومان\n\n" . implode("\n\n────────────\n\n", $body);
    }
    $kb_back = json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => 'user_tx_back']]]]);
    Editmessagetext($from_id, $message_id, $msg, $kb_back);
    return;
}
if ($datain == "user_tx_back") {
    $dateacc = jdate('Y/m/d');
    $timeacc = jdate('H:i:s');
    $countorder = select("invoice", "*", "id_user", $from_id, "count");
    $aff_earn = number_format(function_exists('getAffiliatesEarned') ? getAffiliatesEarned($from_id) : 0);
    $Balanceuser = number_format(intval($user['Balance'] ?? 0), 0);
    $text_account = sprintf($textbotlang['users']['account'], $first_name, $from_id, $Balanceuser, $countorder, $user['affiliatescount'] ?? 0, $aff_earn, $dateacc, $timeacc);
    Editmessagetext($from_id, $message_id, $text_account, $keyboardPanel);
    return;
}


if ($text == $datatextbot['text_sell'] || $datain == "buy" || $text == "/buy") {
    // وضعیت خرید را زنده از دیتابیس بخوان (برای همه، حتی ادمین)
    $__st_buy = select("setting", "*", null, null, "select");
    $__buy_flag = '1';
    if (is_array($__st_buy) && array_key_exists('status_buy', $__st_buy) && $__st_buy['status_buy'] !== null && $__st_buy['status_buy'] !== '') {
        $__buy_flag = strval($__st_buy['status_buy']);
    } elseif (isset($setting['status_buy']) && $setting['status_buy'] !== null && $setting['status_buy'] !== '') {
        $__buy_flag = strval($setting['status_buy']);
    }
    if ($__buy_flag === '0') {
        sendmessage($from_id, getEditableBotText('msg_buy_disabled', $textbotlang['users']['sell']['buy_disabled']), $keyboard, 'HTML');
        return;
    }

    $locationproduct = select("marzban_panel", "*", "status", "activepanel", "count");
    if ($locationproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], null, 'HTML');
        return;
    }
    if ($setting['get_number'] == "1" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "1")
        return;
    #-----------------------#
    if ($locationproduct == 1) {
        $panel = select("marzban_panel", "*", "status", "activepanel", "select");
        update("user", "Processing_value", $panel['name_panel'], "id", $from_id, "select");
        if ($setting['statuscategory'] == "0") {
            $nullproduct = select("product", "*", null, null, "count");
            if ($nullproduct == 0) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
                return;
            }
            $textproduct = sprintf($textbotlang['users']['buy']['selectService'], $panel['name_panel']);
            sendmessage($from_id, $textproduct, KeyboardProduct($panel['name_panel'], "backuser", $panel['MethodUsername']), 'HTML');
        } else {
            $emptycategory = select("category", "*", null, null, "count");
            if ($emptycategory == 0) {
                sendmessage($from_id, $textbotlang['users']['category']['NotFound'], null, 'HTML');
                return;
            }
            if ($datain == "buy") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['category']['selectCategory'], KeyboardCategorybuy("backuser", $panel['name_panel']));
            } else {
                sendmessage($from_id, $textbotlang['users']['category']['selectCategory'], KeyboardCategorybuy("backuser", $panel['name_panel']), 'HTML');
            }
        }
    } else {
        if ($datain == "buy") {
            Editmessagetext($from_id, $message_id, $textbotlang['users']['Service']['Location'], $list_marzban_panel_user);
        } else {
            sendmessage($from_id, $textbotlang['users']['Service']['Location'], $list_marzban_panel_user, 'HTML');
        }
    }
} elseif (preg_match('/^categorylist_(.*)/', $datain, $dataget)) {
    $categoryid = $dataget[1];
    $product = [];
    $nullproduct = select("product", "*", null, null, "count");
    if ($nullproduct == 0) {
        sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
        return;
    }
    $location = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($location == false) {
        sendmessage($from_id, $textbotlang['users']['category']['error'], null, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['buy']['selectService'], $location['name_panel']), KeyboardProduct($location['name_panel'], "buy", $location['MethodUsername'], $categoryid));
    update("user", "Processing_value", $location['name_panel'], "id", $from_id);
} elseif (preg_match('/^location_(.*)/', $datain, $dataget)) {
    $locationid = $dataget[1];
    $panellist = select("marzban_panel", "*", "id", $locationid, "select");
    $location = $panellist['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    if ($setting['statuscategory'] == "0") {
        $nullproduct = select("product", "*", null, null, "count");
        if ($nullproduct == 0) {
            sendmessage($from_id, $textbotlang['Admin']['Product']['nullpProduct'], null, 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['buy']['selectService'], $panellist['name_panel']), KeyboardProduct($panellist['name_panel'], "buy", $panellist['MethodUsername']));
    } else {
        $emptycategory = select("category", "*", null, null, "count");
        if ($emptycategory == 0) {
            sendmessage($from_id, $textbotlang['users']['category']['NotFound'], null, 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, $textbotlang['users']['category']['selectCategory'], KeyboardCategorybuy("buy", $panellist['name_panel']));
    }
} elseif (preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    update("user", "Processing_value_one", $prodcut, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['selectusername'], $keyboard_getusername, 'html');
    step('endstepuser', $from_id);
} elseif ($user['step'] == "endstepuser" || preg_match('/prodcutservice_(.*)/', $datain, $dataget)) {
    if ($user['step'] != "endstepuser") {
        $prodcut = $dataget[1];
    }
    $panellist = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($panellist == false) {
        sendmessage($from_id, $textbotlang['users']['category']['error'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    if ($panellist['MethodUsername'] == $textbotlang['users']['customusername']) {
        if ($text === '🎲 خودکار انتخاب کن' || $text === 'خودکار انتخاب کن') {
            $text = generateAvailableUsername($panellist['name_panel']);
        }
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $keyboard_getusername, 'HTML');
            return;
        }
        $loc = $user['Processing_value_one'];
    } else {
        deletemessage($from_id, $message_id);
        $loc = $prodcut;
    }
    if ($loc == null) {
        sendmessage($from_id, $textbotlang['users']['category']['error'], $keyboard, 'html');
        step("home", $from_id);
        return;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (location = :loc1 OR location = '/all') LIMIT 1");
    $stmt->bindValue(':code_product', $loc);
    $stmt->bindValue(':loc1', $user['Processing_value']);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($info_product == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error2'], $keyboard, 'HTML');
        step("home", $from_id);
        return;
    }
    $randomString = bin2hex(random_bytes(2));
    $panellist = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $username_ac = strtolower(generateUsername($from_id, $panellist['MethodUsername'], $username, $randomString, $text));
    $DataUserOut = $ManagePanel->DataUser($panellist['name_panel'], $username_ac);
    $random_number = random_int(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . $username_ac;
    }
    update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ($info_product['Volume_constraint'] == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['status']['Unlimited'];
    $price_disp = number_format(intval($info_product['price_product']), 0);
    $bal_disp = number_format(intval($user['Balance']), 0);
    $textin = sprintf($textbotlang['users']['buy']['invoicebuy'], $username_ac, $info_product['name_product'], $info_product['Service_time'], $price_disp, $info_product['Volume_constraint'], $bal_disp);
    // اول کیبورد پایین حذف شود، بعد فاکتور مثل قبل با دکمه‌های پرداخت زیر خودش
    sendmessage($from_id, "‌", json_encode(['remove_keyboard' => true]), 'HTML');
    sendmessage($from_id, $textin, $payment, 'HTML');
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && ($datain == "confirmandgetservice" || $datain == "confirmandgetserviceDiscount")) {
    Editmessagetext($from_id, $message_id, $text_callback, json_encode(['inline_keyboard' => []]));
    $partsdic = explode("_", $user['Processing_value_four']);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code AND (location = :loc1 OR location = '/all') LIMIT 1");
    $stmt->bindValue(':code', $user['Processing_value_one']);
    $stmt->bindValue(':loc1', $user['Processing_value']);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($info_product == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error2'], $keyboard, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error2'], $keyboard, 'HTML');
        return;
    }
    if ($marzban_list_get['linksubx'] == null and in_array($marzban_list_get['type'], ["x-ui_single", "alireza"])) {
        foreach ($admin_ids as $admin) {
            sendmessage($admin, sprintf($textbotlang['Admin']['managepanel']['notsetlinksub'], $marzban_list_get['name_panel']), null, 'HTML');
        }
        sendmessage($from_id, $textbotlang['Admin']['managepanel']['paneldeactive'], $keyboard, 'HTML');
        return;
    }
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    if (empty($info_product['price_product']) || empty($info_product['price_product']))
        return;
    if ($datain == "confirmandgetserviceDiscount") {
        $priceproduct = $partsdic[2];
    } else {
        $priceproduct = $info_product['price_product'];
    }
    if ($priceproduct > $user['Balance']) {
        // واریز خاموش باشد → فقط پیام بسته بودن، بدون ساخت فاکتور unpaid
        if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
            sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $Balance_prim = $priceproduct - $user['Balance'];
        if ($Balance_prim < getDepositLimits()['min']) {
            sendmessage($from_id, msgShortfallBelowMin('buy'), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        update("user", "Processing_value", $Balance_prim, "id", $from_id);
        sendmessage($from_id, "‌", json_encode(['remove_keyboard' => true]), 'HTML');
        sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
        step('get_step_payment', $from_id);
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice(id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?,?,?)");
        $Status = "unpaid";
        $stmt->bind_param("ssssssssss", $from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status);
        $stmt->execute();
        $stmt->close();
        update("user", "Processing_value_one", $username_ac, "id", $from_id);
        update("user", "Processing_value_tow", "getconfigafterpay", "id", $from_id);
        return;
    }
    // قفل + کسر اتمیک قبل از ساخت سرویس (ضد دابل‌کلیک)
    $__price_buy = intval($priceproduct);
    $__buy_lock = function_exists('paymentAcquireLock') ? paymentAcquireLock($from_id, 'buy') : true;
    if ($__buy_lock === null) {
        sendmessage($from_id, "⏳ درخواست قبلی در حال انجام است. چند لحظه صبر کنید.", $keyboard, 'HTML');
        return;
    }
    $__bal_row = select("user", "Balance", "id", $from_id, "select");
    $__bal_before = is_array($__bal_row) ? intval($__bal_row['Balance'] ?? 0) : intval($user['Balance'] ?? 0);
    if ($__price_buy > $__bal_before) {
        if (function_exists('paymentReleaseLock') && is_array($__buy_lock)) {
            paymentReleaseLock($__buy_lock);
        }
        if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
            sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        $Balance_prim = $__price_buy - $__bal_before;
        if ($Balance_prim < getDepositLimits()['min']) {
            sendmessage($from_id, msgShortfallBelowMin('buy'), $keyboard, 'HTML');
            step('home', $from_id);
            return;
        }
        update("user", "Processing_value", $Balance_prim, "id", $from_id);
        sendmessage($from_id, "‌", json_encode(['remove_keyboard' => true]), 'HTML');
        sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
        step('get_step_payment', $from_id);
        return;
    }
    $Balance_prim = function_exists('atomicDeductBalance')
        ? atomicDeductBalance($from_id, $__price_buy)
        : false;
    if ($Balance_prim === false) {
        if (function_exists('paymentReleaseLock') && is_array($__buy_lock)) {
            paymentReleaseLock($__buy_lock);
        }
        sendmessage($from_id, "⏳ امکان انجام همزمان نیست یا موجودی کافی نیست.", $keyboard, 'HTML');
        return;
    }
    $__buy_deducted = $__price_buy;

    if (in_array($randomString, $id_invoice)) {
        $random_number = random_int(1000000, 9999999);
        $randomString = $random_number . $randomString;
    }
    $sql = "INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $Status = "active";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $from_id);
    $stmt->bindParam(2, $randomString);
    $stmt->bindParam(3, $username_ac, PDO::PARAM_STR);
    $stmt->bindParam(4, $date);
    $stmt->bindParam(5, $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(6, $info_product['name_product'], PDO::PARAM_STR);
    $stmt->bindParam(7, $info_product['price_product']);
    $stmt->bindParam(8, $info_product['Volume_constraint']);
    $stmt->bindParam(9, $info_product['Service_time']);
    $stmt->bindParam(10, $Status);
    $stmt->execute();
    if ($info_product['Service_time'] == "0") {
        $data = "0";
    } else {
        $date = strtotime("+" . $info_product['Service_time'] . "days");
        $data = strtotime(date("Y-m-d H:i:s", $date));
    }
    $datac = array(
        'expire' => $data,
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
    );
    $dataoutput = createUserWithRetry($marzban_list_get['name_panel'], $username_ac, $datac);
    if (!empty($dataoutput['username_final'])) {
        $username_ac = $dataoutput['username_final'];
    }
    if ($dataoutput['username'] == null) {
        $err_msg = formatPanelErrorMsg($dataoutput['msg'] ?? null);
        // موجودی قبل از ساخت کسر شده — در صورت خطا برگردان
        $price_try = intval($__buy_deducted ?? $priceproduct ?? 0);
        $bal_before = intval($__bal_before ?? $user['Balance'] ?? 0);
        $refunded = 0;
        if ($price_try > 0) {
            if (function_exists('atomicAddBalance')) {
                $refunded = atomicAddBalance($from_id, $price_try);
            } else {
                $refunded = refundBalanceIfDeducted($from_id, $price_try, $bal_before);
            }
        }
        if (function_exists('paymentReleaseLock') && isset($__buy_lock) && is_array($__buy_lock)) {
            paymentReleaseLock($__buy_lock);
        }
        if ($refunded > 0) {
            $price_fmt = number_format($refunded);
            sendmessage($from_id, sprintf($textbotlang['users']['buy']['create_failed_refund'], $price_fmt), $keyboard, 'HTML');
            $admin_extra = "

💰 مبلغ {$price_fmt} تومان به کیف پول کاربر برگشت داده شد.";
        } else {
            sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
            $admin_extra = "

ℹ️ موجودی کاربر کم نشده بود؛ برگشت وجه انجام نشد.";
        }
        $texterros = sprintf($textbotlang['users']['buy']['errorInCreate'], $err_msg, $from_id, $username);
        $texterros .= $admin_extra;
        foreach ($admin_ids as $admin) {
            sendmessage($admin, $texterros, null, 'HTML');
        }
        if (function_exists('sendChannelReport') && isset($textbotlang['Admin']['Report']['create_error'])) {
            $refund_txt = ($refunded > 0) ? (number_format($refunded) . ' تومان برگشت شد') : 'برگشت وجه نداشت';
            $panel_n = $user['Processing_value'] ?? ($info_product['Location'] ?? '-');
            $uname_try = $username_ac ?? '-';
            $ch_err = sprintf(
                $textbotlang['Admin']['Report']['create_error'],
                $from_id,
                $username ?? '-',
                $uname_try,
                $panel_n,
                $err_msg,
                $refund_txt,
                'خرید از کیف پول'
            );
            sendChannelReport('rpt_create_error', $ch_err);
        }
        step('home', $from_id);
        return;
    }
    if (!empty($dataoutput['username']) && isset($randomString)) {
        update("invoice", "username", $dataoutput['username'], "id_invoice", $randomString);
        $username_ac = $dataoutput['username'];
    }
    if ($datain == "confirmandgetserviceDiscount") {
        $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $partsdic[0], "select");
        $value = intval($SellDiscountlimit['usedDiscount']) + 1;
        update("DiscountSell", "usedDiscount", $value, "codeDiscount", $partsdic[0]);
        $text_report = sprintf($textbotlang['users']['Report']['discountused'], $username, $from_id, $partsdic[0]);
        if (function_exists('sendChannelReport')) { sendChannelReport('rpt_discount', $text_report); }
        elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
            sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
        }
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    if ($affiliatescommission['status_commission'] == "oncommission" && ($user['affiliates'] !== null || $user['affiliates'] != "0")) {
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        $result = ($priceproduct * $affiliatescommission['affiliatespercentage']) / 100;
        $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
        if ($user_Balance) {
            $Balance_prim = $user_Balance['Balance'] + $result;
            update("user", "Balance", $Balance_prim, "id", $user['affiliates']);
            if (function_exists('addAffiliatesBalance')) {
                addAffiliatesBalance($user['affiliates'], $result);
            }
            $result = number_format($result);
            $textadd = sprintf($textbotlang['users']['affiliates']['porsantuser'], $result);
            sendmessage($user['affiliates'], $textadd, null, 'HTML');
        }
    }
    $link_config = "";
    $text_config = "";
    $config = "";
    $configqr = "";
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
        $link_config = $output_config_link;
    }
    if ($marzban_list_get['configManual'] == "onconfig") {
        if (is_array($dataoutput['configs']) and count($dataoutput['configs']) != 0) {
            foreach ($dataoutput['configs'] as $configs) {
                $config .= "\n" . $configs;
                $configqr .= $configs;
            }
        } else {
            $config .= "";
            $configqr .= "";
        }
        $text_config = $config;
    }
    $Shoppinginfo = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['help']['btninlinebuy'], 'callback_data' => "helpbtn"],
            ]
        ]
    ]);
    if ($marzban_list_get['type'] == "wgdashboard") {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservicewgbuy'], $username_ac, $info_product['name_product'], $marzban_list_get['name_panel'], $info_product['Service_time'], $info_product['Volume_constraint']);
    } elseif ($marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservice_mikrotik_buy'], $username_ac, $dataoutput['subscription_url'], $info_product['name_product'], $marzban_list_get['name_panel'], $info_product['Service_time'], $info_product['Volume_constraint']);
    } else {
        $textcreatuser = sprintf($textbotlang['users']['buy']['createservice'], $username_ac, $info_product['name_product'], $marzban_list_get['name_panel'], $info_product['Service_time'], $info_product['Volume_constraint'], $text_config, $link_config);
    }
    if ($marzban_list_get['type'] == "mikrotik") {
        sendmessage($from_id, $textcreatuser, $Shoppinginfo, 'HTML');
        sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
    } else {
        if ($marzban_list_get['sublink'] == "onsublink") {
            $urlimage = "$from_id$randomString.png";
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
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'reply_markup' => $Shoppinginfo,
                'caption' => $textcreatuser,
                'parse_mode' => "HTML",
            ]);
            if ($marzban_list_get['type'] == "wgdashboard") {
                $urldocs = "{$marzban_list_get['inboundid']}_{$randomString}.conf";
                file_put_contents($urldocs, $output_config_link);
                sendDocument($from_id, $urldocs, $textbotlang['users']['buy']['configwg']);
                unlink($urlimage);
            }
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
            unlink($urlimage);
        } elseif ($marzban_list_get['config'] == "onconfig") {
            if (count($dataoutput['configs']) == 1) {
                $urlimage = "$from_id$randomString.png";
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
                    'chat_id' => $from_id,
                    'photo' => new CURLFile($urlimage),
                    'reply_markup' => $Shoppinginfo,
                    'caption' => $textcreatuser,
                    'parse_mode' => "HTML",
                ]);
                unlink($urlimage);
            } else {
                sendmessage($from_id, $textcreatuser, $Shoppinginfo, 'HTML');
            }
        } else {
            sendmessage($from_id, $textcreatuser, $Shoppinginfo, 'HTML');
            sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'HTML');
        }
    }
    // موجودی قبلاً اتمیک کسر شده — فقط لاگ و آزادسازی قفل
    $Balance_prim = intval($Balance_prim ?? 0);
    if (function_exists('paymentReleaseLock') && isset($__buy_lock) && is_array($__buy_lock)) {
        paymentReleaseLock($__buy_lock);
    }
    if (function_exists('logWalletTx')) {
        logWalletTx($from_id, 'buy', intval($priceproduct), $Balance_prim, 'خرید سرویس: ' . ($username_ac ?? ''));
    }
    if (function_exists('recordSale')) {
        recordSale($from_id, intval($priceproduct), 'buy', $username_ac ?? null, null);
    }
    // موجودی بعد از کسر — از مقدار محاسبه‌شده (نه number_format+intval که برای اعداد بالای 999 می‌شود 1)
    $bal_after_fmt = number_format($Balance_prim);
    $price_fmt = number_format(intval(str_replace(',', '', strval($info_product['price_product']))));
    $text_report = sprintf($textbotlang['users']['Report']['reportbuy'], $username_ac, $price_fmt, $info_product['Volume_constraint'], $from_id, $username, $user['number'], $user['Processing_value'], $bal_after_fmt);
    if (function_exists('sendChannelReport')) { sendChannelReport('rpt_buy', $text_report); }
    elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
    step('home', $from_id);
} elseif ($datain == "aptdc") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcodesell'], $backuser, 'HTML');
    step('getcodesellDiscount', $from_id);
    deletemessage($from_id, $message_id);
} elseif ($user['step'] == "getcodesellDiscount") {
    if (!in_array($text, $SellDiscount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], $backuser, 'HTML');
        return;
    }
    $SellDiscountlimit = select("DiscountSell", "*", "codeDiscount", $text, "select");
    if ($SellDiscountlimit == false) {
        sendmessage($from_id, $textbotlang['Admin']['Discount']['invalidcodedis'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code AND (location = :loc1 OR location = '/all') LIMIT 1");
    $stmt->bindValue(':code', $user['Processing_value_one']);
    $stmt->bindValue(':loc1', $user['Processing_value']);
    $stmt->execute();
    $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($info_product == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error2'], $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($SellDiscountlimit['limitDiscount'] == $SellDiscountlimit['usedDiscount']) {
        sendmessage($from_id, $textbotlang['users']['Discount']['erorrlimit'], null, 'HTML');
        return;
    }
    if ($SellDiscountlimit['usefirst'] == "1") {
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user");
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $countinvoice = $stmt->rowCount();
        if ($countinvoice != 0) {
            sendmessage($from_id, $textbotlang['users']['Discount']['firstdiscount'], null, 'HTML');
            return;
        }
    }
    sendmessage($from_id, $textbotlang['users']['Discount']['correctcode'], $keyboard, 'HTML');
    step('payment', $from_id);
    $result = ($SellDiscountlimit['price'] / 100) * $info_product['price_product'];

    $info_product['price_product'] = $info_product['price_product'] - $result;
    $info_product['price_product'] = round($info_product['price_product']);
    if ($info_product['price_product'] < 0)
        $info_product['price_product'] = 0;
    $textin = sprintf($textbotlang['users']['buy']['invoicebuy'], $user['Processing_value_tow'], $info_product['name_product'], $info_product['Service_time'], $info_product['price_product'], $info_product['Volume_constraint'], $user['Balance']);
    $paymentDiscount = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['buy']['payandGet'], 'callback_data' => "confirmandgetserviceDiscount"]],
            [['text' => $textbotlang['users']['backhome'], 'callback_data' => "backuser"]]
        ]
    ]);
    $parametrsendvalue = "dis_" . $text . "_" . $info_product['price_product'];
    update("user", "Processing_value_four", $parametrsendvalue, "id", $from_id);
    sendmessage($from_id, $textin, $paymentDiscount, 'HTML');
}



#-------------------[ text_Add_Balance ]---------------------#
if ($text == $datatextbot['text_Add_Balance'] || $text == "/wallet") {
    if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
        return;
    }

    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    if ($setting['get_number'] == "1" && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['Confirming'], $request_contact, 'HTML');
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && $setting['get_number'] == "1")
        return;
    $pkgs = getBalancePackages();
    if (count($pkgs) > 0) {
        sendmessage($from_id, $textbotlang['users']['Balance']['choose_package'] ?? "یک پکیج انتخاب کنید یا مبلغ دلخواه وارد کنید:", buildBalancePackageUserKeyboard(), 'HTML');
        step('home', $from_id);
    } else {
        $depLim = getDepositLimits();
        sendmessage($from_id, sprintf($textbotlang['users']['Balance']['priceinput'], formatToman($depLim['min']), formatToman($depLim['max'])), $backuser, 'HTML');
        step('getprice', $from_id);
    }
} elseif ($datain == "balpkg_custom") {
    if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
        return;
    }
    $depLim = getDepositLimits();
    deletemessage($from_id, $message_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    sendmessage($from_id, sprintf($textbotlang['users']['Balance']['priceinput'], formatToman($depLim['min']), formatToman($depLim['max'])), $backuser, 'HTML');
    step('getprice', $from_id);
} elseif (preg_match('/^balpkg_(.+)$/', strval($datain), $m_bp) && $datain != "balpkg_custom") {
    if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
        return;
    }
    $pkg = getBalancePackageById($m_bp[1]);
    if (!$pkg) {
        sendmessage($from_id, $textbotlang['users']['buy']['package_not_found'], $keyboard, 'HTML');
        return;
    }
    $pay = getBalancePackagePayAmount($pkg['amount'], $pkg['discount']);
    $depLim = getDepositLimits();
    if ($pay < $depLim['min'] || $pay > $depLim['max']) {
        sendmessage($from_id, sprintf($textbotlang['users']['Balance']['errorpricelimit'], formatToman($depLim['min']), formatToman($depLim['max'])), $keyboard, 'HTML');
        return;
    }
    update("user", "Processing_value", strval($pay), "id", $from_id);
    update("user", "Processing_value_one", strval($pkg['amount']), "id", $from_id);
    update("user", "Processing_value_tow", "balpkg", "id", $from_id);
    deletemessage($from_id, $message_id);
    $disc = rtrim(rtrim(number_format($pkg['discount'], 1, '.', ''), '0'), '.');
    $info = sprintf(
        $textbotlang['users']['Balance']['package_selected'] ?? "🎁 پکیج انتخاب شد
اعتبار: %s تومان
تخفیف: %s٪
مبلغ قابل پرداخت: %s تومان

روش پرداخت را انتخاب کنید:",
        formatToman($pkg['amount']),
        $disc,
        formatToman($pay)
    );
    sendmessage($from_id, $info, $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "getprice") {
    if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    $depLim = getDepositLimits();
    if (intval($text) > $depLim['max'] || intval($text) < $depLim['min']) {
        return sendmessage($from_id, sprintf($textbotlang['users']['Balance']['errorpricelimit'], formatToman($depLim['min']), formatToman($depLim['max'])), null, 'HTML');
    }
    update("user", "Processing_value", $text, "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Balance']['selectPatment'], $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    if (function_exists('isDepositEnabled') && !isDepositEnabled()) {
        sendmessage($from_id, getEditableBotText('msg_deposit_closed', $textbotlang['users']['Balance']['deposit_closed']), $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if (isset($user['Processing_value']) && is_numeric($user['Processing_value']) && intval($user['Processing_value']) > 0 && intval($user['Processing_value']) < getDepositLimits()['min']) {
        sendmessage($from_id, msgShortfallBelowMin('pay'), $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    if ($datain == "cart_to_offline") {
        $PaySetting = select("PaySetting", "ValuePay", "NamePay", "CartDescription", "select")['ValuePay'];
        $Processing_value = number_format($user['Processing_value']);
        $textcart = sprintf($textbotlang['users']['moeny']['carttext'], $Processing_value, $PaySetting);
        preg_match_all('/\d+/', $PaySetting, $Matches);
        if (!empty($Matches[0]) && intval($setting['copy_cart']) == 1) {
            $peymentSettings['card_number'] = implode('', $Matches[0]);
            $MESSAGE = $textcart;
            $KEYBOARD = json_encode(["inline_keyboard" => [[['text' => $textbotlang['users']['moeny']['copy_card_number'], 'copy_text' => ['text' => $peymentSettings['card_number']]], ['text' => $textbotlang['users']['moeny']['copy_price'], 'copy_text' => ['text' => $user['Processing_value']]]], [['text' => $textbotlang['users']['backhome'], 'callback_data' => 'backuser']]]]);
            Editmessagetext($from_id, $message_id, $MESSAGE, $KEYBOARD);
        } else {
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textcart, $backuser, 'HTML');
        }
        step('cart_to_cart_user', $from_id);
    }
    if ($datain == "iranpay") {
        $amount_toman = intval($user['Processing_value']);
        $built = buildCurrencyPaymentText($amount_toman);
        if (!$built['ok']) {
            if (($built['error'] ?? '') === 'rate') {
                sendmessage($from_id, $textbotlang['users']['moeny']['currency_rate_error'], $keyboard, 'HTML');
            } else {
                sendmessage($from_id, $textbotlang['users']['moeny']['currency_empty'], $keyboard, 'HTML');
            }
            step('home', $from_id);
            return;
        }
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $built['text'], $backuser, 'HTML');
        step('crypto_receipt_user', $from_id);
    }

} elseif ($user['step'] == "cart_to_cart_user" || $user['step'] == "crypto_receipt_user") {
    if (!$photo) {
        sendmessage($from_id, $textbotlang['users']['Balance']['Invalid-receipt'], null, 'HTML');
        return;
    }
    $dateacc = date('Y/m/d H:i:s');
    $randomString = bin2hex(random_bytes(5));
    $payment_Status = "waiting";
    $Payment_Method = ($user['step'] == "crypto_receipt_user") ? "crypto" : "cart to cart";
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
    } elseif ($user['Processing_value_tow'] == "balpkg" && intval($user['Processing_value_one']) > 0) {
        $invoice = "balpkg|" . intval($user['Processing_value_one']);
    } else {
        $invoice = "0|0";
    }
    $stmt = $pdo->prepare("INSERT INTO Payment_report (id_user, id_order, time, price, payment_Status, Payment_Method,invoice) VALUES (?, ?, ?, ?, ?, ?,?)");
    $stmt->bindParam(1, $from_id);
    $stmt->bindParam(2, $randomString);
    $stmt->bindParam(3, $dateacc);
    $stmt->bindParam(4, $user['Processing_value'], PDO::PARAM_STR);
    $stmt->bindParam(5, $payment_Status);
    $stmt->bindParam(6, $Payment_Method);
    $stmt->bindParam(7, $invoice);
    $stmt->execute();
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receip-buy'], $keyboard, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Balance']['Send-receipt'], $keyboard, 'HTML');
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['Confirmpaying'], 'callback_data' => "Confirm_pay_{$randomString}"],
                ['text' => $textbotlang['users']['Balance']['reject_pay'], 'callback_data' => "reject_pay_{$randomString}"],
            ],
            [
                ['text' => '👤 اطلاعات کاربر', 'callback_data' => "userinfo_pay_{$from_id}"],
            ]
        ]
    ]);
    $Processing_value = number_format($user['Processing_value']);
    $user_balance_fmt = number_format(intval($user['Balance']));
    // توضیح نوع پرداخت برای ادمین (پکیج / خرید / دلخواه)
    $pay_note = '';
    if (($user['Processing_value_tow'] ?? '') === 'balpkg' && intval($user['Processing_value_one'] ?? 0) > 0) {
        $credit_fmt = number_format(intval($user['Processing_value_one']));
        $pay_note = "🎁 <b>پکیج افزایش موجودی</b>\n💎 اعتبار واریزی: <b>{$credit_fmt}</b> تومان\n💰 مبلغ قابل پرداخت: <b>{$Processing_value}</b> تومان";
    } elseif (($user['Processing_value_tow'] ?? '') === 'getconfigafterpay') {
        $svc = htmlspecialchars(strval($user['Processing_value_one'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $pay_note = "🛒 پرداخت برای خرید سرویس\n🔑 نام کاربری: <code>{$svc}</code>";
    } else {
        $pay_note = 'افزایش موجودی (مبلغ دلخواه)';
        if (!empty($caption)) {
            $pay_note .= "\n" . $caption;
        }
    }
    $textsendrasid = sprintf($textbotlang['users']['moeny']['cartresid'], $from_id, $randomString, $username, $Processing_value, $user_balance_fmt, $pay_note);
    foreach ($admin_ids as $id_admin) {
        telegram('sendphoto', [
            'chat_id' => $id_admin,
            'photo' => $photoid,
            'reply_markup' => $Confirm_pay,
            'caption' => $textsendrasid,
            'parse_mode' => "HTML",
        ]);
    }
    step('home', $from_id);
}

#----------- اطلاعات کاربر از روی رسید پرداخت ------------#
if (preg_match('/userinfo_pay_(\d+)/', $datain, $dataget)) {
    if (!in_array($from_id, $admin_ids)) {
        return;
    }
    $id_user_info = intval($dataget[1]);
    if (function_exists('sendAdminUserInfo')) {
        sendAdminUserInfo($from_id, $id_user_info);
    } else {
        sendmessage($from_id, $textbotlang['users']['admin']['userinfo_unavailable'], null, 'HTML');
    }
}

#----------------Discount------------------#
if ($datain == "Discount") {
    sendmessage($from_id, $textbotlang['users']['Discount']['getcode'], $backuser, 'HTML');
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM Giftcodeconsumed WHERE id_user = :id_user");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $Checkcode = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Checkcode[] = $row['code'];
    }
    if (in_array($text, $Checkcode)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['onecode'], $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    $balance_user = $user['Balance'] + $get_codesql['price'];
    update("user", "Balance", $balance_user, "id", $from_id);
    if (function_exists('logWalletTx')) {
        logWalletTx($from_id, 'deposit', intval($get_codesql['price']), $balance_user, 'کد هدیه / اعتبار');
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code");
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    step('home', $from_id);
    number_format($get_codesql['price']);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['acceptdiscount'], $get_codesql['price']);
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (?, ?)");
    $stmt->bindParam(1, $from_id);
    $stmt->bindParam(2, $text, PDO::PARAM_STR);
    $stmt->execute();
    $text_report = sprintf($textbotlang['users']['Report']['discountuser'], $text, $from_id, $username, $get_codesql['price']);
    if (function_exists('sendChannelReport')) { sendChannelReport('rpt_gift', $text_report); }
    elseif (isset($setting['Channel_Report']) && strlen($setting['Channel_Report']) > 0) {
        sendmessage($setting['Channel_Report'], $text_report, null, 'HTML');
    }
}
#----------------[  text_Tariff_list  ]------------------#
if ($text == $datatextbot['text_Tariff_list']) {
    if (isset($setting['status_tariff_list']) && ($setting['status_tariff_list'] == '0' || $setting['status_tariff_list'] === 0) && !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['sell']['tariff_disabled'], $keyboard, 'HTML');
        return;
    }
    sendmessage($from_id, $datatextbot['text_dec_Tariff_list'], null, 'HTML');
}
if ($datain == "closelist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
}
if ($text == $textbotlang['users']['affiliates']['btn']) {
    if (isset($setting['status_affiliates_btn']) && ($setting['status_affiliates_btn'] == '0' || $setting['status_affiliates_btn'] === 0)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
        return;
    }
    $affiliatesvalue = select("affiliates", "*", null, null, "select")['affiliatesstatus'];
    if ($affiliatesvalue == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    $my_code = $user['ref_code'];
    $keyboard_share = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang["users"]["affiliates"]["share"], 'url' => "https://t.me/$usernamebot?start=$my_code"],
            ],
        ]
    ]);
    $textaffiliates = ($affiliates['description'] !== null && $affiliates['description'] !== '' && $affiliates['description'] !== 'none')
        ? "{$affiliates['description']}\n\n🔗 https://t.me/$usernamebot?start=$my_code"
        : "🔗 https://t.me/$usernamebot?start=$my_code";

    // Check if badge/image is set before sending photo
    if (!empty($affiliates['id_media']) && $affiliates['id_media'] !== 'none') {
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => $affiliates['id_media'],
            'caption' => $textaffiliates,
            'parse_mode' => "HTML",
            'reply_markup' => $keyboard_share
        ]);
    } else {
        // Send as text message if no badge/image is available
        sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $affiliatespercentage = $affiliatescommission['affiliatespercentage'] . $textbotlang['users']['Percentage'];
    } else {
        $affiliatespercentage = $textbotlang['users']['status']['disabled'];
    }
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $price_Discount = $affiliatescommission['price_Discount'] . $textbotlang['users']['IRT'];
    } else {
        $price_Discount = $textbotlang['users']['status']['disabled'];
    }
    $textaffiliates = sprintf($textbotlang['users']['affiliates']['infotext'], $price_Discount, $affiliatespercentage);
    sendmessage($from_id, $textaffiliates, $keyboard, 'HTML');
}
require_once 'admin.php';
$connect->close();
