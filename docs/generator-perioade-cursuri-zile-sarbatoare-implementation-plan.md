# GeneratorPerioadeCursuri and ZileSarbatoare Implementation Plan

Status: Proposed — awaiting approval

Date: 2026-07-18

Current versions:

- `GeneratorPerioadeCursuri` 2.3.4
- `ZileSarbatoare` 0.7.7

Proposed release versions:

- `GeneratorPerioadeCursuri` 2.4.0
- `ZileSarbatoare` 0.8.0

## 1. Objective

Deliver three bounded tasks without mixing their causes or responsibilities:

1. Investigate the Content Security Policy reports observed after installing
   `GeneratorPerioadeCursuri` 2.3.4, and change repository code only if the
   extension is proven to create or modify the responsible setting.
2. Add an explicit **Preia zilele** action to the
   `GeneratorPerioadeCursuri` create-page holiday repeater. The action must
   read already stored Romanian holiday dates through a browser endpoint owned
   by `ZileSarbatoare`, while preserving all manual repeater behavior.
3. Correct the `ZileSarbatoare` record detail presentation so dates always
   include the year and source years are displayed without digit grouping,
   without changing the stored date or integer contracts when the raw values
   are correct.

The work will be implemented in independently reviewable phases. Each phase
has its own evidence, file boundary, tests, and exit criteria so that findings
from one task cannot silently broaden another task.

## 2. Scope and non-goals

### In scope

- focused installer/CSP root-cause investigation;
- a read-only holiday lookup endpoint in `ZileSarbatoare`;
- button-driven import into the existing `GeneratorPerioadeCursuri` holiday
  repeater;
- controlled client handling for missing, unavailable, forbidden, invalid,
  malformed, failed, and empty holiday lookup outcomes;
- presentation-only correction of `ZileLibere.dateStart` and
  `ZileLibere.sourceYear` when runtime evidence confirms that storage is
  already correct;
- targeted offline, contract, API-action, and runtime validation;
- version, package-contract, and existing release-documentation updates after
  the implementation is accepted.

### Explicit non-goals

- calling Nager.Date from `GeneratorPerioadeCursuri`;
- starting synchronization from `GeneratorPerioadeCursuri`;
- duplicating the month/year/country filtering owned by
  `ZileLibereCalendarService`;
- introducing a compile-time PHP dependency from
  `GeneratorPerioadeCursuri` to a `ZileSarbatoare` class;
- replacing, removing, or making the manual holiday repeater dependent on
  `ZileSarbatoare`;
- changing `sourceYear` to `varchar` to influence presentation;
- changing stored holiday dates or running a data migration without evidence
  of a storage defect;
- changing production web-server, reverse-proxy, CDN, or firewall
  configuration;
- suppressing CSP reports instead of finding their source;
- adding a dependency, database table, entity, field, synchronization path,
  service, or infrastructure component.

## 3. Confirmed facts

### 3.1 GeneratorPerioadeCursuri installation and CSP

- The captured policy is `Content-Security-Policy-Report-Only`; the browser
  reports the violations but the policy shown in the trace does not enforce a
  block.
- The policy affects EspoCRM core scripts, API requests, templates, WebSocket
  traffic, and extension assets globally. It is not isolated to the
  `GeneratorPerioadeCursuri` create page.
- No `Content-Security-Policy`, `script-src`, or `connect-src` directive is
  present in either extension's repository files.
- `GeneratorPerioadeCursuri/scripts/AfterInstall.php` writes only `tabList`
  through `ConfigWriter`, then calls `save()` when the computed value differs.
- Version 2.3.3 already used the same `ConfigWriter->set('tabList', ...)` and
  `save()` mechanism. Version 2.3.4 changed menu-entry filtering; it did not
  introduce configuration persistence.
- EspoCRM 10.0.3's real `ConfigWriter` reads the applicable configuration
  arrays, applies pending changes, refreshes cache/microtime values, and writes
  an updated configuration file. A `tabList` change therefore causes the main
  configuration file to be rewritten, although unrelated array keys are
  retained by the implementation.
- The repository's `menu-lifecycle.php` uses a simplified `ConfigWriter` test
  double. It verifies submitted values and save counts, but it cannot establish
  real file-serialization behavior or response-header behavior.

Reference for the matching core behavior:

- [EspoCRM 10.0.3 ConfigWriter](https://github.com/espocrm/espocrm/blob/10.0.3/application/Espo/Core/Utils/Config/ConfigWriter.php)

### 3.2 Holiday lookup ownership

- `ZileLibereCalendar` is already bound to `ZileLibereCalendarService` in
  `ZileSarbatoare/Binding.php`.
- `getDateLiberePentruLuni` already returns sorted, unique ISO dates for exact
  year, selected months, and country.
- `EspoZileLibereRepository` reads stored `ZileLibere` records only.
- The calendar service does not contact Nager.Date or initiate
  synchronization.
- There is no existing browser-callable route for
  `getDateLiberePentruLuni`.
- The Generator holiday field stores a comma-separated internal value in
  `DD.MM.YYYY` form and already owns manual rows, date pickers, parsing,
  serialization, validation, and internal/display date conversion.
- `selectedMonths` values originate in the client model as strings, while the
  calendar service accepts integer months only.

### 3.3 ZileLibere storage and presentation

- `dateStart` is defined as a required Espo `date` field.
- `sourceYear` is defined as a read-only Espo `int` field.
- The managed synchronization write DTO declares `date` as `string` and
  `sourceYear` as `int`.
- `EspoHolidayStore` writes `$data->date` to `dateStart` and
  `$data->sourceYear` to `sourceYear` without presentation conversion.
- Stored holiday reconciliation reads `dateStart` as a string and
  `sourceYear` as an integer.
- The synchronization fixture contains Good Friday as
  `"date": "2026-04-10"`; synchronization derives the integer source year
  `2026` from the first four characters of that ISO date.
- `sourceYear` participates in the `managedSyncScope` index and the managed
  synchronization query. Its integer storage contract is functional, not
  presentational.
- `ZileLibereCalendarService` filters by the year contained in the ISO
  `dateStart` value. Its contract depends on the stored date remaining an ISO
  date and does not require a schema change.
- The custom `zile-libere-detail.js` modal does not format fields. It disables
  the Full Form action before calling the standard Espo detail-modal setup.
- The packaged detail layout only selects field placement; it does not define
  date or number formatting.
- No custom date or integer field view currently exists in `ZileSarbatoare`.
- EspoCRM 10.0.3's standard date detail renderer uses a readable format and
  omits the year when the value is in the current year. Setting the standard
  `useNumericFormat` field parameter makes it use the configured full display
  date instead.
- EspoCRM 10.0.3's standard integer renderer inserts the configured thousands
  separator. Setting its standard `disableFormatting` parameter returns the
  integer's ungrouped string representation.

References for the matching core field behavior:

- [EspoCRM 10.0.3 date field view](https://github.com/espocrm/espocrm/blob/10.0.3/client/src/views/fields/date.ts)
- [EspoCRM 10.0.3 integer field view](https://github.com/espocrm/espocrm/blob/10.0.3/client/src/views/fields/int.ts)

## 4. Unconfirmed facts and decision gates

### 4.1 CSP source remains unconfirmed

The console trace does not identify the layer that emitted the response
header. The source could be EspoCRM/PHP configuration, Apache/Nginx, a reverse
proxy, a CDN, or other middleware. `AfterInstall.php` remains relevant because
it can trigger a main configuration rewrite, but it is not a confirmed root
cause.

No CSP fix will be proposed or implemented until the response header's source
and the before/after configuration difference are captured.

### 4.2 Attached ZileLibere record payload requires live verification

The workspace has no authenticated EspoCRM runtime or exported API response
for the attached record. Repository code and tests establish the intended
storage contract, but they do not prove the exact live record's response.

Before changing presentation metadata, inspect the authenticated response for
the affected record:

```text
GET /api/v1/ZileLibere/{recordId}
```

The pre-change gate expects:

```json
{
  "dateStart": "2026-04-10",
  "sourceYear": 2026
}
```

Also inspect the modal's live model before rendering and confirm:

```text
model.get('dateStart') === '2026-04-10'
model.get('sourceYear') === 2026
```

No authentication token, cookie, or other secret will be copied into test
output or documentation.

Decision gate:

- If the raw API and model values match the expected ISO string and integer,
  classify both symptoms as UI formatting defects and use the metadata-only
  correction in Section 7.
- If either raw value is already formatted as `10 Apr` or `2,026`, stop the
  presentation phase and trace server serialization before changing metadata.
- If the stored values themselves are wrong, report a storage defect and seek
  approval for a revised plan before any schema, synchronization, or data
  change.

Current classification: **no storage defect is confirmed**. Repository and
EspoCRM core evidence explain both observed values as standard UI formatting,
but the affected live record must pass the raw API/model gate before that
classification is finalized.

## 5. Architecture and responsibility boundaries

### 5.1 Holiday lookup data flow

```text
Generator holidays field
    -> POST /api/v1/ZileLibere/availableDates
        -> ZileSarbatoare API action
            -> ZileLibereCalendar::getDateLiberePentruLuni(
                   year,
                   integerMonths,
                   'RO'
               )
                -> EspoZileLibereRepository
                    -> stored ZileLibere records
    <- {"dates": ["YYYY-MM-DD", ...]}
    -> validate response
    -> convert to DD.MM.YYYY
    -> merge into current manual repeater DOM
```

Ownership rules:

- `ZileSarbatoare` owns stored holiday lookup, input validation at its HTTP
  boundary, authorization, country selection, and the response contract.
- `ZileLibereCalendarService` remains the sole owner of exact
  year/month/country filtering and date deduplication.
- `GeneratorPerioadeCursuri` owns button interaction, create-form validation,
  error presentation, date conversion for its field, and merge behavior.
- The integration dependency is the authenticated route and JSON contract.
  Generator PHP code does not import, type-reference, construct, or inject a
  ZileSarbatoare class.
- The manual repeater continues to operate entirely locally and does not check
  whether `ZileSarbatoare` exists.

### 5.2 Proposed endpoint contract

Route:

```text
POST /ZileLibere/availableDates
```

Request:

```json
{
  "year": 2026,
  "months": [1, 2]
}
```

Successful response:

```json
{
  "dates": ["2026-01-01", "2026-01-02"]
}
```

Contract rules:

- use the current authenticated Espo session;
- require an administrator, matching the requested administrator workflow;
- accept a JSON object only;
- accept a valid integer year and a non-empty list of integer months 1–12;
- do not accept `countryCode` from the browser; the action passes `RO`;
- return `400 Bad Request` for invalid input;
- return `403 Forbidden` for a non-administrator;
- return `200` with `dates: []` when the query is valid but no stored dates
  match;
- return only valid, unique ISO date strings;
- do not expose exception traces or internal class names;
- do not call the Nager.Date client, synchronization runner, or settings
  service.

### 5.3 Generator client outcomes

The client performs no request until **Preia zilele** is clicked. It maps
outcomes as follows:

| Outcome | User-facing behavior |
| --- | --- |
| No year | Show `Selectează anul înainte de a prelua zilele libere.` |
| No months | Show `Selectează cel puțin o lună înainte de a prelua zilele libere.` |
| Valid empty response | Show the required no-results message verbatim |
| `403` | Show a clear permission message |
| `400` or otherwise invalid request | Show a clear selection/request message |
| Missing route/extension (`404`) | Show a clear ZileSarbatoare-unavailable message |
| Network failure, status `0`, or `5xx` | Show a clear temporary-unavailability message |
| Malformed `2xx` response | Show a clear invalid-response message |
| Valid dates | Merge only missing dates and retain all existing rows |

Required no-results text:

> Nu există zile libere disponibile pentru anul și lunile selectate. Verifică dacă anul a fost sincronizat în extensia Zile Sărbătoare.

The button will be disabled only while its own request is pending. Changing
`year` or `selectedMonths` will not import, clear, or otherwise change holiday
rows.

## 6. GeneratorPerioadeCursuri installation/CSP investigation

This task remains independent from the feature and presentation work.

### Phase CSP-1 — Establish the response-header owner

On an isolated or staging EspoCRM 10 instance matching the affected runtime:

1. Capture sanitized response headers before installation, immediately after
   installing 2.3.4, and after the documented rebuild/cache refresh.
2. Inspect both `Content-Security-Policy` and
   `Content-Security-Policy-Report-Only` values in the browser Network panel
   and with a header-only HTTP request.
3. Determine whether PHP/Espo, the origin web server, or an upstream proxy adds
   the header. When possible, compare direct-origin and public-edge responses.
4. Do not use the console line alone as proof of header ownership.

Exit criteria:

- the emitting layer is identified, or the remaining access limitation is
  explicitly recorded;
- header captures contain no credentials or authentication query values.

### Phase CSP-2 — Compare installation configuration behavior

1. Snapshot sanitized key names and values relevant to CSP and navigation
   before and after installation. Do not print unrelated secrets.
2. Compare a fresh 2.3.3 install, repeated 2.3.3 install, and 2.3.4 upgrade
   using the same starting `tabList`.
3. Record whether each path calls `ConfigWriter::save()` and which keys differ.
4. Use the real EspoCRM 10 `ConfigWriter` where runtime behavior matters; keep
   the offline test double for deterministic menu-transform tests only.
5. Check whether serialization changes an existing CSP-related value even
   though the extension sets only `tabList`.

Exit criteria:

- the last known working installation behavior is reproducible;
- any relationship between the config rewrite and the response header is
  demonstrated rather than inferred.

### Phase CSP-3 — Conditional repository correction

If repository evidence confirms that `AfterInstall.php` creates or corrupts
the relevant setting:

1. Add a failing regression case to `tests/offline/menu-lifecycle.php` or a
   narrowly scoped real-writer integration check.
2. Make the smallest correction in `scripts/AfterInstall.php`.
3. Preserve unrelated `tabList` entries and idempotent install behavior.
4. Verify that the resulting response header no longer changes because of the
   extension installation.

If the responsible layer is external:

1. make no extension CSP change;
2. report the exact header owner and evidence;
3. leave server/proxy remediation outside this implementation.

Expected conditional files:

```text
GeneratorPerioadeCursuri/scripts/AfterInstall.php
GeneratorPerioadeCursuri/tests/offline/menu-lifecycle.php
```

No bug file is guaranteed to change. `BeforeUninstall.php` is not expected to
change unless the evidence identifies the same proven defect in its shared
configuration path.

## 7. ZileSarbatoare detail presentation correction

This task is separate from the CSP investigation and holiday import feature.

### Phase DETAIL-1 — Verify raw values

1. Select the affected Good Friday record displaying `10 Apr` and `2,026`.
2. Capture its authenticated `GET /api/v1/ZileLibere/{recordId}` response in
   the browser Network panel.
3. Verify that `dateStart` is the ISO string `2026-04-10`.
4. Verify that `sourceYear` is the JSON number `2026`, not a formatted string.
5. Inspect `model.get('dateStart')` and `model.get('sourceYear')` before field
   rendering and verify the same values and types.
6. Do not inspect or expose session cookies or authentication tokens.

Exit criteria:

- both raw values and types are recorded;
- the phase proceeds to a presentation fix only if storage/serialization is
  correct.

### Phase DETAIL-2 — Lock the presentation contract

Create a focused offline contract test that loads the production metadata and
asserts:

- `dateStart.type` remains `date`;
- `dateStart.useNumericFormat` is enabled;
- `sourceYear.type` remains `int`;
- `sourceYear.disableFormatting` is enabled;
- `sourceYear` remains read-only;
- `managedSyncScope` still includes `sourceYear`;
- the country/date indexes remain unchanged;
- the custom detail modal still extends the native detail modal and does not
  manually rewrite model values.

Expected new test:

```text
ZileSarbatoare/tests/phase-007/detail-presentation.test.mjs
```

### Phase DETAIL-3 — Apply the presentation-only metadata correction

If DETAIL-1 confirms the raw contract, update only:

```text
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/entityDefs/ZileLibere.json
```

Set the standard Espo field parameters:

```json
{
  "dateStart": {
    "type": "date",
    "useNumericFormat": true
  },
  "sourceYear": {
    "type": "int",
    "disableFormatting": true
  }
}
```

The existing metadata properties remain in place; the snippet shows only the
relevant presentation additions.

Expected behavior:

- `dateStart` renders through Espo's configured date format, including the
  year, for example `10.04.2026`;
- `sourceYear` renders as `2026` without a thousands separator;
- stored values, API types, filtering, indexes, synchronization, and
  `ZileLibereCalendarService` remain unchanged.

This metadata applies consistently to standard renderings of those fields,
including detail/list contexts. That is compatible with the requirement that
the date always include the year and avoids a detail-modal-only formatting
fork.

The following files are inspected but are not expected to change:

```text
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/clientDefs/ZileLibere.json
ZileSarbatoare/files/client/custom/modules/zile-sarbatoare/src/views/modals/zile-libere-detail.js
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/layouts/ZileLibere/detail.json
```

No custom date or year field view is planned. A custom view will be considered
only if the standard metadata parameters fail in the target EspoCRM version,
and that deviation will require approval before implementation.

### Phase DETAIL-4 — Runtime presentation validation

After rebuild/cache refresh:

1. reopen the same record through the calendar/detail modal;
2. verify the complete configured date, including `2026`;
3. verify the exact ungrouped source year `2026`;
4. verify a date outside the current year still renders correctly;
5. verify list, filter, calendar, synchronization, and month-service behavior
   remain unchanged;
6. re-check the raw API response to prove that only presentation changed.

Defect classification after the gate:

- expected: two UI formatting defects;
- not currently confirmed: any storage, synchronization, schema, or API
  serialization defect.

## 8. Holiday import feature

### Phase IMPORT-1 — Lock the ZileSarbatoare endpoint contract

Create:

```text
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/routes.json
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/Api/PostAvailableDates.php
ZileSarbatoare/tests/phase-007/contract.test.mjs
ZileSarbatoare/tests/phase-007/holiday-lookup-api.test.php
```

The tests will execute the production action with small Espo API/user/calendar
test doubles and verify:

- administrator access succeeds;
- non-administrator access is forbidden;
- invalid or missing request bodies are rejected;
- year and month constraints are enforced;
- string, floating-point, boolean, null, out-of-range, and empty month values
  are rejected at the HTTP boundary;
- valid inputs are delegated once to
  `getDateLiberePentruLuni($year, $months, 'RO')`;
- dates are returned under a stable `dates` property;
- an empty service result returns `dates: []`;
- the action contains no Nager.Date or synchronization dependency;
- route metadata points to the action class.

Exit criteria:

- API tests fail for the expected missing-route/action reason before runtime
  implementation;
- the endpoint contract is independent of Generator PHP code.

### Phase IMPORT-2 — Implement the ZileSarbatoare endpoint

1. Add the POST route under ZileSarbatoare module metadata.
2. Implement the API action with `Request`, `ResponseComposer`, the current
   `User`, and the existing `ZileLibereCalendar` interface.
3. Enforce administrator access before executing the query.
4. Validate and normalize the request boundary without duplicating database
   filtering.
5. Call the calendar method with the constant country `RO`.
6. Return only the JSON contract from Section 5.2.

Exit criteria:

- all IMPORT-1 tests pass;
- targeted PHP syntax validation passes;
- no outbound HTTP or synchronization path is reachable from the action.

### Phase IMPORT-3 — Lock Generator client behavior

Create:

```text
GeneratorPerioadeCursuri/tests/offline/holiday-import.mjs
```

Update:

```text
GeneratorPerioadeCursuri/tests/run-safe.sh
```

The offline test will execute the production AMD field view with bounded model,
DOM, date-time, Ajax, and notification test doubles. It will verify:

- the button and translated label are present in edit mode;
- no request occurs during setup, render, year change, or month change;
- missing year and months stop before Ajax;
- month strings are converted to integers for the request;
- double-click/concurrent request protection works;
- ISO dates are validated and converted to `DD.MM.YYYY`;
- manually entered and still-unsaved DOM values are retained;
- imported dates are appended rather than replacing rows;
- duplicates against manual values, existing imported values, and repeated
  response values are skipped;
- the hidden input and field change event remain synchronized;
- an empty result uses the required message verbatim;
- 400, 403, 404, status 0, 5xx, rejected promises, and malformed `2xx`
  responses produce useful translated messages without leaking internals;
- manual add, remove, picker, fetch, and validation behavior remains usable
  when the endpoint is unavailable.

Exit criteria:

- the new behavior test fails against the pre-change field for the expected
  missing-button/import reason;
- existing manual repeater tests and contracts remain intact.

### Phase IMPORT-4 — Implement Generator field interaction

Update:

```text
GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuri.json
```

Implementation rules:

1. Keep the existing manual add-date control and help text.
2. Add **Preia zilele** as an explicit second action associated with the
   repeater.
3. Read `year` and `selectedMonths` from the current model only in the click
   handler.
4. Perform local required-selection validation before Ajax.
5. Send the route request by URL; do not add a Generator PHP bridge or
   ZileSarbatoare class reference.
6. Treat the response as untrusted and require valid ISO date strings.
7. Build the merge from current visible input values, not only the last saved
   model value, so unsaved manual input is preserved.
8. Compare and deduplicate in the field's internal `DD.MM.YYYY` form.
9. Reuse the existing row rendering/date-picker initialization path or extract
   a narrow row helper within the same field view.
10. Synchronize the hidden input and emit the same field change event used by
    manual edits.
11. Close the pending state in a `finally` path so every failure re-enables the
    button.

No change is expected in:

```text
GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri/record/edit.js
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/clientDefs/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuri/edit.json
```

Exit criteria:

- IMPORT-3 tests pass;
- existing manual field validation and serialization remain unchanged;
- no import occurs without the explicit click.

## 9. Cross-extension runtime validation

Run the following scenarios on a non-production EspoCRM 10 installation after
rebuild/cache refresh:

1. ZileSarbatoare installed and synchronized for 2026; January selected:
   five stored January dates are appended.
2. One of those dates already exists manually: only the other four are
   appended.
3. Several manual dates and one blank manual row exist: populated manual dates
   remain unchanged; imported values are added without duplication.
4. The same button is clicked twice: the second result adds no duplicates.
5. Year is missing: only the year validation message appears and no request is
   sent.
6. Months are missing: only the month validation message appears and no
   request is sent.
7. Year/month values change without clicking: holidays remain unchanged.
8. The selected period has no stored dates: the required no-results message
   appears exactly.
9. ZileSarbatoare is absent, disabled, or its route is unavailable: the
   Generator form remains usable and the request failure is controlled.
10. The user is not an administrator: the forbidden outcome is controlled.
11. A malformed successful response is simulated: no row is added and a clear
    error appears.
12. Network/server failure is simulated: no row is removed or replaced.
13. The same ZileLibere record shows a complete date with year and an ungrouped
    source year while its API still returns the original ISO date and integer.
14. Calendar display, search by year/month, synchronization, and
    `getDateLiberePentruLuni` continue to use the existing stored values.

No Generator request may reach Nager.Date, synchronization settings, or a sync
action during these scenarios.

## 10. Targeted validation commands

Design-only work will not run tests. During implementation, use the smallest
checks relevant to each phase.

Proposed focused checks:

```bash
php -l ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/Api/PostAvailableDates.php
php ZileSarbatoare/tests/phase-007/holiday-lookup-api.test.php
node ZileSarbatoare/tests/phase-007/contract.test.mjs
node ZileSarbatoare/tests/phase-007/detail-presentation.test.mjs
node GeneratorPerioadeCursuri/tests/offline/holiday-import.mjs
php GeneratorPerioadeCursuri/tests/offline/menu-lifecycle.php
```

Run `menu-lifecycle.php` as a required check only if CSP investigation changes
installer code; otherwise it is a limited regression check against unchanged
installer behavior.

Broader/manual validation commands will be supplied at implementation handoff
instead of being run automatically unless separately approved.

## 11. Release metadata and package validation

After all three tasks reach their exit criteria:

1. bump `GeneratorPerioadeCursuri` to 2.4.0 for the user-facing import feature;
2. bump `ZileSarbatoare` to 0.8.0 for the new public browser endpoint and
   presentation correction;
3. update existing README and package-test version references;
4. set release dates consistently;
5. build archives only with the repository's documented build script;
6. validate that each archive contains every new route/action file and no
   tests, fixtures, credentials, or unrelated generated files;
7. do not install the archives on production as part of this work.

Expected release/package files:

```text
GeneratorPerioadeCursuri/manifest.json
GeneratorPerioadeCursuri/README.md
GeneratorPerioadeCursuri/tests/wordpress-updater/phase-6.php
ZileSarbatoare/manifest.json
ZileSarbatoare/README.md
ZileSarbatoare/tests/phase-006/contract.test.mjs
ZileSarbatoare/tests/phase-006/package.test.php
ZileSarbatoare/docs/phase-6-runtime-validation.md
```

Generated archives are expected to be:

```text
dist/generator-perioade-cursuri-2.4.0.zip
dist/zile-sarbatoare-0.8.0.zip
```

Generated archives will not be committed unless the repository's tracked-file
policy or a later explicit request requires it.

## 12. Complete expected file-change inventory

### Expected feature and presentation changes

```text
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/routes.json
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/Api/PostAvailableDates.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/entityDefs/ZileLibere.json
ZileSarbatoare/tests/phase-007/contract.test.mjs
ZileSarbatoare/tests/phase-007/holiday-lookup-api.test.php
ZileSarbatoare/tests/phase-007/detail-presentation.test.mjs
GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/tests/offline/holiday-import.mjs
GeneratorPerioadeCursuri/tests/run-safe.sh
```

`ZileLibere.json` is guaranteed only after the DETAIL-1 raw-value gate passes.
If it fails, presentation implementation stops and this inventory must be
re-approved.

### Conditional CSP changes

```text
GeneratorPerioadeCursuri/scripts/AfterInstall.php
GeneratorPerioadeCursuri/tests/offline/menu-lifecycle.php
```

These files change only when repository causation is proven.

### Release/package changes

```text
GeneratorPerioadeCursuri/manifest.json
GeneratorPerioadeCursuri/README.md
GeneratorPerioadeCursuri/tests/wordpress-updater/phase-6.php
ZileSarbatoare/manifest.json
ZileSarbatoare/README.md
ZileSarbatoare/tests/phase-006/contract.test.mjs
ZileSarbatoare/tests/phase-006/package.test.php
ZileSarbatoare/docs/phase-6-runtime-validation.md
```

### Inspected files expected to remain unchanged

```text
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Binding.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendar.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereCalendarService.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/EspoZileLibereRepository.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere/ZileLibereData.php
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/metadata/clientDefs/ZileLibere.json
ZileSarbatoare/files/client/custom/modules/zile-sarbatoare/src/views/modals/zile-libere-detail.js
ZileSarbatoare/files/custom/Espo/Modules/ZileSarbatoare/Resources/layouts/ZileLibere/detail.json
GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri/record/edit.js
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/clientDefs/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuri.json
GeneratorPerioadeCursuri/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuri/edit.json
```

## 13. Implementation order and approval gates

Safest order:

1. CSP and live ZileLibere raw-value investigations, both read-only.
2. Presentation contract test and metadata-only correction, only after the raw
   payload gate passes.
3. ZileSarbatoare endpoint contract tests and endpoint implementation.
4. Generator client contract tests and button/merge implementation.
5. Cross-extension runtime validation, including absent/forbidden/error cases.
6. Any proven installer correction, kept in its own reviewable change set; if
   evidence is available earlier, it may be completed before feature code but
   must not be mixed with endpoint/UI reasoning.
7. Release metadata, package build, and archive inventory checks.

Stop and request renewed approval when:

- the CSP source is external but remediation is requested;
- the live ZileLibere API values are not the expected ISO date and integer;
- the standard Espo metadata parameters do not correct the target runtime;
- authorization must support non-admin users;
- a schema, migration, dependency, direct PHP extension dependency, or new
  service becomes necessary;
- files outside the inventory above require material changes.

No implementation, package build, commit, push, production change, or data
migration is authorized by this plan alone. Implementation begins only after
explicit approval.
