import {
    test,
    expect,
    divider,
    dragRow,
    editForm,
    expectAjaxOk,
    expectConfirm,
    freshSource,
    hiddenGroupOf,
    itemRow,
    layout,
    openSource,
    saveForm,
    waitForGridPost,
    watchDocumentNavigations,
} from './support';

// MultiValue mode: the groups are key => name pairs in the source's Sections MultiValueField, and
// each item's group key is the 'Section' many_many extra field of the source's Items relation (the
// README's MultiValue example). GridFieldAddNewGroupButton is added AFTER GridFieldGroupable, which
// since 2.4 still switches the dividers to the editable ("enhanced") template.
// Seed: alpha => Alpha (I1, I2), beta => Beta (I3), I4 unassigned ("none").

const SEEDED = [
    ['Alpha', ['I1', 'I2']],
    ['Beta', ['I3']],
    ['none', ['I4']],
];

test('renders editable dividers from the MultiValueField, with each item under its join-table group', async ({ page }) => {
    const { grid } = await freshSource(page, 'mv', 'MV render');
    expect(await layout(grid)).toEqual(SEEDED);

    // Enhanced divider: hidden key + editable name input (namespaced under the grid name, so they
    // reach GridFieldGroupable::handleSave in the grid's value), a remove button and a drag handle.
    const alpha = divider(grid, 'Alpha');
    await expect(alpha).toHaveClass(/groupable-advanced-bound/);
    await expect(alpha.locator('input.group-key')).toHaveAttribute('name', 'Items[Sections][key][]');
    await expect(alpha.locator('input.group-key')).toHaveValue('alpha');
    await expect(alpha.locator('input.group-val')).toHaveAttribute('name', 'Items[Sections][val][]');
    await expect(alpha.locator('input.group-val')).toBeEditable();
    await expect(alpha.locator('button.ss-gridfield-delete-groups-divider')).toBeVisible();
    await expect(alpha.locator('.col-reorder .handle')).toBeVisible();

    // The unassigned divider's inputs are disabled, so it never submits a group.
    const none = divider(grid, 'none');
    await expect(none.locator('input.group-key')).toBeDisabled();
    await expect(none.locator('input.group-val')).toBeDisabled();

    expect(await hiddenGroupOf(itemRow(grid, 'I3'))).toBe('beta');
    await expect(grid.locator('button.ss-gridfield-add-new-group')).toHaveText(/Add Section/);
});

test('dragging an item into another group writes the many_many extra field at once', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV drag');
    const i1Id = await itemRow(grid, 'I1').getAttribute('data-id');
    const navigations = watchDocumentNavigations(page);

    const posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'I1'), itemRow(grid, 'I3'), 'above');
    const request = await posted;
    await expectAjaxOk(request);
    const sent = new URLSearchParams(request.postData() ?? '');
    expect(sent.get('groupable_item_id')).toBe(i1Id);
    expect(sent.get('groupable_group_key')).toBe('beta');

    const expected = [['Alpha', ['I2']], ['Beta', ['I1', 'I3']], ['none', ['I4']]];
    expect(await layout(grid)).toEqual(expected);
    expect(navigations(), 'no document navigation').toEqual([]);
    await expect(editForm(page)).not.toHaveClass(/\bchanged\b/);
    expect(await layout(await openSource(page, 'mv', id))).toEqual(expected);
});

test('"Add Section" adds an unsaved group at the top, which Save stores in the MultiValueField', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV add');
    await grid.locator('button.ss-gridfield-add-new-group').click();

    const added = grid.locator('tbody tr.groupable-bound').first();
    await expect(added.locator('input.group-val')).toHaveValue('…');
    // A generated key, and a notice that items can only be added once the record is saved.
    await expect(added.locator('input.group-key')).toHaveValue(/^group_\d+_\d+$/);
    await expect(added.locator('.alert-warning')).toBeVisible();
    await expect(editForm(page)).toHaveClass(/\bchanged\b/);

    await added.locator('input.group-val').fill('Delta');
    await saveForm(page);

    const after = await layout(await openSource(page, 'mv', id));
    expect(after).toEqual([['Delta', []], ...SEEDED]);
});

test('removing a group divider asks first; after Save the group is gone and its items show as unassigned', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV remove');

    let asked = expectConfirm(page, false);
    await divider(grid, 'Beta').locator('button.ss-gridfield-delete-groups-divider').click();
    expect(await asked).toContain('"Beta"');
    await expect(divider(grid, 'Beta')).toHaveCount(1);

    asked = expectConfirm(page, true);
    await divider(grid, 'Beta').locator('button.ss-gridfield-delete-groups-divider').click();
    await asked;
    await expect(divider(grid, 'Beta')).toHaveCount(0);

    await saveForm(page);
    // I3 keeps its key 'beta' in the join table, but no group has that key any more.
    const after = await layout(await openSource(page, 'mv', id));
    expect(after.map(([name]) => name)).toEqual(['Alpha', 'none']);
    expect(after[1][1].sort()).toEqual(['I3', 'I4']);
});

test('after dragging a whole group, an item dropped into it is saved with that group (2.4.1)', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV group drag');

    // Whole-group drag in MultiValue mode only reorders the dividers in the form (no request).
    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_') && requests++);
    await dragRow(page, divider(grid, 'Beta'), divider(grid, 'Alpha'), 'above');
    expect(await layout(grid)).toEqual([['Beta', ['I3']], ['Alpha', ['I1', 'I2']], ['none', ['I4']]]);
    expect(requests, 'a group drag sends nothing by itself').toBe(0);
    await expect(editForm(page)).toHaveClass(/\bchanged\b/);

    // The Beta divider is now a clone made for the drag; dropping I4 under it must still send
    // Beta's key. Before 2.4.1 the clone had lost it and the item was saved unassigned.
    const posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'I4'), itemRow(grid, 'I3'), 'above');
    const request = await posted;
    await expectAjaxOk(request);
    expect(new URLSearchParams(request.postData() ?? '').get('groupable_group_key')).toBe('beta');
    expect(await hiddenGroupOf(itemRow(grid, 'I4'))).toBe('beta');

    // Save stores the new group order in the MultiValueField.
    await saveForm(page);
    const after = await layout(await openSource(page, 'mv', id));
    expect(after.map(([name, items]) => [name, [...items].sort()])).toEqual([['Beta', ['I3', 'I4']], ['Alpha', ['I1', 'I2']], ['none', []]]);
});

test('deferred saving: a drag marks the form changed and Save writes the many_many extra field', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV deferred', 'deferred');
    let requests = 0;
    page.on('request', (r) => r.url().includes('/group_assignment') && requests++);

    await dragRow(page, itemRow(grid, 'I1'), itemRow(grid, 'I3'), 'above');
    expect(await layout(grid)).toEqual([['Alpha', ['I2']], ['Beta', ['I1', 'I3']], ['none', ['I4']]]);
    expect(requests, 'nothing saved before the form is').toBe(0);
    await expect(editForm(page)).toHaveClass(/\bchanged\b/);

    await saveForm(page);
    const after = await layout(await openSource(page, 'mv', id));
    expect(after.map(([name, items]) => [name, [...items].sort()])).toEqual([['Alpha', ['I2']], ['Beta', ['I1', 'I3']], ['none', ['I4']]]);
});

test('without the add-group button the dividers are display-only, and items still drag between groups', async ({ page }) => {
    const { id, grid } = await freshSource(page, 'mv', 'MV plain', 'plain');
    expect(await layout(grid)).toEqual(SEEDED);
    await expect(grid.locator('button.ss-gridfield-add-new-group')).toHaveCount(0);
    // Plain divider template: the name as text, no inputs, no remove button, no group handle.
    await expect(grid.locator('tbody tr.groupable-bound input, tbody tr.groupable-bound button')).toHaveCount(0);
    await expect(grid.locator('tbody tr.groupable-bound .handle')).toHaveCount(0);
    await expect(divider(grid, 'Alpha').locator('strong')).toHaveText('Alpha');

    const posted = waitForGridPost(page, 'group_assignment');
    await dragRow(page, itemRow(grid, 'I4'), itemRow(grid, 'I2'), 'below');
    await expectAjaxOk(await posted);
    const expected = [['Alpha', ['I1', 'I2', 'I4']], ['Beta', ['I3']], ['none', []]];
    expect(await layout(grid)).toEqual(expected);
    expect(await layout(await openSource(page, 'mv', id))).toEqual(expected);
});

// Known bug, kept visible: the notice's default text is the Dutch string, so an English CMS shows
// Dutch (the _t() default and comment arguments are swapped).
test.fixme('the unsaved-group notice is in the CMS language (https://github.com/restruct/silverstripe-groupablegridfield/issues/15)', async ({ page }) => {
    const { grid } = await freshSource(page, 'mv', 'MV notice');
    await grid.locator('button.ss-gridfield-add-new-group').click();
    await expect(grid.locator('tbody tr.groupable-bound').first().locator('.alert-warning')).toHaveText(
        'Save mv source before adding items to this (unsaved) section',
    );
});
