# Holiday Management

EspoCRM 10 extension foundation for annual-holiday settings.

Phase 1 provides only the installable module and its Administration settings:

- annual entitlement and reset-date defaults;
- reset ceiling, warning, warning-repeat, and negative-balance limits;
- one approver role, limited to at most two active regular/admin users;
- exactly two printed approval title/name blocks. Blank configured names mean
  that the later document phase must use the actual approver names.

It intentionally contains no profiles, balances, requests, holidays, approval
workflow, notifications, or document generation.

Build from the repository root:

```bash
./build.sh --extension ./HolidayManagement --zip 1.0.0 files scripts
```

Run the phase contract tests:

```bash
node --test HolidayManagement/tests/phase-001/contract.test.mjs
```
