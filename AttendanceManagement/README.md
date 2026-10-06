# Attendance Management

Attendance Management provides a personal attendance register for EspoCRM 10.
Employees can mark a current or past working day as **At work** or **In business
trip**. Future dates, weekends, Romanian public holidays from `ZileLibere`, and
days covered by an approved Holiday Management request cannot be marked.
Only active regular users are treated as employees. Administrator accounts can
manage the extension but are excluded from schedule lists, the manager overview,
reminders, completion calculations, and XLSX/PDF exports. Any historical or
personal attendance data belonging to an administrator is ignored by the register.
The monthly register uses an immediate month selector. By default, employees can
change entries in the current and immediately previous calendar month; older
months are read-only but remain available in the archive. Both employee and
manager month selectors include every month from the earliest attendance record
or employee schedule through the current month. The attendance initialization
button can explicitly start the visible archive from the current month; this is
a non-destructive reset, so older database records are retained even though
earlier months no longer appear in the selectors. Administrators can adjust the
editing window under **Administration > Attendance Management**.

Approved `HolidayRequest` records are read dynamically and displayed as locked
**In holiday** days. Attendance Management never changes holiday requests or
holiday balances.

The employee page and manager overview are grouped under one **Attendance
Management** side-navigation section. The manager overview is visible and
available only to users granted **Attendance Overview** access through a native
EspoCRM role. Create a role such as **Attendance Manager**, enable its
**Attendance Overview** permission, and assign the role to the responsible user
or team. It provides a
monthly employee-by-day preview, highlights missing entries, sends in-app
reminders to employees with unsigned days, and exports the register as XLSX or
PDF once all elapsed working days have been completed. Its selector shows the
complete monthly archive through the current month. Actionable cards show completion by
employee, missing entries by employee, schedule coverage with missing names, and
the exact export-readiness blockers. Reminders are disabled for months employees
can no longer edit. An administrator sets each
employee's default start and end time under **Administration > Attendance
Management**. The responsive schedule editor uses theme-native 15-minute time
selectors and saves each employee independently. Existing times outside a
15-minute interval remain available and are not changed. The interval applies to
every month and can be changed there at any time. The overview displays it
read-only below the employee name. Both exports include entry time, exit time,
and attendance status; no signature column is
included. Printing is preconfigured for A4 portrait with seven employees per
page, repeated date/program columns and headings, explicit horizontal page
breaks, page numbering, larger print text, and body rows sized to use the
printable page height in the same way as the original register. A single
worksheet therefore prints all employee groups without selecting multiple
sheets.

Users with the role permission receive the standard **Administration** entry in
the flyout menu through EspoCRM's native `clientNavbar.accessDataList` check.
Their restricted administration page contains only **Attendance Management >
Attendance settings**. Administrators and attendance managers use the same
purpose-built workspace, organized into configuration, archive initialization,
and employee-schedule sections. On that page they can set the editable previous-month
window, allow or disallow incomplete exports, initialize the visible register,
and edit employee schedules. It uses
`#Admin/attendanceManagementSettings`; all other administration settings remain
restricted to administrators. API access uses the same server-side ACL scope.

Administrators can enable **Allow incomplete register exports** under
**Administration > Attendance Management**. When enabled, managers may download
both formats before every employee has completed the month; missing attendance
or schedule values are left blank. The setting is disabled by default.

Build from the repository root:

```bash
./build.sh --extension ./AttendanceManagement --zip 1.10.13 files scripts
```

After installation or upgrade, run `bin/command rebuild`.
