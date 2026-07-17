# Zile Sărbătoare

EspoCRM 10 extension for centrally managed non-working days, manual company
holidays, and Nager.Date synchronization.

## Requirements

- EspoCRM `>=10.0.0`;
- PHP `>=8.4`;
- an existing EspoCRM cron or daemon worker for automatic synchronization;
- outbound HTTPS access to `https://date.nager.at` when synchronization runs.

The extension does not install or configure cron, a daemon, containers, or a
database.

## Build and install

From the `projects/espo_extensions` repository root:

```bash
bash build.sh --extension ZileSarbatoare --zip 0.7.7 files scripts
```

Upload `dist/zile-sarbatoare-0.7.7.zip` in **Administration > Extensions**, or
install it from the EspoCRM root:

```bash
bin/command extension --file="/path/to/zile-sarbatoare-0.7.7.zip"
bin/command rebuild
bin/command populate-scheduled-jobs
```

Installing the same version again or upgrading preserves saved Nager.Date
settings. Installation appends `ZileLibere` to `calendarEntityList`, `tabList`,
and `quickCreateList` without replacing existing configuration.

## Manual holidays

Administrators can add company-specific days from **Zile libere > Create** or
from EspoCRM's **Quick Create** menu. Manual records remain editable and are not
removed or overwritten by Nager.Date synchronization.

## First synchronization

1. Open **Administration > Integrations > Nager.Date**.
2. Confirm the country, years, holiday types, and schedule.
3. Select **Synchronize now**.
4. Confirm the accepted, created, updated, and removed counts.
5. Open Calendar and verify the imported all-day entries.

Automatic synchronization uses the existing EspoCRM job runner. Verify it from
the EspoCRM root with:

```bash
bin/command run-job SyncZileSarbatoare
```

## Public PHP service

Inject
`Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereCalendar` into a
server-side class. The service reads stored records only and performs no HTTP
request.

```php
$records = $calendar->getZileLiberePentruLuni(2026, [2, 3, 7], 'RO');
$dates = $calendar->getDateLiberePentruLuni(2026, [2, 3, 7], 'RO');
$isFree = $calendar->esteZiLibera('2026-07-15', 'RO');
```

## Diagnostics

- If automatic runs do not start, verify the EspoCRM cron or daemon and confirm
  that the `SyncZileSarbatoare` scheduled job is active.
- If the manual button is unavailable, confirm that the current user is an
  administrator and that the Nager.Date integration is enabled.
- If synchronization fails, inspect the safe last-run message on the integration
  page and the EspoCRM logs. Existing holiday data is retained after network,
  validation, or transactional write failures.
- If records do not appear in Calendar, run `bin/command rebuild`, verify
  `ZileLibere` is present in `calendarEntityList`, and verify role-level read
  access to **Zile libere**.

## Uninstall and data retention

Uninstall with:

```bash
bin/command extension -u --name="Zile Sărbătoare"
```

The uninstall hook unregisters the Calendar, navigation, and Quick Create
entries and removes the extension's scheduled-job record. It deliberately does
not delete `ZileLibere` records or the `zile_libere` custom table. Reinstalling
the extension makes the retained records available again. Do not run a separate
manual database cleanup unless permanent data deletion is explicitly intended
and backed up.
