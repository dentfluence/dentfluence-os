import { test, expect } from '@playwright/test';

test('Dentfluence login works', async ({ page }) => {
  await page.goto('http://dentfluence.test/login');

  await page.getByRole('textbox', { name: 'Email address' }).fill('sumit@tulipdental.in');
  await page.getByRole('textbox', { name: 'Password' }).fill('Tulip@2025');

  await page.getByRole('button', { name: 'Sign In' }).click();

  await expect(page).toHaveURL(/\/dashboard/);
  await expect(page.getByRole('heading', { name: /Good Morning/ })).toBeVisible();
});