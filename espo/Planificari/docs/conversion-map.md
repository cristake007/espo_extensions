# Conversion Map - Planificari perioade cursuri

## 1. Summary

The Django app is a course-period planning tool. It imports CSV/XLSX course lists, generates scheduled course periods across selected months while avoiding weekends and configured holidays, stores short-lived generation history, exports XLSX/XML, updates WordPress course dates through REST calls, and can update a Word document by matching course names to generated periods.

The EspoCRM conversion should be metadata-first. The Django code is reference material only; Django models, forms, views, templates, URLs, static JavaScript, permissions, and authentication should not be copied directly.

Django `AppSetting` is saved workflow preferences/configuration only. It is not a business object and must not be converted into an EspoCRM entity during Phase 2.

## 2. Source Django structure

- `models.py`: `AppSetting` and `ScheduleGeneration`.
- `forms.py`: upload, generator, export, Word matching, and XML export form validation.
- `views.py` and `urls.py`: generator, history, export, WordPress updater, XML export, and Word converter routes.
- `services.py`, `scheduler.py`, `file_handlers.py`: schedule-generation workflow, business-day scheduling, CSV/XLSX parsing, and XLSX export.
- `xml_export.py`: XML event export for generated schedules.
- `word_matching.py`: DOCX table parsing, fuzzy course matching, and DOCX generation.
- `wp_course_updater.py`: WordPress REST client and course-date merge/update logic.
- `settings_store.py`, `selectors.py`, `presentation.py`, `validators.py`: saved settings, owned-history queries, UI data shaping, and input/security validation.
- `templates/planificator/`: Django screens and partials for generator, history, XML converter, Word converter, and course updater.
- `static/planificator/`: browser JavaScript for the workflow screens.
- `tests*.py`: behavioral expectations for permissions, validation, exports, generation, cleanup, Word matching, and WordPress update safety.
- `migrations/`: Django table history for settings and schedule generations.

## 3. Extension scaffold structure

- `manifest.json`: EspoCRM extension metadata.
- `README.md`: package notes and ZIP build command.
- `files/custom/Espo/Modules/Planificari/Resources/module.json`: EspoCRM module registration.
- `files/custom/Espo/Modules/Planificari/Controllers/Planificari.php`: minimal standard record controller.
- `files/custom/Espo/Modules/Planificari/Resources/metadata/scopes/Planificari.json`: existing generic scaffold entity scope.
- `files/custom/Espo/Modules/Planificari/Resources/metadata/entityDefs/Planificari.json`: generic scaffold fields only: name, description, timestamps, users, and teams.
- `files/custom/Espo/Modules/Planificari/Resources/metadata/clientDefs/Planificari.json`: standard record controller, only-my filter, calendar icon.
- `files/custom/Espo/Modules/Planificari/Resources/metadata/aclDefs`, `entityAcl`, `recordDefs`: empty/basic scaffold ACL and dynamic logic metadata.
- `files/custom/Espo/Modules/Planificari/Resources/i18n/en_US` and `ro_RO`: scaffold labels.
- `scripts/AfterInstall.php` and `scripts/BeforeUninstall.php`: add/remove `Planificari` from EspoCRM tab list.

The scaffold is a simple EspoCRM extension package, not the full `ext-template` npm scaffold. Runtime files will be installed under `custom/Espo/Modules/Planificari/...`. It can be built with the README ZIP command.

## 4. Django model to EspoCRM entity map

| Django model | Purpose | Proposed EspoCRM entity | EspoCRM entity type | Convert in phase | Notes |
| --- | --- | --- | --- | --- | --- |
| `ScheduleGeneration` | Stores one generated schedule, source upload metadata, JSON schedule rows, original file bytes, owner, creation time, and expiration. | `Planificari` | `BasePlus` | Phase 2 minimal root, Phase 3 fields | Reuse the existing scaffold entity as the root generation record; do not add business fields in Phase 1. |
| `AppSetting` | Stores global/user workflow preferences by scope for generator, Word converter, and WordPress updater. | None in Phase 2 | Not an entity | Phase 10 review only | Do not create `AppSetting`, `PlanificariSettings`, settings fields, custom PHP, custom JS, or Django-style settings storage. Later review EspoCRM user preferences, EspoCRM config, metadata defaults, or a small admin/settings mechanism. |

## 5. Django field to EspoCRM field map

| Django model | Django field | Django type | Required/default/choices | Proposed EspoCRM field | Proposed EspoCRM type | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `AppSetting` | `user`, `scope`, `payload`, `updated_at` | FK, varchar, JSON, datetime | Saved workflow preferences/configuration | None in Phase 2 | Not converted | Do not create settings entities or settings fields now. Later review EspoCRM user preferences, EspoCRM config, metadata defaults, or a small admin/settings mechanism only if still needed. |
| `ScheduleGeneration` | `id` | UUIDField primary key | Default UUID | Native id | EspoCRM id | Use native EspoCRM record ID. |
| `ScheduleGeneration` | `owner` | FK to auth user | Required, cascade | Assigned/created owner | `createdBy`, `assignedUser`, or `assignedUsers` | Use EspoCRM users, teams, and ACL instead of Django auth. |
| `ScheduleGeneration` | `year` | PositiveSmallIntegerField | Required | `year` | Int/enum | Year bounds are dynamic: current year - 1 through current year + 5. |
| `ScheduleGeneration` | `selected_months` | JSONField | Default `[]` | `selectedMonths` | Multi-enum or JSON | Prefer multi-enum with months if storing selections. |
| `ScheduleGeneration` | `holidays` | JSONField | Default `[]` | `holidays` | Array/JSON or related records | DD.MM.YYYY validation may need formula or custom PHP later. |
| `ScheduleGeneration` | `random_seed` | PositiveBigIntegerField | Required | `randomSeed` | BigInt/varchar | Needed only if reproducibility remains a requirement. |
| `ScheduleGeneration` | `schedule` | JSONField | Default `[]` | Related schedule rows | Has-many entity or JSON | Prefer child records for CRM-native search/layouts; JSON only if kept as generated artifact. |
| `ScheduleGeneration` | `source_course_count` | PositiveIntegerField | Required | `sourceCourseCount` | Int | Metadata field. |
| `ScheduleGeneration` | `generated_entry_count` | PositiveIntegerField | Required | `generatedEntryCount` | Int | Metadata field. |
| `ScheduleGeneration` | `source_file_name` | CharField(255) | Required | `sourceFileName` | Varchar | Could be derived from Attachment/Document. |
| `ScheduleGeneration` | `source_file_digest` | CharField(64) | Required | `sourceFileDigest` | Varchar | SHA-256 digest, custom PHP later if recalculated. |
| `ScheduleGeneration` | `source_file_data` | BinaryField | Default `bytes`, not editable | Source file attachment | Attachment/Document | Use EspoCRM attachment/document mechanisms. |
| `ScheduleGeneration` | `created_at` | DateTimeField | Auto add | `createdAt` | Native datetime | Native EspoCRM field. |
| `ScheduleGeneration` | `expires_at` | DateTimeField indexed | Required | `expiresAt` | Datetime | Cleanup/expiration behavior likely needs later custom PHP or scheduled job. |

## 6. Django relationship to EspoCRM relationship map

| Django relationship | Source | Target | Proposed EspoCRM link | Relationship type | Notes |
| --- | --- | --- | --- | --- | --- |
| `AppSetting.user` | `AppSetting` | Django `User` | None in Phase 2 | Not converted | Treat as workflow preference ownership only; do not create a settings entity or link. |
| `ScheduleGeneration.owner` | `ScheduleGeneration` | Django `User` | `createdBy`, `assignedUser`, or `assignedUsers` | User ownership | Use EspoCRM ACL and teams. Exact field choice should be finalized when the root entity is implemented. |
| Generated schedule rows inside `ScheduleGeneration.schedule` | JSON row | Generation | `planificariRows` | One-to-many normalized child records | Implemented metadata-only in Phase 3E as `PlanificariRow`; no parser/generator/export service yet. |

## 7. Django view/template to EspoCRM UI map

| Django view/template | Purpose | Proposed EspoCRM equivalent | Custom JS needed? | Custom PHP needed? | Notes |
| --- | --- | --- | --- | --- | --- |
| `PeriodGeneratorView`, `generator_perioade.html` | Upload course list and generate periods. | Start with standard `Planificari` list/detail/edit layouts; custom action later only if needed. | Later likely yes | Later likely yes | Upload parsing and generation are workflow logic, not Phase 1. |
| `ScheduleResultView`, result partials | Display generated schedule and export actions. | Detail view plus relationship panels or bottom panels later. | Later maybe | Later maybe | Use standard layouts first; custom table only if native layouts are insufficient. |
| `ScheduleHistoryView`, `istoric.html` | List owned, unexpired generations. | List view with only-my filter and filters on expiration. | No initially | Later maybe | Existing scaffold already has only-my bool filter. |
| `ScheduleSampleCsvView` | Download sample CSV. | Documentation or later custom action. | No | Later maybe | Not needed for first entity phases. |
| `ScheduleExportView` | Download XLSX from saved generation. | Later row/action button or standard export if sufficient. | Later maybe | Later yes | XLSX shape is custom. |
| `ScheduleXmlExportView`, `XmlExportView` | Generate XML events. | Later custom action/export service. | Later maybe | Later yes | XML contract is domain-specific. |
| `CourseRefreshView` and updater endpoints | Preview and update WordPress course dates. | Later separate tool/action if still in scope. | Later yes | Later yes | Requires REST credentials, safe URL validation, and row actions. |
| `WordConverterView` and Word match endpoints | Match generated schedule to DOCX rows and produce updated DOCX. | Later custom tool/action if still in scope. | Later yes | Later yes | Fuzzy matching and DOCX editing are custom workflow. |
| Django templates and HTMX/Alpine/DaisyUI screens | Current UI. | Do not port directly. | No for Phase 1 | No for Phase 1 | Replace with EspoCRM native record views first. |

## 8. Business rule map

| Business rule | Source file | EspoCRM-native approach | Needs metadata? | Needs formula? | Needs dynamic logic? | Needs hook/service later? | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Only authenticated users with feature permission can use tools. | `models.py`, `views.py` | EspoCRM users, roles, teams, ACL. | Yes | No | Maybe | Maybe | Do not recreate Django auth. |
| CSV/XLSX upload must have `Title`, `Durata Curs`, `Permalink`, optional `investitie`. | `file_handlers.py`, `forms.py` | Attachment plus later parser service. | Maybe | No | No | Yes | File parsing is custom. |
| Upload limits: 20 MB, 5000 rows, 50 columns. | `validators.py` | Attachment limits/config plus later validator. | Maybe | No | No | Yes | Espo metadata alone may not validate file internals. |
| Course duration must be 1-366 days. | `file_handlers.py` | Field validation if duration becomes a field. | Yes | Maybe | Maybe | Maybe | Depends on row entity design. |
| Holidays use DD.MM.YYYY and max 366 dates. | `forms.py`, `validators.py` | Date fields or child records. | Yes | Maybe | Maybe | Maybe | Avoid JSON if native records are practical. |
| Scheduling skips weekends and configured holidays. | `scheduler.py` | Store generated result first; service later if generation is in Espo. | No | No | No | Yes | Algorithmic scheduling needs custom PHP later. |
| Short courses cannot cross work week or month; longer courses can. | `scheduler.py` | Later generation service. | No | No | No | Yes | Core domain algorithm. |
| Randomness controls date spacing/selection. | `services.py` | Later generation service. | Maybe | No | No | Yes | Preserve only if users still need generation inside EspoCRM. |
| Incomplete schedules are not persisted. | `services.py` | Later service transaction/validation. | No | No | No | Yes | Metadata alone is insufficient. |
| Generation history is owner-scoped and expires after 24 hours. | `selectors.py`, `services.py`, management command | ACL plus expiration field/filter; cleanup later. | Yes | Maybe | Maybe | Yes | Scheduled cleanup likely custom. |
| XLSX export neutralizes spreadsheet formulas. | `file_handlers.py` | Later custom export service. | No | No | No | Yes | Important security rule. |
| XML export uses stable MEC/WordPress event contract. | `xml_export.py` | Later export service/action. | No | No | No | Yes | Domain-specific XML format. |
| Word matching uses normalized/fuzzy title scoring thresholds. | `word_matching.py` | Later service/tool if retained. | Maybe | No | No | Yes | Needs PHP library choice or external service decision later. |
| WordPress base URLs must avoid private/metadata destinations. | `validators.py`, `wp_course_updater.py` | Later secured integration service. | No | No | No | Yes | SSRF protection is mandatory if ported. |
| WordPress app password is not stored. | `views.py`, `settings_store.py` | Use EspoCRM credential handling if implemented. | Maybe | No | Maybe | Yes | Do not store secrets casually. |

## 9. Features EspoCRM already provides

- Authentication and sessions.
- Users, teams, roles, and ACL.
- Standard CRUD controllers and record views.
- List, detail, edit, search, filters, and only-my behavior.
- Timestamps, created/modified users, assigned users, teams.
- Imports and standard exports for normal entity data.
- Attachments/Documents mechanisms for files.
- Audit/stream if enabled for the entity.
- Administration UI for extension upload/install.
- Cache rebuild mechanisms after extension installation.

## 10. Items that may require custom PHP later

- Schedule generation from CSV/XLSX because it needs parsing, date availability, randomness, and all-or-nothing persistence.
- XLSX export because the output workbook has a custom shape and formula-neutralization behavior.
- XML export because the MEC/WordPress XML structure is domain-specific.
- DOCX parsing/generation because EspoCRM metadata cannot edit Word table cells.
- Fuzzy Word/course matching because it needs normalization, scoring thresholds, and review payloads.
- WordPress REST integration because it needs credentials, safe URL validation, redirect handling, retries, rate limiting, and ACF payload updates.
- Expired generation cleanup because the Django app deletes records after expiration.
- Source file digesting because SHA-256 generation is not a normal metadata-only field behavior.

## 11. Items that may require custom JavaScript later

- Multi-step upload/generation workflow if standard record layouts are not enough.
- Interactive generated schedule table if relationship panels are not usable enough for wide month matrices.
- Word matching review UI with candidate selection and DOCX download.
- WordPress updater table with per-row fetch/update actions.
- Async XML/DOCX/XLSX download flows if standard buttons/actions cannot provide the workflow.

## 12. Items that should not be converted

- Django authentication, permissions, sessions, admin classes, forms, URLs, views, middleware assumptions, and management command structure.
- Django templates, HTMX snippets, Alpine behavior, DaisyUI/Tailwind classes, and static JavaScript files.
- Django migrations and Python models as PHP classes.
- Pycache files and Django tests as runtime extension files.
- Django-specific saved settings storage if EspoCRM preferences/config can cover it.
- Existing UI styling or custom EspoCRM theme changes.

## 13. Proposed phased implementation plan

- Phase 2: Formalize the existing `Planificari` scaffold entity as the minimal root generation entity with only native/system fields and safe labels.
- Phase 3: Add basic `ScheduleGeneration` metadata fields: year, selected months, holidays, source counts, generated count, source filename, digest, and expiration.
- Phase 4: Add a minimal generated schedule row entity only if JSON schedule rows should become CRM-native records.
- Phase 5: Add the relationship between `Planificari` and generated schedule rows.
- Phase 6: Add list/detail/edit/search layouts for the fields and any relationship panel.
- Phase 7: Polish labels and i18n for Romanian user-facing text.
- Phase 8: Add filters/search for owner, year, source filename, expiration, and active history.
- Phase 9: Add ACL guidance for generator, Word converter, XML export, and updater equivalents using EspoCRM roles.
- Phase 10: Review whether custom PHP is still needed for generation, exports, attachments, cleanup, WordPress integration, and any remaining workflow preferences/configuration.
- Phase 11: Review whether custom JavaScript is still needed for workflows that standard record views cannot support.

## 14. Manual test checklist

- Phase 2: Upload ZIP, install extension, rebuild, confirm the extension appears with the correct name and the `Planificari` tab/entity opens.
- Phase 3: Create/edit a minimal generation record and confirm metadata fields save and display.
- Phase 4: Create a schedule row record manually and confirm required fields behave as expected.
- Phase 5: Link rows to a generation and confirm the relationship panel works.
- Phase 6: Check list/detail/edit/search layouts on desktop and mobile.
- Phase 7: Switch language if applicable and confirm labels are clear and ASCII-safe for technical names.
- Phase 8: Verify only-my, year, expiration, and filename filters.
- Phase 9: Test user with access, user without access, and admin/superuser equivalents.
- Phase 10: If custom PHP is approved later, test generation, exports, expiration cleanup, and attachment handling with small files first.
- Phase 11: If custom JS is approved later, test uploads, async actions, downloads, and failure states.

## 15. Risks and open questions

- The Django app is workflow-heavy; metadata alone will not reproduce generation, DOCX, XML, and WordPress integrations.
- The best EspoCRM storage shape for generated schedule rows is still a product decision: JSON artifact versus child records.
- The current scaffold already includes a generic `Planificari` entity. It should be treated as placeholder-only until Phase 2.
- Django `AppSetting` is not a business entity. Do not model it in Phase 2; revisit preferences/configuration later only if required.
- Source file binary storage should likely use EspoCRM Attachment/Document rather than a custom binary field.
- WordPress credentials and app passwords need a secure EspoCRM-native approach before any updater is ported.
- Expiration and cleanup need confirmation: preserve 24-hour history or use normal CRM retention.
- Current Django UI depends on custom JavaScript and Django templates that should not be copied.

## 16. Do not implement yet

- Do not create real business fields, relationships, layouts, ACL, formulas, dynamic logic, hooks, services, APIs, migrations, custom JavaScript, or custom CSS in Phase 1.
- Do not create `AppSetting`, `PlanificariSettings`, settings fields, custom PHP for settings, custom JavaScript for settings, or Django-style settings storage in Phase 2.
- Do not port Django templates, forms, views, URLs, models, migrations, admin, tests, or static files.
- Do not implement schedule generation, exports, Word matching, WordPress updates, file parsing, cleanup, or attachment handling yet.
- Do not modify live EspoCRM runtime folders or Docker-mounted runtime paths.
