# Document Builder for EspoCRM

## Product Requirements Document

| Attribute | Value |
|---|---|
| Product name | Document Builder |
| Product type | Installable EspoCRM extension |
| Target platform | EspoCRM 10.3.x |
| Document status | Detailed implementation-ready draft |
| Primary audience | Product owner, Codex implementation agent, reviewer |
| Version | 1.0 |
| Date | 2026-07-19 |
| Source concept | Generalization of `apps/diplome` from `cristake007/platforma` |
| Primary objective | Let non-technical users visually design and generate professional PDFs from any accessible EspoCRM entity or CSV/XLSX data without HTML or CSS |

---

## 1. Executive summary

Document Builder is a no-code visual PDF design and generation extension for EspoCRM.

EspoCRM already includes PDF templates, but its most precise workflows require users to understand HTML, CSS, template expressions, data paths, loops, and page-breaking behavior. Document Builder will provide a visual editor that exposes the same business data through understandable controls:

- Select an EspoCRM entity or upload a CSV/XLSX file.
- Browse available fields and relationships as named variables.
- Drag visual elements into a document.
- Arrange content using flow, grid, or freeform sections.
- Configure typography, spacing, borders, backgrounds, icons, lists, images, QR codes, tables, and page behavior.
- Preview the document with sample or real records.
- Generate a PDF for one record, multiple records, or spreadsheet rows.
- Save generated files and an immutable generation snapshot in EspoCRM.

The editor must support two apparently conflicting needs:

1. **Flowing, multi-page documents** such as offers, reports, histories, lists, and summaries.
2. **Pixel-perfect documents** such as diplomas, certificates, badges, forms, and branded one-page layouts.

The product will solve this through a hybrid section model:

- **Flow sections** for natural document content and automatic pagination.
- **Grid sections** for visually structured rows and columns with snap-to-grid behavior.
- **Freeform sections** for precise placement within a fixed-size region or page.

The document itself remains a normal ordered sequence of sections. Absolute positioning is allowed only inside a freeform section. This avoids forcing offers and reports into a fragile coordinate system while preserving exact control where it is genuinely required.

---

## 2. Product vision

A business user who understands the desired document but does not know HTML or CSS should be able to build it independently.

The intended experience is closer to Elementor, Canva, Word, and a CRM field browser than to a source-code template editor.

A user should be able to say:

- “Create a diploma for this Contact.”
- “Create an offer for this Account using its related Offer Items.”
- “Create a course history for this participant.”
- “Create one certificate for every row in this XLSX file.”
- “Create a branded audit report with a QR verification URL.”
- “Create a two-page summary using fields from a custom entity made in Entity Manager.”

The extension should continue to work when administrators create new custom entities or fields after installation. It must derive its variable catalogue from EspoCRM metadata rather than maintain a hardcoded list.

---

## 3. Problem statement

### 3.1 Current problem

EspoCRM PDF templates are powerful, but non-technical users face several barriers:

- They must understand template variables and relationship paths.
- Precise layouts require HTML and CSS.
- Repeating related records require template-loop syntax.
- Page breaking can be difficult to predict.
- Users cannot easily discover available entity fields.
- Visual iteration is slow because the editor and generated PDF may differ.
- Building diplomas or certificates with precise brand positioning is inconvenient.
- Creating templates for custom entities requires technical knowledge.

### 3.2 Business impact

These limitations create dependency on developers for ordinary business-document changes:

- Logo or address movement.
- Font and spacing changes.
- New fields in offers.
- Additional related-record columns.
- Diploma layout adjustments.
- QR code placement.
- Different templates by course, department, customer, or document category.

Document Builder should reduce this dependency and make document maintenance an administrative or operational capability.

### 3.3 Product opportunity

EspoCRM already provides:

- Entity metadata.
- Custom entities and fields through Entity Manager.
- Record-level ACL.
- File attachments and storage abstraction.
- Frontend views and custom routes.
- Job queues.
- A built-in PDF engine.

Document Builder should combine these platform capabilities with a visual layout model and a safe data resolver.

---

## 4. Product principles

### 4.1 No code required

Normal users must never need to write:

- HTML.
- CSS.
- JavaScript.
- PHP.
- Handlebars expressions.
- SQL.
- EspoCRM API queries.

### 4.2 Metadata-driven

Entity types, fields, labels, relationships, and supported formatting options must be discovered from EspoCRM metadata.

### 4.3 Safe by default

Users may only expose data they are allowed to read. The editor must not list forbidden fields, and generation must re-check access server-side.

### 4.4 Preview must be trustworthy

The browser preview and generated PDF must use the same canonical layout schema and equivalent formatting rules.

### 4.5 Flow first, precision when required

Normal documents should paginate naturally. Pixel-perfect positioning should be isolated inside explicit freeform sections.

### 4.6 Preserve history

Generated documents must remain understandable after source records or templates change. Generation records should preserve template and data snapshots.

### 4.7 Conservative rendering

The PDF renderer must target HTML/CSS constructs reliably supported by the selected PDF engine. The editor may present modern controls, but the server renderer should translate them into deterministic output.

### 4.8 Extensible block system

New elements should be addable later without redesigning the entire editor or layout schema.

---

## 5. Goals

### 5.1 Primary goals

1. Provide a visual drag-and-drop PDF template editor inside EspoCRM.
2. Support any eligible standard or custom EspoCRM entity.
3. Support CSV and XLSX column-based variables.
4. Support single-value fields, related fields, and repeating related-record collections.
5. Generate one-page and multi-page PDF documents.
6. Support flow, grid, and freeform layout modes.
7. Support professional visual elements including images, backgrounds, icons, icon lists, lists, paragraphs, QR codes, and tables.
8. Integrate generation actions into compatible EspoCRM record and list views.
9. Respect EspoCRM scope, record, field, and link access controls.
10. Save generated PDFs through EspoCRM attachments and configured file storage.
11. Preserve immutable generation history and snapshots.
12. Be installable and uninstallable as a normal EspoCRM extension.

### 5.2 Secondary goals

- Reuse proven concepts from the Django `apps/diplome` editor where appropriate.
- Allow templates to be duplicated and versioned.
- Support batch generation in EspoCRM jobs.
- Provide Romanian and English translations.
- Make templates shareable by ownership and teams.
- Provide validation that prevents malformed or unsafe layouts.

---

## 6. Non-goals

The following are explicitly outside the initial implementation unless promoted in a later phase:

1. Replacing EspoCRM’s native PDF Template feature.
2. Full desktop-publishing parity with Adobe InDesign.
3. Arbitrary user-provided HTML, CSS, JavaScript, PHP, or template code.
4. Arbitrary SQL queries.
5. Cross-entity joins unrelated to configured EspoCRM relationships.
6. Editing existing PDF files.
7. Importing Word, PowerPoint, Canva, or Elementor layouts.
8. Digital signatures with legal trust services.
9. Collaborative real-time multi-user editing.
10. External SaaS rendering dependencies.
11. Full responsive web-page design; the target is paged PDF output.
12. Automatic OCR.
13. Automated translation of document content.
14. Composite templates with multiple unrelated primary data sources in the first release.
15. Unrestricted remote image loading.
16. Executing formulas or expressions as arbitrary code.

---

## 7. Target users and roles

### 7.1 Administrator

Responsibilities:

- Install and configure the extension.
- Grant roles access to templates and generated documents.
- Configure allowed entity types.
- Configure file, image, page, and batch limits.
- Configure whether SVG, barcode, remote resources, or public verification links are permitted.
- View failed jobs and system diagnostics.

### 7.2 Template designer

Responsibilities:

- Create templates.
- Choose a data source.
- Design layouts.
- Select fields and relationships.
- Configure formatting and conditions.
- Preview templates.
- Publish template versions.

The designer may be a non-technical operational user.

### 7.3 Document generator

Responsibilities:

- Generate documents from records or spreadsheets.
- Preview resolved output.
- Download or attach generated PDFs.
- Generate a batch if permitted.

This user may not have permission to edit templates.

### 7.4 Reviewer or manager

Responsibilities:

- View generated-document history.
- Review source snapshots.
- Download generated files.
- Inspect generation errors.

### 7.5 Portal user

Portal support is not required in the first release. The data model must not make future portal support impossible.

---

## 8. Representative use cases

### 8.1 Diploma for a Contact

Primary entity: `Contact`

Example variables:

- Contact name.
- Birth date.
- Certificate number.
- Course title through a relationship.
- Course dates.
- Trainer name.
- Verification URL encoded as a QR code.

Layout:

- A4 landscape.
- Full-page background.
- Freeform section.
- Pixel-perfect name and signature placement.

### 8.2 Offer for an Account

Primary entity: custom `Offer` or standard/custom business entity.

Example variables:

- Offer number.
- Offer date.
- Account name and billing address.
- Assigned user.
- Related Offer Items.
- Currency totals.
- Terms and conditions.

Layout:

- Flow sections.
- Two-column header grid.
- Dynamic related-record table.
- Automatic page breaks.
- Header/footer and page numbering.

### 8.3 History report

Primary entity: `Contact`, `Account`, or custom participant entity.

Example variables:

- Identity fields.
- Related courses or certifications.
- Status and dates.
- Notes.
- Totals or counts.

Layout:

- Flow sections.
- Repeating table or repeating cards.
- Optional summary section.

### 8.4 Spreadsheet batch certificates

Primary source: XLSX or CSV.

Example columns:

- Full Name.
- Date of Birth.
- Course.
- Certificate Number.
- Verification URL.

Behavior:

- Map columns.
- Preview one row.
- Generate one PDF per valid row.
- Download a ZIP or store all files in a generation batch.

### 8.5 Custom entity report

An administrator creates a new entity with Entity Manager after Document Builder is installed. The entity and readable fields should appear automatically without extension changes.

---

## 9. Product scope by release

### 9.1 Minimum viable product

The MVP proves the complete vertical workflow:

- Installable extension.
- Template entity.
- EspoCRM entity as a data source.
- Flow and simple grid sections.
- Text, paragraph, variable, image, divider, spacer, and page break elements.
- Single-level related fields.
- Browser preview.
- Server PDF generation.
- Single-record generation.
- Generated-document history and attachment.
- ACL enforcement.
- Romanian and English labels.

### 9.2 Version 1.0

Version 1.0 adds the complete intended product:

- Freeform sections.
- Icons and icon lists.
- QR codes.
- Static and dynamic tables.
- Repeating related-record sections.
- CSV/XLSX data sources.
- Batch generation and ZIP download.
- Header/footer/page numbering.
- Template versioning and publication workflow.
- List-view mass action.
- Conditions and fallback values.
- More formatting controls.
- Operational limits and diagnostics.

### 9.3 Future enhancements

Potential later features:

- Template folders and shared style presets.
- Barcode types beyond QR.
- Reusable components.
- Charts.
- Conditional page sections.
- Formula builder.
- Public verification endpoint.
- Email generated document.
- Merge multiple generated documents.
- Portal support.
- External data connectors.
- DOCX output.
- PDF/A configuration.
- Digital signature integrations.

---

## 10. Information architecture

### 10.1 Main navigation group

A top-level navigation group named **Document Builder** should contain:

- Templates.
- Generated Documents.
- Generation Batches.
- Spreadsheet Imports.
- Media Library, if the extension uses a dedicated media entity.
- Settings, visible only to administrators.

### 10.2 Template record actions

A template record should expose:

- Open Designer.
- Preview.
- Duplicate.
- Publish.
- Create Draft from Published Version.
- Archive.
- Generate Test Document.
- View Versions.
- View Generated Documents.

### 10.3 Source record actions

For an entity that has at least one published compatible template, the record detail view should expose:

- Generate Document.
- View Generated Documents.

The action can be provided through a global view setup handler or compatible record-view action integration. It must not require manually adding code to every entity.

### 10.4 List view actions

For entities with compatible templates:

- Generate Documents for Selected Records.
- Generate Documents for Current Filter, future or administrator-controlled.
- View Latest Generation Batch.

Mass generation must require explicit confirmation and display an estimated record count.

---

## 11. Template lifecycle

### 11.1 Statuses

A template should have the following statuses:

- Draft.
- Published.
- Archived.

### 11.2 Draft behavior

- Drafts can be edited.
- Drafts cannot be used by ordinary document generators.
- Designers may create test previews from drafts.
- Unsaved browser changes must not overwrite the server version.
- Leaving with unsaved changes must trigger a warning.

### 11.3 Publication behavior

Publishing must:

1. Validate the complete template.
2. Validate the data-source definition.
3. Validate referenced media.
4. Validate all variables.
5. Create an immutable template-version snapshot.
6. Mark the version as active.
7. Make it available for generation.

Published versions must not mutate. Editing a published template creates or updates a draft.

### 11.4 Archiving

Archiving must prevent new generation while preserving:

- Existing template versions.
- Generated documents.
- Generation history.
- Source snapshots.

### 11.5 Duplication

Duplicating a template should copy:

- Page settings.
- Data-source definition.
- Layout JSON.
- Media references.
- Styles.
- Conditions.

It should not copy generated documents or batch history.

---

## 12. Data-source model

Every template has one primary data-source type.

### 12.1 Supported primary source types

- EspoCRM Entity.
- CSV.
- XLSX.

### 12.2 Entity source

Required configuration:

- Entity type.
- Optional default preview record.
- Maximum relationship depth.
- Allowed relationships.
- Default locale and timezone.
- Optional record filter for template compatibility.

Example:

```json
{
  "type": "entity",
  "entityType": "Contact",
  "relationshipDepth": 2
}
```

### 12.3 Spreadsheet source

Required configuration:

- File type.
- Header row.
- Worksheet for XLSX.
- Column catalogue.
- Column type and formatting metadata.
- Optional source filename.
- Import expiration and retention.

Example:

```json
{
  "type": "spreadsheet",
  "format": "xlsx",
  "worksheet": "Participants",
  "columns": [
    {
      "key": "full_name",
      "label": "Full Name",
      "sourceIndex": 0,
      "type": "text"
    }
  ]
}
```

### 12.4 Source switching

Changing the source of a populated template is dangerous because existing variables may become invalid.

Rules:

- Source type may be changed only while the template is a draft.
- The system must show all variables that would become unresolved.
- The user must explicitly confirm.
- Invalid variables remain visible with an error badge until replaced or removed.
- Publication is blocked while unresolved variables exist.

### 12.5 Composite data sources

Combining unrelated entity and spreadsheet sources in the same template is not part of version 1.0.

Global system values such as generation date, current user, page number, and organization settings are available regardless of the primary source.

---

## 13. Entity discovery and field catalogue

### 13.1 Entity discovery

The extension must read EspoCRM metadata to discover eligible entities.

An entity is eligible when:

- It is an entity type with readable records.
- It is not explicitly disabled in Document Builder settings.
- It is not an internal/system-only scope.
- The current user has scope-level read access.
- It is not marked as unsuitable by extension rules.

### 13.2 Field discovery

For the selected entity, the field browser must display:

- Translated label.
- Internal field name.
- Field type.
- Required/read-only status where relevant.
- Relationship group.
- Search box.
- Whether the field is direct, related, calculated, or collection-based.

### 13.3 Field exclusion

The field browser must exclude:

- Passwords and secrets.
- Internal tokens.
- Forbidden fields.
- Fields the current user cannot read.
- Fields explicitly disabled by entity ACL.
- Non-displayable technical attributes.
- File content attributes not intended as values.
- Unsupported field types unless a safe textual fallback exists.

### 13.4 Relationship navigation

The field browser should display relationships as expandable nodes.

Example:

```text
Contact
├── Name
├── Email Address
├── Birth Date
├── Account
│   ├── Name
│   ├── Billing Address
│   └── VAT Number
└── Courses
    ├── Course Name
    ├── Start Date
    └── Result
```

Relationship rules:

- Single-record links can be traversed as variable paths.
- Multi-record links are exposed as repeating data sources.
- Relationship depth defaults to two levels.
- Administrators may lower or raise the limit within a safe maximum.
- Circular relationship paths must be detected and blocked.
- Every traversal is re-checked for link and field access at generation time.

### 13.5 Stable variable identity

A variable must store internal identifiers, not translated labels.

Example:

```json
{
  "source": "entity",
  "entityType": "Contact",
  "path": [
    {"kind": "field", "name": "account"},
    {"kind": "field", "name": "name"}
  ],
  "displayLabel": "Account › Name"
}
```

Translated labels may change without breaking the template.

---

## 14. Variable types

### 14.1 Direct field variable

Examples:

- `Contact.name`
- `Offer.total`
- `Course.startDate`

### 14.2 Related field variable

Examples:

- `Contact.account.name`
- `Offer.account.billingAddress`
- `Course.assignedUser.name`

### 14.3 System variable

Initial system variables:

- Current date.
- Current datetime.
- Current user name.
- Current user email.
- Organization name.
- Source record ID.
- Source record URL, when meaningful.
- Generated document ID.
- Page number.
- Page count, if supported by renderer.
- Template name.
- Template version.

### 14.4 Spreadsheet column variable

Examples:

- `row.full_name`
- `row.certificate_number`
- `row.course_date`

### 14.5 Repeating collection

Examples:

- `Offer.offerItems`
- `Contact.attendedCourses`
- `Account.contacts`

A collection cannot be inserted into a simple variable element. It must be used by a dynamic table, repeater, list, or collection-count element.

### 14.6 Derived values

Version 1.0 should support a limited safe set of transformations:

- Date formatting.
- Datetime formatting.
- Number formatting.
- Currency formatting.
- Boolean labels.
- Enum translation.
- Uppercase.
- Lowercase.
- Title case.
- Trim.
- Prefix.
- Suffix.
- Fallback value.
- Join multi-value fields.
- Count related records.

No arbitrary expression language is required initially.

---

## 15. Null and missing-value behavior

Each variable element must expose a missing-value policy:

- Show nothing.
- Show placeholder.
- Show configured fallback.
- Hide element.
- Hide parent row.
- Hide parent section.
- Raise generation warning.
- Block generation, only for fields marked required by the template designer.

The default should be **Show nothing** for normal variables.

A preview must visibly distinguish:

- Real value.
- Sample value.
- Missing value.
- Access-restricted value.
- Invalid variable path.

---

## 16. Editor layout model

### 16.1 Document structure

A document consists of:

1. Document settings.
2. Optional global header.
3. Ordered page-content sections.
4. Optional global footer.

```text
Document
├── Header
├── Section 1: Flow
├── Section 2: Grid
├── Section 3: Freeform
├── Page Break
├── Section 4: Flow
└── Footer
```

### 16.2 Flow section

Purpose:

- Paragraphs.
- Offers.
- Reports.
- Terms.
- Histories.
- Lists.
- Dynamic content.

Behavior:

- Children render from top to bottom.
- Content height is automatic.
- Content continues to the next page.
- Margins and padding are configurable.
- A section may start on a new page.
- A section may request “keep together” when it fits on one page.
- Child elements may control page-break behavior.

### 16.3 Grid section

Purpose:

- Structured headers.
- Two-column customer blocks.
- Signature rows.
- Logo and address arrangements.
- Balanced visual layouts.

Editor behavior:

- Configurable 1–24 visual columns.
- Default 12 columns.
- Elements snap to column and row guides.
- Users set column span.
- Users set row placement or natural row flow.
- Configurable gap.
- Nested grids are supported only to a limited depth.
- Elements expose width, alignment, padding, and margin.

PDF behavior:

The renderer must not depend on CSS Grid or Flexbox support. It should translate grid rows into conservative table-based or percentage-width block markup compatible with the configured PDF engine.

### 16.4 Freeform section

Purpose:

- Diplomas.
- Certificates.
- Badges.
- Forms.
- Branded one-page layouts.
- Exact signature or seal placement.

Behavior:

- Fixed width and height in millimetres.
- Optional “occupy full page.”
- Absolute positioning only inside the section.
- Drag, resize, rotate, align, distribute, layer, lock, and hide.
- Configurable snap grid.
- Configurable guides.
- Elements use x/y/width/height in millimetres.
- Overflow is clipped or reported based on section policy.
- Freeform section should not split across pages.
- If it does not fit remaining page space, it starts on a new page.
- Publication is blocked if the section is larger than the printable page area unless bleed mode is explicitly supported.

### 16.5 Hybrid documents

A template may combine modes.

Examples:

- Page 1: Freeform diploma.
- Page 2: Flow verification details.
- Offer header: Grid.
- Offer items: Flow dynamic table.
- Terms: Flow.
- Signature block: Grid.

### 16.6 Page breaks

Users can insert:

- Manual page break.
- Page break before section.
- Page break after section.
- Avoid break inside.
- Repeat table header after page break.
- Keep heading with next element.

### 16.7 Headers and footers

Headers and footers may contain:

- Text.
- Images.
- Variables.
- Page number.
- Page count.
- Divider.
- Grid layout.

Rules:

- Configurable first-page visibility.
- Configurable odd/even behavior is future scope.
- Headers and footers must reserve page space.
- Freeform full-page documents may disable them.

---

## 17. Page settings

Each template must support:

- A4 portrait.
- A4 landscape.
- Letter portrait.
- Letter landscape.
- Custom page size, administrator-controlled.
- Top, right, bottom, and left margins.
- Header height.
- Footer height.
- Background color.
- Default font.
- Default text color.
- Default line height.
- Locale.
- Timezone.
- PDF title pattern.
- Output filename pattern.

Suggested internal units:

- Millimetres for page geometry and freeform positions.
- Points or normalized numeric values for font sizes.
- Percentage widths or grid spans for flow/grid layout.
- Pixels only as an editor display conversion, never as the canonical page unit.

---

## 18. Element library

All elements must share a common contract:

- Stable ID.
- Type.
- Label.
- Visibility.
- Lock status.
- Style.
- Margin.
- Padding where applicable.
- Background.
- Border.
- Alignment.
- Conditional visibility.
- Page-break behavior.
- Accessibility or alternative-text attributes where relevant.
- Versioned schema.

### 18.1 Section

Properties:

- Layout mode: flow, grid, freeform.
- Background color/image.
- Width.
- Minimum height.
- Padding.
- Margin.
- Border.
- Page-break behavior.
- Keep together.
- Visibility condition.

### 18.2 Container

A nested grouping element for flow or grid content.

Properties:

- Width or grid span.
- Padding and margin.
- Background.
- Border.
- Child alignment.
- Keep together.
- Maximum nesting depth.

### 18.3 Heading

Properties:

- Static text or variable-composed text.
- Heading level.
- Font family.
- Font size.
- Weight.
- Style.
- Color.
- Alignment.
- Line height.
- Letter spacing.
- Text transform.
- Margin.
- Keep with next.

### 18.4 Paragraph

Properties:

- Rich text using a restricted toolbar.
- Inline variables.
- Bold, italic, underline.
- Lists within paragraph only if safely representable.
- Alignment.
- Line height.
- Paragraph spacing.
- No raw HTML editing.

The stored representation should be structured content or sanitized markup generated only by the editor.

### 18.5 Variable

Properties:

- Variable path.
- Format.
- Prefix.
- Suffix.
- Fallback.
- Null policy.
- Typography.
- Alignment.
- Optional label.
- Optional hide-when-empty.

### 18.6 Image

Sources:

- Document Builder media library.
- EspoCRM image/file field.
- Attachment selected from a permitted record.
- Generated QR or barcode is handled by its own element.

Properties:

- Fit: contain, cover, stretch.
- Width and height.
- Alignment.
- Opacity.
- Border.
- Radius.
- Alternative text.
- Maintain aspect ratio.
- Crop focal point, future.

Remote image URLs should be disabled by default.

### 18.7 Background image

Usable on:

- Page.
- Section.
- Freeform section.
- Container.

Properties:

- Contain, cover, stretch.
- Position.
- Opacity.
- Repeat disabled initially.
- Layering behind child content.

### 18.8 Icon

Sources:

- Bundled approved icon set.
- Sanitized SVG media, optional.
- Raster media.

Properties:

- Icon name.
- Size.
- Color.
- Background.
- Alignment.
- Optional text label.
- Rotation.
- Opacity.

### 18.9 Icon list

Inspired by Elementor’s Icon List.

Each item contains:

- Selected icon.
- Text, which may include inline variables.
- Optional link text representation; hyperlinks in PDFs may be supported later.
- Individual icon color.
- Optional divider.

List-level properties:

- Vertical or horizontal orientation.
- Icon size.
- Gap between icon and text.
- Gap between rows.
- Alignment.
- Typography.
- Divider style.
- Item padding.
- Repeatable item editor.

### 18.10 Bulleted or numbered list

Data modes:

- Static list.
- Manually composed variable list.
- Related-record collection list.

Properties:

- Bullet style.
- Numbering style.
- Marker color and size.
- Indentation.
- Row gap.
- Typography.
- Maximum items.
- Empty-list behavior.

### 18.11 QR code

Value sources:

- Static text.
- Single variable.
- Composed text using safe tokens.
- Record URL.
- Verification URL pattern.
- Generated document URL, when available.

Properties:

- Error-correction level.
- Size.
- Foreground color.
- Background color.
- Quiet zone.
- Optional label.
- Optional human-readable value.
- Validation of maximum encoded length.

The QR code must be generated locally without an external API.

### 18.12 Barcode

Optional for version 1.0 or a later minor release.

Potential types:

- Code 128.
- EAN-13.
- Data Matrix.

QR code is mandatory before other barcode types.

### 18.13 Divider

Properties:

- Horizontal or vertical in suitable containers.
- Thickness.
- Color.
- Style.
- Width.
- Alignment.
- Margin.

### 18.14 Spacer

Properties:

- Height in flow mode.
- Grid row gap contribution.
- Width/height in freeform mode.
- Maximum sensible limits.

### 18.15 Shape

Useful for visual decoration in freeform layouts.

Initial shapes:

- Rectangle.
- Circle.
- Line.

Properties:

- Fill.
- Border.
- Opacity.
- Radius.
- Rotation.
- Layer.

### 18.16 Static table

Properties:

- Columns.
- Rows.
- Header visibility.
- Column widths.
- Cell alignment.
- Borders.
- Header background.
- Zebra rows.
- Cell padding.
- Keep rows together where possible.
- Repeat header across pages.

Cell contents may include static text and simple variables.

### 18.17 Dynamic related-record table

Source:

- A multi-record relationship from the primary entity.
- Spreadsheet rows in batch-summary mode, future.

Configuration:

- Relationship path.
- Selected fields as columns.
- Column labels.
- Column widths.
- Field formatting.
- Sort order.
- Optional safe filter builder.
- Maximum rows.
- Empty-table behavior.
- Header repetition.
- Totals row.
- Count.
- Sum for numeric fields.
- Page-break behavior.

The table must retrieve only records accessible to the current user.

### 18.18 Repeater

A repeater renders a designed block once per related record.

Example:

```text
Course name
Course date
Result
Divider
```

Properties:

- Relationship source.
- Inner child elements.
- Sort.
- Filter.
- Maximum records.
- Empty behavior.
- Page-break between items.
- Keep one item together.
- Grid or flow inner layout.

Nested repeaters are excluded from version 1.0 unless proven safe.

### 18.19 Signature block

A convenience compound element.

Possible contents:

- Signatory label.
- Name variable.
- Role variable.
- Signature image.
- Date.
- Signature line.

It may be implemented as a preset grid/container rather than a unique renderer.

### 18.20 Page break

Properties:

- Always start next content on a new page.
- Optional label visible only in editor.

### 18.21 Page number

Properties:

- Current page.
- Current page of total.
- Prefix/suffix.
- Typography.
- Intended for header/footer.

### 18.22 Generated-at value

A system variable convenience element with locale-aware date/time formatting.

---

## 19. Styling system

### 19.1 Common box model

Flow and grid elements should support:

- Margin top/right/bottom/left.
- Padding top/right/bottom/left.
- Width.
- Minimum width.
- Maximum width.
- Height where meaningful.
- Minimum height.
- Background color.
- Background image.
- Border width.
- Border style.
- Border color.
- Border radius.
- Opacity.
- Horizontal alignment.
- Vertical alignment where supported.

### 19.2 Typography

Supported controls:

- Font family.
- Font size.
- Font weight.
- Italic.
- Underline.
- Color.
- Text alignment.
- Line height.
- Letter spacing.
- Uppercase/lowercase/title-case.
- Paragraph spacing.
- White-space behavior where safe.

### 19.3 Font management

Initial strategy:

- Use a controlled list of fonts known to render correctly.
- Bundle only legally redistributable fonts.
- Provide DejaVu Sans or another Unicode-capable default.
- Support Romanian diacritics.
- Allow administrators to register additional fonts in a later phase.
- Font availability must be identical between preview and PDF or visibly marked as approximate.

### 19.4 Style inheritance

Document defaults flow into sections and elements.

Priority:

1. Document defaults.
2. Section defaults.
3. Element styles.
4. Inline text styles where supported.

### 19.5 Presets

Future but recommended:

- Heading styles.
- Body style.
- Table style.
- Brand colors.
- Reusable spacing tokens.

Version 1.0 may include a small fixed set of presets without a full style-management UI.

---

## 20. Editor interaction requirements

### 20.1 Editor layout

Recommended interface:

- Left panel: elements and variables.
- Center: document canvas/preview.
- Right panel: selected element properties.
- Top toolbar: save, preview, undo, redo, zoom, page controls, publish.
- Optional bottom status bar: page, zoom, save status, validation errors.

### 20.2 Drag and drop

Users must be able to:

- Drag elements from the library into sections.
- Reorder elements in flow mode.
- Move and resize elements in freeform mode.
- Move elements between compatible containers.
- Receive clear drop indicators.
- Cancel a drag without modifying the document.

### 20.3 Selection

Support:

- Single selection everywhere.
- Multi-selection in freeform mode.
- Keyboard deletion with confirmation for complex elements.
- Layer panel for freeform mode.
- Breadcrumb for nested containers.
- Clear selected-state indication.

### 20.4 Undo and redo

Requirements:

- At least 100 local history states.
- Track structural and property changes.
- Do not include server preview requests in history.
- Reset saved baseline after successful save.
- Preserve undo history during the current editor session.

### 20.5 Autosave

Initial recommendation:

- Manual save is authoritative.
- Optional debounced draft autosave may be added after stability.
- If autosave is introduced, optimistic concurrency control is required.
- Never silently overwrite a newer server draft.

### 20.6 Keyboard support

Recommended shortcuts:

- Ctrl/Cmd + S: save.
- Ctrl/Cmd + Z: undo.
- Ctrl/Cmd + Shift + Z or Ctrl/Cmd + Y: redo.
- Delete: delete selected element.
- Arrow keys: nudge in freeform mode.
- Shift + arrows: larger nudge.
- Escape: clear selection or close dialog.
- Ctrl/Cmd + D: duplicate selected element.

### 20.7 Zoom

Support:

- Fit page width.
- Fit current page.
- 25–200% presets.
- Ctrl/Cmd + mouse wheel.
- Zoom must not alter stored dimensions.

### 20.8 Grid and guides

For freeform mode:

- Configurable minor grid.
- Configurable major grid.
- Snap toggle.
- Rulers in millimetres.
- Add draggable horizontal and vertical guides.
- Align and distribute controls.
- Lock/unlock elements.
- Layer ordering.

For grid sections:

- Visible column overlay.
- Configurable column count.
- Gap control.
- Snap to column boundaries.

### 20.9 Validation feedback

The editor must show:

- Element-level badges.
- Section-level warnings.
- Global validation summary.
- Click-to-focus navigation.
- Blocking errors versus non-blocking warnings.
- Unresolved variables.
- Unsupported relationship paths.
- Missing media.
- Overflow.
- Invalid page geometry.
- Missing required values.

---

## 21. Rich text rules

The paragraph editor must allow useful formatting without exposing unsafe markup.

Allowed:

- Paragraphs.
- Line breaks.
- Bold.
- Italic.
- Underline.
- Text color.
- Alignment.
- Inline variables.
- Simple links, future.
- Simple lists, if represented safely.

Disallowed:

- Script.
- Style tags.
- Iframes.
- Event attributes.
- Arbitrary HTML.
- Embedded remote resources.
- User-defined CSS classes.
- JavaScript URLs.
- Data URLs except internally generated and validated media.

Server-side sanitization remains authoritative.

---

## 22. Conditional visibility

Version 1.0 should provide a safe visual condition builder.

Supported conditions:

- Field is empty.
- Field is not empty.
- Field equals value.
- Field does not equal value.
- Boolean is true/false.
- Number greater/less than.
- Date before/after.
- Enum is one of selected values.
- Related-record count greater than zero.

Targets:

- Element.
- Container.
- Section.
- Table row template.

No arbitrary code or nested unbounded expression trees.

Example:

```json
{
  "all": [
    {
      "variable": "Offer.discount",
      "operator": "greaterThan",
      "value": 0
    }
  ]
}
```

---

## 23. Template schema

### 23.1 Canonical format

The canonical template representation should be versioned JSON.

Top-level example:

```json
{
  "schemaVersion": 1,
  "document": {
    "page": {
      "size": "A4",
      "orientation": "portrait",
      "marginsMm": {
        "top": 15,
        "right": 15,
        "bottom": 15,
        "left": 15
      }
    },
    "defaults": {
      "fontFamily": "DejaVu Sans",
      "fontSizePt": 10,
      "color": "#222222"
    }
  },
  "dataSource": {
    "type": "entity",
    "entityType": "Offer"
  },
  "header": [],
  "sections": [],
  "footer": []
}
```

### 23.2 Versioning rules

- Every saved template contains `schemaVersion`.
- Validation normalizes optional defaults.
- Migrations convert older schema versions.
- Conversion must be deterministic.
- Original immutable published versions remain available.
- Unsupported future schema versions must fail clearly, not be silently modified.

### 23.3 Element IDs

Element IDs must:

- Be unique within a template.
- Be stable across saves.
- Use safe characters.
- Not expose database IDs unnecessarily.
- Allow validation errors to reference the element.

### 23.4 Size limits

Suggested defaults:

- Layout JSON: 1 MB.
- Maximum elements: 500.
- Maximum nesting depth: 8.
- Maximum freeform elements per section: 200.
- Maximum sections: 100.
- Maximum conditions per template: 250.
- Maximum related table columns: 20.
- Maximum related rows per generation: configurable, default 500.

These values should be configuration parameters, with safe upper bounds.

---

## 24. Browser preview

### 24.1 Preview modes

- Sample values.
- Selected real record.
- Selected spreadsheet row.
- Empty-data diagnostic mode.
- PDF preview.

### 24.2 Sample values

The system should generate type-aware samples:

- Text: “Example text.”
- Name: realistic name.
- Date: localized date.
- Currency: localized monetary amount.
- Boolean: translated yes/no.
- Enum: first permitted option.
- Relationship: related sample label.
- Collection: 2–3 sample rows.

Designers can optionally override samples for a template.

### 24.3 Real-record preview

The designer selects a readable record. The server returns only permitted resolved values.

The browser must not query arbitrary records or fields directly from untrusted layout input.

### 24.4 PDF preview

PDF preview should:

- Use the server renderer.
- Use the same published/draft layout under review.
- Produce an ephemeral file or streamed response.
- Not create a permanent Generated Document record.
- Be rate-limited.
- Be visibly marked as preview if needed.

---

## 25. Rendering architecture

### 25.1 Canonical pipeline

```text
Template JSON
    ↓
Schema validation
    ↓
Access-aware data resolution
    ↓
Formatting and condition evaluation
    ↓
Intermediate document tree
    ↓
HTML/CSS renderer
    ↓
EspoCRM PDF engine / Dompdf
    ↓
PDF bytes
    ↓
Attachment storage and generation record
```

### 25.2 Intermediate document tree

The data resolver should not directly concatenate HTML.

It should create an internal normalized tree containing:

- Resolved values.
- Safe styles.
- Page instructions.
- Media references.
- Collection rows.
- Conditions already evaluated.

Benefits:

- Easier tests.
- Clear separation of data and rendering.
- Future support for another PDF engine.
- Better security.
- Consistent browser/PDF behavior.

### 25.3 PDF engine strategy

EspoCRM supports PDF engines and includes Dompdf by default.

Document Builder should initially integrate with the configured EspoCRM PDF engine where practical, but the extension may need its own renderer adapter to guarantee layout behavior.

The renderer must use conservative constructs because Dompdf does not support CSS Flexbox or CSS Grid.

Recommended translation:

- Flow: normal block layout.
- Grid: HTML tables or deterministic percentage-width row structures.
- Freeform: `position: relative` container with absolutely positioned children.
- Dynamic tables: HTML table layout.
- Page settings: `@page`.
- Page breaks: supported break properties and explicit break nodes.
- Backgrounds: local file paths or safe data representations.
- QR code: locally generated image.

### 25.4 Preview parity

Both browser and server renderers must consume the same normalized schema.

Differences that cannot be avoided must be documented and surfaced in the editor.

A PDF preview is the final authority.

### 25.5 Rendering isolation

Every render should use a fresh renderer instance.

The renderer must:

- Disable arbitrary remote resource access.
- Use controlled local file roots.
- Set memory and execution safeguards.
- Validate image dimensions and file types.
- Escape text values.
- Avoid injecting source values into raw CSS or HTML.

---

## 26. Data resolution architecture

### 26.1 Resolver responsibilities

The resolver must:

- Confirm scope access.
- Load the source record.
- Confirm record read access.
- Confirm each field and link is readable.
- Resolve direct fields.
- Traverse allowed single relationships.
- Load allowed multi-record relationships.
- Format values.
- Apply null policies.
- Evaluate conditions.
- Return warnings without leaking forbidden data.

### 26.2 Query efficiency

The resolver should:

- Collect all required variable paths before querying.
- Avoid one query per element.
- Deduplicate field and relationship requests.
- Preload direct single relationships where practical.
- Fetch collection fields in bounded queries.
- Use pagination or explicit limits for large collections.
- Avoid loading unused record attributes.

### 26.3 Record snapshots

The generation snapshot should store only data actually used by the document, plus required provenance.

It should not copy the entire source record automatically.

Snapshot example:

```json
{
  "source": {
    "entityType": "Offer",
    "id": "record-id",
    "name": "Offer 2026-014"
  },
  "values": {
    "Offer.number": "2026-014",
    "Offer.account.name": "Example SRL"
  },
  "collections": {
    "Offer.offerItems": [
      {
        "name": "Course A",
        "quantity": 2,
        "total": 500
      }
    ]
  }
}
```

### 26.4 Deleted or changed records

Generated documents remain available if:

- The source record is deleted.
- Related records are deleted.
- The template changes.
- Field labels change.

The source link may become unavailable, but the snapshot and PDF remain intact.

---

## 27. Spreadsheet workflow

### 27.1 Upload

Supported formats:

- CSV.
- XLSX.

Rules:

- File-size limit.
- Row-count limit.
- Worksheet-count limit.
- Formula handling must be safe.
- Macros are not executed.
- Only supported cell values are read.
- Uploaded files are stored temporarily and linked to an import record.

### 27.2 Discovery

The import wizard should:

1. Detect worksheets.
2. Let the user select a worksheet.
3. Let the user choose whether the first row contains headers.
4. Display detected columns.
5. Infer basic types.
6. Allow renaming variable labels.
7. Show sample rows.

### 27.3 Column typing

Supported inferred types:

- Text.
- Integer.
- Decimal.
- Date.
- Datetime.
- Boolean.
- Currency-like number.
- URL.
- Email.
- Image path is not supported unless explicitly designed later.

### 27.4 Validation

The user may mark template variables as required.

Before generation:

- Validate required columns.
- Validate required row values.
- Report invalid rows.
- Allow generating valid rows only, if chosen.
- Preserve row numbers in errors.
- Detect duplicate output filenames and resolve safely.

### 27.5 Import retention

Draft spreadsheet imports should expire after a configurable period, for example 24 or 72 hours.

Completed generation batches may retain:

- Original filename.
- Column mapping.
- Validation summary.
- Optional original attachment according to retention policy.

### 27.6 Batch output

Options:

- One PDF per row.
- ZIP download.
- Store each PDF as a Generated Document.
- Optional combined PDF, future.
- Output filename pattern using columns.

---

## 28. Dynamic tables and repeaters

### 28.1 Collection selection

The designer selects a multi-record relationship.

The editor displays readable fields from the related entity.

### 28.2 Sorting

Allow:

- One primary sort.
- Optional secondary sort.
- Ascending/descending.
- Only sortable fields.

### 28.3 Filtering

A safe filter builder may support:

- Equals.
- Not equals.
- Is empty.
- Is not empty.
- Greater/less.
- Date range.
- Enum in list.
- Boolean.

The filter must be compiled by server-side code, not accepted as raw query parameters.

### 28.4 Limits

Every dynamic collection element must have:

- Maximum record count.
- Truncation behavior.
- Warning behavior.
- Optional “show first N.”
- Optional “show all up to administrator maximum.”

### 28.5 Aggregation

Initial aggregations:

- Count.
- Sum.
- Average, future.
- Minimum/maximum, future.

Aggregation must be limited to supported numeric fields.

### 28.6 Page splitting

Dynamic tables must:

- Repeat header rows.
- Avoid splitting a row where practical.
- Continue across pages.
- Respect configured table width.
- Display meaningful empty state or hide.

Repeater items may use “keep item together,” but the renderer may split oversized items.

---

## 29. Media management

### 29.1 Storage options

Recommended approach:

- Use EspoCRM Attachment records and File Storage Manager.
- Maintain a Document Builder media entity only if tags, ownership, dimensions, and reusable asset metadata justify it.

### 29.2 Supported formats

Initial:

- PNG.
- JPEG.
- WebP if renderer compatibility is verified.
- SVG only with strict sanitization and controlled rendering.

### 29.3 Validation

Validate:

- MIME type.
- Extension.
- Actual file signature.
- File size.
- Image dimensions.
- SVG markup where enabled.
- Ownership/access.
- Decompression-bomb risk where applicable.

### 29.4 Media ownership

A template may reference media when the user has read access.

Publishing should verify that media remains accessible to intended generators.

Recommended permission model:

- Template-owned media.
- Team-shared media.
- Administrator-shared media.

### 29.5 Missing media

Missing media should:

- Appear as an error in the editor.
- Block publication when required.
- Block generation or render configured fallback.
- Never silently load an unrelated file.

---

## 30. QR-code requirements

### 30.1 Generation

QR codes must be generated locally by a bundled or compatible PHP library.

### 30.2 Security

The QR value is text data, not executable content.

The system must:

- Limit maximum length.
- Escape preview labels.
- Validate URL schemes when configured as URL.
- Never fetch the encoded URL during generation.
- Not use an external QR service.

### 30.3 Verification URL pattern

Future or optional version 1.0 feature:

```text
https://example.com/verify/{generatedDocumentId}
```

The extension may provide a public verification endpoint only when explicitly enabled by an administrator.

The public endpoint is not required for the core document designer.

---

## 31. Generated-document model

Recommended entity: `DocumentBuilderDocument`

### 31.1 Core fields

- Name.
- Status.
- Template.
- Template version.
- Source type.
- Source entity type.
- Source record ID.
- Source record name.
- Spreadsheet import.
- Spreadsheet row number.
- Output filename.
- PDF attachment.
- Data snapshot JSON.
- Template snapshot JSON or template-version link.
- Warning summary.
- Error summary.
- Generated by.
- Assigned user.
- Teams.
- Created at.
- Completed at.
- Batch.

### 31.2 Statuses

- Pending.
- Generating.
- Completed.
- Completed with warnings.
- Failed.
- Cancelled.

### 31.3 Immutability

After successful generation, the following should be read-only:

- PDF attachment.
- Source snapshot.
- Template-version reference.
- Source identifiers.
- Generation timestamps.
- Generator identity.

Metadata such as tags or notes may remain editable.

---

## 32. Generation-batch model

Recommended entity: `DocumentBuilderBatch`

Fields:

- Name.
- Template.
- Template version.
- Source type.
- Source entity type.
- Source selection summary.
- Spreadsheet import.
- Status.
- Total count.
- Pending count.
- Success count.
- Warning count.
- Failed count.
- Started at.
- Completed at.
- Error summary.
- ZIP attachment.
- Generated by.
- Assigned user.
- Teams.

Batch statuses:

- Pending.
- Running.
- Completed.
- Completed with errors.
- Failed.
- Cancelled.

Batch generation must execute through EspoCRM jobs for non-trivial counts.

---

## 33. Template and version data model

### 33.1 `DocumentBuilderTemplate`

Suggested fields:

- Name.
- Category.
- Description.
- Status.
- Source type.
- Entity type.
- Spreadsheet schema JSON.
- Current draft layout JSON.
- Current published version.
- Page size.
- Orientation.
- Is active.
- Owner.
- Assigned user.
- Teams.
- Created at.
- Modified at.

### 33.2 `DocumentBuilderTemplateVersion`

Suggested fields:

- Name.
- Template.
- Version number.
- Schema version.
- Layout snapshot JSON.
- Data-source snapshot JSON.
- Published by.
- Published at.
- Change note.
- Hash/checksum.
- Is current.

Published version records are immutable.

### 33.3 `DocumentBuilderImport`

Suggested fields:

- Name.
- Source attachment.
- Format.
- Worksheet.
- Header-row configuration.
- Columns JSON.
- Sample rows JSON.
- Validation summary.
- Row count.
- Status.
- Expires at.
- Owner.
- Teams.

### 33.4 `DocumentBuilderMedia`

Optional entity fields:

- Name.
- Attachment.
- Kind.
- MIME type.
- Width.
- Height.
- Size.
- Tags.
- Owner.
- Teams.
- Active.

---

## 34. API design

Exact paths may follow EspoCRM module conventions. The following represents required capabilities, not mandatory route names.

### 34.1 Metadata catalogue

`GET DocumentBuilder/entity-catalogue`

Returns eligible entities for the current user.

`GET DocumentBuilder/entity/{entityType}/fields`

Returns readable fields and relationships.

Requirements:

- ACL-aware.
- Translated labels.
- Cached safely.
- Does not expose forbidden internal metadata.

### 34.2 Template validation

`POST DocumentBuilder/template/{id}/validate`

Returns:

- Normalized layout.
- Errors.
- Warnings.
- Unresolved variables.
- Missing media.
- Renderer compatibility warnings.

### 34.3 Draft save

`PUT DocumentBuilder/template/{id}/draft`

Payload:

- Layout JSON.
- Expected revision.
- Optional change note.

Must detect stale revision conflicts.

### 34.4 Preview data

`POST DocumentBuilder/template/{id}/preview-data`

Payload:

- Source record ID or spreadsheet row.
- Requested preview mode.

Returns resolved, permitted values only.

### 34.5 PDF preview

`POST DocumentBuilder/template/{id}/preview-pdf`

Returns a temporary PDF stream or attachment reference.

### 34.6 Publish

`POST DocumentBuilder/template/{id}/publish`

Creates immutable version after validation.

### 34.7 Single generation

`POST DocumentBuilder/generate`

Payload:

- Template ID/version.
- Source entity type.
- Source record ID.
- Optional filename override within safe rules.

Returns:

- Generated document ID.
- Status.
- Download reference.

### 34.8 Batch generation

`POST DocumentBuilder/generate-batch`

Payload:

- Template ID/version.
- Record IDs or saved selection/filter representation.
- Spreadsheet import ID.
- Options.

Returns batch record.

### 34.9 Media

Required endpoints:

- List permitted media.
- Upload.
- Read metadata.
- Delete owned unused media.
- Validate template references.

### 34.10 Spreadsheet import

Required endpoints:

- Upload.
- List worksheets.
- Select worksheet.
- Detect headers.
- Confirm schema.
- Validate rows.
- Preview row.
- Start generation.

---

## 35. Record-view integration

### 35.1 Template compatibility

A template is compatible with a record when:

- Template status is Published.
- Source type is Entity.
- Template entity type matches the record entity type.
- User has read access to template.
- User has read access to source record.
- Any optional compatibility condition passes.

### 35.2 Generate Document dialog

The dialog should show:

- Compatible templates.
- Template category.
- Description.
- Last published date.
- Optional thumbnail.
- Preview action.
- Output filename.
- Generate button.
- Warning if required values are missing.

### 35.3 Generated Documents panel

A source-record panel may list:

- Document name.
- Template.
- Generation date.
- Generated by.
- Status.
- Download.
- Regenerate with latest version.
- Regenerate with original version, if allowed.

The panel should be added globally only where meaningful and should respect ACL.

---

## 36. Permissions and ACL

### 36.1 Extension entities

Standard role-based permissions should cover:

- Templates.
- Template versions.
- Generated documents.
- Batches.
- Imports.
- Media.

### 36.2 Action permissions

Suggested additional permissions:

- Design templates.
- Publish templates.
- Generate documents.
- Generate batches.
- Use spreadsheet imports.
- Manage shared media.
- View data snapshots.
- Delete generated documents.
- Configure Document Builder.

### 36.3 Source record access

Generation must verify:

1. Current user can read the source scope.
2. Current user can read the source record.
3. Current user can read every requested field.
4. Current user can read every traversed link.
5. Current user can read each related record included in a collection.

### 36.4 Template designer versus generator

A designer might be allowed to reference fields that future generators cannot read.

Recommended rule:

- The editor catalogue is based on the designer’s rights.
- Publishing warns when a template contains sensitive fields.
- At generation, inaccessible values are never resolved.
- Administrators may restrict which entity fields are available to Document Builder regardless of user ACL.
- A template may optionally require a minimum role or permission.

### 36.5 Snapshot access

Data snapshots may contain sensitive information.

Viewing snapshots should require a dedicated permission and at least the same access level as viewing generated documents.

---

## 37. Security requirements

### 37.1 No arbitrary code

The template schema must not accept:

- JavaScript.
- PHP.
- Raw CSS.
- Raw HTML.
- SQL.
- Shell commands.
- Arbitrary template helpers.

### 37.2 XSS prevention

- All source values are treated as text by default.
- Rich text is sanitized server-side.
- Attribute values are escaped.
- Preview uses safe DOM APIs where possible.
- No `innerHTML` with untrusted source values.
- Template names and labels are escaped.

### 37.3 CSS injection prevention

Styles are stored as validated typed values:

- Enumerated alignment.
- Numeric sizes with bounds.
- Validated hex/RGB colors.
- Known font identifiers.
- Known border styles.
- No raw CSS strings.

### 37.4 File security

- Validate MIME and signatures.
- Limit size and dimensions.
- Restrict local paths.
- Prevent path traversal.
- Use safe generated filenames.
- Never trust spreadsheet filenames.
- Prevent ZIP-slip when creating archives.
- Do not extract arbitrary uploaded archives.

### 37.5 SVG security

If SVG is enabled:

- Remove script.
- Remove event attributes.
- Remove foreignObject.
- Remove external references.
- Remove unsafe URL schemes.
- Limit complexity and size.
- Prefer rasterizing to a safe image before PDF rendering.

Disabling SVG for the first milestone is acceptable.

### 37.6 Remote resources

Disabled by default.

If enabled later:

- Allowlist hosts.
- Block localhost and private networks.
- Block file scheme.
- Block redirects to disallowed hosts.
- Limit response size and timeout.
- Cache safely.

### 37.7 Spreadsheet security

- Never execute macros.
- Do not evaluate spreadsheet formulas through office software.
- Treat formulas as cached values or text according to parser behavior.
- Prevent CSV formula injection when exporting any CSV.
- Limit rows, columns, worksheets, and cell lengths.

### 37.8 Denial-of-service protections

- Layout limits.
- Collection limits.
- Image limits.
- Render timeouts.
- Job queue limits.
- Rate-limit previews.
- Batch maximums.
- Memory-aware image processing.
- Cancel or fail jobs that exceed safe thresholds.

### 37.9 Auditability

Record:

- Template creation and publication.
- Version used.
- User who generated.
- Source record identity.
- Batch counts.
- Failures.
- Security-relevant validation errors.

Do not log full sensitive field values by default.

---

## 38. Performance requirements

### 38.1 Editor

Targets under normal conditions:

- Open a medium template within 2 seconds after metadata is available.
- Property changes reflect in preview within 100 ms where possible.
- Dragging remains responsive with 200 visible elements.
- Metadata catalogue should be cached by entity and user access context.

### 38.2 Single PDF

Target:

- Typical one-to-five-page PDF generated within 5 seconds.
- Complex large documents may use a background job.

### 38.3 Batch

- Batch generation must not block an HTTP request.
- Process items in bounded job chunks.
- Persist progress.
- Allow retry of failed items.
- Avoid duplicate generation after job retry through idempotency keys or record state.

### 38.4 Collections

- Default maximum 500 rows per dynamic table.
- Warn before large generation.
- Use efficient collection queries.
- Avoid loading every source field.

### 38.5 Caching

Potential caches:

- Entity catalogue.
- Field catalogue.
- Template validation result by hash.
- Published template normalized schema.
- Media metadata.
- Font catalogue.

ACL-sensitive caches must not leak data between users.

---

## 39. Error handling

### 39.1 User-facing categories

- Validation error.
- Permission error.
- Source record missing.
- Variable missing.
- Related record missing.
- Media missing.
- Renderer error.
- File storage error.
- Batch job error.
- Template revision conflict.

### 39.2 Error behavior

- Provide a clear human-readable message.
- Preserve technical details in logs.
- Do not expose server paths or stack traces.
- Associate errors with element IDs where possible.
- Allow retry when safe.
- Do not create a Completed generated-document record for a failed render.
- Clean temporary files after failure.

### 39.3 Partial batch failure

A batch may complete with errors.

It must show:

- Successful records.
- Failed records.
- Warning records.
- Error reason per item.
- Retry failed items action.

---

## 40. Internationalization and localization

Initial languages:

- `en_US`.
- `ro_RO`.

Requirements:

- All UI labels translatable.
- Entity and field labels use EspoCRM translations.
- Dates use selected locale and timezone.
- Currency uses source field currency and locale.
- Boolean and enum values use translated labels.
- Generated PDF supports Romanian diacritics.
- Template designers may override static document text per template.

Multi-language variants of one template are future scope. Initial solution: duplicate templates by language.

---

## 41. Accessibility

The visual editor should provide:

- Keyboard-accessible controls.
- Visible focus states.
- Proper button labels.
- Dialog focus management.
- Screen-reader labels for element library and property fields.
- Non-color-only validation indications.
- Text alternatives for images in the editor.
- Sufficient contrast in editor chrome.

The generated PDF’s formal accessibility tagging is not required initially, but the layout should avoid creating image-only text unnecessarily.

---

## 42. Extension architecture

### 42.1 Module paths

Follow EspoCRM module conventions:

Backend:

```text
custom/Espo/Modules/DocumentBuilder/
```

Frontend:

```text
client/custom/modules/document-builder/
```

### 42.2 Recommended backend layers

```text
DocumentBuilder/
├── Controllers or API actions
├── Entities
├── Repositories
├── Services
├── DataSource
│   ├── EntityCatalogue
│   ├── EntityResolver
│   └── SpreadsheetResolver
├── Layout
│   ├── Validator
│   ├── Normalizer
│   ├── Migrator
│   └── ConditionEvaluator
├── Rendering
│   ├── DocumentTreeBuilder
│   ├── HtmlRenderer
│   ├── PdfRenderer
│   └── QrCodeRenderer
├── Jobs
├── File
├── Security
└── Resources
    ├── metadata
    ├── i18n
    ├── routes.json
    └── module.json
```

### 42.3 Recommended frontend layers

```text
client/custom/modules/document-builder/
├── src/
│   ├── controllers
│   ├── views
│   │   ├── template
│   │   ├── editor
│   │   ├── dialogs
│   │   ├── fields
│   │   └── panels
│   ├── editor
│   │   ├── state
│   │   ├── commands
│   │   ├── renderer
│   │   ├── drag-drop
│   │   ├── validation
│   │   └── elements
│   ├── services
│   └── utils
├── res/
│   ├── templates
│   └── css
└── lib/
```

### 42.4 Separation of concerns

- Views orchestrate UI.
- API actions validate requests and call services.
- Services own use cases.
- Resolvers own data access.
- Validators own schema rules.
- Renderers do not perform database access.
- File services own attachment persistence.
- Jobs call generation services.
- Layout JSON is never trusted without server normalization.

---

## 43. Reuse from the Django `apps/diplome` application

### 43.1 Reusable concepts

The following concepts are valuable:

- Versioned layout JSON.
- Strict server-side validation.
- Browser renderer separated from editor state.
- Undo/redo history.
- Image, background, icon, list, and table concepts.
- Media ownership checks.
- Preview workflow.
- Generation batches.
- Generated-document snapshots.
- Safe filenames.
- Owner-scoped assets and outputs.
- Explicit page dimensions in millimetres.
- Grid and guide behavior in freeform mode.

### 43.2 Components requiring redesign

- Django models.
- Django forms and views.
- Python ReportLab renderer.
- Participant-specific entities.
- Participant import mapping fixed to four fields.
- Hardcoded variable catalogue.
- Django media-library integration.
- Absolute-position-only document model.
- Django transaction and file-storage code.

### 43.3 Components that should not be mechanically ported

The implementation should not translate Python line by line into PHP.

It should preserve product behavior while using native EspoCRM:

- Metadata.
- ORM.
- ACL.
- Attachments.
- Jobs.
- Views.
- Routes.
- Module structure.
- Record actions.

---

## 44. Suggested metadata and integration points

The extension is expected to use EspoCRM capabilities including:

- `entityDefs` for extension entities and fields.
- `clientDefs` for controllers, views, menus, and record integration.
- `aclDefs` and standard roles for access.
- `entityAcl` and ACL services for field/link restrictions.
- Module routes for custom API actions.
- Custom frontend views.
- View setup handlers for global record/list integration.
- Attachment and File Storage Manager for files.
- Jobs for batch generation.
- ORM EntityManager and repositories.
- Metadata service on backend and frontend.

Implementation must verify exact EspoCRM 10.3 APIs against the installed source and official documentation rather than rely on older examples.

---

## 45. Configuration

Recommended administrator settings:

- Enabled source entity types.
- Disabled source entity types.
- Maximum relationship depth.
- Maximum template size.
- Maximum elements.
- Maximum image size.
- Maximum spreadsheet size.
- Maximum spreadsheet rows.
- Maximum batch records.
- Maximum collection rows.
- Preview rate limit.
- Temporary import retention.
- Generated-document retention, optional.
- Allow SVG.
- Allow WebP.
- Allowed fonts.
- Enable public verification, future.
- Default PDF engine.
- Default locale.
- Default page size.
- Store template snapshot in each generated document.
- Store resolved data snapshot.
- Enable list-view mass generation.
- Allow generators to use draft templates, normally false.

---

## 46. Output filenames

Templates should define a safe filename pattern using variables.

Example visual configuration:

```text
offer-{Offer.number}-{Offer.account.name}.pdf
```

Rules:

- Resolve permitted scalar variables only.
- Remove path separators.
- Normalize control characters.
- Limit length.
- Replace unsafe characters.
- Avoid reserved filenames.
- Resolve duplicates with a suffix.
- Always enforce `.pdf`.
- Provide fallback filename.

Spreadsheet batch filenames must include row identity or a collision suffix.

---

## 47. Template thumbnails

Recommended but not mandatory for MVP.

Behavior:

- Generate thumbnail from first page after publication.
- Store as attachment.
- Display in template selector.
- Regenerate on new published version.
- Thumbnail failure should not block publication if PDF generation succeeds.

---

## 48. Testing strategy

### 48.1 Unit tests

Required units:

- Layout schema validator.
- Layout normalizer.
- Schema migration.
- Variable-path parser.
- Field catalogue filtering.
- Field formatting.
- Null policies.
- Condition evaluation.
- Safe filename generation.
- Grid-to-render translation.
- Freeform geometry validation.
- QR value validation.
- Media validation.
- Spreadsheet header/type detection.
- Snapshot builder.

### 48.2 Integration tests

Required workflows:

- Create and publish template.
- Resolve standard entity fields.
- Resolve custom entity fields.
- Resolve related field.
- Resolve repeating relationship.
- Generate single PDF.
- Store attachment.
- Preserve snapshot.
- Generate batch through job.
- Partial batch failure.
- Spreadsheet upload and mapping.
- CSV/XLSX row generation.
- Delete source after generation.
- Delete/archive template after generation.
- ACL denial for scope, record, field, and link.
- Cross-user template/media access.
- Revision conflict.

### 48.3 Frontend tests

Focus:

- Add/reorder/delete element.
- Undo/redo.
- Source selection.
- Variable browser search.
- Flow insertion.
- Grid span controls.
- Freeform move/resize/snap.
- Property editing.
- Save status.
- Validation navigation.
- Unsaved-change warning.
- Preview request.
- Dialog focus.

### 48.4 PDF regression tests

PDF byte-for-byte comparison is often unstable.

Recommended checks:

- PDF generated and readable.
- Page count.
- Expected text extraction for test fixtures.
- Image presence where inspectable.
- Known page geometry.
- Visual golden screenshots in controlled test environments, optional.
- Manual acceptance fixtures for diploma, offer, history, and spreadsheet batch.

### 48.5 Security tests

- XSS payload in source field.
- XSS payload in static text.
- CSS injection attempts.
- Unsafe SVG.
- Path traversal filename.
- Oversized image.
- Huge layout.
- Huge spreadsheet.
- Circular relationship.
- Forbidden field.
- Forbidden related record.
- Remote-resource URL.
- Malformed JSON.
- Stale revision.
- Unauthorized batch download.

### 48.6 Manual testing

Manual scenarios should be performed on an existing approved EspoCRM test instance. The implementation agent should not create disposable infrastructure without explicit instruction.

---

## 49. Acceptance criteria

### 49.1 Core product

- A non-technical user can create a template without HTML/CSS.
- A template can target a standard or custom EspoCRM entity.
- Fields added through Entity Manager appear automatically.
- The user can insert readable entity fields as variables.
- The user can preview with a real record.
- The user can generate a PDF and download it.
- The generated PDF is stored as an EspoCRM attachment.
- The generated record preserves source and template provenance.
- ACL restrictions are enforced server-side.

### 49.2 Flow layout

- Elements can be reordered.
- Paragraphs expand naturally.
- Long content continues onto new pages.
- Manual page breaks work.
- Margins and padding affect output predictably.
- Headers and footers reserve page space.

### 49.3 Grid layout

- User can choose column count.
- Elements snap to columns.
- Column spans are visually represented.
- PDF output matches intended row and column proportions.
- Rendering does not rely on unsupported CSS Grid/Flexbox.

### 49.4 Freeform layout

- User can position and resize elements precisely.
- Dimensions are stored in millimetres.
- Snap grid and guides work.
- Elements can be rotated, layered, locked, aligned, and distributed.
- A full-page diploma can be generated pixel-accurately within renderer tolerances.
- Freeform section does not split across pages.

### 49.5 Variables

- Direct fields resolve.
- Related single-record fields resolve.
- Missing values follow configured policy.
- Forbidden fields are not exposed.
- Publication fails for unresolved variable paths.

### 49.6 Repeating data

- Related records can populate a dynamic table.
- Table headers repeat across pages.
- Sorting and row limits work.
- Empty collection behavior works.
- Related records are ACL-filtered.

### 49.7 Media and QR

- User can insert a permitted image.
- Missing image is detected.
- QR code accepts a variable-composed value.
- QR is generated locally.
- QR is readable in the generated PDF.

### 49.8 Spreadsheet

- User can upload CSV/XLSX.
- User can select sheet and header row.
- Columns become variables.
- Invalid rows are reported.
- Valid rows can generate one PDF each.
- ZIP output uses safe unique filenames.

### 49.9 History

- Generated document remains available after source changes.
- Exact template version is known.
- Resolved data snapshot is available to authorized users.
- Batch progress and errors are visible.

---

## 50. Implementation phases

Each phase is intentionally bounded so it can be started in a new Codex chat. A phase must not silently begin work assigned to a later phase.

### Phase 0 — Repository and extension scaffold

Goal:

Create the installable Document Builder module structure without business functionality.

Deliverables:

- Extension metadata.
- Module paths.
- Composer/autoload setup if required.
- Basic navigation group.
- Empty translations.
- Installation and uninstall scripts.
- Minimal README.
- Development commands.
- No template editor yet.

Exit criteria:

- Extension installs.
- EspoCRM rebuild completes.
- Extension uninstalls without deleting unrelated data or files.
- Module paths load correctly.

### Phase 1 — Template domain and lifecycle

Goal:

Create template and template-version entities with Draft, Published, and Archived lifecycle.

Deliverables:

- `DocumentBuilderTemplate`.
- `DocumentBuilderTemplateVersion`.
- Metadata, layouts, ACL.
- Create/list/detail records.
- Draft layout JSON storage.
- Publish service with immutable version.
- Duplicate and archive actions.
- Basic schema version.

Exit criteria:

- Draft template can be created.
- Publishing creates immutable version.
- Published version cannot be edited.
- Duplicate works.
- ACL tests pass.

### Phase 2 — Editor shell and flow elements

Goal:

Provide a functioning block editor for simple multi-page documents.

Elements:

- Section.
- Container.
- Heading.
- Paragraph.
- Static text.
- Divider.
- Spacer.
- Page break.

Deliverables:

- Editor route/view.
- Left library, center canvas, right inspector.
- Drag/reorder.
- Save.
- Undo/redo.
- Validation display.
- Page settings.
- Browser renderer for flow mode.

Exit criteria:

- User can visually create a multi-section document.
- Save/reload preserves layout.
- Long sample paragraphs show page-flow preview.
- No entity variables yet.

### Phase 3 — Entity catalogue and variables

Goal:

Connect templates to any eligible EspoCRM entity.

Deliverables:

- Entity source selector.
- Entity catalogue endpoint.
- Field browser.
- Direct variables.
- Single-related field paths.
- Type-aware formatting.
- Sample data.
- Real-record preview data.
- ACL-aware server resolver.

Exit criteria:

- Contact fields work.
- Account fields work.
- A custom Entity Manager entity works without extension changes.
- Forbidden fields are absent.
- Related field preview works.
- Server re-checks ACL.

### Phase 4 — Server PDF rendering and generated documents

Goal:

Generate and persist a PDF for one EspoCRM record.

Deliverables:

- Intermediate document tree.
- HTML renderer.
- PDF adapter.
- `DocumentBuilderDocument`.
- Attachment persistence.
- Single-record generation endpoint.
- Data/template snapshots.
- Safe output filenames.
- PDF preview.

Exit criteria:

- One record produces a stored PDF.
- Browser and PDF output are acceptably aligned.
- Source snapshot is stored.
- Failure cleans temporary files.
- Generated file uses configured storage abstraction.

### Phase 5 — Grid layout, media, and visual styling

Goal:

Support professional structured documents.

Deliverables:

- Grid sections.
- Column spans.
- Grid gaps.
- Image.
- Background image.
- Common box styling.
- Border/background controls.
- Font controls.
- Media picker and upload.
- Conservative grid-to-PDF translation.

Exit criteria:

- Offer-style two-column header renders.
- Images render in preview and PDF.
- No CSS Grid/Flexbox dependency in PDF.
- Missing media is reported.

### Phase 6 — Icons, icon lists, QR codes, shapes

Goal:

Expand the visual element library.

Deliverables:

- Icon.
- Icon List.
- QR code.
- Rectangle/circle/line.
- Bundled approved icon catalogue.
- Local QR generator.
- Element properties and validation.

Exit criteria:

- Icon list works with variables.
- QR from record URL is readable.
- Shape elements render correctly.
- Unsafe custom icon content is blocked.

### Phase 7 — Freeform section

Goal:

Support pixel-perfect diplomas and certificates.

Deliverables:

- Freeform section.
- Millimetre geometry.
- Drag/resize.
- Grid snapping.
- Guides and rulers.
- Align/distribute.
- Rotation.
- Layer panel.
- Lock/hide.
- Full-page mode.
- Freeform PDF renderer.

Exit criteria:

- A4 landscape diploma can be recreated.
- Elements match intended coordinates within accepted tolerance.
- Freeform section starts on a new page when necessary.
- Freeform section never splits.

### Phase 8 — Dynamic tables and repeaters

Goal:

Support related-record histories and offer items.

Deliverables:

- Relationship collection browser.
- Dynamic related-record table.
- Repeater.
- Sort.
- Safe filters.
- Row limits.
- Empty behavior.
- Header repetition.
- Count and sum.
- ACL-filtered related records.

Exit criteria:

- Offer Items table spans pages.
- Contact course history renders.
- Forbidden related records are excluded.
- Query count remains bounded.

### Phase 9 — Spreadsheet sources

Goal:

Support CSV/XLSX templates and batch generation.

Deliverables:

- `DocumentBuilderImport`.
- Upload wizard.
- Worksheet selection.
- Header detection.
- Type inference.
- Column variables.
- Row validation.
- Preview row.
- One PDF per valid row.
- ZIP output.

Exit criteria:

- CSV and XLSX fixtures work.
- Invalid rows are reported by source row.
- Valid rows generate PDFs.
- Output filenames are safe and unique.
- Temporary imports expire.

### Phase 10 — Record/list integration and batch jobs

Goal:

Make Document Builder available naturally across EspoCRM.

Deliverables:

- Global Generate Document record action.
- Compatible-template dialog.
- Generated Documents record panel.
- List-view selected-record action.
- `DocumentBuilderBatch`.
- Background jobs.
- Progress and retry.
- Cancellation where safe.

Exit criteria:

- Compatible templates appear on Contact and custom entities.
- Batch generation does not block HTTP.
- Partial failures are visible.
- Retry does not duplicate successful documents.

### Phase 11 — Hardening, polish, and release

Goal:

Prepare production release.

Deliverables:

- Full security review.
- Performance profiling.
- Accessibility pass.
- Romanian and English translation review.
- Upgrade/migration tests.
- Uninstall behavior review.
- Documentation.
- Example templates.
- Production checklist.
- Extension ZIP.

Exit criteria:

- All acceptance scenarios pass.
- No critical/high security findings.
- Upgrade from prior phase test package works.
- Uninstall preserves generated business data according to documented policy.
- Release package installs on clean EspoCRM 10.3 test instance.

---

## 51. Codex implementation boundaries

### 51.1 General rules

- Treat this PRD as product requirements, not permission to implement all phases at once.
- Work on one phase or narrowly defined task per chat.
- Do not start the next phase automatically.
- Do not create unrelated infrastructure.
- Do not create temporary EspoCRM containers or databases without explicit approval.
- Prefer the existing approved test instance.
- Do not modify EspoCRM core files.
- Keep all extension code inside module paths.
- Use EspoCRM APIs and metadata rather than direct database access.
- Inspect the installed EspoCRM 10.3 source when documentation is ambiguous.
- Preserve backwards-compatible schema migrations.
- Keep server validation authoritative.
- Provide manual test steps for product-owner validation.

### 51.2 Validation proportionality

For each task:

- Run focused syntax checks.
- Run focused unit/integration tests for affected behavior.
- Avoid unrelated full-suite runs unless contracts cross multiple areas.
- Report untested manual behavior clearly.
- Do not claim visual pixel accuracy without an actual PDF comparison.

### 51.3 Source-of-truth hierarchy

When requirements conflict:

1. Explicit latest product-owner instruction.
2. This PRD.
3. Repository `AGENTS.md`.
4. Phase/task specification.
5. Existing extension behavior.
6. EspoCRM official documentation and installed source.
7. Framework conventions.

---

## 52. Risks and mitigations

### 52.1 Browser/PDF mismatch

Risk:

Browser CSS support is much broader than Dompdf.

Mitigation:

- Canonical schema.
- Dedicated browser and PDF renderers.
- Conservative PDF layout translation.
- PDF preview as final authority.
- Compatibility warnings.

### 52.2 Overly complex editor

Risk:

Combining flow, grid, and freeform may overwhelm users.

Mitigation:

- Section mode is explicit.
- Default to Flow.
- Use templates/presets.
- Hide advanced controls until selected.
- Contextual inspector.
- Freeform presented as an advanced section.

### 52.3 ACL data leakage

Risk:

Template fields or previews expose restricted data.

Mitigation:

- Filter field catalogue.
- Server-side access checks.
- Access-aware related queries.
- No client-provided raw paths without validation.
- Dedicated snapshot permission.

### 52.4 Large related collections

Risk:

Large histories produce slow or huge PDFs.

Mitigation:

- Required row limits.
- Warnings.
- Background jobs.
- Efficient queries.
- Configurable hard maximum.
- Pagination.

### 52.5 Template schema instability

Risk:

Rapid editor development creates incompatible layouts.

Mitigation:

- Versioned schema.
- Migrators.
- Immutable published versions.
- Contract tests.
- No ad-hoc unversioned JSON changes.

### 52.6 Unsafe media

Risk:

SVG or remote resources create security issues.

Mitigation:

- Local attachments.
- Strict validation.
- SVG disabled initially or sanitized/rasterized.
- Remote resources disabled by default.

### 52.7 Scope creep

Risk:

Attempting Word/Canva/InDesign parity prevents completion.

Mitigation:

- Phase boundaries.
- Prioritized element library.
- Clear non-goals.
- Use presets and compound elements instead of unlimited custom behavior.

---

## 53. Product decisions already made

The following decisions should be considered approved unless the product owner changes them:

1. Product name: **Document Builder**.
2. It is a generic visual PDF builder, not a diploma-only extension.
3. It must support both EspoCRM entity data and CSV/XLSX data.
4. Entity fields must be metadata-driven, not hardcoded.
5. It must support standard and custom entities.
6. It must support multi-page documents.
7. Normal document content uses flow layout.
8. Structured content can use a visual grid.
9. Pixel-perfect content is supported through freeform sections.
10. Freeform positioning is not the default for the entire document.
11. Padding, margin, width, alignment, and style controls are required.
12. Background images, images, icons, icon lists, lists, paragraphs, tables, and QR codes are required.
13. Dynamic related-record histories and offer items are required.
14. The extension is intended for users who do not know HTML/CSS.
15. Generated files and history live inside EspoCRM.
16. The native EspoCRM PDF template feature remains untouched.

---

## 54. Open decisions with recommended defaults

### 54.1 Dedicated media entity

Recommended default:

Use a dedicated `DocumentBuilderMedia` entity backed by EspoCRM attachments because reusable tagging, ownership, dimensions, and template-reference checks are useful.

### 54.2 SVG support

Recommended default:

Disable custom SVG upload in the first production milestone. Support bundled icons and raster images. Add sanitized SVG after security tests.

### 54.3 Template source type

Recommended default:

One primary source per template. Do not mix unrelated entity and spreadsheet sources in version 1.0.

### 54.4 Relationship depth

Recommended default:

Two levels for single-record paths, configurable up to three.

### 54.5 Custom fonts

Recommended default:

Controlled bundled font list in version 1.0. Administrator-uploaded fonts later.

### 54.6 Autosave

Recommended default:

Manual save with unsaved-change warning first. Add revision-aware autosave after the editor is stable.

### 54.7 Public verification

Recommended default:

Not part of core version 1.0. QR codes can still encode existing record or external verification URLs.

### 54.8 Native PDF engine integration

Recommended default:

Use EspoCRM’s installed Dompdf capabilities through a Document Builder renderer adapter. Do not depend on unsupported CSS Grid or Flexbox.

---

## 55. Example template structures

### 55.1 Diploma

```text
Document: A4 Landscape, margins 0
└── Freeform Section: full page
    ├── Background Image
    ├── Organization Logo
    ├── Heading: DIPLOMĂ
    ├── Paragraph: introductory text
    ├── Variable: Contact Name
    ├── Variable: Course Name
    ├── Variable: Certificate Number
    ├── QR Code: Verification URL
    ├── Signature Image
    └── Generated Date
```

### 55.2 Offer

```text
Document: A4 Portrait
├── Header Grid
│   ├── Logo
│   └── Company details
├── Heading: Offer Number
├── Customer Grid
│   ├── Account name/address
│   └── Offer date/validity
├── Paragraph: introduction
├── Dynamic Table: Offer Items
├── Grid: totals
├── Paragraph: terms
├── Signature Grid
└── Footer: page number
```

### 55.3 Course history

```text
Document: A4 Portrait
├── Heading: Training History
├── Grid: Contact identity fields
├── Divider
├── Repeater: Attended Courses
│   ├── Heading: Course name
│   ├── Grid: dates, result, certificate
│   └── Divider
└── Footer: generated at
```

### 55.4 Spreadsheet certificate batch

```text
Data Source: XLSX worksheet Participants
Document: A4 Landscape
└── Freeform Section
    ├── Background Image
    ├── Variable: row.full_name
    ├── Variable: row.course_name
    ├── Variable: row.certificate_number
    └── QR Code: row.verification_url
```

---

## 56. Definition of done for version 1.0

Version 1.0 is complete only when all of the following are true:

- The extension installs on EspoCRM 10.3.x.
- A non-technical user can design a document visually.
- Entity and spreadsheet sources both work.
- Standard and custom entities work.
- Flow, grid, and freeform sections work.
- Required element library is available.
- Direct, related, and repeating data work.
- Single and batch generation work.
- Generated PDFs are stored using EspoCRM attachments.
- Template versions and data snapshots are preserved.
- ACL is enforced for scopes, records, fields, links, media, and outputs.
- Browser preview and PDF preview are acceptably aligned.
- Error handling is actionable.
- Romanian and English UI are available.
- Security and performance limits are configurable.
- Upgrade and uninstall behavior is documented and tested.
- Example templates demonstrate diploma, offer, history, and XLSX batch scenarios.

---

## 57. Official technical references

The implementation should verify behavior against the current EspoCRM 10.3 source and official documentation.

- EspoCRM module structure:  
  https://docs.espocrm.com/development/modules/

- EspoCRM extension development and module directories:  
  https://docs.espocrm.com/development/how-to-start/

- Metadata access and extension:  
  https://docs.espocrm.com/development/metadata/

- Entity definitions:  
  https://docs.espocrm.com/development/metadata/entity-defs/

- Frontend client definitions:  
  https://docs.espocrm.com/development/metadata/client-defs/

- Custom views:  
  https://docs.espocrm.com/development/custom-views/

- View setup handlers:  
  https://docs.espocrm.com/development/frontend/view-setup-handlers/

- ACL services:  
  https://docs.espocrm.com/development/acl/

- Entity ACL metadata:  
  https://docs.espocrm.com/development/metadata/entity-acl/

- ORM and Entity Manager:  
  https://docs.espocrm.com/development/orm/

- Record services:  
  https://docs.espocrm.com/development/services/

- Attachments and file storage:  
  https://docs.espocrm.com/development/attachments/

- Background jobs:  
  https://docs.espocrm.com/development/jobs/

- PDF engines metadata:  
  https://docs.espocrm.com/development/metadata/app-pdf-engines/

- Existing EspoCRM PDF behavior:  
  https://docs.espocrm.com/user-guide/printing-to-pdf/

- Dompdf capabilities and limitations:  
  https://github.com/dompdf/dompdf

---

## 58. Final product statement

Document Builder will make EspoCRM document generation accessible to people who understand business documents but do not know HTML or CSS.

It will not reduce every document to one layout model. Instead, it will combine:

- The natural pagination of Word-like flow.
- The structural control of a visual grid.
- The precision of a freeform diploma canvas.
- The data awareness of EspoCRM metadata and ACL.
- The repeatability of CRM and spreadsheet batch generation.

The extension should be built as a document platform rather than a collection of hardcoded diploma fields. Every architectural decision should preserve that generality.
