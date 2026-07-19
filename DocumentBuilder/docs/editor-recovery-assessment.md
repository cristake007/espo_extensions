# Document Builder editor recovery assessment

## Status and boundary

This specification defines the editor recovery gate at the Layer 5, Phase 37 entry boundary. The latest implemented repository phase is Phase 36. Phase 37 and every later phase remain paused until this recovery plan is approved and completed.

Recovery is limited to the existing flow editor and the schema/rendering additions strictly required by the interaction model below. It does not authorize Layer 6 grid layout, media-manager work, freeform layout, or another frontend framework.

## Final interaction model

### Document-first canvas

The canvas always represents the document itself, not a technical tree:

- Sections, containers, and their descendants render as actual nested visual containment.
- A child is a DOM descendant of its parent canvas element and visibly responds to the parent's padding, margin, width, background, border, alignment, and spacing.
- Technical arrows, depth badges, child counts, hierarchy cards, and persistent structural labels are absent from the canvas.
- Selection uses a subtle outline that does not alter layout geometry.
- Empty compatible parents may show a compact editing placeholder in edit mode only.
- Approximate browser pagination may remain an editing aid, but it must not turn elements into structural cards or displace the actual document content.

Drag-and-drop supports insertion before, insertion after, insertion inside a compatible section or container, sibling reordering, and moves between compatible parents. A valid destination is clear during an active drag. Destination indicators are absent at rest. Escape, drag cancellation, invalid targets, and drops outside a valid target do not execute a command or change the layout.

### Hover controls

Hovering or keyboard-focusing an element exposes a compact overlay toolbar with:

- Edit;
- Duplicate;
- Remove.

The toolbar is positioned outside normal document flow and chooses an adjacent placement that avoids covering meaningful content where space permits. Edit selects the element and activates the Properties tab. Duplicate inserts a deep copy immediately after the source under the same parent. Remove confirms when the element owns children or otherwise risks meaningful content loss. Duplicate and Remove execute through editor commands and participate in undo/redo. Section and container toolbars may additionally expose a drag handle or Add Inside action when required for discoverability and accessibility.

### Left sidebar: Variables only

The left sidebar contains:

- the selected data source;
- variable search;
- system variables;
- direct entity fields;
- compact expandable single-record relationships;
- a visually distinct representation of scalar fields and collection relationships;
- translated labels, unobtrusive internal names, type indicators, and full-path tooltips.

Rows must remain readable within the sidebar. The primary label gets width priority; secondary technical names truncate; badges remain compact; long paths are available through a tooltip and accessible label. Collection relationships remain visibly non-scalar and are not inserted into scalar-only locations.

A scalar variable can be dragged to a compatible canvas destination to create a standalone variable element. When a heading or paragraph editor has an active or saved caret/selection, clicking a scalar variable inserts an inline token at that range. Variable presentation settings belong in Properties for the selected standalone variable or inline token; they do not consume permanent space in the Variables sidebar.

### Right sidebar: exactly two primary tabs

The right sidebar has exactly two primary tabs:

1. **Elements** — Section, Container, Heading, Paragraph, Static text, Variable, Divider, Spacer, Page break, and later element types only when those types are actually implemented. Items support click insertion and drag insertion.
2. **Properties** — advanced settings for the current selection, including the applicable content, typography, alignment, box model, dimensions, background, border, opacity, page-break behavior, conditions, variable formatting, and element-specific controls.

Edit from the hover toolbar selects the element and switches to Properties. With no element selected, Properties shows document/page settings or a clear empty state. Tabs are interface state, not canonical layout state and not undoable commands.

### Direct WYSIWYG editing

Heading and paragraph content is edited in place on the canvas. Editing is caret-aware and selection-aware and supports:

- bold, italic, underline, and text color;
- alignment;
- bulleted and numbered lists;
- inline variable tokens inserted at the current range.

The editor continues to store structured, sanitized rich-text data. Browser DOM is an editing projection, not the persisted format. Paste and DOM-to-model conversion accept only the supported structure and plain text; raw HTML and CSS are never exposed or stored. A selection/caret bookmark is kept outside canonical layout state so sidebar interaction can insert at the last valid range without polluting undo history.

### In-canvas Preview and PDF Proof

Preview remains in the same canvas. It retains the page geometry, zoom, canvas scroll position, and resolved sample or record values while hiding editing-only UI:

- hover toolbars;
- selection outlines;
- drag handles;
- drop indicators;
- empty-container placeholders;
- both sidebars when the available viewport benefits from it.

Preview provides a clear **Back to Edit** action and renders only the clean document. The existing ACL-safe JSON preview endpoint supplies resolved sample or record values. The first recovery release retains the existing saved-revision requirement and must prompt the user to save rather than showing stale data.

The server-generated PDF preview remains separate and is renamed **PDF Proof**. It continues to use the existing PDF endpoint and renderer and is not substituted for in-canvas Preview.

## Recovery assessment

| Area | Current evidence | Recovery conclusion |
| --- | --- | --- |
| State and history | `EditorState` owns canonical layout, single selection, a 100-state history, dirty baseline, and command execution. | Retain it as the only layout mutation owner. UI tab, hover, drag, caret, scroll, zoom, and preview-mode state remain transient. |
| Canonical hierarchy | `NodeTree` and `FlowStructure` already preserve nested `children`, validate compatible parents, and support moves between parents. | Reuse the hierarchy and parent rules; the defect is primarily the flattened canvas projection. |
| Canvas renderer | `BrowserRenderer` produces a flattened `rows` array with depth, badges, child counts, and structural metadata; the shell renders every row as a sibling card. | Replace the flattened visual projection with a recursive document projection whose DOM nesting matches canonical nesting. Remove technical hierarchy presentation. |
| Box model | Typed style and flow values already include margin, padding, width, height, background, border, opacity, and alignment. | Apply the existing resolved style to real nested canvas elements so parent layout visibly governs descendants. Do not introduce raw CSS fields. |
| Drag-and-drop | Native drag events and `FlowStructure.assertTarget` already protect compatible moves and self-descendant drops. Commands execute only on drop. Indicators are permanently present as empty drop elements. | Retain target validation and move/add commands. Add geometric before/after/inside target resolution, drag-only indicators, explicit cancellation cleanup, variable payloads, and accessible non-pointer alternatives. |
| Duplicate/remove | `DuplicateNodeCommand` exists but is not wired into the shell; flow removal and complex-node confirmation already exist. | Wire recovery actions through validated commands. Ensure duplication respects flow limits and selects the copy. Keep confirmation for content-owning removal. |
| Variables | ACL-safe source catalogue, metadata tree, stable identities, types, relationship classification, search, and JSON preview data already exist. Elements and variables currently share the left sidebar. Variable clicks append to the end of selected rich text. | Dedicate the left sidebar to variables, move Elements right, tighten row layout/tooltips, distinguish collections, add variable drag payloads, and insert inline variables at a saved range. |
| Standalone variable | The schema supports inline variable tokens but has no standalone flow `variable` node. | Add one scalar standalone variable node using the existing identity and presentation contracts. Extend validation and both browser/server renderers without changing resolver, ACL, or formatter ownership. |
| Rich text | Rich text is a sanitized sequence of text, break, and variable tokens. Marks are applied to all text in a node; content is edited in Properties rather than in the canvas. | Preserve the structured representation and sanitization boundary. Add range-aware transformations and an additive structured list form; build a WYSIWYG adapter that maps only allowlisted DOM back to that model. |
| Preview | `PreviewApi.load` already returns ACL-safe resolved values, while `loadPdf` opens the authoritative server PDF. The shell currently invokes only the PDF path for its preview actions. | Use `load` for same-canvas Preview and retain `loadPdf` as PDF Proof. Preserve saved-revision checks, resolved-data distinctions, zoom, and scroll. |
| Shell structure | One Espo view and template currently own most behavior; the shell view is large and mixes orchestration, canvas, sidebars, drag logic, and content editing. | Keep the Espo view/AMD stack, but extract focused AMD collaborators or child views where ownership becomes clear. Do not add a framework or client dependency. |
| Backend services | ACL, metadata, variables, preview data, PDF rendering, snapshots, attachments, and generation history are already separate services. | Preserve their interfaces except for the narrow additive layout/tree/rendering support required by standalone variables and lists. |

## Design ownership

- `EditorState` and command objects exclusively own canonical layout changes and undo/redo.
- `FlowStructure` exclusively owns flow compatibility, nesting, count limits, and legal insertion targets.
- The canvas renderer owns the safe nested browser projection; it does not mutate layout.
- The drag controller owns transient drag payloads, candidate destinations, indicators, and cancellation; it executes one command only after a valid drop.
- The WYSIWYG adapter owns DOM range bookmarks and conversion between allowlisted editable DOM and structured content; the rich-text model owns canonical transforms and normalization.
- The variable browser owns searchable display rows and safe identities; it never resolves source records.
- Existing backend preview services own resolved data and ACL distinctions.
- Existing PDF services remain the authority for PDF Proof.
- Sidebar tab, hover, focus, preview, zoom, and scroll state are view state and never enter the layout schema.

## Affected-file map

Paths are relative to `DocumentBuilder/`. “Modify” denotes a confirmed recovery touchpoint. “Add if extraction remains cohesive” denotes the planned boundary; exact filenames may be adjusted during implementation without changing ownership.

### Client shell and interaction

| File | Action | Responsibility |
| --- | --- | --- |
| `files/client/custom/modules/document-builder/src/views/editor/shell.js` | Modify | Reduce to editor orchestration, sidebar mode, command dispatch, preview/PDF Proof actions, selection hand-off, and collaborator lifecycle. |
| `files/client/custom/modules/document-builder/res/templates/editor/shell.tpl` | Modify | Variables-only left sidebar, canvas host, right Elements/Properties tabs, clean-preview shell, and renamed PDF Proof surface. |
| `files/client/custom/modules/document-builder/res/css/editor.css` | Modify | Three-panel sizing, compact variable rows, tabs, nested document containment, subtle selection, overlay controls, drag-only indicators, WYSIWYG states, and clean preview. |
| `files/client/custom/modules/document-builder/src/editor/renderer/browser-renderer.js` | Modify | Return a nested, presentation-safe document projection and preserve pure condition/preview/style mapping without technical hierarchy metadata. |
| `files/client/custom/modules/document-builder/src/editor/flow/flow-structure.js` | Modify | Register standalone variable creation and validate all recovery insertion/duplication targets within existing limits. |
| `files/client/custom/modules/document-builder/src/editor/commands/duplicate-node.js` | Modify | Enforce flow compatibility/limits for deep adjacent copies and expose the new root ID for selection. |
| `files/client/custom/modules/document-builder/src/editor/commands/add-flow-node.js` | Modify | Accept the standalone variable initialization data while retaining one command per addition. |
| `files/client/custom/modules/document-builder/src/editor/content/rich-text.js` | Modify | Add range-aware structured transforms, inline insertion at a model range, list normalization, and safe rendering. |
| `files/client/custom/modules/document-builder/src/editor/variables/metadata-browser.js` | Modify | Produce compact display metadata, full paths/tooltips, scalar/collection distinctions, and draggable scalar identities. |
| `files/client/custom/modules/document-builder/src/services/preview-api.js` | Modify only if naming improves clarity | Keep JSON Preview and PDF Proof calls distinct; do not change their security boundary. |
| `files/client/custom/modules/document-builder/src/editor/canvas/document-canvas.js` | Add if extraction remains cohesive | Render and update recursive canvas DOM, hover controls, selection, and edit/preview decoration without owning layout. |
| `files/client/custom/modules/document-builder/src/editor/drag-drop/drag-controller.js` | Add if extraction remains cohesive | Resolve before/after/inside destinations, variable/element payloads, indicator lifecycle, and cancellation. |
| `files/client/custom/modules/document-builder/src/editor/content/wysiwyg-adapter.js` | Add if extraction remains cohesive | Manage `contenteditable`, DOM selections/bookmarks, allowlisted paste/input normalization, and model-range mapping. |

### Canonical schema and server rendering

| File or area | Action | Responsibility |
| --- | --- | --- |
| `files/custom/Espo/Modules/DocumentBuilder/Resources/jsonSchema/document-builder-layout-v1.json` | Modify additively | Define standalone scalar variable nodes and structured list content without allowing raw HTML/CSS. Preserve existing node/content forms. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/Node/NodeRegistry.php` | Modify | Register the standalone variable as a flow element. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/LayoutValidator.php` | Modify | Validate standalone identity/presentation and bounded structured list content. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Layout/RichTextSanitizer.php` | Modify | Normalize the additive list representation and preserve allowlisted inline tokens/marks. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering/DocumentTreeBuilder.php` and resolved tree value types | Modify | Resolve standalone variables and variable tokens nested in list items without changing ACL/resolver ownership. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering/Html/ElementRendererRegistry.php` | Modify | Map standalone variables to conservative generated markup. |
| `files/custom/Espo/Modules/DocumentBuilder/Tools/DocumentBuilder/Rendering/HtmlRenderer.php` and typed renderer helpers | Modify | Emit escaped standalone values and semantic ordered/unordered lists using allowlisted generated CSS. |

### Metadata, translations, and focused verification

| File or area | Action | Responsibility |
| --- | --- | --- |
| `files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/en_US/DocumentBuilderTemplate.json` | Modify | English labels/actions/messages for tabs, hover actions, preview, PDF Proof, drop states, WYSIWYG, and variable types. |
| `files/custom/Espo/Modules/DocumentBuilder/Resources/i18n/ro_RO/DocumentBuilderTemplate.json` | Modify | Romanian equivalents for every new visible string. |
| `tests/editor-recovery/` | Add | Focused client/model/server contract tests for nested projection, drop targets/cancel, command history, standalone variables, WYSIWYG ranges/lists, preview modes, and shell structure. |
| Existing `tests/phase-16/`, `phase-19/`, `phase-20/`, `phase-22/`, `phase-23/`, `phase-25/`–`phase-27/`, `phase-30/`, `phase-32/`–`phase-35/` | Modify only where an obsolete UI assertion conflicts | Preserve state, flow, sanitization, style, variable, preview, resolved-tree, HTML, and PDF contracts; replace assertions that intentionally require structural cards/badges. |

### Explicitly preserved and out of scope

No recovery change is planned for ACL policy, entity/field metadata access, source record resolution, variable path compilation, formatting policy, preview authorization, PDF adapter isolation, attachments, snapshots, generation-history entities, or generation persistence. A change in one of these areas requires separate evidence and approval. No dependency, package-manager, framework, database migration, container, or infrastructure change is planned.

## Proposed implementation sequence

Implementation starts only after approval. Each step is separately reviewable and keeps the editor loadable.

### Recovery 1 — Lock contracts and additive schema

- Add focused recovery fixtures and acceptance assertions.
- Add the standalone scalar variable node and bounded structured list representation to the canonical schema, client precheck, PHP validator/sanitizer, resolved tree, and conservative HTML renderer.
- Preserve existing layouts without migration; old text/break/variable sequences remain valid.
- Confirm no change to ACL, resolver, formatter, preview, or PDF service ownership.

### Recovery 2 — Rebuild the canvas as a nested document projection

- Change the browser renderer from flattened rows to a recursive projection.
- Render children physically inside section/container DOM.
- Apply current typed box/style values so containment is visible and geometry remains document-first.
- Remove structural cards, arrows, levels, counts, and permanent drop zones.
- Add subtle non-layout-shifting selection and edit-only empty-parent placeholders.

### Recovery 3 — Complete drag/drop and hover commands

- Add active-drag-only before/after/inside indicators with compatibility feedback.
- Support palette additions, variable additions, sibling reorder, cross-parent moves, invalid-drop rejection, Escape cancellation, and drop-outside cancellation.
- Wire Edit, Duplicate, and Remove overlays; keep overlays outside document flow.
- Route Duplicate/Remove through validated commands and confirm complex removals.

### Recovery 4 — Recompose the sidebars

- Make the left sidebar Variables-only and compact its source/search/system/direct/relationship hierarchy.
- Add full-path tooltips, translated labels, secondary internal names, type indicators, and explicit collection treatment.
- Make the right sidebar exactly Elements and Properties.
- Move element insertion to Elements and current element/document/variable presentation controls to Properties.
- Switch to Properties on selection through Edit; show page/document settings or an empty state when selection is clear.

### Recovery 5 — Add direct structured WYSIWYG editing

- Enable direct heading and paragraph editing with a focused toolbar.
- Map caret/selections to structured model ranges for marks, color, alignment, lists, and inline variables.
- Preserve the last valid caret range while a variable is chosen from the left sidebar.
- Sanitize paste/input through the allowlist and commit canonical changes through commands with coherent undo/redo steps.
- Keep raw HTML/CSS inaccessible and preserve inline variable identity/presentation validation.

### Recovery 6 — Separate clean Preview from PDF Proof and stabilize

- Use the existing JSON preview API to resolve sample or selected-record values into the same canvas.
- Toggle editing decoration and sidebars without changing document geometry; capture and restore scroll/focus while retaining zoom.
- Provide Back to Edit and rename the existing server PDF action/surface to PDF Proof.
- Complete focused keyboard, focus, accessible-name, overlay placement, preview-state, teardown, and regression checks.
- Record broader EspoCRM runtime/browser/PDF comparison commands for manual execution; do not begin Phase 37 or Layer 6 until recovery is accepted.

## Acceptance criteria

Recovery is complete only when all of the following are demonstrated:

1. Nested canvas DOM and visible box-model behavior match the canonical parent/child layout.
2. No technical hierarchy cards, arrows, levels, or child counts remain on the canvas.
3. Every required add/move/reorder/drop/cancel path produces exactly one valid command or no state change.
4. Hover/focus controls do not reserve document space; Edit, Duplicate, and Remove have the specified state/history behavior.
5. The left sidebar is Variables-only and remains usable with long translated labels and technical paths.
6. The right sidebar has exactly Elements and Properties, with correct automatic tab switching and no-selection behavior.
7. Heading and paragraph editing is caret/selection aware; supported marks, colors, alignment, lists, paste, and inline-variable insertion round-trip through the structured sanitizer.
8. Standalone scalar variables render consistently in the browser, resolved tree, generated HTML, Preview, and PDF Proof.
9. Preview stays in the canvas and removes editing decoration while preserving geometry, zoom, scroll, and resolved data; Back to Edit restores editing context.
10. PDF Proof continues to use the existing server-generated PDF path.
11. Draft save, revision conflicts, dirty guards, undo/redo, validation, ACL-safe metadata, preview data, conditions, and the existing PDF pipeline have no known recovery regressions.
12. No Layer 6 capability or dependency/framework/infrastructure change is introduced.

## Approval decision

Approval authorizes Recoveries 1–6 in order. It does not authorize Phase 37, Phase 38, Layer 6, unrelated refactoring, dependency additions, or production changes.
