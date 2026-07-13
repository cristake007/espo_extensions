# Holiday Management PHASE-002 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-managed employee profiles, cached holiday balances, an append-only ledger, and serialized/idempotent reset and correction accounting.

**Architecture:** EspoCRM metadata defines two internal entities with database uniqueness constraints. A pure `BalanceMath` policy owns reset arithmetic, while `HolidayBalanceService` owns transactions, row locks, idempotency, profile updates, and ledger creation; admin-only API actions and one Administration view are thin adapters.

**Tech Stack:** EspoCRM 10 metadata and ORM, PHP 8.4, AMD client views, Node.js contract tests, PowerShell Docker/package tests.

## Global Constraints

- Implement only PHASE-002; no requests, holiday synchronization, approvals, notifications, calendars, or documents.
- Preserve all PHASE-001 behavior and the portable ZIP fix.
- A pending reset is eligible only when `current balance + entitlement <= ceiling`.
- One unique `HolidayProfile` exists per eligible active regular or administrator user.
- `HolidayLedger` is append-only; all mutations use `HolidayBalanceService`.
- Every mutation is transactional, row-locked, idempotent, and audited.
- EspoCRM compatibility remains `>=10.0.0`; PHP compatibility remains `>=8.4`.

---

### Task 1: Contract tests and entity metadata

**Files:**
- Create: `HolidayManagement/tests/phase-002/contract.test.mjs`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/entityDefs/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/entityDefs/HolidayLedger.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/scopes/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/scopes/HolidayLedger.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/aclDefs/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/aclDefs/HolidayLedger.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/recordDefs/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/recordDefs/HolidayLedger.json`

**Interfaces:**
- Produces: unique `HolidayProfile.userId`, unique `HolidayLedger.idempotencyKey`, service-managed profile accounting fields, immutable ledger record hooks.

- [ ] Write contract assertions for fields, unique indexes, record hooks, append-only actions, and forbidden later-phase entity names.
- [ ] Run `node --test HolidayManagement/tests/phase-002/contract.test.mjs`; expect missing-file failures.
- [ ] Add the two entity definitions, scopes, ACL definitions, and record definitions with the exact constraints asserted by the test.
- [ ] Re-run the contract test; expect the metadata tests to pass.

### Task 2: Reset arithmetic policy

**Files:**
- Create: `HolidayManagement/tests/phase-002/balance-math.test.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/BalanceMath.php`

**Interfaces:**
- Produces: `BalanceMath::canApplyReset(float $balance, float $entitlement, float $ceiling): bool` and `BalanceMath::applyEntitlement(float $balance, float $entitlement): float`.

- [ ] Write executable PHP assertions for `10 + 21 = 31`, `-5 + 21 = 16`, rejection at balances 80 and 70, and acceptance at 69 and 60 for ceiling 90.
- [ ] Run the PHP test; expect failure because `BalanceMath` is absent.
- [ ] Implement the two pure static methods using the approved expression.
- [ ] Re-run the PHP test; expect all arithmetic assertions to pass.

### Task 3: Immutable-record guards

**Files:**
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayLedger/BeforeCreate.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayLedger/BeforeUpdate.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayLedger/BeforeDelete.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayProfile/BeforeCreate.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayProfile/BeforeUpdate.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Classes/Record/Hooks/HolidayProfile/BeforeDelete.php`

**Interfaces:**
- Consumes: record-hook class names from Task 1.
- Produces: normal record API rejection for ledger create/update/delete and profile create/delete/accounting-field update.

- [ ] Extend the contract test to assert each hook implements the EspoCRM 10 hook interface and throws `Forbidden`.
- [ ] Run the contract test; expect missing hook failures.
- [ ] Implement focused hook classes. The profile update hook rejects changes to `balance`, `isInitialized`, `resetPending`, `pendingResetDate`, and `pendingResetKey`.
- [ ] Re-run contract and PHP syntax tests; expect success.

### Task 4: Transactional balance service

**Files:**
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/HolidayBalanceService.php`

**Interfaces:**
- Consumes: `BalanceMath`, EspoCRM `EntityManager`, `Config`, and current `User`.
- Produces: `listProfiles(): array`, `bulkInitialize(array $items): array`, `correct(string $profileId, float $delta, string $reason, string $idempotencyKey): array`, and `reset(string $profileId, string $idempotencyKey, bool $force, ?string $reason): array`.

- [ ] Extend the contract test to require `TransactionManager::run`, `forUpdate`, idempotency lookup, unique user eligibility checks, ledger-before/after fields, and pending auto-reset logic.
- [ ] Run the contract test; expect the service assertions to fail.
- [ ] Implement validation, per-item transactions, row locks, profile creation/update, immutable ledger insertion, correction reasons, pending resets, forced reset reasons, and automatic reset using `balance + entitlement <= ceiling`.
- [ ] Re-run contract tests and PHP syntax checks; expect success.

### Task 5: Admin-only API and Administration view

**Files:**
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/routes.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/Api/GetProfiles.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/Api/PostBulkInitialize.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/Api/PostCorrection.php`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Tools/HolidayBalance/Api/PostReset.php`
- Create: `HolidayManagement/files/client/custom/modules/holiday-management/src/views/admin/profiles.js`
- Modify: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/metadata/app/adminPanel.json`

**Interfaces:**
- Consumes: the four `HolidayBalanceService` public methods.
- Produces: GET `/HolidayManagement/profiles`; POST `/HolidayManagement/bulkInitialize`, `/correct`, and `/reset`; Administration page `#Admin/holidayManagementProfiles`.

- [ ] Extend contract tests for exact routes, action classes, admin checks, parsed request bodies, and the Administration view endpoint usage.
- [ ] Run contract tests; expect missing route/action/view failures.
- [ ] Implement thin API actions that reject non-admin users and validate body shapes before calling the service.
- [ ] Implement the bulk setup table with entitlement, opening balance, and reset-date inputs plus a save action using unique operation keys.
- [ ] Add the Administration panel item and re-run contract/PHP checks; expect success.

### Task 6: Localization, packaging, and Docker lifecycle test

**Files:**
- Modify: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/en_US/Admin.json`
- Modify: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/ro_RO/Admin.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/en_US/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/en_US/HolidayLedger.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/ro_RO/HolidayProfile.json`
- Create: `HolidayManagement/files/custom/Espo/Modules/HolidayManagement/Resources/i18n/ro_RO/HolidayLedger.json`
- Create: `HolidayManagement/tests/phase-002/docker.ps1`
- Modify: `HolidayManagement/manifest.json`
- Modify: `HolidayManagement/README.md`
- Modify: `HolidayManagement/tests/phase-001/contract.test.mjs`

**Interfaces:**
- Produces: bilingual PHASE-002 UI, version `1.1.0`, upgrade/install/concurrency test harness, updated package documentation.

- [ ] Extend contract tests to require all entity/admin translations, version `1.1.0`, phase non-goals, and Docker upgrade/concurrency assertions.
- [ ] Run contract tests; expect translation/version/harness failures.
- [ ] Add translations and Docker test steps that install the PHASE-001 archive, upgrade to PHASE-002, bulk-initialize a user, verify ledger audit data, retry an idempotency key, and submit two simultaneous corrections.
- [ ] Update manifest and README, relax the old PHASE-001 version assertion to accept later `1.x` releases, and build the portable ZIP.
- [ ] Run all Node tests, the PHP arithmetic test, PHP lint, JSON parsing, raw ZIP-entry validation, and `git diff --check`; expect zero failures.
