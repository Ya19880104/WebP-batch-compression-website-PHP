<?php
// cron.php - 用於定期清理過期的上傳檔案

// --- 設定 ---
$uploadsDir = __DIR__ . '/uploads';
$logFile = __DIR__ . '/logs/cron.log';
$maxLifetime = 300; // 5 分鐘 = 300 秒

// --- 日誌函式 ---
function write_log($message) {
    global $logFile;
    // 使用 FILE_APPEND 來附加內容，LOCK_EX 防止多人同時寫入
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- 主程式 ---
write_log("CRON Job Started.");

if (!is_dir($uploadsDir)) {
    write_log("Error: Uploads directory '{$uploadsDir}' not found. Exiting.");
    exit;
}

$sessionDirs = glob($uploadsDir . '/upload_*', GLOB_ONLYDIR);

if (empty($sessionDirs)) {
    write_log("Info: No session directories found to clean. Exiting.");
    exit;
}

write_log("Info: Found " . count($sessionDirs) . " directories to check.");
$deletedCount = 0;

foreach ($sessionDirs as $dir) {
    $timestampFile = $dir . '/timestamp.txt';
    $dirName = basename($dir);

    if (file_exists($timestampFile)) {
        $creationTime = (int)file_get_contents($timestampFile);
        $age = time() - $creationTime;

        if ($age > $maxLifetime) {
            try {
                // 遞迴刪除整個資料夾及其內容
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }

                if (rmdir($dir)) {
                    write_log("Success: Deleted expired directory '{$dirName}'. Age: {$age}s.");
                    $deletedCount++;
                } else {
                    write_log("Error: Failed to delete directory '{$dirName}' after deleting contents.");
                }

            } catch (Exception $e) {
                write_log("Error: Exception caught while deleting directory '{$dirName}': " . $e->getMessage());
            }
        } else {
            write_log("Info: Skipping directory '{$dirName}'. Age: {$age}s (not expired).");
        }
    } else {
        write_log("Warning: Skipping directory '{$dirName}' because it does not contain a timestamp.txt file.");
    }
}

write_log("CRON Job Finished. Deleted {$deletedCount} expired directories.");
echo "CRON job finished. See logs/cron.log for details.";
?>
