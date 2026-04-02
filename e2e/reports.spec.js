const { test, expect } = require('@playwright/test');

test('plugin report page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard_plugins');

    await expect(page.locator('h1')).toContainText('Cross-Site Plugin Report');
});

test('theme report page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard_themes');

    await expect(page.locator('h1')).toContainText('Cross-Site Theme Report');
});

test('networks page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard_networks');

    await expect(page.locator('h1')).toContainText('Networks');
});
