import { expect, test } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const fixture = (name) => new URL(`./.fixtures/${name}.html`, import.meta.url).href;
const script = fileURLToPath(new URL('../../assets/multi-instance-sync.js', import.meta.url));

// Load a fixture, then the plugin script as WP Rocket's Delay JS would: after the page has parsed
const open = async (page, name, { loadScript = true } = {}) => {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(fixture(name));
  if (loadScript) {
    await page.addScriptTag({ path: script });
  }
  return errors;
};

// Which section holds form 7 (or another form), by the section's id
const holder = (page, id = 7) =>
  page.evaluate(
    (id) => document.querySelector(`#gform_wrapper_${id}`)?.closest('section, #modal')?.id,
    id,
  );

const scrollTo = async (page, selector) => {
  await page.locator(selector).scrollIntoViewIfNeeded();
  await page.waitForTimeout(400);
};

test.describe('the same form at the top and bottom of a page', () => {
  test('moves to the bottom placement as it nears, keeping the answers, and back again', async ({
    page,
  }) => {
    const errors = await open(page, 'top-and-bottom');

    expect(await holder(page)).toBe('top');
    await expect(page.locator('#bottom [data-gf-mis-link]')).toBeVisible();
    await page.fill('#input_7_1', 'visitor@example.com');

    await scrollTo(page, '#bottom');
    expect(await holder(page)).toBe('bottom');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');
    await expect(page.locator('#bottom [data-gf-mis-link]')).toBeHidden();

    await scrollTo(page, '#top');
    expect(await holder(page)).toBe('top');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');
    expect(errors).toEqual([]);
  });

  test('keeps the height of the placement it leaves, so the page above does not jump', async ({
    page,
  }) => {
    await open(page, 'top-and-bottom');
    const height = await page.locator('#top .gf-mis-slot').evaluate((slot) => slot.offsetHeight);

    await scrollTo(page, '#bottom');

    expect(await page.locator('#top .gf-mis-slot').evaluate((slot) => slot.offsetHeight)).toBe(
      height,
    );
    await expect(page.locator('#top [data-gf-mis-link]')).toHaveCSS('display', 'inline');
  });

  test('keeps its element IDs, so conditional logic still finds its fields after a move', async ({
    page,
  }) => {
    await open(page, 'top-and-bottom');
    await scrollTo(page, '#bottom');

    await page.check('#choice_7_3_1');
    await expect(page.locator('#field_7_26')).toBeVisible();
    expect(await page.locator('[id="field_7_26"]').count()).toBe(1);
  });

  test('stays put while it is submitting', async ({ page }) => {
    await open(page, 'top-and-bottom');
    await page.evaluate(() => (window.gf_submitting_7 = true));

    await scrollTo(page, '#bottom');

    expect(await holder(page)).toBe('top');
  });

  test('the link in an empty placement scrolls to the form', async ({ page }) => {
    await open(page, 'top-and-bottom');
    await page.evaluate(() => (window.gf_submitting_7 = true));
    await scrollTo(page, '#bottom');

    await page.click('#bottom [data-gf-mis-link]');
    await page.waitForTimeout(800);

    await expect(page.locator('#gform_wrapper_7')).toBeInViewport();
  });
});

test.describe('a mobile-only and a desktop-only placement', () => {
  test('on desktop, the inline script moves the form into the visible placement before the main script loads', async ({
    page,
  }) => {
    await open(page, 'mobile-and-desktop', { loadScript: false });

    expect(await holder(page)).toBe('desktop');
    await expect(page.locator('#gform_wrapper_7')).toBeVisible();
  });

  test('on mobile, the form stays in the first placement', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await open(page, 'mobile-and-desktop', { loadScript: false });

    expect(await holder(page)).toBe('mobile');
    await expect(page.locator('#gform_wrapper_7')).toBeVisible();
  });

  test('resizing across the breakpoint moves the form to the placement that shows', async ({
    page,
  }) => {
    const errors = await open(page, 'mobile-and-desktop');
    await page.fill('#input_7_1', 'visitor@example.com');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('mobile');

    await page.setViewportSize({ width: 1280, height: 800 });
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('desktop');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');
    expect(errors).toEqual([]);
  });
});

test.describe('a mobile-only and a desktop-only placement hidden with visibility', () => {
  test('moves to the placement that shows, on load and across the breakpoint', async ({ page }) => {
    const errors = await open(page, 'mobile-and-desktop-visibility');
    expect(await holder(page)).toBe('desktop');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('mobile');

    await page.setViewportSize({ width: 1280, height: 800 });
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('desktop');
    expect(errors).toEqual([]);
  });
});

test.describe('the form on the page and in a modal', () => {
  test('moves into the modal when it opens, even with the page form on screen, and back when it closes', async ({
    page,
  }) => {
    const errors = await open(page, 'modal');
    await page.fill('#input_7_1', 'visitor@example.com');

    await page.evaluate(() => (document.getElementById('modal').style.display = 'block'));
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('modal');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');

    await page.evaluate(() => (document.getElementById('modal').style.display = 'none'));
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');
    expect(errors).toEqual([]);
  });

  test('moves back to the page when the modal closes with the page placement off screen', async ({
    page,
  }) => {
    await open(page, 'modal');
    await page.evaluate(() => (document.getElementById('modal').style.display = 'block'));
    await page.waitForTimeout(400);
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(400);

    await page.evaluate(() => (document.getElementById('modal').style.display = 'none'));
    await page.waitForTimeout(400);

    expect(await holder(page)).toBe('top');
  });
});

test.describe('the form on the page and in a modal, opened while the form is submitting', () => {
  test('moves into the modal once the submission ends', async ({ page }) => {
    await open(page, 'modal');
    await page.evaluate(() => (window.gf_submitting_7 = true));

    await page.evaluate(() => (document.getElementById('modal').style.display = 'block'));
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');

    await page.evaluate(() => (window.gf_submitting_7 = false));
    await page.waitForTimeout(600);
    expect(await holder(page)).toBe('modal');
  });
});

test.describe('the form on the page and in a modal hidden with visibility', () => {
  test('stays out of the closed modal while scrolling, even though the modal is in the viewport', async ({
    page,
  }) => {
    await open(page, 'modal-visibility');

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(400);

    expect(await holder(page)).toBe('top');
  });

  test('moves into the modal when it fades in, and back after it fades out', async ({ page }) => {
    const errors = await open(page, 'modal-visibility');
    await page.fill('#input_7_1', 'visitor@example.com');

    await page.evaluate(() => document.getElementById('modal').classList.add('is-open'));
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('modal');
    await expect(page.locator('#modal #input_7_1')).toBeVisible();

    await page.evaluate(() => document.getElementById('modal').classList.remove('is-open'));
    await page.waitForTimeout(600);
    expect(await holder(page)).toBe('top');
    await expect(page.locator('#input_7_1')).toHaveValue('visitor@example.com');
    expect(errors).toEqual([]);
  });
});

test.describe('the form on the page and in a modal hidden by an attribute', () => {
  test('moves into the modal when the attribute changes, and back', async ({ page }) => {
    const errors = await open(page, 'modal-aria-hidden');

    await page.evaluate(() =>
      document.getElementById('modal').setAttribute('aria-hidden', 'false'),
    );
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('modal');

    await page.evaluate(() => document.getElementById('modal').setAttribute('aria-hidden', 'true'));
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');
    expect(errors).toEqual([]);
  });
});

test.describe('the form on the page and in a panel revealed by its toggle', () => {
  test('moves into the panel when a sibling attribute reveals it, and back', async ({ page }) => {
    const errors = await open(page, 'sibling-toggle');
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');

    await page.evaluate(() =>
      document.getElementById('toggle').setAttribute('aria-expanded', 'true'),
    );
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('panel');

    await page.evaluate(() =>
      document.getElementById('toggle').setAttribute('aria-expanded', 'false'),
    );
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');
    expect(errors).toEqual([]);
  });
});

test.describe('the form on the page and in a tab revealed by a checked input', () => {
  test('moves into the tab when the input is checked, and back', async ({ page }) => {
    const errors = await open(page, 'checked-tab');
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');

    await page.check('#show-tab');
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('tab');

    await page.uncheck('#show-tab');
    await page.waitForTimeout(400);
    expect(await holder(page)).toBe('top');
    expect(errors).toEqual([]);
  });
});

test.describe('placements on screen together', () => {
  test('the form stays in the first placement and the second shows its link', async ({ page }) => {
    await open(page, 'side-by-side');
    await page.waitForTimeout(400);

    expect(await holder(page)).toBe('first');
    await expect(page.locator('#second [data-gf-mis-link]')).toBeVisible();
  });
});

test.describe('two different forms', () => {
  test('move independently', async ({ page }) => {
    await open(page, 'two-forms');

    await scrollTo(page, '#a-bottom');
    expect(await holder(page, 7)).toBe('a-bottom');
    expect(await holder(page, 8)).toBe('b-top');

    await scrollTo(page, '#b-bottom');
    expect(await holder(page, 8)).toBe('b-bottom');
  });
});

test.describe('a single placement', () => {
  test('is left as it is', async ({ page }) => {
    const errors = await open(page, 'single');

    expect(await holder(page)).toBe('only');
    await expect(page.locator('[data-gf-mis-link]')).toBeHidden();
    expect(errors).toEqual([]);
  });
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('the later placement links to the form', async ({ page }) => {
    await page.goto(fixture('top-and-bottom'));

    expect(await page.locator('#top .gf-mis-slot').getAttribute('id')).toBe('gf-mis-form-7');
    await expect(page.locator('#bottom [data-gf-mis-link]')).toHaveAttribute(
      'href',
      '#gf-mis-form-7',
    );
    await expect(page.locator('#bottom [data-gf-mis-link]')).toHaveText('Go to the form');
  });
});
