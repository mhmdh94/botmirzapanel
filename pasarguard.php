<?php
require_once 'functions.php';

# timeoutهای مشترک برای جلوگیری از گیر کردن workerها
function marzban_curl_init()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // حداکثر ۵ ثانیه برای اتصال
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);         // حداکثر ۱۰ ثانیه کل درخواست
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    return $ch;
}
#-----------------------------#
function token_panel($code_panel){
    $panel = select("marzban_panel","*","id",$code_panel,"select");
    if($panel['datelogin'] != null){
        $date = json_decode($panel['datelogin'],true);
        if(isset($date['time'])){
        $timecurrent = time();
        $start_date = time() - strtotime($date['time']);
        if($start_date <= 3600){
            return $date;
        }
        }
    }
    $url_get_token = $panel['url_panel'].'/api/admin/token';
    $data_token = array(
        'username' => $panel['username_panel'],
        'password' => $panel['password_panel']
    );
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT_MS => 5000,
        CURLOPT_TIMEOUT_MS => 10000,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_POSTFIELDS => http_build_query($data_token),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'accept: application/json'
        )
    );
    $curl_token = curl_init($url_get_token);
    curl_setopt_array($curl_token, $options);
    $token = curl_exec($curl_token);
    if (curl_error($curl_token)) {
        $token = [];
        $token['errror'] = curl_error($curl_token);
        return $token;
    }
    curl_close($curl_token);

    $body = json_decode( $token, true);
    if(isset($body['access_token'])){
        $time = date('Y/m/d H:i:s');
        $data = json_encode(array(
            'time' => $time,
            'access_token' => $body['access_token']
            ));
        update("marzban_panel","datelogin",$data,'name_panel',$panel['name_panel']);
    }
    return $body;
}

#-----------------------------#

function getuser($usernameac,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac;
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
#-----------------------------#
function ResetUserDataUsage($usernameac,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac.'/reset';
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
#-----------------------------#
function adduser($username,$expire,$data_limit,$location,$is_test = false)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url = $marzban_list_get['url_panel']."/api/user";
    $header_value = 'Bearer ';
    
    // گروه را از خود پنل می‌خوانیم (بدون ساخت mirza_paid / mirza_test)
    $data = array(
        "data_limit" => $data_limit,
        "username" => $username
    );
    // proxies فقط اگر در پنل تنظیم شده باشد (حالت قدیمی)
    if (!empty($marzban_list_get['proxies']) && $marzban_list_get['proxies'] != "null") {
        $decoded_proxies = json_decode($marzban_list_get['proxies'], true);
        if (is_array($decoded_proxies) && count($decoded_proxies) > 0) {
            $data["proxies"] = $decoded_proxies;
        }
    }

    // group_ids الزامی در پاسارگارد — همه گروه‌های ادمین پنل
    $group_ids = resolve_panel_group_ids($location, $is_test, $marzban_list_get);
    if (!is_array($group_ids) || count($group_ids) === 0) {
        // تلاش مجدد با رفرش توکن
        update("marzban_panel", "datelogin", null, "name_panel", $location);
        $group_ids = resolve_panel_group_ids($location, $is_test, $marzban_list_get);
    }
    if (is_array($group_ids) && count($group_ids) > 0) {
        $data['group_ids'] = array_map('intval', array_values($group_ids));
    } else {
        error_log("marzban adduser: no group_ids for panel={$location}");
        // بدون گروه کاربر نساز — پاسخ ساختگی شبیه خطای API برای caller
        return json_encode([
            'detail' => 'هیچ گروهی از پنل خوانده نشد. در پاسارگارد حداقل یک گروه برای ادمین تعریف کنید.',
            'msg' => 'no_group_ids',
            'username' => null,
        ], JSON_UNESCAPED_UNICODE);
    }

    if ($marzban_list_get['inbounds'] != null && $marzban_list_get['inbounds'] != "null") {
        $decoded_inbounds = json_decode($marzban_list_get['inbounds'], true);
        if (is_array($decoded_inbounds) && count($decoded_inbounds) > 0) {
            $data['inbounds'] = $decoded_inbounds;
        }
    }
    if($expire == "0"){
        $data['expire'] = 0;
    }else {
        if($marzban_list_get['onholdstatus'] == "ononhold"){
            $data["expire"] = 0;
            $data["status"] = "on_hold";
            $data["on_hold_expire_duration"] = $expire - time();
        }else{
        $data['expire'] = $expire;
        }
    }
    $payload = json_encode($data);

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token'],
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
//----------------------------------
function Get_System_Stats($location){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url =  $marzban_list_get['url_panel'].'/api/system';
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token'],
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $Get_System_Stats = json_decode($output, true);
    return $Get_System_Stats;
}
//----------------------------------
function removeuser($location,$username)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
//----------------------------------
function Modifyuser($location,$username,array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $payload = json_encode($data);
$ch = marzban_curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$headers = array();
$headers[] = 'Accept: application/json';
$headers[] = 'Authorization: Bearer '.$Check_token['access_token'];
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
curl_close($ch);
     $data_useer = json_decode($result, true);
    return $data_useer;
}

#-----------------------------------------------#
function revoke_sub($username,$location)
{
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panel($marzban_list_get['id']);
    $usernameac = $username;
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac.'/revoke_sub';
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}

//----------------------------------
// Check if Marzban version is above 0.8.4
function is_marzban_version_above_084($location) {
    try {
        $system_stats = Get_System_Stats($location);
        if (isset($system_stats['version'])) {
            $version = $system_stats['version'];
            // Extract numeric version part (e.g., from 'beta 1.0.0' get '1.0.0')
            if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches)) {
                $numeric_version = $matches[1];
            } else {
                $numeric_version = $version; // fallback if no match
            }
            return version_compare($numeric_version, '0.8.4', '>');
        }
    } catch (Exception $e) {
        error_log("Error checking Marzban version: " . $e->getMessage());
    }
    return false;
}

//----------------------------------
// Get all available inbounds for a panel
function get_all_inbounds($location) {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url = $marzban_list_get['url_panel'] . '/api/inbounds';
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);
    $inbounds = json_decode($output, true);
    
    // Extract inbound tags
    $inbound_tags = array();
    if (is_array($inbounds)) {
        foreach ($inbounds as $inbound) {
            if (isset($inbound['tag'])) {
                $inbound_tags[] = $inbound['tag'];
            }
        }
    }
    
    return $inbound_tags;
}

//----------------------------------
// NEW: Fetch inbound tags via /api/cores (more reliable for newer Marzban versions)
function get_inbound_tags_from_cores($location) {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url = rtrim($marzban_list_get['url_panel'], '/') . '/api/cores';
    $header_value = 'Bearer ';

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token']
    ));

    $output = curl_exec($ch);
    curl_close($ch);

    $cores_data = json_decode($output, true);
    $all_inbound_tags = array();

    if (isset($cores_data['cores']) && is_array($cores_data['cores'])) {
        foreach ($cores_data['cores'] as $core) {
            if (isset($core['config']['inbounds']) && is_array($core['config']['inbounds'])) {
                foreach ($core['config']['inbounds'] as $inbound) {
                    if (isset($inbound['tag'])) {
                        $all_inbound_tags[] = $inbound['tag'];
                    }
                }
            }
        }
    }
    $all_inbound_tags = array_values(array_unique($all_inbound_tags));
    return $all_inbound_tags;
}

//----------------------------------
// Helper: normalize groups response and return array of group objects
function _extract_groups_array($existing_groups) {
    if (!is_array($existing_groups)) {
        return array();
    }
    // شکل استاندارد پاسارگارد/مرزبان: { "groups": [ {...}, ... ], "total": N }
    foreach (array('groups', 'data', 'items', 'result') as $key) {
        if (isset($existing_groups[$key]) && is_array($existing_groups[$key])) {
            return array_values($existing_groups[$key]);
        }
    }
    // خود پاسخ یک لیست است
    $keys = array_keys($existing_groups);
    $numeric = true;
    foreach ($keys as $k) {
        if (!is_int($k) && !(is_string($k) && ctype_digit(strval($k)))) {
            $numeric = false;
            break;
        }
    }
    if ($numeric && count($existing_groups) > 0) {
        return array_values($existing_groups);
    }
    // تک‌گروه
    if (isset($existing_groups['id']) && (isset($existing_groups['name']) || isset($existing_groups['inbound_tags']))) {
        return array($existing_groups);
    }
    return array();
}

// Helper: get single group by name (case-insensitive)
function get_group_by_name($location, $group_name) {
    $existing_groups = get_groups($location);
    $groups_list = _extract_groups_array($existing_groups);
    foreach ($groups_list as $g) {
        if (isset($g['name']) && strcasecmp($g['name'], $group_name) === 0) {
            return $g;
        }
    }
    return null;
}

// Create a group in Marzban (updated to auto-populate inbound_tags like working sample)
function create_group($location, $group_name, $inbound_tags = null) {
    // Avoid duplicate creation attempt if it already exists
    $existing = get_group_by_name($location, $group_name);
    if ($existing !== null) {
        return $existing; // Return existing group instead of creating new duplicate
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $Check_token = token_panel($marzban_list_get['id']);
    $url = rtrim($marzban_list_get['url_panel'], '/') . '/api/group';
    $header_value = 'Bearer ';

    // If no inbound tags explicitly passed, try to fetch from /api/cores first, then fallback to previous method
    if ($inbound_tags === null || !is_array($inbound_tags) || count($inbound_tags) === 0) {
        $inbound_tags = get_inbound_tags_from_cores($location);
        if (count($inbound_tags) === 0) {
            // Fallback to old /api/inbounds endpoint if cores returned nothing
            $inbound_tags = get_all_inbounds($location);
        }
    }

    $data = array(
        "name" => $group_name
    );

    if (is_array($inbound_tags) && count($inbound_tags) > 0) {
        $data["inbound_tags"] = array_values($inbound_tags);
    }

    $payload = json_encode($data);

    $ch = marzban_curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value . $Check_token['access_token'],
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Basic debug logging (can be removed/adjusted)
    if ($http_code !== 200 && $http_code !== 201) {
        error_log('create_group failed: HTTP ' . $http_code . ' response: ' . $response);
    }

    return json_decode($response, true);
}

//----------------------------------
// Get all groups from Marzban
function get_groups($location) {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    if (!$marzban_list_get || !is_array($marzban_list_get)) {
        return null;
    }
    $Check_token = token_panel($marzban_list_get['id']);
    if (!is_array($Check_token) || empty($Check_token['access_token'])) {
        error_log("get_groups: token failed for {$location}: " . json_encode($Check_token));
        return null;
    }
    $base = rtrim(strval($marzban_list_get['url_panel']), '/');
    $token = $Check_token['access_token'];
    // endpoints پاسارگارد: GET /api/groups  و  GET /api/groups/simple?all=true
    $urls = array(
        $base . '/api/groups?offset=0&limit=200',
        $base . '/api/groups',
        $base . '/api/groups/simple?all=true',
        $base . '/api/groups/simple?offset=0&limit=200',
        $base . '/api/group',
    );
    foreach ($urls as $url) {
        $ch = marzban_curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ));
        $output = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($output === false || $code === 0) {
            continue;
        }
        // اگر 401 بود توکن را تازه کن و یک‌بار دیگر همین URL
        if ($code === 401) {
            update("marzban_panel", "datelogin", null, "name_panel", $location);
            $Check_token = token_panel($marzban_list_get['id']);
            if (!is_array($Check_token) || empty($Check_token['access_token'])) {
                continue;
            }
            $token = $Check_token['access_token'];
            $ch = marzban_curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ));
            $output = curl_exec($ch);
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);
        }
        if ($code >= 400 || $output === false) {
            error_log("get_groups HTTP {$code} url={$url} body=" . substr(strval($output), 0, 200));
            continue;
        }
        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            continue;
        }
        $list = _extract_groups_array($decoded);
        if (count($list) > 0) {
            return array('groups' => $list, 'total' => count($list));
        }
    }
    error_log("get_groups: no groups for panel={$location}");
    return null;
}

//----------------------------------
// Ensure default groups exist for Marzban >0.8.4 (now passes inbound_tags) (updated to avoid duplicates)

/**
 * انتخاب group_ids از گروه‌های موجود در پنل پاسارگارد/مرزبان
 * اولویت:
 * 1) inboundid عددی = همان id گروه
 * 2) inboundid متنی = نام گروه
 * 3) اکانت تست → گروهی که نامش test/تست دارد
 * 4) همه گروه‌های فعال موجود در پنل (هرچه ادمین تعریف کرده)
 */
function resolve_panel_group_ids($location, $is_test = false, $panel_row = null)
{
    if ($panel_row === null) {
        $panel_row = select("marzban_panel", "*", "name_panel", $location, "select");
    }
    $resp = get_groups($location);
    $list = _extract_groups_array($resp);
    if (!is_array($list) || count($list) === 0) {
        error_log("resolve_panel_group_ids: empty for {$location}");
        return null;
    }

    $ids_all = array();
    $by_name = array();
    foreach ($list as $g) {
        if (!is_array($g)) {
            continue;
        }
        // پشتیبانی از id به صورت عدد یا رشته
        $id = null;
        if (isset($g['id'])) {
            $id = intval($g['id']);
        } elseif (isset($g['group_id'])) {
            $id = intval($g['group_id']);
        }
        if ($id === null || $id <= 0) {
            continue;
        }
        if (!empty($g['is_disabled']) || !empty($g['disabled'])) {
            continue;
        }
        $ids_all[] = $id;
        $n = isset($g['name']) ? strtolower(trim(strval($g['name']))) : '';
        if ($n !== '') {
            $by_name[$n] = $id;
        }
    }
    $ids_all = array_values(array_unique($ids_all));
    if (count($ids_all) === 0) {
        return null;
    }

    // اگر ادمین در inboundid یک id یا نام مشخص کرده فقط همان
    if ($panel_row && isset($panel_row['inboundid'])) {
        $pref = trim(strval($panel_row['inboundid']));
        if ($pref !== '' && $pref !== '0' && $pref !== 'null') {
            if (ctype_digit($pref)) {
                return array(intval($pref));
            }
            $key = strtolower($pref);
            if (isset($by_name[$key])) {
                return array($by_name[$key]);
            }
        }
    }

    // اکانت تست: گروه تست اگر باشد
    if ($is_test) {
        foreach ($by_name as $n => $id) {
            if (strpos($n, 'test') !== false || strpos($n, 'تست') !== false) {
                return array($id);
            }
        }
    }

    // پیش‌فرض: همه گروه‌های موجود برای ادمین پنل
    return $ids_all;
}

function ensure_default_groups($location) {
    static $already_ran = array();
    if (isset($already_ran[$location])) { return array(); }
    $already_ran[$location] = true; // guard for repeated calls within same request
    try {
        if (!is_marzban_version_above_084($location)) {
            return false; // Not needed for older versions
        }

        $existing_groups_resp = get_groups($location);
        $groups_list = _extract_groups_array($existing_groups_resp);
        $group_names = array();
        foreach ($groups_list as $group) {
            if (isset($group['name'])) {
                $group_names[] = strtolower($group['name']); // case-insensitive tracking
            }
        }

        // دیگر گروه‌های mirza_paid / mirza_test ساخته نمی‌شوند.
        // گروه از پنل (گروه‌های موجود ادمین) خوانده می‌شود.
        return array();
    } catch (Exception $e) {
        error_log("Error ensuring default groups: " . $e->getMessage());
        return false;
    }
}
