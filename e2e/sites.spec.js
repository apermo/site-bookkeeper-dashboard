const { test, expect } = require('@playwright/test');

test('sites overview page loads', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard');

    await expect(page.locator('h1')).toContainText('Site Bookkeeper Dashboard');
});

test('table or error notice is present', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=site_bookkeeper_dashboard');

    // Without a configured hub the page shows an error notice instead of a table.
    const table = page.locator('table.wp-list-table');
    const notice = page.locator('.notice-error');
    await expect(table.or(notice)).toBeVisible();
});
