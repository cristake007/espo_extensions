# Holiday Management

EspoCRM 10 extension for employee holiday balances and self-service booking.

The extension includes settings, balance accounting, and self-service booking:

- annual entitlement and reset-date defaults;
- a configurable accumulated-balance warning threshold and a negative-balance limit;
- one or two directly selected active regular/admin holiday approvers;
- exactly two printed approval title/name blocks. Blank configured names use
  the actual deciding approver's name when the corresponding title is set;
- one holiday profile per eligible internal user;
- role-controlled profile initialization with entitlement, opening balance and reset date;
- transactional, idempotent corrections and annual grants;
- append-only balance ledger and automatic annual resets;
- an optional approver dashboard dashlet with pending requests and decision actions;
- a role-controlled monthly overview with an employee-by-day matrix, request
  statuses, balances, and operational summary cards;
- a standard **Time Off / Concediu** main-navigation entry showing the
  signed-in user's remaining balance;
- self-service holiday bookings from both that page and EspoCRM Calendar;
- requester-visible pending, approved, and rejected states, with a final
  decision by either configured approver from the Calendar request detail;
- audited cancellation requests for approved future leave, approver acceptance
  or rejection, and direct reasoned cancellation by an approver for exceptional
  cases, with an automatic one-time balance refund;
- approval-time DOCX generation using the bundled Romanian vacation-request
  layout, with one downloadable document per covered calendar month;
- weekday and Romanian `ZileLibere` calculation, overlap prevention, configured
  balance-limit enforcement, and automatic balance reservation,
  adjustment, and refund when a booking is created, edited, or deleted.

Bookings are immediately reserved against the initialized holiday profile. The
working-day total excludes weekends and Romanian dates already stored by the
`ZileSarbatoare` extension as `ZileLibere`. Holiday Management only reads those
records; it does not modify them. Rejected requests refund their reserved days
exactly once and no longer appear in Calendar. The extension does not yet include
approval notifications or public-holiday synchronization.

When **Enable Automatic Annual Grants** is explicitly enabled, at each profile's
reset date the daily scheduled job grants that profile's
annual entitlement in full. Existing days are never removed during the reset.
When the resulting accumulated balance exceeds the configured warning threshold
(90 days by default), EspoCRM notifies the employee and the configured holiday
approvers. A negative balance is carried in full and reduces the resulting
balance; for example, a balance of -5 with a 21-day entitlement becomes 16.

Automatic annual grants are disabled by default. The scheduled job exits without
changing balances until an administrator enables them after reviewing profile
balances and next-reset dates. The extension registers its active daily job
automatically during installation and upgrades; EspoCRM's normal scheduled-job
runner executes it.

Administrators can use **Holiday Management > RESET** to preview and reset
extension test data before go-live. The guarded reset requires the exact phrase
`RESET HOLIDAY DATA`, refuses to run while automatic annual grants are enabled,
removes holiday requests, generated approval documents, ledger entries and
balance-warning notifications, and returns holiday profiles to the uninitialized
state. Users, roles, Holiday Management settings and public-holiday records are
preserved.

Build from the repository root:

```bash
./build.sh --extension ./HolidayManagement --zip 1.9.4 files scripts
```

Run the phase contract tests:

The old `tests/phase-001/docker.ps1` and `phase-002/docker.ps1` scripts create
separate Compose labs and remove their volumes. They are historical fixtures,
not integration runners for the persistent test instance. Keep them disabled
until their setup, mutations and cleanup are isolated for shared runtime use.

```bash
./test-node.sh test HolidayManagement
php HolidayManagement/tests/phase-002/balance-math.test.php
php HolidayManagement/tests/phase-003/working-day-calculator.test.php
php HolidayManagement/tests/phase-003/booking-date-policy.test.php
php HolidayManagement/tests/phase-003/lifecycle.test.php
php HolidayManagement/tests/phase-004/document-generator.test.php
```

After upgrading, run `bin/command rebuild`. Every active regular user can then
select **Time Off / Concediu** when creating an entry in Calendar.
Administrators can manage the extension but do not receive holiday profiles or
holiday balances. New bookings and date changes cannot start before the current
date. A user's holiday profile must be initialized in
**Administration > Holiday Profiles** before their first booking.

Holiday settings and the monthly overview use native EspoCRM boolean role
permissions. Enable **Holiday Management Settings** and/or **Holiday Overview**
in a role, then assign the role to the responsible users or teams. The test-data
RESET action remains visible and executable only for administrators.

To delegate profile setup, enable **Holiday Profile Management** in a native
EspoCRM role and assign that role to the responsible active internal user or
team. The user receives a **Manage holiday profiles** button next to the normal
Time Off page actions and can use the profile workspace without access to global
holiday settings, forced resets, or direct accounting-record writes. Opening balance is accepted only during the
first initialization. To permit later balance changes, also enable the separate
**Holiday Balance Correction** role permission. Each correction requires a new
balance and a reason and creates an immutable Holiday Ledger entry containing
the delta, before/after balances, actor, reason, and timestamp. Initialized
balances are read-only in the profile table; use **Correct Balance** to change
them. **Correction History** displays every correction and its recorded reason.

To let a responsible user register time off for colleagues who cannot use the
platform, enable **Time Off Manager** in an EspoCRM role. The Time Off page then
shows a **Book for employee** action. Delegated requests are created as pending,
reserve the selected employee's balance, and follow the normal approval flow.
Past start dates are accepted only within the current calendar month; overlap,
working-day and balance checks remain enforced.

Calendar displays every user's time off to internal users. Each marker includes
the umbrella icon and the booking user's name. The **Time Off / Concediu** page
lists only the signed-in user's requests. Users can still edit or delete only
their own bookings, and portal access remains disabled.
