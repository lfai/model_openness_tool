const { test, expect } = require('@playwright/test');

test.describe('Public page tests', () => {

    test('Models page loads with disclaimer', async ({ page }) => {
        await page.goto('/models');
        await expect(page.locator('h1.page-title')).toContainText('Models');
        await expect(page.locator('h2:not([class])')).toContainText('Disclaimer');
    });

    test('Licenses page loads with working search form', async ({ page }) => {
        await page.goto('/licenses');
        await expect(page.locator('h1.page-title')).toContainText('Licenses');

        const searchFiltersToggle = page.getByRole('button', { name: 'Search filters' });
        await expect(searchFiltersToggle).toBeVisible();

        await searchFiltersToggle.click();

        // Check that the license_id input is present and visible
        const licenseIdInput = page.locator('[data-drupal-selector="edit-license-id"]');
        await expect(licenseIdInput).toBeVisible();

        // Check that the fsf_libre select is present and visible
        const fsfLibreSelect = page.locator('[data-drupal-selector="edit-fsf-libre"]');
        await expect(fsfLibreSelect).toBeVisible();

        // Fill the "name" input with a search term
        await page.fill('[data-drupal-selector="edit-name"]', 'Gutmann');

        // Click the submit button
        await page.click('[data-drupal-selector="edit-submit"]');

        // Wait for search to complete
        await page.waitForLoadState('networkidle');

        // Assert that expected result appears
        await expect(page.getByRole('cell', { name: 'Gutmann License' })).toBeVisible();
    });

    test('Evaluate model page loads as expected', async ({ page }) => {
        await page.goto('/model/evaluate');
        await expect(page.locator('h1.page-title')).toContainText('Evaluate model');

        await expect(page.getByRole('button', { name: 'Evaluate' })).toBeVisible();
    });    
});

