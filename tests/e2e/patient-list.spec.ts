import { test, expect } from '@playwright/test';

test('authenticated user can view patient list', async ({ page }) => {
  // login logic duplicated for now (or use a setup file later)
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/);

  // Navigate to patients list
  await page.goto('/patients');

  // Assert that we are on the right page
  await expect(page).toHaveURL(/\/patients/);

  // Assert that some patient content is visible (adjust selector as needed)
  // E.g. a table row or a specific header
  // await expect(page.locator('table')).toBeVisible();
});
