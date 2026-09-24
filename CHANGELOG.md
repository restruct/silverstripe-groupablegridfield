# Changelog

## 4.0.0 (2026-09-24)

Silverstripe 5 and 6 from one codebase. This line is the `v2` code (2.4.1) made to run on
Silverstripe 6, not a continuation of the `3.0.0` tag, which was an unfinished Silverstripe 6 port
of 2.0.0. See [UPGRADING.md](UPGRADING.md).

### Breaking

- **Silverstripe 4 is no longer supported.** `silverstripe/framework` is `^5 || ^6`,
  `symbiote/silverstripe-gridfieldextensions` `^4 || ^5`, `symbiote/silverstripe-multivaluefield`
  `^6 || ^7`. Silverstripe 4 projects keep resolving the 2.x tags. `silverstripe/vendor-plugin`
  `^2 || ^3` is now declared (the `client/` expose depends on it; framework already pulled it in).
- **Group deletion order ('unassign' mode, DataObject mode).** The items are unassigned, then the
  group is deleted while it is still linked to its owner, then a many_many (or many_many through)
  link is removed, all in one database transaction. Before, the group was unlinked from its owner
  before `delete()` ran (#11).
- **`getGroupSortTable()`'s parameter is untyped** (was `SilverStripe\ORM\SS_List`, which has a
  different class name on Silverstripe 6). Calls are unaffected; a subclass that overrides it must
  drop the `SS_List` type from its signature.
- **From 3.0.0 only:** `GroupableDataField::getValues()` no longer turns an empty value into `[]`;
  it returns what `MultiValueField::getValues()` returns (`null` when empty), as on 2.x.

### Fixed

- Silverstripe 6: `GroupableDataField` fataled at class load (its `scaffoldFormField()` signature
  did not match multivaluefield 7), and `ArrayData`, `FormField::Value()` and
  `Controller::has_curr()` no longer exist there. All are replaced with forms that work on 5 and 6.
- Group reordering failed whenever the group sort field lives on the group record (a has_many, or
  a many_many without the field in its extraFields): `getGroupSortTable()` called
  `DataObject::hasOwnTableDatabaseField()`, which exists in neither Silverstripe 5 nor 6, so
  `handleGroupReorder()` answered "Error reordering groups". On Silverstripe 6 the `SS_List` type
  made it throw before that. Many_many groups with the sort field in extraFields were not affected.
- A group's `onBeforeDelete()` could not resolve its owner, and a veto thrown there left an unlinked
  but still existing group with its items already unassigned (#11). It now sees its owner, and a
  veto rolls the whole deletion back, item unassignment included. A group class with
  `cascade_deletes` on its items keeps those items: they are unassigned before the group is deleted.
- 'prevent' mode left the many_many join row of a deleted group behind.
- Silverstripe 5: a legacy-mode save no longer raises `Controller::has_curr()`'s deprecation notice
  when the groups arrive in the grid value; the controller is only looked up for the pre-2.4
  top-level request-var fallback.
- README: the DataObject-mode example chained the component setters onto the `GridFieldConfig`
  (which fails), and documented a `setGroupDeleteHandler()` method that does not exist; the handler
  is the second argument of `setGroupDeleteBehavior('callback', $handler)`.

### Added

- Everything released on `v2` since 2.0.0, which the `3.0.0` tag did not have: DataObject group
  mode (2.2.0), metadata rendering for group rows (2.3.0), the OrderableRows interplay fixes and
  test suite (2.4.0) and group identity surviving whole-group drags (2.4.1). See the `v2` history.
- Tests for the code paths that differ between Silverstripe 5 and 6 (`CrossMajorCompatTest`) and
  for group deletion (`GroupDeleteTest`), and CI running the suite on Silverstripe 5 and 6.
- `composer.json` `funding`, `.gitattributes` keeping `tests/`, `docs/` and CI files out of dist
  installs.

### Issues

- Fixed here: #8 (port the 2.x work to the Silverstripe 6 line), #11 (delete order), #12
  (`handleGroupAssignment` never forwarded to `GridFieldOrderableRows`; already fixed on `v2` in
  2.4.0 and carried over), and the dependabot alert on master's `phpunit/phpunit ^5.7` dev
  dependency (replaced by `silverstripe/recipe-testing`).
- Not reproduced: #9. `tmpl.js` escapes `{%=...%}` interpolation (`"`, `<`, `>`, `&`), so a group
  name with quotes or markup renders as text in the divider templates.
- Still open: #10 (API cleanup), not done in this release.

## 3.0.0 (2025-10-27)

Unfinished Silverstripe 6 port of 2.0.0 (`^6` only). Superseded by 4.0.0.

## 2.x

See the `v2` branch.
