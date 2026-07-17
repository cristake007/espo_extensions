# Nager.Date Holidays Extension and Integration Implementation Plan

Status: Proposed

Date: 2026-07-17

Primary extension: `NagerDateHolidays` (new)

First consumer: `GeneratorPerioadeCursuri`

Target runtime: EspoCRM `>=10.0.0`, PHP `>=8.4`

## 1. Objective

Create a reusable EspoCRM extension that:

1. exposes Nager.Date configuration at **Administration > Integrations**;
2. synchronizes selected holiday data from Nager.Date into an EspoCRM
   `PublicHoliday` entity;
3. preserves manually maintained company holidays independently from imported
   records;
4. exposes a stable PHP service for `GeneratorPerioadeCursuri` and future
   extensions; and
5. keeps network access, storage, and course-scheduling policy in separate
   components.

The intended data flow is:

```text
Administration > Integrations > Nager.Date
                    |
                    v
          NagerDateSyncService
          /        |          \
 settings   NagerDateClient   sync log
                    |
                    v
          PublicHoliday entity/table
                    |
                    v
       PublicHolidayCalendar service
          /                       \
 GeneratorPerioadeCursuri     future consumers
```

The integration configuration controls synchronization. It does not store
holiday dates, and consumers do not read integration settings or the
`public_holiday` table directly.

## 2. Sources and Current-State Constraints

This plan uses the request dated 2026-07-17 as its functional source because
the repository-designated `agent.md` file was not present when the plan was
written. If `agent.md` is added later and materially conflicts with this plan,
resolve that contradiction before implementation.

Confirmed current state in `GeneratorPerioadeCursuri` version `2.3.4`:

- the extension targets EspoCRM `>=10.0.0` and PHP `>=8.4`;
- `GeneratorPerioadeCursuri.holidays` is a `varchar` field containing manually
  entered `dd.mm.yyyy` values;
- `GenerationService` parses those formatted values and passes them to
  `CourseScheduler`;
- no shared holiday entity, Nager.Date integration, or holiday synchronization
  service exists; and
- the existing manual holiday value is included in generated output and must
  remain backward compatible.

Primary external contracts verified for this plan:

- EspoCRM integration fields are defined under
  `Resources/metadata/integrations/{IntegrationName}.json`, and
  `allowUserAccounts` controls per-user external accounts:
  <https://docs.espocrm.com/development/metadata/integrations/>.
- EspoCRM 10 supports scheduled-job metadata with a job class and the
  `isDefault` installation behavior:
  <https://docs.espocrm.com/development/metadata/app-scheduled-jobs/>.
- Nager.Date exposes
  `GET /api/v4/Holidays/{CountryCode}/{Year}` and returns `date`, `name`,
  `countryCode`, `nationalHoliday`, `subdivisionCodes`, and `holidayTypes`:
  <https://date.nager.at/api>.
- EspoCRM's ORM supports repositories and transactions, which are required for
  scoped atomic replacement:
  <https://docs.espocrm.com/development/orm/>.

The Nager.Date example response currently shows `subdivisionCodes` as `null`
even though the response model describes an array. The client must normalize
`null` to `[]` and reject other invalid shapes.

## 3. Scope

### Included

- a new installable `NagerDateHolidays` EspoCRM extension package;
- system-wide Nager.Date integration settings;
- a dedicated `PublicHoliday` entity and table;
- scheduled, CLI-invokable synchronization;
- stable read methods for holiday consumers;
- English and Romanian labels;
- a compatibility change in `GeneratorPerioadeCursuri` that unions centrally
  stored holidays with its existing manual holiday dates;
- offline contract/unit tests and targeted EspoCRM runtime tests; and
- packaging, upgrade, rollback, and operational instructions.

### Not included

- API keys, OAuth, client secrets, or per-user external accounts;
- an administrator-editable API base URL;
- browser-side calls to Nager.Date;
- storing holiday payloads inside the Integration entity;
- automatic synchronization during a user-facing generation request;
- regional-holiday selection beyond the `national holidays only` policy;
- deleting manual holidays during synchronization;
- replacing EspoCRM's Working Time Calendar feature; or
- redesigning the existing course scheduling algorithm or holiday picker.

## 4. Confirmed Design Decisions

### 4.1 Extension boundary

`NagerDateHolidays` is a separate extension and module. It owns:

- Nager.Date settings;
- external HTTP communication;
- response validation and normalization;
- synchronization policy;
- the `PublicHoliday` schema and persistence rules; and
- the public holiday query service.

`GeneratorPerioadeCursuri` becomes a consumer. It does not gain Nager.Date
HTTP code, Integration entity queries, or direct `PublicHoliday` ORM queries.
This boundary allows other extensions to reuse stored holidays without taking
a dependency on course-generation concepts.

The holiday extension must be installed before a generator release that
requires it. EspoCRM's documented extension manifest does not expose a package
dependency field, so Phase 7 must add an explicit, non-destructive install-time
compatibility check or a clearly documented install order. It must not allow a
missing class to produce an opaque runtime fatal error.

### 4.2 Integration identity and settings

Use one system integration named `NagerDate` with
`allowUserAccounts: false`. The native Integration `enabled` state is the only
enable/disable switch; do not add a second custom `enabled` field.

| Setting | Espo representation | Default | Validation and meaning |
| --- | --- | --- | --- |
| Enabled | Native Integration state | Yes on a fresh install | When off, scheduled and manual jobs make no HTTP requests and perform no writes. An upgrade must never re-enable a previously disabled integration. |
| Country | `varchar` or constrained country field | `RO` | Normalize to uppercase and require exactly two ASCII letters. The request path uses the normalized ISO 3166-1 alpha-2 code. |
| Holiday types | `multiEnum` | `["Public"]` | Allowed values are `Public`, `Bank`, `School`, `Authorities`, `Optional`, and `Observance`. At least one value is required. |
| National holidays only | `bool` | `true` | When true, discard records whose `nationalHoliday` is not `true`. |
| Years to synchronize | `enum` policy | `currentAndNext` | Initial supported policies are `current` and `currentAndNext`; resolve them at the start of a run using the EspoCRM system timezone. |

The country setting may use a custom constrained field view later, but a
country-picker UI is not required for the first release. Server-side
validation is authoritative.

The API base URL is a private constant:

```text
https://date.nager.at/api/v4
```

No setting, request parameter, hook, or consumer may override the host or
scheme.

### 4.3 `PublicHoliday` data contract

The entity represents both synchronized and manually maintained non-working
dates. Its conceptual fields are:

| Field | Espo type | Required | Ownership and rule |
| --- | --- | --- | --- |
| `date` | `date` | Yes | Persist as ISO `YYYY-MM-DD`; never store UI formatting. |
| `countryCode` | `varchar(2)` | Yes | Uppercase ISO country code. |
| `name` | `varchar` | Yes | Nager common English name or manual company label. |
| `holidayTypes` | array-capable field | Yes | Store an array; imported values must use supported Nager types. Manual rows default to `["Public"]`. |
| `nationalHoliday` | `bool` | Yes | Imported value from Nager; manual rows default to `true`. |
| `subdivisionCodes` | array-capable field | Yes | Store an array and normalize Nager `null` to `[]`. |
| `source` | `enum` | Yes | Initial values: `nager-date` and `manual`. |
| `sourceYear` | `int` | Conditional | Required for Nager rows and equal to the year in `date`; null for manual rows. |
| `managed` | `bool` | Yes | `true` only for synchronization-owned rows. |
| `syncedAt` | `datetime` | Conditional | UTC synchronization timestamp for Nager rows; null for manual rows. |

Implementation must verify the EspoCRM 10 array-capable metadata type during
Phase 0. Prefer a native array/multi-enum representation. Do not silently
serialize arrays into comma-separated text. If subdivision codes require a
JSON-backed field, keep serialization inside the entity/repository boundary so
the public service still returns arrays.

Required invariants:

```text
source = nager-date => managed = true, sourceYear != null, syncedAt != null
source = manual     => managed = false, sourceYear = null, syncedAt = null
sourceYear          => sourceYear = year(date)
countryCode         => /^[A-Z]{2}$/
```

Server-side logic owns these invariants. Read-only UI metadata alone is not
sufficient. Ordinary record APIs must not let a user convert a manual row into
a managed row or edit synchronization-owned fields. Imported rows are
viewable but must either be read-only or editable only through the sync
repository. Manual rows remain administrator-maintainable.

Recommended indexes:

- `(countryCode, date, deleted)` for range reads;
- `(source, managed, countryCode, sourceYear, deleted)` for scoped sync; and
- a reviewed natural-key index only after real Nager payloads confirm whether
  multiple records with the same date and name can occur.

Do not add a premature unique constraint that could reject legitimate
same-date holidays. Range-query correctness does not depend on uniqueness
because `getNonWorkingDates` returns distinct dates.

### 4.4 Public consumer service

Expose one injectable interface owned by `NagerDateHolidays`, with inclusive
date boundaries:

```php
interface PublicHolidayCalendar
{
    /** @return PublicHolidayData[] */
    public function getHolidays(
        string $countryCode,
        string $startDate,
        string $endDate
    ): array;

    /** @return string[] ISO dates in ascending order, without duplicates */
    public function getNonWorkingDates(
        string $countryCode,
        string $startDate,
        string $endDate
    ): array;

    public function isNonWorkingDate(string $countryCode, string $date): bool;
}
```

Boundary rules:

- accept and return ISO dates only;
- normalize the country code before querying;
- reject invalid dates and inverted ranges;
- query through an extension-owned repository, never raw SQL in consumers;
- include both active Nager-managed rows and manual rows for the country;
- return deterministic ordering;
- treat multiple holiday records on one date as one non-working date; and
- never read the Nager.Date Integration entity or make an HTTP request.

`getHolidays` returns immutable DTO/value objects, not mutable ORM entities.
This prevents consumers from changing storage-owned records accidentally.

### 4.5 Synchronization semantics

A synchronization run performs these steps:

1. Read the `NagerDate` Integration record once.
2. If disabled, log a skipped result and return without network or database
   side effects.
3. Validate and normalize all settings.
4. Resolve the configured years once for the run.
5. Fetch every year from the fixed HTTPS host using explicit connection and
   response timeouts, a bounded response size, and no redirects to a different
   host.
6. Require a successful HTTP status and valid JSON array.
7. Validate every source row before any replacement starts:
   - ISO date is valid and belongs to the requested year;
   - response country matches the configured country;
   - name is a non-empty bounded string;
   - `nationalHoliday` is boolean;
   - `holidayTypes` contains only supported strings; and
   - `subdivisionCodes` is `null` or an array of bounded strings.
8. Normalize `subdivisionCodes: null` to `[]`.
9. Keep rows whose types intersect the configured holiday types, then apply
   the `national holidays only` filter.
10. Start one database transaction after all requested years have fetched and
    validated successfully.
11. Reconcile each selected country/year scope so its final active Nager-managed
    records exactly match the normalized payload. Update matching rows, create
    new rows, and remove stale managed rows.
12. Commit, then log requested, accepted, created, updated, and removed counts
    plus duration. Do not log full payloads.

The replacement predicate is always equivalent to:

```text
source = nager-date
AND managed = true
AND countryCode = configured country
AND sourceYear IN resolved years
```

Manual records and managed records for other countries or years are outside
the mutation boundary. The transaction rolls back the entire run if any write
fails. A network or validation failure leaves all existing records untouched.

Concurrent sync attempts must be serialized with one extension-owned lock or
job group. A second run should skip or wait safely; it must not interleave
replacement operations.

Disabling the integration stops future synchronization but does not delete
already stored holidays. Consumers continue to use stored data until an
administrator changes or removes it deliberately.

## 5. Proposed Package Structure

The exact class names may be adjusted to match EspoCRM 10 runtime interfaces
during Phase 0, but ownership should remain as follows:

```text
projects/espo_extensions/NagerDateHolidays/
  manifest.json
  files/
    custom/Espo/Modules/NagerDateHolidays/
      Binding.php
      Entities/PublicHoliday.php
      Jobs/SyncNagerDateHolidays.php
      Repositories/PublicHolidayRepository.php
      Resources/
        i18n/{en_US,ro_RO}/...
        layouts/PublicHoliday/{detail,edit,list,search}.json
        metadata/
          aclDefs/PublicHoliday.json
          app/containerServices.json
          app/scheduledJobs.json
          clientDefs/PublicHoliday.json
          entityAcl/PublicHoliday.json
          entityDefs/PublicHoliday.json
          integrations/NagerDate.json
          recordDefs/PublicHoliday.json
          scopes/PublicHoliday.json
        module.json
      Tools/NagerDate/
        NagerDateClient.php
        NagerDateSettings.php
        NagerDateSyncService.php
        NagerHolidayNormalizer.php
      Tools/PublicHoliday/
        PublicHolidayCalendar.php
        PublicHolidayCalendarService.php
        PublicHolidayData.php
  scripts/
  tests/
    offline/
    integration/
```

The HTTP mechanism belongs behind `NagerDateClient`; tests use a fake client.
Do not add a third-party HTTP dependency unless Phase 0 proves EspoCRM/PHP has
no suitable existing mechanism. Any such dependency requires explicit notice
before implementation.

## 6. Phased Implementation

### Phase 0 - Lock Runtime and Package Contracts

#### Goal

Remove framework uncertainty before creating schema or production behavior.

#### Work

1. Scaffold the new extension using the repository's existing extension
   conventions and build script.
2. Confirm against the target EspoCRM `10.x` runtime:
   - the Integration entity read API and enabled/data attributes;
   - metadata defaults and whether fresh-install enablement requires an
     install script;
   - the native array-capable field types and their ORM values;
   - the supported HTTP client/transport available through dependency
     injection;
   - scheduled-job `JobDataLess`, `isDefault`, and job grouping/locking APIs;
   - transaction and deletion behavior for custom entities; and
   - module interface binding and cross-module injection.
3. Capture representative Nager fixtures for:
   - Romanian public national holidays;
   - `subdivisionCodes: null` and non-empty arrays;
   - multiple holiday types;
   - regional holidays;
   - duplicate dates;
   - empty arrays; and
   - malformed/error responses.
4. Add offline contract tests for settings normalization, response validation,
   filtering, and the scoped replacement predicate before implementation.

#### Exit criteria

- All framework-specific choices above are confirmed by a minimal runtime
  probe or official EspoCRM 10 API.
- Nager fixtures contain no live-network dependency.
- The proposed data invariants and service signatures can be represented
  without lossy serialization.
- Any required dependency change has been reported and approved.

### Phase 1 - Add Integration Metadata and Settings Boundary

#### Goal

Provide the small, system-wide Nager.Date configuration page without adding
sync behavior yet.

#### Work

1. Add `NagerDate` integration metadata with `allowUserAccounts: false`.
2. Add country, holiday types, national-only, and year-policy fields with
   defaults from Section 4.2.
3. Add English and Romanian labels, option labels, and concise tooltips.
4. Implement `NagerDateSettings` as the only reader/validator of Integration
   data.
5. If an install hook is required to enable a fresh integration by default,
   make it idempotent and distinguish install from upgrade. Never overwrite
   saved administrator settings.
6. Add tests proving disabled state short-circuits before any client call.

#### Exit criteria

- The integration appears once under Administration > Integrations.
- No User > External Accounts entry is created.
- Invalid server-side settings are rejected with actionable messages.
- The API URL is absent from settings and metadata.
- Reinstall/upgrade does not reset an administrator's saved values.

### Phase 2 - Create the `PublicHoliday` Entity and Manual-Record Policy

#### Goal

Establish reliable holiday storage before connecting it to an external API.

#### Work

1. Add entity, scope, ACL, record, client, layout, and translation metadata.
2. Add the fields, invariants, and indexes from Section 4.3.
3. Add the typed entity/DTO and extension-owned repository.
4. Make technical fields (`source`, `managed`, `sourceYear`, `syncedAt`)
   unavailable for arbitrary client mutation.
5. Default new administrator-created records to `source = manual` and
   `managed = false` on the server.
6. Reject ordinary updates/removals that attempt to mutate managed records,
   while exposing a private repository path for the sync service.
7. Add focused tests proving manual and managed records cannot cross ownership
   states.

#### Exit criteria

- EspoCRM rebuild creates the table and expected indexes.
- Stored `date` values are ISO dates and array fields round-trip as arrays.
- Administrators can create and edit manual holidays.
- Imported-record ownership cannot be forged through the record API.

### Phase 3 - Implement and Harden the Nager.Date Client

#### Goal

Fetch and normalize untrusted API data without database side effects.

#### Work

1. Implement `NagerDateClient` against the fixed base URL.
2. Apply explicit timeouts, response-size bounds, status checks, JSON error
   handling, and redirect restrictions.
3. Encode only validated country and integer year path segments.
4. Implement `NagerHolidayNormalizer` and all row-level validation.
5. Apply type and national-holiday filtering after validation.
6. Translate transport, status, JSON, and schema failures into distinct
   extension exceptions suitable for job logs.
7. Test entirely with fake transport responses and recorded fixtures.

#### Exit criteria

- No caller can influence the scheme, host, port, or base path.
- Invalid or unexpected payloads produce no partial normalized result.
- `null` subdivisions normalize to an empty array.
- Filtering behavior is deterministic and fully covered offline.

### Phase 4 - Implement Atomic, Ownership-Safe Synchronization

#### Goal

Make the selected country/year scopes match validated Nager data without
touching manual records.

#### Work

1. Implement `NagerDateSyncService` using settings, client, normalizer, and
   repository dependencies.
2. Fetch and validate every configured year before starting a transaction.
3. Add a single-run lock and the all-years transaction described in Section
   4.5.
4. Reconcile records using a deterministic comparison key documented by tests;
   do not assume date alone is unique.
5. Set `source`, `managed`, `sourceYear`, and one shared UTC `syncedAt` value
   inside the service; never trust these values from the response.
6. Emit structured summary logs without full response bodies or sensitive
   system data.
7. Add repository tests with manual rows, other countries, other years,
   changed names, duplicate dates, empty filtered results, and forced rollback.

#### Exit criteria

- Repeating the same sync is idempotent at the active-record level.
- Any fetch/validation failure preserves all pre-run records.
- Any write failure rolls back every selected year.
- Manual and out-of-scope records remain byte-for-byte unchanged.
- Concurrent runs cannot create duplicate active datasets.

### Phase 5 - Add Scheduling and Operations

#### Goal

Run synchronization predictably without coupling it to user requests.

#### Work

1. Add a `JobDataLess` scheduled job that delegates only to
   `NagerDateSyncService`.
2. Define one default daily schedule at a low-traffic local time. The exact
   cron expression is an operational choice confirmed during deployment.
3. Ensure the job is available through EspoCRM's standard CLI
   `run-job` mechanism for initial synchronization and diagnosis.
4. Ensure disabled integrations are recorded as skipped, not failed.
5. Log start, outcome, country, years, counts, duration, and a safe categorized
   failure message.
6. Document that EspoCRM cron/daemon processing must already be configured;
   the extension does not create host cron or systemd configuration.

#### Exit criteria

- Installation or `populate-scheduled-jobs` creates no duplicate scheduled
  job.
- A CLI job run and a scheduled run execute the same service path.
- A failed upstream call is visible in EspoCRM job/log diagnostics and does
  not erase stored data.
- No infrastructure is created or modified by the extension package.

### Phase 6 - Expose the Stable Holiday Calendar Service

#### Goal

Give consumers a storage-independent API for holiday queries.

#### Work

1. Implement `PublicHolidayCalendar` and bind it to
   `PublicHolidayCalendarService` through EspoCRM module DI.
2. Implement all three methods from Section 4.4 through the repository.
3. Validate inputs at the service boundary.
4. Deduplicate and sort non-working dates in the service, not in each
   consumer.
5. Add query-count assertions so range retrieval does not degrade into one
   query per date.
6. Add tests covering inclusive bounds, leap day, invalid ranges, country
   normalization, duplicate-date records, manual records, managed records, and
   soft-deleted records.

#### Exit criteria

- Consumers require only the public interface and DTOs.
- The service makes no network calls and reads no integration settings.
- Results are deterministic ISO values for all supported database engines.

### Phase 7 - Integrate `GeneratorPerioadeCursuri`

#### Goal

Use centrally stored non-working dates during course generation without
breaking existing records or manual overrides.

#### Work

1. Add an explicit `countryCode` business input to
   `GeneratorPerioadeCursuri`, defaulting to `RO`, or lock the value to `RO`
   only if the functional source confirms the generator will remain
   Romania-only. The generator must not derive the country from integration
   settings.
2. Inject `PublicHolidayCalendar` into `GenerationService`.
3. For the selected year/month range, call
   `getNonWorkingDates(countryCode, startDate, endDate)` once.
4. Parse the existing `holidays` field exactly as today and union those
   per-record manual dates with the centrally stored dates.
5. Convert ISO dates to the scheduler's current `dd.mm.yyyy` representation
   only at this in-memory adapter boundary. Do not persist formatted values in
   `PublicHoliday`.
6. Deduplicate and sort the effective holiday list before constructing
   `CourseScheduler` and before exporting the holidays sheet.
7. Make generation purely local: missing/stale central data must never trigger
   a Nager request. Define the user-visible result for zero stored holidays as
   a non-blocking warning while preserving manual-only generation.
8. Keep already generated records immutable under the existing `generatedAt`
   rule. A later holiday sync must not rewrite prior files or generation
   results.
9. Add the extension-presence/install-order guard described in Section 4.1.

#### Exit criteria

- Existing records containing only manual `holidays` still generate the same
  schedule.
- Central and per-record dates are both excluded, with duplicates removed.
- Generation performs no HTTP request.
- Updating central holidays affects only future generation attempts.
- Missing extension/data behavior is explicit and tested, not a fatal class
  resolution error.

### Phase 8 - Integration Tests, Packaging, and Upgrade Safety

#### Goal

Prove framework wiring and deliver installable packages without relying on the
live Nager service in automated tests.

#### Work

1. Add an offline safe suite for metadata, fixtures, normalizers, sync policy,
   ownership rules, and generator adaptation.
2. Add a targeted EspoCRM runtime suite that verifies:
   - DI resolves the client, sync service, repository, and calendar service;
   - Integration settings can be read in their saved shape;
   - entity schema and array fields round-trip correctly;
   - a fake-client sync commits and rolls back correctly;
   - the scheduled job delegates correctly; and
   - the generator consumes the holiday interface.
3. Keep live Nager smoke testing manual and optional so CI is deterministic.
4. Build and inspect both ZIP packages. Confirm manifests, namespaces, module
   order, required files, and no development fixtures in production paths.
5. Test fresh installation and upgrade from `GeneratorPerioadeCursuri 2.3.4`.
6. Verify uninstall scripts do not delete `PublicHoliday` data. Data removal,
   if ever needed, is a separate explicit administrator operation.

#### Exit criteria

- Offline tests pass without network, credentials, containers, or an EspoCRM
  database.
- Targeted runtime tests pass in the supported EspoCRM environment.
- Both packages build reproducibly and contain only declared files.
- Upgrade preserves existing generator records and manual holiday values.
- No install/upgrade script overwrites administrator settings or holiday data.

### Phase 9 - Deployment and Acceptance

#### Goal

Introduce synchronization before switching course generation to the shared
calendar.

#### Rollout order

1. Back up the EspoCRM database and application files.
2. Install `NagerDateHolidays`.
3. Run EspoCRM rebuild/cache refresh and inspect the `PublicHoliday` schema.
4. Open Administration > Integrations > Nager.Date and confirm settings.
5. Populate/confirm the scheduled job.
6. Run one manual CLI synchronization and inspect Romanian current/next-year
   counts and representative dates.
7. Create one manual company holiday and repeat synchronization to prove it is
   preserved.
8. Install the compatible `GeneratorPerioadeCursuri` consumer release.
9. Generate a test schedule containing one Nager holiday, one manual global
   holiday, and one record-specific holiday.
10. Confirm all three dates are excluded and appear once in the export holiday
    sheet.
11. Disable the integration, run the job, and confirm no HTTP request or
    database mutation occurs while stored holidays remain queryable.

#### Acceptance criteria

- Administration exposes only the intended settings and no credential fields.
- Current and next year Romanian public national holidays synchronize.
- Manual global holidays survive every synchronization.
- Other country/year managed records are untouched by a scoped run.
- A malformed or unavailable upstream response cannot empty existing data.
- `GeneratorPerioadeCursuri` uses the service contract and never queries the
  integration or holiday table directly.
- The scheduled job and operational logs provide enough information to detect
  stale or failed synchronization.

## 7. Test Matrix

| Area | Required cases |
| --- | --- |
| Settings | defaults, lowercase country normalization, invalid country, empty types, unsupported type, disabled short-circuit, upgrade preserves saved values |
| HTTP | 200 valid, non-2xx, timeout, oversized body, invalid JSON, redirect, wrong country, wrong year, invalid date, invalid arrays |
| Filtering | public vs bank/school/observance, multiple types, national-only on/off, regional subdivisions, empty filtered result |
| Storage | date/array round-trip, manual defaults, managed invariant enforcement, indexes, soft-deleted rows excluded |
| Sync | first import, identical rerun, changed record, removed record, duplicate date, two years, other country/year preserved, manual preserved, rollback, concurrent run |
| Calendar service | inclusive range, leap day, invalid range, normalized country, sorted distinct dates, DTO immutability, no network |
| Generator | manual-only backward compatibility, central-only, union/deduplication, selected range, zero central data warning, no HTTP, immutable prior generation |
| Packaging | fresh install, upgrade, reinstall, disabled state preserved, scheduled-job uniqueness, ZIP contents |

## 8. Failure and Recovery Policy

| Failure | Required behavior | Recovery |
| --- | --- | --- |
| Integration disabled | Skip before client creation/use; no writes | Enable and run the standard job |
| Invalid settings | Fail with field-specific message; preserve data | Correct settings and rerun |
| DNS/TLS/timeout/non-2xx | Fail run; preserve data | Rerun after upstream/network recovery |
| Invalid JSON/schema | Fail all configured years; preserve data | Inspect safe log category and payload fixture, then rerun |
| Database write failure | Roll back all configured years | Correct database issue and rerun |
| Concurrent run | One run proceeds; the other safely skips/waits | No data repair should be required |
| Generator has no central rows | Continue with record-specific manual dates and warning | Run sync or add manual global records |
| Extension rollback | Stop job and restore prior extension files; retain table/data | Reinstall compatible version and rebuild |

## 9. Security and Operational Requirements

- Use HTTPS and a fixed Nager.Date origin to eliminate administrator-created
  SSRF targets.
- Validate all settings and response values on the server.
- Bound time, response size, array counts, and string lengths.
- Do not follow redirects to another origin.
- Restrict integration configuration and manual `PublicHoliday` maintenance to
  administrators or an explicitly privileged role.
- Do not expose sync-only fields for mass update, import, or ordinary record
  mutation.
- Do not log full upstream payloads, internal stack traces to end users, or
  unrelated Integration entity data.
- Use a transaction and single-run lock for visible data changes.
- Do not create system cron, daemon, containers, databases, or services from
  install scripts.
- Treat freshness as operational state: monitor the scheduled-job result and
  latest `syncedAt` values rather than making user requests wait on the
  upstream API.

## 10. Open Decisions Before Phase 7

Only one functional decision remains material to the generator consumer:

- **Country ownership:** add a visible `countryCode` field to each generator
  record, or confirm the generator is permanently Romania-only and pass `RO`
  as its documented domain constant.

The recommended choice is a record field defaulting to `RO`, because it keeps
the consumer's query explicit and avoids reading connection settings. This
decision does not block Phases 0 through 6.

The exact daily cron time is a deployment choice and does not affect the
software design.
