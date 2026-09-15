# Attendance Management

Attendance Management provides a personal attendance register for EspoCRM 10.
Employees can mark a current or past working day as **At work** or **In business
trip**. Future dates, weekends, Romanian public holidays from `ZileLibere`, and
days covered by an approved Holiday Management request cannot be marked.

Approved `HolidayRequest` records are read dynamically and displayed as locked
**In holiday** days. Attendance Management never changes holiday requests or
holiday balances.

The employee page and manager overview are grouped under one **Attendance
Management** side-navigation section. The manager overview is visible and
available only to users selected under **Administration > Attendance
Management**. It provides a
monthly employee-by-day preview, highlights missing entries, sends in-app
reminders to employees with unsigned days, and exports the register as XLSX once
all elapsed working days have been completed. An administrator sets each
employee's default start and end time under **Administration > Attendance
Management**. The interval applies to every month and can be changed there at any
time. The overview displays it read-only below the employee name. The XLSX
includes entry time, exit time, and attendance status; no signature column is
included.

Build from the repository root:

```bash
./build.sh --extension ./AttendanceManagement --zip 1.4.3 files scripts
```

After installation or upgrade, run `bin/command rebuild`.
