# Holiday Management

EspoCRM 10 extension foundation for annual-holiday settings.

The extension currently includes PHASE-001 settings and PHASE-002 accounting:

- annual entitlement and reset-date defaults;
- reset ceiling, warning, warning-repeat, and negative-balance limits;
- one approver role, limited to at most two active regular/admin users;
- exactly two printed approval title/name blocks. Blank configured names mean
  that the later document phase must use the actual approver names.
- one holiday profile per eligible internal user;
- admin-only bulk initialization with entitlement, opening balance and reset date;
- transactional, idempotent corrections and annual grants;
- append-only balance ledger and pending/forced reset handling.

It intentionally contains no leave requests, holiday synchronization, approval
workflow, notifications, calendar projection, or document generation.

Build from the repository root:

```bash
./build.sh --extension ./HolidayManagement --zip 1.1.0 files scripts
```

Run the phase contract tests:

```bash
node --test HolidayManagement/tests/phase-001/contract.test.mjs
node --test HolidayManagement/tests/phase-002/contract.test.mjs
php HolidayManagement/tests/phase-002/balance-math.test.php
```
