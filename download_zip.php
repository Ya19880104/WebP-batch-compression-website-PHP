<?php
// download_zip.php
if (isset($_GET['session_id'])) {
    $sessionId = basename($_GET['session_id']); // 基本的安全性過濾
    $sessionDir = __DIR__ . '/uploads/' . $sessionId;

    if (is_dir($sessionDir)) {
        $zip = new ZipArchive();
        $zipFileName = 'webp_' . $sessionId . '.zip';
        $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

        $files = glob($sessionDir . '/*.webp');

        // 檢查是否有任何 webp 檔案可以壓縮
        if (empty($files)) {
            http_response_code(404);
            // 顯示一個對使用者友善的錯誤頁面
            echo '<!DOCTYPE html><html lang="zh-TW"><head><meta charset="UTF-8"><title>錯誤</title>';
            echo '<style>body { font-family: sans-serif; text-align: center; padding: 50px; } h1 { color: #d9534f; } .btn { display: inline-block; padding: 10px 20px; margin-top: 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }</style>';
            echo '</head><body>';
            echo '<h1>下載失敗</h1>';
            echo '<p>找不到任何可供下載的轉換後檔案。這可能是因為檔案已過期並被系統清理。</p>';
            echo '<a href="/" class="btn">返回首頁</a>';
            echo '</body></html>';
            exit;
        }

        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // 再次檢查 zip 檔案是否成功建立
            if (file_exists($zipFilePath)) {
                // 提供下載
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                header('Content-Length: ' . filesize($zipFilePath));
                readfile($zipFilePath);

                // 刪除暫存的 zip 檔案
                unlink($zipFilePath);
                exit;
            } else {
                 http_response_code(500);
                 echo "建立 ZIP 檔案失敗，無法提供下載。";
            }
        } else {
            http_response_code(500);
            echo "無法開啟 ZIP 檔案進行寫入。";
        }
    } else {
        http_response_code(404);
        echo "找不到指定的上傳 session。";
    }
} else {
    http_response_code(400);
    echo "缺少 session ID。";
}
?>
