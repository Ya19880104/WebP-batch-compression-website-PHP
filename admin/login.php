<?php
session_start();
require_once __DIR__ . '/../config.php';

// 讀取並解析設定檔
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);

$adminUsername = $settings['admin_username'];
$adminPasswordMd5 = $settings['admin_password_md5'];

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 驗證使用者名稱和密碼 (將提交的密碼轉為 MD5 後比對)
    if ($username === $adminUsername && md5($password) === $adminPasswordMd5) {
        // 登入成功，設定 session
        $_SESSION['loggedin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        // 登入失敗
        echo "<h1>登入失敗</h1>";
        echo "<p>帳號或密碼錯誤。</p>";
        echo "<a href='index.php'>返回登入頁面</a>";
    }
} else {
    // 如果不是 POST 請求，則導向回登入頁面
    header('Location: index.php');
    exit;
}
?>
