import {
    test,
    expect,
    divider,
    dragRow,
    editForm,
    expectAjaxOk,
    expectConfirm,
    expectJsonSuccess,
    freshSource,
    hiddenGroupOf,
    itemRow,
    layout,
    openSource,
    saveForm,
    waitForGridPost,
    watchDocumentNavigations,
} from './support';

// DataObject mode: the groups are GgBSection records of the source (has_many Sections, sorted on
// Sort), each item holds its group as an FK (SectionID). Set up as the README's DataObject example:
// metadata badge (Code), group sorting, editable titles, 'unassign' delete, one custom action, and
// GridFieldAddNewDataObjectGroupButton. Seed: Alpha (A1, A2), Beta (B1), Gamma (empty), U1 unassigned.

const SEEDED = [
    ['Alpha', ['A1', 'A2']],
    ['Beta', ['B1']],
    ['Gamma', []],
    ['No section', ['U1']],
];

/** The section record ID a divider row stands for. */
async function groupId(row: import('@playwright/test').Locator): Promise<string> {
    return (await row.getAttribute('data-group-id')) ?? '';
}

test('renders one divider per group, in sort order, with each item under its own group', async ({ page }) => {
    const { grid } = await freshSource(page, 'do', 'DO render');
    expect(await layout(grid)).toEqual(SEEDED);

    // A real group's divider: drag handle, delete button, the custom action, the click-to-edit
    // title and the Code metadata as a badge.
    const alpha = divider(grid, 'Alpha');
    await expect(alpha).toHaveClass(/groupable-advanced-bound/);
    await expect(alpha.locator('.col-reorder .handle')).toBeVisible();
    await expect(alpha.locator('button.ss-gridfield-group-delete')).toHaveAttribute('title', 'Delete Alpha');
    await expect(alpha.locator('button.ss-gridfield-group-action[data-action="stamp"]')).toHaveAttribute('title', 'Stamp section');
    await expect(alpha.locator('.group-title-editable .group-title')).toHaveText('Alpha');
    await expect(alpha.locator('.group-title-input')).toBeHidden();
    await expect(alpha.locator('.badge')).toHaveText('AL');

    // The unassigned group is not a record: no handle, no actions, not draggable as a group.
    const none = divider(grid, 'No section');
    await expect(none).not.toHaveClass(/groupable-advanced-bound/);
    await expect(none.locator('.handle, button')).toHaveCount(0);

    // Each item's hidden group input carries its section's ID (what a deferred save submits).
    expect(await hiddenGroupOf(itemRow(grid, 'B1'))).toBe(await groupId(divider(grid, 'Beta')));

    // client/css/groupable.css is exposed and loaded: it gives divider rows their striped background.
    const background = await alpha.evaluate((el) => getComputedStyle(el).backgroundImage);
    expect(background).toContain('repeating-linear-gradient');
});

test('dragging an item into another group saves it at once, without a page load', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO drag');
    const betaId = await groupId(divider(grid, 'Beta'));
    const a1Id = await itemRow(grid, 'A1').getAttribute('data-id');
    const navigations = watchDocumentNavigations(page);

    const posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'A1'), itemRow(grid, 'B1'), 'above');
    const request = await posted;
    await expectAjaxOk(request);

    // The request names the item and its new group (the Beta section's ID).
    const sent = new URLSearchParams(request.postData() ?? '');
    expect(sent.get('groupable_item_id')).toBe(a1Id);
    expect(sent.get('groupable_group_key')).toBe(betaId);

    expect(await layout(grid)).toEqual([['Alpha', ['A2']], ['Beta', ['A1', 'B1']], ['Gamma', []], ['No section', ['U1']]]);
    expect(await hiddenGroupOf(itemRow(grid, 'A1'))).toBe(betaId);
    expect(navigations(), 'no document navigation').toEqual([]);
    // Saved already, so the form has nothing unsaved.
    await expect(editForm(page)).not.toHaveClass(/\bchanged\b/);

    // Stored: a fresh page load shows the same grouping, sort order included.
    const reloaded = await openSource(page, 'do', id);
    expect(await layout(reloaded)).toEqual([['Alpha', ['A2']], ['Beta', ['A1', 'B1']], ['Gamma', []], ['No section', ['U1']]]);
});

test('dragging an item into "No section" unassigns it, and into an empty group assigns it', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO unassign');

    let posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'B1'), itemRow(grid, 'U1'), 'above');
    let sent = new URLSearchParams((await posted).postData() ?? '');
    await expectAjaxOk(await posted);
    // The unassigned group has an empty key; the server stores it as no section (SectionID 0).
    expect(sent.get('groupable_group_key')).toBe('');

    // Gamma has no items: dropping on the lower half of its divider puts A2 into it.
    posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'A2'), divider(grid, 'Gamma'), 'below');
    sent = new URLSearchParams((await posted).postData() ?? '');
    await expectAjaxOk(await posted);
    expect(sent.get('groupable_group_key')).toBe(await groupId(divider(grid, 'Gamma')));

    const expected = [['Alpha', ['A1']], ['Beta', []], ['Gamma', ['A2']], ['No section', ['B1', 'U1']]];
    expect(await layout(grid)).toEqual(expected);
    expect(await layout(await openSource(page, 'do', id))).toEqual(expected);
});

test('a new group is created from the inline input (button or Enter) and listed after the others', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO create');
    const input = grid.locator('input.ss-gridfield-new-group-name');
    const button = grid.locator('button.ss-gridfield-create-group-btn');
    await expect(input).toHaveAttribute('placeholder', 'New group name...');

    // An empty name sends nothing and puts the cursor in the input.
    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_create') && requests++);
    await button.click();
    await expect(input).toBeFocused();
    expect(requests, 'no create request for an empty name').toBe(0);

    // With the button: the server creates the section and answers the re-rendered grid.
    await input.fill('Delta');
    let posted = waitForGridPost(page, 'group_create');
    await button.click();
    let json = await expectJsonSuccess(await posted);
    expect((json.group as { name: string }).name).toBe('Delta');
    await expect(divider(grid, 'Delta')).toBeVisible();
    await expect(grid.locator('input.ss-gridfield-new-group-name')).toHaveValue('');

    // With Enter in the input.
    await grid.locator('input.ss-gridfield-new-group-name').fill('Epsilon');
    posted = waitForGridPost(page, 'group_create');
    await grid.locator('input.ss-gridfield-new-group-name').press('Enter');
    json = await expectJsonSuccess(await posted);
    await expect(divider(grid, 'Epsilon')).toBeVisible();

    // New sections get the next sort value, so they come after the existing ones; the new
    // dividers are full groups (handle and delete button).
    const expected = [...SEEDED.slice(0, 3), ['Delta', []], ['Epsilon', []], SEEDED[3]];
    expect(await layout(grid)).toEqual(expected);
    await expect(divider(grid, 'Delta').locator('button.ss-gridfield-group-delete')).toBeVisible();
    expect(await layout(await openSource(page, 'do', id))).toEqual(expected);
});

test('a group title is renamed in place: Enter saves it, Escape cancels without a request', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO rename');
    // Pinned by the section ID: the title text, which divider() matches on, is what changes here.
    const beta = grid.locator(`tbody tr.groupable-bound[data-group-id="${await groupId(divider(grid, 'Beta'))}"]`);
    const title = beta.locator('.group-title-editable');
    const input = beta.locator('input.group-title-input');

    // Escape: the input closes, the old title is back, nothing was sent.
    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_title_update') && requests++);
    await title.click();
    await expect(input).toBeVisible();
    await expect(input).toBeFocused();
    await input.fill('Discarded');
    await input.press('Escape');
    await expect(input).toBeHidden();
    await expect(title).toHaveText('Beta');
    expect(requests, 'no title update after Escape').toBe(0);

    // Enter: saved through a JSON POST, the title text updates in place.
    await title.click();
    await input.fill('Beta renamed');
    const posted = waitForGridPost(page, 'group_title_update');
    await input.press('Enter');
    const request = await posted;
    expect(request.url()).toMatch(new RegExp(`/group_title_update/${await groupId(beta)}$`));
    expect(JSON.parse(request.postData() ?? '{}')).toEqual({ title: 'Beta renamed' });
    await expectJsonSuccess(request);
    await expect(input).toBeHidden();
    await expect(title).toHaveText('Beta renamed');

    expect((await layout(await openSource(page, 'do', id)))[1]).toEqual(['Beta renamed', ['B1']]);
});

test('a group name with markup and quotes is shown as text, not rendered (#9)', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO escape');
    const name = '<b class="injected">Bold</b> & "quoted"';
    const beta = grid.locator(`tbody tr.groupable-bound[data-group-id="${await groupId(divider(grid, 'Beta'))}"]`);
    await beta.locator('.group-title-editable').click();
    await beta.locator('input.group-title-input').fill(name);
    const posted = waitForGridPost(page, 'group_title_update');
    await beta.locator('input.group-title-input').press('Enter');
    await expectJsonSuccess(await posted);

    // After a fresh load the divider is rendered by tmpl.js from the stored name.
    const reloaded = await openSource(page, 'do', id);
    const row = reloaded.locator('tbody tr.groupable-bound').nth(1);
    await expect(row.locator('.group-title')).toHaveText(name);
    await expect(reloaded.locator('b.injected')).toHaveCount(0);
    await expect(row.locator('button.ss-gridfield-group-delete')).toHaveAttribute('title', `Delete ${name}`);
});

test('deleting a group asks first; on yes the section is deleted and its items unassigned', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO delete');
    const alpha = divider(grid, 'Alpha');
    const alphaId = await groupId(alpha);

    // No: nothing is sent, the group stays.
    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_delete') && requests++);
    let asked = expectConfirm(page, false);
    await alpha.locator('button.ss-gridfield-group-delete').click();
    expect(await asked).toContain('"Alpha"');
    expect(await asked).toContain('unassigned');
    expect(requests, 'no delete request after No').toBe(0);
    await expect(divider(grid, 'Alpha')).toHaveCount(1);

    // Yes: deleted on the server, which reports how many items it unassigned.
    asked = expectConfirm(page, true);
    const posted = waitForGridPost(page, 'group_delete');
    await divider(grid, 'Alpha').locator('button.ss-gridfield-group-delete').click();
    await asked;
    const request = await posted;
    expect(request.url()).toMatch(new RegExp(`/group_delete/${alphaId}$`));
    const json = await expectJsonSuccess(request);
    expect(json.message).toBe('Group deleted. 2 item(s) unassigned.');

    const expected = [['Beta', ['B1']], ['Gamma', []], ['No section', ['A1', 'A2', 'U1']]];
    await expect(divider(grid, 'Alpha')).toHaveCount(0);
    expect(await layout(grid)).toEqual(expected);
    expect(await layout(await openSource(page, 'do', id))).toEqual(expected);
});

test('dragging a group divider moves the whole group, items included, and stores the new order', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO reorder');
    const ids = {
        alpha: await groupId(divider(grid, 'Alpha')),
        beta: await groupId(divider(grid, 'Beta')),
        gamma: await groupId(divider(grid, 'Gamma')),
    };

    const posted = waitForGridPost(page, 'group_reorder');
    await dragRow(page, divider(grid, 'Beta'), divider(grid, 'Alpha'), 'above');
    const request = await posted;
    const sent = new URLSearchParams(request.postData() ?? '');
    expect(sent.getAll('group_order[]')).toEqual([ids.beta, ids.alpha, ids.gamma]);
    await expectJsonSuccess(request);

    // B1 travelled with its group; the unassigned group stays last.
    const expected = [['Beta', ['B1']], ['Alpha', ['A1', 'A2']], ['Gamma', []], ['No section', ['U1']]];
    await expect.poll(() => layout(grid)).toEqual(expected);
    expect(await layout(await openSource(page, 'do', id))).toEqual(expected);
});

test('a custom group action runs its server handler and the grid re-renders with the result', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO action');
    const beta = divider(grid, 'Beta');
    const posted = waitForGridPost(page, 'group_action');
    await beta.locator('button.ss-gridfield-group-action[data-action="stamp"]').click();
    const request = await posted;
    expect(request.url()).toMatch(new RegExp(`/group_action/${await groupId(beta)}/stamp$`));
    const json = await expectJsonSuccess(request);
    expect(json.message).toBe('Stamped Beta');

    // The fixture handler sets the section's Code, which the re-rendered divider shows as its badge.
    await expect(divider(grid, 'Beta').locator('.badge')).toHaveText('STAMPED');
    await expect(divider(grid, 'Alpha').locator('.badge')).toHaveText('AL');
    await expect(divider(await openSource(page, 'do', id), 'Beta').locator('.badge')).toHaveText('STAMPED');
});

test('deferred saving (OrderableRows immediate update off): a drag marks the form changed and Save stores it', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'do', 'DO deferred', 'deferred');
    await expect(grid).not.toHaveAttribute('data-immediate-update', '1');
    const betaId = await groupId(divider(grid, 'Beta'));

    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_assignment') && requests++);
    await dragRow(page, itemRow(grid, 'A1'), itemRow(grid, 'B1'), 'above');
    expect(await layout(grid)).toEqual([['Alpha', ['A2']], ['Beta', ['A1', 'B1']], ['Gamma', []], ['No section', ['U1']]]);
    expect(requests, 'nothing saved before the form is').toBe(0);
    // The new group is in the row's hidden input, which the form save submits.
    expect(await hiddenGroupOf(itemRow(grid, 'A1'))).toBe(betaId);
    await expect(editForm(page)).toHaveClass(/\bchanged\b/);

    await saveForm(page);
    const after = await layout(await openSource(page, 'do', id));
    // GridFieldGroupable::handleSave stores the group; the order within it is OrderableRows' business.
    expect(after.map(([name, items]) => [name, [...items].sort()])).toEqual([['Alpha', ['A2']], ['Beta', ['A1', 'B1']], ['Gamma', []], ['No section', ['U1']]]);
});

// Known bug, kept visible: the non-inline add-group button targets a modal that is never rendered,
// so it does nothing.
test.fixme('without the inline input, "Add Group" opens a dialog to name the group (https://github.com/restruct/silverstripe-groupablegridfield/issues/14)', async ({ page }) => {
    const { grid } = await freshSource(page, 'do', 'DO modal', 'modal');
    await expect(grid.locator('input.ss-gridfield-new-group-name')).toHaveCount(0);
    await grid.locator('button.ss-gridfield-add-group-modal-btn').click();
    await expect(page.locator('.modal.show')).toBeVisible();
});
