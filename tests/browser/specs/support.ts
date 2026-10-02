import { test as base, expect, type Locator, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the groupable-gridfield specs.
//
// The CMS screen is the fixture ModelAdmin in tests/browser/fixtures/ (copied into the scratch host
// by the runner): /admin/gg-browser/<mode>, where a source record's edit form holds the groupable
// "Items" grid. Every spec first asks the fixture reset endpoint for a freshly seeded source record
// of its own (GgBDOSource::reseed / GgBMVSource::reseed), so it starts from the same groups and items
// on every run and under --repeat-each:
//   DataObject mode: Alpha (A1, A2), Beta (B1), Gamma (empty), "No section" (U1)
//   MultiValue mode: Alpha (I1, I2), Beta (I3), "none" (I4)

export type Mode = 'do' | 'mv';

/** Answers queued for the next confirm() dialogs, per page (see expectConfirm). */
const confirmAnswers = new WeakMap<Page, { accept: boolean; seen: (msg: string) => void }[]>();

/**
 * test, extended with an automatic guard: every spec fails if the page logs a console error, throws
 * an uncaught exception, or opens an alert() or a confirm() nobody asked for. The module reports
 * every failed AJAX call through console.error and/or alert() ("Error creating group: ..."), so a
 * failing endpoint is caught here even where a spec does not look at the response. Warnings (the
 * admin's own deprecation notices) do not count.
 */
export const test = base.extend<{ guard: void }>({
    guard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            confirmAnswers.set(page, []);
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => {
                if (isKnownSs5EntwineError(err)) {
                    return;
                }
                errors.push(`uncaught: ${err.message}`);
            });
            page.on('dialog', async (dialog) => {
                const queue = confirmAnswers.get(page) ?? [];
                if (dialog.type() === 'confirm' && queue.length) {
                    const answer = queue.shift()!;
                    answer.seen(dialog.message());
                    await (answer.accept ? dialog.accept() : dialog.dismiss());
                    return;
                }
                if (dialog.type() === 'beforeunload') {
                    await dialog.accept();
                    return;
                }
                errors.push(`unexpected ${dialog.type()}(): ${dialog.message()}`);
                await dialog.dismiss();
            });

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors, uncaught exceptions or unexpected dialogs').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/**
 * The one uncaught error that is not counted, and only where it comes from: the Silverstripe 5
 * admin's bundled entwine (silverstripe/admin 2.x, client/dist/js/vendor.js). Its MutationObserver
 * hands every added node except "#text" to the onadd rules, so an added comment node reaches the
 * selector matcher, which calls getAttribute() on it: "el.getAttribute is not a function". It only
 * fires when some loaded script registers an onadd rule, which symbiote/gridfieldextensions'
 * GridFieldExtensions.js does (loaded by GridFieldOrderableRows); with groupable.js on onmatch the
 * error stays (measured 2026-10-02), so it is not this module's. The Silverstripe 6 admin (3.x)
 * passes element nodes only (nodeType === 1) and never shows it. Any other uncaught error still
 * fails the spec.
 */
function isKnownSs5EntwineError(err: Error): boolean {
    return (
        err.message === 'el.getAttribute is not a function' &&
        /\/silverstripe\/admin\/client\/dist\/js\/vendor\.js/.test(err.stack ?? '') &&
        /\bas matches\b/.test(err.stack ?? '')
    );
}

/** Queue the answer to the next confirm() dialog; resolves with its message once it has shown. */
export function expectConfirm(page: Page, accept: boolean): Promise<string> {
    return new Promise((resolve) => confirmAnswers.get(page)!.push({ accept, seen: resolve }));
}

/** Ask the fixture reset endpoint for a freshly seeded source record; returns its ID. */
export async function reseed(page: Page, mode: Mode, title: string, setup = 'immediate'): Promise<number> {
    const response = await page.request.get('/admin/gg-reset/reseed', { params: { mode, title, setup } });
    expect(response.status(), `reseed ${mode} "${title}"`).toBe(200);
    return (await response.json()).id;
}

/** The URL of a source record's edit form in the fixture ModelAdmin. */
export function editUrl(mode: Mode, id: number): string {
    return `/admin/gg-browser/${mode}/EditForm/field/${mode}/item/${id}/edit`;
}

/**
 * Open a source record's edit form with a full page load and wait until the module's JS has built
 * the groups: the divider rows exist only once groupable.js has run (they are rendered client-side
 * from a template), so their presence is the proof the script loaded and ran.
 */
export async function openSource(page: Page, mode: Mode, id: number): Promise<Locator> {
    await page.goto(editUrl(mode, id));
    const grid = page.locator('#Form_ItemEditForm_Items');
    await expect(grid.locator('tbody tr.groupable-bound').first()).toBeVisible();
    return grid;
}

/** Seed a source record and open it: the start of most specs. */
export async function freshSource(page: Page, mode: Mode, title: string, setup = 'immediate'): Promise<{ id: number; grid: Locator }> {
    const id = await reseed(page, mode, title, setup);
    return { id, grid: await openSource(page, mode, id) };
}

/** One group as the grid shows it: the divider's name and the item titles below it, in order. */
export type GroupRows = [name: string, items: string[]];

/**
 * Read the grid's grouping from the DOM, top to bottom: each divider row starts a group and the item
 * rows up to the next divider belong to it. The name is what the divider shows: the editable name
 * input (MultiValue mode with the add button), else the title text.
 */
export async function layout(grid: Locator): Promise<GroupRows[]> {
    return grid.locator('tbody').evaluate((tbody) => {
        const groups: [string, string[]][] = [];
        for (const tr of Array.from(tbody.children)) {
            if (tr.classList.contains('groupable-bound')) {
                const input = tr.querySelector<HTMLInputElement>('input.group-val');
                const title = tr.querySelector('.group-title, strong');
                groups.push([(input ? input.value : (title?.textContent ?? '')).trim(), []]);
            } else if (tr.classList.contains('ss-gridfield-item') && groups.length) {
                groups[groups.length - 1][1].push((tr.querySelector('td.col-Title')?.textContent ?? '').trim());
            }
        }
        return groups;
    });
}

/** An item row by its Title cell. */
export function itemRow(grid: Locator, title: string): Locator {
    return grid.locator('tbody tr.ss-gridfield-item').filter({
        has: grid.page().locator('td.col-Title', { hasText: new RegExp(`^\\s*${title}\\s*$`) }),
    });
}

/**
 * A divider row by the group name it shows: the name input's value attribute (enhanced MultiValue
 * divider) or the title text (DataObject and plain dividers). Deliberately not by the row's
 * data-group-name attribute, which is part of what the specs check (the 2.4.1 fix).
 */
export function divider(grid: Locator, name: string): Locator {
    const page = grid.page();
    return grid.locator('tbody tr.groupable-bound').filter({
        has: page.locator(`input.group-val[value="${name}"], .group-title:text-is("${name}"), strong:text-is("${name}")`),
    });
}

/** The value of an item row's hidden group input, i.e. the group key the form would submit for it. */
export async function hiddenGroupOf(row: Locator): Promise<string> {
    return row.locator('input.ss-groupable-hidden-group').inputValue();
}

/**
 * Drag a row by its drag handle and drop it onto the upper or lower half of another row, with real
 * mouse events in small steps (jQuery UI sortable only reacts to a pointer that actually moves).
 * Dropping on the upper half of a row places the dragged row directly above it.
 */
export async function dragRow(page: Page, row: Locator, target: Locator, where: 'above' | 'below'): Promise<void> {
    const handle = row.locator('.col-reorder .handle');
    await handle.scrollIntoViewIfNeeded();
    const from = (await handle.boundingBox())!;
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    // Past jQuery UI's start distance first, then towards the target.
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2 + 8, { steps: 4 });
    const to = (await target.boundingBox())!;
    const y = where === 'above' ? to.y + to.height * 0.25 : to.y + to.height * 0.75;
    await page.mouse.move(from.x + from.width / 2, y, { steps: 20 });
    // A couple of small moves at the destination let sortable settle the placeholder.
    await page.mouse.move(from.x + from.width / 2, y + 1, { steps: 2 });
    await page.mouse.move(from.x + from.width / 2, y, { steps: 2 });
    await page.mouse.up();
}

/** Wait for a POST to one of the grid's own endpoints (group_assignment, group_create, ...). */
export function waitForGridPost(page: Page, endpoint: string): Promise<Request> {
    const url = new RegExp(`/field/Items/${endpoint}(/|\\?|$)`);
    return page.waitForRequest((r) => r.method() === 'POST' && url.test(r.url()));
}

/**
 * Assert a request was an AJAX request answered with 200, and return the response body. In dev
 * mode an error answer is Silverstripe's error page, so its start goes into the failure message.
 */
export async function expectAjaxOk(request: Request): Promise<string> {
    expect(['xhr', 'fetch'], 'the request is an AJAX request').toContain(request.resourceType());
    const response = await request.response();
    const status = response?.status();
    const body = (await response?.text().catch(() => '')) ?? '';
    const brief = body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 1500);
    expect(status, `response status${status === 200 ? '' : `; body: ${brief}`}`).toBe(200);
    return body;
}

/** Parse a JSON answer of the module's group endpoints and assert success. */
export async function expectJsonSuccess(request: Request): Promise<Record<string, unknown>> {
    const body = await expectAjaxOk(request);
    const json = JSON.parse(body);
    expect(json.success, `endpoint answered success; message: ${json.message}`).toBe(true);
    return json;
}

/**
 * Record every DOCUMENT request of the main frame from now on: the module's actions must run
 * through AJAX and never reload or replace the page. Returns a getter for the URLs seen.
 */
export function watchDocumentNavigations(page: Page): () => string[] {
    const seen: string[] = [];
    page.on('request', (r) => {
        if (r.isNavigationRequest() && r.frame() === page.mainFrame()) {
            seen.push(`${r.method()} ${r.url()}`);
        }
    });
    return () => [...seen];
}

/** The CMS marks an edit form with unsaved changes with the class "changed". */
export function editForm(page: Page): Locator {
    return page.locator('form#Form_ItemEditForm');
}

/**
 * Save the edit form with its Save button and wait for the CMS's AJAX save to come back: the
 * admin submits the form through XHR and swaps in the re-rendered form.
 */
export async function saveForm(page: Page): Promise<Request> {
    const posted = page.waitForRequest((r) => r.method() === 'POST' && /\/ItemEditForm(\?|$)/.test(r.url()));
    await page.locator('button[name="action_doSave"]').click();
    const request = await posted;
    await expectAjaxOk(request);
    return request;
}
