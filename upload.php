<?php
session_start();
require_once 'config.php';

// 設定 PHP 選項
error_reporting(E_ALL);
ini_set('display_errors', 0); // 在生產環境中應設為 0，錯誤記錄到檔案
header('Content-Type: application/json');

// --- Cloudflare Turnstile 驗證 ---
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);

$secretKey = $settings['cloudflare_turnstile_secret_key'];
$response = ['success' => false, 'message' => '驗證失敗，請重試。'];

if (isset($_POST['cf-turnstile-response']) && !empty($secretKey)) {
    $turnstileResponse = $_POST['cf-turnstile-response'];
    $ip = $_SERVER['REMOTE_ADDR'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => $turnstileResponse,
        'remoteip' => $ip
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($result['success']) || !$result['success']) {
        echo json_encode($response);
        exit;
    }
} elseif (!empty($secretKey)) {
    echo json_encode($response);
    exit;
}
// --- 驗證結束 ---


// --- 處理檔案上傳 ---
if (isset($_FILES['images'])) {
    // 為這次上傳建立一個唯一的 session ID 和對應的資料夾
    $sessionId = uniqid('upload_');
    $sessionDir = __DIR__ . '/uploads/' . $sessionId;
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0755, true);
    }

    // 記錄上傳時間
    file_put_contents($sessionDir . '/timestamp.txt', time());

    $uploadedFiles = [];
    $errors = [];
    $totalFiles = count($_FILES['images']['name']);

    if ($totalFiles > 20) {
        echo json_encode(['success' => false, 'message' => '上傳總數不能超過 20 個檔案。']);
        exit;
    }

    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = $_FILES['images']['name'][$i];
        $fileTmpName = $_FILES['images']['tmp_name'][$i];
        $fileError = $_FILES['images']['error'][$i];

        if ($fileError === UPLOAD_ERR_OK) {
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];

            if (in_array($fileExtension, $allowedExtensions)) {
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

                    if ($image !== null) {
                        // --- 圖片縮放邏輯 ---
                        $shouldResize = isset($_POST['resize']) && $_POST['resize'] === 'true';
                        $maxWidth = 2480;
                        $maxHeight = 2480;
                        $width = imagesx($image);
                        $height = imagesy($image);

                        if ($shouldResize && ($width > $maxWidth || $height > $maxHeight)) {
                            $ratio = $width / $height;
                            if ($width > $height) {
                                $newWidth = $maxWidth;
                                $newHeight = $maxWidth / $ratio;
                            } else {
                                $newHeight = $maxHeight;
                                $newWidth = $maxHeight * $ratio;
                            }

                            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                            // 處理 PNG 透明背景
                            if ($fileExtension == 'png') {
                                imagealphablending($resizedImage, false);
                                imagesavealpha($resizedImage, true);
                                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                                imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
                            }

                            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                            imagedestroy($image); // 釋放舊圖片資源
                            $image = $resizedImage; // 將指標指向新圖片
                        }

                        // --- 儲存為 WebP ---
                        if ($fileExtension == 'png') {
                            imagepalettetotruecolor($image);
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
                        } else {
                            $errors[] = "檔案 '{$fileName}' 無法轉換為 WebP。";
                        }
                        imagedestroy($image);
                    } else {
                        $errors[] = "檔案 '{$fileName}' 無法建立圖片資源。";
                    }
                } else {
                    $errors[] = "檔案 '{$fileName}' 移動失敗。";
                }
            } else {
                $errors[] = "檔案 '{$fileName}' 的格式不被支援。";
            }
        } else {
            $errors[] = "檔案 '{$fileName}' 上傳失敗，錯誤碼：{$fileError}。";
        }
    }

    if (!empty($uploadedFiles)) {
        echo json_encode([
            'success' => true,
            'files' => $uploadedFiles,
            'sessionId' => $sessionId,
            'errors' => $errors
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '沒有任何檔案成功上傳或轉換。', 'errors' => $errors]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => '沒有收到任何檔案。']);
?>
