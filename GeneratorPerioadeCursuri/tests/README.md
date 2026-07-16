# GeneratorPerioadeCursuri tests

Run commands from `/opt/espo_extensions`. None of these suites calls a
WordPress endpoint.

## Safe source and package suite

This is the default credential-free check. It executes the parser, merge,
transport doubles, server/client contracts, error-quality regression, build,
and package inventory checks.

```bash
bash GeneratorPerioadeCursuri/tests/run-safe.sh
```

## Persistent EspoCRM integration suite

This suite uses the existing Compose installation, real EspoCRM services,
native attachment storage, and the installed PhpSpreadsheet library. Every
record and attachment has a unique `AUTOTEST-GPC-` name and is removed by exact
ID in a `finally` block.

```bash
bash GeneratorPerioadeCursuri/tests/integration/run.sh
```

`ESPO_RUNTIME_ROOT` may override `/opt/crm.cursurituv.ro`. Do not point it at a
production installation unless creating and immediately deleting isolated test
records is permitted.

## Authenticated browser suite

The browser suite uses the API only for isolated record setup and exact
teardown. Validation, native file selection, preview rendering, reload,
responsive table behavior, accessibility state, and localized failure messages
are checked through the visible UI. A context-wide route guard fails the suite
if it attempts a WordPress mutation or an updater `connect`, `fetchDates`, or
`updateRow` action.

The official Playwright image supplies Chromium and its system libraries, so no
browser package needs to be installed system-wide. The commands below keep
credentials out of shell history and command-line arguments:

```bash
cd /opt/espo_extensions/GeneratorPerioadeCursuri/tests/browser
read -r -p 'Espo username: ' ESPO_ADMIN_USERNAME
read -r -s -p 'Espo password: ' ESPO_ADMIN_PASSWORD; printf '\n'
export ESPO_ADMIN_USERNAME ESPO_ADMIN_PASSWORD
docker run --rm --network host \
  -e ESPO_BASE_URL=https://crm.cursurituv.ro \
  -e ESPO_ADMIN_USERNAME -e ESPO_ADMIN_PASSWORD \
  -v "$PWD:/work" -w /work \
  mcr.microsoft.com/playwright:v1.61.1-noble \
  sh -lc 'npm ci && npx playwright test --config=playwright.config.mjs'
unset ESPO_ADMIN_USERNAME ESPO_ADMIN_PASSWORD
```

Failure screenshots are written under `/tmp/gpc-playwright-results` inside the
one-off container. Add a separate host bind for that directory when artifacts
must survive container removal.
