<?php
// admin/auth_check.php - 共用的身份驗證與 Session 逾時檢查

// 確保 session 在所有需要驗證的頁面都被啟動
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 檢查使用者是否已登入，否則重新導向到登入頁面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

// --- Session 逾時檢查 (1 小時) ---
$timeout = 3600; // 1 小時 = 3600 秒
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    // Session 過期，銷毀並導向回登入頁面
    session_unset();
    session_destroy();
    header('Location: index.php?reason=session_expired');
    exit;
}

// 更新活動時間，以實現活動續期
$_SESSION['last_activity'] = time();
