<?php
session_start();
require_once __DIR__ . '/../config.php';

// 讀取並解析設定檔
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);

$adminUsername = $settings['admin_username'];
$adminPasswordHash = $settings['admin_password_hash'] ?? null;
$adminPasswordMd5 = $settings['admin_password_md5'] ?? null; // For fallback

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $loginSuccess = false;

    // 優先使用 password_verify (更安全)
    if (isset($adminPasswordHash)) {
        if ($username === $adminUsername && password_verify($password, $adminPasswordHash)) {
            $loginSuccess = true;
        }
    // 如果 password_hash 不存在，則回退到舊的 MD5 驗證 (用於過渡期)
    } elseif (isset($adminPasswordMd5)) {
        if ($username === $adminUsername && md5($password) === $adminPasswordMd5) {
            $loginSuccess = true;
        }
    }

    if ($loginSuccess) {
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
