<?php
session_start();
require_once __DIR__ . '/../config.php';

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // 驗證使用者名稱和密碼
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
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
