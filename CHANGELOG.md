# Changelog (2.x)

The `v2` line (Silverstripe 4 and 5). Releases before 2.4.3 are described in their tags and
commits; `main` (4.x) has its own CHANGELOG.

## 2.4.3 (unreleased)

### Security

- **The group endpoints now check the form's CSRF token** (#18, same fix as 4.1.1 on `main`).
  `group_create`, `group_delete`, `group_title_update`, `group_reorder`, `group_action` and
  `group_assignment` checked `canEdit()` but not the form's `SecurityID`, so only the session
  cookie's `SameSite=Lax` default kept a cross-site POST from creating, deleting, renaming or
  reordering groups as a logged-in editor. Each endpoint now answers 400 (core's CSRF message)
  before it does anything, the way core's own GridField actions do, unless the request carries the
  token of the form the grid is in. A form that disabled its token (`disableSecurityToken()`) is
  not checked, as in core. `client/js/groupable.js` sends the token in the `X-SecurityID` header
  with every group request; drags already sent it with the form data.

### Changed

- Code of your own that posts to a group endpoint (a custom script, or a copy of
  `groupable.js`) must send the form's `SecurityID`, as the `SecurityID` request variable or the
  `X-SecurityID` header, or it gets a 400.
