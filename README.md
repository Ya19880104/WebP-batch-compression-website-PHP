WEBP 批次壓縮網站<br>
純PHP專案 無須資料庫

後台網址  ./admin<br>
帳號密碼預設為 admin<br>
設定檔案儲存於 setting.php 有加密，但仍建議對該檔案設置640禁止外部存取

2025/11/18
原先機制為下載過檔案就會重新載入網頁，且會有多個用戶競爭問題出現
本次更新 index.php 與 download_zip.php
改為 "重新上傳檔案時 清理前次上傳檔案 與 定期清理"
定期清理預設為5分鐘，請設定cron為5分鐘一次
你可以修改cron.php 內的過期條件"maxLifetime" 搭配cron執行
