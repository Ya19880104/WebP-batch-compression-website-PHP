<?php
require_once 'config.php';
// 讀取並解析設定檔
$rawSettings = file_get_contents(SETTINGS_FILE);
$jsonSettings = substr($rawSettings, strpos($rawSettings, '{'));
$settings = json_decode($jsonSettings, true);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($settings['seo_title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($settings['seo_description']); ?>">
    <?php if (!empty($settings['seo_og_image'])): ?>
        <meta property="og:image" content="<?php
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $domain = $_SERVER['HTTP_HOST'];
            $ogImageUrl = $protocol . $domain . '/' . htmlspecialchars($settings['seo_og_image']);
            echo $ogImageUrl;
        ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
        <header>
            <a href="<?php echo htmlspecialchars($settings['logo_link']); ?>" class="logo">
                <img src="<?php echo htmlspecialchars($settings['logo_url']); ?>" alt="LOGO">
            </a>
        </header>

        <main>
            <?php if (!empty($settings['banner_top_image'])): ?>
                <div class="banner-container" id="banner-top">
                    <?php if (!empty($settings['banner_top_link'])): ?>
                        <a href="<?php echo htmlspecialchars($settings['banner_top_link']); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars($settings['banner_top_image']); ?>" alt="Top Banner">
                        </a>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($settings['banner_top_image']); ?>" alt="Top Banner">
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div id="upload-area">
                <p>將圖片拖曳到此處，或點擊以選擇檔案</p>
                <p style="font-size: 0.8em; color: #888;">(最多 20 個檔案，僅支援 JPG/PNG)</p>
                <input type="file" id="file-input" multiple accept="image/jpeg,image/png" style="display: none;">
            </div>

            <?php if (!empty($settings['cloudflare_turnstile_site_key'])): ?>
                <div id="turnstile-widget"></div>
            <?php endif; ?>

            <div class="options-container" style="text-align: center; margin-top: 20px;">
                <p style="margin-bottom: 10px;">圖片尺寸選項：</p>
                <div id="resize-options" style="display: flex; justify-content: center; flex-wrap: wrap; gap: 15px;">
                    <label><input type="radio" name="resize" value="none" checked> 原始尺寸</label>
                    <label><input type="radio" name="resize" value="2480"> 2480px</label>
                    <label><input type="radio" name="resize" value="1920"> 1920px</label>
                    <label><input type="radio" name="resize" value="600"> 600px</label>
                    <label><input type="radio" name="resize" value="600_crop"> 600px (置中裁切)</label>
                </div>
            </div>

            <div class="upload-btn-container">
                <button id="convert-btn">立即轉換</button>
            </div>

            <div id="download-list">
                <!-- 轉換後的檔案列表將顯示於此 -->
            </div>

            <div class="batch-download-container" style="display: none;">
                <button id="batch-download-btn">批次壓縮下載</button>
            </div>

            <?php if (!empty($settings['banner_bottom_image'])): ?>
                <div class="banner-container" id="banner-bottom">
                    <?php if (!empty($settings['banner_bottom_link'])): ?>
                        <a href="<?php echo htmlspecialchars($settings['banner_bottom_link']); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars($settings['banner_bottom_image']); ?>" alt="Bottom Banner">
                        </a>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($settings['banner_bottom_image']); ?>" alt="Bottom Banner">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <a href="<?php echo htmlspecialchars($settings['footer_link']); ?>" target="_blank"><?php echo htmlspecialchars($settings['footer_text']); ?></a>. All rights reserved.</p>
    </footer>

    <script>
        // --- Turnstile Onload Callback ---
        function renderTurnstileWidget() {
            console.log('Cloudflare script loaded. renderTurnstileWidget() is called.');
            if (typeof turnstile !== 'undefined' && document.getElementById('turnstile-widget')) {
                console.log('Turnstile object is available. Calling render...');
                try {
                    turnstile.render('#turnstile-widget', {
                        sitekey: '<?php echo htmlspecialchars($settings['cloudflare_turnstile_site_key']); ?>',
                        callback: function(token) {
                            console.log("Turnstile challenge completed. Token received.");
                        },
                        'error-callback': function() {
                            console.error('Turnstile challenge failed.');
                        }
                    });
                    console.log('Turnstile render function was called.');
                } catch (e) {
                    console.error('An error occurred while rendering Turnstile:', e);
                }
            } else {
                console.error('Turnstile object not found or widget container is missing.');
            }
        }

        // --- Main Application Logic ---
        document.addEventListener('DOMContentLoaded', function () {
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('file-input');
            const convertBtn = document.getElementById('convert-btn');
            const downloadList = document.getElementById('download-list');
            const batchDownloadContainer = document.querySelector('.batch-download-container');
            let filesToUpload = [];

            uploadArea.addEventListener('click', () => fileInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('drag-over');
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('drag-over');
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('drag-over');
                handleFiles(e.dataTransfer.files);
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });

            function handleFiles(files) {
                const newFiles = Array.from(files).filter(file => ['image/jpeg', 'image/png'].includes(file.type));
                if (filesToUpload.length + newFiles.length > 20) {
                    alert('上傳總數不能超過 20 個檔案。');
                    return;
                }
                filesToUpload.push(...newFiles);
                updateFileList();
            }

            function updateFileList() {
                const p = uploadArea.querySelector('p');
                if (filesToUpload.length > 0) {
                    p.textContent = `已選擇 ${filesToUpload.length} 個檔案。`;
                } else {
                    p.textContent = '將圖片拖曳到此處，或點擊以選擇檔案';
                }
            }

            convertBtn.addEventListener('click', () => {
                if (filesToUpload.length === 0) {
                    alert('請先選擇要轉換的圖片。');
                    return;
                }

                const formData = new FormData();
                filesToUpload.forEach(file => {
                    formData.append('images[]', file);
                });

                const selectedResizeOption = document.querySelector('input[name="resize"]:checked');
                formData.append('resize', selectedResizeOption.value);

                const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
                if (turnstileResponse) {
                    formData.append('cf-turnstile-response', turnstileResponse.value);
                }

                convertBtn.textContent = '轉換中...';
                convertBtn.disabled = true;

                fetch('upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    downloadList.innerHTML = '';
                    if (data.success) {
                        window.addEventListener('beforeunload', () => {
                            if (data.sessionId) {
                                const payload = JSON.stringify({ session_id: data.sessionId });
                                navigator.sendBeacon('cleanup.php', payload);
                            }
                        });

                        data.files.forEach(file => {
                            const item = document.createElement('div');
                            item.className = 'download-item';
                            item.innerHTML = `
                                <img src="${file.thumbnail_url}" alt="縮圖">
                                <div class="file-info">${file.webp}</div>
                                <a href="${file.webp_url}" class="download-link" download>下載</a>
                            `;
                            downloadList.appendChild(item);
                        });

                        if (data.files.length > 0) {
                            batchDownloadContainer.style.display = 'block';
                            const batchBtn = document.getElementById('batch-download-btn');
                            batchBtn.onclick = () => {
                                window.location.href = `download_zip.php?session_id=${data.sessionId}`;
                            };
                        }

                        if(data.errors && data.errors.length > 0) {
                            alert("部分檔案處理失敗：\n" + data.errors.join("\n"));
                        }

                    } else {
                        alert('轉換失敗：' + (data.message || '未知錯誤'));
                    }
                })
                .catch(error => {
                    console.error('上傳錯誤:', error);
                    alert('發生未預期的錯誤，請檢查主控台。');
                })
                .finally(() => {
                    convertBtn.textContent = '立即轉換';
                    convertBtn.disabled = false;
                    filesToUpload = [];
                    updateFileList();
                    <?php if (!empty($settings['cloudflare_turnstile_site_key'])): ?>
                    if (window.turnstile) {
                        window.turnstile.reset();
                    }
                    <?php endif; ?>
                });
            });
        });
    </script>
    <?php if (!empty($settings['cloudflare_turnstile_site_key'])): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=renderTurnstileWidget" async defer></script>
    <?php endif; ?>
</body>
</html>
