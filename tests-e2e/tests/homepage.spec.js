const { test, expect } = require('@playwright/test');

test('Homepage loads and contains expected elements', async ({ page }) => {
    await page.goto('/');

    // Verify basic elements are present
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();

    // Ensure a login link is present
    const loginLink = page.getByRole('link', { name: 'Log in' }).nth(1); // There are actually two
    await expect(loginLink).toBeVisible();  

    // Test basic navigation if available
    if (await page.locator('nav ul li a').first().isVisible()) {
        await page.locator('nav ul li a').first().click();
        // Verify navigation worked
        await expect(page).not.toHaveURL('/');
    }
});