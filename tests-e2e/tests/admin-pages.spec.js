const { test, expect } = require('@playwright/test');

async function loginAsAdmin(page) {
  await page.goto('/user/login');
  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'adminpw');
  await page.click('#edit-submit');
  await expect(page.locator('.profile')).toBeVisible();
}

async function logout(page) {
    await page.goto('/user/logout');
    await expect(page.locator('h1.page-title')).toContainText('Are you sure you want to log out?');
    await page.click('input[type="submit"]'); 
    // Wait for navigation after logout
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1.page-title')).toContainText('Log in');    
}

test.describe('Admin functionality tests', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test.afterEach(async ({ page }) => {
    await logout(page);
  });  

  test('Admin can view model administration page', async ({ page }) => {
    await page.goto('/admin/models');
    await expect(page.locator('h1.page-title')).toContainText('Model administration');
  });
});
