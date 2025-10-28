<?php
// cron.php - 用於定期清理過期的上傳檔案

// 上傳目錄的路徑
$uploadsDir = __DIR__ . '/uploads';

// 檔案最長保留時間（1 小時 = 3600 秒）
$maxLifetime = 3600;

echo "CRON Job started at " . date('Y-m-d H:i:s') . "\n";

if (!is_dir($uploadsDir)) {
    echo "Uploads directory not found. Exiting.\n";
    exit;
}

// 掃描 uploads 目錄下的所有 session 資料夾
$sessionDirs = glob($uploadsDir . '/upload_*', GLOB_ONLYDIR);

if (empty($sessionDirs)) {
    echo "No session directories to clean. Exiting.\n";
    exit;
}

$deletedCount = 0;

foreach ($sessionDirs as $dir) {
    $timestampFile = $dir . '/timestamp.txt';

    if (file_exists($timestampFile)) {
        $creationTime = (int)file_get_contents($timestampFile);
        $age = time() - $creationTime;

        if ($age > $maxLifetime) {
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

            echo "Deleted expired directory: " . basename($dir) . "\n";
            $deletedCount++;
        }
    } else {
        // 如果沒有 timestamp 檔案，但資料夾存在超過一段時間，也刪除它（可選策略）
        // 為避免誤刪，目前只處理有 timestamp 的資料夾
        echo "Skipping directory without timestamp: " . basename($dir) . "\n";
    }
}

echo "CRON Job finished. Deleted {$deletedCount} expired directories.\n";
?>
