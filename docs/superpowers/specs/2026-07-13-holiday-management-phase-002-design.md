# Holiday Management PHASE-002 Design

## Scope

PHASE-002 adds employee holiday profiles, cached balances, an immutable accounting ledger, bulk initialization, corrections, and annual reset accounting. It builds on PHASE-001 settings and does not add requests, holiday synchronization, approvals, notifications, calendars, or documents.

## Data model

`HolidayProfile` has exactly one record per eligible active internal user. It stores the user, annual entitlement, reset date, cached balance, initialization state, and pending-reset state. The user link is unique. Cached accounting fields are changed only by `HolidayBalanceService`.

`HolidayLedger` is append-only. Every row records the profile, mutation type, signed delta, balance before and after, actor, reason, effective date, and a globally unique idempotency key. Ledger records cannot be updated or deleted through normal record APIs.

## Accounting service

`HolidayBalanceService` is the only balance mutation boundary. It starts a database transaction, locks the profile row for update, checks the idempotency key, calculates the mutation, inserts one ledger row, updates the cached profile balance, and commits. Any error rolls back the complete mutation.

Opening balances are initialized rather than reconstructed. Corrections are signed deltas, require a reason, and retain old/new values in the ledger. Reusing an idempotency key returns the existing result without applying the delta again.

## Annual reset rules

Annual entitlement is a fixed grant with full carry-over, including deficits:

- balance `10` plus entitlement `21` becomes `31`;
- balance `-5` plus entitlement `21` becomes `16`.

Before applying a reset, the service evaluates `current balance + entitlement <= configured ceiling`. If true, it applies the grant. If false, it records the reset as pending without changing the balance.

With ceiling `90` and entitlement `21`:

- `80 + 21 = 101`: remain pending;
- `70 + 21 = 91`: remain pending;
- `69 + 21 = 90`: apply;
- `60 + 21 = 81`: apply.

After any later correction or balance mutation, a pending reset is automatically applied only when that same expression becomes true. An administrator may force a pending reset above the ceiling only with a non-empty reason. Reset operations are idempotent.

## Bulk initialization and access

An Administration page lists eligible active regular and administrator users and accepts entitlement, opening balance, and reset date for multiple users. Its admin-only endpoint processes each user through the accounting service, creates or updates the unique profile, and writes an initialization or correction ledger entry for every material change.

Disabled, portal, API, and system users are not eligible. Direct profile balance edits and all direct ledger writes are rejected. Administrators are the PHASE-002 correction and forced-reset authority.

## Error handling

Invalid users, duplicate user profiles, missing correction/override reasons, invalid reset dates, and malformed idempotency keys produce validation errors without partial mutations. Database uniqueness constraints provide the final guard against duplicate profiles and ledger operations.

## Testing

Contract tests cover metadata, layouts, translations, API routes, immutable record rules, phase boundaries, and package contents. PHP unit-style tests exercise accounting arithmetic and reset eligibility. Docker tests install or upgrade the extension, verify bulk initialization and audit entries through the API, exercise duplicate idempotency keys, and run concurrent mutations to prove no lost updates.

The release package remains compatible with EspoCRM 10 and contains no PHASE-003 or later entities or services.
