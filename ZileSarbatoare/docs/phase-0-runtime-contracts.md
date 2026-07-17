# Phase 0 runtime contracts

Status: **source-confirmed; runtime proof pending**

Evidence baseline:

- EspoCRM `10.0.3`, commit `15ce01ccc6321648dac91f82f9223dd8b2df7936`;
- official EspoCRM developer documentation, checked on 2026-07-17;
- the repository conventions under `projects/espo_extensions`;
- a captured Nager.Date Romania 2026 response.

No EspoCRM installation is available in this workspace. Conclusions marked
"source-confirmed" come from EspoCRM source and metadata. Installation,
database, browser, and multi-user ACL checks remain runtime work.

## Confirmed contracts

### Package and module

- The installable package root contains `manifest.json` and `files/`.
- Backend code belongs under
  `custom/Espo/Modules/ZileSarbatoare`.
- Frontend code will belong under
  `client/custom/modules/zile-sarbatoare`.
- Module load order is declared in `Resources/module.json`.

### Calendar representation

- Scope metadata must declare `module: ZileSarbatoare`, `type: Event`,
  `calendar: true`, and `calendarOneDay: true`.
- `calendarOneDay` makes a calendar-enabled entity render as a one-day event.
- The canonical persisted date will be `dateStart` with field type `date`.
- `dateEnd`, `dateStartDate`, and `dateEndDate` will not be stored for
  `ZileLibere`. The calendar query will project `dateStart` into the result
  aliases required by the Calendar API.
- The physical table name generated from `ZileLibere` is `zile_libere`.
- EspoCRM adds the standard `deleted` attribute unless `noDeletedAttribute`
  is explicitly enabled. It will not be enabled for this entity.

### Global visibility and ACL

EspoCRM 10.0.3's generic Calendar service is not sufficient for an unassigned
global record. Its base query applies strict ACL and then requires either:

- the requested user in `assignedUsers`;
- `assignedUserId` equal to the requested user; or
- the requested user in a `users` many-to-many relation.

Therefore, `calendar: true` alone does not satisfy the plan. Assigning each
holiday to every user is forbidden by the functional contract.

The Phase 1 implementation will provide the module-owned `ZileLibere` calendar
query through the existing record-service calendar query extension point. The
query must:

1. use the EspoCRM Select Builder for `ZileLibere`;
2. call `withStrictAccessControl()` so role-level read denial is preserved;
3. omit assigned-user and users-link predicates;
4. exclude soft-deleted rows through the ORM default;
5. select one row per `ZileLibere` record;
6. alias the single `dateStart` column into the Calendar response's start and
   end date slots; and
7. filter the requested half-open date range using only `dateStart`.

This means a user with entity read access sees the shared records, while a user
without read access does not. A same-date pair remains two named calendar
events because the query does not deduplicate records.

The EspoCRM extension point is currently named `getCalenderQuery` in core
(including the spelling). It is retained for backward compatibility in
10.0.3. Runtime tests must treat this method as a compatibility seam and fail
clearly if a later supported EspoCRM release removes it.

`calendarEntityList` remains an administrator configuration value. Installation
must add `ZileLibere` without replacing existing entries, and upgrade must be
idempotent. This additive config mutation requires an installation/upgrade
script and runtime verification; metadata merging alone cannot append to saved
configuration.

### Integration and manual action

- `Resources/metadata/integrations/NagerDate.json` is the supported metadata
  location.
- `allowUserAccounts: false` makes this a system integration.
- The metadata `view` property selects a custom Administration > Integrations
  frontend view.
- EspoCRM's core `Integration` controller rejects non-admin users in its
  constructor and exposes the saved `enabled` value with the remaining fields.
- The custom `Synchronize now` server action must independently require an
  administrator and must check the persisted integration `enabled` state in
  the synchronization service. Hiding or disabling a browser button is not an
  authorization boundary.

### Scheduled job

- Register one `JobDataLess` implementation in
  `Resources/metadata/app/scheduledJobs.json`.
- `isDefault: true` is available since EspoCRM 10.0 and creates the scheduled
  job on extension installation or through `bin/command
  populate-scheduled-jobs`.
- Population is idempotent by job name and requires a non-empty fixed
  `scheduling` expression.
- The fixed polling schedule must remain independent of the administrator's
  requested daily, weekly, monthly, or manual-only policy. The job delegates
  all due-time decisions to `NagerDateSchedule`/`NagerDateSyncService`.
- Manual CLI verification commands are `bin/command
  populate-scheduled-jobs` and `bin/command run-job SyncZileLibere`.

### Dependency injection

- `Binding.php` implements `Espo\Core\Binding\BindingProcessor`.
- `ZileLibereCalendar` is bound to `ZileLibereCalendarService` with
  `Binder::bindImplementation`.
- A container service is unnecessary for the public interface unless later
  runtime evidence shows a lifecycle requirement. Constructor injection via
  EspoCRM's Injectable Factory is sufficient.
- Runtime proof command: `bin/command app-info --binding`.

### ORM, arrays, deletion, and indexes

- Atomic reconciliation will use
  `EntityManager::getTransactionManager()->run(...)`. The manager commits on
  success, rolls back on any thrown error, and supports nested savepoints.
- Repository customization is supported by the module repository class and/or
  `entityDefs.repositoryClassName`; the final choice will follow the standard
  module repository lookup proven in the runtime.
- `holidayTypes` and `subdivisionCodes` will use storable `jsonArray` fields.
  They preserve ordered JSON arrays without creating the auxiliary value rows
  used by EspoCRM `array` fields.
- The planned metadata indexes are:
  - `countryDate`: `countryCode`, `dateStart`, `deleted`;
  - `managedSyncScope`: `source`, `managed`, `countryCode`, `sourceYear`,
    `deleted`.
- There is no date-only unique index. The schema must permit multiple names on
  one date.

### Offline upstream fixtures

`tests/fixtures/nager-date/ro-2026.json` is a representative response captured
from `GET https://date.nager.at/api/v3/PublicHolidays/2026/RO` on 2026-07-17.
It intentionally includes nullable `counties` and two holidays on
2026-06-01. Tests must consume this file locally and must not call Nager.Date.

## Runtime proof still required

Phase 0 cannot meet its exit criteria until an EspoCRM `>=10.0.0` instance is
available. The focused runtime spike must verify:

1. install/rebuild creates `zile_libere` with the two planned indexes;
2. a single record with one stored date appears once as an all-day item;
3. two records on one date appear twice;
4. an administrator and a regular user with read access see the records;
5. a regular user without read access does not see them;
6. `calendarEntityList` installation is additive and upgrade-idempotent;
7. integration defaults, custom view loading, enabled-state reads, and admin
   server-action authorization;
8. default scheduled-job population and direct CLI execution;
9. interface injection through `Binding.php`;
10. `jsonArray` round trips, soft deletion, transaction rollback, and custom
    repository resolution.

Production schema and synchronization behavior must not proceed until these
checks pass or a supported alternative is selected for any failed contract.

## References

- <https://docs.espocrm.com/development/modules/>
- <https://docs.espocrm.com/development/custom-entity-type/>
- <https://docs.espocrm.com/development/metadata/scopes/>
- <https://docs.espocrm.com/development/metadata/integrations/>
- <https://docs.espocrm.com/development/metadata/app-scheduled-jobs/>
- <https://docs.espocrm.com/development/scheduled-job/>
- <https://docs.espocrm.com/development/di/>
- <https://docs.espocrm.com/development/orm/>
- <https://github.com/espocrm/espocrm/tree/10.0.3>
