# Attendance Management

Attendance Management provides a personal attendance register for EspoCRM 10.
Employees can mark a current or past working day as **At work** or **In business
trip**. Future dates, weekends, Romanian public holidays from `ZileLibere`, and
days covered by an approved Holiday Management request cannot be marked.

Approved `HolidayRequest` records are read dynamically and displayed as locked
**In holiday** days. Attendance Management never changes holiday requests or
holiday balances.

Build from the repository root:

```bash
./build.sh --extension ./AttendanceManagement --zip 1.0.0 files
```

After installation or upgrade, run `bin/command rebuild`.
