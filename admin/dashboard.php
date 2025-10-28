<?php
session_start();
require_once __DIR__ . '/../config.php';

// 檢查使用者是否已登入，否則重新導向到登入頁面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

// 讀取目前的設定
$settings = json_decode(file_get_contents(SETTINGS_FILE), true);
$logFile = __DIR__ . '/../logs/cron.log';

// 檢查日誌檔案是否存在，不存在則建立
if (!file_exists($logFile)) {
    // 嘗試建立檔案，並加上錯誤抑制符 @，因為我們會在後面檢查
    @file_put_contents($logFile, '');
    // 設定權限，確保網頁伺服器和 CRON 使用者都能寫入
    @chmod($logFile, 0666);
}

// 處理清除日誌的請求
if (isset($_GET['action']) && $_GET['action'] === 'clear_log' && file_exists($logFile)) {
    file_put_contents($logFile, '');
    header('Location: dashboard.php#cron-log-section');
    exit;
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 處理使用者管理表單 ---
    if (isset($_POST['update_credentials'])) {
        $newUsername = $_POST['admin_username'] ?? 'admin';
        $newPassword = $_POST['admin_password'] ?? '';

        $settings['admin_username'] = $newUsername;
        if (!empty($newPassword)) {
            $settings['admin_password_md5'] = md5($newPassword);
        }
        $message = "使用者憑證已成功更新！";

    // --- 處理主要設定表單 ---
    } elseif (isset($_POST['update_settings'])) {
        $settings['cloudflare_turnstile_site_key'] = $_POST['cloudflare_turnstile_site_key'] ?? '';
        $settings['cloudflare_turnstile_secret_key'] = $_POST['cloudflare_turnstile_secret_key'] ?? '';
    $settings['logo_link'] = $_POST['logo_link'] ?? '';
    $settings['footer_html'] = $_POST['footer_html'] ?? '';

    // 處理 LOGO 上傳
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == UPLOAD_ERR_OK) {
        // 為避免瀏覽器快取，使用時間戳記作為檔名
        $newLogoName = 'logo.png?v=' . time();
        $logoUploadPath = __DIR__ . '/../logo.png';
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $logoUploadPath)) {
            $settings['logo_url'] = $newLogoName;
        }
    }
        $message = "設定已成功儲存！";
    }

    // 在處理完所有 POST 請求後，統一寫入檔案
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理後台</title>
    <style>
        body {
            font-family: sans-serif;
            line-height: 1.6;
            background-color: #121212;
            color: #e0e0e0;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
        }
        label { display: block; margin-top: 15px; color: #bbb; }
        input[type="text"], input[type="password"], textarea {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            background-color: #2c2c2c;
            border: 1px solid #444;
            color: #e0e0e0;
            border-radius: 4px;
        }
        textarea { height: 150px; }
        input[type="submit"] {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background-color: #0056b3; }
        .message { color: #28a745; background-color: rgba(40, 167, 69, 0.1); padding: 10px; border-radius: 4px; }
        h1, h2, h3 { color: #ffffff; }
        h2 { border-bottom: 1px solid #444; padding-bottom: 10px; }
        h3 { margin-top: 25px; }
        pre { background-color: #2c2c2c; padding: 10px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #444; }
        hr { border: none; border-top: 1px solid #444; margin: 30px 0; }
        a { color: #0d8eff; }
        a:hover { color: #4fa9ff; }
        textarea[readonly], input[readonly] {
            background-color: #2c2c2c;
            cursor: default;
        }
        #cron-log-section + textarea {
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>歡迎來到管理後台</h1>
        <p>在這裡您可以管理網站的各項設定。</p>

        <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <hr>
        <h2>使用者管理</h2>
        <form action="dashboard.php" method="post">
            <label for="admin_username">管理員帳號:</label>
            <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($settings['admin_username']); ?>" required>

            <label for="admin_password">新密碼 (留空則不修改):</label>
            <input type="password" id="admin_password" name="admin_password">

            <br>
            <input type="submit" name="update_credentials" value="更新憑證">
        </form>
        <hr>

        <form action="dashboard.php" method="post" enctype="multipart/form-data">
            <h2>網站設定</h2>
            <h3>Cloudflare Turnstile 設定</h3>
            <label for="cloudflare_turnstile_site_key">Site Key:</label>
            <input type="text" id="cloudflare_turnstile_site_key" name="cloudflare_turnstile_site_key" value="<?php echo htmlspecialchars($settings['cloudflare_turnstile_site_key']); ?>">

            <label for="cloudflare_turnstile_secret_key">Secret Key:</label>
            <input type="text" id="cloudflare_turnstile_secret_key" name="cloudflare_turnstile_secret_key" value="<?php echo htmlspecialchars($settings['cloudflare_turnstile_secret_key']); ?>">

            <h2>LOGO 設定</h2>
            <label for="logo_link">LOGO 超連結:</label>
            <input type="text" id="logo_link" name="logo_link" value="<?php echo htmlspecialchars($settings['logo_link']); ?>">

            <label for="logo_file">上傳新的 LOGO (只接受.png):</label>
            <input type="file" id="logo_file" name="logo_file" accept="image/png">

            <h2>Footer 設定</h2>
            <label for="footer_html">Footer HTML 內容:</label>
            <textarea id="footer_html" name="footer_html"><?php echo htmlspecialchars($settings['footer_html']); ?></textarea>

            <br>
            <input type="submit" name="update_settings" value="儲存設定">
        </form>

        <hr style="margin: 30px 0;">

        <h2>CRON 排程設定</h2>
        <p>為了自動刪除過期的上傳檔案，您需要在您的伺服器上設定一個 CRON 工作，來定期觸發以下的網址。</p>
        <p>建議的執行頻率是每 5 到 15 分鐘一次。</p>
        <p><strong>觸發網址:</strong></p>
        <input type="text" value="<?php
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $domain = $_SERVER['HTTP_HOST'];
            $scriptPath = str_replace(basename(__FILE__), '', $_SERVER['SCRIPT_NAME']);
            $cronUrl = $protocol . $domain . str_replace('/admin/', '/', $scriptPath) . 'cron.php';
            echo htmlspecialchars($cronUrl);
        ?>" readonly>
        <p><strong>CRON 指令範例 (每 10 分鐘):</strong></p>
        <pre>*/10 * * * * curl --silent "<?php echo htmlspecialchars($cronUrl); ?>" > /dev/null 2>&1</pre>

        <hr style="margin: 30px 0;">

        <h2 id="cron-log-section">CRON 執行日誌</h2>
        <textarea readonly><?php
            if (file_exists($logFile)) {
                echo htmlspecialchars(file_get_contents($logFile));
            } else {
                echo "日誌檔案不存在。";
            }
        ?></textarea>
        <a href="dashboard.php?action=clear_log" onclick="return confirm('您確定要清除所有日誌嗎？');" style="display: inline-block; margin-top: 10px;">清除日誌</a>

        <br><br>
        <a href="logout.php">登出</a>
    </div>
</body>
</html>
