import { test, expect, type Locator, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The content comes from the
// browser fixtures: GgBShowcaseAdmin seeds a "Help centre FAQ" source record (DataObject mode) and
// an "Event programme" one (MultiValue mode), shown in the fixture ModelAdmin's edit form.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

// Left edge of the CMS content panel at a 1280 px wide window: the shots leave out the CMS menu,
// which on the test host lists the fixtures' own admin sections.
const PANEL_X = 180;

type Mode = 'do' | 'mv';

/** Seed the demo source record for a mode, open its edit form, and wait for the groups to render. */
async function openShowcase(page: Page, mode: Mode): Promise<Locator> {
    const response = await page.request.get('/admin/gg-showcase/seed', { params: { mode } });
    expect(response.status(), `seed ${mode}`).toBe(200);
    const id = (await response.json()).id;
    await page.goto(`/admin/gg-browser/${mode}/EditForm/field/${mode}/item/${id}/edit`);
    const grid = page.locator('#Form_ItemEditForm_Items');
    // The divider rows are rendered client-side by groupable.js: their presence means it has run.
    await expect(grid.locator('tbody tr.groupable-bound').first()).toBeVisible();
    // No focus ring or hover state left over from loading.
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.mouse.move(5, 795);
    await page.waitForTimeout(400);
    return grid;
}

/** The content panel from the top of the window down to just below the grid. */
async function panelClip(grid: Locator, extraBelow = 12) {
    const box = (await grid.boundingBox())!;
    const page = grid.page();
    const width = page.viewportSize()!.width - PANEL_X;
    return { x: PANEL_X, y: 0, width, height: Math.ceil(box.y + box.height + extraBelow) };
}

/** An item row by its Title cell. */
function itemRow(grid: Locator, title: string): Locator {
    return grid.locator('tbody tr.ss-gridfield-item').filter({
        has: grid.page().locator('td.col-Title', { hasText: title }),
    });
}

/**
 * Pick a row up by its drag handle and move it over a target row, WITHOUT dropping it, so the
 * screenshot shows the drag in progress. Real mouse events in small steps: jQuery UI sortable only
 * reacts to a pointer that actually moves.
 */
async function liftOnto(page: Page, row: Locator, target: Locator, fraction: number): Promise<void> {
    const handle = row.locator('.col-reorder .handle');
    const from = (await handle.boundingBox())!;
    const x = from.x + from.width / 2;
    await page.mouse.move(x, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(x, from.y + from.height / 2 - 8, { steps: 4 });
    const to = (await target.boundingBox())!;
    const y = to.y + to.height * fraction;
    await page.mouse.move(x, y, { steps: 20 });
    await page.mouse.move(x, y + 1, { steps: 2 });
    await page.mouse.move(x, y, { steps: 2 });
    await page.waitForTimeout(300);
}

test.use({ viewport: { width: 1280, height: 900 } });

test('DataObject mode: sections with badges, actions and the add-group input', async ({ page }) => {
    const grid = await openShowcase(page, 'do');
    await expect(grid.locator('tbody tr.groupable-bound')).toHaveCount(4);
    await page.screenshot({ path: path.join(IMAGES, 'dataobject-mode.png'), clip: await panelClip(grid) });
});

// No still of a whole group being dragged: with the pointer held down, the group's drag helper
// (its divider and items) is drawn at the bottom of the window rather than under the pointer
// (seen on Silverstripe 6, 2026-11-02), so a still would not show what a user sees moving.
test('Dragging an item into another group', async ({ page }) => {
    const grid = await openShowcase(page, 'do');
    const clip = await panelClip(grid);
    // The unassigned question, lifted into the Ordering section (between its first two rows).
    await liftOnto(page, itemRow(grid, 'Is gift wrapping available?'), itemRow(grid, 'Can I change an order after paying?'), 0.3);
    // jQuery UI's placeholder row is in the DOM (it is styled invisible): the drag has started.
    await expect(grid.locator('tbody tr.ui-sortable-placeholder')).toBeAttached();
    await page.screenshot({ path: path.join(IMAGES, 'drag-item.png'), clip });
    await page.mouse.up();
});

test('MultiValue mode: editable group names', async ({ page }) => {
    const grid = await openShowcase(page, 'mv');
    await expect(grid.locator('tbody tr.groupable-bound')).toHaveCount(3);
    await page.screenshot({ path: path.join(IMAGES, 'multivalue-mode.png'), clip: await panelClip(grid) });
});
