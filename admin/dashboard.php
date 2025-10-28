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

// 處理清除日誌的請求
if (isset($_GET['action']) && $_GET['action'] === 'clear_log' && file_exists($logFile)) {
    file_put_contents($logFile, '');
    header('Location: dashboard.php#cron-log-section');
    exit;
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 更新設定
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

    // 將更新後的設定寫回檔案
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $message = "設定已成功儲存！";
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>管理後台</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; }
        label { display: block; margin-top: 15px; }
        input[type="text"], textarea { width: 100%; padding: 8px; }
        textarea { height: 150px; }
        input[type="submit"] { margin-top: 20px; padding: 10px 20px; }
        .message { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1>歡迎來到管理後台</h1>
        <p>在這裡您可以管理網站的各項設定。</p>

        <?php if (isset($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form action="dashboard.php" method="post" enctype="multipart/form-data">
            <h2>Cloudflare Turnstile 設定</h2>
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
            <input type="submit" value="儲存設定">
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
        ?>" readonly style="background-color: #eee;">
        <p><strong>CRON 指令範例 (每 10 分鐘):</strong></p>
        <pre style="background-color: #eee; padding: 10px; border-radius: 5px;">*/10 * * * * curl --silent "<?php echo htmlspecialchars($cronUrl); ?>" > /dev/null 2>&1</pre>

        <hr style="margin: 30px 0;">

        <h2 id="cron-log-section">CRON 執行日誌</h2>
        <textarea readonly style="width: 100%; height: 300px; background-color: #eee;"><?php
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
