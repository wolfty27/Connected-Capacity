import { test, expect } from '@playwright/test';

test('user can login successfully', async ({ page }) => {
  // Navigate to the login page
  await page.goto('/login');

  // Fill in credentials (adjust these selectors based on actual Blade view)
  // Assuming standard Laravel auth fields
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');

  // Submit the form
  await page.click('button[type="submit"]');

  // Assert redirection to dashboard
  // This might fail if no user exists, but establishes the test structure
  await expect(page).toHaveURL(/\/dashboard/);
});
