
import re
from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # --- 登入並設定橫幅 ---
    page.goto("http://localhost:8000/admin/")
    page.get_by_label("帳號：").fill("admin")
    page.get_by_label("密碼：").fill("admin")
    page.get_by_role("button", name="登入").click()
    page.wait_for_url("http://localhost:8000/admin/dashboard.php")

    print("已登入管理後台。")

    # 上傳上方橫幅圖片
    page.locator('input[name="banner_top_file"]').set_input_files('jules-scratch/verification/test_banner.png')
    print("已選擇橫幅圖片。")

    # 填寫上方橫幅連結
    page.locator('input[name="banner_top_link"]').fill("https://www.google.com")
    print("已填寫橫幅連結。")

    # 提交設定
    page.get_by_role("button", name="儲存設定").click()

    # 等待頁面重新載入以確認儲存成功
    page.wait_for_load_state('networkidle')
    print("設定已儲存。")

    # --- 驗證主頁 ---
    page.goto("http://localhost:8000")
    page.wait_for_load_state('networkidle')

    # 檢查橫幅是否存在
    banner_image = page.locator('#banner-top img')
    expect(banner_image).to_be_visible()
    print("橫幅圖片已在主頁上可見。")

    # 檢查橫幅連結是否存在
    banner_link = page.locator('#banner-top a')
    expect(banner_link).to_have_attribute('href', 'https://www.google.com')
    print("橫幅連結已正確設定。")

    page.screenshot(path="jules-scratch/verification/homepage_with_banner.png", full_page=True)
    print("已截圖帶有橫幅的主頁。")

    context.close()
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
