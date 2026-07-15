# XML Converter Implementation Plan

## Status and source of truth

This plan covers only the standalone XML converter from the Django `planificator` app and its adaptation as an EspoCRM entity. It does not cover the WordPress course updater, direct XML export from an existing schedule-generation record, or any other `planificator` workflow.

The parity baseline is `cristake007/platforma` commit [`ce7caf4196c107625ba8d553523bca79c9f13f8a`](https://github.com/cristake007/platforma/tree/ce7caf4196c107625ba8d553523bca79c9f13f8a), specifically:

- [`apps/planificator/xml_export.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/xml_export.py)
- [`apps/planificator/forms.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/forms.py)
- [`apps/planificator/views.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/views.py#L673-L725)
- [`apps/planificator/file_handlers.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/file_handlers.py)
- [`apps/planificator/templates/planificator/xml_formatter.html`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/templates/planificator/xml_formatter.html)
- [`apps/planificator/static/planificator/xml_formatter.js`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/static/planificator/xml_formatter.js)
- [`apps/planificator/tests_xml_export.py`](https://github.com/cristake007/platforma/blob/ce7caf4196c107625ba8d553523bca79c9f13f8a/apps/planificator/tests_xml_export.py)

If the Django implementation changes before this plan is implemented, parity must be re-baselined deliberately. Do not silently combine contracts from different source commits.

## Objective

Add a dedicated `GeneratorPerioadeCursuriXmlConverter` BasePlus entity to the existing EspoCRM extension. An authorized user must be able to:

1. Create an XML conversion record.
2. Upload the same CSV or XLSX accepted by the Django XML converter.
3. Enter the first WordPress Post ID, with the same default and bounds.
4. Generate the same WordPress/MEC XML for the same input.
5. Download the XML with the same media type and filename contract.
6. Change the source file or first Post ID and generate again.

"100% functional parity" means that every input and operation supported by the standalone Django XML page remains supported and produces the same observable conversion result. EspoCRM record and attachment persistence are platform adaptations; they must not remove or alter any Django capability.

## Requirements

### Access and workflow

- Use a separate EspoCRM scope and ACL, equivalent to Django's `use_xml_export` permission boundary.
- Require an authenticated user with create/read/edit access to the XML converter scope and access to the specific record.
- Use EspoCRM's native file field and attachment lifecycle for the uploaded source.
- Expose `Generate XML` and `Download XML` actions on the record detail view.
- Make output handling match the existing `GeneratorPerioadeCursuri` entity: `Generate XML` creates and stores the output attachment, while `Download XML` is a separate explicit user action.
- Do not automatically open or download the XML after generation.
- Permit repeated generation. `xmlConvertedAt` records the latest successful run but must not disable `Generate XML`.
- A repeated generation replaces the record's latest XML attachment only after the new XML has been generated and saved successfully.

### Input contract

- Accepted extensions: `.csv` and `.xlsx`.
- Maximum source size: 20 MiB (`20 * 1024 * 1024` bytes).
- Maximum header width: 50 columns.
- Maximum course rows: 5,000 rows with a non-empty title.
- CSV must be UTF-8 and may contain a UTF-8 BOM.
- CSV delimiter detection must support comma, semicolon, pipe, tab, and `@`, matching the Django shared tabular reader.
- XLSX must have a valid ZIP/XLSX structure and is read from its active worksheet with calculated values rather than formulas.
- Header matching is case-insensitive and trims surrounding whitespace.
- Required logical columns:
  - course title;
  - `Permalink`;
  - at least one supported month column.
- Django title header: `Title`.
- Compatibility title aliases: `Nume curs` and `Course Name`. These are a strict input superset needed because the current EspoCRM XLSX generator emits `Nume curs`. They must not change behavior for Django-compatible `Title` files.
- Supported month headers:
  - all Romanian month names;
  - all English month names;
  - `Luna 1` through `Luna 12`.
- Preserve supported month columns in source-header order. Do not sort them into calendar order.
- Skip a row with an empty title, as Django does.
- Require the `Permalink` header, but preserve Django behavior by allowing an individual row's permalink value to be empty.
- Ignore an empty date cell and a textual `nan` date cell.
- Reject a file that produces no events.

### Start Post ID contract

- Entity field: `startPostId`.
- Default: `20000`.
- Minimum: `1`.
- Maximum: `2147483647`.
- The first generated item receives exactly `startPostId`; later items increment by one.
- Preserve the Django contract, which validates the submitted starting ID but does not impose a second limit on the final incremented ID.

### Date-range contract

Support exactly the three Django formats, including optional whitespace around the hyphen:

- single day: `5.01.2026`;
- multiple days in one month: `12-13.02.2026`;
- multiple days across months: `30.01-2.02.2026`.

The parser must emit ISO dates in `YYYY-MM-DD` form. Invalid calendar dates must fail conversion when the timestamp/date value is created, matching the Django failure class. Do not add a start-before-end business rule in this parity phase because the Django converter does not enforce one.

### Event grouping and ordering contract

- Group events by the exact, case-sensitive course-title string.
- Preserve the order in which each distinct course title first appears.
- Preserve event order within each course from the parsed schedule.
- Number periods independently inside each course group: `perioada 1`, `perioada 2`, and so on.
- Assign IDs while iterating the grouped course output, not in the original interleaved row order.
- Preserve the event-specific permalink even when two rows share the same course title.

For input ordered as Course A, Course B, Course A, the XML item order must be Course A, Course A, Course B. This is an explicit source contract.

### XML contract

The XML builder must port the Django element names, nesting, values, and order without reinterpretation.

- Root element: `events`.
- One `item` per schedule event.
- `item/ID` and `item/post/ID` use the allocated event ID.
- `item/title` and `item/post/post_title` use the course title.
- `item/content` is empty.
- `post_author`: `5`.
- `post_date` and `post_date_gmt`: start date at `00:00:00`.
- `post_status`: `draft`.
- `mec_more_info_title`: `perioada N`.
- `mec_read_more`: event permalink.
- `mec_color`: empty.
- `mec_location_id`: `1`.
- `mec_organizer_id`: `1`.
- `mec_allday`: `1`.
- Start time: 08:00 AM and 28,800 day seconds.
- End time: 06:00 PM and 64,800 day seconds.
- Repeat status: `0`.
- `mec_date`, `mec`, and `time` blocks match the Django fields and order exactly.
- `time/start` is `All Day`; `time/end` is empty.
- Raw times are `8:00 am` and `6:00 pm`.
- Epoch timestamps use the same effective `Europe/Bucharest` local-time behavior as the source deployment.
- XML text must be escaped by the XML library, including Romanian diacritics and `&`, `<`, and `>`.

Use a golden fixture generated by the Django source to lock declaration, indentation, empty-element representation, node order, and values. Where PHP's serializer differs by default, format the final document to match the golden output rather than weakening the parity assertion.

### Download and error contract

- Output MIME type: `application/xml`.
- Filename: `formatted_courses_<current-local-year>.xml`.
- The current local year must use `Europe/Bucharest`, matching Django's `timezone.localdate()` configuration.
- A successful generation stores the latest downloadable XML attachment and enables the separate `Download XML` action.
- Boundary validation errors return HTTP 400, except oversized input, which returns HTTP 413.
- Authentication and ACL failures retain EspoCRM's standard 401/403 behavior.
- Expected input errors must be user-readable and translated in `ro_RO` and `en_US`.
- Unexpected exceptions must be logged server-side and exposed as the generic equivalent of `Unable to read the uploaded schedule or create XML.`

## Non-goals

- No WordPress API connection or XML import.
- No WordPress course-date updater.
- No XML preview or editor.
- No configurable author, organizer, location, times, post status, or repeat values.
- No direct XML export action on `GeneratorPerioadeCursuri` in this phase.
- No changes to schedule generation or Word matching behavior.
- No generic tabular-reader refactor across existing services.
- No new third-party dependency.
- No separate event-row or conversion-history entity.

## Domain and component boundaries

### `XmlScheduleParser`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/XmlScheduleParser.php`

Owns the untrusted-file boundary and only the XML converter's input schema:

- extension and size validation;
- CSV/XLSX decoding;
- header normalization and aliases;
- row, column, and course limits;
- month-column recognition and source ordering;
- normalized event production.

Output shape:

```php
array<int, array{
    courseTitle: string,
    dateRange: string,
    permalink: string,
    sourceRow: int,
    sourceColumn: int
}>
```

Do not call `CourseInputParser`; its required duration and URL rules represent a different domain contract. Keep the XML parser local until a real, behaviorally identical shared abstraction exists.

### `MecXmlBuilder`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/MecXmlBuilder.php`

Owns the deterministic conversion policy:

- date-range recognition;
- course grouping and ordering;
- period and ID allocation;
- fixed MEC values;
- date/time and epoch construction;
- XML element construction and final formatting.

It must be independent of EspoCRM entities, file storage, HTTP, and translations. It accepts normalized events and a starting ID and returns XML text. This is the primary parity-test target.

### `XmlConversionService`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/XmlConversionService.php`

Owns orchestration and side effects:

1. Load the converter record.
2. Check scope and record ACL.
3. Read the saved `xmlScheduleFileId` and `startPostId` from the record.
4. Resolve and read the native EspoCRM attachment.
5. Invoke `XmlScheduleParser`.
6. Invoke `MecXmlBuilder`.
7. Create an `application/xml` export attachment.
8. Update `xmlConvertedFileId` and `xmlConvertedAt` to the latest successful result.
9. Return the event count, attachment ID, timestamp, filename, and download URL.
10. Remove the replaced generated attachment only after the new attachment and record reference are safely stored.

The service must never accept a caller-supplied attachment ID independent of the saved record.

### `PostGenerateXml`

Path:

`files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostGenerateXml.php`

Owns only HTTP adaptation:

- validate route ID;
- call `XmlConversionService`;
- return the service result as JSON.

Route:

```text
POST /GeneratorPerioadeCursuriXmlConverter/:id/generateXml
```

Use EspoCRM's native download entry point for the generated attachment:

```text
?entryPoint=download&id=<attachment-id>
```

## EspoCRM entity design

Entity type: `GeneratorPerioadeCursuriXmlConverter`

Base template: `BasePlus`

### Fields

| Field | Type | Rules |
| --- | --- | --- |
| `name` | varchar | Required; normal Espo no-bad-characters pattern |
| `description` | text | Optional |
| `xmlScheduleFile` | file | Required; `.csv`, `.xlsx`; canonical upload field view |
| `startPostId` | int | Required; default `20000`; min `1`; max `2147483647` |
| `xmlConvertedFile` | file | Read-only; latest generated XML |
| `xmlConvertedAt` | datetime | Read-only; latest successful conversion |
| `assignedUsers` | linkMultiple | Follow existing extension convention |
| `teams` | linkMultiple | Follow existing extension convention |
| audit fields | standard | Follow existing BasePlus entities |

The entity must remain editable and regeneratable after `xmlConvertedAt` is populated. `Download XML` uses the most recent successful attachment.

### Metadata and layout files

Add:

```text
Resources/metadata/scopes/GeneratorPerioadeCursuriXmlConverter.json
Resources/metadata/entityDefs/GeneratorPerioadeCursuriXmlConverter.json
Resources/metadata/entityAcl/GeneratorPerioadeCursuriXmlConverter.json
Resources/metadata/aclDefs/GeneratorPerioadeCursuriXmlConverter.json
Resources/metadata/clientDefs/GeneratorPerioadeCursuriXmlConverter.json
Resources/metadata/recordDefs/GeneratorPerioadeCursuriXmlConverter.json
Resources/layouts/GeneratorPerioadeCursuriXmlConverter/edit.json
Resources/layouts/GeneratorPerioadeCursuriXmlConverter/detail.json
Resources/layouts/GeneratorPerioadeCursuriXmlConverter/list.json
Resources/layouts/GeneratorPerioadeCursuriXmlConverter/search.json
Resources/i18n/en_US/GeneratorPerioadeCursuriXmlConverter.json
Resources/i18n/ro_RO/GeneratorPerioadeCursuriXmlConverter.json
Controllers/GeneratorPerioadeCursuriXmlConverter.php
```

Update both locale `Global.json` files with singular and plural scope names.

### Client views

Add:

```text
client/custom/modules/generator-perioade-cursuri/src/views/
  generator-perioade-cursuri-xml-converter/record/edit.js
  generator-perioade-cursuri-xml-converter/record/detail.js
```

The edit view extends the existing generator edit view so the canonical wide create layout and upload styling are reused.

The detail view owns:

- `Generate XML` action;
- `Download XML` action;
- busy, success, and error notifications;
- button enablement from saved fields;
- enabling the separate download action after successful generation;
- refreshing the latest output attachment and timestamp;
- repeated generation without a generated-state lock.

Update `views/fields/source-file.js` with an `xmlScheduleFile` upload-title mapping. Keep the native `views/fields/file` lifecycle, metadata-driven `accept` attribute, file removal, keyboard activation, and drag-over feedback.

Any XML-specific upload CSS must remain narrowly scoped under `.generator-perioade-cursuri-create`, use only the approved TUVTK theme tokens, retain 3px radii, and add no decorative shadows.

## Data flow

```text
Native Espo file field
    -> source Attachment referenced by XML converter record
    -> PostGenerateXml route with record ID
    -> XmlConversionService ACL and record lookup
    -> FileStorageManager source bytes
    -> XmlScheduleParser normalized events
    -> MecXmlBuilder XML text
    -> latest export Attachment
    -> record xmlConvertedFileId/xmlConvertedAt
    -> JSON download URL
    -> browser XML download
```

## Phased implementation

Each phase is intended to be independently reviewable and committed only after its exit gate passes.

### Phase 0: Lock the Django parity contract

Work:

- Create focused XML fixtures representing the accepted formats and edge behavior.
- Run the pinned Django converter against the fixtures.
- Store the expected XML outputs as generated golden artifacts.
- Record expected error category/status for invalid fixtures.
- Build a parity matrix linking every requirement in this plan to at least one fixture or acceptance check.

Minimum fixtures:

1. Course A, Course B, Course A grouping and ID ordering.
2. Single-day, same-month range, and cross-month range.
3. Romanian, English, and `Luna N` headers in non-calendar source order.
4. UTF-8 titles and XML-special characters.
5. Empty per-row permalink.
6. Semicolon CSV with BOM.
7. Django-generated XLSX.
8. Invalid date, missing header, no month, empty schedule, malformed XLSX, and invalid start ID.

Exit gate:

- Expected outputs were generated from the pinned Django source, not handwritten.
- The source commit and generation procedure are recorded with the fixtures.
- Every Django XML test has an equivalent planned Espo assertion.

### Phase 1: Implement the pure conversion core

Work:

- Implement `XmlScheduleParser`.
- Implement `MecXmlBuilder`.
- Use PhpSpreadsheet already available to the extension for XLSX input.
- Use PHP DOM APIs for escaped XML construction.
- Match Django declaration, indentation, empty nodes, element order, values, grouping, and timestamps.
- Add the `Nume curs` and `Course Name` compatibility aliases behind the same logical title field.

Exit gate:

- Every valid Django fixture produces the golden XML output.
- Every invalid Django fixture fails in the equivalent category.
- A current EspoCRM generator XLSX using `Nume curs` also converts successfully.
- No Espo entity, attachment, or HTTP dependency exists in `MecXmlBuilder`.

### Phase 2: Add the EspoCRM entity and native upload UI

Work:

- Add the entity controller, scope, entity definitions, ACL definitions, record definitions, and layouts.
- Add `ro_RO` and `en_US` entity translations and global scope names.
- Add client definitions and edit/detail view shells.
- Reuse the canonical `source-file` field view for `.csv` and `.xlsx`.

This phase introduces a new EspoCRM entity/table through normal metadata rebuild behavior. It does not use a custom SQL migration.

Exit gate:

- EspoCRM rebuild succeeds on a test instance.
- Authorized users can create, edit, read, list, and delete XML converter records according to role ACL.
- Unauthorized users cannot access the scope.
- Upload selection, drag-and-drop, keyboard use, selected-file display, validation, and removal work on desktop and narrow screens.
- `startPostId` defaults to `20000` and enforces its bounds in metadata/client validation.

### Phase 3: Add conversion orchestration and API

Work:

- Implement `XmlConversionService`.
- Implement `PostGenerateXml`.
- Register the POST route in `Resources/routes.json`.
- Add server-side start-ID validation even though the entity metadata also validates it.
- Enforce record ACL before reading the source attachment or changing the record.
- Persist the generated XML as the latest export attachment.
- Replace the previous output safely only after the new output is complete.
- Return a download URL compatible with EspoCRM's native entry point.
- Translate expected failures and log unexpected failures.

Exit gate:

- Conversion uses only the source attachment referenced by the saved record.
- A successful API call creates a downloadable `application/xml` attachment with the expected filename and content.
- Editing the source or `startPostId` and generating again produces a new correct download.
- A failed second generation leaves the previous successful output available.
- Cross-record and insufficient-ACL attempts fail without exposing attachment content.
- Oversized input returns 413; other invalid input returns 400.

### Phase 4: Complete the detail-view workflow

Work:

- Implement `Generate XML` and `Download XML` detail actions.
- Disable generation only when required saved input is absent or while a request is active.
- Do not disable generation merely because `xmlConvertedAt` exists.
- Update `xmlConvertedFileId` and `xmlConvertedAt` after success.
- Show a success notification after generation without opening the returned download URL.
- Keep manual download available for the latest output.
- Add localized field labels, tooltips, action labels, notifications, and errors.

Exit gate:

- The full create -> upload -> configure -> generate -> download flow works without browser-console errors.
- Repeated generation works after changing the file or starting ID.
- The latest XML remains manually downloadable after page reload.
- Client-side messages never replace server-side validation.
- The UI matches the repository's canonical upload presentation and TUVTK compact visual language.

### Phase 5: Package and run parity acceptance

Work:

- Add the entity to `AfterInstall.php` and `BeforeUninstall.php` menu item lists.
- Confirm install, upgrade, and uninstall behavior on a disposable test EspoCRM instance supplied for validation.
- Bump the extension minor version in `manifest.json` because this adds a new entity and workflow.
- Build the installable extension ZIP with the repository build command.
- Run syntax and metadata checks limited to changed files.
- Run the full golden parity set through the installed Espo API.
- Import representative output into the intended WordPress/MEC test environment.

No dependency manifest or lockfile should change. PhpSpreadsheet and DOM are already used by this extension.

Exit gate:

- All parity-matrix rows pass.
- Valid Django inputs produce equivalent XML files in EspoCRM.
- Golden fixtures match exactly where the output is deterministic.
- XML tree/value/order comparisons pass for all fixtures.
- A representative output imports into MEC with the same event titles, IDs, periods, links, dates, times, status, organizer, and location as the Django output.
- Existing schedule generation and Word conversion smoke checks remain successful.
- The extension ZIP installs and rebuilds cleanly.

## Validation matrix

| Area | Required assertion |
| --- | --- |
| Permission | Separate scope blocks users without XML converter access |
| Upload | CSV/XLSX only; 20 MiB; native field lifecycle |
| CSV | UTF-8/BOM and all supported delimiters |
| XLSX | Active sheet; values only; invalid ZIP rejected |
| Headers | `Title` and compatibility aliases; required `Permalink`; supported months |
| Limits | 50 columns and 5,000 titled course rows |
| Dates | All three source formats and invalid-calendar failure |
| Ordering | Exact A/A/B grouping contract and source month-column order |
| IDs | First ID exact; consecutive grouped allocation; submitted bounds |
| XML | All nodes, values, constants, order, escaping, and formatting |
| Time | Bucharest-local 08:00/18:00 epoch behavior |
| Download | `application/xml`; current-year filename; separate manual `Download XML` action only |
| Repetition | Same record can generate repeatedly after edits |
| Failure safety | Failed regeneration preserves latest successful XML |
| ACL | No cross-record source read or output mutation |
| Compatibility | Current Espo `Nume curs` XLSX converts without changing Django `Title` behavior |
| Regression | Existing generator and Word matcher remain unchanged |

## Expected file changes

### New files

```text
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Controllers/GeneratorPerioadeCursuriXmlConverter.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/XmlScheduleParser.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/MecXmlBuilder.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/XmlConversionService.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/Api/PostGenerateXml.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/scopes/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityDefs/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/entityAcl/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/aclDefs/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/clientDefs/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/metadata/recordDefs/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriXmlConverter/edit.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriXmlConverter/detail.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriXmlConverter/list.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriXmlConverter/search.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/GeneratorPerioadeCursuriXmlConverter.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/GeneratorPerioadeCursuriXmlConverter.json
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-xml-converter/record/edit.js
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-xml-converter/record/detail.js
```

Phase 0 also adds focused fixture and contract-test files in the extension's test location selected during implementation.

### Modified files

```text
manifest.json
scripts/AfterInstall.php
scripts/BeforeUninstall.php
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/routes.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/en_US/Global.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/i18n/ro_RO/Global.json
files/client/custom/modules/generator-perioade-cursuri/src/views/fields/source-file.js
files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css only if XML-specific state styling is still required
```

## Implementation checks

Run only targeted checks during implementation, then provide broader commands for the test EspoCRM instance:

```bash
php -l <each changed PHP file>
node --check <each changed JavaScript file>
php -r 'json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);' <each changed JSON file>
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts
```

On the supplied non-production EspoCRM test instance:

```bash
php command.php rebuild
rm -rf data/cache/*
```

Do not run installation, rebuild, cache deletion, or WordPress import against production as part of implementation.
