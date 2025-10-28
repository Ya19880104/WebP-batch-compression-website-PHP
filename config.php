<?php
// config.php

// 管理員登入憑證
define('ADMIN_USERNAME', 'admin');
// 重要：請將 'your_secure_password' 替換為一個高強度的密碼
define('ADMIN_PASSWORD_HASH', password_hash('your_secure_password', PASSWORD_DEFAULT));

// 網站設定檔案的路徑
define('SETTINGS_FILE', __DIR__ . '/settings.json');

?>
