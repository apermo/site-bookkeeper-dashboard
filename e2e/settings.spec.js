const { test, expect } = require('@playwright/test');

test('settings page loads with hub URL and client token fields', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=site_bookkeeper_dashboard_settings');

    await expect(page.locator('h1')).toContainText('Site Bookkeeper Dashboard Settings');
    await expect(page.locator('input[name="site_bookkeeper_dashboard_hub_url"]')).toBeVisible();
    await expect(page.locator('input[name="site_bookkeeper_dashboard_token"]')).toBeVisible();
});

test('save settings works', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=site_bookkeeper_dashboard_settings');

    await page.locator('input[name="site_bookkeeper_dashboard_hub_url"]').fill('https://hub.example.tld');
    await page.locator('input[name="site_bookkeeper_dashboard_token"]').fill('test-token-123');
    await page.locator('#submit').click();

    await expect(page.locator('#setting-error-settings_updated, .notice-success')).toBeVisible();
    await expect(page.locator('input[name="site_bookkeeper_dashboard_hub_url"]')).toHaveValue('https://hub.example.tld');
});
