<?php
require_once 'config.php';
$settings = json_decode(file_get_contents(SETTINGS_FILE), true);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>WebP 圖片轉換器</title>
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
            <div id="upload-area">
                <p>將圖片拖曳到此處，或點擊以選擇檔案</p>
                <p style="font-size: 0.8em; color: #888;">(最多 20 個檔案，僅支援 JPG/PNG)</p>
                <input type="file" id="file-input" multiple accept="image/jpeg,image/png" style="display: none;">
            </div>

            <div id="turnstile-widget"></div>

            <div class="upload-btn-container">
                <button id="convert-btn">立即轉換</button>
            </div>

            <div id="download-list">
                <!-- 轉換後的檔案列表將顯示於此 -->
            </div>

            <div class="batch-download-container" style="display: none;">
                <button id="batch-download-btn">批次壓縮下載</button>
            </div>
        </main>
    </div>

    <footer>
        <?php echo $settings['footer_html']; ?>
    </footer>

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('file-input');
            const convertBtn = document.getElementById('convert-btn');
            const downloadList = document.getElementById('download-list');
            const batchDownloadContainer = document.querySelector('.batch-download-container');
            let filesToUpload = [];

            // 觸發檔案選擇
            uploadArea.addEventListener('click', () => fileInput.click());

            // 處理拖曳事件
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

            // 處理檔案選擇事件
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
                // 在此可以加入一個預覽列表，但為求簡潔，暫時只更新提示文字
                const p = uploadArea.querySelector('p');
                if (filesToUpload.length > 0) {
                    p.textContent = `已選擇 ${filesToUpload.length} 個檔案。`;
                } else {
                    p.textContent = '將圖片拖曳到此處，或點擊以選擇檔案';
                }
            }

            // 轉換按鈕點擊事件
            convertBtn.addEventListener('click', () => {
                if (filesToUpload.length === 0) {
                    alert('請先選擇要轉換的圖片。');
                    return;
                }

                const formData = new FormData();
                filesToUpload.forEach(file => {
                    formData.append('images[]', file);
                });

                // 加入 Turnstile response
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
                    downloadList.innerHTML = ''; // 清空舊列表
                    if (data.success) {
                        // 註冊頁面卸載事件以進行清理
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
                        alert('轉換失敗：' + data.message);
                    }
                })
                .catch(error => {
                    console.error('上傳錯誤:', error);
                    alert('發生未預期的錯誤，請檢查主控台。');
                })
                .finally(() => {
                    convertBtn.textContent = '立即轉換';
                    convertBtn.disabled = false;
                    filesToUpload = []; // 清空已上傳的檔案
                    updateFileList();
                    // 重設 Turnstile
                    if (window.turnstile) {
                        window.turnstile.reset();
                    }
                });
            });

            // 渲染 Turnstile 小工具
            if (document.getElementById('turnstile-widget')) {
                turnstile.render('#turnstile-widget', {
                    sitekey: '<?php echo htmlspecialchars($settings['cloudflare_turnstile_site_key']); ?>',
                });
            }
        });
    </script>
</body>
</html>
