# Custom Workflow Architecture - Planificari

Timestamp: 20260707-212958

## 1. Current Direction

The `Planificari` entity is the workflow record. Users enter the source file,
year, selected months, and holidays directly on a `Planificari` record, then
click a Generate button on that record.

The actual product workflow is:

1. Create or open a `Planificari` record.
2. Upload CSV/XLSX on the record.
3. Choose year.
4. Choose months.
5. Enter holidays.
5. Click Generate.
6. See the generated schedule table.
7. Download XLSX.

This means future phases should use the native record as the input surface and
add custom generation/export behavior only where EspoCRM does not already
provide it.

## 2. Read-Only EspoCRM Evidence

Read-only Docker inspection was used because EspoCRM core PHP files live in the
Docker named volume, not in the host workspace.

No container files were edited.

Confirmed local/core evidence:

- EspoCRM core has API action classes implementing `Espo\Core\Api\Action`.
- Routes can map directly to an `actionClassName`.
- CRM module routes live under `Resources/routes.json`.
- Client metadata supports `detailActionList`, `menu`, handlers, custom views,
  `recordViews`, `relationshipPanels`, and `bottomPanels`.
- `Espo.Ajax.postRequest(...)` is the normal client call style.
- Native file fields are backed by `Attachment` records.
- File bytes can be read server-side with `Espo\Core\FileStorage\Manager` or
  attachment repository helpers.
- `vendor/phpoffice/phpspreadsheet` is already installed in the container.

Key inspected examples:

- `application/Espo/Resources/routes.json`
- `application/Espo/Modules/Crm/Resources/routes.json`
- `application/Espo/Tools/Import/Api/PostFile.php`
- `application/Espo/Tools/EmailTemplate/Api/PostPrepare.php`
- `application/Espo/Modules/Crm/Tools/Campaign/Api/PostGenerateMailMerge.php`
- `application/Espo/Tools/Kanban/Api/PutOrder.php`
- `application/Espo/Tools/Attachment/Service.php`
- `application/Espo/Tools/Attachment/UploadService.php`
- `application/Espo/Repositories/Attachment.php`
- `application/Espo/Core/FileStorage/Manager.php`
- `application/Espo/Tools/Import/Import.php`
- `application/Espo/Tools/Export/Format/Xlsx/PhpSpreadsheetProcessor.php`
- `application/Espo/Resources/metadata/clientDefs/EmailAccount.json`
- `application/Espo/Resources/metadata/clientDefs/Import.json`
- `application/Espo/Resources/metadata/clientDefs/ApiUser.json`

## 3. Recommended User Experience

The `Planificari` detail page is the user-facing generation screen.

Recommended UX:

- Keep the `Planificari` tab.
- Use standard `Planificari` create/edit/detail views for inputs that EspoCRM
  already handles: source file, year, months, holidays, users, and teams.
- Add a custom Generate button on the `Planificari` detail view.
- The Generate button should use the saved values from the current record.
- Show generated schedule results after generation.
- Add Download XLSX after export exists.
- Do not keep `PlanificariRow` as a separate metadata entity unless a later
  persistence/export phase proves that stored child records are required.

## 4. Backend Architecture

Recommended extension paths:

```text
files/custom/Espo/Modules/Planificari/Resources/routes.json
files/custom/Espo/Modules/Planificari/Tools/Planificari/Api/PostGenerate.php
files/custom/Espo/Modules/Planificari/Tools/Planificari/Api/GetExport.php
files/custom/Espo/Modules/Planificari/Tools/Planificari/GenerationService.php
files/custom/Espo/Modules/Planificari/Tools/Planificari/CourseInputParser.php
files/custom/Espo/Modules/Planificari/Tools/Planificari/CourseScheduler.php
files/custom/Espo/Modules/Planificari/Tools/Planificari/XlsxExportService.php
files/client/custom/modules/planificari/src/views/planificari/record/detail.js
```

Recommended routes:

```json
[
    {
        "route": "/Planificari/generate",
        "method": "post",
        "actionClassName": "Espo\\Modules\\Planificari\\Tools\\Planificari\\Api\\PostGenerate"
    },
    {
        "route": "/Planificari/:id/exportXlsx",
        "method": "post",
        "actionClassName": "Espo\\Modules\\Planificari\\Tools\\Planificari\\Api\\PostExportXlsx"
    }
]
```

The Generate action should read the current `Planificari` record values,
generate schedule data, and return table data immediately to the client. A
later phase can decide whether generated rows are stored as JSON, attachments,
or child records.

## 5. Generate API Contract

Preferred request shape:

```json
{
    "name": "June 2026",
    "sourceFileId": "attachment-id",
    "year": "2026",
    "selectedMonths": ["6"],
    "holidays": "22.06.2026"
}
```

Alternative request shape:

```json
{
    "planificariId": "existing-record-id"
}
```

Current first implementation:

- the record detail view sends the current saved record values;
- the server confirms the values were received;
- no parser, scheduler, persistence, generated rows, or export is implemented
  yet.

Recommended response shape:

```json
{
    "id": "planificari-id",
    "rowCount": 10,
    "rows": [
        {
            "courseTitle": "Course title",
            "permalink": "https://example.test/course",
            "durationLabel": "2 zile",
            "investment": "250 euro",
            "month": "6",
            "monthName": "Iunie",
            "dateRange": "01-02.06.2026",
            "sourceRow": 3,
            "originalOrder": 1
        }
    ],
    "downloadUrl": "?entryPoint=download&id=attachment-id"
}
```

The response can omit `downloadUrl` until XLSX export is implemented.

## 6. File Handling

Use EspoCRM native attachments and file storage.

Confirmed core APIs:

- file fields use Attachment records;
- `Espo\Entities\Attachment`;
- `Espo\Core\FileStorage\Manager::getContents($attachment)`;
- `Espo\Repositories\Attachment::getContents($attachment)`;
- `Espo\Repositories\Attachment::getStream($attachment)`.

Recommended approach:

- custom frontend uploads via native Espo attachment/file mechanisms if
  practical;
- Generate API receives an attachment id;
- PHP service loads the `Attachment`;
- PHP service reads bytes with `FileStorage\Manager`;
- service validates extension, size, headers, rows, columns, URLs, and duration.

Do not create a custom binary field.

## 7. Parser And Scheduler

Parser:

- support CSV first;
- support XLSX in the same phase if straightforward, because PhpSpreadsheet is
  already installed;
- CSV delimiter detection should include `@`, `;`, tab, `|`, and comma;
- require `Title`, `Durata Curs`, and `Permalink`;
- allow optional `investitie`;
- enforce 20 MB, 5000 rows, 50 columns, valid HTTP(S) URLs, and duration
  1-366.

Scheduler:

- port the Django scheduling behavior deliberately;
- business days are Monday-Friday minus holidays;
- courses of 5 business days or fewer must stay in the same work week and
  month;
- longer courses can cross week/month;
- incomplete schedules save nothing;
- generated rows sort by `originalOrder`, then `month`.

Open product decision:

- Django has randomness and random seed.
- Current Espo metadata does not.
- Recommended custom-tool default: deterministic output first, then add a
  `randomness` control only if the user confirms it still matters.

## 8. Generated Table UI

The primary generated table should be in the custom tool view.

Suggested columns:

- Title
- Permalink
- Durata Curs
- Investitie
- Month
- Period

Optional table behavior:

- group by month;
- show source row;
- show unscheduled errors by month;
- provide a link to the saved `Planificari` record;
- provide a Download XLSX button after successful generation.

Avoid making the user inspect generated output through raw relationship panels
as the main workflow.

## 9. XLSX Export

Use PhpSpreadsheet, already present in:

```text
vendor/phpoffice/phpspreadsheet
```

Export service should:

- read the generated schedule data from the chosen persistence shape;
- pivot rows into one workbook row per source course;
- create month columns January-December or Romanian labels if desired;
- add a holiday sheet;
- neutralize spreadsheet formulas and dangerous prefixes;
- create an `Attachment` for the workbook;
- return a download attachment id.

## 10. Frontend Architecture

Current custom client file:

```text
files/client/custom/modules/planificari/src/views/planificari/record/detail.js
```

Recommended phased UI approach:

1. Add `Generate` on the `Planificari` detail record view.
2. Use the saved record fields as generation input.
3. Build the generated result table into the record detail experience.

## 11. Revised Implementation Phases

### Phase 4B - Custom Workflow Technical Spike

- Add the smallest safe custom route and API action.
- Return a static JSON response.
- Add the smallest safe client action/view that calls it.
- No parsing/generation yet.

Implementation result:

- Added packaged route `POST /Planificari/generate`.
- Added `PostGenerate` API action that confirms saved record values.
- Added a custom `Planificari` detail view with a Generate button.
- No parser, generator, persistence, file reading, or XLSX export was added.

### Phase 4C - CSV Parser Service

- Implement attachment read.
- Implement CSV parser and validation.
- Return parsed preview rows.
- No schedule generation yet.

### Phase 4D - Scheduler Service

- Implement scheduling algorithm.
- Generate in memory.
- Return generated table.
- Save nothing until all rows are complete.

### Phase 4E - Persistence

- Create/update `Planificari`.
- Store generated schedule data using the chosen persistence shape.
- Show saved generated rows in the custom result table.

### Phase 4F - XLSX Download

- Implement PhpSpreadsheet export.
- Create Attachment.
- Return download link.

### Phase 4G - UX Polish

- Make the tool the natural first screen.
- Add loading/error states.
- Humanize validation messages.
- Keep standard entity views as history/admin fallback.

## 12. Safety Boundaries

Do not:

- edit live runtime folders directly;
- install dependencies;
- edit EspoCRM core files;
- port Django templates or views;
- create Django-style settings storage;
- make users manually create generated rows;
- continue polishing generic Entity Manager layouts as the main UX.

Do:

- package all custom PHP/JS/templates inside the extension ZIP;
- keep `Planificari` as the workflow/input record;
- use native Espo attachments;
- use PhpSpreadsheet already present in the EspoCRM image;
- keep read-only Docker inspection documented when used.
