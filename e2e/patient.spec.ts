import { test, expect } from '@playwright/test';

test('Open patient profile', async ({ page }) => {
  await page.goto('http://dentfluence.test/login');

  await page.getByRole('textbox', { name: 'Email address' }).fill('sumit@tulipdental.in');
  await page.getByRole('textbox', { name: 'Password' }).fill('Tulip@2025');

  await page.getByRole('button', { name: 'Sign In' }).click();

  await expect(page).toHaveURL(/\/dashboard/);

  await page.getByRole('link', { name: 'Patients' }).click();

  await page.getByText('Mr. subhash Patil AOCP').click();

  await expect(page).toHaveURL(/\/patients\/3960/);
  await expect(page.getByText('Mr. subhash Patil')).toBeVisible();
});