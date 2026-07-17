# Zile Sărbătoare Extension – Implementation Plan

Status: Delivered — pending non-administrator runtime validation

Date: 2026-07-17

Delivery date: 2026-07-18

Delivery note: Administrator installation, synchronization, manual holiday, and
calendar workflows are delivered. Runtime verification with non-administrator
users remains deferred until further testing; the related ACL cases are not yet
marked as verified.

Extension label: **Zile Sărbătoare**

Technical module identifier: `ZileSarbatoare`

Entity label: **Zile libere**

Technical entity identifier: `ZileLibere`

External data source: **Nager.Date**

Target runtime: EspoCRM `>=10.0.0`, PHP `>=8.4`

## 1. Objective

Create one installable EspoCRM extension that provides a central and reusable
calendar of non-working days.

The extension must:

1. synchronize public holidays from Nager.Date;
2. allow an administrator to configure automatic synchronization frequency;
3. provide a **Synchronize now** button;
4. allow administrators to create, edit, and remove manual non-working days;
5. display all active non-working days automatically in the EspoCRM calendar;
6. store the records in the `ZileLibere` entity/table;
7. expose a stable PHP service for retrieving records by year and selected
   months; and
8. protect manual records and existing valid data from failed synchronization.

No named consumer module is part of this implementation plan. The extension is
complete and useful on its own.

## 2. Naming Contract

The following names are mandatory and must be used consistently:

| Purpose | Name |
| --- | --- |
| Extension shown in EspoCRM | `Zile Sărbătoare` |
| Module/package identifier | `ZileSarbatoare` |
| Entity shown in EspoCRM | `Zile libere` |
| Entity identifier | `ZileLibere` |
| Database table | EspoCRM-generated table for `ZileLibere`; expected physical name must be confirmed during Phase 0 |
| Integration identifier | `NagerDate` |
| External provider | `Nager.Date` |
| Public query interface | `ZileLibereCalendar` |

No alternative English entity or package name is permitted.

## 3. Intended Architecture

```text
Administration > Integrations > Nager.Date
                     |
         settings + Synchronize now
                     |
                     v
           NagerDateSyncService
            /        |        \
    scheduler   NagerDateClient  status/logging
                     |
                     v
              ZileLibere entity
              /              \
      EspoCRM Calendar    ZileLibereCalendar
                              |
                   year + selected months
```

Responsibilities are separated as follows:

- `NagerDateClient` performs only external HTTP communication;
- `NagerDateNormalizer` validates and normalizes source data;
- `NagerDateSyncService` owns synchronization and replacement rules;
- `ZileLibereRepository` owns storage queries and synchronization writes;
- `ZileLibereCalendar` exposes stable read methods;
- the calendar presentation reads stored records only;
- no user-facing request performs a live Nager.Date call except the explicit
  **Synchronize now** action.

## 4. Scope

### 4.1 Included

- installable `ZileSarbatoare` package;
- system-wide Nager.Date integration settings;
- configurable automatic synchronization;
- manual synchronization button;
- `ZileLibere` entity, list, detail, edit, and search layouts;
- manual non-working-day management;
- automatic one-day calendar display;
- read-only protection for synchronized records;
- reusable month-selection query service;
- synchronization status and safe operational logs;
- Romanian and English translations;
- offline tests, focused EspoCRM runtime tests, and ZIP packaging.

### 4.2 Not included

- API keys, OAuth, or user external accounts;
- browser-side calls directly to Nager.Date;
- administrator-editable Nager.Date host or base URL;
- one database record per user;
- automatic creation of host cron, systemd services, Docker containers, or
  databases;
- replacing EspoCRM Working Time Calendar;
- automatically blocking meetings or resources;
- implementing business rules belonging to a consuming module;
- direct SQL access as the supported integration contract.

## 5. Administration and Synchronization Settings

Use one system integration named `NagerDate` with
`allowUserAccounts: false`.

The integration page must contain the following settings:

| Setting | Default | Meaning |
| --- | --- | --- |
| Enabled | Enabled on fresh install | Master switch for manual and automatic synchronization |
| Country | `RO` | ISO 3166-1 alpha-2 country code, normalized to uppercase |
| Years to synchronize | Current and next year | Years requested from Nager.Date during each run |
| Holiday types | `Public` | Accepted Nager.Date holiday types |
| National holidays only | `true` | Exclude regional records when enabled |
| Automatic synchronization | Enabled | Whether the scheduler may start synchronization |
| Frequency | Weekly | `Daily`, `Weekly`, `Monthly`, or `Manual only` |
| Time of day | `03:00` | Local EspoCRM system time for an automatic run |
| Day of week | Monday | Used only for weekly frequency |
| Day of month | `1` | Used only for monthly frequency |

The same page must show read-only operational information:

- last attempted synchronization time;
- last successful synchronization time;
- last result: success, skipped, or failed;
- requested years;
- accepted, created, updated, and removed record counts;
- short safe error message when the last run failed;
- calculated next automatic synchronization time.

### 5.1 Frequency implementation rule

The extension must own one standard EspoCRM scheduled job. The job may run at a
small fixed polling interval, but it must execute the external synchronization
only when the saved schedule is due.

This design avoids rewriting scheduled-job records every time the administrator
changes the frequency.

Required behavior:

- `Manual only` never performs an automatic HTTP request;
- disabling the integration prevents manual and automatic synchronization;
- disabling only automatic synchronization keeps the manual button available;
- a failed run does not immediately retry in a tight loop;
- the next due time is calculated in the EspoCRM system timezone;
- schedule calculations are deterministic and unit tested;
- upgrades never overwrite saved administrator settings.

## 6. Manual Synchronization

The Nager.Date integration page must contain a primary action:

**Synchronize now**

The button must:

1. require administrator permission;
2. prevent a second concurrent synchronization;
3. show that synchronization is in progress;
4. call a server-side action;
5. execute the same service used by automatic synchronization;
6. return a concise result summary;
7. refresh last-sync information after completion; and
8. never expose the full upstream response to the browser.

A manual run must not create a different synchronization path or different
replacement rules.

## 7. `ZileLibere` Entity Contract

`ZileLibere` represents both synchronized legal holidays and manually entered
non-working days.

### 7.1 Calendar-compatible entity

The entity must be compatible with the EspoCRM calendar and displayed as a
one-day, all-day event.

The implementation must use EspoCRM's supported calendar/event metadata rather
than copying each record into another entity.

A single `ZileLibere` record must correspond to a single calendar item. Per-user
record duplication is forbidden.

Phase 0 must confirm the exact EspoCRM 10 event-template fields and the supported
way to make the entity visible automatically in Calendar for every user who has
read access. If the default Assigned User behavior prevents global visibility,
the implementation must add a module-owned calendar collection mechanism or
another supported framework-level solution. It must not assign every holiday to
every user.

### 7.2 Conceptual fields

| Field | Type | Required | Rule |
| --- | --- | --- | --- |
| `name` | varchar | Yes | Holiday or company non-working-day name |
| canonical calendar date | Event all-day date field | Yes | Stored once as an ISO date; exact field confirmed in Phase 0 |
| `countryCode` | varchar(2) | Yes | Uppercase country code; default `RO` for manual records |
| `source` | enum | Yes | `nager-date` or `manual` |
| `managed` | bool | Yes | `true` only for synchronized records |
| `sourceYear` | int | Conditional | Required for synchronized records and equal to the date year |
| `holidayTypes` | array-capable field | Yes | Nager.Date values; manual default `Public` |
| `nationalHoliday` | bool | Yes | Nager value; manual default `true` |
| `subdivisionCodes` | array-capable field | Yes | Normalize upstream `null` to an empty array |
| `syncedAt` | datetime | Conditional | UTC time of the successful synchronization |
| `description` | text | No | Optional administrator note for manual records |
| standard Espo fields | framework fields | As generated | Created/modified metadata and soft-delete state |

Do not add stored `year` and `month` columns only to support filtering. They are
derived from the canonical date, and duplicating them would create consistency
risks.

### 7.3 Required invariants

```text
source = nager-date => managed = true
source = nager-date => sourceYear != null
source = nager-date => syncedAt != null
source = manual     => managed = false
source = manual     => sourceYear = null
source = manual     => syncedAt = null
sourceYear          => sourceYear = year(canonical date)
countryCode         => /^[A-Z]{2}$/
```

These rules must be enforced on the server. Read-only front-end fields are not
enough.

### 7.4 Indexes

At minimum, create and verify indexes equivalent to:

```text
(countryCode, canonicalDate, deleted)
(source, managed, countryCode, sourceYear, deleted)
```

Do not create a unique constraint on the date alone. More than one named record
may exist on the same date.

## 8. Manual Non-Working Days

Administrators must be able to manage manual records from the **Zile libere**
list and record views. Installation must expose the entity in navigation and
Quick Create without replacing existing administrator configuration.

### 8.1 Allowed actions

An administrator may:

- create a manual record;
- set its name, date, country, and optional description;
- edit a manual record;
- remove a manual record;
- filter the list by date, year, month, country, and source;
- distinguish manual and synchronized records visually.

### 8.2 Managed-record protection

Synchronized records must be visible but protected:

- ordinary edit must not change their synchronization-owned fields;
- ordinary delete must not remove them;
- mass update, import, and API payloads must not convert ownership;
- only the private synchronization repository path may create, update, or
  remove managed records.

Manual records must never be deleted or overwritten by synchronization.

### 8.3 Duplicate-date behavior

Multiple records may exist on one date, for example a legal holiday and a
company-specific closure.

The list view shows every record. Date-only query methods return each date once.

## 9. Calendar Behavior

All active `ZileLibere` records must appear automatically in the EspoCRM
calendar.

Required presentation:

- all-day event;
- exactly one calendar day;
- title taken from `name`;
- no meeting invitation or attendee workflow;
- no reminders by default;
- no resource or user blocking behavior;
- synchronized and manual records both visible;
- soft-deleted records excluded;
- no duplicate calendar events generated by synchronization reruns.

The extension installation must register `ZileLibere` for calendar display
without requiring the administrator to manually create the entity. Any
modification to existing Activities/Calendar configuration must be additive and
must preserve existing settings.

The Calendar quick-detail modal for this global read-only event type must not
offer a Full Form action.

Acceptance requires verification with:

- an administrator;
- a non-administrator who has read access;
- a non-administrator who has no access;
- one synchronized record;
- one manual record;
- two named records on the same date.

## 10. Nager.Date Client

The Nager.Date origin is a private constant:

```text
https://date.nager.at/api/v3
```

The client requests:

```text
GET /PublicHolidays/{Year}/{CountryCode}
```

The client must:

- use HTTPS;
- validate country and year before building the path;
- apply connection and response timeouts;
- enforce a bounded response size;
- require a successful HTTP status;
- reject invalid JSON;
- reject redirects to a different origin;
- return no partial normalized result when any row is invalid;
- distinguish transport, status, JSON, and schema failures;
- avoid logging complete response payloads.

The source response fields currently expected are:

```text
date
localName
name
countryCode
global
counties
types
```

The normalizer must store `localName`, map `global`, `counties`, and `types` to
the extension domain fields, and convert `counties: null` to `[]`.
Other invalid shapes must be rejected.

## 11. Synchronization Semantics

A synchronization run must execute the following sequence:

1. Acquire the extension-owned synchronization lock.
2. Read settings once.
3. Stop safely when the integration is disabled.
4. Validate and normalize all settings.
5. Resolve the configured years once for the run.
6. Fetch every requested year.
7. Validate every response and every row.
8. Normalize all valid rows.
9. Apply configured holiday-type and national-only filters.
10. Begin one database transaction only after every requested year has been
    fetched and validated successfully.
11. Reconcile only managed Nager.Date records for the selected country and
    requested years.
12. Create new records, update changed records, and remove stale managed
    records.
13. Leave manual records and all out-of-scope managed records untouched.
14. Commit the transaction.
15. Update synchronization status and write a concise structured log.
16. Release the lock.

The write boundary is equivalent to:

```text
source = nager-date
AND managed = true
AND countryCode = configured country
AND sourceYear IN requested years
```

### 11.1 Failure safety

- any network failure preserves existing records;
- any validation failure preserves existing records;
- any database write failure rolls back the full run;
- a failed next-year response must not partially replace the current year;
- an empty but valid filtered result may remove managed records only after the
  complete payload has passed validation;
- disabling synchronization does not remove stored records;
- repeated identical synchronization is idempotent.

### 11.2 Concurrency

Only one synchronization may run at a time.

A second scheduled or manual attempt must return a safe `already running`
result or skip without performing writes.

## 12. Public Month-Selection Service

The extension must expose one injectable, documented PHP interface named
`ZileLibereCalendar`.

The core contract is:

```php
interface ZileLibereCalendar
{
    /** @return ZileLibereData[] */
    public function getZileLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO'
    ): array;

    /** @return string[] ISO dates, sorted and unique */
    public function getDateLiberePentruLuni(
        int $year,
        array $months,
        string $countryCode = 'RO'
    ): array;

    public function esteZiLibera(
        string $date,
        string $countryCode = 'RO'
    ): bool;
}
```

### 12.1 Input contract

For month-based methods:

- `year` is mandatory;
- `months` is mandatory;
- accepted month values are integers `1` through `12`;
- duplicate months are removed;
- months are normalized into ascending order;
- non-consecutive months are supported;
- an empty selection is rejected with an actionable validation error;
- invalid month values are rejected rather than ignored;
- country code is normalized to uppercase.

Example:

```php
$records = $calendar->getZileLiberePentruLuni(
    2026,
    [2, 3, 7],
    'RO'
);
```

The result must contain only records whose canonical date is in February,
March, or July 2026.

### 12.2 Result contract

`getZileLiberePentruLuni` returns immutable data objects equivalent to:

```php
[
    new ZileLibereData(
        id: '...',
        date: '2026-02-24',
        name: 'Zi liberă companie',
        countryCode: 'RO',
        source: 'manual'
    ),
]
```

`getDateLiberePentruLuni` returns:

```php
[
    '2026-02-24',
    '2026-03-01',
    '2026-07-15',
]
```

Result requirements:

- ISO `YYYY-MM-DD` dates;
- selected months only;
- selected year only;
- selected country only;
- deterministic ascending order;
- soft-deleted records excluded;
- manual and synchronized records included;
- duplicate records on one date collapsed only in the date-only method;
- no ORM entities returned to callers;
- no HTTP request and no Integration settings read.

### 12.3 Query implementation

The public service must query through `ZileLibereRepository`.

It may use one indexed query covering the minimum and maximum selected month and
then apply a strict selected-month filter in memory, or one ORM query containing
multiple month ranges. The implementation must avoid one query per day and must
prove that unselected months are never returned.

The documented and supported contract is the PHP service, not direct SQL.
The database table remains available to EspoCRM ORM and administrative views,
but its physical schema is an implementation detail.

## 13. Proposed Package Structure

Exact framework class names may be adjusted after Phase 0, but ownership should
remain equivalent to:

```text
ZileSarbatoare/
  manifest.json
  files/
    custom/Espo/Modules/ZileSarbatoare/
      Binding.php
      Entities/ZileLibere.php
      Repositories/ZileLibereRepository.php
      Jobs/SyncZileLibere.php
      Controllers/NagerDate.php
      Resources/
        i18n/en_US/...
        i18n/ro_RO/...
        layouts/ZileLibere/
          detail.json
          edit.json
          list.json
          search.json
        metadata/
          aclDefs/ZileLibere.json
          app/containerServices.json
          app/scheduledJobs.json
          clientDefs/ZileLibere.json
          entityAcl/ZileLibere.json
          entityDefs/ZileLibere.json
          integrations/NagerDate.json
          recordDefs/ZileLibere.json
          scopes/ZileLibere.json
        module.json
      Tools/NagerDate/
        NagerDateClient.php
        NagerDateNormalizer.php
        NagerDateSettings.php
        NagerDateSchedule.php
        NagerDateSyncService.php
        SyncResult.php
      Tools/ZileLibere/
        ZileLibereCalendar.php
        ZileLibereCalendarService.php
        ZileLibereData.php
  scripts/
  tests/
    offline/
    integration/
```

A custom integration front-end view may be added for the synchronization button
and status panel.

## 14. Phased Implementation

### Phase 0 – Confirm EspoCRM Runtime Contracts

#### Goal

Remove framework uncertainty before production behavior is implemented.

#### Work

1. Scaffold the extension using repository conventions.
2. Confirm the exact EspoCRM 10 Event template metadata and all-day date fields.
3. Confirm how `calendar`, `calendarOneDay`, Activities settings, and ACL affect
   visibility for global unassigned records.
4. Prove a single test `ZileLibere` record can appear once in Calendar without
   per-user duplication.
5. Confirm Integration metadata, custom integration views, server actions, and
   enabled-state access.
6. Confirm scheduled-job registration, CLI execution, and `isDefault` behavior.
7. Confirm module DI/interface binding.
8. Confirm ORM transactions, repository customization, array-capable fields,
   soft deletion, and index metadata.
9. Capture representative Nager.Date fixtures without making tests depend on
   live network availability.

#### Exit criteria

- the entity can be represented without duplicate date storage;
- global calendar visibility has a proven supported implementation;
- the physical table name and indexes are known;
- every framework-specific assumption is documented;
- no unresolved runtime uncertainty blocks schema creation.

### Phase 1 – Create `ZileLibere` Storage and Administration UI

#### Goal

Deliver manual records and the central table before external synchronization.

#### Work

1. Add entity, scope, ACL, layouts, labels, and indexes.
2. Configure calendar-compatible all-day behavior.
3. Implement server-side ownership invariants.
4. Add list filters for year, month, country, and source.
5. Allow administrator CRUD only for manual records.
6. Protect managed fields and managed records.
7. Register the entity automatically for calendar display.

#### Exit criteria

- rebuild creates the table and indexes;
- administrators can manage manual records;
- manual records appear automatically in Calendar;
- managed ownership cannot be forged through the UI or API;
- existing Calendar/Activities configuration is preserved.

### Phase 2 – Implement Settings, Schedule, and Manual Button

#### Goal

Provide the complete Administration > Integrations > Nager.Date interface.

#### Work

1. Add integration metadata and translations.
2. Implement settings validation and normalization.
3. Implement deterministic due-time calculation.
4. Add last-run and next-run status fields.
5. Add **Synchronize now** action and progress/result feedback.
6. Register the scheduled job without executing external writes yet.
7. Prove disabled and manual-only states short-circuit correctly.

#### Exit criteria

- all settings save and reload correctly;
- frequency-dependent fields behave correctly;
- manual button uses a protected server action;
- upgrades preserve saved settings;
- scheduled-job registration is idempotent.

### Phase 3 – Implement and Harden the Nager.Date Client

#### Goal

Fetch and normalize untrusted upstream data without database side effects.

#### Work

1. Implement fixed-origin HTTPS client.
2. Add timeouts, response-size bounds, status checks, and redirect restrictions.
3. Validate full response shape and every row.
4. Normalize nullable subdivision codes.
5. Apply holiday-type and national-only filtering after validation.
6. Add fake transport tests for all success and failure categories.

#### Exit criteria

- callers cannot override origin, scheme, or base path;
- malformed responses produce no partial result;
- fixtures cover Romanian public holidays and edge cases;
- tests require no live network.

### Phase 4 – Implement Atomic Synchronization

#### Goal

Make managed records match validated Nager.Date data safely.

#### Work

1. Implement synchronization lock.
2. Fetch and validate all years before starting a transaction.
3. Reconcile only the defined managed scope.
4. Preserve manual and out-of-scope records.
5. Set ownership fields internally.
6. Update run status and safe logs.
7. Add idempotency, rollback, and concurrency tests.

#### Exit criteria

- first import creates expected records;
- identical rerun makes no logical changes;
- stale managed records are removed safely;
- failures preserve all pre-run data;
- manual records remain unchanged;
- imported records appear in Calendar.

### Phase 5 – Implement the Public Month Service

#### Goal

Expose stable retrieval by year and checkbox-selected months.

#### Work

1. Implement `ZileLibereCalendar` and immutable DTOs.
2. Validate year, months, and country.
3. Implement record and date-only methods.
4. Add strict filtering for non-consecutive month selections.
5. Add deterministic sorting and date deduplication.
6. Add query-count and index-use checks.
7. Document the service contract with examples.

#### Exit criteria

- `[2, 3, 7]` returns February, March, and July only;
- an empty month selection is rejected;
- invalid months are rejected;
- records from unselected months never appear;
- manual and synchronized dates are both returned;
- the service performs no HTTP calls.

### Phase 6 – Tests, Packaging, and Installation Safety

#### Goal

Produce a reliable installable ZIP.

#### Work

1. Run offline metadata, validation, schedule, normalization, and query tests.
2. Run focused EspoCRM runtime tests for DI, ORM, calendar, ACL, scheduled job,
   manual button, and transactions.
3. Build and inspect the ZIP contents.
4. Test fresh installation, reinstall, and upgrade behavior.
5. Verify uninstall removes extension code and scheduled behavior without
   silently deleting `ZileLibere` data.
6. Document rebuild, cron/daemon prerequisite, first sync, and diagnostics.

#### Exit criteria

- offline suite passes without network or credentials;
- runtime suite passes in the supported EspoCRM environment;
- ZIP contains only declared production files;
- installation does not overwrite unrelated settings;
- uninstall preserves stored records unless a separate explicit cleanup action
  is deliberately executed.

## 15. Test Matrix

| Area | Required cases |
| --- | --- |
| Settings | defaults, saved values, disabled integration, automatic disabled, manual-only, daily, weekly, monthly, timezone, invalid time/day |
| Manual button | success, failure, already running, unauthorized user, status refresh |
| HTTP | valid 200, timeout, DNS/TLS failure, non-2xx, oversized body, redirect, invalid JSON |
| Payload | wrong country, wrong year, invalid date, empty name, invalid booleans, invalid arrays, nullable subdivisions |
| Filtering | Public type, multiple types, national-only on/off, regional row, empty valid result |
| Storage | manual defaults, managed invariants, array round-trip, soft delete, indexes, same-date records |
| Sync | first import, identical rerun, changed row, removed row, two years, manual preserved, rollback, concurrent run |
| Calendar | manual row, synchronized row, all-day, one day, global read visibility, ACL denial, no duplicate event |
| Month service | one month, consecutive months, non-consecutive months, duplicate months, invalid month, empty selection, selected year, country normalization |
| Results | full DTO list, date-only list, sorted output, duplicate-date collapse, soft-deleted exclusion |
| Packaging | fresh install, rebuild, scheduled-job uniqueness, upgrade settings preserved, ZIP inventory, uninstall data preservation |

## 16. Failure and Recovery Policy

| Failure | Required behavior | Recovery |
| --- | --- | --- |
| Integration disabled | No manual or automatic HTTP request | Enable integration and synchronize |
| Automatic disabled | Scheduled job skips; manual button remains available | Enable automatic synchronization |
| Invalid settings | Field-specific error; data unchanged | Correct settings |
| Network or upstream error | Run fails; existing data preserved | Retry manually or wait for next due run |
| Invalid payload | Entire run fails; existing data preserved | Diagnose safe error category and retry |
| Database failure | Full transaction rollback | Correct database issue and rerun |
| Concurrent run | Second run skips safely | No repair required |
| Calendar registration failure | Data remains available in list/service; installation reports actionable error | Correct metadata/configuration and rebuild |
| No stored records | Calendar and service return no data without live fallback | Synchronize or create manual records |

## 17. Security and Operational Requirements

- use a fixed HTTPS Nager.Date origin;
- validate every setting and every response value on the server;
- restrict settings, synchronization, and manual record maintenance to
  administrators or an explicitly privileged role;
- prevent ordinary mutation of managed records;
- do not expose full upstream payloads or stack traces to users;
- use one synchronization lock and one transaction per run;
- do not perform network calls while rendering Calendar or querying months;
- do not create infrastructure from installation scripts;
- rely on the existing EspoCRM cron or daemon for scheduled-job execution;
- preserve administrator settings and stored data during upgrade;
- treat last successful sync time as visible operational state.

## 18. Acceptance Criteria

The extension is accepted when all of the following are true:

1. It installs as **Zile Sărbătoare**.
2. EspoCRM exposes the **Zile libere** entity.
3. An administrator can create, edit, and delete manual days.
4. Nager.Date records are synchronized for the configured country and years.
5. The administrator can choose manual-only, daily, weekly, or monthly automatic
   synchronization.
6. **Synchronize now** works and displays a result summary.
7. Failed synchronization cannot erase valid stored data.
8. Manual records survive every synchronization.
9. Every active record appears automatically as one all-day calendar item.
10. No per-user duplicate records are created.
11. Managed records cannot be edited or removed through ordinary APIs.
12. `getZileLiberePentruLuni(2026, [2, 3, 7], 'RO')` returns only records in
    February, March, and July 2026.
13. `getDateLiberePentruLuni` returns sorted unique ISO dates.
14. Month queries use stored data only and never contact Nager.Date.
15. The installable ZIP is reproducible and contains no development fixtures in
    production paths.

## 19. Official References

- EspoCRM integration metadata:
  https://docs.espocrm.com/development/metadata/integrations/
- EspoCRM scheduled-job metadata:
  https://docs.espocrm.com/development/metadata/app-scheduled-jobs/
- EspoCRM scheduled jobs and CLI:
  https://docs.espocrm.com/development/scheduled-job/
- EspoCRM custom entity types:
  https://docs.espocrm.com/development/custom-entity-type/
- EspoCRM Calendar and custom Event entities:
  https://docs.espocrm.com/user-guide/activities-and-calendar/
- EspoCRM scope calendar metadata:
  https://docs.espocrm.com/development/metadata/scopes/
- Nager.Date API:
  https://date.nager.at/api
