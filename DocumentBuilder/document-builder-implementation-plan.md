# Document Builder for EspoCRM — Layered Implementation Plan

## 1. Status and authority

This plan covers the incremental implementation of the Document Builder extension described by `document-builder-espocrm-prd.md`.

The source-of-truth order is:

1. The latest explicit product-owner instruction.
2. The PRD.
3. Applicable repository `AGENTS.md` instructions.
4. The current phase brief.
5. Verified EspoCRM 10.0.0 source and official documentation.
6. Existing extension conventions.

Verified baseline: **EspoCRM 10.0.0**. The product-owner-approved manifest constraint is `>=10.0.0`; later admitted releases are not certified without separate validation.

The plan does not turn temporary phase boundaries into permanent product exclusions. Every version 1.0 capability required by the PRD remains represented in a later phase. PRD non-goals and explicitly future enhancements remain outside version 1.0 unless the product owner promotes them.

## 2. Approved product and implementation decisions

- Product and display name: **Document Builder**.
- Canonical backend module name and namespace segment: `DocumentBuilder`.
- Canonical frontend module name: `document-builder`.
- The existing `Documentbuilder` scaffold must be normalized before feature implementation.
- The extension is source-verified against EspoCRM 10.0.0. Its manifest accepts `>=10.0.0` by explicit product-owner decision; compatibility claims for later releases still require explicit validation.
- EspoCRM core files must not be edited. Native behavior is extended only through packaged module/custom metadata and supported extension points.
- Generated PDFs, immutable template versions, snapshots, and generation history are preserved when the extension is uninstalled.
- Reusable design assets use a dedicated `DocumentBuilderMedia` entity backed by EspoCRM `Attachment` records.
- The standard EspoCRM `Document` entity and its `file` field are not modified.
- Required media scope is PNG, JPEG, WebP after compatibility validation, and sanitized SVG after dedicated security and renderer phases.
- Remote images remain disabled.
- Entity relationship traversal starts at two levels and later supports the approved hard maximum of three.
- Manual draft saving with stale-revision detection and unsaved-change protection is authoritative initially.
- Autosave is not required initially, but the revision contract must allow it to be added later without redesign.
- A public verification endpoint is not required initially.
- QR codes may encode existing EspoCRM record URLs or external verification URLs without fetching those URLs.
- `/opt/crm.cursurituv.ro` is production and is prohibited for development installation, test-record creation, experimental validation, or cleanup.
- Runtime validation may run only on a separate non-production EspoCRM instance explicitly supplied by the product owner.

## 3. Verified EspoCRM 10.0.0 capabilities

EspoCRM 10.0.0 already supplies the backend libraries needed for the planned core features:

- Dompdf 3.1.x.
- PhpSpreadsheet 5.7.x.
- chillerlan/php-qrcode 5.x.
- Picqer barcode generation.
- EspoCRM Attachment and File Storage Manager abstractions.
- EspoCRM queued jobs and job scheduling.

It also supplies relevant client libraries, including DOMPurify, Summernote, Shopify Draggable, jQuery UI, GridStack, QR and barcode utilities. A phase may reuse an EspoCRM-bundled library only after confirming its supported loading contract in 10.0.0. No new dependency is planned. Any future dependency proposal requires a separate product-owner decision and the repository-required dependency announcement.

## 4. Architectural ownership

Each business rule has one authoritative owner:

| Rule or responsibility | Authoritative owner |
|---|---|
| Canonical JSON shape, versions, defaults, structural limits | Layout schema validator/normalizer/migrator |
| Template status transitions and editable/immutable state | Template lifecycle service |
| Immutable publication snapshots and version numbering | Template publication service |
| Espo scope, record, field, link, and related-record access | EspoCRM ACL, wrapped by a narrow Document Builder access policy |
| Additional allowed/disabled source entities and fields | Document Builder source policy |
| Stable variable identity and valid path structure | Variable path compiler |
| Entity/spreadsheet value loading | Data-source resolver implementations |
| Formatting and null policy | Value formatter and missing-value evaluator |
| Conditional visibility | Condition evaluator |
| Resolved, renderer-independent document representation | Document tree builder |
| Safe deterministic HTML/CSS emission | HTML renderer and element-renderer registry |
| PDF engine invocation and isolation | PDF renderer adapter |
| Media file validation and attachment ownership | Media service |
| Generation transaction, statuses, filenames, snapshots, and attachment persistence | Generation service |
| Batch idempotency, chunking, progress, retry, and cancellation | Batch coordinator and EspoCRM jobs |
| Unsaved editor state, command history, and selection | Frontend editor state/command layer |
| Browser visual representation | Browser renderer consuming the canonical schema |

Renderers never query the database. API actions validate input and call services. Services own use cases. Resolvers own data access. File services own attachment persistence. Jobs call the same generation services used by synchronous workflows.

## 5. Phase execution contract

Only one phase is implemented at a time. Before changing code in that phase, the implementation session must publish a short phase brief containing only:

- objective and explicit non-goals;
- confirmed dependencies and relevant decisions;
- acceptance criteria;
- files likely to be changed;
- focused checks and any manual runtime validation;
- unresolved choice that would materially change the phase.

The session must inspect the named EspoCRM 10.0.0 APIs before using them. It must not redesign a later phase, add speculative abstractions, or silently begin later work.

Every phase must leave the repository coherent, add or update focused tests for its contracts, and finish with a commit. No implementation branch is pushed automatically. Runtime checks that need EspoCRM must wait for the explicitly supplied non-production instance.

Likely path aliases used below:

```text
<extension-root>/
<backend>/ = <extension-root>/files/custom/Espo/Modules/DocumentBuilder/
<client>/  = <extension-root>/files/client/custom/modules/document-builder/
<custom>/  = <extension-root>/files/custom/Espo/Custom/
<tests>/   = <extension-root>/tests/
```

Exact filenames are confirmed at the start of each phase against EspoCRM 10.0.0 and the implementation produced by prior phases.

## 6. Layer dependency map

```text
1. Foundation and extension architecture
   -> 2. Template entities and versioned schema
   -> 3. Basic flow editor
   -> 4. Entity metadata and variables
   -> 5. PDF rendering and single generation
   -> 6. Grid layout and media manager
   -> MVP acceptance checkpoint
   -> 7. Icons, lists, QR/barcodes, and SVG
   -> 8. Freeform pixel-perfect sections
   -> 9. Dynamic tables, repeaters, and deeper relationships
   -> 10. CSV/XLSX sources and batch generation
   -> 11. Record integration, hardening, and release validation
```

The layers are sequential. Focused tasks inside a layer may be executed only in the listed order unless the plan is explicitly revised.

---

# Layer 1 — Foundation and extension architecture

## Phase 00 — Pin the EspoCRM 10.0.0 baseline runtime contract

- **Objective:** Record the exact EspoCRM 10.0.0 source contracts the extension will use and establish the non-production runtime gate.
- **Depends on:** Approved target version and production prohibition.
- **Expected changes:** Add a concise runtime-contract reference covering module loading, routes/API actions, DI, metadata, ACL, attachments/file storage, jobs, PDF services, client library loading, rebuild, install, upgrade, and uninstall behavior. Record exact 10.0.0 commit/tag references. Do not create application functionality.
- **Likely files:** `<extension-root>/docs/phase-00-runtime-contract.md`, `<tests>/phase-00/` contract checks if useful.
- **Validation:** Source links resolve; every planned Espo integration point has a 10.0.0 source or official-documentation reference; the document clearly prohibits `/opt/crm.cursurituv.ro`.
- **Complete when:** Later phases can verify an API choice without relying on memory or examples from another Espo version.

## Phase 01 — Normalize the extension identity and scaffold

- **Objective:** Establish one consistent extension identity before code and database metadata depend on it.
- **Depends on:** Phase 00.
- **Expected changes:** Rename the extension root and backend module directory from `Documentbuilder` to `DocumentBuilder`; retain frontend `document-builder`; update manifest, README, module metadata, translations, and build command. Pin the manifest to the approved EspoCRM/PHP compatibility contract rather than the scaffold's broad defaults.
- **Likely files:** `<extension-root>/manifest.json`, `README.md`, `<backend>/Resources/module.json`, `<backend>/Resources/metadata/app/module.json` if required by 10.0.0, i18n files, frontend root.
- **Validation:** Path/case inventory contains no unintended `Documentbuilder`; JSON and manifest range validation pass; build script recognizes the renamed extension.
- **Complete when:** Folder, namespace, scope prefixes, frontend module, display name, and package identity are unambiguous.

## Phase 02 — Establish package, install, navigation, and safe uninstall lifecycle

- **Objective:** Make the empty module installable and removable without touching business data or unrelated configuration.
- **Depends on:** Phase 01.
- **Expected changes:** Add the minimal Document Builder navigation group, install/uninstall scripts only where required, idempotent config updates, package inventory checks, and an uninstall policy that removes module registration/config changes but never deletes Document Builder records or attachments.
- **Likely files:** `<extension-root>/scripts/AfterInstall.php`, `BeforeUninstall.php`, backend scopes/client metadata/i18n, `<tests>/phase-02/`, build/package files.
- **Validation:** Static package-root inventory; PHP/JSON syntax; idempotency tests for scripts; install/rebuild/uninstall smoke checklist prepared for the future test instance.
- **Complete when:** The package is structurally installable, navigation registration is reversible, and preservation behavior is explicit and tested without a runtime.

## Phase 03 — Confirm bundled dependency and client-library capabilities

- **Objective:** Decide which EspoCRM-bundled libraries and loaders are safe to reuse without adding dependencies.
- **Depends on:** Phase 00.
- **Expected changes:** Add focused capability checks for Dompdf, PhpSpreadsheet, QR/barcode libraries, DOMPurify, rich-text support, drag/reorder, and freeform interaction candidates. Document supported import/loading mechanisms and licenses. Do not choose a UI library merely because it exists; compare only candidates needed by the PRD.
- **Likely files:** `<tests>/phase-03/`, phase runtime-contract reference; no product feature files.
- **Validation:** Checks match the EspoCRM 10.0.0 composer/package metadata and loader definitions; no extension dependency manifest is added.
- **Complete when:** Each planned library use has a verified loading path, or is explicitly deferred to a feasibility phase.

## Phase 04 — Prove renderer feasibility against the required layout modes

- **Objective:** Retire the largest rendering risks before the editor schema becomes expensive to change.
- **Depends on:** Phases 00 and 03; a product-owner-supplied non-production runtime for final execution.
- **Expected changes:** Create isolated renderer fixtures for Romanian text/fonts, flow pagination, conservative table-based grid, freeform millimetre positioning, fixed headers/footers, page numbering/count feasibility, local raster images, WebP, sanitized SVG candidates, QR codes, long table rows, and page breaks. Record measurable support, tolerances, and known Dompdf limitations. These are feasibility fixtures, not the production renderer.
- **Likely files:** `<tests>/phase-04/fixtures/`, `<tests>/phase-04/`, a short compatibility matrix.
- **Validation:** Source-only checks run locally; runtime/PDF checks remain explicitly pending until the test instance exists; no result is inferred from browser rendering alone.
- **Complete when:** The canonical schema and renderer plan can be based on observed 10.0.0 behavior, with unresolved runtime items visibly gated rather than guessed.

## Phase 05 — Create the focused test and fixture foundation

- **Objective:** Give every later phase a small, repeatable place for contract, unit, integration, and PDF acceptance checks.
- **Depends on:** Phases 01–04.
- **Expected changes:** Add phase-oriented test structure, helpers/test doubles only where needed, representative Contact/Account/custom-entity metadata fixtures, ACL cases, media fixtures, malicious inputs, and four named acceptance documents: diploma, offer, history, spreadsheet certificate. Define safe runtime setup/teardown identifiers for the future test instance.
- **Likely files:** `<tests>/README.md`, `<tests>/helpers/`, `<tests>/fixtures/`, initial `<tests>/phase-05/`.
- **Validation:** Test runners execute without a live Espo instance where designed; fixtures are small and license-safe; runtime tests cannot default to the production path.
- **Complete when:** Later phases can add narrow tests without inventing a new harness or unsafe cleanup approach.

## Phase 06 — Define administrator settings and hard operational limits

- **Objective:** Give all resource-sensitive behavior one bounded configuration source before feature services use limits.
- **Depends on:** Phases 00 and 05.
- **Expected changes:** Define backend configuration/defaults for source entities, relationship depth, schema size, elements, nesting, sections, media size/dimensions, imports, rows/worksheets/cells, collections, batches, preview rates, rendering, retention, fonts, SVG, WebP, remote resources, and mass generation. Defaults follow the PRD; safe hard ceilings are separate from editable defaults.
- **Likely files:** `<backend>/Resources/metadata/app/`, config provider/value objects under `<backend>/Tools/DocumentBuilder/`, i18n, `<tests>/phase-06/`.
- **Validation:** Boundary/unit checks for defaults, overrides, invalid values, and hard ceilings; remote-resource setting cannot be enabled because remote images are an approved disabled capability.
- **Complete when:** Feature code can request normalized limits from one provider and cannot silently bypass the hard maximums.

## Phase 07 — Define action permissions, ACL composition, audit, and error contracts

- **Objective:** Establish cross-cutting authorization and failure semantics before extension entities and custom actions proliferate.
- **Depends on:** Phases 00, 05, and 06.
- **Expected changes:** Define action permissions for design, publish, generate, batch, spreadsheet imports, shared media, snapshots, deletion, and settings. Define the Document Builder policy wrapper around Espo ACL, audit event categories, safe user-facing error categories, security-safe logging, and the rule that forbidden data is never disclosed through warnings.
- **Likely files:** `<backend>/Resources/metadata/aclDefs/`, security/policy and error value objects under `<backend>/Tools/DocumentBuilder/`, i18n, `<tests>/phase-07/`.
- **Validation:** Permission matrix tests, redaction tests, and error-to-HTTP mapping contracts. Default role grants that remain a product decision are presented for approval in the phase brief rather than guessed.
- **Complete when:** Every later custom action can depend on a single action-permission and error contract in addition to normal Espo entity ACL.

---

# Layer 2 — Template entities and versioned schema

## Phase 08 — Define canonical layout schema primitives

- **Objective:** Establish the smallest versioned document model that can grow through approved phases without replacing earlier layouts.
- **Depends on:** Phases 04, 06, and 07.
- **Expected changes:** Define schema version, document/page defaults, header/sections/footer sequence, stable element IDs, typed units, source descriptor union, section/element registry mechanism, common style/value objects, and explicit capability markers. Only currently implemented node types may publish; later types are additive schema migrations, not raw unknown JSON.
- **Likely files:** `<backend>/Layout/` or `<backend>/Tools/DocumentBuilder/Layout/`, JSON schema/resources, `<tests>/phase-08/`.
- **Validation:** Valid/invalid JSON fixtures cover IDs, units, bounds, depth, unknown versions/types, and deterministic defaulting.
- **Complete when:** Flow, grid, freeform, entity, spreadsheet, collection, raster, and SVG requirements have viable extension points without accepting unsupported content.

## Phase 09 — Implement schema validation, normalization, and migration services

- **Objective:** Make server-side schema processing authoritative and deterministic.
- **Depends on:** Phase 08.
- **Expected changes:** Implement parse/size checks, structural validation, typed-value bounds, default normalization, stable error paths tied to element IDs, canonical serialization/hash input, migration dispatch, and rejection of unsupported future versions. No renderer or database access belongs here.
- **Likely files:** `<backend>/Layout/Validator`, `Normalizer`, `Migrator`, schema exceptions/results, `<tests>/phase-09/`.
- **Validation:** Unit tests for normalization idempotency, deterministic migration, error locality, limits, malformed JSON, and unsupported versions.
- **Complete when:** Saving, publishing, previewing, and generation can all call the same schema services and obtain the same normalized result.

## Phase 10 — Add the `DocumentBuilderTemplate` entity

- **Objective:** Persist template identity, ownership, draft source configuration, lifecycle status, and current draft schema.
- **Depends on:** Phases 07 and 09.
- **Expected changes:** Add entity/scopes/ACL/record/client metadata, relationships, layouts, repositories only if native behavior is insufficient, ownership/teams, status, source type, entity type, current draft JSON, revision, page summary fields, and current published-version link.
- **Likely files:** `<backend>/Resources/metadata/{entityDefs,scopes,aclDefs,entityAcl,recordDefs,clientDefs}/DocumentBuilderTemplate.json`, layouts/i18n, optional entity/repository class, `<tests>/phase-10/`.
- **Validation:** Metadata/JSON contracts, field invariants, native CRUD/ACL expectations, and rebuild checklist for the test instance.
- **Complete when:** An authorized user can persist a valid draft template record without any visual editor or publication behavior.

## Phase 11 — Add immutable `DocumentBuilderTemplateVersion` records

- **Objective:** Persist exact published layout and data-source snapshots independently from mutable drafts.
- **Depends on:** Phase 10.
- **Expected changes:** Add version entity metadata, template link, monotonic version number, schema version, normalized layout/source snapshots, publisher/time/change note, checksum, current flag, and read-only enforcement hooks/services. Prevent normal update/delete paths from mutating published snapshots.
- **Likely files:** Version entity metadata/layouts/i18n, lifecycle policy/service classes, `<tests>/phase-11/`.
- **Validation:** Immutability, checksum determinism, unique template/version constraint, ACL, and record-read tests.
- **Complete when:** A version record cannot change after creation and remains readable independently of later draft changes.

## Phase 12 — Implement draft lifecycle and optimistic revision control

- **Objective:** Make draft edits safe from stale writes and define valid draft/source state transitions.
- **Depends on:** Phases 09–11.
- **Expected changes:** Add draft save use case/API action with expected revision, normalized-schema storage, revision increment, change note, conflict response, and source-type/entity selection rules. Source changes return an unresolved-reference impact report and require explicit confirmation.
- **Likely files:** Template service/API/routes, revision value objects, frontend-neutral DTOs, `<tests>/phase-12/`.
- **Validation:** First save, stale save, retry, malformed schema, source switch with/without impacted variables, and unauthorized edit tests.
- **Complete when:** Concurrent sessions cannot silently overwrite one another and a source switch cannot hide broken variables.

## Phase 13 — Implement publication and immutable version activation

- **Objective:** Turn a valid draft into one current immutable version through one transactional use case.
- **Depends on:** Phases 11 and 12.
- **Expected changes:** Add complete pre-publication validation orchestration, data-source/media/variable validation hooks, version-number allocation, version snapshot creation, checksum, current-version switch, publish permission, audit record, and rollback on failure. Unimplemented phase capabilities block publication rather than being silently ignored.
- **Likely files:** Publication service/API/routes, lifecycle policy, transaction/repository helpers, `<tests>/phase-13/`.
- **Validation:** Successful publish, every blocking validation category, concurrent publish, rollback, immutable prior versions, and permission denial.
- **Complete when:** Exactly one current published version exists and publication cannot leave a partial or mutable state.

## Phase 14 — Add duplicate, archive, draft-from-version, and version-history workflows

- **Objective:** Complete the approved template lifecycle without compromising existing generation history.
- **Depends on:** Phase 13.
- **Expected changes:** Add duplicate behavior, archive/new-generation prohibition, draft creation from a published version, version list/detail UI metadata/actions, and links to generated documents. Template hard-delete behavior remains disabled unless separately approved; archive is the normal lifecycle endpoint.
- **Likely files:** Template lifecycle service/actions/routes, client action/dialog views, layouts/i18n, `<tests>/phase-14/`.
- **Validation:** Copy inclusion/exclusion rules, archive effects, draft restoration, immutable history, ACL, and navigation tests.
- **Complete when:** Designers can safely evolve templates while every published version and generation reference remains intact.

---

# Layer 3 — Basic flow editor

## Phase 15 — Create the editor route and three-panel shell

- **Objective:** Open a draft-specific visual designer inside EspoCRM with no editing mechanics yet.
- **Depends on:** Phases 10, 12, and 14.
- **Expected changes:** Add client route/controller/view, template action, top toolbar shell, element/variable left panel placeholders, central canvas host, inspector host, status area, loading/error states, and access checks.
- **Likely files:** `<client>/src/controllers/`, `views/template/`, `views/editor/`, `res/templates/`, CSS, backend clientRoutes/clientDefs/i18n, `<tests>/phase-15/`.
- **Validation:** Client contract tests, route/access behavior, empty/loading/error rendering, focus entry, and teardown/listener cleanup.
- **Complete when:** An authorized designer can open the editor for a draft and unauthorized or non-draft access fails clearly.

## Phase 16 — Implement editor state, stable IDs, selection, and command history

- **Objective:** Give structural edits one predictable client-side state owner.
- **Depends on:** Phase 15 and schema primitives from Phase 08.
- **Expected changes:** Add normalized editor state, stable ID creation, single selection, command interface, add/remove/move/update/duplicate commands, 100-state undo/redo, saved baseline, and dirty-state calculation. Server requests are not history commands.
- **Likely files:** `<client>/src/editor/state/`, `commands/`, unit tests under `<tests>/phase-16/`.
- **Validation:** Command reversibility, ID stability, history limit, redo invalidation, dirty baseline, selection cleanup, and no-op behavior.
- **Complete when:** All subsequent editor changes can be represented as tested commands instead of ad hoc DOM mutations.

## Phase 17 — Connect manual save, revision conflicts, and unsaved-change protection

- **Objective:** Persist editor state safely using the draft contract without adding autosave.
- **Depends on:** Phases 12 and 16.
- **Expected changes:** Add Ctrl/Cmd+S, save button/state, client schema precheck, expected revision, success baseline reset, actionable 409 conflict dialog, retry/reload choices, navigation/browser unload warning, and save error display.
- **Likely files:** Editor controller/state service, API service, dialogs/i18n, `<tests>/phase-17/`.
- **Validation:** Save success/failure/conflict, keyboard shortcut, leave warning, baseline reset, and no silent overwrite tests.
- **Complete when:** Manual save is authoritative and the data/revision contract can later support debounced autosave without changing server semantics.

## Phase 18 — Add page settings, canvas geometry, and zoom

- **Objective:** Represent paged document geometry consistently without storing browser pixels.
- **Depends on:** Phases 08, 16, and 17.
- **Expected changes:** Add A4/Letter portrait/landscape settings, administrator-controlled custom size hook, margins, locale/timezone/default font/color/line height, filename/title patterns, millimetre-to-screen conversion, page frames, fit-width/fit-page and 25–200% zoom. Zoom never mutates schema values.
- **Likely files:** Schema/page validators, editor page settings/renderer utilities/inspector, CSS, `<tests>/phase-18/`.
- **Validation:** Unit conversions, page presets, custom-size authorization, invalid printable area, zoom invariance, and save/reload tests.
- **Complete when:** One canonical geometry produces stable page frames at every zoom level.

## Phase 19 — Implement flow sections and containers

- **Objective:** Allow ordered, naturally sized document structure with bounded nesting.
- **Depends on:** Phases 16–18.
- **Expected changes:** Add flow section/container schemas and validators, library items, drag/drop insertion, reorder/move between compatible parents, breadcrumbs, drop indicators, margin/padding/min-height, keep-together/start-new-page flags, and nesting limits.
- **Likely files:** Backend element definitions/validators; client elements, drag-drop, canvas views, inspector; `<tests>/phase-19/`.
- **Validation:** Add/reorder/move/cancel drag, invalid parent, depth/element limits, save/reload, and undo/redo tests.
- **Complete when:** A multi-section flow hierarchy can be built without text elements or PDF rendering.

## Phase 20 — Add heading, static text, and restricted paragraph elements

- **Objective:** Support safe primary document content, including structured rich text without raw HTML editing.
- **Depends on:** Phase 19 and Phase 03's confirmed rich-text/DOMPurify contracts.
- **Expected changes:** Add heading/static text/paragraph nodes, editor controls, restricted formatting representation, inline-variable token placeholder contract, server sanitizer, escaping, and allowed/disallowed content rules. Source values remain text even inside rich content.
- **Likely files:** Element schemas/validators/sanitizer; client element views/inspectors/rich-text adapter; `<tests>/phase-20/`.
- **Validation:** Allowed formatting round-trip, XSS/event/style/iframe/URL payload rejection, undo/redo, paste sanitation, and server-authoritative normalization.
- **Complete when:** Designers can author styled prose without any path to raw HTML, CSS, or executable content.

## Phase 21 — Add divider, spacer, and manual page-break elements

- **Objective:** Complete the source-independent flow element set required for the initial editor.
- **Depends on:** Phases 19 and 20.
- **Expected changes:** Add typed divider/spacer/page-break nodes, bounded dimensions/styles, editor-only page-break labels, insertion/reorder/inspector controls, and browser page-flow markers.
- **Likely files:** Element definitions/renderers/inspectors, i18n, `<tests>/phase-21/`.
- **Validation:** Bounds, orientation where permitted, page-break serialization, undo/redo, and browser preview behavior.
- **Complete when:** A static multi-page document can be composed from the complete basic flow library.

## Phase 22 — Implement common styling and typography

- **Objective:** Give flow elements one typed, inheritable style system that later grid/freeform/media elements reuse.
- **Depends on:** Phases 18–21.
- **Expected changes:** Add document/section/element inheritance, typed box model, borders, colors, opacity, alignment, safe width/height constraints, controlled Espo/Dompdf font catalogue, font sizes/weights/style/decoration/line height/letter spacing/text transform, and Romanian-capable defaults. No raw CSS strings are stored.
- **Likely files:** Backend style value objects/validators/normalizer; client style controls; CSS/browser style mapper; `<tests>/phase-22/`.
- **Validation:** Inheritance precedence, bounds/enums/colors, unsafe CSS rejection, font fallback, Romanian diacritics, and save/reload tests.
- **Complete when:** One validated style contract is shared by editor and future server renderers.

## Phase 23 — Build the basic browser renderer and editor validation experience

- **Objective:** Render static flow layouts from canonical state and make schema problems actionable.
- **Depends on:** Phases 20–22.
- **Expected changes:** Separate browser renderer from editor state, add type-aware sample placeholders, page-flow approximation, element/section badges, global error/warning summary, click-to-focus, blocking versus warning severity, keyboard focus states, deletion confirmation for complex nodes, and accessible labels.
- **Likely files:** `<client>/src/editor/renderer/`, `validation/`, views/status/dialogs, CSS/i18n, `<tests>/phase-23/`.
- **Validation:** Renderer purity, sample/empty distinctions, error navigation, non-color indications, keyboard traversal, and accessibility-state tests.
- **Complete when:** A non-technical user can create, validate, save, reload, and visually review a static flow template.

---

# Layer 4 — Entity metadata and variables

## Phase 24 — Implement the ACL-aware entity catalogue

- **Objective:** Discover eligible standard/custom EspoCRM entities dynamically for the current user.
- **Depends on:** Phases 06, 07, and 10.
- **Expected changes:** Add entity-catalogue service/API, metadata inspection, source allow/deny policy, system/internal exclusions, translated labels, scope-read checks, safe ACL-sensitive caching, and source selector UI.
- **Likely files:** `<backend>/DataSource/EntityCatalogue/`, API/routes, source policy/cache, client source selector/service, `<tests>/phase-24/`.
- **Validation:** Contact, Account, custom entity, disabled/internal scope, no-read user, translation, cache isolation, and malicious entity-name tests.
- **Complete when:** Eligible custom entities appear without extension changes and forbidden scopes never appear.

## Phase 25 — Implement the readable field and relationship catalogue

- **Objective:** Expose a safe, searchable metadata tree without treating labels as identifiers.
- **Depends on:** Phase 24.
- **Expected changes:** Add field/link classification, translated labels, types, direct/calculated/single/collection flags, required/read-only display metadata, secret/technical/unsupported exclusions, field/link ACL filtering, circular-path guards, and expandable field-browser UI.
- **Likely files:** Entity catalogue field/link services and DTOs, API/routes, client variable browser/tree/search, `<tests>/phase-25/`.
- **Validation:** Standard/custom fields, field ACL, password/token exclusions, single/multiple links, circular metadata, search, labels, and cache isolation.
- **Complete when:** The browser presents only paths that the current designer may inspect and clearly distinguishes scalar from collection data.

## Phase 26 — Define stable variable identities and compile variable paths

- **Objective:** Give every data reference a stable internal representation that can be validated before resolution.
- **Depends on:** Phases 08 and 25.
- **Expected changes:** Implement variable source/type/path value objects, canonical serialization, direct/related/system/spreadsheet/collection identities, display labels as non-authoritative metadata, path compiler, source/entity compatibility checks, and scalar-versus-collection usage rules.
- **Likely files:** `<backend>/DataSource/Variable/`, schema integration, client variable insertion/token models, `<tests>/phase-26/`.
- **Validation:** Stable identity under label changes, malformed/circular/excess-depth paths, wrong source/entity, collection-in-scalar rejection, and deterministic serialization.
- **Complete when:** Layouts store no label-derived or arbitrary raw data paths.

## Phase 27 — Add formatting, system variables, and missing-value policies

- **Objective:** Resolve presentation rules through a finite safe vocabulary rather than expressions.
- **Depends on:** Phase 26.
- **Expected changes:** Implement type-aware date/datetime/number/currency/boolean/enum/multi-value formatting; case/trim/prefix/suffix/fallback; system variables; and missing-value policies including hide element/row/section, warning, and required-value failure. Page variables remain renderer-owned placeholders where necessary.
- **Likely files:** formatter/missing-value/system-variable services, variable element schema/inspector, i18n, `<tests>/phase-27/`.
- **Validation:** Locales/timezones/currencies/enums/booleans, null versus forbidden versus invalid states, format bounds, Romanian output, and no arbitrary expressions.
- **Complete when:** Scalar presentation and absence behavior are deterministic and independent of the renderer.

## Phase 28 — Resolve direct entity fields with server-side ACL

- **Objective:** Load only required direct values from one readable source record.
- **Depends on:** Phases 07, 24–27.
- **Expected changes:** Add entity resolver interface/implementation, source scope and record checks, per-field read checks, required-field collection from layout, bounded select/query planning, raw-value result types, access-restricted markers without value leakage, and source provenance.
- **Likely files:** `<backend>/DataSource/EntityResolver/`, query/path collectors, ACL adapter, `<tests>/phase-28/`.
- **Validation:** Scope/record/field allowed and denied cases, deleted record, custom field, unused fields not loaded, invalid client path rejection, and error redaction.
- **Complete when:** Direct Contact/Account/custom-entity values can be resolved safely without one query per element.

## Phase 29 — Resolve single-record relationships to depth two

- **Objective:** Traverse approved single links efficiently while rechecking every link, field, and related record.
- **Depends on:** Phase 28.
- **Expected changes:** Extend the query planner/resolver for single links, default two-level depth, cycle detection, deduplication/preload where supported, null related records, link ACL, related record ACL, and administrator source restrictions. Collection links remain catalogue-only until Layer 9.
- **Likely files:** Entity resolver/path/query planner, ACL policy, `<tests>/phase-29/`.
- **Validation:** One/two-level paths, null/deleted link, circular/excess path, denied link/field/record, bounded query count, and custom relationships.
- **Complete when:** Approved related scalar fields resolve without exposing collections or forbidden related data.

## Phase 30 — Add sample and real-record preview data APIs

- **Objective:** Let the editor preview permitted entity data without trusting layout-supplied arbitrary queries.
- **Depends on:** Phases 27–29.
- **Expected changes:** Add type-aware sample generator, real-record selector/API, resolved-value DTOs, real/sample/missing/restricted/invalid distinctions, rate-limit hook, and editor binding. The server derives required paths from the normalized draft.
- **Likely files:** Preview service/API/routes, sample generator, client preview dialog/service/renderer binding, `<tests>/phase-30/`.
- **Validation:** Sample coverage, readable/unreadable record selection, server-derived paths, rate-limit behavior, stale draft revision, and client visual distinctions.
- **Complete when:** A designer can switch safely between sample and real-record preview without direct arbitrary field queries.

## Phase 31 — Implement conditions, required variables, and source-change diagnostics

- **Objective:** Add bounded visibility/required rules and make source incompatibility visible before publication.
- **Depends on:** Phases 26–30.
- **Expected changes:** Add finite condition schema/operators/types, bounded all/any groups, evaluator, targets, editor builder, required-variable generation rules, unresolved-variable diagnostics, and complete source-switch impact reporting. No arbitrary expression language or unbounded nesting.
- **Likely files:** `<backend>/Layout/ConditionEvaluator`, condition schema/validator, client condition builder/validation, lifecycle source-change service, `<tests>/phase-31/`.
- **Validation:** Every supported operator/type, invalid operands, bounds, missing/restricted values, element/parent targets, unresolved publication block, and deterministic evaluation.
- **Complete when:** Conditional visibility and required data have one safe owner and source changes cannot produce silently broken published templates.

---

# Layer 5 — PDF rendering and single-record generation

## Phase 32 — Build the intermediate resolved document tree

- **Objective:** Separate data resolution and policy evaluation from HTML/PDF mechanics.
- **Depends on:** Phases 09, 27–31.
- **Expected changes:** Add immutable normalized tree nodes containing resolved safe values, styles, media references, page instructions, condition results, warnings, provenance, and future collection slots. The builder invokes resolvers/formatters/conditions but never emits HTML. Forbidden values do not enter the tree.
- **Likely files:** `<backend>/Rendering/DocumentTreeBuilder`, tree node/value classes, `<tests>/phase-32/`.
- **Validation:** Static/sample/real fixtures, hidden nodes, required failures, warning paths, stable order/IDs, absence of raw entities, and deterministic tree snapshots.
- **Complete when:** Browser/server renderers can be tested against a data-access-free resolved representation.

## Phase 33 — Implement the conservative flow HTML renderer

- **Objective:** Convert the resolved flow tree into deterministic escaped markup and typed generated CSS.
- **Depends on:** Phases 22, 23, and 32.
- **Expected changes:** Add renderer and element-renderer registry for flow sections, containers, headings, text/paragraphs, variables, dividers, spacers, and page breaks. Generate only allowlisted CSS properties/values; escape all source text; use conservative block/table constructs; do not accept raw markup/styles.
- **Likely files:** `<backend>/Rendering/HtmlRenderer`, element renderers/style mapper/templates, `<tests>/phase-33/`.
- **Validation:** Golden HTML for core fixtures, escaping/injection payloads, page-break/keep rules, style mapping, and renderer purity.
- **Complete when:** Flow HTML is reproducible, data-access-free, and safe for Dompdf input.

## Phase 34 — Implement the isolated Dompdf adapter and PDF preview

- **Objective:** Render one fresh PDF instance from safe generated HTML using EspoCRM 10.0.0 capabilities.
- **Depends on:** Phases 04 and 33.
- **Expected changes:** Add PDF adapter, engine/config selection constrained to the verified Dompdf contract, fresh-instance lifecycle, local resource/chroot rules, JavaScript/remote access disabled, memory/time/error handling, ephemeral preview endpoint/response, cleanup, and preview rate enforcement. Do not create generation history for previews.
- **Likely files:** `<backend>/Rendering/PdfRenderer`, preview service/API/routes, temp-file helper, `<tests>/phase-34/`.
- **Validation:** Focused PDF generation/readability, Romanian text, page geometry/count, injection/remote-resource rejection, fresh-instance test, timeout/failure cleanup, and rate limit. Runtime items wait for the supplied test instance.
- **Complete when:** A normalized draft can produce an ephemeral authoritative PDF preview without persistent business records.

## Phase 35 — Add headers, footers, page numbers, and page count behavior

- **Objective:** Complete paged flow-document chrome with reserved printable space.
- **Depends on:** Phases 18, 33, and 34.
- **Expected changes:** Add header/footer regions with allowed text/image/variable/grid-ready nodes, heights, first-page visibility, disable-on-full-page hook, fixed Dompdf markup, current page, and page-count implementation only if Phase 04 proved it. If total page count is not reliable, publication surfaces a compatibility error rather than a false value.
- **Likely files:** Schema/validators/tree nodes, browser and HTML renderers, header/footer editor views, `<tests>/phase-35/`.
- **Validation:** Reserved margins, first page, page numbers across multi-page fixtures, page-count compatibility result, no overlap, and browser/PDF comparison.
- **Complete when:** Headers and footers behave predictably and PDF preview remains the final authority.

## Phase 36 — Add the `DocumentBuilderDocument` generation-history entity

- **Objective:** Persist generation state and provenance separately from files and source records.
- **Depends on:** Phases 07, 11, and 13.
- **Expected changes:** Add entity metadata for statuses, template/version/source identity, source display name, spreadsheet/batch hooks for later phases, output filename, PDF attachment field/link, data/template snapshots, warnings/errors, generator/timestamps, ownership/teams, and immutable-after-completion policy.
- **Likely files:** Entity/scopes/ACL/record/client metadata, layouts/i18n, entity/access policy, `<tests>/phase-36/`.
- **Validation:** Metadata contracts, valid status transitions, completed-field immutability, snapshot permission, normal generated-document ACL, and future optional links remaining nullable.
- **Complete when:** A pending generation record can represent success, warning, failure, or cancellation without storing PDF bytes in JSON/database fields.

## Editor recovery approval gate — before Phase 37 implementation

- **Status:** Phase 36 is the latest implemented phase on `main`; Phase 37 and all later phases are paused at this gate.
- **Objective:** Recover the existing flow editor as a document-first, directly editable canvas before generation persistence extends the completed implementation surface.
- **Authoritative recovery specification:** [`docs/editor-recovery-assessment.md`](docs/editor-recovery-assessment.md).
- **Constraints:** Retain EspoCRM views, templates, AMD modules, canonical editor state and commands, existing backend services, and the current PDF pipeline. Do not begin Layer 6 grid or media work as part of recovery.
- **Approval:** Implementation must not start until the revised assessment, affected-file map, and recovery sequence are approved.
- **Complete when:** The recovery acceptance criteria are satisfied and focused regression checks confirm that draft save, undo/redo, variables, preview data, PDF Proof, validation, and existing flow rendering contracts remain intact.

## Phase 37 — Persist PDF attachments, snapshots, and safe filenames transactionally

- **Objective:** Give completed generation artifacts durable storage and recoverable failure behavior.
- **Depends on:** Phases 32, 34, and 36.
- **Expected changes:** Add file service using Espo Attachment/File Storage Manager, safe filename pattern compiler, collision handling, `.pdf` enforcement, resolved-data snapshot of used values only, template-version/template snapshot policy, attachment parent/field metadata, transactional status updates, temp cleanup, and rollback/failed-state handling.
- **Likely files:** `<backend>/File/`, snapshot/filename services, generation repository/service, `<tests>/phase-37/`.
- **Validation:** Local/storage abstraction doubles, unsafe/reserved/duplicate filenames, source/template change after generation, attachment write failure, rollback, cleanup, immutability, and sensitive log redaction.
- **Complete when:** A completed record always has a durable readable PDF and matching provenance; a failed record is never marked completed.

## Phase 38 — Implement single-record generation and its template-side workflow

- **Objective:** Complete the first entity-to-stored-PDF vertical use case.
- **Depends on:** Phases 13, 28–37.
- **Expected changes:** Add generation service/API, published-version selection, template/source compatibility, permission/ACL rechecks, required-data validation, status orchestration, synchronous-versus-background threshold hook, download reference, template detail/test-generation dialog, and generated-history link. Drafts remain preview-only except explicit designer test behavior defined by the PRD.
- **Likely files:** Generation service/API/routes, template client actions/dialogs, generated-document views, `<tests>/phase-38/`.
- **Validation:** Success/warnings/failure, wrong entity/template, draft/published rights, deleted source, ACL changes between preview/generation, safe download access, and no duplicate side effect on failed request retry.
- **Complete when:** An authorized user can generate, store, view, and download one PDF for one readable Espo record.

## Phase 39 — Validate flow rendering parity and the single-generation milestone

- **Objective:** Stabilize the complete flow/entity workflow before grid and media expand the rendering surface.
- **Depends on:** Phases 24–38.
- **Expected changes:** Add offer/report flow acceptance fixtures, PDF text/page/geometry checks, browser-versus-PDF comparison checklist, query-count assertions, failure matrix, and manual test script for the supplied non-production instance. Fix only defects within completed phases.
- **Likely files:** `<tests>/phase-39/`, acceptance fixtures/checklists, affected completed-phase files for fixes.
- **Validation:** Standard and custom entity, direct/related variables, null/conditions, headers/footers, multi-page flow, attachment/snapshot/history, ACL denial, and Romanian output.
- **Complete when:** The flow vertical slice is stable enough to be a dependency for visual layout work; unexecuted runtime checks are explicitly recorded.

---

# Layer 6 — Grid layout and media manager

## Phase 40 — Implement grid-section schema and editor behavior

- **Objective:** Add visually structured rows/columns without changing flow semantics.
- **Depends on:** Phases 19, 22, and 39.
- **Expected changes:** Add grid section/container configuration, 1–24 columns with default 12, spans, rows/natural flow, gaps, bounded nested grids, column overlay, snap/drop behavior, compatible element moves, inspector controls, and schema migration if required.
- **Likely files:** Grid schema/validator/normalizer; client grid elements/drag-drop/renderer/inspector/CSS; `<tests>/phase-40/`.
- **Validation:** Spans/gaps/nesting/bounds, reorder/move, overlay/zoom, save/reload, undo/redo, and invalid placement tests.
- **Complete when:** Designers can create structured headers/signature rows visually, while publication still blocks grid output until the server renderer exists.

## Phase 41 — Translate grid layouts into conservative PDF markup

- **Objective:** Render grid sections without CSS Grid or Flexbox dependencies.
- **Depends on:** Phases 33, 34, and 40.
- **Expected changes:** Add normalized grid-row planning, deterministic percentage widths, table-based or proven conservative markup from Phase 04, vertical alignment/padding/gaps, overflow validation, browser mapping updates, and renderer compatibility warnings.
- **Likely files:** Document tree grid nodes/planner, HTML/browser grid renderers, `<tests>/phase-41/`.
- **Validation:** One/two/multi-column fixtures, span totals, nested limit, long content, page breaks, no Grid/Flex CSS in PDF HTML, and browser/PDF proportion comparison.
- **Complete when:** Grid output is deterministic in Dompdf and no longer blocked at publication.

## Phase 42 — Add the attachment-backed `DocumentBuilderMedia` entity

- **Objective:** Create a reusable, ACL-aware asset catalogue designed for raster and later SVG files.
- **Depends on:** Phases 06, 07, and 36's attachment conventions.
- **Expected changes:** Add media entity fields for name, attachment, kind, MIME, dimensions, size, checksum, validation status/result, ownership/teams, active state, and reference metadata needed for safe deletion checks. Model formats generically so SVG adds a validation pipeline, not a replacement entity.
- **Likely files:** Media entity/scopes/ACL/record/client metadata, layouts/i18n, access policy, `<tests>/phase-42/`.
- **Validation:** Metadata, attachment relationship, ownership/team ACL, valid state transitions, immutable validated file identity, and no changes to Espo `Document` metadata.
- **Complete when:** Media metadata can safely represent PNG/JPEG/WebP/SVG lifecycle states even though only raster upload is enabled next.

## Phase 43 — Implement secure PNG/JPEG media upload and validation

- **Objective:** Accept the first approved media formats through native attachment storage with authoritative server checks.
- **Depends on:** Phases 37 and 42.
- **Expected changes:** Add upload/API workflow, MIME/extension/signature agreement, PNG/JPEG decoding, size/dimension/pixel/decompression limits, checksum, ownership/teams, attachment parent/field metadata, failure cleanup, duplicate policy, and inactive/invalid states. Do not accept SVG or WebP yet.
- **Likely files:** `<backend>/File/MediaService`, validators, media API/routes, native/custom file field views only if required, `<tests>/phase-43/fixtures/`.
- **Validation:** Valid images, spoofed MIME/extension, corrupt/truncated/oversized/extreme-dimension files, ACL, duplicate, storage failure, cleanup, and malicious metadata.
- **Complete when:** PNG/JPEG media records are reusable and every stored active asset has passed authoritative validation.

## Phase 44 — Build the media picker and reference-integrity workflow

- **Objective:** Let templates select readable active media and detect missing/inaccessible references.
- **Depends on:** Phases 13, 42, and 43.
- **Expected changes:** Add media list/select/upload dialog, thumbnails/metadata, ownership/team filters, template reference extraction, publication-time access validation for intended generators, missing-media errors, unused-owned delete action, and reference checks. Deleting referenced media is prevented or explicitly handled according to the phase-approved policy.
- **Likely files:** Media service/API, client dialogs/picker/views, publication validator hook, layouts/i18n, `<tests>/phase-44/`.
- **Validation:** Own/team/admin ACL, inaccessible/missing/inactive media, publication block, deletion-in-use, thumbnail failure, search/filter, and cross-user isolation.
- **Complete when:** Templates refer to stable media IDs and cannot silently resolve to an unrelated attachment.

## Phase 45 — Add image and background-image elements

- **Objective:** Render permitted PNG/JPEG assets consistently in browser and PDF layouts.
- **Depends on:** Phases 41, 43, and 44.
- **Expected changes:** Add image/background nodes and media IDs, contain/cover/stretch, aspect ratio, size/alignment/opacity/border/alt text, page/section/container/freeform-ready background contract, safe attachment-to-render-source conversion, missing fallback/error, and local-only rendering.
- **Likely files:** Element schema/tree/browser/HTML renderers/inspectors, media source provider, `<tests>/phase-45/`.
- **Validation:** PNG/JPEG, transparent image, fit modes, inaccessible/missing media, CSS/data injection, no remote fetch, PDF presence, and browser/PDF comparison.
- **Complete when:** Raster images and backgrounds work in flow/grid and the schema can later place the same element in freeform sections.

## Phase 46 — Validate and enable WebP end to end

- **Objective:** Promote WebP from represented media type to accepted product format only after observed renderer compatibility.
- **Depends on:** Phases 04 and 42–45; supplied non-production runtime.
- **Expected changes:** Add WebP signature/decode validation, capability gate, browser/PDF fixtures including alpha/large dimensions, failure message when the environment cannot render it, and upload/picker enablement only when validated.
- **Likely files:** Media validator/config/capability provider, render-source adapter, fixtures/tests, i18n.
- **Validation:** Valid/corrupt/spoofed WebP, transparency, PDF readability, memory limits, capability-disabled behavior, and existing PNG/JPEG regression.
- **Complete when:** WebP is either enabled with test evidence on the supported runtime or remains visibly blocked pending that evidence—not removed from scope.

## Phase 47 — Complete the PRD MVP acceptance checkpoint

- **Objective:** Certify the full MVP vertical slice before adding the advanced element/freeform/collection/spreadsheet surface.
- **Depends on:** Phases 00–46.
- **Expected changes:** Add/finalize MVP acceptance fixtures and only defect fixes within completed scope. No Layer 7 feature implementation begins here.
- **Likely files:** `<tests>/phase-47/`, acceptance checklist/results, affected existing files for bounded fixes.
- **Validation:** Install/rebuild on supplied test instance, template lifecycle, flow/grid editor, static/variable/image elements, direct/two-level related data, browser/PDF preview, single stored PDF, snapshots/history, ACL, en/ro basics, and uninstall preservation rehearsal on test data.
- **Complete when:** Every PRD MVP criterion passes or is explicitly blocked by the missing non-production runtime; the repository remains a coherent releasable MVP package.

---

# Layer 7 — Icons, lists, QR/barcodes, and SVG support

## Phase 48 — Add bundled icons and decorative shapes

- **Objective:** Provide safe visual primitives without accepting arbitrary icon markup.
- **Depends on:** Phases 22, 41, and 45.
- **Expected changes:** Add approved bundled icon registry, icon element, rectangle/circle/line shapes, typed size/color/background/opacity/rotation/layer properties, raster/vector-safe render representation, editor library/inspector, and renderer implementations.
- **Likely files:** Bundled resources/icons, element definitions/renderers/inspectors, `<tests>/phase-48/`.
- **Validation:** Registry allowlist, unknown icon rejection, style bounds, browser/PDF output, shape geometry, and injection attempts.
- **Complete when:** Icons and shapes need no user-provided HTML/SVG and render consistently in flow/grid with freeform-ready geometry properties.

## Phase 49 — Add static structured content and signature presets

- **Objective:** Support repeatable static/variable lists, editable static tables, and a reusable signature composition.
- **Depends on:** Phases 20, 27, and 48.
- **Expected changes:** Add icon-list items/orientation/dividers/gaps; static and manually composed bullet/number lists; inline variables; empty/max-item rules; static-table columns/rows/header/widths/cell alignment/borders/zebra/padding/repeated-header settings; accessible editors; and signature block as a preset composed of existing grid/elements rather than a separate renderer where possible.
- **Likely files:** List/static-table schemas, renderers and inspectors, preset factory, i18n, `<tests>/phase-49/`.
- **Validation:** Static/variable list values, item editing/reorder, empty/max behavior, table row/column edits and limits, widths/cell variables/header behavior, horizontal/vertical lists, signature preset decomposition, and browser/PDF output.
- **Complete when:** Required static lists and tables work without duplicating layout/rendering rules already owned by core elements.

## Phase 50 — Add local QR and approved barcode elements

- **Objective:** Generate codes locally from bounded safe values without external services or URL fetching.
- **Depends on:** Phases 03, 27, 32, and 34.
- **Expected changes:** Add QR element with static/variable/composed/record URL value, length/scheme checks, error correction, quiet zone/colors/label, local server renderer, browser preview adapter, and PDF readability fixtures. Add the PRD-approved barcode types that the bundled 10.0.0 library proves reliable; unsupported candidate types require a product decision rather than substitution.
- **Likely files:** QR/barcode schemas, renderers, validators, inspectors, `<tests>/phase-50/`.
- **Validation:** Length, URLs, no fetch, injection, supported/unsupported types, contrast/quiet zone, scanner-readable output, and browser/PDF comparison.
- **Complete when:** QR is mandatory and reliable; any additional barcode support has explicit tested type coverage.

## Phase 51 — Implement the SVG sanitization and security pipeline

- **Objective:** Accept SVG media without allowing executable, external, or resource-exhausting content.
- **Depends on:** Phases 42–44 and Phase 07 security contracts.
- **Expected changes:** Add SVG MIME/signature/XML parsing, allowlist sanitizer, removal/rejection of scripts, events, `foreignObject`, external references, unsafe URL schemes, embedded remote/data resources outside approved rules, entity expansion, excessive nodes/paths/coordinates/size, and canonical sanitized output/checksum. Preserve original only if policy explicitly permits; renderers consume sanitized content only.
- **Likely files:** Media SVG validator/sanitizer/value objects, fixtures including malicious corpus, config/i18n, `<tests>/phase-51/`.
- **Validation:** Safe SVGs, every PRD attack class, XXE/entity expansion, namespace tricks, obfuscation, huge complexity, malformed XML, and no regression to raster validation.
- **Complete when:** Sanitized SVG bytes have a clear trust boundary and unsafe SVG never becomes active media.

## Phase 52 — Validate SVG preview/PDF compatibility and enable the format

- **Objective:** Make sanitized SVG a supported browser/PDF media source with deterministic fallback behavior.
- **Depends on:** Phases 04, 45, and 51; supplied non-production runtime.
- **Expected changes:** Compare safe direct/data-URI/rasterization strategies, choose the observed 10.0.0-compatible path, add bounded rasterization only if required and available without an unapproved dependency, enable upload/picker after capability proof, and expose compatibility errors. Keep remote references disabled.
- **Likely files:** SVG render-source adapter, media capability provider, browser/HTML renderers, fixtures/tests.
- **Validation:** Logos/icons with paths/text/gradients/transparency, PDF presence/geometry, sanitizer invariance, memory limits, malformed output, and cross-format regression.
- **Complete when:** SVG is enabled with security and renderer evidence, or implementation stops at an explicit capability blocker requiring product direction rather than dropping SVG.

## Phase 53 — Validate the advanced visual-element layer

- **Objective:** Stabilize icons, lists, codes, shapes, and all four media formats before freeform editing multiplies placement combinations.
- **Depends on:** Phases 48–52.
- **Expected changes:** Add visual acceptance pages and only bounded defect fixes.
- **Likely files:** `<tests>/phase-53/`, visual fixtures/results, affected element/media files.
- **Validation:** PNG/JPEG/WebP/SVG, icon/icon list, lists, QR/barcodes, shapes/signature preset, ACL/missing media, injection corpus, browser/PDF parity, and Romanian labels.
- **Complete when:** Every Layer 7 element has stable schema/editor/tree/browser/HTML/PDF behavior.

---

# Layer 8 — Freeform pixel-perfect sections

## Phase 54 — Add freeform schema and geometry validation

- **Objective:** Define fixed millimetre geometry and printable-area invariants before adding direct manipulation.
- **Depends on:** Phases 08, 18, 45, 48, and 53.
- **Expected changes:** Add freeform section size/full-page/overflow/snap/grid/guides, child x/y/width/height/rotation/layer/lock/hidden properties, element compatibility, bounds, maximum elements, printable area, no-split invariant, and deterministic geometry normalization.
- **Likely files:** Freeform schema/validator/normalizer/tree nodes, `<tests>/phase-54/`.
- **Validation:** Unit conversions, bounds, negative/NaN/extreme values, rotation envelope, full-page geometry, oversized section, overflow policies, and stable normalization.
- **Complete when:** Invalid freeform states are rejected server-side and later UI operations have one canonical geometry model.

## Phase 55 — Implement freeform placement, resize, nudge, and snap

- **Objective:** Provide the minimum precise direct-manipulation workflow.
- **Depends on:** Phases 16, 18, 54, and the interaction library decision from Phase 03.
- **Expected changes:** Add freeform canvas, drag/resize, aspect-ratio rules, minor/major grid, snap toggle, arrow/shift-arrow nudge, zoom-correct pointer conversion, drop from library, command-history integration, and cancel behavior.
- **Likely files:** `<client>/src/editor/freeform/`, commands/renderer/CSS/inspector, `<tests>/phase-55/`.
- **Validation:** Millimetre persistence across zoom, snap/nudge, resize bounds, undo/redo, cancel, keyboard behavior, and save/reload.
- **Complete when:** One element can be placed and resized precisely without DOM position becoming canonical state.

## Phase 56 — Add freeform selection, layers, visibility, and locking

- **Objective:** Make overlapping document designs controllable without corrupting locked or hidden content.
- **Depends on:** Phase 55.
- **Expected changes:** Add multi-selection, shift selection, layer panel, reorder/front/back, lock/unlock, hide/show, group command application without persistent group schema unless required, duplicate/delete, breadcrumbs, and clear selected-state/focus behavior.
- **Likely files:** Freeform selection/layer views and commands, inspector/status, `<tests>/phase-56/`.
- **Validation:** Overlap selection, locked mutation prevention, hidden editor/PDF semantics, multi-command undo, layer determinism, keyboard deletion confirmation, and accessibility.
- **Complete when:** Complex overlapping layouts remain predictable and all mutations flow through commands.

## Phase 57 — Add rulers, guides, alignment, distribution, and rotation

- **Objective:** Complete professional precision tools using the canonical millimetre model.
- **Depends on:** Phases 54–56.
- **Expected changes:** Add millimetre rulers, draggable horizontal/vertical guides, guide snapping, align/distribute controls, rotation handle/input, rotated bounds indicators, and command/history integration. Guides are editor metadata and do not render into PDFs.
- **Likely files:** Freeform guide/ruler/geometry utilities/views/commands, CSS, `<tests>/phase-57/`.
- **Validation:** Zoom conversion, guide persistence, align/distribute formulas, rotation/undo, snap precedence, keyboard/focus, and no guide leakage into render tree.
- **Complete when:** Designers can reproduce measured branded layouts without hand-editing coordinates.

## Phase 58 — Implement overflow, full-page placement, and page-flow interaction

- **Objective:** Integrate unsplittable freeform regions safely into hybrid documents.
- **Depends on:** Phases 35, 54–57.
- **Expected changes:** Add clip/report overflow behavior, start-on-new-page rule when remaining space is insufficient, full-page header/footer disable behavior, page break interaction, bleed unsupported/blocking rule, editor overflow visualization, and publication errors for impossible geometry.
- **Likely files:** Freeform validator/tree/page planner, browser renderer/validation UI, `<tests>/phase-58/`.
- **Validation:** Hybrid flow/freeform documents, remaining-space transitions, full-page margins, oversized elements/section, clip/report modes, and no section split.
- **Complete when:** Freeform sections coexist with flow/grid without changing the pagination rules of either mode.

## Phase 59 — Render freeform sections in Dompdf

- **Objective:** Translate validated millimetre geometry into a relative fixed region with absolutely positioned safe children.
- **Depends on:** Phases 34, 45, 48–52, and 58.
- **Expected changes:** Add freeform tree/HTML renderers, `position: relative` section, typed absolute coordinates, rotation/layers/clipping, local media/code/icon rendering, fresh-instance tests, and tolerance measurements. Absolute positioning remains impossible outside freeform sections.
- **Likely files:** Freeform HTML/browser renderers and style mapper, PDF fixtures, `<tests>/phase-59/`.
- **Validation:** Coordinate/tolerance checks, rotations/layers/clipping, all supported elements/media, page start/no split, injection, and browser/PDF comparison.
- **Complete when:** Freeform PDF output follows the same geometry model as the editor within approved measured tolerances.

## Phase 60 — Recreate and accept the diploma use case

- **Objective:** Prove the complete pixel-perfect workflow with the PRD's representative A4 landscape diploma.
- **Depends on:** Phases 54–59.
- **Expected changes:** Add the diploma acceptance template/fixtures, coordinate baseline, background/logo/text/variables/QR/signature, manual comparison procedure, and only bounded freeform fixes.
- **Likely files:** `<tests>/phase-60/`, example fixture/assets, acceptance result; affected freeform files for fixes.
- **Validation:** A4 landscape page, exact millimetre positions, Romanian diacritics, supported media including sanitized SVG where used, QR readability, no split, ACL, and actual PDF comparison.
- **Complete when:** Pixel accuracy is demonstrated with evidence rather than claimed from browser appearance.

---

# Layer 9 — Dynamic tables, repeaters, and deeper relationships

## Phase 61 — Extend relationship discovery to collections and depth three

- **Objective:** Expose approved multi-record paths and the hard maximum relationship depth without weakening existing cycle/ACL rules.
- **Depends on:** Phases 25, 26, and 29.
- **Expected changes:** Add collection-source descriptors, related entity field catalogue, administrator/user depth handling up to hard max three, circular-path detection across mixed paths, scalar/collection context rules, and editor collection browser.
- **Likely files:** Entity/field catalogue, variable compiler, client browser, config/i18n, `<tests>/phase-61/`.
- **Validation:** One/multiple links, max depth 2/3, cycles, forbidden links/fields/scopes, custom relationships, and collection-in-scalar rejection.
- **Complete when:** Designers can select a collection and its readable row fields through stable identities.

## Phase 62 — Implement bounded ACL-aware collection query planning

- **Objective:** Load related records efficiently with safe sort, filter, limit, and truncation policies.
- **Depends on:** Phases 06, 07, 28, and 61.
- **Expected changes:** Add collection resolver/query planner, related-record ACL filtering, sortable field allowlist, primary/secondary sort, typed safe filter compiler, per-element limit/hard maximum, pagination/bounded queries, truncation/warning result, deduplication, and query metrics. Raw query parameters are never accepted from layout JSON.
- **Likely files:** Entity resolver collection/query/filter services, ACL adapter, `<tests>/phase-62/`.
- **Validation:** Sort/filter operators, bad types/fields, limits/truncation, inaccessible records, large fixture, bounded query count, and no N+1 behavior.
- **Complete when:** Collection consumers receive a finite safe row set with explicit truncation and provenance.

## Phase 63 — Add the dynamic related-record table editor

- **Objective:** Let designers configure a table from one collection path and its readable scalar fields.
- **Depends on:** Phases 40, 61, and 62.
- **Expected changes:** Add dynamic-table schema, relationship source, columns/labels/widths/formats, sort/filter/limit controls, header/zebra/border/padding/empty/truncation settings, totals configuration hooks, editor preview sample rows, and validation.
- **Likely files:** Table schema/validator; client element/column editor/filter dialog/inspector; `<tests>/phase-63/`.
- **Validation:** Column/source compatibility, width totals, unsupported fields/operators, maximum columns, edit/reorder, sample rows, save/reload, and publication diagnostics.
- **Complete when:** A dynamic table can be designed safely but remains publication-blocked until server pagination exists.

## Phase 64 — Render dynamic tables with pagination and repeated headers

- **Objective:** Produce multi-page related-record tables within verified Dompdf constraints.
- **Depends on:** Phases 32–35, 62, and 63.
- **Expected changes:** Add collection rows to the document tree, table HTML renderer, repeated headers, fixed/percentage widths, empty behavior, truncation warnings, avoid-row-split where practical, and explicit oversized-row handling based on Phase 04. Never claim a row can split if Dompdf cannot do it.
- **Likely files:** Tree builder, table renderer/page rules, fixtures, `<tests>/phase-64/`.
- **Validation:** Empty/small/500-row/truncated tables, repeated headers, long cells/oversized row, page count, ACL-filtered rows, width overflow, and browser/PDF comparison.
- **Complete when:** Offer-item/history tables paginate predictably with honest compatibility behavior.

## Phase 65 — Add collection aggregations and totals

- **Objective:** Provide count and sum through typed collection rules, not arbitrary formulas.
- **Depends on:** Phases 27, 62, and 64.
- **Expected changes:** Add collection-count variables/elements, numeric-field sum, totals-row formatting, empty/truncated aggregation policy, and editor controls. Average/min/max remain outside initial aggregation scope unless explicitly promoted.
- **Likely files:** Collection aggregation service, table/variable schema/renderers/inspectors, `<tests>/phase-65/`.
- **Validation:** Count/sum, currencies/decimals/nulls, unsupported field types, ACL-filtered input, truncated set semantics, and totals rendering.
- **Complete when:** Required v1.0 aggregations have one deterministic owner and cannot execute arbitrary expressions.

## Phase 66 — Add repeaters and collection-backed lists

- **Objective:** Render a designed block or list item once per accessible related record.
- **Depends on:** Phases 49, 62, and 64.
- **Expected changes:** Add repeater schema with flow/grid child layout, collection context variable paths, sort/filter/limit/empty behavior, page break between/keep item together, maximum child depth, related-record bullet/icon lists, and nested-repeater prohibition for v1.0. The prohibition is an approved PRD boundary, not a limitation on ordinary repeaters.
- **Likely files:** Repeater/list schemas/tree/renderers; client context-aware editor/inspectors; `<tests>/phase-66/`.
- **Validation:** Empty/multiple/maximum rows, scoped variables, item pagination/oversize, list markers/icons, ACL, nesting rejection, and query reuse.
- **Complete when:** Course-history cards and related-record lists render without duplicating collection queries per child.

## Phase 67 — Accept offer and history collection workflows

- **Objective:** Stabilize all repeating-data behavior before spreadsheet rows introduce another collection-like source.
- **Depends on:** Phases 61–66.
- **Expected changes:** Add offer-items and course-history acceptance templates, query/performance baselines, ACL matrices, and only bounded fixes.
- **Likely files:** `<tests>/phase-67/`, acceptance fixtures/results, affected collection files.
- **Validation:** Dynamic table across pages, repeated header, totals, repeater cards, related list, empty/truncated data, forbidden related records, depth three, and bounded query count.
- **Complete when:** The two representative entity-collection documents meet their PRD acceptance criteria.

---

# Layer 10 — CSV/XLSX sources and batch generation

## Phase 68 — Add the attachment-backed `DocumentBuilderImport` entity and retention lifecycle

- **Objective:** Persist temporary spreadsheet source identity, mapping, validation state, and expiration safely.
- **Depends on:** Phases 06, 07, 37, and 42's attachment patterns.
- **Expected changes:** Add import entity with source attachment, format, worksheet/header config, column schema/sample/validation summary, row count/status/expiry, owner/teams, and optional completed-batch provenance. Add upload size/type/signature checks and retention state transitions; no rows are generated yet.
- **Likely files:** Import entity metadata/layouts/i18n, import service/API, retention policy, `<tests>/phase-68/`.
- **Validation:** CSV/XLSX attachment lifecycle, spoof/corrupt/oversize file, ACL, expiry, status transitions, cleanup safety, and no macros/extraction behavior.
- **Complete when:** A permitted temporary source file can be uploaded and tracked without parsing unbounded content.

## Phase 69 — Parse CSV and XLSX safely

- **Objective:** Extract bounded worksheet/row/cell values using EspoCRM-bundled parsers without executing active content.
- **Depends on:** Phases 03, 06, and 68.
- **Expected changes:** Add CSV encoding/BOM/delimiter handling; XLSX worksheet discovery/selection; row/column/worksheet/cell-length limits; formula policy using cached value or text only; macro non-execution; dates/numbers/booleans; and streamed/bounded reading where supported. CSV and XLSX adapters share normalized rows but remain separate implementations.
- **Likely files:** `<backend>/DataSource/Spreadsheet/Parser/`, fixtures/corpus, `<tests>/phase-69/`.
- **Validation:** Valid/edge CSV and XLSX, multiple sheets, formulas, dates, empty rows, encodings, huge dimensions, malformed ZIP/XML, zip bombs, and memory bounds.
- **Complete when:** Both formats yield a finite normalized row stream with original row numbers and no active-content execution.

## Phase 70 — Implement header detection, column typing, mapping, and row validation

- **Objective:** Turn parsed rows into a user-approved stable spreadsheet schema with actionable errors.
- **Depends on:** Phases 26, 27, and 69.
- **Expected changes:** Add header-row selection/detection, stable normalized column keys with collision handling, editable labels, supported type inference, sample rows, required columns/values, row validation, safe URL/email/date/number conversion, invalid-row summaries, and duplicate output-name diagnostics.
- **Likely files:** Spreadsheet schema/type/validation services, import wizard client views, i18n, `<tests>/phase-70/`.
- **Validation:** Duplicate/blank/non-Latin headers, type ambiguity, invalid values with source row numbers, required rules, sample bounds, and deterministic schema confirmation.
- **Complete when:** The user confirms a schema before spreadsheet columns can become template variables.

## Phase 71 — Add spreadsheet template sources, variables, and row preview

- **Objective:** Let one template use one confirmed CSV/XLSX schema as its primary source.
- **Depends on:** Phases 12, 26–27, and 68–70.
- **Expected changes:** Add spreadsheet source descriptor/resolver, `row.*` variables, formatting/null/conditions reuse, selected-row preview, sample rows, source switching diagnostics, import expiry behavior, and publication rules for stable column schemas. Entity and spreadsheet sources are not mixed.
- **Likely files:** Spreadsheet resolver/source validator, variable catalogue, preview API/client binding, template lifecycle integration, `<tests>/phase-71/`.
- **Validation:** CSV/XLSX schemas, missing/renamed columns, expired import, row preview, source switch both directions, unresolved publication block, and no entity-variable leakage.
- **Complete when:** A spreadsheet-backed template can preview any valid selected row through the same document tree/render pipeline.

## Phase 72 — Add `DocumentBuilderBatch` and idempotent item orchestration

- **Objective:** Persist batch selection, counts, progress, and item-level idempotency before background execution.
- **Depends on:** Phases 36, 38, 62, and 71.
- **Expected changes:** Add batch entity/status/counts/source selection/import/template version/ZIP/error/ownership fields, item state or deterministic per-item identity, idempotency key rules, immutable template-version binding, selection-size confirmation data, and cancellation/retry state machine. Exact item storage shape is confirmed in the phase brief from scalability evidence.
- **Likely files:** Batch entity metadata/layouts/i18n, batch coordinator/state classes, generated-document batch link, `<tests>/phase-72/`.
- **Validation:** Status/count invariants, duplicate request/idempotency, partial item states, retry/cancel transitions, ACL, and immutable template version.
- **Complete when:** A batch can be queued exactly once and its progress can be reconstructed without scanning files.

## Phase 73 — Execute batches through bounded EspoCRM jobs

- **Objective:** Generate non-trivial batches outside HTTP requests with resumable bounded work.
- **Depends on:** Phases 03, 37–38, and 72.
- **Expected changes:** Add job classes/scheduling, bounded chunks, queue/group choice verified against 10.0.0, per-item generation through the existing generation service, progress persistence, heartbeat/stale recovery, retry failed only, safe cancellation points, terminal reconciliation, and error redaction.
- **Likely files:** `<backend>/Jobs/`, batch coordinator/repository, job metadata if required, `<tests>/phase-73/`.
- **Validation:** Chunking, crash/retry, duplicate delivery, partial failure, cancellation, success/warning/error counts, no duplicate PDFs, and cron/daemon manual checklist.
- **Complete when:** Batch work never blocks the initiating request and job retries cannot regenerate completed items.

## Phase 74 — Generate spreadsheet-row documents and safe ZIP output

- **Objective:** Complete one-PDF-per-valid-row output and batch download.
- **Depends on:** Phases 37, 70–73.
- **Expected changes:** Add valid-only versus block-all option, row-number provenance, per-row generated documents, safe unique filename resolution, ZIP creation without path traversal, ZIP attachment persistence, temporary cleanup, and retention rules for original import/ZIP/PDFs. Combined PDF remains a PRD future enhancement.
- **Likely files:** Spreadsheet batch coordinator, ZIP/file service, generation snapshot integration, `<tests>/phase-74/`.
- **Validation:** Valid/invalid mixed rows, duplicate/unsafe names, ZIP-slip attempts, large bounded batch, partial failure, retry, attachment access, and cleanup.
- **Complete when:** Every valid spreadsheet row has an independently stored PDF and the authorized user can download a safe ZIP.

## Phase 75 — Build batch/import UI and accept spreadsheet workflows

- **Objective:** Expose upload, mapping, validation, generation, progress, errors, retry, cancel, and download coherently.
- **Depends on:** Phases 68–74.
- **Expected changes:** Complete import wizard, row-preview selector, validation summary, valid-only confirmation, batch detail/progress/error views, retry/cancel actions, ZIP download, and spreadsheet certificate acceptance fixture.
- **Likely files:** Client import/batch controllers/views/dialogs/templates/CSS, entity layouts/i18n, `<tests>/phase-75/`.
- **Validation:** Keyboard/dialog focus, mapping reload, invalid-row navigation, progress refresh, partial failure/retry/cancel, safe download ACL, CSV/XLSX acceptance fixtures, and no production access.
- **Complete when:** A non-technical user can complete the entire CSV/XLSX batch workflow with actionable row-specific feedback.

---

# Layer 11 — Record integration, hardening, and final validation

## Phase 76 — Add the global compatible-template record action

- **Objective:** Offer Generate Document on eligible entity record views without per-entity core modifications.
- **Depends on:** Phases 07, 24, 31, and 38.
- **Expected changes:** Add verified 10.0.0 global view setup handler/action integration, compatible-template query, category/description/published date/thumbnail hook, required-value warning, filename option, preview/generate dialog, and ACL checks. The action remains absent where no compatible template exists.
- **Likely files:** `<custom>/Resources/metadata/clientDefs/Global.json` or verified module equivalent, client setup handler/dialog/service, compatibility API, `<tests>/phase-76/`.
- **Validation:** Contact/Account/custom entity, no-template/no-access states, wrong source, condition compatibility, action duplication after rebuild, keyboard/dialog behavior, and generation success.
- **Complete when:** Compatible published templates appear globally without editing each entity definition.

## Phase 77 — Add generated-document panels and regeneration actions

- **Objective:** Show source-linked history and permit explicit regeneration with latest/original versions.
- **Depends on:** Phases 14, 36–38, and 76.
- **Expected changes:** Add source-record panel integration, history query, status/template/date/generator/download, latest-version regeneration, original-version regeneration where permitted, missing source/version handling, and dedicated snapshot permission.
- **Likely files:** Global/clientDefs panel setup, client panel/actions/dialogs, history API/service, layouts/i18n, `<tests>/phase-77/`.
- **Validation:** Panel visibility/ACL, deleted source, archived template, original/latest distinction, snapshot denial, download, and no unrelated scopes.
- **Complete when:** Users can understand and reproduce prior output without mutating its historical record.

## Phase 78 — Add list-view selected-record mass generation

- **Objective:** Start an entity batch from explicit selected records with confirmation and bounded counts.
- **Depends on:** Phases 72–73 and 76.
- **Expected changes:** Add global list setup handler/mass action, compatible-template selection, estimated selected count, maximum enforcement, explicit confirmation, batch creation, latest batch link, and feature setting. Current-filter generation remains disabled unless separately implemented and approved because the PRD marks it future/administrator-controlled.
- **Likely files:** Global client metadata/setup handler, list action/dialog, entity batch API/coordinator, `<tests>/phase-78/`.
- **Validation:** Selected IDs, stale/deleted/inaccessible records, maximum, duplicate request, confirmation, custom entity, partial failure, and absence when disabled.
- **Complete when:** Selected-record generation uses the same idempotent batch pipeline and never blocks the HTTP request.

## Phase 79 — Build administrator settings, diagnostics, and retention operations

- **Objective:** Make approved limits, capabilities, failures, and cleanup observable and safely configurable.
- **Depends on:** Phases 06, 34, 42, 68, and 73.
- **Expected changes:** Add admin-only settings UI, normalized validation, effective/hard limit display, renderer/media/font capability diagnostics, job/failure summaries without sensitive values, preview rate information, cleanup/retention jobs for temporary previews/imports and configured artifacts, and safe manual retry hooks.
- **Likely files:** Admin metadata/routes/views/templates, settings/diagnostic services, cleanup jobs, i18n, `<tests>/phase-79/`.
- **Validation:** Admin denial, invalid/hard-limit settings, capability reporting, expired-import/temp cleanup by exact IDs, retention preservation, log redaction, and job retry safety.
- **Complete when:** Operators can understand limits and failures without filesystem access or exposure of source values.

## Phase 80 — Add template thumbnails and representative example templates

- **Objective:** Improve template selection and provide maintainable acceptance examples for diploma, offer, history, and spreadsheet certificates.
- **Depends on:** Phases 34, 60, 67, and 75.
- **Expected changes:** Generate first-page thumbnail after publication, store as attachment, link to exact version, regenerate on new version, tolerate non-blocking thumbnail failure, and package/import example templates/assets through a documented safe mechanism. Examples do not hardcode customer-specific entities or data.
- **Likely files:** Thumbnail job/service, template/version fields, selector UI, example resources/fixtures, `<tests>/phase-80/`.
- **Validation:** Thumbnail generation/failure/version update/ACL/cleanup and successful example import/use with generic fixtures.
- **Complete when:** All four representative workflows are discoverable and double as regression fixtures.

## Phase 81 — Perform application-security hardening

- **Objective:** Review and close security gaps across schema, ACL, files, rendering, imports, APIs, snapshots, jobs, and downloads.
- **Depends on:** All functional phases through 80.
- **Expected changes:** Execute the PRD security corpus; review authorization at every boundary; XSS/CSS/HTML injection; SVG; MIME/signature/path traversal; remote/SSRF; ZIP; spreadsheet formulas/macros/bombs; layout/collection/batch DoS; stale revisions; unauthorized snapshots/downloads; audit/log redaction; and rate limits. Make bounded fixes without architectural rewrites.
- **Likely files:** `<tests>/phase-81/`, security fixtures/report required by the phase, affected services/validators/policies.
- **Validation:** Every PRD security scenario has evidence; no critical/high finding remains; unresolved lower findings have explicit risk/owner/follow-up.
- **Complete when:** The extension has no known critical/high application-security defect and all sensitive boundaries are server-authoritative.

## Phase 82 — Profile and harden performance and resource use

- **Objective:** Verify editor, resolver, rendering, media, collection, and batch behavior against PRD targets and hard ceilings.
- **Depends on:** Phase 81 and complete functional workflows.
- **Expected changes:** Add/query/render/job metrics where useful, profile medium editor/200 elements, one-to-five-page PDFs, 500-row table, bounded spreadsheet/batch fixtures, ACL-sensitive cache review, memory-aware image handling, and targeted optimizations only where evidence shows a problem.
- **Likely files:** `<tests>/phase-82/`, diagnostics/metrics, affected query/cache/render/job code.
- **Validation:** Measured timings/query counts/memory on the supplied test instance, cache isolation, timeout behavior, and no semantic regression.
- **Complete when:** Targets are met or explicit measured limits are approved; no unbounded query/file/render path remains.

## Phase 83 — Complete accessibility and keyboard interaction

- **Objective:** Ensure the visual editor and workflows remain operable without pointer-only or color-only interaction.
- **Depends on:** Complete UI through Phase 80.
- **Expected changes:** Audit/fix focus order/states, dialog focus trapping/restoration, keyboard shortcuts, drag alternatives, element library/inspector labels, validation announcements/navigation, freeform nudge/layer controls, contrast, status semantics, and image alternative-text editing.
- **Likely files:** Client views/templates/CSS/i18n, accessibility-focused tests under `<tests>/phase-83/`.
- **Validation:** Keyboard-only scenarios, automated semantics where available, manual focus/dialog/contrast checklist, and no shortcut conflict with EspoCRM.
- **Complete when:** All core design/generation workflows are keyboard accessible and validation never relies only on color.

## Phase 84 — Complete English and Romanian localization

- **Objective:** Make all UI, validation, enum/boolean/date/currency output, and examples correct in `en_US` and `ro_RO`.
- **Depends on:** All user-facing phases through 83.
- **Expected changes:** Inventory every label/message/action/status/error/setting; review entity/field translated-label use; Romanian diacritics/fonts; locale/timezone formatting; PDF samples; and translation parity checks. Multi-language variants of one template remain a PRD future enhancement; duplicate templates are the initial solution.
- **Likely files:** Backend/client i18n resources, fixtures/tests, affected formatter/example files.
- **Validation:** Missing-key/parity checks, both UI locales, PDF extraction/visual diacritics, date/currency/boolean/enum formatting, and safe untranslated fallback.
- **Complete when:** No implemented feature lacks both required languages and generated Romanian PDFs render correctly.

## Phase 85 — Validate schema, extension, and EspoCRM upgrade paths

- **Objective:** Prove deterministic upgrades from earlier development packages and schema versions without mutating published history.
- **Depends on:** Phases 09, 11–14, and all later schema migrations.
- **Expected changes:** Consolidate migrators, add old-layout/version fixtures, extension upgrade scripts only where 10.0.0 requires them, metadata/database rebuild checklist, attachment/link preservation, unsupported-future-version failure, and rollback/backup guidance. No destructive migration is allowed without separate approval.
- **Likely files:** Layout migrators/tests, package upgrade scripts, `<tests>/phase-85/`, release documentation.
- **Validation:** Each schema step, direct multi-step migration, normalized hash stability, immutable old published snapshot, extension package upgrade on supplied test instance, and failure recovery.
- **Complete when:** Supported old drafts open/save/publish correctly and historical generated documents remain tied to their original version/snapshot.

## Phase 86 — Validate uninstall and reinstall preservation

- **Objective:** Prove the approved preservation policy using an isolated non-production dataset.
- **Depends on:** Phases 02, 37, 42, 68, 72, 79, and 85; supplied test instance.
- **Expected changes:** Finalize uninstall scripts/config cleanup, document what Espo hard rebuild does to extension tables/metadata, ensure no explicit deletion of records/attachments, and create reinstall/recovery checklist. Do not test on production.
- **Likely files:** Install/uninstall scripts/tests, lifecycle documentation/checklist, package metadata.
- **Validation:** Create isolated template/version/media/import/generated document/batch and attachments; uninstall; verify preserved storage/database state as Espo permits; reinstall/rebuild; verify records/history/files become usable again.
- **Complete when:** Uninstall removes the extension's active registration but does not intentionally destroy business records or files.

## Phase 87 — Run full product acceptance and build the release package

- **Objective:** Produce the production-candidate Document Builder 1.0 package only after all functional and quality gates pass.
- **Depends on:** Phases 00–86.
- **Expected changes:** Run the complete PRD acceptance matrix on the supplied non-production EspoCRM 10.0.0 instance; finalize operator/user documentation, example templates, known measured renderer tolerances, installation/upgrade/uninstall procedures, checksums/version/release date, package inventory, and release ZIP. Make only release-blocking bounded fixes.
- **Likely files:** `<tests>/phase-87/`, README/user/admin/release documentation required by PRD, manifest, build output under the repository's ignored `dist/` path.
- **Validation:** Clean install/rebuild, all four representative workflows, entity/custom entity/spreadsheet sources, flow/grid/freeform, all required elements/media, direct/related/repeating data, single/batch output, history/snapshots, ACL/security/performance/accessibility/i18n, upgrade, uninstall/reinstall, and clean package root.
- **Complete when:** Every version 1.0 definition-of-done item in the PRD has evidence, no critical/high security finding remains, all runtime checks target the approved test instance, and the installable ZIP is reproducible.

---

## 7. Release checkpoints

### Foundation checkpoint

Reached after Phase 14. The package, runtime contracts, settings/security boundaries, schema, template entities, publication, and immutable versions are stable.

### Static flow-editor checkpoint

Reached after Phase 23. A designer can create and safely save a source-independent flow layout.

### Entity-preview checkpoint

Reached after Phase 31. Entity metadata, direct/two-level related variables, formatting, conditions, and safe real-record previews work.

### Single-generation checkpoint

Reached after Phase 39. A published entity template produces a stored PDF with snapshots/history and server-side ACL.

### PRD MVP checkpoint

Reached after Phase 47. Flow, simple grid, PNG/JPEG images/backgrounds, entity variables, preview, single generation, attachment/history, ACL, and English/Romanian basics work end to end. WebP must be either enabled with evidence or visibly pending the required test-runtime compatibility proof.

### Complete visual-designer checkpoint

Reached after Phase 60. Icons/lists/codes/shapes, secure SVG, and freeform diploma layouts work.

### Repeating-data checkpoint

Reached after Phase 67. Dynamic tables, repeaters, related lists, aggregations, safe filters/sorts, depth three, and bounded ACL-aware queries work.

### Spreadsheet/batch checkpoint

Reached after Phase 75. CSV/XLSX imports, row validation, spreadsheet templates, background batches, safe ZIP output, progress/retry/cancel, and history work.

### Version 1.0 release checkpoint

Reached only after Phase 87.

## 8. Explicit temporary limitations versus permanent scope

The following are sequencing limits, not removed requirements:

- Grid templates cannot publish before Phase 41.
- PNG/JPEG media become active before WebP and SVG, but the media entity is designed for all required formats from Phase 42.
- WebP remains capability-gated until Phase 46 provides PDF evidence.
- SVG remains inactive until both sanitization and rendering phases 51–52 pass.
- Freeform nodes cannot publish before Phase 59.
- Collection nodes cannot publish before their Layer 9 server resolvers/renderers exist.
- Spreadsheet templates cannot publish before Phase 71.
- Batch UI does not perform synchronous bulk work; it waits for the Phase 73 job pipeline.
- Manual save is authoritative initially; later autosave must reuse revision control rather than replace it.
- Public verification is not required initially; QR values may reference an already existing verification URL.

Permanent version 1.0 exclusions are only those explicitly stated as PRD non-goals or future enhancements and not promoted by a later product-owner instruction.

## 9. Required technical references

- PRD: `document-builder-espocrm-prd.md`.
- EspoCRM 10.0.0 source: <https://github.com/espocrm/espocrm/tree/10.0.0>
- Extension packages: <https://docs.espocrm.com/development/extension-packages/>
- Modules: <https://docs.espocrm.com/development/modules/>
- Metadata: <https://docs.espocrm.com/development/metadata/>
- API actions: <https://docs.espocrm.com/development/api-action/>
- ACL: <https://docs.espocrm.com/development/acl/>
- Attachments: <https://docs.espocrm.com/development/attachments/>
- Jobs: <https://docs.espocrm.com/development/jobs/>
- View setup handlers: <https://docs.espocrm.com/development/frontend/view-setup-handlers/>
- Dompdf limitations: <https://github.com/dompdf/dompdf>

The installed 10.0.0 source on the supplied test instance takes precedence over general documentation when exact signatures or runtime behavior differ.
