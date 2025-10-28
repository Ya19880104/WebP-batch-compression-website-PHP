<?php
session_start();
require_once 'config.php';

// --- PHP 設定 ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// --- 讀取設定檔 ---
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);

// --- Cloudflare Turnstile 驗證 ---
$secretKey = $settings['cloudflare_turnstile_secret_key'];
$response = ['success' => false, 'message' => '驗證失敗，請重試。'];
if (isset($_POST['cf-turnstile-response']) && !empty($secretKey)) {
    // ... (Turnstile 驗證邏輯省略以保持簡潔)
}

// --- 處理檔案上傳 ---
if (isset($_FILES['images'])) {
    $sessionId = uniqid('upload_');
    $sessionDir = __DIR__ . '/uploads/' . $sessionId;
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }
    file_put_contents($sessionDir . '/timestamp.txt', time());

    $uploadedFiles = [];
    $errors = [];
    $totalFiles = count($_FILES['images']['name']);
    $resizeMode = $_POST['resize'] ?? 'none';

    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = $_FILES['images']['name'][$i];
        $fileTmpName = $_FILES['images']['tmp_name'][$i];
        $fileError = $_FILES['images']['error'][$i];

        if ($fileError !== UPLOAD_ERR_OK) continue;

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $baseFileName = pathinfo($fileName, PATHINFO_FILENAME);
        $originalImagePath = $sessionDir . '/' . $fileName;
        $webpImagePath = $sessionDir . '/' . $baseFileName . '.webp';

        if (move_uploaded_file($fileTmpName, $originalImagePath)) {
            $image = null;
            if ($fileExtension == 'jpg' || $fileExtension == 'jpeg') {
                $image = imagecreatefromjpeg($originalImagePath);
            } elseif ($fileExtension == 'png') {
                $image = imagecreatefrompng($originalImagePath);
            }

            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);

                // --- 核心圖片處理邏輯 ---
                if ($resizeMode !== 'none') {
                    if ($resizeMode === '600_crop') {
                        // --- 置中裁切 ---
                        $cropSize = min($width, $height);
                        $cropX = ($width > $cropSize) ? ($width - $cropSize) / 2 : 0;
                        $cropY = ($height > $cropSize) ? ($height - $cropSize) / 2 : 0;

                        $croppedImage = imagecrop($image, ['x' => $cropX, 'y' => $cropY, 'width' => $cropSize, 'height' => $cropSize]);
                        if ($croppedImage !== FALSE) {
                            imagedestroy($image);
                            $image = $croppedImage;
                            // 更新尺寸以便後續縮放
                            $width = $cropSize;
                            $height = $cropSize;
                        }
                        $targetSize = 600;
                    } else {
                        // --- 等比例縮放 ---
                        $targetSize = (int)$resizeMode;
                    }

                    if (($targetSize > 0) && ($width > $targetSize || $height > $targetSize || $resizeMode === '600_crop')) {
                         $ratio = $width / $height;
                         if ($ratio > 1) { // 橫向
                             $newWidth = $targetSize;
                             $newHeight = $targetSize / $ratio;
                         } else { // 直向或方形
                             $newHeight = $targetSize;
                             $newWidth = $targetSize * $ratio;
                         }
                         if($resizeMode === '600_crop'){ // 裁切後強制為正方形
                             $newWidth = $targetSize;
                             $newHeight = $targetSize;
                         }

                         $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                         if ($fileExtension == 'png') {
                             imagealphablending($resizedImage, false);
                             imagesavealpha($resizedImage, true);
                             $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                             imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
                         }
                         imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                         imagedestroy($image);
                         $image = $resizedImage;
                    }
                }

                // --- 儲存為 WebP ---
                if ($fileExtension == 'png') {
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                if (imagewebp($image, $webpImagePath, 80)) {
                    $uploadedFiles[] = [
                        'original' => $fileName,
                        'webp' => $baseFileName . '.webp',
                        'webp_url' => 'uploads/' . $sessionId . '/' . $baseFileName . '.webp',
                        'thumbnail_url' => 'uploads/' . $sessionId . '/' . $baseFileName . '.webp' // 使用 webp 作為縮圖
                    ];
                }
                imagedestroy($image);
            }
        }
    }
    echo json_encode(['success' => true, 'files' => $uploadedFiles, 'sessionId' => $sessionId]);
} else {
    echo json_encode(['success' => false, 'message' => '沒有收到任何檔案。']);
}
?>
