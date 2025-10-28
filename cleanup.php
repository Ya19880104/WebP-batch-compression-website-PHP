<?php
// cleanup.php - 接收 beacon 請求以刪除 session 資料夾

// 忽略使用者中斷連線，確保腳本能完整執行
ignore_user_abort(true);

// 從請求的 body 中讀取原始資料
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['session_id'])) {
    $sessionId = basename($data['session_id']); // 基本的安全性過濾
    $sessionDir = __DIR__ . '/uploads/' . $sessionId;

    if (is_dir($sessionDir)) {
        // 遞迴刪除整個資料夾及其內容
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }
}
?>
