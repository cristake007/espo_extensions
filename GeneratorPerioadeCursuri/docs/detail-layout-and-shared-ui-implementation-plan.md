# Detail Layout and Shared UI Implementation Plan

Status: Approved

Date: 2026-07-17

Target extension: `GeneratorPerioadeCursuri`

Current extension version: `2.3.2`

Planned release version: `2.3.3`

## 1. Objective

Implement two related but independently reviewable improvements:

1. Remove assignment, audit, and generated-state fields from the extension's
   record detail layouts while keeping their entity metadata, persistence, and
   Entity Manager availability intact.
2. Extract only behaviorally identical, repeated client-side record UI
   primitives into a small shared module without combining entity-specific
   workflow logic.

The affected EspoCRM entities are:

- `GeneratorPerioadeCursuri`
- `GeneratorPerioadeCursuriWordMatcher`
- `GeneratorPerioadeCursuriXmlConverter`
- `GeneratorPerioadeCursuriWordPressUpdater`

## 2. Confirmed Design Decisions

### 2.1 Field visibility

The default detail layouts, not the entity definitions, own whether these
fields are rendered in the main record panel. The implementation will remove
layout cells and preserve the corresponding `entityDefs` entries.

`Responsible Users` is the label for the `assignedUsers` field. The singular
`assignedUser` field is already disabled and is not present in the packaged
detail layouts.

The removal matrix is:

| Entity | Fields removed from `detail.json` |
| --- | --- |
| `GeneratorPerioadeCursuri` | `assignedUsers`, `teams`, `generatedAt`, `createdAt`, `createdBy`, `modifiedAt`, `modifiedBy` |
| `GeneratorPerioadeCursuriWordMatcher` | `assignedUsers`, `teams`, `createdAt`, `createdBy`, `modifiedAt`, `modifiedBy` |
| `GeneratorPerioadeCursuriXmlConverter` | `assignedUsers`, `teams`, `createdAt`, `createdBy`, `modifiedAt`, `modifiedBy` |
| `GeneratorPerioadeCursuriWordPressUpdater` | `assignedUsers`, `teams`, `createdAt`, `createdBy`, `modifiedAt`, `modifiedBy` |

`generatedAt` exists only on `GeneratorPerioadeCursuri`. It will stay defined
and loaded on the record model because the detail view uses it to prevent
duplicate generation. Only its visible layout cell will be removed.

The following will not be changed as part of field visibility:

- entity fields and links;
- database columns or indexes;
- scopes and ACL metadata;
- edit layouts;
- list layouts;
- search layouts;
- translations;
- generated business-output fields such as `wordConvertedAt` and
  `xmlConvertedAt`.

The implementation will not mark these fields `disabled`, `utility`, or
`notStorable`. It will also not add `layoutIgnoreList` by default. A
`layoutIgnoreList: ["detail"]` policy can be considered separately only if
administrators must be prevented from adding the fields back to a Detail
layout.

### 2.2 Shared client component boundary

The canonical file-upload component already exists in:

```text
files/client/custom/modules/generator-perioade-cursuri/src/views/fields/source-file.js
```

It will remain the canonical full-width upload presentation and will retain
EspoCRM's native file lifecycle, metadata-driven `accept` handling, keyboard
behavior, and TUVTK theme conventions.

A new stateless AMD module will own only repeated record-DOM primitives:

```text
files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js
```

Its proposed public functions are:

- `escapeHtml(value)`
- `ensureRecordRegion(root, name)`
- `setActionButtonState(root, action, disabled, title)`
- `synchronizeHorizontalScroll(container, selectors)`

The four entity detail views will continue to extend EspoCRM's native
`views/record/detail`. The shared module will be composed into those views as a
dependency; it will not introduce a new inheritance hierarchy.

### 2.3 Explicit non-components

The following similarities will not be extracted in this work:

- result panel or table rendering, because each workflow has different
  columns, row actions, state, and sticky-column behavior;
- error normalization, because the WordPress updater has stricter filtering
  and different security requirements from the other workflows;
- request, translation, or business-state handling;
- PHP parsers, attachment lifecycles, or API action classes;
- a generic metadata fragment for fields, links, or layouts.

## 3. Phase 1 - Lock the Field-Visibility Contract

### Goal

Add a focused regression test before changing layouts.

### Changes

Create:

```text
tests/offline/detail-layout-field-visibility.mjs
```

The test will:

1. Parse the four entity `detail.json` files.
2. Flatten their field cells into field-name lists.
3. Assert that the removal matrix fields are absent from the corresponding
   detail layouts.
4. Parse the four matching `entityDefs` files.
5. Assert that every removed layout field is still defined for the applicable
   entity.
6. Assert that `generatedAt` remains defined on
   `GeneratorPerioadeCursuri`.
7. Assert that representative business fields remain in each detail layout so
   an accidentally emptied or incorrectly targeted layout is detected.

Add the test to `tests/run-safe.sh` after the existing offline client tests.

### Exit criteria

- The new test fails against the pre-change detail layouts for the expected
  reason.
- The test contains no EspoCRM runtime, network, credential, or database
  dependency.

## 4. Phase 2 - Remove Fields from Packaged Detail Layouts

### Goal

Make fresh installations render only workflow-relevant fields in the main
detail panel.

### Changes

Edit:

```text
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuri/detail.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordMatcher/detail.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriXmlConverter/detail.json
files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/layouts/GeneratorPerioadeCursuriWordPressUpdater/detail.json
```

Rules for the edit:

1. Remove the requested field cells and any rows left empty by their removal.
2. Preserve panel order, labels, styles, and all workflow fields.
3. Keep `exportFile` in the main entity layout after removing its current
   `generatedAt` row partner; make the remaining file cell explicitly
   full-width.
4. Keep the WordPress verified connection panel and its `wpBaseUrl` and
   `wpUsername` fields.
5. Do not modify `entityDefs`, edit, list, or search layouts.

### Exit criteria

- All four JSON files parse successfully.
- The Phase 1 visibility test passes.
- `generatedAt` remains referenced by the main detail view and remains present
  in entity metadata.
- No schema or persistence change is introduced.

## 5. Phase 3 - Verify Side-Panel and Override Behavior

### Goal

Ensure the fields do not reappear elsewhere on the visible detail screen and
avoid overwriting administrator-owned layouts during upgrades.

### Installed-instance checks

For each entity, inspect:

```text
Administration > Entity Manager > {Entity} > Layouts > Side Panel Fields
```

If `assignedUsers` or `teams` is present in an administrator-owned layout,
remove it from that layout. EspoCRM 10.0.0 source confirms that Side Panel
Fields uses `defaultSidePanel.json` and generates `:assignedUser` and `teams`
for entities with those relationships. The package therefore provides an
empty `defaultSidePanel.json` for each of the four extension entities so a
fresh installation does not expose either assignment field in the detail
screen's side panel.

Do not set `sideDisabled` on every detail view solely to hide these two fields.
That would suppress the entire side column and could remove unrelated useful
content.

### Upgrade behavior

EspoCRM administrator customizations under `custom/Espo/Custom` can take
precedence over the module's packaged default layouts. The install hook will
not delete, reset, or rewrite these files automatically.

For an existing installation where the old fields remain visible after the
extension update:

1. Inspect the entity's current Detail and Side Panel Fields layouts.
2. Remove the fields through Layout Manager, or explicitly reset the layout
   only after confirming that local administrator customizations may be
   replaced.
3. Clear EspoCRM cache and reload the record.

### Exit criteria

- Fresh-install defaults are controlled by the packaged layouts.
- Existing administrator customizations are not destructively overwritten.
- The packaged side-panel adjustment uses the confirmed EspoCRM 10
  `defaultSidePanel.json` layout shape.

## 6. Phase 4 - Extract the Shared Record UI Module

### Goal

Remove duplication of stable UI behavior without combining workflow-specific
concepts.

### Changes

Create:

```text
files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js
tests/offline/record-ui.mjs
```

Update the AMD dependency lists and call sites in:

```text
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri/record/detail.js
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-word-matcher/record/detail.js
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-xml-converter/record/detail.js
files/client/custom/modules/generator-perioade-cursuri/src/views/generator-perioade-cursuri-wordpress-updater/record/detail.js
files/client/custom/modules/generator-perioade-cursuri/src/views/fields/holidays.js
```

Implementation rules:

1. `escapeHtml` must preserve numeric zero and convert nullish values to an
   empty string.
2. `ensureRecordRegion` must return an existing matching region before
   creating one and must append new regions to `.record` when available.
3. `setActionButtonState` must be a no-op when the action button does not
   exist; otherwise it must set the native `disabled` property, mirror the
   existing `disabled` CSS class, and clear stale titles when enabled.
4. `synchronizeHorizontalScroll` must validate all required elements, size the
   top scroller from the rendered table, and synchronize both scroll
   directions.
5. Generated-schedule sticky-column sizing remains in the main detail view and
   runs before the shared scrollbar synchronization.
6. The helper must not read or mutate model data, issue requests, translate
   labels, display notifications, or own workflow state.

### Test updates

Update the AMD loaders in:

```text
tests/offline/error-message-quality.mjs
tests/offline/word-matcher-view-state.mjs
```

Update source-contract assertions in:

```text
tests/wordpress-updater/phase-3.php
```

The assertions should verify that the updater still depends on
`views/record/detail` and now also depends on the shared record UI module. They
must not depend on an exact single-item dependency-array string.

### Exit criteria

- The new shared module's pure DOM behavior is covered by the offline test.
- Existing entity-specific view-state and error-quality tests pass.
- WordPress error filtering remains unchanged.
- No result markup, API contract, or workflow state changes.

## 7. Phase 5 - Consolidate Identical CSS Rules

### Goal

Reduce repeated wide-record layout declarations without changing page scopes
or visual behavior.

### Changes

Edit:

```text
files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css
```

Group the existing selectors for:

- one-column `record-grid-wide` layout;
- hidden `.side` column where the corresponding view already disables it;
- full-width `.left` and `.middle` columns;
- identical panel radius and overflow rules.

Preserve:

- current entity-specific page classes used by tests and scoped styling;
- all TUVTK theme variables;
- 3px radii;
- the absence of decorative shadows;
- mobile rules;
- upload drag-over, focus, selected-file, validation, and removal behavior;
- WordPress-specific workspace and table styling.

### Exit criteria

- CSS selector changes are behavior-preserving and remain narrowly scoped.
- Canonical upload component source-contract tests pass.
- No literal replacement colors or new visual dependency is introduced.

## 8. Phase 6 - Release and Package Validation

### Release changes

Update:

```text
manifest.json
tests/wordpress-updater/phase-6.php
```

Set the patch release to `2.3.3` and use the actual release date. Update the
package test's hard-coded version, date, and archive messages in the same
change.

No dependency, database migration, new service, or infrastructure change is
required.

### Targeted checks

Run from the extension directory:

```bash
node --check files/client/custom/modules/generator-perioade-cursuri/src/views/shared/record-ui.js
node tests/offline/detail-layout-field-visibility.mjs
node tests/offline/record-ui.mjs
node tests/offline/error-message-quality.mjs
node tests/offline/word-matcher-view-state.mjs
```

Parse all modified JSON files with a small PHP `json_decode` check using
`JSON_THROW_ON_ERROR`.

### Broader manual validation

Run from the extension repository root:

```bash
cd /opt/codex-workflow/projects/espo_extensions
bash GeneratorPerioadeCursuri/tests/run-safe.sh
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts
```

The package inventory check must confirm that the new shared client module is
included and no tests, documentation, credentials, dependency manifests, or
development artifacts are shipped.

### EspoCRM validation

Install the package on a disposable EspoCRM 10 instance, rebuild, clear cache,
and verify:

1. All four entity detail screens omit the requested fields.
2. The fields remain visible under Entity Manager > Fields.
3. Responsible Users and Teams do not reappear through Side Panel Fields.
4. Edit, list, and search layouts remain unchanged.
5. The main Generate action still becomes unavailable after successful
   generation and remains unavailable after reloading the record.
6. XML generation and download button states remain correct.
7. Word matching preview, selection, and download state remain correct.
8. WordPress preview, connection, row actions, error filtering, and credential
   handling remain correct.
9. Horizontal table scrolling works in both directions for the generated
   schedule and WordPress results.
10. Upload fields remain keyboard accessible and retain metadata-driven file
    type restrictions.

### Exit criteria

- Targeted checks pass.
- The broader safe suite passes when run manually.
- The installable ZIP passes package inventory checks.
- Disposable-instance UI validation passes in both English and Romanian where
  localized messages are affected.

## 9. Review and Commit Boundaries

Keep the work reviewable with these logical boundaries:

1. Field-visibility regression test and four detail-layout changes.
2. Shared `record-ui` module, consumers, and focused unit tests.
3. Behavior-preserving CSS consolidation.
4. Manifest, package-contract, and release updates.

If a component refactor causes unexpected UI behavior, the field-visibility
change can still be released independently because it has no dependency on the
shared client module.

## 10. Completion Criteria

The implementation is complete when:

- every requested field is absent from the applicable default detail layout;
- every field remains defined and available in Entity Manager;
- `generatedAt` continues to own the duplicate-generation rule;
- repeated record-DOM primitives have one tested owner;
- workflow-specific rendering, error, and business rules remain local;
- administrator-owned layout overrides are not destroyed;
- no dependency or schema change is introduced;
- the package and disposable-instance validation requirements pass.
