<?php
session_start();

// 清除所有 session 變數
$_SESSION = [];

// 銷毀 session
session_destroy();

// 重新導向到登入頁面
header('Location: index.php');
exit;
?>
