# GeneratorPerioadeCursuri Fix Implementation Plan

Status: documentation only; no implementation has been performed.

Target extension: `GeneratorPerioadeCursuri`

Current manifest version inspected: `2.5.0`

## 1. Current implementation inspection and likely root causes

### Confirmed repository findings

#### Year and holiday state

- `views/generator-perioade-cursuri/record/edit.js` only enables the wide layout
  for a new model. It has no listener or handler for `change:year`.
- The custom `views/fields/holidays.js` field owns the visible holiday rows,
  imported-row metadata, hidden serialized `holidays` input, and the
  `holidayDetails` value returned by `fetch()`.
- Holiday import reads the current `year` and `selectedMonths`, but it does not
  subscribe to changes in either field.
- `tests/offline/holiday-import.mjs` currently asserts that changing the fake
  model year leaves the old `05.01.2026` date in the UI. That assertion locks in
  the reported incorrect behavior and must be replaced deliberately.

Likely root cause: the year field and the component that owns holiday state are
not connected. Clearing only the model or only the DOM would be incomplete;
`holidays`, `holidayDetails`, hidden input state, visible rows, and imported-row
metadata must be reset as one operation.

#### Required Generation Name feedback

- `Resources/metadata/entityDefs/GeneratorPerioadeCursuri.json` already defines
  `name` as `required: true`.
- The English field label is `Generation Name`; the Romanian field label is
  `Denumire generare`.
- The custom edit view does not override or extend the native validation
  failure path. There is no Generator-specific required-name message in either
  locale.

Likely root cause: native required validation correctly blocks save, while the
record-level failure notification remains EspoCRM's generic `Not valid`. The
exact EspoCRM 10 extension point for replacing that toast must still be
confirmed from the runtime/core version before implementation; the solution
must retain native field validation and focus/inline error behavior.

#### Detail/edit width and Source File presentation

- New create records set `isWide = true` and `sideDisabled = true`; saved-record
  edit mode does not.
- The main entity detail view does not set either flag. In contrast, the Word
  Matcher detail/edit views already use the wide, side-disabled pattern.
- CSS that creates a one-column grid and styles the canonical upload drop zone
  is scoped to `.generator-perioade-cursuri-create`, a class added only for new
  records.
- `sourceFile` occupies one layout cell followed by `false` in both
  `edit.json` and `detail.json`; it is not marked `fullWidth`.
- The packaged `defaultSidePanel.json` is empty, but an existing installation
  can have administrator-owned layout overrides. The reported right-side
  Created panel therefore requires read-only effective-layout/DOM inspection
  before deciding whether `sideDisabled` is necessary.

Likely root cause: saved detail/edit pages do not opt into the extension's
existing wide-page behavior, their CSS scope does not apply, and the upload
field remains a half-row layout cell. A runtime side-panel override may be an
additional contributor but is not confirmed by repository defaults.

#### Course title naming and compatibility

- `CourseInputParser` requires the uploaded source header `title`
  case-insensitively and emits the internal key `title`.
- `GenerationService::buildRow()` renames that key to `courseTitle`.
- The browser preview and `XlsxExportService` consume `courseTitle`.
- `XlsxExportService` writes the output header `Nume curs`.
- `WordConversionService` accepts `nume curs`, `course name`, and `title`, but
  currently prefers them in that order. If both `title` and `nume curs` are
  present, `nume curs` silently wins.
- `XmlScheduleParser` and `WordPressScheduleParser` already accept `title`,
  `nume curs`, and `course name`, preferring `title`. They duplicate their own
  header maps and return different internal shapes; the XML parser still emits
  `courseTitle` while the WordPress parser emits `title`.
- Header maps use trimmed cell text and `mb_strtolower`, but duplicate normalized
  headers overwrite earlier columns. None of the consumers defines a conflict
  contract for simultaneous `title` and `nume curs` values.

Likely root cause: the logical course-title rule has several owners. The main
pipeline changes `title` to `courseTitle` and exports `Nume curs`, while each
downstream parser independently chooses aliases and precedence.

#### XML Romanian-character encoding

- `MecXmlBuilder` creates `new DOMDocument('1.0')` without an encoding and then
  calls `saveXML()`.
- A direct PHP reproduction in the current environment produces numeric
  entities for Romanian text with that constructor and literal characters with
  `new DOMDocument('1.0', 'UTF-8')`.
- The builder rewrites a declaration that contains only `version="1.0"`; it has
  no contract for an `encoding="UTF-8"` declaration.

Root cause confirmed: the XML document has no declared UTF-8 encoding, so
libxml serializes non-ASCII characters as numeric character references. Numeric
entities are valid XML, but they do not meet the required literal UTF-8 output
contract.

## 2. Domain rules, ownership, and non-goals

The implementation should assign each rule one owner:

- `holidays.js` owns the atomic reset of holiday UI and persisted form values;
  the year field only triggers that operation.
- EspoCRM's native required-field validation remains authoritative for `name`;
  the extension only supplies localized, field-specific feedback through a
  supported record/field validation hook.
- Main-entity record views and layouts own page width. Upload behavior remains
  in the existing native-file subclass `views/fields/source-file.js`.
- A small course-title header policy component owns header normalization, alias
  recognition, precedence, and conflicts. It must not absorb the otherwise
  different CSV/XLSX parsers.
- `title` is the canonical internal course-title key and exact preferred
  input/output header. `nume curs` remains a legacy accepted alias.
- `MecXmlBuilder` owns XML encoding and escaping through DOM APIs.

Non-goals for all stages:

- no containers, databases, temporary services, new test infrastructure, or
  dependency additions;
- no schema or migration changes;
- no direct changes to the existing EspoCRM instance, cache, installed files,
  or administrator layouts;
- no replacement of native file upload/attachment behavior;
- no global CSS that affects unrelated EspoCRM pages;
- no removal of `nume curs` compatibility;
- no broad parser consolidation beyond the proven shared title-header rule;
- no final XML-wide `html_entity_decode`, string replacement of numeric
  entities, or hand-built XML.
- no ZileSarbatoare source, metadata, package, or release changes; the holiday
  picker continues to consume its existing read endpoint as an external
  dependency.

## 3. Course-title normalization contract

Stages 5 through 7 must use this explicit contract:

- Canonical internal key: `title`.
- Preferred new input/output header: exact text `title` in newly generated XLSX
  files.
- Accepted legacy alias: `nume curs`.
- Retain `course name` only where it is already an accepted compatibility alias;
  do not remove an existing compatibility path as an incidental cleanup.
- Header comparison trims surrounding whitespace, removes only a leading UTF-8
  BOM where relevant, and uses Unicode-aware case folding compatible with the
  repository's existing `mb_strtolower` behavior.
- Preserve course-title cell values and Romanian diacritics verbatim after
  surrounding whitespace is trimmed. Do not transliterate `ă`, `â`, `î`, `ș`,
  or `ț` when selecting a column. Matcher-specific comparison normalization may
  continue after column resolution.
- Duplicate instances of the same normalized header are invalid and must name
  the duplicate logical header rather than silently choosing the last column.
- When both `title` and `nume curs` are present in one file, resolve each row as
  follows:
  1. if only one value is non-empty, use it as canonical `title`;
  2. if both are empty, apply the existing consumer-specific empty-title rule;
  3. if both are non-empty and identical after surrounding-whitespace trimming,
     use `title`;
  4. if both are non-empty and differ, reject that row/file through the
     consumer's existing validation channel with a field/row-specific conflict
     message. Do not silently choose one value.
- Matching, previews, generated Word output, XML conversion, and WordPress
  preview receive the same resolved `title` value.

This policy is the smallest shared abstraction justified by the new
requirement. File decoding, month detection, row limits, empty-row behavior,
and error translation remain with the existing parsers/services.

## 4. Stage 1 - High priority: emit literal UTF-8 XML

### Objective

Make XML output declare UTF-8 and contain literal Romanian characters while
preserving valid DOM escaping and the established MEC structure.

This is the highest-priority stage and can start in a new Codex chat with no
dependency on the UI or title-normalization stages. Begin by re-reading
`MecXmlBuilder.php`, its callers, and the existing XML converter plan/tests.

### Dependencies

None.

### Expected changes

- Update
  `files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri/MecXmlBuilder.php`
  to construct a UTF-8 `DOMDocument`.
- Adjust the declaration-formatting step so it deliberately supports and
  retains `encoding="UTF-8"`, including the currently expected space before
  `?>` if that formatting remains part of the compatibility contract.
- Add a focused offline XML builder test and register only that focused command
  in `tests/run-safe.sh` if repository convention requires registration.
- Reuse DOM `createTextNode` escaping. Do not post-process the complete XML with
  entity decoding.

### Acceptance criteria

- Output begins with an XML declaration that explicitly declares UTF-8.
- `Măsurarea eficacității unui sistem`, `Ș`, `Ț`, `ă`, `â`, and `î` remain
  literal UTF-8 in `title` and `post_title`.
- Romanian characters are not emitted as decimal or hexadecimal numeric
  character references.
- XML-sensitive input such as `A & B < C` remains safely escaped and the result
  parses successfully as XML.
- Node order, empty elements, indentation, IDs, dates, timestamps, MIME type,
  and attachment lifecycle remain unchanged.

### Proportional validation

- Run `php -l` on `MecXmlBuilder.php`.
- Execute the focused builder test with Romanian and XML-special-character
  fixtures.
- Assert valid UTF-8 bytes, an encoding declaration, literal Romanian text,
  absence of numeric entities for those characters, and successful DOM parse.
- Do not invoke the EspoCRM endpoint or modify an attachment in the existing
  instance.

## 5. Stage 2 - Reset holidays when year changes

### Objective

Atomically remove all holiday data when the user commits a different year,
without reacting to unrelated field changes.

This stage can start in a new chat independently of Stage 1. Reinspect the
production holiday field and execute its existing offline harness before
editing.

### Dependencies

None.

### Expected changes

Likely files:

- `files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js`
- `files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri/record/edit.js`
  only if the field cannot reliably subscribe to the model itself;
- `Resources/i18n/{en_US,ro_RO}/GeneratorPerioadeCursuri.json` for an optional
  native confirmation message;
- `tests/offline/holiday-import.mjs`.

Implementation rules:

1. Prefer a model `change:year` listener owned by the holiday field, because
   that component already owns every representation being cleared. Do not
   duplicate DOM reset logic in the record view.
2. Provide one reset method that clears:
   - all visible date rows and imported metadata;
   - the hidden serialized input;
   - model `holidays` to `null`/the existing empty representation;
   - model `holidayDetails` to `[]`;
   - any pending imported-state markers;
   - validation residue associated with removed dates, if native field APIs
     expose it.
3. Leave one clean blank row only if that is the existing edit-mode empty-state
   convention. It must not serialize as a holiday.
4. Ignore initial model hydration and same-value assignments. Changing
   `selectedMonths`, `randomness`, `sourceFile`, `name`, or other fields must not
   clear holidays.
5. Inspect the exact EspoCRM 10 confirmation primitive before deciding. The
   recommended UX is to confirm only when the form contains at least one
   holiday:
   - confirm: commit the new year and perform the atomic reset;
   - cancel: restore the previous year with a recursion guard, so no year change
     has occurred and holidays remain valid;
   - empty holiday list: change immediately with no prompt.
6. If the product owner rejects confirmation, retain the same atomic reset but
   make the no-prompt behavior explicit in tests. Do not leave this decision
   implicit.

### Acceptance criteria

- Changing from year A to year B clears manual and imported dates,
  `holidays`, `holidayDetails`, hidden input value, badges, and imported names.
- Saving after the change cannot persist a holiday from year A.
- Same-year assignments and unrelated field changes preserve holidays.
- Initial form render does not clear saved edit data.
- Confirmation accept/cancel behavior, if used, is deterministic and localized
  in English and Romanian.

### Proportional validation

- Replace the existing test assertion that preserves old dates after a year
  change.
- Extend `tests/offline/holiday-import.mjs` for manual dates, imported dates,
  metadata, hidden input, fetched values, unrelated-field changes, same-year
  changes, initial render, and confirmation accept/cancel.
- Run only that test for this stage:

  ```bash
  node GeneratorPerioadeCursuri/tests/offline/holiday-import.mjs
  ```

## 6. Stage 3 - Provide a field-specific required-name validation message

### Objective

Keep native save blocking and field focus/inline validation while replacing the
unhelpful generic toast with a localized name-specific message.

This stage can start in a new chat independently. First inspect the exact
EspoCRM 10 `views/record/edit` save/validation flow and base varchar field
`validateRequired` implementation from the deployed version or matching core
source, read-only.

### Dependencies

None, but the native validation extension point must be confirmed before code
changes.

### Expected changes

Likely files:

- `views/generator-perioade-cursuri/record/edit.js`
- `Resources/i18n/en_US/GeneratorPerioadeCursuri.json`
- `Resources/i18n/ro_RO/GeneratorPerioadeCursuri.json`
- a new focused offline edit-validation test, or the nearest existing client
  view test if it can execute the production validation path.

Add messages equivalent to:

- English: `Generation Name is required.`
- Romanian: `Denumirea generării este obligatorie.`

Implementation rules:

1. Retain `entityDefs.name.required = true` as the authoritative required rule.
2. Reuse a supported native validation-failure callback/event or field
   validation hook. Do not implement an alternate save endpoint, manual form
   serializer, or independent validation framework.
3. Call the native validation path exactly once so the name field retains its
   invalid styling, inline message, focus behavior, and save prevention.
4. Show the specific toast only when `name` is the failing required field. Do
   not mislabel failures in holidays, attachments, patterns, or other fields as
   a name error.
5. Avoid displaying both `Not valid` and the specific message. If the native
   hook cannot suppress only the generic toast, document the limitation and
   select the smallest supported record-view override rather than monkey
   patching global `Espo.Ui` behavior.

### Acceptance criteria

- Empty or whitespace-only Generation Name blocks save.
- English locale displays `Generation Name is required.` and Romanian displays
  its approved translation.
- The name field is visibly identified and receives the native inline/focus
  behavior.
- A different invalid field produces its own native/specific feedback, not the
  Generation Name message.
- A valid name proceeds through the unchanged native save lifecycle.
- No other entity's validation toast changes.

### Proportional validation

- Execute a focused AMD test using the production edit view and a minimal native
  base-view stub that covers empty, whitespace, valid name, and unrelated-field
  failure.
- Parse both locale JSON files.
- Syntax-check the modified JavaScript.
- Do not save a record in the current EspoCRM instance.

## 7. Stage 4 - Make saved detail/edit pages wide and the uploader usable

### Objective

Use the normal available record width on saved detail and edit pages, give
Source File sufficient space, and keep the change responsive and entity-scoped.

This stage can start in a new chat independently. Reinspect the effective
runtime grid and side panel read-only before changing the packaged defaults.

### Dependencies

None.

### Expected changes

Likely files:

- `views/generator-perioade-cursuri/record/edit.js`
- `views/generator-perioade-cursuri/record/detail.js`
- `css/generator-perioade-cursuri.css`
- `Resources/layouts/GeneratorPerioadeCursuri/edit.json`
- `Resources/layouts/GeneratorPerioadeCursuri/detail.json`
- `Resources/layouts/GeneratorPerioadeCursuri/defaultSidePanel.json` only if
  inspection proves the packaged default is not already correct;
- `tests/offline/generator-detail-export.mjs` and
  `tests/offline/detail-layout-field-visibility.mjs`, or a new focused layout
  test beside them.

Implementation rules:

1. Reuse the existing wide-record pattern already proven on the Word Matcher
   views. Apply it to both main-entity detail and edit, not only new records.
2. Add a semantically named main-entity page class for shared create/edit/detail
   layout CSS. Do not label a detail page as “create” merely to inherit styles.
3. Expand only the GeneratorPerioadeCursuri main-page CSS selectors. Preserve
   the app-wide stylesheet's strict entity scoping.
4. Mark Source File layout cells full-width in both edit and detail layouts, and
   remove placeholder `false` partners/empty row structure according to the
   established Espo layout shape.
5. Continue using `views/fields/source-file.js`, the repository's canonical
   native-file upload presentation. Do not fork its attachment lifecycle or add
   an endpoint.
6. Determine from effective DOM/layout evidence whether the Created panel is a
   useful side region, a native audit panel, or an administrator override:
   - if the page contract intentionally has no side content, use the existing
     `sideDisabled` pattern;
   - if useful side content must remain, use `isWide` and a corrected grid/span
     without hiding it;
   - never overwrite administrator-owned layout files from an installer hook.
7. Add narrow-screen CSS only where the existing upload rules do not already
   wrap correctly. Preserve keyboard, focus, drag-over, selected-file,
   validation, and removal states and the TUVTK theme variables required by
   `AGENTS.md`.

### Acceptance criteria

- `#GeneratorPerioadeCursuri/view/{id}` and saved-record edit use the normal
  available width consistent with the extension's other wide pages.
- Source File uses a full row and no longer wraps text or controls word by word.
- Upload instructions, action, accepted-format badges, selected file, and remove
  control render normally.
- The layout remains usable at common desktop/tablet widths and a narrow mobile
  viewport without horizontal page overflow caused by the upload component.
- CSS affects only the main GeneratorPerioadeCursuri page.
- Administrator-owned runtime layouts are not reset or rewritten.

### Proportional validation

- Parse the two layout JSON files.
- Extend focused offline assertions for wide flags, page class, side decision,
  full-width Source File cells, and preserved detail export behavior.
- Run only the affected offline layout/view tests.
- Perform read-only browser inspection at representative wide and narrow
  viewport sizes after the user separately deploys through their normal
  process; do not alter the current instance during implementation.

## 8. Stage 5 - Normalize the main generation pipeline and XLSX output to `title`

### Objective

Keep one `title` name from source parsing through generation preview and export,
and make new XLSX files write the exact header `title`.

This stage can start in a new chat after reading the contract in Section 3. It
does not require Stages 1 through 4.

### Dependencies

- The title normalization/conflict contract in Section 3 is approved.

### Expected changes

Likely files:

- `CourseInputParser.php`
- `GenerationService.php`
- `XlsxExportService.php`
- `views/generator-perioade-cursuri/record/detail.js`
- focused parser/generation/export tests and XLSX fixtures.

Implementation rules:

1. Preserve `CourseInputParser`'s canonical `title` output and document its
   returned shape precisely.
2. Stop renaming `title` to `courseTitle` in transient generated rows. Update
   the generation response, grouping/export projection, and browser preview to
   consume `title`.
3. Change the generated Program worksheet header from `Nume curs` to exact
   lowercase `title`.
4. Keep visible UI labels such as English `Course Name` and Romanian `Nume curs`
   as localized presentation labels; the file header/internal key contract does
   not require renaming UI captions.
5. Preserve formula-injection protection, row order, month headers, date values,
   workbook sheet names, holidays sheet, and export attachment behavior.
6. Inspect any persisted row/entity field named `courseTitle` before changing
   it. Current generation reports `rowPersistence: false`; if a storable field
   is discovered in the effective branch, stop and split schema compatibility
   into a separately approved migration stage.

### Acceptance criteria

- Source `title` remains `title` in parsed course, generated row, JSON preview,
  grouped export data, and consumer-facing response.
- New XLSX Program worksheets contain exact header `title` and no generated
  `Nume curs` header.
- Romanian course-title values remain unchanged.
- Scheduling, grouping, order, months, hyperlinks/text, and safe spreadsheet
  handling remain unchanged.
- No schema migration is introduced.

### Proportional validation

- Add a focused pure parser/generation-shape test.
- Generate XLSX bytes in an offline test with the existing PhpSpreadsheet
  dependency, reopen them, and assert the first-row header and Romanian value.
- Extend the production AMD detail-view test to consume `title` rows.
- Run `php -l` on modified PHP and only the affected PHP/Node tests.

## 9. Stage 6 - Preserve Word Matcher compatibility with `nume curs`

### Objective

Make `title` canonical and preferred in Word Matcher while keeping existing
`nume curs` files behaviorally compatible.

This stage can start in a new chat after Stage 5. It should not modify the
generation algorithm, Word scoring rules, or UI layout.

### Dependencies

- Stage 5 canonical producer contract.
- Section 3 alias/conflict rules.

### Expected changes

Likely files:

- `WordConversionService.php`
- a small pure course-title header policy/resolver in the existing
  `Tools/GeneratorPerioadeCursuri` namespace, if inspection confirms it can be
  reused without merging parser error contracts;
- focused Word Matcher fixtures/tests.

Implementation rules:

1. Resolve `title` first and map legacy `nume curs` into canonical internal
   `title`; retain `course name` because it is already accepted.
2. Apply the exact duplicate/both-columns contract from Section 3.
3. Translate resolver conflicts into the Word Matcher's existing `BadRequest`
   channel with useful row/header context and no file contents in logs/errors.
4. Feed the resolved title through the unchanged normalization, scoring,
   suggestions, preview selection, reviewed generation, and DOCX replacement
   paths.
5. Do not change fuzzy/exact matching thresholds or auto-selection rules while
   making header compatibility changes.

### Acceptance criteria

- XLSX with `title` works in preview and generation.
- Existing XLSX with `nume curs` produces the same matching, suggestions,
  selected rows, dates, and generated Word output as before.
- Header case and surrounding whitespace are ignored.
- Romanian title values and diacritics survive column resolution and still
  participate in the existing matcher normalization correctly.
- Both-column equal/blank/fallback/conflict cases follow Section 3 exactly.
- Duplicate normalized headers fail explicitly rather than silently selecting
  a column.

### Proportional validation

- Add paired `title` and `nume curs` fixtures with identical logical data and
  assert identical preview payloads/matching decisions.
- Add case/whitespace, Romanian-diacritic, both-column, and duplicate-header
  fixtures.
- Exercise reviewed generation for at least one legacy file, not preview only.
- Run `php -l` and the focused Word Matcher compatibility tests. Do not run the
  browser or integration suite automatically.

## 10. Stage 7 - Apply the same title contract to remaining consumers

### Objective

Remove remaining alternate internal title keys and duplicated alias decisions
from XML and WordPress conversion without changing their distinct parser rules.

This stage can start in a new chat after Stage 6. It is separate from the XML
encoding stage: Stage 1 controls serialization; this stage controls input
column naming and internal data shape.

### Dependencies

- Stages 5 and 6, including the approved reusable title policy.

### Expected changes

Likely files:

- `XmlScheduleParser.php`
- `MecXmlBuilder.php` only to consume event key `title` instead of
  `courseTitle`;
- `WordPressScheduleParser.php`
- `WordPressUpdaterService.php` only if its typed shapes/call sites require it;
- existing parser fixtures and WordPress contract tests;
- a focused XML parser/builder contract test.

Implementation rules:

1. Reuse only the title header resolver from Stage 6. Retain separate file
   decoding, month rules, row handling, limits, and exception translation.
2. Make XML parser events use canonical `title`; update the builder's input
   shape without changing its XML element names (`title`, `post_title`).
3. Keep WordPress parser/service payloads on their existing canonical `title`
   key while replacing local alias selection with the shared policy.
4. Preserve legacy `nume curs` and existing `course name` input compatibility
   for both consumers.
5. Update historical compatibility tests/fixtures to include new `title`
   producer output; do not delete the legacy fixture.

### Acceptance criteria

- The main generator, Word Matcher, XML converter, and WordPress updater all
  resolve the logical field to internal `title`.
- New generated `title` XLSX files work in every downstream workflow.
- Existing `nume curs` files continue to work in every workflow that already
  accepted them.
- Conflict, duplicate, case, whitespace, and Romanian-diacritic behavior is
  identical across consumers, subject only to documented consumer-specific
  empty-row/error translation.
- No whole-parser abstraction or behavior drift is introduced.

### Proportional validation

- Run paired canonical/legacy fixtures through XML parsing and WordPress
  preview parsing.
- Extend `tests/offline/parser-merge-boundaries.php` and the smallest applicable
  WordPress phase test.
- Rerun the focused Stage 1 XML builder test because its input key changes.
- Run syntax checks on modified PHP.

## 11. Stage 8 - Cross-stage regression and package verification

### Objective

Verify the completed fixes as one release without using the existing EspoCRM
instance or introducing infrastructure.

This stage can start in a new chat when all selected implementation stages are
complete. It should not add new behavior.

### Dependencies

- Stages 1 through 7 that are selected for the release are complete.

### Expected changes

- Update `manifest.json`, README current-version text, and the hard-coded
  version/date assertions in `tests/wordpress-updater/phase-6.php` consistently
  according to the repository's release policy.
- Ensure `tests/run-safe.sh` lists new focused offline tests, but do not run that
  broad suite automatically under the global repository instructions.
- Do not hand-edit lockfiles or add/update/remove dependencies.

### Acceptance criteria

- Each issue's focused acceptance criteria remains satisfied after integration.
- The package inventory exactly matches manifest, README, `files/`, and
  `scripts/` and excludes tests, docs, credentials, dependencies, and stale
  copies.
- New XLSX output uses `title`; legacy `nume curs` fixtures remain in tests and
  pass.
- XML output is valid UTF-8 with literal Romanian characters.
- No schema, migration, installer menu, ACL, endpoint, attachment ownership, or
  unrelated page behavior changes.
- No archive is installed into the current EspoCRM instance.

### Proportional validation

Run the focused tests from each changed stage once after integration. Syntax
check all modified PHP and parse all modified JSON. Do not run integration,
browser, infrastructure, or full framework suites automatically.

If package creation is authorized, announce the generated artifact and run:

```bash
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts
php GeneratorPerioadeCursuri/tests/wordpress-updater/phase-6.php \
  "dist/generator-perioade-cursuri-<manifest-version>.zip"
```

Provide the following broader command for the user to run manually if desired:

```bash
bash GeneratorPerioadeCursuri/tests/run-safe.sh
```

Any browser check must use a package deployed separately through the user's
normal approved process. Validate year reset, name error, wide detail/edit,
Source File responsiveness, Word Matcher canonical/legacy files, and XML output
without changing administrator layouts or creating a test instance.

## 12. Stage handoff requirements

Every stage handoff must be self-contained and state:

- repository commit/branch and applicable instructions read;
- confirmed findings and assumptions still requiring runtime/core inspection;
- exact files changed and the rule owner affected;
- focused commands actually run and their results;
- dependency/schema/package status;
- the next stage's prerequisites.

Use one focused commit per stage. Do not combine XML encoding, holiday state,
validation, layout, or title compatibility into one implementation commit even
if they are released in one archive.
