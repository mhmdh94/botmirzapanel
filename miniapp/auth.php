<?php
/**
 * مینی‌اپ — بررسی امضای initData تلگرام (نسخه 2.7.0)
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 * خروجی: آرایه کاربر تلگرام یا null
 */
function miniVerifyInitData($init, $botToken, $maxAge = 86400)
{
    if (!is_string($init) || $init === '' || strlen($init) > 8192) {
        return null;
    }
    $pairs = [];
    $hash = '';
    foreach (explode('&', $init) as $chunk) {
        if ($chunk === '') {
            continue;
        }
        $kv = explode('=', $chunk, 2);
        $k = urldecode($kv[0]);
        $v = urldecode($kv[1] ?? '');
        if ($k === 'hash') {
            $hash = $v;
            continue;
        }
        $pairs[$k] = $v;
    }
    if ($hash === '' || empty($pairs)) {
        return null;
    }
    ksort($pairs, SORT_STRING);
    $lines = [];
    foreach ($pairs as $k => $v) {
        $lines[] = $k . '=' . $v;
    }
    $secret = hash_hmac('sha256', strval($botToken), 'WebAppData', true);
    $calc = hash_hmac('sha256', implode("\n", $lines), $secret);
    if (!hash_equals($calc, strtolower($hash))) {
        return null;
    }
    $authDate = intval($pairs['auth_date'] ?? 0);
    if ($authDate <= 0 || (time() - $authDate) > $maxAge) {
        return null;
    }
    $user = json_decode(strval($pairs['user'] ?? ''), true);
    if (!is_array($user) || empty($user['id'])) {
        return null;
    }
    return $user;
}
