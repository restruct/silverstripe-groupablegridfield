# Upgrading

## To 4.x from 2.x

Most projects need only the constraint change: `"restruct/silverstripe-groupable-gridfield": "^4"`.
The code is 2.4.1 plus the changes below.

1. **Silverstripe 5 or 6 is required.** Silverstripe 4 projects stay on `^2`.
2. **Group deletion order ('unassign' mode, DataObject mode).** 4.0 unassigns the items, then
   deletes the group record while it is still linked to its owner, then removes a many_many (or
   many_many through) link, all in one database transaction. If your group class's
   `onBeforeDelete()` or `onAfterDelete()` assumed the group was already unlinked from its owner, it
   now sees it still linked. The usual reason to care is the opposite one: code that needs the owner
   in `onBeforeDelete()`, or that vetoes a delete by throwing (the whole deletion, unassignment
   included, is then rolled back), now works as expected. 'callback' mode is unchanged.
3. **`getGroupSortTable()`** takes an untyped list parameter instead of `SS_List` (whose class
   name differs between Silverstripe 5 and 6). Only relevant if you override it in a subclass:
   drop the type from your signature. A groups method returning a plain `DataList` keeps working.
4. **Legacy-mode boundary drags need `canEdit()` on the source record.** Dragging a group divider
   writes the source record; it now answers 403 when the member cannot edit that record (before,
   only the item class was checked).
5. **Flush after upgrading**, as for any module update.

## To 4.x from 3.0.0

3.0.0 was an unfinished Silverstripe 6 port of 2.0.0. 4.x is the 2.x line ported to Silverstripe 6,
so upgrading from 3.0.0 also brings in everything added in 2.1 to 2.4 (see the `v2` history and
the README). The points above apply, plus:

- `GroupableDataField::getValues()` returns `null` for an empty value again, as on 2.x (3.0.0
  returned `[]`).
- The legacy-mode divider inputs are namespaced under the grid name
  (`{GridName}[{groupsField}][key][]`, since 2.4.0). A custom divider template still submitting
  top-level `{groupsField}[key][]` inputs keeps working through a fallback.
- Immediate-vs-deferred saving follows `GridFieldOrderableRows::setImmediateUpdate()` (since 2.4.0);
  do not rely on `GridFieldGroupable::$immediateUpdate`.
