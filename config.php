<?php
// config.php

// 網站設定檔案的路徑
define('SETTINGS_FILE', __DIR__ . '/settings.json');

/*
 * --- 手動重設管理員密碼說明 ---
 * 如果您忘記了管理員密碼，可以透過以下步驟手動重設：
 *
 * 1. 打開 'settings.json' 檔案。
 * 2. 找到 "admin_username" 和 "admin_password_md5" 這兩個欄位。
 * 3. 將 "admin_username" 的值改為您想要的新帳號 (例如 "admin")。
 * 4. 將 "admin_password_md5" 的值改為您想要的新密碼的 MD5 雜湊值。
 *    - 例如，如果您想將密碼設回 "admin"，請將此值改為 "21232f297a57a5a743894a0e4a801fc3"。
 *    - 您可以使用任何線上的 MD5 生成器來計算您新密碼的雜湊值。
 * 5. 儲存 'settings.json' 檔案即可。
 */

?>
