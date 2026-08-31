# Holiday Management

EspoCRM 10 extension for employee holiday balances and self-service booking.

The extension includes settings, balance accounting, and self-service booking:

- annual entitlement and reset-date defaults;
- reset ceiling, warning, warning-repeat, and negative-balance limits;
- one or two directly selected active regular/admin holiday approvers;
- exactly two printed approval title/name blocks. Blank configured names mean
  that the later document phase must use the actual approver names.
- one holiday profile per eligible internal user;
- admin-only bulk initialization with entitlement, opening balance and reset date;
- transactional, idempotent corrections and annual grants;
- append-only balance ledger and pending/forced reset handling;
- a standard **Time Off / Concediu** main-navigation entry showing the
  signed-in user's remaining balance;
- self-service holiday bookings from both that page and EspoCRM Calendar;
- requester-visible pending, approved, and rejected states, with a final
  decision by either configured approver from the Calendar request detail;
- weekday and Romanian `ZileLibere` calculation, overlap prevention, configured
  balance-limit enforcement, and automatic balance reservation,
  adjustment, and refund when a booking is created, edited, or deleted.

Bookings are immediately reserved against the initialized holiday profile. The
working-day total excludes weekends and Romanian dates already stored by the
`ZileSarbatoare` extension as `ZileLibere`. Holiday Management only reads those
records; it does not modify them. Rejected requests refund their reserved days
exactly once and no longer appear in Calendar. The extension does not yet include
approval notifications, public-holiday synchronization, or document generation.

Build from the repository root:

```bash
./build.sh --extension ./HolidayManagement --zip 1.4.1 files scripts
```

Run the phase contract tests:

```bash
node --test HolidayManagement/tests/phase-001/contract.test.mjs
node --test HolidayManagement/tests/phase-002/contract.test.mjs
php HolidayManagement/tests/phase-002/balance-math.test.php
node --test HolidayManagement/tests/phase-003/contract.test.mjs
php HolidayManagement/tests/phase-003/working-day-calculator.test.php
php HolidayManagement/tests/phase-003/booking-date-policy.test.php
php HolidayManagement/tests/phase-003/lifecycle.test.php
```

After upgrading, run `bin/command rebuild`. Every active regular or
administrator user can then select **Time Off / Concediu** when creating an
entry in Calendar. New bookings and date changes cannot start before the
current date. A user's holiday profile must be initialized in
**Administration > Holiday Profiles** before their first booking.

Calendar displays every user's time off to internal users. Each marker includes
the umbrella icon and the booking user's name. The **Time Off / Concediu** page
lists only the signed-in user's requests. Users can still edit or delete only
their own bookings, and portal access remains disabled.
