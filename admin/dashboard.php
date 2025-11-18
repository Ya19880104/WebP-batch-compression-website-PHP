<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config.php';

// 讀取目前的設定
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);

$logsDir = __DIR__ . '/../logs';
$logFile = $logsDir . '/cron.log';

// 確保日誌目錄存在
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

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

// 處理移除圖片的請求
if (isset($_GET['action']) && $_GET['action'] === 'remove_image' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $img_dir = __DIR__ . '/../img';
    $setting_key_image = null;
    $setting_key_link = null;
    $baseName = null;

    if ($type === 'banner_top') {
        $setting_key_image = 'banner_top_image';
        $setting_key_link = 'banner_top_link';
        $baseName = 'banner_top';
    } elseif ($type === 'banner_bottom') {
        $setting_key_image = 'banner_bottom_image';
        $setting_key_link = 'banner_bottom_link';
        $baseName = 'banner_bottom';
    } elseif ($type === 'og_image') {
        $setting_key_image = 'seo_og_image';
        $baseName = 'og_image';
    }

    if ($baseName) {
        // 刪除實體檔案
        $files = glob($img_dir . '/' . $baseName . '.*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // 更新設定
        $settings[$setting_key_image] = '';
        if (isset($settings[$setting_key_link])) {
            $settings[$setting_key_link] = '';
        }

        $newSettingsContent = '<?php die(); ?>' . json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(SETTINGS_FILE, $newSettingsContent);

        // 重新導向回儀表板
        header('Location: dashboard.php');
        exit;
    }
}


/**
 * 處理圖片上傳的輔助函數
 * @param array $file 從 $_FILES 傳遞過來的檔案資訊 (例如 $_FILES['logo_file'])
 * @param string $baseName 儲存檔案時的基本名稱 (例如 'logo')
 * @param string $settingsKey 要更新的設定檔中的鍵名 (例如 'logo_url')
 * @param array &$settings 包含所有設定的陣列 (以引用方式傳遞)
 * @param string $imgDir 圖片儲存的目錄路徑
 */
function handle_image_upload($file, $baseName, $settingsKey, &$settings, $imgDir) {
    if (isset($file) && $file['error'] == UPLOAD_ERR_OK) {
        // 取得副檔名
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $allowedExtensions)) {
            // 刪除同名的舊檔案 (不同副檔名)
            $oldFiles = glob($imgDir . '/' . $baseName . '.*');
            foreach ($oldFiles as $oldFile) {
                if (is_file($oldFile)) {
                    unlink($oldFile);
                }
            }

            // 建立新的檔案路徑
            $newFileName = $baseName . '.' . $extension;
            $uploadPath = $imgDir . '/' . $newFileName;

            // 移動檔案並更新設定
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $settings[$settingsKey] = 'img/' . $newFileName . '?v=' . time();
            }
        }
    }
}

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 處理使用者管理表單 ---
    if (isset($_POST['update_credentials'])) {
        $newUsername = $_POST['admin_username'] ?? $settings['admin_username'];
        $newPassword = $_POST['admin_password'] ?? '';

        $settings['admin_username'] = $newUsername;
        if (!empty($newPassword)) {
            $settings['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            unset($settings['admin_password_md5']);
        }

        // 儲存設定並設定成功訊息
        $newSettingsContent = '<?php die(); ?>' . json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(SETTINGS_FILE, $newSettingsContent);
        $message = "使用者憑證已成功更新！";
    }

    // --- 處理主要設定表單 ---
    if (isset($_POST['update_settings'])) {
        // 對每個欄位使用 ?? 運算子，如果 POST 中不存在該值，則保留原有的設定值
        $settings['cloudflare_turnstile_site_key'] = $_POST['cloudflare_turnstile_site_key'] ?? $settings['cloudflare_turnstile_site_key'];
        $settings['cloudflare_turnstile_secret_key'] = $_POST['cloudflare_turnstile_secret_key'] ?? $settings['cloudflare_turnstile_secret_key'];

        $settings['seo_title'] = $_POST['seo_title'] ?? $settings['seo_title'];
        $settings['seo_description'] = $_POST['seo_description'] ?? $settings['seo_description'];

        $settings['logo_link'] = $_POST['logo_link'] ?? $settings['logo_link'];

        $settings['banner_top_link'] = $_POST['banner_top_link'] ?? $settings['banner_top_link'];
        $settings['banner_bottom_link'] = $_POST['banner_bottom_link'] ?? $settings['banner_bottom_link'];

        $settings['footer_html'] = $_POST['footer_html'] ?? $settings['footer_html'];

        $img_dir = __DIR__ . '/../img';

        // 處理圖片上傳
        handle_image_upload($_FILES['logo_file'], 'logo', 'logo_url', $settings, $img_dir);
        handle_image_upload($_FILES['banner_top_file'], 'banner_top', 'banner_top_image', $settings, $img_dir);
        handle_image_upload($_FILES['banner_bottom_file'], 'banner_bottom', 'banner_bottom_image', $settings, $img_dir);
        handle_image_upload($_FILES['seo_og_image_file'], 'og_image', 'seo_og_image', $settings, $img_dir);

        // 儲存設定並設定成功訊息
        $newSettingsContent = '<?php die(); ?>' . json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(SETTINGS_FILE, $newSettingsContent);
        $message = "設定已成功儲存！";
    }
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
        .footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 20px; /* 與上方 container 的間距 */
            font-size: 0.9em;
            color: #888;
        }
        .footer a {
            color: #888;
            text-decoration: none;
        }
        .footer a:hover {
            color: #bbb;
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
        <p style="font-size: 0.8em; color: #888; margin-top: 10px;">安全建議：為了加強伺服器層級的安全性，建議將網站根目錄下的 `settings.php` 檔案權限設定為 `640`。</p>
        <hr>

        <form action="dashboard.php" method="post" enctype="multipart/form-data">
            <h2>網站設定</h2>
            <h3>Cloudflare Turnstile 設定</h3>
            <label for="cloudflare_turnstile_site_key">Site Key:</label>
            <input type="text" id="cloudflare_turnstile_site_key" name="cloudflare_turnstile_site_key" value="<?php echo htmlspecialchars($settings['cloudflare_turnstile_site_key']); ?>">

            <label for="cloudflare_turnstile_secret_key">Secret Key:</label>
            <input type="text" id="cloudflare_turnstile_secret_key" name="cloudflare_turnstile_secret_key" value="<?php echo htmlspecialchars($settings['cloudflare_turnstile_secret_key']); ?>">

            <h2>SEO 設定</h2>
            <label for="seo_title">網站標題 (Title):</label>
            <input type="text" id="seo_title" name="seo_title" value="<?php echo htmlspecialchars($settings['seo_title']); ?>">

            <label for="seo_description">網站描述 (Description):</label>
            <textarea id="seo_description" name="seo_description" style="height: 80px;"><?php echo htmlspecialchars($settings['seo_description']); ?></textarea>

            <label for="seo_og_image_file" style="margin-top: 25px;">OG Image (建議 1200x630px):</label>
            <input type="file" id="seo_og_image_file" name="seo_og_image_file" accept="image/jpeg,image/png,image/webp">
            <p style="font-size: 0.8em; color: #888;">
                建議使用英文檔案名稱。目前圖片路徑: <?php echo htmlspecialchars($settings['seo_og_image']); ?>
                <?php if (!empty($settings['seo_og_image'])): ?>
                    <a href="?action=remove_image&type=og_image" onclick="return confirm('您確定要移除 OG Image 嗎？');" style="color: #ff4d4d; margin-left: 10px;">移除圖片</a>
                <?php endif; ?>
            </p>

            <h2>LOGO 設定</h2>
            <label for="logo_link">LOGO 超連結:</label>
            <input type="text" id="logo_link" name="logo_link" value="<?php echo htmlspecialchars($settings['logo_link']); ?>">

            <label for="logo_file">上傳新的 LOGO (支援 JPG, PNG, WEBP):</label>
            <input type="file" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/webp">
            <p style="font-size: 0.8em; color: #888;">目前 LOGO 路徑: <?php echo htmlspecialchars($settings['logo_url']); ?></p>

            <h2>廣告 Banner 設定</h2>
            <label for="banner_top_file">上方廣告 Banner (支援 JPG, PNG, WEBP):</label>
            <input type="file" id="banner_top_file" name="banner_top_file" accept="image/jpeg,image/png,image/webp">
            <p style="font-size: 0.8em; color: #888;">目前圖片路徑: <?php echo htmlspecialchars($settings['banner_top_image']); ?>
                <?php if (!empty($settings['banner_top_image'])): ?>
                    <a href="?action=remove_image&type=banner_top" onclick="return confirm('您確定要移除上方廣告 Banner 嗎？');" style="color: #ff4d4d; margin-left: 10px;">移除圖片</a>
                <?php endif; ?>
            </p>
            <label for="banner_top_link">上方廣告 Banner 超連結:</label>
            <input type="text" id="banner_top_link" name="banner_top_link" value="<?php echo htmlspecialchars($settings['banner_top_link']); ?>">

            <label for="banner_bottom_file" style="margin-top: 25px;">下方廣告 Banner (支援 JPG, PNG, WEBP):</label>
            <input type="file" id="banner_bottom_file" name="banner_bottom_file" accept="image/jpeg,image/png,image/webp">
            <p style="font-size: 0.8em; color: #888;">目前圖片路徑: <?php echo htmlspecialchars($settings['banner_bottom_image']); ?>
                <?php if (!empty($settings['banner_bottom_image'])): ?>
                    <a href="?action=remove_image&type=banner_bottom" onclick="return confirm('您確定要移除下方廣告 Banner 嗎？');" style="color: #ff4d4d; margin-left: 10px;">移除圖片</a>
                <?php endif; ?>
            </p>
            <label for="banner_bottom_link">下方廣告 Banner 超連結:</label>
            <input type="text" id="banner_bottom_link" name="banner_bottom_link" value="<?php echo htmlspecialchars($settings['banner_bottom_link']); ?>">

            <h2>Footer 設定</h2>
            <label for="footer_html">Footer HTML:</label>
            <textarea id="footer_html" name="footer_html" style="height: 120px;"><?php echo htmlspecialchars($settings['footer_html']); ?></textarea>

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

    <div class="footer">
        <p>本系統由<a href="https://yangsheep.com.tw" target="_blank">羊羊數位科技有限公司</a>開發</p>
    </div>
</body>
</html>
