<?php
// config.php

// 網站設定檔案的路徑
define('SETTINGS_FILE', __DIR__ . '/settings.php');

/*
 * --- 手動重設管理員密碼說明 ---
 * 如果您忘記了管理員密碼，可以透過以下步驟手動重設。
 * 警告：直接編輯設定檔具有風險，請謹慎操作。
 *
 * 1. 建立一個暫時的 PHP 檔案 (例如 `generate_hash.php`)，並在其中貼上以下內容：
 *    <?php
 *    echo password_hash('您想設定的新密碼', PASSWORD_DEFAULT);
 *    ?>
 *
 * 2. 在瀏覽器中執行這個檔案，您會看到一長串的雜湊值，例如：
 *    $2y$10$abcdefghijklmnopqrstuvwxyz...
 *
 * 3. 複製這串完整的雜湊值。
 *
 * 4. 打開 `settings.php` 檔案。
 *
 * 5. 找到 `"admin_password_hash"` 這一行。如果不存在，您可以手動新增。
 *    (如果存在舊的 `"admin_password_md5"`，請將其刪除或改名為 `"admin_password_hash"`)
 *
 * 6. 將您剛剛複製的雜湊值貼到 `"admin_password_hash"` 的值中，例如：
 *    "admin_password_hash": "$2y$10$abcdefghijklmnopqrstuvwxyz..."
 *
 * 7. 儲存 `settings.php` 檔案。
 *
 * 8. 為了安全，請務必刪除您剛剛建立的 `generate_hash.php` 檔案。
 */

?>
