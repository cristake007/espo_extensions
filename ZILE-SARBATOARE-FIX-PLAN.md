# ZileSarbatoare Fix Implementation Plan

Status: documentation only; no implementation has been performed.

Target extension: `ZileSarbatoare`

Current manifest version inspected: `0.9.0`

Technical entity type: `ZileLibere`

## 1. Current implementation inspection and likely root causes

### Confirmed repository findings

- `scripts/AfterInstall.php` registers the technical entity type `ZileLibere` in
  `calendarEntityList`, `tabList`, and `quickCreateList`. The installer does not
  register the visible Romanian text directly.
- Both locale files below define `Global.scopeNames.ZileLibere` and
  `Global.scopeNamesPlural.ZileLibere` as `Zile libere`:
  - `files/custom/Espo/Modules/ZileSarbatoare/Resources/i18n/en_US/Global.json`
  - `files/custom/Espo/Modules/ZileSarbatoare/Resources/i18n/ro_RO/Global.json`
- The same files already label the module key `ZileSarbatoare` as
  `Zile Sărbătoare`. The stale navbar text is therefore owned by the entity
  scope translations, not by the module manifest or installer configuration.
- `Resources/metadata/scopes/ZileLibere.json` keeps the entity visible as a tab
  and calendar entity. Nothing in the requested label change requires changing
  the scope name, PHP classes, route, table, or entity type.
- `Resources/layouts/ZileLibere/edit.json`, which is the packaged create/edit
  layout, does not contain `syncedAt` or `sourceYear`. Those fields occur in
  `detail.json`; `syncedAt` also occurs in `list.json`. This conflicts with the
  report that the malformed values are visible on the packaged
  `#ZileLibere/create` form and must be resolved during runtime inspection.
- The ZileSarbatoare client code contains no CSS file, generated-content rule,
  literal `â€“`, or literal en-dash placeholder. Its custom edit and detail
  modal views delegate rendering to EspoCRM's native modal views.
- A repository-wide text search found no literal `â€“` in the extension source.
  A separately scoped generated-content rule exists outside ZileSarbatoare,
  but its presence is not evidence that it affects this entity and it is not
  part of this plan's implementation scope.
- The inspected `dist/zile-sarbatoare-0.9.0.zip` copies of the two Global locale
  files, `ZileLibere` entity definitions, and edit layout match the current
  repository files byte-for-byte.

### Likely root causes

The navbar root cause is confirmed: both locale-specific entity scope labels
still contain `Zile libere`.

The malformed-character root cause is not yet confirmed. The repository
evidence rules out a literal mojibake value in the current ZileSarbatoare
source, but it does not prove whether the runtime value is:

1. CSS `::before`/`::after` content on an EspoCRM loading or empty-value node;
2. a native EspoCRM placeholder decoded with the wrong stylesheet or response
   charset;
3. an installed theme or administrator customization outside this package;
4. stale cached or installed extension assets that differ from version `0.9.0`;
5. a text node produced by a formatter, translation, metadata override, or
   custom layout that is not present in the packaged default; or
6. a route/view identification mismatch, because the packaged create layout
   does not render the two reported fields.

No corrective CSS or custom formatter should be chosen until the rendered DOM,
computed pseudo-element content, loaded asset, and effective runtime layout
identify the authoritative source.

## 2. Scope, ownership, and non-goals

The implementation must follow these rules:

- Keep `ZileSarbatoare` as the technical module name and `ZileLibere` as the
  technical entity type.
- Change user-facing translation values only where the label audit shows that
  the old navbar/breadcrumb text is being resolved.
- Reuse EspoCRM's native empty-value and field formatting behavior where it can
  represent the required result. Add a module-specific field view only if the
  native formatter cannot be configured and the formatter is confirmed as the
  owner.
- Give empty-value presentation one owner. Do not add competing fixes in CSS,
  translation JSON, metadata, and JavaScript.
- Do not add dependencies, schema changes, migrations, containers, databases,
  services, or test infrastructure.
- Do not modify GeneratorPerioadeCursuri or combine its release/package work
  with this plan.
- Do not install or rebuild the extension, clear cache, rewrite administrator
  layouts, or otherwise modify the existing EspoCRM instance while executing
  this plan.
- If the malformed character is owned by a different extension, the theme, or
  EspoCRM core, do not hide it with a ZileSarbatoare workaround. Record the
  owning component and obtain approval for that separate scope.

## 3. Stage 1 - Confirm label resolution and malformed-character origin

### Objective

Establish the exact source of both visible behaviors without changing files or
runtime state.

This stage is safe to start in a new Codex chat. Begin at the
`projects/espo_extensions` repository root, read `../AGENTS.md` and `AGENTS.md`,
confirm a clean worktree, and inspect only ZileSarbatoare plus read-only runtime
evidence supplied by the user.

### Dependencies

None.

### Inspection work

1. Reconfirm the packaged label sources in both `Global.json` locale files and
   identify whether EspoCRM uses `scopeNames`, `scopeNamesPlural`, or both for:
   - navbar tab;
   - list-page title and breadcrumb;
   - create-page title and breadcrumb;
   - quick-create entry;
   - calendar entity label.
2. Audit `ZileLibere.json` in both locales, `scopes/ZileLibere.json`,
   `clientDefs/ZileLibere.json`, all `ZileLibere` layouts, and installer menu
   registration. Classify every occurrence as a technical identifier, a
   user-facing label, or prose. Do not bulk-replace `ZileLibere`.
3. On the affected page, collect read-only browser evidence:
   - exact route and locale;
   - effective fields rendered and the effective layout source;
   - the DOM node containing `â€“`;
   - whether it is a text node or computed `::before`/`::after` content;
   - element classes, especially `loading-value`;
   - the matching CSS rule and loaded asset URL, if generated content is used;
   - response `Content-Type`/charset for the owning HTML, JavaScript, CSS, or
     metadata response;
   - whether the value exists in the response bytes or is introduced only
     during rendering.
4. Compare the installed extension version and relevant installed file hashes
   with the source/package read-only. Do not clear caches or replace files.
5. Reproduce create, edit, detail, and list rendering separately. Distinguish a
   temporary loading indicator from the final rendering of a null value.
6. If the source is external to ZileSarbatoare, stop the malformed-character
   branch and report the exact owning component. The label branch may proceed
   independently.

### Likely affected components

This is an inspection-only stage. The likely sources are:

- `Resources/i18n/{en_US,ro_RO}/Global.json` for labels;
- `Resources/metadata/entityDefs/ZileLibere.json` and the `ZileLibere` layouts
  if a metadata formatter is confirmed;
- a ZileSarbatoare client field/view only if native metadata is insufficient;
- an external loaded stylesheet, theme, runtime customization, or cache if the
  repository remains clean of the malformed value.

### Acceptance criteria

- The navbar translation category and key are confirmed for both locales.
- Every old label occurrence is classified; technical identifiers are not
  mistaken for visible text.
- The malformed output is traced to one concrete text node, pseudo-element,
  formatter, translation, metadata value, or external asset.
- The discrepancy between the reported create route and packaged edit layout is
  explained.
- Confirmed findings and remaining assumptions are stated separately in the
  stage handoff.

### Proportional validation

- Parse all inspected JSON files with a JSON parser.
- Use byte/hex inspection only on the small owning value or asset fragment.
- Compare relevant archive entries with source using read-only `unzip -p` and
  `cmp`.
- Do not run an extension build or any test suite in this stage.

## 4. Stage 2 - Rename the navbar and related entity-scope labels

### Objective

Display `Zile sărbătoare` in the navbar without renaming the entity or changing
unrelated descriptions.

This stage can start in a new chat after the label portion of Stage 1 is
complete. It does not depend on the malformed-character investigation.

### Dependencies

- Stage 1 must confirm the keys used by the navbar and breadcrumbs.

### Expected changes

Update the confirmed visible translation entries in:

- `files/custom/Espo/Modules/ZileSarbatoare/Resources/i18n/en_US/Global.json`
- `files/custom/Espo/Modules/ZileSarbatoare/Resources/i18n/ro_RO/Global.json`

The expected minimum is to set both `scopeNames.ZileLibere` and
`scopeNamesPlural.ZileLibere` to the required visible text in both installed
locales, because both currently contain the obsolete text. Keep these keys and
all technical identifiers unchanged.

Inspect, but change only when semantically required:

- `Resources/i18n/{en_US,ro_RO}/ZileLibere.json`, including
  `Create ZileLibere`;
- README navigation examples that literally instruct users to open
  `Zile libere`;
- existing label-contract tests.

Do not mechanically rename prose such as “non-working day” or “zi liberă” when
it describes record semantics rather than the navbar label.

### Reuse-first implementation rules

- Use EspoCRM's existing `Global.scopeNames` and `scopeNamesPlural` translation
  mechanism. Do not add a custom navbar view or installer label.
- Preserve `ZileLibere` in installer arrays, API routes, PHP namespaces,
  metadata filenames, entity classes, table naming, ACL, tests of technical
  identity, and retained data.
- Extend the closest existing contract test rather than creating a new test
  framework. `tests/phase-000/contract.test.mjs` is the likely home for the
  locale label contract.

### Acceptance criteria

- Navbar, list title, breadcrumb, quick create, and calendar labels display the
  approved text wherever they use the entity scope label.
- Neither locale contains the obsolete `Zile libere` value in the confirmed
  navbar/scope translation keys.
- `ZileLibere` remains the technical entity type and existing records/routes
  require no migration.
- Other entities and modules are unchanged.

### Proportional validation

- Parse the two modified locale JSON files.
- Run the focused label contract test, for example:

  ```bash
  node --test ZileSarbatoare/tests/phase-000/contract.test.mjs
  ```

- Search translation values for the obsolete navbar text and manually review
  every remaining match instead of requiring technical/prose matches to vanish.
- Runtime label confirmation is read-only unless the user separately deploys
  the package through their normal process.

## 5. Stage 3 - Correct the confirmed malformed-character source

### Objective

Remove mojibake from empty technical values at its authoritative source while
preserving correct loading feedback and Romanian UTF-8 text.

This stage can start in a new chat only after Stage 1 identifies the exact
owner. Do not implement from the symptom alone.

### Dependencies

- Stage 1 malformed-character root-cause evidence.
- Stage 2 is not a functional dependency.

### Expected changes

Choose exactly one of these paths based on evidence:

1. **Metadata/translation source:** correct the specific value in the owning
   ZileSarbatoare JSON file and retain valid UTF-8.
2. **ZileSarbatoare formatter/view:** configure the existing native field view
   first. If that cannot express the behavior, add the smallest module-scoped
   field view and assign it only to the affected field(s). Null/empty values
   must render as empty text or one intentional, correctly encoded placeholder.
3. **ZileSarbatoare CSS generated content:** replace or remove only the
   confirmed module-scoped rule. Do not use a global selector and do not mask
   real field content.
4. **External owner:** make no ZileSarbatoare code change. Report the theme,
   other extension, administrator override, stale installed artifact, or core
   asset that owns the output and request a separate authorized task.

Potential ZileSarbatoare files, only if confirmed, include:

- `Resources/metadata/entityDefs/ZileLibere.json`
- `Resources/layouts/ZileLibere/{edit,detail,detailSmall,list}.json`
- `Resources/i18n/{en_US,ro_RO}/ZileLibere.json`
- a narrowly scoped client field/view under
  `files/client/custom/modules/zile-sarbatoare/src/views/fields/`
- `tests/phase-007/detail-presentation.test.mjs`

### Behavior contract

- A final null/empty `syncedAt`, `sourceYear`, or other field using the same
  formatter renders nothing or the single approved placeholder.
- A temporary loading state remains distinguishable from a final empty value.
- No `â€“`, `Ã`, replacement character, or other mojibake is rendered.
- Romanian values such as `Sărbătoare`, `Țara`, and `Sincronizată` remain valid
  UTF-8.
- The same owner behaves consistently in create, edit, detail, and list modes
  whenever that field/formatter is present. The default create layout need not
  add technical fields merely to test them.

### Acceptance criteria

- The fix is located in the component proven to create the malformed output.
- No duplicate CSS, JavaScript, translation, or metadata workaround is added.
- Empty, populated, and loading states render correctly.
- Shared formatter behavior is clean across the applicable view matrix.
- Unrelated EspoCRM pages are unaffected by selector/view scope.

### Proportional validation

- Extend the closest existing phase-007 presentation test or add one focused
  test beside it that executes the production formatter/view behavior.
- Parse modified JSON and run syntax validation on modified JavaScript.
- Search the extension source and built archive for common mojibake byte
  sequences.
- Use a small UTF-8 fixture with Romanian diacritics and an XML/HTML-special
  character if a formatter is changed.
- Do not modify the current EspoCRM instance for validation.

## 6. Stage 4 - Regression, release metadata, and package verification

### Objective

Verify the two fixes together without broadening their implementation scope and
produce a reviewable extension archive only when release publication is
authorized.

This stage can start in a new chat after Stages 2 and 3 are complete. If Stage
3 proves an external owner, verify only the ZileSarbatoare label change and
record the unresolved external dependency.

### Dependencies

- Stage 2 complete.
- Stage 3 complete or explicitly handed off to its external owner.

### Expected changes

- Add or update only focused regression assertions in existing ZileSarbatoare
  test phases.
- If a release is being prepared, apply the repository's patch-version policy
  consistently to `manifest.json`, README version instructions, and the
  hard-coded version/release assertions in
  `tests/phase-006/package.test.php`.
- Do not add dependencies or change schemas, layouts unrelated to the fix,
  synchronization logic, or installer menu identifiers.

### Acceptance criteria

- Both locales expose the approved entity label.
- The technical entity remains `ZileLibere`.
- The confirmed malformed-character path has empty/populated/loading coverage.
- The installable archive contains the modified source files exactly once and
  contains no tests, docs, credentials, dependency trees, or stale alternate
  copies.
- No package is installed into the existing EspoCRM instance.

### Proportional validation

Run only the focused checks affected by the implementation, such as:

```bash
node --test ZileSarbatoare/tests/phase-000/contract.test.mjs
node --test ZileSarbatoare/tests/phase-007/detail-presentation.test.mjs
```

If package creation is authorized, announce the generated artifact, build from
the repository root using the manifest version, and run the existing archive
contract:

```bash
bash build.sh --extension ZileSarbatoare --zip files scripts
php ZileSarbatoare/tests/phase-006/package.test.php \
  "dist/zile-sarbatoare-<manifest-version>.zip"
```

Inspect the archive's locale, metadata, view, and test-exclusion entries. Broader
runtime validation remains a manual deployment check in a user-approved
environment; this plan does not create one or alter the current instance.

## 7. Stage handoff requirements

At the end of every stage, record in the chat handoff:

- repository commit/branch inspected;
- confirmed findings and unresolved assumptions;
- exact files changed, or “inspection only”;
- focused checks actually run and their results;
- package path if an artifact was explicitly authorized and generated;
- the next stage's prerequisites.

Use one focused commit per implementation stage. Do not commit a stage with
known syntax errors, an unconfirmed malformed-character workaround, or an
unresolved accidental entity rename.
