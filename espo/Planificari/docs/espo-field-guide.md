# EspoCRM Field Guide - Planificari

This guide is intended to be copied into:

`extensions/Planificari/docs/espo-field-guide.md`

It is a local source of truth for Codex while converting the Django app `app_to_convert/planificator/` into the EspoCRM extension `extensions/Planificari/`.

The purpose is to reduce repeated context usage, avoid field/layout drift, and keep future phases metadata-first.

---

## 0. Current product direction

As of the custom workflow pivot, the extension should no longer aim to feel like
a set of Entity Manager screens.

`Planificari` and `PlanificariRow` remain backend storage/history entities.
The main user experience should be a purpose-built generation tool:

1. upload CSV/XLSX;
2. choose year;
3. choose months;
4. enter holidays;
5. click Generate;
6. see a generated schedule table;
7. download XLSX.

Detailed architecture is documented in:

```text
extensions/Planificari/docs/custom-workflow-architecture.md
```

Future implementation phases should prioritize that custom workflow over
additional generic CRM layout polishing.

---

## 1. Core principles

### 1.1 Do not port Django literally

The Django app is a reference for business rules and data shape only.

Do not copy Django:

- models as PHP classes;
- forms;
- views;
- URLs;
- templates;
- HTMX/Alpine/DaisyUI/static JavaScript;
- permissions/authentication;
- migrations;
- admin classes;
- tests.

Use EspoCRM-native mechanisms first:

- `entityDefs`;
- `scopes`;
- `clientDefs`;
- layouts;
- labels/i18n;
- users;
- teams;
- roles;
- ACL;
- attachments/documents;
- standard record views;
- formulas/dynamic logic only when appropriate.

Custom PHP, custom JavaScript, custom CSS, services, hooks, controllers, API actions, or scheduled jobs must be later-phase decisions, not default implementation.

### 1.2 Extension packaging rule

This project is deployed as a ZIP uploaded manually in EspoCRM Administration > Extensions.

Do not edit live EspoCRM runtime folders directly.

Expected package structure:

```text
extensions/Planificari/
├── manifest.json
├── files/
│   └── custom/Espo/Modules/Planificari/...
└── scripts/
```

Files under `files/` are installed into the EspoCRM runtime by the extension installer.

### 1.3 Server safety rules

There is no Git repository.

Do not use:

- `git`;
- `sudo`;
- `su`;
- `chown`;
- broad or recursive `chmod`;
- Docker/container edits;
- dependency installation unless explicitly approved.

Before modifying existing files, create backups under:

```text
extensions/Planificari/.codex-backups/<timestamp>/
```

Update this file after every phase:

```text
extensions/Planificari/docs/codex-change-log.md
```

Keep output concise. Do not narrate every command.

---

## 2. EspoCRM metadata locations used by this extension

Use these paths inside the extension package:

```text
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/scopes/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/clientDefs/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/aclDefs/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/entityAcl/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/recordDefs/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/layouts/Planificari/
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/i18n/en_US/Planificari.json
extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/i18n/ro_RO/Planificari.json
```

Do not edit equivalent paths directly in the live EspoCRM runtime.

---

## 3. EspoCRM field type quick reference

Use local metadata examples first. If a local example is not available, use official EspoCRM field concepts carefully and document the decision.

Common EspoCRM field types relevant to this project:

| EspoCRM type | Use for | Notes for Planificari |
| --- | --- | --- |
| `varchar` | Short text | File names, SHA-256 digest, simple labels. |
| `text` | Longer text | Description, raw notes, fallback multi-line holiday input if no better native structure is confirmed. |
| `int` | Whole numbers | Year, counts, durations if later represented as fields. |
| `datetime` | Date and time | Expiration timestamp. |
| `date` | Date without time | Individual holiday records if holidays become child records later. |
| `enum` | One fixed option | Useful for future status fields. |
| `multiEnum` | Multiple fixed options | Good candidate for selected months because months are a fixed list. |
| `checklist` | Multiple fixed checkbox options | Alternative candidate for selected months if local UI/metadata supports it cleanly. |
| `array` | List of values | Possible candidate for holidays only if free-entry list behavior is confirmed locally. |
| `file` | Single file upload | Candidate for source CSV/XLSX upload. Confirm local metadata shape before implementing. |
| `attachmentMultiple` | Multiple file uploads | Not preferred for the source course list because the workflow expects one source file. |
| `link` / `linkMultiple` | Relationships | Do not create manually as simple fields unless relationship metadata is being implemented in a dedicated relationship phase. |
| `foreign` | Read-only field from related record | Later only, if useful after relationships exist. |

Important: do not invent unsupported field parameters. Inspect local examples before adding parameters such as length limits, accepted extensions, read-only flags, or dynamic logic.

---

## 4. Do not convert AppSetting

Django `AppSetting` is workflow preferences/configuration only. It is not a business entity.

Do not create:

- `AppSetting`;
- `PlanificariSettings`;
- settings fields;
- settings entity;
- settings relationship;
- settings controller;
- custom settings storage;
- custom PHP for settings;
- custom JavaScript for settings.

Later, if settings remain necessary, review EspoCRM-native approaches:

- user preferences;
- global config;
- metadata defaults;
- admin configuration;
- a small dedicated settings mechanism only if unavoidable.

---

## 5. Field classification table

Use this table before modifying entityDefs or layouts.

| Field | Source | Current status | User editable? | Derived? | Upload-related? | Detail view | Edit view | List view | Search view | Recommended Espo type | Implementation phase | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `name` | EspoCRM native/basic field | Implemented | Yes | No | No | Yes | Yes | Yes | Yes | Existing native/basic type | Phase 2 | Keep as the human-readable record name. |
| `description` | EspoCRM native/basic field | Implemented | Yes | No | No | Yes | Yes | Optional | Optional | Existing native/basic type, usually text | Phase 2 | Manual notes. Do not store generated schedule JSON here. |
| `year` | Django `ScheduleGeneration.year` | Implemented | Yes | No | No | Yes | Yes | Yes | Yes | `enum` | Phase 3A, corrected after Phase 3C-3 | User-entered planning year. Stored as a string option so EspoCRM does not format it with thousands separators like `2,026`. Current options are `2025` through `2031`. |
| `sourceFile` | Uploaded CSV/XLSX source course list | Implemented metadata-only | Yes, before generation | No | Yes | Yes | Yes | No | No | `file` | Phase 3E | Native EspoCRM file field. Parsing and generation are not implemented yet. |
| `sourceFileName` | Django `source_file_name`; uploaded file metadata | Removed from runtime metadata | No | Yes, from upload | Yes | Later | No | Later | Later | Later service field or non-stored display | Install repair after Phase 3E | Removed because rebuild failed looking for missing DB column `source_file_name`. Revisit with custom service/storage design. |
| `sourceFileDigest` | Django `source_file_digest`; SHA-256 digest | Removed from runtime metadata | No | Yes, from upload/file contents | Yes | Later | No | Later | Later | Later service field or non-stored display | Install repair after Phase 3E | Revisit after upload parsing/storage is implemented. |
| `sourceCourseCount` | Django `source_course_count`; parser result | Removed from runtime metadata | No | Yes, from CSV/XLSX parsing | Yes | Later | No | Later | Later | Later service field or non-stored display | Install repair after Phase 3E | Revisit after parser service exists. |
| `generatedEntryCount` | Django `generated_entry_count`; generation result | Removed from runtime metadata | No | Yes, from generation | No | Later | No | Later | Later | Later service field or non-stored display | Install repair after Phase 3E | Revisit after generation service exists. |
| `expiresAt` | Django `expires_at`; history retention | Removed from runtime metadata | No for now | Yes, from workflow/retention rule | No | Later | No | Later | Later | Later service field or retention design | Install repair after Phase 3E | Revisit after retention/cleanup behavior is finalized. |
| `selectedMonths` | Django `selected_months`; generator form input | Implemented | Yes | No | No | Yes | Yes | No | No | `multiEnum` | Phase 3C-3 | Fixed month values `1` through `12`; labels are provided through entity i18n `options.selectedMonths`. |
| `holidays` | Django `holidays`; generator form input | Implemented | Yes | No | No | Yes | Yes | No | No | `varchar` | Phase 3C-3, corrected after Phase 3C-3 | Comma-separated `DD.MM.YYYY` date input. Uses named pattern `$holidayDates` for human-readable validation messages. Full calendar validity remains deferred custom PHP/service logic. |
| `randomSeed` | Django `random_seed`; generation reproducibility | Not implemented | Usually no | Usually generated | No | Optional | No by default | No | No | Candidate: `int`, `varchar`, or omit | Phase 3D | Decide whether reproducibility is required in EspoCRM. Do not add by default. |
| `schedule` | Django JSON generated rows | Implemented as child row records metadata-only | No | Yes, generated result | No | Via `planificariRows` relationship | Child rows are manually editable only until generation service exists | Child row list | Child row search | Child entity `PlanificariRow` | Phase 3E | One `PlanificariRow` per generated course/month entry. No parser, generator, export, or automatic row creation exists yet. |
| `sourceFileData` | Django `BinaryField` | Do not implement directly | No | Stored upload data | Yes | No | No | No | No | Do not use; replace with `sourceFile` native file/attachment mechanism | Do not implement | Avoid custom binary field. |
| `createdAt` | EspoCRM native/system | Native | No | System | No | Yes | No | Yes | Yes | Native | Existing | Do not redefine unless already scaffolded safely. |
| `modifiedAt` | EspoCRM native/system | Native | No | System | No | Yes | No | Optional | Optional | Native | Existing | Do not redefine unless already scaffolded safely. |
| `createdBy` | EspoCRM native/system | Native | No | System | No | Yes | No | Optional | Optional | Native link/system | Existing | Use EspoCRM users, not Django auth. |
| `modifiedBy` | EspoCRM native/system | Native | No | System | No | Optional | No | Optional | Optional | Native link/system | Existing | Use as system metadata only. |
| `assignedUser` | EspoCRM ownership/ACL field if present | Use actual field from metadata | Yes if present | No | No | Yes | Yes | Yes | Yes | Existing native assignment field | Existing/Phase 2 | Do not invent `assignedUsers`. Use actual metadata field name. |
| `teams` | EspoCRM ACL/team field if present | Use actual field from metadata | Yes if present | No | No | Yes | Yes | Optional | Optional | Existing native team field | Existing/Phase 2 | Use EspoCRM teams and roles. |

---

## 6. Layout rules

### 6.1 Edit layout

Only fields the user should manually enter belong in edit layout.

Allowed examples:

- `name`;
- `description`;
- `year`;
- `sourceFile` once confirmed;
- `selectedMonths` once confirmed;
- `holidays` once design is confirmed;
- assignment/team fields if current scaffold uses them.

Do not put derived fields in edit layout:

- `sourceFileName`;
- `sourceFileDigest`;
- `sourceCourseCount`;
- `generatedEntryCount`;
- `expiresAt`, unless explicitly approved as user-editable later;
- generated schedule rows;
- raw JSON artifacts.

### 6.2 Detail layout

Detail layout can show both user input and derived metadata.

Good detail fields:

- name;
- description;
- year;
- sourceFile once implemented;
- selectedMonths once implemented;
- holidays once implemented;
- sourceFileName;
- sourceFileDigest;
- sourceCourseCount;
- generatedEntryCount;
- expiresAt;
- created/modified info.

### 6.3 List layout

List layout should be concise.

Good list candidates:

- name;
- year;
- sourceCourseCount;
- generatedEntryCount;
- sourceFileName;
- expiresAt;
- assignedUser;
- createdAt.

Avoid wide, noisy, or raw fields:

- sourceFileDigest unless needed;
- holidays;
- generated schedule JSON;
- large text fields.

### 6.4 Search layout

Search layout should include fields users would actually filter by.

Good search candidates:

- name;
- year;
- sourceFileName;
- expiresAt;
- assignedUser;
- createdAt.

Avoid search fields that are not useful or not clearly supported:

- file upload field;
- digest;
- generated JSON;
- large text holidays unless intentionally useful.

---

## 7. Phase decision table

| Phase | Fields / area | Allowed action | Not allowed |
| --- | --- | --- | --- |
| Phase 3A | `year`, scalar derived metadata | Already added/corrected | No upload, no generation, no custom code. |
| Phase 3B | Upload field investigation | Completed documentation-only if metadata shape unclear | No guessing, no custom binary field. |
| Phase 3B-2 | `sourceFile` | Implement only after native file metadata shape is confirmed | No parsing, no digest, no counts, no custom PHP/JS/CSS. |
| Phase 3C | `selectedMonths`, `holidays` | Input design and metadata only if native field shape is clear | No generation, no validation service, no custom JS. |
| Phase 3C-2 | `selectedMonths` field type confirmation | Completed documentation-only; local evidence did not confirm an entity-level multi-value field metadata shape | No field implementation yet. |
| Phase 3C-3 | `selectedMonths`, `holidays` implementation | Implemented metadata-only after official EspoCRM docs confirmed `multiEnum`/option metadata and i18n option shape | No generation, upload, custom validation, custom PHP, custom JS, list/search expansion, or settings fields. |
| Phase 3D | `randomSeed` | Decide whether needed; implement only if approved | Do not add just because Django had it. |
| Phase 3E | Generated schedule rows | Implemented `PlanificariRow` child entity and `planificariRows` relationship metadata | No parser, algorithmic generation, export, custom PHP, or custom JavaScript. |
| Phase 3F | Generation service design | Document service/controller/action approach only | No PHP or JavaScript implementation without explicit approval. |
| Phase 4A | Local EspoCRM core/API convention inspection | Completed documentation-only host-side inspection | Do not implement while controller/action and file-byte APIs remain unconfirmed. |
| Pivot | Custom workflow architecture | Completed design pivot to custom generation tool | Do not keep treating Entity Manager screens as the product UX. |
| Phase 4B | Custom workflow technical spike | Implement only a minimal custom route/API action and minimal client view/action | No parser/generator/export yet. |
| Phase 4C | Parser service | Read attachment and parse/validate CSV/XLSX preview | No schedule persistence yet. |
| Phase 4D | Scheduler service | Generate schedule in memory and return table | No partial saves. |
| Phase 4E | Persistence | Save `Planificari` and replace `PlanificariRow` rows transactionally | Do not make users manually create rows. |
| Phase 4F | XLSX download | Use installed PhpSpreadsheet to export and create Attachment | Keep formula-neutralization behavior. |
| Phase 5 | Export/download design | Design XLSX output from generated rows | No ad hoc file generation before service design. |
| Later | PHP/service review | Parsing, generation, digest, exports, cleanup, integrations | No broad rewrite or Django port. |

---

## 8. Upload field decision guide

The Django app stores original file bytes in `source_file_data`, but EspoCRM should not receive a custom binary field.

Preferred direction:

- use one native EspoCRM file/upload field, likely `sourceFile`;
- use a single-file field, not multi-file, because the workflow expects one source course list;
- use generated metadata fields for file name, digest, and counts;
- keep parsing/digest/count calculation for a later service phase.

Do not implement upload field if the metadata shape is unconfirmed.

Before implementing `sourceFile`, Codex should inspect local examples:

```bash
find /opt/crm.cursurituv.ro \
  -path '*/.codex-backups/*' -prune -o \
  -type f -name '*.json' -print 2>/dev/null \
  | xargs grep -n '"type"[[:space:]]*:[[:space:]]*"file"\|"type"[[:space:]]*:[[:space:]]*"attachmentMultiple"' 2>/dev/null \
  | head -80
```

If a clear local example is found, copy the native metadata pattern minimally.

If no local example is found, do one of these:

1. document that `sourceFile` needs confirmation;
2. ask for permission to use official EspoCRM documentation as evidence;
3. do not implement the field yet.

When upload is eventually implemented:

- add to edit layout;
- add to detail layout;
- usually do not add to list/search;
- add labels in `en_US` and `ro_RO`;
- do not add custom validation yet;
- do not parse the file yet.

Phase 3E result:

- `sourceFile` is implemented as a native `file` field.
- It is shown on edit and detail layouts.
- It is intentionally not shown on list/search layouts.
- CSV/XLSX parsing, file type validation, digest calculation, source row count,
  preview generation, and XLSX download remain later custom PHP/service work.

---

## 8.1 Generated schedule row decision guide

Phase 3E inspected:

- `extensions/Planificari/docs/input file.csv`
- `extensions/Planificari/docs/generated file.xlsx`
- `app_to_convert/planificator/scheduler.py`
- `app_to_convert/planificator/services.py`
- `app_to_convert/planificator/file_handlers.py`
- current `Planificari` metadata, layouts, and i18n

The Django saved schedule is normalized: one generated item per source course and
per selected month. Each generated item contains:

- `source_row`
- `original_order`
- `Title`
- `Permalink`
- `Durata Curs`
- `duration_label`
- `investitie`
- `date_range`
- `month`
- `month_name`

The generated XLSX is a later export view of that data. It pivots generated
items into one workbook row per source course and one column per month.

Phase 3E storage decision:

- Create `PlanificariRow` as the child entity for generated schedule rows.
- Add parent link `Planificari.planificariRows`.
- Add child link `PlanificariRow.planificari`.
- Store one child record per generated course/month entry.
- Keep generated row fields metadata-only and manually editable until a later
  generation service creates them automatically.

Implemented `PlanificariRow` fields:

| Field | Type | Source value | Notes |
| --- | --- | --- | --- |
| `name` | `varchar` | Row label | Required record name. Later service can derive it from title/month. |
| `planificari` | `link` | Parent generation | Required link to `Planificari`. |
| `courseTitle` | `varchar` | `Title` | Source course title. |
| `permalink` | `varchar` | `Permalink` | Kept as varchar because local URL field evidence was not required for this phase. |
| `durationLabel` | `varchar` | `Durata Curs` / `duration_label` | Human duration label, e.g. `3 zile`. |
| `investment` | `varchar` | `investitie` | Optional source column. |
| `month` | `enum` | `month` | Fixed values `1` through `12`, with i18n labels. |
| `dateRange` | `varchar` | `date_range` | Values such as `24.06.2026` or `01-02.06.2026`. |
| `sourceRow` | `int` | `source_row` | Original uploaded file row number. |
| `originalOrder` | `int` | `original_order` | Stable source order for sorting/export pivoting. |

Not implemented in Phase 3E:

- automatic row creation;
- upload parsing;
- generation algorithm;
- XLSX export/download;
- custom PHP services;
- custom JavaScript views;
- custom relationship panel JavaScript;
- generated count/source count/digest/expiration fields.

---

## 8.2 Phase 3F generation service design

Phase 3F is design-only. No PHP, JavaScript, metadata, layout, or ZIP changes
were made for this phase.

### Evidence inspected

- `app_to_convert/planificator/scheduler.py`
- `app_to_convert/planificator/services.py`
- `app_to_convert/planificator/file_handlers.py`
- `app_to_convert/planificator/validators.py`
- `extensions/Planificari/docs/input file.csv`
- `extensions/Planificari/docs/generated file.xlsx`
- current `Planificari` entity metadata
- current `PlanificariRow` entity metadata
- current extension controller `Controllers/Planificari.php`

Local EspoCRM PHP examples are limited in this server tree. The current module
only shows a standard `BasePlus` controller. Therefore exact PHP class
signatures should be confirmed against installed EspoCRM core/application files
or official documentation immediately before implementation.

### User workflow to support

1. User creates or edits a `Planificari` record.
2. User uploads `sourceFile`.
3. User selects `year`.
4. User selects one or more `selectedMonths`.
5. User enters optional comma-separated `holidays` in `DD.MM.YYYY` format.
6. User runs a Generate action.
7. Server parses the file and generates a complete set of `PlanificariRow`
   records.
8. Detail view shows generated rows through the `planificariRows` relationship.
9. Later, user downloads an XLSX export generated from `PlanificariRow` records.

### Recommended implementation shape

Use custom PHP for the generation workflow, but keep the UI as native EspoCRM as
long as possible.

Recommended classes/areas:

- `Controllers/Planificari.php`: add a record action endpoint later, only after
  confirming EspoCRM action method conventions locally.
- `Services/PlanificariGenerationService.php`: orchestration service for one
  generation run.
- `Services/CourseInputParser.php`: CSV/XLSX parser.
- `Services/CourseScheduler.php`: PHP port of the scheduling algorithm.
- `Services/PlanificariRowWriter.php`: deletes/replaces existing rows and
  persists generated `PlanificariRow` records transactionally.
- `Services/PlanificariExportService.php`: later XLSX export projection from
  stored rows.
- `Utils/SafeSpreadsheetText.php` or equivalent helper: later formula
  neutralization for XLSX export.

Do not create Django-style forms, views, URLs, templates, migrations, or
settings storage.

### Generate action contract

Preferred action:

- record-level action on an existing `Planificari` record;
- action reads fields from the saved record, not an arbitrary request payload;
- action is available only to users who can read/edit the record under EspoCRM
  ACL.

Inputs read from `Planificari`:

- `sourceFile`
- `year`
- `selectedMonths`
- `holidays`

Outputs:

- on success: generated row count and a message suitable for UI display;
- on validation failure: clear user-facing error message;
- on incomplete schedule: no generated rows saved;
- later: optional diagnostics such as unscheduled courses by month.

Important behavior:

- delete/replace existing child rows only inside the same successful generation
  transaction;
- if parsing or scheduling fails, preserve existing rows;
- if generation is incomplete for any selected month, save no partial rows;
- avoid storing raw source bytes in a custom binary field;
- do not reintroduce derived DB fields until their migration/storage strategy is
  confirmed.

### Source file parsing design

CSV:

- accept UTF-8 with optional BOM;
- detect delimiter from `,`, `;`, `|`, tab, or `@`;
- the attached sample uses `@`;
- require a header row;
- required columns are `Title`, `Durata Curs`, and `Permalink`;
- optional column is `investitie`;
- ignore fully blank rows;
- limit to 5000 course rows and 50 columns;
- enforce max upload size of 20 MB.

XLSX:

- accept valid `.xlsx` only;
- read the first/active worksheet for source data;
- require the same column rules as CSV;
- use a PHP spreadsheet library only if already available or explicitly
  approved later.

Course row validation:

- `Title` is required;
- `Durata Curs` is required and must contain a number;
- parsed duration must be between 1 and 366 days;
- `Permalink` is required and must be an HTTP(S) URL;
- `investitie` is optional;
- preserve `sourceRow` and `originalOrder`.

### Holiday parsing design

Metadata currently allows the basic comma-separated shape. The service must do
real date validation:

- split `holidays` on commas;
- trim whitespace;
- allow an empty list;
- each item must parse as `DD.MM.YYYY`;
- reject impossible dates such as `31.02.2026`;
- maximum 366 holiday dates;
- normalize stored/used values to `DD.MM.YYYY`.

### Scheduling algorithm design

Port the Django algorithm intentionally, not mechanically.

Business day:

- Monday through Friday;
- not in holiday set.

Course placement:

- start date must be a business day;
- all course days must avoid holidays;
- durations up to 5 business days must stay in the same work week and month;
- durations greater than 5 business days may cross week/month boundaries;
- available start dates are calculated per month and duration.

Date selection:

- use the current Django random-spacing behavior if randomness remains in the
  product;
- Phase 3 metadata currently has no `randomness` field and no `randomSeed`;
- before implementation, decide whether to add a user-facing randomness field,
  use a fixed default, or remove randomness for predictable output.

Completeness:

- expected rows equal source course count multiplied by selected month count;
- actual rows must match every `(sourceRow, month)` pair;
- each generated row must have a non-empty `dateRange`;
- incomplete schedules must not be persisted.

### Row persistence design

For each generated item, create one `PlanificariRow`:

| Generated value | `PlanificariRow` field |
| --- | --- |
| derived label | `name` |
| parent record | `planificari` |
| `Title` | `courseTitle` |
| `Permalink` | `permalink` |
| `Durata Curs` / `duration_label` | `durationLabel` |
| `investitie` | `investment` |
| `month` | `month` |
| `date_range` | `dateRange` |
| `source_row` | `sourceRow` |
| `original_order` | `originalOrder` |

Suggested generated `name` format:

```text
<originalOrder + 1>. <course title> - <month label>
```

The service should assign teams/responsible users consistently with the parent
record if EspoCRM does not inherit them automatically.

### XLSX export design for later

Export should be a separate later action/service.

Input:

- saved `Planificari` record;
- related `PlanificariRow` records;
- optional `holidays` from the parent record.

Output workbook:

- first sheet: `Program`;
- headers: `Title`, `Permalink`, `Durata Curs`, `investitie`, then all 12
  month columns;
- one workbook row per source course/original order;
- place `dateRange` in the month column matching each row's `month`;
- second sheet for holidays, matching the current generated workbook shape;
- neutralize formulas or dangerous spreadsheet prefixes in every text cell.

### Implementation risks and decisions still needed

- Confirm EspoCRM custom action method conventions before editing
  `Controllers/Planificari.php`.
- Confirm how to read bytes for a native `file` field from PHP.
- Confirm whether a spreadsheet library is available for XLSX read/write, or
  whether CSV-only generation should be implemented first.
- Decide how to expose the Generate button: native row/action metadata if
  possible, or minimal custom JS only if required.
- Decide whether to add `randomness` and `randomSeed` fields before generation
  implementation.
- Decide whether old generated rows should always be replaced or whether the
  user needs versioned generation history.
- Decide whether to reintroduce `sourceCourseCount`, `generatedEntryCount`,
  `sourceFileName`, `sourceFileDigest`, and `expiresAt` after the service and
  storage strategy are confirmed.

### Phase 4 implementation gate

Before implementing PHP:

1. Verify local EspoCRM controller/action/service conventions.
2. Verify native file-field storage and read API.
3. Verify available spreadsheet libraries.
4. Decide randomness behavior.
5. Approve whether the first implementation supports CSV only or CSV plus XLSX.

---

## 8.3 Phase 4A local EspoCRM core/API inspection

Phase 4A is documentation-only. No PHP, JavaScript, metadata, layout, i18n, or
ZIP changes were made.

### Local paths inspected

- `docker-compose.yml`
- `custom/Espo/Modules/Planificari/Controllers/Planificari.php`
- `custom.container-backup/Espo/Custom/Controllers/CCursuri.php`
- `custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json`
- `custom.container-backup/Espo/Custom/Resources/metadata/clientDefs/CCursuri.json`
- host-side `application/Espo` path check
- host-side `client` and `client-custom.container-backup` checks

### Environment finding

The host workspace does not contain EspoCRM core PHP files under
`application/Espo`. The command-side inspection found zero PHP files there.

`docker-compose.yml` shows the EspoCRM application core lives in the Docker
named volume mounted at `/var/www/html`, while only these paths are bind-mounted
from the host:

- `./custom:/var/www/html/custom`
- `./client/custom:/var/www/html/client/custom`
- `./extensions:/extensions`

Because the established safety rules prohibit Docker/container edits and an
earlier user rule limited Docker use to logs, Phase 4A did not inspect core PHP
inside the container.

### Controller/action evidence

Confirmed local controller examples:

```php
class Planificari extends \Espo\Core\Templates\Controllers\BasePlus
{
}
```

and backup custom controller:

```php
class CCursuri extends \Espo\Core\Templates\Controllers\BasePlus
{
}
```

Evidence confirmed:

- custom/module controllers can extend `\Espo\Core\Templates\Controllers\BasePlus`;
- the current `Planificari` controller is a standard record controller only.

Evidence not confirmed locally:

- record action method naming;
- accepted request/response classes for custom API actions;
- route metadata needed for custom record actions;
- how to expose a Generate button without custom JavaScript;
- whether a controller action alone is enough for the desired UI.

Implementation status:

- do not edit `Controllers/Planificari.php` yet;
- custom action conventions remain an implementation blocker.

### Service and entity manager evidence

No local EspoCRM core PHP files were available on the host to confirm:

- service namespace conventions;
- dependency injection/container conventions;
- `EntityManager` usage;
- repository APIs;
- transaction APIs;
- save/delete patterns for related records.

The package may still use services later, but class signatures and dependencies
must be confirmed before implementation.

Implementation status:

- do not add service classes yet;
- do not create row persistence PHP until repository/entity-manager APIs are
  confirmed.

### Native file-field storage evidence

Confirmed metadata currently installed/packaged:

```json
"sourceFile": {
    "type": "file",
    "tooltip": true
}
```

Evidence confirmed:

- the native `file` metadata type is accepted well enough for the installed
  extension to work in the UI;
- `sourceFile` appears in edit/detail layouts.

Evidence not confirmed locally:

- exact database columns created for a `file` field;
- whether the stored value is an attachment id, file id, or another structure;
- PHP API for retrieving original file name;
- PHP API for reading file bytes;
- whether native file fields enforce extension or size limits without custom
  service validation.

Implementation status:

- do not implement parser access to `sourceFile` yet;
- file-byte access remains an implementation blocker.

### Client/UI action evidence

Local metadata examples include `sidePanels` and `bottomPanels` in the
`CCursuri` backup clientDefs, but no local example was found for:

- record action buttons;
- row actions;
- action handlers;
- custom Generate button metadata.

Implementation status:

- prefer a server-side action only after action metadata/API conventions are
  confirmed;
- custom JavaScript remains deferred unless native action metadata is not
  enough.

### Spreadsheet library evidence

No host-side PHP dependency information was available because the EspoCRM core
and vendor tree are inside the Docker named volume, not the workspace.

Implementation status:

- do not implement XLSX read/write yet;
- Phase 4 implementation should either confirm a PHP spreadsheet library inside
  EspoCRM or start with CSV-only parsing if approved.

### Phase 4A conclusion

The implementation path is not fully confirmed from host-local files alone.

Safe next step options:

1. User explicitly approves read-only Docker/container inspection of EspoCRM core
   PHP and vendor files, with no edits.
2. User approves official EspoCRM documentation lookup for custom controllers,
   services, record actions, entity manager, and file field storage.
3. Start Phase 4 with a smaller documentation/design task only, such as deciding
   randomness behavior and CSV-only vs CSV+XLSX scope.

Do not implement generation PHP until either option 1 or option 2 confirms the
missing APIs.

### Pivot follow-up

After read-only Docker inspection, the missing APIs are sufficiently understood
for architecture:

- API route files can map to classes implementing `Espo\Core\Api\Action`.
- API actions use `process(Request $request): Response`.
- Responses can use `Espo\Core\Api\ResponseComposer::json(...)`.
- Native attachments can be read with `Espo\Core\FileStorage\Manager` or
  `Espo\Repositories\Attachment`.
- PhpSpreadsheet is installed under `vendor/phpoffice/phpspreadsheet`.
- clientDefs support action handlers and custom record/list views.

The next implementation should be a custom workflow technical spike, not more
metadata-only entity work.

---

## 9. Selected months decision guide

Django `selected_months` is a list-like input.

Recommended first choice:

- use a fixed-option native multi-select type such as `multiEnum` if local metadata confirms the shape.

Reason:

- months are a fixed list;
- users select multiple values;
- values can be displayed cleanly;
- better than raw JSON.

Possible option values:

```text
01, 02, 03, 04, 05, 06, 07, 08, 09, 10, 11, 12
```

Possible Romanian labels:

```text
Ianuarie, Februarie, Martie, Aprilie, Mai, Iunie,
Iulie, August, Septembrie, Octombrie, Noiembrie, Decembrie
```

Implementation rules:

- field belongs in edit layout;
- field belongs in detail layout;
- list/search only if useful and supported;
- do not trigger generation;
- do not add custom JavaScript;
- do not add custom PHP;
- do not add dynamic month validation yet.

If field type or options syntax is unclear, document and stop.

---

## 9A. Phase 3C-2 local evidence for selected months

Phase 3C-2 investigated only local files already present on disk. No online
search was used. No entity metadata, layouts, i18n files, PHP, JavaScript, CSS,
or runtime files were changed.

### Evidence inspected

Planificari extension package:

- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/layouts/Planificari/detail.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/layouts/Planificari/edit.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/layouts/Planificari/list.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/layouts/Planificari/search.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/i18n/en_US/Planificari.json`
- `extensions/Planificari/files/custom/Espo/Modules/Planificari/Resources/i18n/ro_RO/Planificari.json`

Local metadata examples:

- `/opt/crm.cursurituv.ro/custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json`
- `/opt/crm.cursurituv.ro/custom/Espo/Custom/Resources/metadata/entityDefs/Contact.json`
- `/opt/crm.cursurituv.ro/custom.container-backup/Espo/Custom/Resources/metadata/entityDefs/CCursuri.json`
- `/opt/crm.cursurituv.ro/custom.container-backup/Espo/Custom/Resources/metadata/entityDefs/Contact.json`
- `/opt/crm.cursurituv.ro/custom/Espo/Custom/Resources/metadata/themes/Tuvtk.json`
- `/opt/crm.cursurituv.ro/custom.container-backup/Espo/Custom/Resources/i18n/en_US/CCursuri.json`
- `/opt/crm.cursurituv.ro/custom.container-backup/Espo/Custom/Resources/i18n/ro_RO/CCursuri.json`

Local UI/CSS evidence:

- `/opt/crm.cursurituv.ro/client/custom/css/tuvtk.css`

### Exact metadata shapes found

Confirmed entity text field shape:

```json
{
    "fields": {
        "description": {
            "type": "text"
        }
    }
}
```

Confirmed custom text field shape:

```json
{
    "fields": {
        "cAdresaCertificat": {
            "type": "text",
            "rowsMin": 2,
            "cutHeight": 200,
            "isPersonalData": false,
            "isCustom": true
        }
    }
}
```

Confirmed single enum shape in theme metadata only, not entity field metadata:

```json
{
    "params": {
        "navbar": {
            "type": "enum",
            "default": "side",
            "options": [
                "side",
                "top"
            ]
        }
    }
}
```

No local entity metadata example was found for these field types:

- `multiEnum`
- `checklist`
- `array`
- `jsonObject`
- JSON-backed custom list fields

CSS contains selectors for checklist, multi-enum, and array UI classes, including
`.checklist-item-container`, `.multi-enum-item-label-container`, and
`.array-control-container`. This proves the installed frontend theme contains
styling for those UI patterns, but CSS selectors are not sufficient evidence for
safe entity metadata syntax.

### Exact options/i18n shape found

For entity fields, existing local i18n uses a simple `fields` map:

```json
{
    "fields": {
        "name": "Generation Name",
        "year": "Year"
    }
}
```

No local i18n example was found for field option labels for `multiEnum`,
`checklist`, `array`, or `jsonObject`.

The only local `options` example found was in theme metadata, not entity i18n:

```json
{
    "options": [
        "side",
        "top"
    ]
}
```

Because no entity field option-label example was found, do not guess whether
month labels belong under an `options`, `options.<fieldName>`, `lists`, or other
i18n key.

### Layout handling evidence

Current Planificari layouts use the standard field reference shape:

```json
{
    "name": "year"
}
```

No local layout example showed special handling for `multiEnum`, `checklist`,
`array`, or `jsonObject`. If those fields are later confirmed as normal entity
fields, edit/detail layouts will likely use the standard `{ "name": "fieldName" }`
shape, but that remains an inference until confirmed by a metadata example.

### List/search safety

- `text` is confirmed as a safe entity field type, but wide/free-form text is
  not a good list field and should usually stay out of list/search layouts.
- A single `enum` shape was found only in theme metadata, so it does not prove
  list/search support for entity enum fields.
- No local evidence confirms list/search behavior for `multiEnum`, `checklist`,
  `array`, or `jsonObject`.

### Recommendation for selectedMonths

Do not implement `selectedMonths` yet.

The preferred design remains a native multi-value fixed-option field, likely
`multiEnum` or `checklist`, with month values `01` through `12` or `1` through
`12`. However, Phase 3C-2 did not confirm the entity metadata shape or option
i18n shape locally. Do not guess.

Fallback options if confirmation remains unavailable:

- ask for permission to use official EspoCRM documentation as evidence;
- obtain a local exported entity metadata example that uses `multiEnum` or
  `checklist`;
- use conservative `text` only as a temporary storage fallback, accepting weak
  UX and deferred validation;
- defer month selection until a custom workflow UI/service phase, if native
  metadata cannot represent it cleanly.

### Recommendation for holidays

`holidays` can safely be documented as a native `text` candidate because local
entity metadata confirms `text` fields. Recommended MVP shape, if approved in a
future implementation phase:

- field type: `text`;
- edit/detail layout only;
- one `DD.MM.YYYY` date per line, or comma-separated dates;
- no list layout by default;
- search only if the product explicitly needs text search over holiday input.

Tradeoff: `text` cannot enforce date format, duplicate removal, max 366 dates,
or year consistency through metadata alone. Those checks remain deferred custom
PHP/service logic.

### Phase 3C-3 implementation safety

Phase 3C-3 is not safe for `selectedMonths` implementation from local evidence
alone. It becomes safe only after the native entity-level multi-value metadata
shape and option/i18n shape are confirmed.

Phase 3C-3 could safely implement only `holidays` as `text`, but implementing
only one half of the generation input pair is not recommended unless explicitly
requested.

---

## 9B. Phase 3C-3 official documentation confirmation

Phase 3C-3 used official EspoCRM documentation to confirm the missing native
field metadata shape.

Official evidence:

- EspoCRM Administration > Fields documents `Multi-Enum` as a native field type
  for selecting multiple ordered values and documents `Checklist` and `Array` as
  related multi-value field types with `Options`.
- EspoCRM Development > Custom Entity Type shows entity field metadata using
  `"type": "enum"` with an `"options"` array and entity i18n using
  `"options": { "<fieldName>": { "<value>": "<label>" } }`.
- EspoCRM Development > Custom Config Parameters shows the same field metadata
  pattern for an enum setting and an i18n `options` map by field name.
- EspoCRM Administration > Entity Manager states fields need to be placed on
  layouts after creation; no special layout JSON shape is documented beyond the
  standard field placement used by this extension.

Implemented Phase 3C-3 metadata:

```json
{
    "selectedMonths": {
        "type": "multiEnum",
        "options": ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12"],
        "maxCount": 12
    },
    "holidays": {
        "type": "text",
        "rowsMin": 3,
        "cutHeight": 200
    }
}
```

Implemented Phase 3C-3 i18n shape:

```json
{
    "fields": {
        "selectedMonths": "Selected Months",
        "holidays": "Holidays"
    },
    "options": {
        "selectedMonths": {
            "1": "January"
        }
    }
}
```

Layout decision:

- `selectedMonths` and `holidays` are on edit and detail layouts.
- They are intentionally not on list or search layouts in Phase 3C-3.
- No custom JavaScript, custom PHP, custom CSS, dynamic logic, formula, upload
  handling, or generation behavior was added.

Remaining caveats:

- `holidays` uses named pattern `$holidayDates` for one or more `DD.MM.YYYY`
  values separated by commas. The earlier install failure was later traced to
  derived DB fields, not this named pattern, so the named pattern was restored
  after the derived fields were removed from runtime metadata.
- `selectedMonths` stores string option values. Later generation code should
  parse them to integers if it expects Django-style month numbers.
- `year` is an enum string field rather than an integer field to avoid numeric
  thousands separators in the UI. Later generation code should parse it to an
  integer if needed.

---

## 10. Holidays decision guide

Django `holidays` is a list of dates, originally using DD.MM.YYYY-style validation.

This needs more care than selected months.

Possible approaches:

### Option A: `text` field for MVP manual input

Use a multi-line text field where users enter one holiday per line.

Pros:

- simple;
- no custom JavaScript;
- no child entity yet;
- easy to display.

Cons:

- validation requires later custom PHP/service logic;
- search/filtering is weak;
- less CRM-native.

### Option B: `array` field

Use only if local EspoCRM metadata confirms free-entry list behavior works well.

Pros:

- closer to list storage.

Cons:

- unclear UI behavior unless confirmed locally;
- validation still needed.

### Option C: child records

Create holiday records related to the generation.

Pros:

- most CRM-native for dates;
- searchable;
- validated as date fields;
- good if holidays become reusable.

Cons:

- requires relationships and more UI;
- should not be done before relationship/storage phases.

Recommended Phase 3C behavior:

- implement holidays only if a simple native shape is clearly chosen;
- if not clear, document and defer;
- do not create custom PHP/JS;
- do not create child records in Phase 3C unless explicitly approved.

---

## 10A. Sample generation files

Sample files inspected:

- `extensions/Planificari/docs/input file.csv`
- `extensions/Planificari/docs/generated file.xlsx`

Input CSV evidence:

- Encoding: UTF-8 with BOM.
- Delimiter: `@`.
- Header columns: `Title`, `Durata Curs`, `investitie`, `Permalink`.
- Row count: 192 rows including header, so 191 source course rows.

Generated XLSX evidence:

- Sheet `Schedule` has columns:
  - `Title`
  - `Permalink`
  - `Durata Curs`
  - `investitie`
  - `January` through `December`
- Sheet `Holidays` has column:
  - `Holiday Date`
- Example holiday value: `22.06.2026`.
- Example one-day generated date: `24.06.2026`.
- Example multi-day generated date range: `01-02.06.2026`.

Target workflow confirmed by user:

1. User uploads the source CSV/XLSX course list.
2. User selects the year for generation.
3. User selects one or more months for generation.
4. User enters holidays/non-working dates to exclude.
5. Generation creates a preview table in EspoCRM.
6. User can download the generated workbook.

Implementation note: this workflow needs later custom PHP/service work for
upload parsing, scheduling, preview table creation, and XLSX download. The
current metadata fields only capture the input parameters.

---

## 11. Random seed decision guide

Django has `random_seed` for reproducibility.

Do not add `randomSeed` automatically.

Questions before adding it:

- Does the user need to reproduce the same generated schedule later?
- Should the seed be visible to users?
- Should it be generated automatically?
- Should it be hidden technical metadata?
- Can generation be deterministic without exposing the seed?

Recommended default:

- defer to Phase 3D;
- do not add to edit layout;
- if later implemented, likely generated/read-only rather than user-entered.

---

## 12. Generated schedule storage decision guide

Django stores generated schedule rows in JSON.

Do not decide this too early.

Options:

### Option A: JSON artifact field

Pros:

- simplest storage;
- closer to Django.

Cons:

- poor CRM-native search/layouts;
- hard to edit rows;
- ugly UI if exposed.

### Option B: child entity records

Pros:

- CRM-native;
- searchable;
- relationship panels;
- can support exports and review better.

Cons:

- requires new entity and relationship;
- more phases;
- more metadata.

Recommended direction:

- prefer child records if rows need to be visible, searchable, linked, exported, or reviewed inside EspoCRM;
- use JSON/text artifact only if the generated schedule is merely an internal file/export payload.

Do not implement in Phase 3.

---

## 13. Deferred custom PHP/service logic

Do not implement these until a dedicated custom PHP review phase:

- CSV/XLSX parsing;
- file size validation;
- row count validation;
- column count validation;
- required column validation;
- formula-neutralization for spreadsheet export;
- source file digest calculation;
- source course count calculation;
- scheduling algorithm;
- generated entry count calculation;
- all-or-nothing persistence;
- expiration cleanup;
- XLSX export;
- XML export;
- DOCX matching/update;
- WordPress update;
- WordPress credential handling;
- SSRF/private-network protection for WordPress URLs.

If custom PHP is approved later, implement small services with focused tests/checks, not a direct port of Django views.

---

## 14. Deferred custom JavaScript logic

Do not implement custom JavaScript until standard EspoCRM views are proven insufficient.

Possible future JS areas:

- multi-step upload/generate workflow;
- async generation;
- preview table;
- export buttons;
- Word matching review UI;
- WordPress update preview UI.

Until then:

- use standard record layouts;
- use native buttons/actions only if supported;
- avoid frontend frameworks.

Do not introduce:

- Vue;
- React;
- Alpine;
- HTMX;
- Tailwind;
- DaisyUI;
- Bootstrap.

---

## 15. Codex implementation checklist before changing fields

Before changing any field metadata:

1. Read this guide.
2. Read `conversion-map.md`.
3. Read `field-behavior-map.md`.
4. Read current `entityDefs/Planificari.json`.
5. Read current layouts.
6. Check whether the field is user-entered, derived, upload-related, native/system, future-review, or do-not-implement.
7. Confirm the EspoCRM field type from local examples or clear documentation.
8. Back up changed files.
9. Modify the smallest set of files.
10. Validate JSON.
11. Validate existing PHP with `php -l`.
12. Update `codex-change-log.md`.
13. Bump patch version if building a new ZIP.
14. Build ZIP only if safe.
15. Stop after the requested phase.

---

## 16. Concise final response format for Codex

Codex should reply with this structure only:

```markdown
## Done
One sentence.

## Changed
- file path
- file path

## Backups
extensions/Planificari/.codex-backups/<timestamp>/

## Validation
Passed/failed summary.

## ZIP
extensions/planificari-perioade-cursuri-x.y.z.zip

## Manual test
1. Upload ZIP.
2. Install/update extension.
3. Rebuild.
4. Hard refresh.
5. Test the specific fields changed.

## Next
Maximum 12 short bullets.
```

Do not include long command transcripts unless something failed.

---

## 17. Next recommended prompt after creating this guide

After this guide exists in the project, Phase 3C can be retried with a short prompt:

```text
Execute Phase 3C only.

Use extensions/Planificari/docs/espo-field-guide.md as the source of truth.

Implement only selectedMonths and holidays if their native EspoCRM metadata shape is clear.

Do not add sourceFile, randomSeed, schedule rows, relationships, AppSetting/settings logic, custom PHP, custom JavaScript, or custom CSS.

Back up files first, update docs, validate JSON/PHP, bump patch version, build ZIP if safe, and stop after Phase 3C.

Keep output concise.
```
