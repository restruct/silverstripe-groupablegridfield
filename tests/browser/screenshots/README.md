# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts` (in this directory), which
the browser tests and CI never load (their config only reads `../specs`), so a normal test run never
rewrites the images. Never edit the PNGs by hand; regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed and the browser fixtures from
  `tests/browser/fixtures/` copied into its `app/src/`, then the database built.
  `GgBShowcaseAdmin` seeds the demo content on request: a "Help centre FAQ" record (DataObject
  mode) and an "Event programme" record (MultiValue mode), opened in the fixture ModelAdmin;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

The shared runner in the maintainers' workspace does all of that, from the workspace root:

```bash
BROWSER_ONLY=ss6 tools/browser/run.sh silverstripe-groupablegridfield --config=screenshots/playwright.screenshots.config.ts
```

Against a host you started yourself, from `tests/browser/`:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8882" npx playwright test --config=screenshots/playwright.screenshots.config.ts
```

The shots leave out the CMS menu (the test host lists the fixtures' own admin sections there). There
is no still of a whole group being dragged; `readme.shot.ts` says why.
