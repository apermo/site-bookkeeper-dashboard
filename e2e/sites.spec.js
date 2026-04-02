const { test, expect } = require('@playwright/test');

test('sites overview page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard');

    await expect(page.locator('h1')).toContainText('Site Bookkeeper Dashboard');
});

test('table structure is present', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard');

    await expect(page.locator('table.wp-list-table')).toBeVisible();
    await expect(page.locator('table.wp-list-table thead')).toBeVisible();
    await expect(page.locator('table.wp-list-table tbody')).toBeVisible();
});
