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
$turnstile_verified = false;
$secretKey = $settings['cloudflare_turnstile_secret_key'];

// 如果沒有設定 Secret Key，則直接跳過驗證
if (empty($secretKey)) {
    $turnstile_verified = true;
}
// 如果設定了 Secret Key，但前端沒有回傳 token，則視為失敗
elseif (!isset($_POST['cf-turnstile-response'])) {
    $turnstile_verified = false;
    $response = ['success' => false, 'message' => '缺少 Turnstile Token，請重新整理頁面。'];
    echo json_encode($response);
    exit;
}
// 執行驗證
else {
    $token = $_POST['cf-turnstile-response'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['secret' => $secretKey, 'response' => $token, 'remoteip' => $ip]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $api_response = curl_exec($ch);
    curl_close($ch);

    $api_data = json_decode($api_response, true);
    if (isset($api_data['success']) && $api_data['success']) {
        $turnstile_verified = true;
    } else {
        $response = ['success' => false, 'message' => 'Cloudflare Turnstile 驗證失敗。'];
        echo json_encode($response);
        exit;
    }
}


// --- 處理檔案上傳 ---
if ($turnstile_verified && isset($_FILES['images'])) {
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
                        // --- 600px 置中裁切 (正方形) ---
                        $cropSize = min($width, $height);
                        $cropX = ($width - $cropSize) / 2;
                        $cropY = ($height - $cropSize) / 2;

                        $croppedImage = imagecrop($image, ['x' => $cropX, 'y' => $cropY, 'width' => $cropSize, 'height' => $cropSize]);
                        if ($croppedImage !== FALSE) {
                            imagedestroy($image);
                            $image = $croppedImage;
                            $width = $height = $cropSize; // 更新尺寸
                        }

                        // 縮放到 600x600
                        $resizedImage = imagecreatetruecolor(600, 600);
                        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, 600, 600, $width, $height);
                        imagedestroy($image);
                        $image = $resizedImage;

                    } elseif ($resizeMode === '1200x630_crop') {
                        // --- 1200x630 置中裁切 (社群媒體) ---
                        $targetWidth = 1200;
                        $targetHeight = 630;
                        $targetRatio = $targetWidth / $targetHeight;
                        $originalRatio = $width / $height;

                        if ($originalRatio > $targetRatio) {
                            // 原始圖片比較寬，以高度為基準縮放
                            $newHeight = $targetHeight;
                            $newWidth = $newHeight * $originalRatio;
                        } else {
                            // 原始圖片比較高或比例相同，以寬度為基準縮放
                            $newWidth = $targetWidth;
                            $newHeight = $newWidth / $originalRatio;
                        }

                        // 先縮放以完全覆蓋目標尺寸
                        $scaledImage = imagecreatetruecolor($newWidth, $newHeight);
                        imagecopyresampled($scaledImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                        // 計算置中裁切的起始點
                        $cropX = ($newWidth - $targetWidth) / 2;
                        $cropY = ($newHeight - $targetHeight) / 2;

                        // 進行裁切
                        $croppedImage = imagecrop($scaledImage, ['x' => $cropX, 'y' => $cropY, 'width' => $targetWidth, 'height' => $targetHeight]);
                        if ($croppedImage !== FALSE) {
                            imagedestroy($image);
                            imagedestroy($scaledImage);
                            $image = $croppedImage;
                        }

                    } else {
                        // --- 一般等比例縮放 ---
                        $targetSize = (int)$resizeMode;
                        if (($targetSize > 0) && ($width > $targetSize || $height > $targetSize)) {
                             $ratio = $width / $height;
                             if ($ratio > 1) { // 橫向
                                 $newWidth = $targetSize;
                                 $newHeight = $targetSize / $ratio;
                             } else { // 直向或方形
                                 $newHeight = $targetSize;
                                 $newWidth = $targetSize * $ratio;
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
