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
    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: sans-serif;
            background-color: #121212;
            color: #e0e0e0;
        }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            padding: 40px;
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        h1 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 30px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #bbb;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            box-sizing: border-box;
            background-color: #2c2c2c;
            border: 1px solid #444;
            color: #e0e0e0;
            border-radius: 4px;
        }
        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>管理後台登入</h1>
        <form action="login.php" method="post">
            <label for="username">帳號：</label>
            <input type="text" name="username" id="username" required>
            <label for="password">密碼：</label>
            <input type="password" name="password" id="password" required>
            <input type="submit" value="登入">
        </form>
    </div>
</body>
</html>
