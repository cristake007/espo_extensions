# Document Builder tests

The test tree is organized by implementation phase. Tests that only inspect source and fixtures run without EspoCRM. Runtime checks always require an explicit, approved non-production path; no helper supplies a default instance.

## Shared foundation

`bootstrap.php` loads the dependency-free helpers in `helpers/`:

- `Assert` provides the small assertion surface used by standalone PHP tests.
- `FixtureLoader` reads only files contained by an explicit fixture root.
- `RuntimeGuard` requires an absolute path, prohibits `/opt/crm.cursurituv.ro`, resolves aliases, and verifies EspoCRM 10.0.0 before runtime use.
- `RuntimeIdentity` creates exact ownership markers for future runtime records.

Runtime adapters must persist the complete marker returned by `RuntimeIdentity::marker()`. Setup may reuse a record only when `owns()` returns true. Teardown must query with every value returned by `cleanupCriteria()` and verify the marker again before removal. A name prefix, run ID, or fixture ID alone is never sufficient authority to delete data.

## Commands

Run the offline Phase 05 foundation check from any working directory:

```sh
php /absolute/path/to/DocumentBuilder/tests/phase-05/foundation.test.php
```

Earlier source-only checks remain independent:

```sh
sh tests/phase-00/check-runtime-contract.sh
sh tests/phase-01/check-identity.sh
php tests/phase-02/lifecycle.test.php
php tests/phase-03/check-capabilities.php /path/to/espocrm-10.0.0-source
php tests/phase-04/check-feasibility.php /path/to/espocrm-10.0.0-source /path/to/dompdf-3.1.5-source
php tests/phase-06/settings.test.php
php tests/phase-07/contracts.test.php
php tests/phase-08/primitives.test.php
```

Package inventory is checked separately after building the extension ZIP:

```sh
php tests/phase-02/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-06/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-07/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-08/package-inventory.test.php dist/document-builder-1.0.0.zip
```

Phase 04 PDF rendering is a manual runtime check. It may be run only with an approved non-production EspoCRM 10.0.0 instance and a new output directory, following `docs/phase-04-renderer-feasibility.md`.

## Fixture policy

`fixtures/catalogue.json` is the complete inventory. All fixtures are original, compact project test data. Security payloads are escaped inert strings and must never be evaluated, fetched, decoded as active content, or sent to a real target. Large-resource cases use declared sizes and counts rather than large committed files. Acceptance scenarios name capabilities and expected outcomes; they are intentionally not the production layout schema.
