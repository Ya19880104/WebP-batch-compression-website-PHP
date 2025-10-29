
import re
from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # 驗證主頁
    page.goto("http://localhost:8000")
    page.wait_for_load_state('networkidle')
    page.screenshot(path="jules-scratch/verification/homepage.png", full_page=True)
    print("已截圖主頁。")

    # 驗證管理後台
    page.goto("http://localhost:8000/admin/")
    page.wait_for_load_state('networkidle')

    # 登入
    page.get_by_label("帳號：").fill("admin")
    page.get_by_label("密碼：").fill("admin")
    page.get_by_role("button", name="登入").click()

    # 等待登入成功並跳轉
    page.wait_for_url("http://localhost:8000/admin/dashboard.php", timeout=5000)
    page.wait_for_load_state('networkidle')

    print("已成功登入管理後台。")

    # 截圖儀表板
    page.screenshot(path="jules-scratch/verification/admin_dashboard.png", full_page=True)
    print("已截圖管理儀表板。")

    context.close()
    browser.close()

with sync_playwright() as playwright:
    run(playwright)
