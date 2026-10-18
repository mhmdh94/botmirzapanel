<?php
ini_set('error_log', 'error_log');
require_once 'config.php';
// پنل‌های پشتیبانی‌شده: پاسارگارد + مرزبان (منطق API در pasarguard.php)
require_once 'pasarguard.php';
require_once 'marzban.php';
// فقط پاسارگارد + مرزبان
class ManagePanel
{
    public $name_panel;
    public $connect;
    function createUser($name_panel, $usernameC, array $Data_Config, $is_test = false)
    {
        $Output = [];
        global $connect;
        // input time expire timestep use $Data_Config
        // input data_limit byte use $Data_Config
        // input username use $Data_Config
        // input from_id use $Data_Config
        // input type config use $Data_Config

        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        $expire = $Data_Config['expire'];
        $data_limit = $Data_Config['data_limit'];
        if ($Get_Data_Panel == false || !is_array($Get_Data_Panel)) {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
            $Output['username'] = null;
            return $Output;
        }
        $panel_type = strtolower(trim(strval($Get_Data_Panel['type'] ?? '')));
        // پاسارگارد همان API مرزبان
        if (in_array($panel_type, ['marzban', 'pasarguard', 'pasar_guard', 'pasarguard_panel'], true)) {
            //create user
            $ConnectToPanel = adduser($usernameC, $expire, $data_limit, $Get_Data_Panel['name_panel'], $is_test);
            $data_Output = json_decode($ConnectToPanel, true);
            if (!is_array($data_Output)) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = 'پاسخ خالی یا نامعتبر از پنل';
                $Output['username'] = null;
            } elseif (isset($data_Output['detail']) && $data_Output['detail']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['detail'];
                $Output['username'] = null;
            } elseif (empty($data_Output['username'])) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'] ?? $data_Output['message'] ?? 'ساخت کاربر ناموفق (بدون نام کاربری در پاسخ پنل)';
                $Output['username'] = null;
            } else {
                if (!isset($data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = '';
                }
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = ($data_Output['links'] ?? []);
            }
        } else {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
        }
        return $Output;
    }
    function DataUser($name_panel, $username)
    {
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        if ($Get_Data_Panel == false) {
            return array(
                'status' => 'Unsuccessful',
                'msg' => ""
            );
        }
        if ($Get_Data_Panel['type'] == "marzban" || $Get_Data_Panel['type'] == "pasarguard") {
            $UsernameData = getuser($username, $Get_Data_Panel['name_panel']);
            if (isset($UsernameData['detail']) && $UsernameData['detail']) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['detail']
                );
            } elseif (!isset($UsernameData['username'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['detail']
                );
            }
            if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
            }
            // نرمال‌سازی تاریخ انقضا برای پاسارگارد / مرزبان جدید
            if ($UsernameData['status'] == 'on_hold') {
                $UsernameData['expire'] = 0;
            } elseif (!empty($UsernameData['expire'])) {
                if (!is_numeric($UsernameData['expire'])) {
                    $parsed = strtotime($UsernameData['expire']);
                    $UsernameData['expire'] = $parsed !== false ? $parsed : 0;
                } else {
                    $UsernameData['expire'] = intval($UsernameData['expire']);
                    if ($UsernameData['expire'] > 9999999999) {
                        $UsernameData['expire'] = intval($UsernameData['expire'] / 1000);
                    }
                }
            } else {
                $UsernameData['expire'] = 0;
            }
            $links = $UsernameData['links'] ?? [];
            if ((!is_array($links) || count($links) == 0) && !empty($UsernameData['subscription_url'])) {
                $sub = $UsernameData['subscription_url'];
                // پاسارگارد / مرزبان جدید: کانفیگ‌های تکی از /links
                $try_urls = [
                    rtrim($sub, '/') . '/links',
                    rtrim($sub, '/') . '?format=links',
                    rtrim($sub, '/') . '/links_base64',
                ];
                foreach ($try_urls as $u) {
                    $body = @outputlink($u);
                    if (!$body || strlen(trim($body)) < 10) {
                        continue;
                    }
                    $body = trim($body);
                    // اگر base64 بود decode کن
                    $decoded = @base64_decode($body, true);
                    if ($decoded !== false && (strpos($decoded, 'vless://') !== false || strpos($decoded, 'vmess://') !== false || strpos($decoded, 'trojan://') !== false || strpos($decoded, 'ss://') !== false)) {
                        $body = $decoded;
                    }
                    $parts = preg_split('/\r\n|\r|\n/', $body);
                    $parsed = [];
                    foreach ($parts as $line) {
                        $line = trim($line);
                        if ($line === '') continue;
                        if (preg_match('/^(vless|vmess|trojan|ss|ssr|hysteria2?|tuic|wireguard):\/\//i', $line)) {
                            $parsed[] = $line;
                        }
                    }
                    if (count($parsed) > 0) {
                        $links = $parsed;
                        break;
                    }
                }
            }
            if (!is_array($links)) {
                $links = [];
            }
            $Output = array(
                'status' => $UsernameData['status'],
                'username' => $UsernameData['username'],
                'data_limit' => $UsernameData['data_limit'],
                'expire' => $UsernameData['expire'],
                'online_at' => $UsernameData['online_at'],
                'used_traffic' => $UsernameData['used_traffic'],
                'links' => $links,
                'subscription_url' => $UsernameData['subscription_url'],
            );
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Revoke_sub($name_panel, $username)
    {
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        if ($Get_Data_Panel['type'] == "marzban" || $Get_Data_Panel['type'] == "pasarguard") {
            $revoke_sub = revoke_sub($username, $name_panel);
            if (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } else {
                $config = new ManagePanel();
                $Data_User = $config->DataUser($name_panel, $username);
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $Data_User['subscription_url'])) {
                    $Data_User['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($Data_User['subscription_url'], "/");
                }
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'],
                    'subscription_url' => $Data_User['subscription_url']
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function RemoveUser($name_panel, $username)
    {
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        if ($Get_Data_Panel['type'] == "marzban" || $Get_Data_Panel['type'] == "pasarguard") {
            $UsernameData = removeuser($Get_Data_Panel['name_panel'], $username);
            if (isset($UsernameData['detail']) && $UsernameData['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['detail']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function ResetUserDataUsage($name_panel, $username)
    {
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        if ($Get_Data_Panel['type'] == "marzban" || $Get_Data_Panel['type'] == "pasarguard") {
            ResetUserDataUsage($username, $name_panel);
        }
    }
    function Modifyuser($username, $name_panel, $config = array())
    {
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel, "select");
        if ($Get_Data_Panel['type'] == "marzban" || $Get_Data_Panel['type'] == "pasarguard") {
            Modifyuser($name_panel, $username, $config);
        }

    }
}