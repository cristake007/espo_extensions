# Field Behavior Map - Planificari perioade cursuri

Timestamp: 20260707-195255

This document classifies current and planned `Planificari` fields by how users and later generation logic should treat them. It is based on direct inspection of:

- `app_to_convert/planificator/models.py`
- `app_to_convert/planificator/forms.py`
- `app_to_convert/planificator/file_handlers.py`
- `app_to_convert/planificator/services.py`
- `extensions/Planificari/docs/conversion-map.md`
- current `entityDefs/Planificari.json`
- current `layouts/Planificari/*.json`
- current i18n files

No custom PHP, JavaScript, CSS, relationships, schedule rows, settings entity, or upload field is implemented in this correction phase.

Phase 3B investigation result: the uploaded source course list should be represented by an EspoCRM-native file/attachment mechanism, but no clear local metadata example or installed core metadata shape for a native file/attachment field was found in this server tree. The field is therefore not implemented yet.

## 1. User-Entered Fields Before Generation

| Field name | Source Django field or workflow source | EspoCRM field type currently used or proposed | Edit layout | Detail layout | List layout | Search layout | Read-only | Later custom PHP/service logic | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `name` | EspoCRM root record label | `varchar` | Yes | Yes | Yes | Yes | No | No | Basic record name for manually identifying a generation record. |
| `description` | EspoCRM basic note/description | `text` | Yes | Yes | No | No | No | No | Optional manual context. Included in text search metadata, but not a search-layout field. |
| `year` | `ScheduleGeneratorForm.year`; `ScheduleGeneration.year` | `int` | Yes | Yes | Yes | Yes | No | Later yes | User selects the generation year before generation. Bounds/advanced validation can be refined later. |
| `assignedUsers` | EspoCRM ownership/assignment scaffold | `linkMultiple` to `User` | Yes | Yes | Yes | Yes | No | No | Existing scaffold assignment model. |
| `teams` | EspoCRM teams scaffold | `linkMultiple` to `Team` | Yes | Yes | Yes | Yes | No | No | Supports EspoCRM ACL/team behavior. |

## 2. Upload/Input Fields

| Field name | Source Django field or workflow source | EspoCRM field type currently used or proposed | Edit layout | Detail layout | List layout | Search layout | Read-only | Later custom PHP/service logic | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `sourceFile` | `ScheduleGeneratorForm.input_file`; `read_input_file`; `source_file_data` | Proposed native file/attachment mechanism, not implemented | No | Later | No | No | No | Yes | The Django app accepts `.csv` and `.xlsx` uploads, validates size/content, and stores bytes. Do not use a custom binary field. Phase 3B found no clear local metadata example or installed core metadata shape for native file/attachment fields, so implementation is deferred until the correct EspoCRM metadata shape is confirmed. |

### Missing Evidence For Upload Field Implementation

- No local `entityDefs` example with a `file`, `attachment`, or `attachmentMultiple` field type was found.
- No local metadata example linking an entity field directly to `Attachment` or `Document` for upload storage was found.
- The available extension scaffold only demonstrates scalar fields and user/team links.
- The live/custom CSS contains attachment UI selectors, but CSS selectors are not sufficient evidence for safe entity metadata.

Recommended next step: confirm the native EspoCRM field metadata shape from a trusted EspoCRM source or an installed module example before adding `sourceFile`.

## 3. Derived Fields Calculated From Upload Or Generation

| Field name | Source Django field or workflow source | EspoCRM field type currently used or proposed | Edit layout | Detail layout | List layout | Search layout | Read-only | Later custom PHP/service logic | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `sourceCourseCount` | `ScheduleGeneration.source_course_count`; `len(result.source_courses)` in `services.py` | `int` | No | Yes | No | No | Yes | Yes | Derived from parsed uploaded courses. Removed from edit layout. |
| `generatedEntryCount` | `ScheduleGeneration.generated_entry_count`; `len(result.schedule)` in `services.py` | `int` | No | Yes | No | No | Yes | Yes | Derived from generated schedule rows. Removed from edit layout. |
| `sourceFileName` | `ScheduleGeneration.source_file_name`; upload name normalized in `services.py` | `varchar` | No | Yes | Yes | Yes | Yes | Yes | Derived from uploaded/reused file metadata. Removed from edit layout. |
| `sourceFileDigest` | `ScheduleGeneration.source_file_digest`; SHA-256 of uploaded bytes in `services.py` | `varchar` | No | Yes | No | No | Yes | Yes | Derived from file bytes. Included in full text search metadata, but not as a search-layout field. Removed from edit layout. |
| `expiresAt` | `ScheduleGeneration.expires_at`; `now + 24 hours` in `services.py` | `datetime` | No | Yes | Yes | Yes | Yes | Yes | Derived expiration timestamp. Removed from edit layout. Cleanup behavior remains future work. |

## 4. System/Native EspoCRM Fields

| Field name | Source Django field or workflow source | EspoCRM field type currently used or proposed | Edit layout | Detail layout | List layout | Search layout | Read-only | Later custom PHP/service logic | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `id` | `ScheduleGeneration.id`; EspoCRM native record id | Native EspoCRM id | No | No | No | No | Yes | No | Use native EspoCRM record ID. |
| `createdAt` | `ScheduleGeneration.created_at`; EspoCRM native timestamp | `datetime` | No | Yes | Yes | No | Yes | No | Existing read-only system field. |
| `modifiedAt` | EspoCRM native timestamp | `datetime` | No | Yes | No | No | Yes | No | Existing read-only system field. |
| `createdBy` | EspoCRM native user link | `link` to `User` | No | Yes | No | No | Yes | No | Existing read-only system field. |
| `modifiedBy` | EspoCRM native user link | `link` to `User` | No | Yes | No | No | Yes | No | Existing read-only system field. |
| `assignedUser` | EspoCRM native/supported field, disabled in this scaffold | `link` to `User`, disabled | No | No | No | No | Yes | No | Kept disabled; this scaffold uses `assignedUsers`. |

## 5. Future Fields Not Implemented Yet

| Field name | Source Django field or workflow source | EspoCRM field type currently used or proposed | Edit layout | Detail layout | List layout | Search layout | Read-only | Later custom PHP/service logic | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `selectedMonths` | `ScheduleGeneration.selected_months`; `ScheduleGeneratorForm.months` | Proposed `multiEnum` or JSON | Later | Later | Later | Later | No | Maybe | Not implemented in this correction. User-entered before generation. |
| `holidays` | `ScheduleGeneration.holidays`; `ScheduleGeneratorForm.holidays` | Proposed array/JSON or related date records | Later | Later | Later | Later | No | Maybe | Not implemented in this correction. User-entered before generation. |
| `randomSeed` | `ScheduleGeneration.random_seed`; generation service seed | Proposed bigint/varchar/int-compatible type | No | Later | No | No | Yes | Yes | Not implemented. Derived or service-controlled if reproducibility remains required. |
| `sourceFileData` | `ScheduleGeneration.source_file_data` | Do not implement as binary; use native attachment/file mechanism | No | No | No | No | Yes | Yes | Django stores raw bytes; EspoCRM should use native attachments/documents instead. |
| Generated schedule rows | `ScheduleGeneration.schedule`; generated JSON rows | Child entity `PlanificariRow` | Manual only until service exists | Yes | Yes | Yes | Yes | Yes | Implemented in Phase 3E as metadata-only normalized rows, one record per course/month generated entry. No automatic generation exists yet. |
| `AppSetting` / settings fields | `AppSetting`; `settings_store.py` | Not an entity | No | No | No | No | N/A | Maybe | Treat as workflow preferences/configuration only. Do not create settings entity or fields. |
