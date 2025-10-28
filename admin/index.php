<?php
session_start();

// 如果使用者已登入，直接導向到主控台
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理後台登入</title>
</head>
<body>
    <h1>管理後台登入</h1>
    <form action="login.php" method="post">
        <label for="username">帳號：</label>
        <input type="text" name="username" id="username" required>
        <br><br>
        <label for="password">密碼：</label>
        <input type="password" name="password" id="password" required>
        <br><br>
        <input type="submit" value="登入">
    </form>
</body>
</html>
