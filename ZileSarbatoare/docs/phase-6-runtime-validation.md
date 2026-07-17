# Phase 6 EspoCRM runtime validation

Status: **not executed — an EspoCRM runtime is not available in this workspace**

Run this checklist on a disposable, non-production EspoCRM `>=10.0.0` instance
using PHP `>=8.4`. Back up the database first. Use an administrator, a regular
user with `ZileLibere` read access, and a regular user without that access.

## Install and rebuild

```bash
bin/command extension --file="/path/to/zile-sarbatoare-0.7.1.zip"
bin/command rebuild
bin/command populate-scheduled-jobs
bin/command app-info --binding
```

Verify:

- `ZileLibereCalendar` resolves to `ZileLibereCalendarService`;
- the `zile_libere` table exists;
- `countryDate` covers `country_code`, `date_start`, and `deleted`;
- `managedSyncScope` covers source, managed, country, source year, and deleted;
- `jsonArray` values round-trip for holiday types and subdivisions;
- `SyncZileSarbatoare` exists once and is active with the five-minute polling
  schedule.

## Administration, ACL, and Calendar

1. Confirm only an administrator can save Nager.Date settings or call
   **Synchronize now**.
2. Create one manual record and confirm its managed ownership fields cannot be
   forged through UI or API requests.
3. Confirm a managed record cannot be edited or deleted through ordinary APIs.
4. Create two named records on one date. Confirm both appear exactly once as
   one-day, all-day Calendar items.
5. Confirm the administrator and read-enabled user see the global items.
6. Confirm the user without `ZileLibere` read access sees neither list nor
   Calendar records.

## Synchronization and transactions

1. Run `bin/command run-job SyncZileSarbatoare` with the integration disabled;
   confirm it performs no HTTP request.
2. Enable the integration and synchronize the captured test years; confirm
   manual and out-of-scope rows remain unchanged.
3. Repeat an identical run; confirm created, updated, and removed are all zero.
4. Exercise a database failure in a disposable test database; confirm the full
   reconciliation rolls back.
5. Start overlapping manual and scheduled attempts; confirm the second attempt
   skips without writes.

## Upgrade, reinstall, and uninstall retention

1. Change the country, years, schedule, and enabled state from their defaults.
2. Install the same or newer package without uninstalling; confirm every saved
   value remains unchanged and Calendar registration is not duplicated.
3. Record the row count before uninstall using a read-only database query:

   ```sql
   SELECT COUNT(*) FROM zile_libere;
   ```

4. Uninstall the extension. Confirm `ZileLibere` is removed from
   `calendarEntityList` and no active `SyncZileSarbatoare` scheduled job remains.
5. Confirm the `zile_libere` table and its row count remain unchanged. Do not run
   hard rebuild during this retention check.
6. Reinstall, rebuild, and repopulate scheduled jobs. Confirm retained records
   and settings return and exactly one scheduled job is active.

Record the EspoCRM version, PHP version, database engine, commands, screenshots,
and pass/fail evidence when this checklist is executed.
