<?php
// download_zip.php
if (isset($_GET['session_id'])) {
    $sessionId = basename($_GET['session_id']); // 基本的安全性過濾
    $sessionDir = __DIR__ . '/uploads/' . $sessionId;

    if (is_dir($sessionDir)) {
        $zip = new ZipArchive();
        $zipFileName = 'webp_' . $sessionId . '.zip';
        $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = glob($sessionDir . '/*.webp');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

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
            echo "無法建立 ZIP 檔案。";
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
