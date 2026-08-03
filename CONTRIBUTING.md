# Contributing

## Local setup

Development runs in [DDEV](https://ddev.readthedocs.io/) — PHP 8.3, MariaDB 11.8, docroot
`public/`:

```bash
ddev start
ddev composer install
```

The dev site configuration lives in `config/` (`config/sites/main/config.yaml`,
`config/system/`). It is `export-ignore`d, so it never ships in a release archive.

## Tests

```bash
ddev composer tests:unit         # phpunit --testsuite Unit
ddev composer tests:functional   # phpunit --testsuite Functional
ddev composer tests              # both
```

Unit tests run anywhere PHP does. **Functional tests need the database**, and the credentials in
`phpunit.xml` are pre-baked for DDEV:

```
typo3DatabaseDriver=pdo_mysql   typo3DatabaseName=db
typo3DatabaseHost=db            typo3DatabaseUsername=root
typo3DatabasePort=3306          typo3DatabasePassword=root
```

Running them outside DDEV means overriding those environment variables.

Coverage needs Xdebug:

```bash
ddev xdebug on
ddev exec XDEBUG_MODE=coverage vendor/bin/phpunit --testsuite Unit --coverage-html coverage
```

### Two things that will catch you out

- **`EventRestriction` and `EntryRestriction` survive `removeAll()`.** They are enforced
  restrictions registered globally, so they are appended to your test's own query and your
  fixtures will look like they lost rows. Remove them explicitly with `removeByType()` — see
  [Access Control](Documentation/AccessControl.md#they-survive-removeall).
- **Extension configuration must be in `TYPO3_CONF_VARS` before the DI container is compiled.**
  `Tests/Functional/AbstractCalendarFunctionalTestCase` does this; conditional service
  registration (the requirements widget) depends on it.

## Static analysis

```bash
ddev composer sca      # fixes in place
ddev composer ci:sca   # dry-run, writes checkstyle reports
```

Both run: composer-normalize, editorconfig-cli, `php -l`, php-cs-fixer, PHPStan (level 6 over
`Classes Configuration Resources Tests`), translation validation of
`Resources/Private/Language`, typoscript-lint, and yaml-lint.

`composer php:stan` **regenerates** `phpstan-baseline.neon` rather than checking against it. To
check, run `ddev composer ci:php:stan` or `vendor/bin/phpstan` directly.

## Frontend assets

The backend calendar view is bundled with esbuild:

```bash
npm install
npm run build   # minified, no sourcemaps
npm run watch   # sourcemaps, rebuild on change
```

`Resources/Private/TypeScript/calendar.ts` → `Resources/Public/JavaScript/calendar.js` **and**
`calendar.css` (the CSS is emitted because the entrypoint imports `@event-calendar/core/index.css`).

**Built assets are committed.** `.gitignore` excludes `public` but re-includes
`!Resources/Public`, so run the build and commit its output with your change. There is no
`package-lock.json` in the repo.

Two files in `Resources/Public/JavaScript/` are hand-written and have no TypeScript source —
edit them directly: `recordlist-event-status-modal.js`, `usersettings-notification-categories.js`.

## Class diagram

`Documentation/Images/calendar.png` is generated from `calendar.puml`:

```bash
plantuml Documentation/Images/calendar.puml
```

Regenerate it when the domain model changes.

## Comment style

Comments explain **why**, never what. A comment that restates the line below it, or narrates a
change ("added X", "fixed Y", "was Z before"), gets deleted in review. Non-obvious invariants,
TYPO3-core quirks, workarounds with their reason, and ordering constraints are all worth writing
down — `Tests/` is a good reference for the tone.

## CI

| Workflow | Runs |
|----------|------|
| `tests.yml` | Unit tests on PHP 8.2/8.3/8.4 × TYPO3 13.4; functional tests on the same matrix via DDEV; a coverage job on PHP 8.3 uploading `unit` and `functional` flags to Codecov |
| `sca.yml` | `composer ci:sca`, annotating the PR from the php-cs-fixer and PHPStan checkstyle reports |
| `notifications.yml` | Teams notification |

Triggered on pushes to `main` and `renovate/**`, and on PRs to `main`. Secrets required:
`CODECOV_TOKEN`, `TEAMS_WEBHOOK_URL`.

Codecov is configured to fail on a project coverage regression beyond 1%; patch coverage has a
70% target but is informational.
