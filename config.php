<?php
/*
channel => @mirzapanel
*/
//-----------------------------database-------------------------------
$dbname = '{DATABASE_NAME}'; //  Name Database
$usernamedb = '{DATABASE_USERNAME}'; // Username Database
$passworddb = '{DATABASE_PASSOWRD}'; // Password Database
// در PHP 8.1+ اتصال ناموفق Exception میدهد؛ بررسی connect_error قبلی هرگز اجرا نمیشد.
try {
    $connect = mysqli_connect("localhost", $usernamedb, $passworddb, $dbname);
} catch (\mysqli_sql_exception $e) {
    $connect = false;
}
if (!$connect) {
    error_log('DB connection failed (mysqli): ' . mysqli_connect_error());
    http_response_code(503); // تلگرام بعداً دوباره میفرستد
    exit;
}
mysqli_set_charset($connect, "utf8mb4");

//-----------------------------info-------------------------------

$APIKEY = "{BOT_TOKEN}"; // Token Bot of Botfather
$adminnumber = "{ADMIN_#ID}";// Id Number Admin
$domainhosts = "{DOMAIN.COM/PATH/BOT}";// Domain Host and Path of Bot without trailing /
$usernamebot = "{BOT_USERNAME}"; // Username Bot without @
$secrettoken = '{SECRET_TOKEN}'; // Webhook secret_token (set by installer)

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$dsn = "mysql:host=localhost;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $usernamedb, $passworddb, $options);
} catch (\PDOException $e) {
    error_log('DB connection failed (PDO): ' . $e->getMessage());
    http_response_code(503);
    exit;
}
