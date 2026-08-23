<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once '../config.php';
require_once '../botapi.php';
require_once '../panels.php';
require_once '../functions.php';
require_once '../jdf.php';
require_once '../text.php';
require '../vendor/autoload.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$datatextbotget = select("textbot", "*",null ,null ,"fetchAll");
$datatxtbot = array();
foreach ($datatextbotget as $row) {
    $datatxtbot[] = array(
        'id_text' => $row['id_text'],
        'text' => $row['text']
    );
}
$datatextbot = array(
    'textafterpay' => '',
    'textaftertext' => '',
    'textmanual' => '',
    'textselectlocation' => ''
);
foreach ($datatxtbot as $item) {
    if (isset($datatextbot[$item['id_text']])) {
        $datatextbot[$item['id_text']] = $item['text'];
    }
}
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE payment_Status = 'waiting' AND Payment_Method = 'cart to cart'");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $since_start = time() - strtotime($row['time']);
    if ($since_start >=3600)continue;
    $Payment_report = select("Payment_report","*","id_order",$row['id_order'],"select");
    $Balance_id = select("user","*","id",$Payment_report['id_user'],"select");
    if (($Payment_report['payment_Status'] ?? '') == "paid" || ($Payment_report['payment_Status'] ?? '') == "reject") {
        continue;
    }
    // کاربر ریسکی: تأیید خودکار برای او غیرفعال است
    if (function_exists('ensureUserCartAutoColumn')) { ensureUserCartAutoColumn(); }
    if (is_array($Balance_id) && intval($Balance_id['cart_auto_off'] ?? 0) === 1) {
        continue;
    }
    // قفل اتمیک تا با تأیید دستی ادمین دوبار شارژ نشود
    global $pdo;
    try {
        $stmt_lock = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid', dec_not_confirmed = 'Confirmed by robot' WHERE id_order = ? AND payment_Status = 'waiting'");
        $stmt_lock->execute([$Payment_report['id_order']]);
        if ($stmt_lock->rowCount() < 1) {
            continue;
        }
    } catch (Exception $e) {
        continue;
    }
    DirectPayment($Payment_report['id_order'],"../images.jpg");
    $pd = function_exists('describePaymentReport') ? describePaymentReport($Payment_report) : null;
    $bal_after = number_format(intval(select('user','Balance','id',$Balance_id['id'],'select')['Balance'] ?? 0));
    if ($pd) {
        $auto_txt = sprintf($textbotlang['Admin']['Report']['autocart'], $Balance_id['id'], $Balance_id['username'] ?? '-', $pd['method_label'], $pd['type_label'], $pd['pay_fmt'], $pd['credit_fmt'], $bal_after, $Payment_report['id_order']);
    } else {
        $auto_txt = sprintf($textbotlang['Admin']['Report']['autocart'], $Balance_id['id'], $Balance_id['username'] ?? '-', '💳 کارت‌به‌کارت', 'افزایش موجودی', number_format(intval($Payment_report['price'])), number_format(intval($Payment_report['price'])), $bal_after, $Payment_report['id_order']);
    }
    if (function_exists('sendChannelReport')) {
        sendChannelReport('rpt_cart_auto', $auto_txt);
    } elseif (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage',[
            'chat_id' => $setting['Channel_Report'],
            'text' => $auto_txt,
            'parse_mode' => "HTML"
        ]);
    }
}