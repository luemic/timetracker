import { test, expect } from '@playwright/test';

const EMAIL = 'test@example.com';
const PASSWORD = 'test12345';

async function login(page) {
  // Always use the test front controller
  await page.goto('/index_test.php/login');
  await page.getByTestId('login-email').fill(EMAIL);
  await page.getByTestId('login-password').fill(PASSWORD);
  await Promise.all([
    page.waitForURL(/\/index_test\.php\/app$/),
    page.getByTestId('login-submit').click(),
  ]);
  await expect(page).toHaveURL(/\/index_test\.php\/app$/);
  await expect(page.getByText(/Eingeloggt als|Logged in as/i)).toBeVisible();
}

function toLocalDateTimeInputString(d = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function addMinutes(date: Date, minutes: number): Date {
  return new Date(date.getTime() + minutes * 60000);
}

/**
 * E2E sequence required:
 * - Login
 * - then create a customer
 * - then edit the customer
 * - for that customer create a project
 * - edit the project
 * - create an activity
 * - edit this activity
 * - create another activity
 * - then create a time booking
 * - create another time booking
 * - try to create an overlapping time booking (expect error)
 * - delete all time bookings, activities, projects and customers
 */

test.describe('End-to-end app flow', () => {
  test('login, CRUD master data, time bookings incl. overlap and cleanup', async ({ page }) => {
    // Accept all confirm dialogs automatically (used by delete buttons)
    page.on('dialog', async (dialog) => {
      await dialog.accept();
    });

    // Login
    await login(page);

    // ----- Customers: create then edit -----
    await page.click('#tab-customers');
    const sectionCustomers = page.locator('#section-customers');

    const customerName = `Cust-${Date.now()}`;
    await page.fill('#input-customer-name', customerName);
    await page.click('#btn-customers-save');
    await expect(sectionCustomers.locator('tbody')).toContainText(customerName);

    // Edit the same customer
    await sectionCustomers.locator('tbody tr:has(td:text-is("' + customerName + '"))').first().click();
    const customerNameEdited = customerName + '-edit';
    await page.fill('#input-customer-name', customerNameEdited);
    await page.click('#btn-customers-save');
    await expect(sectionCustomers.locator('tbody')).toContainText(customerNameEdited);

    // ----- Projects: create for that customer, then edit -----
    await page.click('#tab-projects');
    const sectionProjects = page.locator('#section-projects');

    const projectName = `Proj-${Date.now()}`;
    await page.fill('#input-project-name', projectName);
    await page.selectOption('#input-project-customer', { label: customerNameEdited });
    await page.click('#btn-project-save');
    await expect(sectionProjects.locator('tbody')).toContainText(projectName);

    // Edit the project
    await sectionProjects.locator('tbody tr:has(td:text-is("' + projectName + '"))').first().click();
    const projectNameEdited = projectName + '-edit';
    await page.fill('#input-project-name', projectNameEdited);
    await page.click('#btn-project-save');
    await expect(sectionProjects.locator('tbody')).toContainText(projectNameEdited);

    // ----- Activities: create, edit, create another -----
    await page.click('#tab-activities');
    const sectionActivities = page.locator('#section-activities');

    const activityName = `Act-${Date.now()}`;
    await page.fill('#input-activity-name', activityName);
    await page.click('#btn-activities-save');
    await expect(sectionActivities.locator('tbody')).toContainText(activityName);

    // Edit activity
    await sectionActivities.locator('tbody tr:has(td:text-is("' + activityName + '"))').first().click();
    const activityNameEdited = activityName + '-edit';
    await page.fill('#input-activity-name', activityNameEdited);
    await page.click('#btn-activities-save');
    await expect(sectionActivities.locator('tbody')).toContainText(activityNameEdited);

    // Create another activity
    const activityName2 = `Act2-${Date.now()}`;
    await page.fill('#input-activity-name', activityName2);
    await page.click('#btn-activities-save');
    await expect(sectionActivities.locator('tbody')).toContainText(activityName2);

    // ----- Time bookings: create, create second, attempt overlap -----
    await page.click('#tab-time');
    const sectionTime = page.locator('#section-time');
    await page.selectOption('#input-time-project', { label: projectNameEdited });
    await page.selectOption('#input-time-activity', { label: activityNameEdited });

    // Define a deterministic base time (rounded to minute) for today
    const base = new Date(); base.setSeconds(0,0);

    // First booking: base .. base+15
    const ticket1 = `T-${Date.now()}-1`;
    await page.fill('#input-time-ticket', ticket1);
    await page.fill('#input-time-start', toLocalDateTimeInputString(base));
    await page.fill('#input-time-end', toLocalDateTimeInputString(addMinutes(base, 15)));
    await page.click('#btn-time-save');
    await expect(sectionTime.locator('table tbody')).toContainText(ticket1);

    // Second booking (non-overlapping): base+20 .. base+35
    const ticket2 = `T-${Date.now()}-2`;
    await page.selectOption('#input-time-project', { label: projectNameEdited });
    await page.selectOption('#input-time-activity', { label: activityNameEdited });
    await page.fill('#input-time-ticket', ticket2);
    await page.fill('#input-time-start', toLocalDateTimeInputString(addMinutes(base, 20)));
    await page.fill('#input-time-end', toLocalDateTimeInputString(addMinutes(base, 35)));
    await page.click('#btn-time-save');
    await expect(sectionTime.locator('table tbody')).toContainText(ticket2);

    // Overlapping booking attempt: base+10 .. base+25 (overlaps both windows)
    const ticketOverlap = `T-${Date.now()}-OL`;
    await page.selectOption('#input-time-project', { label: projectNameEdited });
    await page.selectOption('#input-time-activity', { label: activityNameEdited });
    await page.fill('#input-time-ticket', ticketOverlap);
    await page.fill('#input-time-start', toLocalDateTimeInputString(addMinutes(base, 10)));
    await page.fill('#input-time-end', toLocalDateTimeInputString(addMinutes(base, 25)));
    await page.click('#btn-time-save');
    // Expect error alert with meaningful overlap message
    await expect(sectionTime.locator('.alert.alert-danger')).toBeVisible();
    await expect(sectionTime.locator('.alert.alert-danger')).toContainText(/überlappt|overlap/i);

    // ----- Cleanup: delete all time bookings, activities, projects, customers -----
    // Delete time bookings (iterate delete buttons in the time section)
    await page.click('#tab-time');
    const timeTable = sectionTime.locator('table tbody');
    while (await timeTable.locator('tr').count() > 0) {
      await timeTable.locator('tr').first().locator('button.btn-outline-danger').click();
      // wait for row count to decrease
      await page.waitForTimeout(200);
    }

    // Delete activities
    await page.click('#tab-activities');
    const actTable = sectionActivities.locator('table tbody');
    while (await actTable.locator('tr').count() > 0) {
      await actTable.locator('tr').first().locator('button.btn-outline-danger').click();
      await page.waitForTimeout(200);
    }

    // Delete projects
    await page.click('#tab-projects');
    const projTable = sectionProjects.locator('table tbody');
    while (await projTable.locator('tr').count() > 0) {
      await projTable.locator('tr').first().locator('button.btn-outline-danger').click();
      await page.waitForTimeout(200);
    }

    // Delete customers (must be last because of FK from projects)
    await page.click('#tab-customers');
    const custTable = sectionCustomers.locator('table tbody');
    while (await custTable.locator('tr').count() > 0) {
      await custTable.locator('tr').first().locator('button.btn-outline-danger').click();
      await page.waitForTimeout(200);
    }

    // Final sanity: tables empty (ensure each tab is active so its section is rendered)
    await page.click('#tab-customers');
    await expect(sectionCustomers.locator('table tbody')).not.toContainText(customerNameEdited);

    await page.click('#tab-projects');
    await expect(sectionProjects.locator('table tbody')).not.toContainText(projectNameEdited);

    await page.click('#tab-activities');
    await expect(sectionActivities.locator('table tbody')).not.toContainText(activityNameEdited);
  });
});
