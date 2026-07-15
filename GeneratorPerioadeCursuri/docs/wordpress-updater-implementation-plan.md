# WordPress Course Updater Implementation Plan

## Status and source of truth

This plan covers only the WordPress course-date updater from the Django `planificator` app and its adaptation into the existing EspoCRM extension. It does not cover schedule generation, Word matching, XML conversion, MEC import, WordPress content other than the ACF course program, or a general WordPress integration framework.

The parity baseline is `cristake007/platforma` commit [`ce7caf4196c107625ba8d553523bca79c9f13f8a`](https://github.com/cristake007/platforma/tree/ce7caf4196c107625ba8d553523bca79c9f13f8a), specifically:

- [`apps/planificator/wp_course_updater.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/wp_course_updater.py)
- [`apps/planificator/views.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/views.py#L319-L670)
- [`apps/planificator/forms.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/forms.py#L152-L176)
- [`apps/planificator/file_handlers.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/file_handlers.py#L36-L73)
- [`apps/planificator/validators.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/validators.py#L106-L181)
- [`apps/planificator/settings_store.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/settings_store.py)
- [`apps/planificator/templates/planificator/actualizeaza_cursuri.html`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/templates/planificator/actualizeaza_cursuri.html)
- [`apps/planificator/static/planificator/course_updater.js`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/static/planificator/course_updater.js)
- [`apps/planificator/urls.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/urls.py#L36-L41)
- [`apps/planificator/tests_course_updater.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/tests_course_updater.py)

If the Django implementation changes before this plan is implemented, parity must be re-baselined deliberately. Do not silently mix behavior from different source commits.

## Objective

Add a dedicated `GeneratorPerioadeCursuriWordPressUpdater` BasePlus entity and record workflow. An authorized user must be able to:

1. Create an updater record and upload a CSV or XLSX schedule through EspoCRM's native file field.
2. Build a local preview without making a WordPress request.
3. Enter a WordPress base URL, username, and transient application password.
4. Verify the WordPress credentials explicitly.
5. Fetch the current ACF `program` dates for one course row.
6. Review the dates from the file, non-expired WordPress dates, and merged final program.
7. Update one WordPress course at a time.
8. See a clear per-row result of `success`, `no changes`, or a client-safe error.

The adapter must retain the current Django feature set while using EspoCRM-native records, ACL, attachments, layouts, API actions, translations, and client views. It must not persist the WordPress application password.

## Upstream workflow inventory

The Django page is a four-step browser workflow:

1. `preview` reads the uploaded CSV/XLSX locally and returns one preview row for each non-empty input row. It does not call WordPress.
2. `connect` calls `GET /wp-json/wp/v2/users/me` with WordPress application-password Basic authentication. Only the base URL and username are saved; the password is not.
3. `fetch-current-dates` resolves the WordPress `cursuri` post by exact slug, reads `acf.program`, removes invalid/expired entries, and merges the remaining entries with dates from the uploaded schedule.
4. `update-row` resolves and reads the course again, recomputes the merged program, skips the POST if the ordered valid program is unchanged, or posts only `{"acf":{"program":...}}` to that course.

The page has no batch-update action and no transaction across rows. A successful update of one row is not rolled back if another row later fails.

The upstream `resolve-post-id` endpoint is not called by `course_updater.js`. Post-ID resolution is already part of fetch and update. The EspoCRM port should therefore keep resolution internal and not add a separate public resolve action.

The upstream `expand_date_token` helper is also not used by the active updater workflow. Date ranges remain one ACF repeater value; they are not expanded into one row per day.

## EspoCRM adaptation decisions

The following are intentional platform and security adaptations rather than literal Django copies:

- Use a saved EspoCRM workflow record and native attachment instead of a one-off multipart Django page.
- Store the non-secret WordPress base URL and username on the updater record. Do not introduce a generic settings entity or copy Django's `AppSetting` model.
- Keep the application password only in the password input and in-memory client state for the current page. It is sent only to connect/fetch/update requests and is never written to an entity, attachment, preference, config, stream entry, or log context.
- Bind row actions to the current record, its saved attachment ID, and a server-generated source-row number. Do not accept caller-supplied slugs, permalinks, post IDs, final dates, or attachment IDs as authoritative update inputs.
- Accept `Nume curs` and `Course Name` as title aliases in addition to Django's `Title`, because the current EspoCRM XLSX export uses `Nume curs`. This is a strict compatibility superset.
- Enforce the existing repository boundary limits of 20 MiB, 50 columns, and 5,000 non-empty course rows even though the current Django updater view does not consistently apply all three after decoding.
- Harden authenticated redirects so credentials cannot be forwarded to a different host. This closes a credential-leak risk in a literal port without removing the intended same-site WordPress behavior.
- Do not persist preview rows or WordPress responses in the database in the first implementation. A page reload requires rebuilding the preview and reconnecting.

## Requirements

### Access and record workflow

- Add a separate EspoCRM scope and ACL for the WordPress updater.
- Require updater-scope read/edit access and read/edit access to the specific record for preview, connect, fetch, and update. Create access is required only to create a new updater record.
- Read the schedule only from the native file attachment referenced by the saved updater record.
- Confirm the attachment is related to that record and targets the updater file field before reading it.
- Never allow an API caller to substitute an unrelated attachment ID.
- Allow preview before WordPress connection.
- Require a successful client-side connection state before enabling fetch/update buttons. Every fetch/update request must still authenticate independently; the connection flag is not a server session.
- Keep updates explicitly per row. Do not add `Update all` in this phase.
- Keep preview and row results ephemeral. The Espo record stores the input/configuration, not a mutable copy of WordPress state.
- External WordPress writes must return the resolved post ID and outcome and must be logged server-side with record ID/source row/post ID only. Do not log credentials, Authorization headers, request bodies, or full WordPress responses.

### Entity input contract

- Native file field accepts `.csv` and `.xlsx` only.
- Maximum source size: 20 MiB (`20 * 1024 * 1024` bytes).
- Maximum header/data-row width: 50 columns.
- Maximum non-empty data rows: 5,000.
- CSV must be UTF-8 and may contain a UTF-8 BOM.
- CSV delimiter detection must support comma, semicolon, pipe, tab, and `@`.
- XLSX must be a valid ZIP/XLSX file and is read from the active worksheet with calculated values rather than formulas.
- XLSX date-formatted cells must normalize to `DD.MM.YYYY`, matching the Python handling of `date`/`datetime` values rather than exposing Excel serial numbers.
- Header matching is case-insensitive and trims surrounding whitespace.
- Required logical columns:
  - title: `Title`, `Nume curs`, or `Course Name`;
  - `Permalink`.
- Recognized date columns are the twelve Romanian month names and the twelve English month names.
- Read month values in January-to-December order, matching the Django `MONTH_COLUMNS`/`ROMANIAN_MONTH_COLUMNS` loop, regardless of physical column order.
- If both English and Romanian columns exist for a month, use the English value when non-empty; otherwise fall back to Romanian, matching Django.
- Ignore unrelated columns such as `Rand`, `Durata Curs`, and `Investitie`.
- Skip a completely empty data row.
- Preserve a non-empty row even if its title is empty; present it as `Curs fără titlu`, matching the browser behavior.
- A blank permalink or a permalink from which no slug can be extracted produces a row-level error and disables WordPress actions for that row.
- The permalink is used only to extract its final path segment as the slug. It is not an outbound request destination. Make it clickable only when it parses as HTTP(S), with `target="_blank"` and `rel="noopener noreferrer"`.
- A blank or textual `nan` month cell contributes no date.
- Preserve each non-empty date cell as one trimmed string; do not split comma-separated text and do not expand date ranges.

### Preview response contract

The preview endpoint returns one item per non-empty source row in source order. Each item has a stable source-row identifier and this logical shape:

```text
{
    sourceRow: int,
    title: string,
    permalink: string,
    slug: string,
    postId: int|null,
    excelDates: string[],
    existingValidDates: string[],
    finalDates: string[],
    payload: {acf: {program: array|false}},
    currentDatesLoaded: bool,
    canFetch: bool,
    canUpdate: bool,
    status: string,
    error: string|null
}
```

- Preview must not construct a WordPress client or perform DNS/network I/O.
- Deduplicate the Excel-only `finalDates` by exact trimmed string while preserving first occurrence.
- Invalid date text must not fail the entire file. Return the row with a localized row-level error and disable its update action.
- Include the current source attachment ID at response level. Fetch/update requests echo that preview source ID; the service rejects the request as stale if the record now references another attachment.
- Do not include WordPress credentials or secret-derived values in any response.

### Slug and post-resolution contract

- Extract the slug from the last non-empty URL path segment after trimming `/` and whitespace.
- Resolve through `GET /wp-json/wp/v2/cursuri?slug=<slug>`.
- Accept a result only when an item has an exact string-equal `slug` and a positive integer `id`.
- Treat no exact match as a row-level 404 (`Course not found by slug`).
- Resolve by slug again for fetch and update. A post ID returned to the browser is display-only and must not become the authoritative target of a later request.
- Do not port the unused `fallback_post_id` behavior.

### Date and merge contract

Supported ACF `program[].data` formats are exactly:

- single date: `D.M.YYYY` or `DD.MM.YYYY`;
- same-month range: `D-D.M.YYYY` or `DD-DD.MM.YYYY`.

Rules:

- The effective date of a single value is that date.
- The effective date of a range is the range end date.
- Invalid calendar dates, other text, cross-month ranges, en/em dashes, and other formats are invalid.
- Use the `Europe/Bucharest` local date as `today`.
- Keep an existing WordPress entry only when its effective date is greater than or equal to today.
- Drop expired and invalid existing entries from the next posted program.
- Deduplicate existing entries by exact trimmed text while preserving their original order.
- Append authoritative dates reparsed from the saved file when they are not already present, again using exact trimmed text and preserving order.
- Do not normalize a valid range to another textual representation. ACF stores the exact reviewed text.
- Compare current-valid and final-valid lists as ordered exact strings.
- Return `no changes` without a WordPress POST when the lists are identical.
- When changed, post exactly:

```json
{
    "acf": {
        "program": [
            {"data": "12.01.2026"},
            {"data": "13-14.02.2026"}
        ]
    }
}
```

- Retain the upstream `false` representation for an empty program payload, although the initial UI should not offer an update for a row with no final dates.
- The update endpoint must re-read current WordPress data immediately before comparing/posting. It must not assume the earlier fetch response is still current.

### WordPress endpoint contract

Use these endpoints relative to the normalized WordPress base URL:

| Operation | Method and path | Authentication |
| --- | --- | --- |
| Connect | `GET /wp-json/wp/v2/users/me` | Required |
| Resolve course | `GET /wp-json/wp/v2/cursuri?slug=<slug>` | Prefer auth; one unauthenticated fallback only after 401 |
| Read course | `GET /wp-json/wp/v2/cursuri/<id>` | Prefer auth; one unauthenticated fallback only after 401 |
| Update program | `POST /wp-json/wp/v2/cursuri/<id>` | Required |

- Strip spaces from the application password, matching WordPress's displayed application-password format.
- Trim the username and base URL.
- Preserve the upstream request headers initially, including `Accept`, JSON `Content-Type`, `Origin`, `Referer`, `Cache-Control`, and `Pragma`. Keep the source User-Agent for initial parity unless staging proves an extension-specific User-Agent is accepted by the protected site.
- Connection success returns only a safe user identity (`id` and display name/slug fallback).
- The WordPress installation must expose the `cursuri` custom post type and ACF `program` through REST, and the supplied WordPress user must be allowed to edit it.

### Network and SSRF contract

Every initial URL and redirect target is untrusted.

- Accept only `http` and `https` URLs.
- Reject embedded username/password, query strings, fragments, missing/invalid hostnames, `localhost`, `.localhost`, and cloud metadata hostnames.
- Normalize scheme and hostname case, remove the trailing slash, preserve a non-default explicit port, and preserve a WordPress subdirectory path.
- Resolve both IPv4 and IPv6 results before each request.
- Reject the destination if any resolved address is loopback, private, link-local, multicast, reserved, documentation-only, unspecified, or otherwise non-global.
- Pin the approved resolved address for the actual cURL connection so DNS cannot change between validation and connection.
- Revalidate and repin every redirect target.
- Disable automatic redirect following. Maximum redirects: 3.
- For authenticated requests, allow redirects only on the same hostname and port, allow HTTP-to-HTTPS upgrade, and reject HTTPS-to-HTTP downgrade or cross-host redirects. Never forward Basic-auth credentials to a different host.
- Use a 5-second connect timeout and 30-second response timeout per attempt.
- Limit every response, including an error response, to 5 MiB while streaming. Do not rely only on `Content-Length`.
- Space WordPress requests by at least 0.85 seconds inside one client operation.
- Allow at most four retries after the first attempt for explicit `403`, `429`, `500`, `502`, `503`, and `504` responses, using `Retry-After` when valid or bounded exponential backoff plus jitter.
- Do not retry a POST after a timeout/connection error where delivery is unknown. Retrying an explicit response with the same full program payload remains idempotent at the application level.
- Detect Cloudflare browser challenges from `cf-mitigated: challenge` or a bounded body containing `Just a moment` and return a specific client-safe error.
- Map 401, 403, 404, 429, 5xx, invalid JSON, oversized responses, redirects, timeouts, and connection errors to localized messages without returning remote response bodies.

[EspoCRM's server requirements](https://docs.espocrm.com/administration/server-configuration/) already include PHP cURL for integrations, so no Composer package should be added. Implementation should use a narrow injectable transport wrapper around cURL to keep network tests deterministic.

### Credential and secret handling

- There is no `wpAppPassword` entity field.
- Do not set the password on the Espo model or any browser storage.
- Keep it only in an `<input type="password">` owned by the detail view.
- Clear it on disconnect, record navigation, view removal, and authentication failure when appropriate.
- Editing the base URL or username invalidates the client connection state and requires reconnection.
- Saving a successful connection stores only the normalized base URL and username on the updater record.
- Redact credentials and Authorization headers from all logs and exception contexts.
- Return generic messages for unexpected errors; log the exception server-side without the request body.
- Require the EspoCRM deployment to be served over HTTPS before production use, because the application password travels from the browser to EspoCRM on each authenticated row request.

### Error and status contract

- `400`: invalid request shape, fields, date values, source data, or WordPress response data.
- `403`: EspoCRM scope/record/attachment ACL failure.
- `404`: updater record, source row, attachment, or WordPress course not found, without leaking cross-record information.
- `409`: stale preview because the source attachment changed after preview.
- `413`: source file exceeds the supported size/row boundary.
- `502`: client-safe WordPress authentication, availability, rate-limit, Cloudflare, redirect, or invalid-response error.
- Expected errors are localized in `ro_RO` and `en_US`.
- Unexpected exceptions are logged with safe identifiers and returned as the generic equivalent of `The WordPress course operation could not be completed.`
- The client must show both a global notification and the affected row status without exposing a stack trace or raw remote body.

## Non-goals

- No changes to the completed Excel generator, Word matcher, or XML converter behavior.
- No bulk `Update all`, background batch, scheduler, or queue.
- No automatic update immediately after file upload or connection.
- No WordPress credential storage, browser storage, external account secret, OAuth, or generic secrets vault in this phase.
- No generic WordPress admin/integration page.
- No creation or deletion of WordPress posts.
- No updates to title, content, permalink, status, categories, other ACF fields, or MEC data.
- No user-editable post ID.
- No separate resolve-post-ID API route.
- No persistence of preview rows, current WordPress dates, final payloads, per-row history, or full remote responses.
- No automatic rollback of already successful WordPress rows.
- No general tabular-reader refactor across the existing generator, Word matcher, and XML converter. Their schemas and failure behavior are different.
- No new Composer or JavaScript dependency.

## Domain and component boundaries

### `WordPressScheduleParser`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressScheduleParser.php`

Owns only the updater's untrusted schedule-file contract:

- size, extension, UTF-8, XLSX structure, row, and column validation;
- header normalization and title aliases;
- Romanian/English month precedence and calendar ordering;
- XLSX typed-date normalization;
- slug extraction from the permalink path;
- production of stable source-row preview inputs.

Output shape:

```php
array<int, array{
    sourceRow: int,
    title: string,
    permalink: string,
    slug: string,
    excelDates: array<int, string>
}>
```

Do not reuse `XmlScheduleParser` directly. It requires at least one month value, flattens rows into XML events, skips empty titles, and has a different error contract. Keep both parsers independent until a later task proves a smaller shared file-decoding mechanism is behaviorally identical.

### `WordPressProgramMerger`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressProgramMerger.php`

Owns all date/program policy:

- parse the two supported effective-end-date formats;
- validate date values parsed from the schedule;
- filter invalid/expired WordPress values;
- exact-text deduplication and stable ordering;
- merge existing values and authoritative file values;
- construct the ACF program array or `false`.

It accepts a `DateTimeImmutable` local date from the caller so tests do not depend on the clock. It has no Espo entity, attachment, HTTP, translation, or UI dependency.

### `WordPressUrlGuard`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUrlGuard.php`

Owns URL syntax normalization, prohibited-host checks, DNS resolution, public-address validation, redirect policy, and the cURL address-pinning data needed by the transport.

No other component may implement a second, weaker public-URL policy.

### `WordPressHttpTransport`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressHttpTransport.php`

Owns one bounded cURL exchange:

- approved URL/address pinning;
- method, headers, Basic auth, query/body, and timeouts;
- automatic redirects disabled;
- header capture and streamed 5 MiB body limit;
- transport-error translation into a narrow internal result/error type.

It does not know WordPress endpoint paths, course rules, retries, or Espo records. Tests replace/inject this boundary rather than making internet requests.

### `WordPressCourseClient`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressCourseClient.php`

Owns the WordPress protocol:

- connection test;
- exact-slug lookup;
- course read;
- ACF program update;
- safe manual redirects;
- request spacing, retry/backoff, and optional-auth GET fallback;
- status/Cloudflare/JSON error mapping.

It accepts normalized credentials at construction and exposes only domain-shaped return values. It must not persist credentials or depend on Espo entities.

### `WordPressUpdaterService`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUpdaterService.php`

Owns EspoCRM orchestration and external side effects through four methods:

1. `preview(recordId)` checks ACL, reads the linked attachment, parses it, validates row dates, and returns local preview rows.
2. `connect(recordId, input)` validates the credential payload, tests WordPress, and saves only normalized `wpBaseUrl`/`wpUsername` to the record.
3. `fetchDates(recordId, input)` verifies the preview attachment/source row, resolves and reads the course, and returns current/final dates without writing WordPress.
4. `updateRow(recordId, input)` verifies the same boundaries, reparses the authoritative file dates, re-reads WordPress, recomputes the final program, skips unchanged data, or performs exactly one course update.

The service owns scope/record/attachment ACL, request-schema whitelisting, source-staleness checks, Bucharest-local `today`, translations, and safe logging. It delegates file, date, URL, and protocol rules to their authoritative components.

### API actions

Add four thin HTTP adapters:

```text
Api/PostPreviewWordPressUpdate.php
Api/PostConnectWordPress.php
Api/PostFetchWordPressDates.php
Api/PostUpdateWordPressCourse.php
```

They validate the route ID, obtain the parsed request object where applicable, call one service method, and compose JSON. They do not duplicate ACL, parsing, date, credential, or WordPress logic.

## EspoCRM entity design

Entity type: `GeneratorPerioadeCursuriWordPressUpdater`

Base template: `BasePlus`

### Fields

| Field | Type | Rules |
| --- | --- | --- |
| `name` | varchar | Required; normal no-bad-characters pattern |
| `description` | text | Optional |
| `wpScheduleFile` | file | Required; `.csv`, `.xlsx`; canonical upload view |
| `wpBaseUrl` | varchar | Optional for local preview; max 2,048; read-only in normal CRUD; updater service saves normalized value after successful connect |
| `wpUsername` | varchar | Optional for local preview; max 150; read-only in normal CRUD; updater service saves trimmed value after successful connect |
| `assignedUsers` | linkMultiple | Follow existing extension convention |
| `teams` | linkMultiple | Follow existing extension convention |
| audit fields | standard | Follow existing BasePlus entities |

There is deliberately no password, post ID, connection-status, preview JSON, or updated-payload field.

### Metadata and layout files

Add:

```text
Resources/metadata/scopes/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/metadata/entityDefs/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/metadata/entityAcl/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/metadata/aclDefs/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/metadata/clientDefs/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/metadata/recordDefs/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/edit.json
Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/detail.json
Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/list.json
Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/search.json
Resources/i18n/en_US/GeneratorPerioadeCursuriWordPressUpdater.json
Resources/i18n/ro_RO/GeneratorPerioadeCursuriWordPressUpdater.json
Controllers/GeneratorPerioadeCursuriWordPressUpdater.php
```

Update both locale `Global.json` files with singular/plural scope names.

The edit layout places `wpScheduleFile` on a full-width row. The standard detail layout displays the last verified base URL/username as read-only values; the connection panel owns their editable inputs and prefills them from those saved values. The record remains editable and reusable with a replacement schedule.

### Client views

Add:

```text
client/custom/modules/generator-perioade-cursuri/src/views/
  generator-perioade-cursuri-wordpress-updater/record/edit.js
  generator-perioade-cursuri-wordpress-updater/record/detail.js
```

The edit view extends the existing generator edit view so the canonical full-width upload presentation is reused.

Update `views/fields/source-file.js` with a `wpScheduleFile` upload-title mapping. Retain EspoCRM's native `views/fields/file` lifecycle, metadata `accept`, drag/drop feedback, keyboard activation, selected-file display, validation, and removal.

The detail view owns only browser workflow state and presentation:

- `Build Preview` detail action;
- connection panel with base URL, username, transient password, status, Connect, and Disconnect;
- VPN/Cloudflare warning;
- in-memory preview row state;
- results table with Course, File Dates, Existing Dates, Final Program, Status, and Actions;
- per-row Fetch Dates and Update buttons;
- payload disclosure panel for review;
- top and native horizontal scroll synchronization;
- busy-state/double-submit protection;
- source/model change invalidation;
- localized safe notifications;
- password cleanup when the view is removed.

Follow the established dynamic result-panel approach used by the generator and Word matcher. Keep the first implementation in the detail view unless implementation proves a separately mounted child view is needed for lifecycle correctness; do not introduce a client-side framework.

Any updater CSS must be narrowly scoped under an updater workspace class, use Espo classes and TUVTK theme tokens, retain 3px radii, and add no decorative shadows. The upload field must continue to obey the repository's stricter upload-component token rules.

## API routes

Register:

```text
POST /GeneratorPerioadeCursuriWordPressUpdater/:id/preview
POST /GeneratorPerioadeCursuriWordPressUpdater/:id/connect
POST /GeneratorPerioadeCursuriWordPressUpdater/:id/fetchDates
POST /GeneratorPerioadeCursuriWordPressUpdater/:id/updateRow
```

Request bodies:

```text
preview: {}

connect: {
    wpBaseUrl: string,
    wpUsername: string,
    wpAppPassword: string
}

fetchDates: {
    previewSourceFileId: string,
    sourceRow: int,
    wpAppPassword: string
}

updateRow: {
    previewSourceFileId: string,
    sourceRow: int,
    wpAppPassword: string
}
```

Reject unknown fields. For fetch/update, load the base URL and username from the saved record and reparse the current attachment to locate `sourceRow` and its file dates. Never accept a browser-provided slug, permalink, post ID, or final date list as authoritative.

## Data flow

```text
Native Espo file field
    -> updater record wpScheduleFile attachment
    -> preview API
    -> ACL + attachment ownership check
    -> WordPressScheduleParser
    -> local preview rows in browser memory

Transient password + record URL/username
    -> connect API
    -> WordPressUrlGuard
    -> WordPressCourseClient users/me
    -> save only normalized URL/username
    -> browser connected state

Preview source ID + source row + transient password
    -> fetch/update API
    -> ACL + stale-preview check
    -> reparse authoritative source row
    -> exact slug resolution
    -> read current acf.program
    -> WordPressProgramMerger
    -> fetch: return review payload only
    -> update: compare, then zero or one WordPress POST
    -> row status in browser memory
```

## Phased implementation

Each phase is independently reviewable and should be committed only after its exit gate passes.

### Phase 0: Lock the source and security contract

Work:

- Pin the Django source commit recorded above.
- Create focused CSV/XLSX fixtures and expected preview/merge results from that source.
- Capture WordPress HTTP behavior with mocked transport responses; do not depend on the live site for automated checks.
- Record intentional Espo compatibility/security differences alongside the fixtures.

Minimum fixture set:

1. Romanian month schedule from the current Espo XLSX exporter (`Nume curs`).
2. Django `Title` schedule using English months.
3. Both English and Romanian values for the same month to lock precedence.
4. Semicolon CSV with BOM and an XLSX date-formatted cell.
5. Empty row, empty title, empty permalink, invalid permalink path, and no dates.
6. Today, expired date, future date, valid range, invalid calendar date, and cross-month range.
7. Duplicate current and file dates with order assertions.
8. Private IPv4/IPv6, metadata host, mixed public/private DNS, redirect to private IP, cross-host authenticated redirect, too many redirects, oversized body, Cloudflare challenge, rate limiting, invalid JSON, and timeouts.

Exit gate:

- Every active upstream updater test has an equivalent planned PHP assertion.
- The source commit and intentional differences are recorded with the fixtures.
- No fixture contains a real WordPress credential or production URL.

### Phase 1: Implement pure parsing and merge policy

Work:

- Implement `WordPressScheduleParser`.
- Implement `WordPressProgramMerger`.
- Use PhpSpreadsheet already available to the extension for XLSX input.
- Add compatibility for the Espo `Nume curs` export without changing Django `Title` behavior.
- Keep all clock behavior injectable through an explicit Bucharest-local date.

Exit gate:

- CSV and XLSX fixtures produce the expected ordered preview rows.
- Typed XLSX dates do not become serial numbers.
- Valid/expired/invalid/deduplicated date cases match the documented policy.
- Neither component depends on Espo entities, attachments, HTTP, translations, or browser code.

### Phase 2: Implement the safe WordPress client

Work:

- Implement `WordPressUrlGuard`.
- Implement the injectable `WordPressHttpTransport` using cURL.
- Implement `WordPressCourseClient` endpoint, redirect, retry, rate-limit, response-limit, Basic-auth, and error behavior.
- Ensure authenticated redirect credentials cannot leave the approved WordPress host.
- Add safe error translation keys without remote response bodies.

Exit gate:

- All network-safety fixtures pass without real internet calls.
- Private/mixed DNS and unsafe redirects are rejected before credentials are sent.
- The 5 MiB cap is enforced during streaming.
- Retry counts and non-retryable transport failures are deterministic under an injected sleeper/random source or equivalent test seam.
- No log or exception assertion contains a credential or Authorization header.

### Phase 3: Add the EspoCRM entity and native input UI

Work:

- Add controller, scope, entity/ACL/record/client metadata, layouts, and translations.
- Add the edit/detail view shells.
- Reuse the canonical `source-file` field for `.csv` and `.xlsx`.
- Add the entity to install/uninstall menu management.

This phase introduces a new EspoCRM entity/table through normal metadata rebuild behavior. It does not use a custom SQL migration.

Exit gate:

- Rebuild succeeds on a non-production test instance.
- Authorized users can create/edit/read/list/delete according to role ACL.
- Unauthorized users cannot access the scope.
- Upload selection, drag/drop, keyboard access, selected state, validation, and removal work on desktop and narrow screens.
- No metadata field can store an application password.

### Phase 4: Add orchestration and API actions

Work:

- Implement `WordPressUpdaterService`.
- Implement and register the four API actions/routes.
- Enforce scope, record, and linked-attachment ACL before parsing or network I/O.
- Enforce strict request-field whitelists and types.
- Reparse by source row and reject stale source attachment IDs.
- Save only URL/username after a successful connection.
- Add safe external-write logging and client-safe error mapping.

Exit gate:

- Preview performs no DNS or WordPress call.
- Cross-record attachment access and stale preview attempts fail safely.
- Fetch returns merged data without a POST.
- Update produces no POST for an unchanged ordered program and exactly one POST for a changed program.
- Update ignores browser-provided target data because no slug/permalink/post-ID input is accepted.
- Failed WordPress operations do not modify the Espo record except that a previously successful connection's non-secret settings remain.

### Phase 5: Complete the detail-view workspace

Work:

- Implement Build Preview, Connect, Disconnect, Fetch Dates, and Update Row interactions.
- Render the six-column responsive result table and payload review.
- Add per-row and global busy/error/success states.
- Invalidate connection/preview state when relevant saved inputs change.
- Clear the transient password on disconnect and view teardown.
- Add narrowly scoped responsive CSS using approved theme vocabulary.

Exit gate:

- Create -> upload -> preview works with WordPress unavailable.
- Connect identifies the user and never places the password on the model.
- Fetch/update buttons are unavailable until connection and valid row input exist.
- Each row can be fetched and updated independently, with no double POST from repeat clicks.
- A page reload loses the password, connection flag, preview, and row results while retaining only the saved source/URL/username.
- The table is keyboard usable and horizontally usable on narrow screens.
- Browser console and network responses contain no unexpected errors or secret echoes.

### Phase 6: Package and staging acceptance

Work:

- Bump the extension minor version from `2.2.0` to `2.3.0` when implementation is complete.
- Build the installable ZIP with the repository command.
- Install/rebuild only on a supplied non-production EspoCRM instance.
- Verify PHP cURL and outbound DNS/HTTPS access from the Espo server.
- Test through the authorized VPN/network path against a WordPress staging course.
- Confirm ACF `program` REST exposure and WordPress user capabilities.
- Run regression smoke checks for the completed Excel generator, Word matcher, and XML converter.

No dependency manifest or lockfile should change. PhpSpreadsheet and the required PHP cURL extension are already part of the supported EspoCRM environment.

Exit gate:

- The complete validation matrix passes.
- Staging fetch preserves non-expired existing dates and drops expired/invalid values from a changed posted program.
- Staging update changes only `acf.program` on the intended exact-slug course.
- A second identical update returns `no changes` and performs no POST.
- Cloudflare/VPN behavior produces a specific safe error when blocked.
- The extension ZIP installs and rebuilds cleanly.

## Validation matrix

| Area | Required assertion |
| --- | --- |
| Permission | Separate scope and record ACL guard preview, connection, fetch, and update |
| Attachment | Only the file linked to the updater record can be parsed |
| Upload | CSV/XLSX only; 20 MiB; 50 columns; 5,000 rows; native lifecycle |
| CSV | UTF-8/BOM and comma/semicolon/pipe/tab/`@` delimiters |
| XLSX | Active sheet, calculated values, typed date normalization, invalid ZIP rejection |
| Headers | `Title`, `Nume curs`, `Course Name`; required `Permalink`; Romanian/English months |
| Ordering | Source row order; January-December date order; English-before-Romanian precedence |
| Preview | Local only; stable source row; row-level errors do not discard other rows |
| Slug | Final path segment; exact returned slug; post ID remains display-only |
| Dates | Single/same-month range only; today retained; expired/invalid existing values dropped |
| Merge | Exact-text stable deduplication; existing valid first, authoritative file dates second |
| No-op | Ordered-equal program returns `no changes` with no POST |
| Payload | Only `acf.program` is posted to one `cursuri` item |
| Secret | Password absent from entity, browser storage, responses, logs, and errors |
| SSRF | Public destinations only; all DNS addresses checked; approved IP pinned per request |
| Redirect | Three maximum; each target checked; authenticated cross-host/downgrade blocked |
| Bounds | 5/30-second timeouts; 5 MiB response cap; bounded retry/backoff |
| Failure | Safe messages for auth, ACL, rate limit, Cloudflare, timeout, invalid JSON, stale preview |
| Responsiveness | Top/native horizontal scroll and usable row actions on narrow screens |
| Regression | Generator, Word matcher, and XML converter behavior unchanged |

## Expected file changes

### New files

```text
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Controllers/GeneratorPerioadeCursuriWordPressUpdater.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressScheduleParser.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressProgramMerger.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUrlGuard.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressHttpTransport.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressCourseClient.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/WordPressUpdaterService.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostPreviewWordPressUpdate.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostConnectWordPress.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostFetchWordPressDates.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostUpdateWordPressCourse.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityAcl/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/aclDefs/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/clientDefs/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/recordDefs/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/edit.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/detail.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/list.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/search.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuriWordPressUpdater.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuriWordPressUpdater.json
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-wordpress-updater/record/edit.js
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-wordpress-updater/record/detail.js
```

Phase 0 also adds focused fixtures and contract tests in the extension test location established during implementation. Do not include real production URLs or credentials.

### Modified files

```text
manifest.json
scripts/AfterInstall.php
scripts/BeforeUninstall.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/routes.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/Global.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/Global.json
files/client/custom/modules/generator-perioade-cursuri/src/views/fields/source-file.js
files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css
```

## Implementation checks

Run targeted checks while implementing:

```bash
php -l <each changed PHP file>
node --check <each changed JavaScript file>
php -r 'json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);' <each changed JSON file>
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts
```

On the supplied non-production EspoCRM test instance:

```bash
php command.php rebuild
php clear_cache.php
```

Broader validation must use mocked HTTP responses or a designated WordPress staging site. Do not run connection, fetch, update, cache deletion, extension installation, or rebuild against production as part of implementation.

## Preconditions and operational risks

- EspoCRM must run over HTTPS for production use of a browser-entered application password.
- The Espo server, not the user's browser, needs DNS and HTTPS access to WordPress through the authorized VPN/network path.
- PHP `max_execution_time` and the reverse-proxy timeout must accommodate bounded retry/backoff behavior. If the staging environment cannot, reduce the total retry budget deliberately rather than allowing unbounded requests.
- WordPress must expose `cursuri` and the ACF `program` field in REST. Connection success alone does not prove those course endpoints are configured.
- Cloudflare may still reject non-browser server traffic even with matching headers. The implementation must report this clearly; it must not attempt to solve challenges or bypass Cloudflare controls.
- Because updates are intentionally per row, partial completion is expected. Operators must be able to identify which rows succeeded before retrying failures.
- Record-level URL/username storage means collaborators with record-read access can see these non-secret values. Limit the scope through Espo roles/teams if that metadata is sensitive.
