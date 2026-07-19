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
php tests/phase-09/processing.test.php
php tests/phase-10/metadata.test.php
php tests/phase-10/hooks.test.php
php tests/phase-10/acl.test.php
php tests/phase-11/metadata.test.php
php tests/phase-11/snapshot.test.php
php tests/phase-11/hooks.test.php
php tests/phase-11/acl.test.php
php tests/phase-12/contracts.test.php
php tests/phase-12/service.test.php
php tests/phase-13/contracts.test.php
php tests/phase-13/service.test.php
php tests/phase-14/contracts.test.php
php tests/phase-14/service.test.php
node tests/phase-15/reference-integrity.test.js
node tests/phase-16/state.test.js
node tests/phase-17/save.test.js
node tests/phase-18/geometry.test.js
node tests/phase-19/flow.test.js
php tests/phase-19/server-validation.test.php
node tests/phase-20/content.test.js
php tests/phase-20/server-validation.test.php
node tests/phase-21/elements.test.js
php tests/phase-21/server-validation.test.php
node tests/phase-22/style.test.js
php tests/phase-22/server-validation.test.php
node tests/phase-23/renderer-validation.test.js
php tests/phase-23/contracts.test.php
php tests/phase-24/catalogue.test.php
node tests/phase-24/client.test.js
php tests/phase-24/contracts.test.php
php tests/phase-25/catalogue.test.php
node tests/phase-25/client.test.js
php tests/phase-25/contracts.test.php
php tests/phase-26/compiler.test.php
node tests/phase-26/client.test.js
php tests/phase-26/contracts.test.php
php tests/phase-27/formatting.test.php
node tests/phase-27/client.test.js
php tests/phase-27/contracts.test.php
node tests/editor-recovery/recovery-01.test.js
php tests/editor-recovery/recovery-01.test.php
node tests/editor-recovery/recovery-02.test.js
node tests/editor-recovery/recovery-03.test.js
node tests/editor-recovery/recovery-04.test.js
node tests/editor-recovery/recovery-05.test.js
node tests/editor-recovery/recovery-06.test.js
node tests/editor-recovery/correction-01.test.js
```

Package inventory is checked separately after building the extension ZIP:

```sh
php tests/phase-02/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-06/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-07/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-08/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-09/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-10/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-11/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-12/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-13/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-14/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-15/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-16/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-17/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-18/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-19/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-20/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-21/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-22/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-23/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-24/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-25/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-26/package-inventory.test.php dist/document-builder-1.0.0.zip
php tests/phase-27/package-inventory.test.php dist/document-builder-1.0.0.zip
```

Phase 15–27 runtime validation remains pending until an approved non-production EspoCRM 10.0.0 instance is provided. Install the rebuilt ZIP there, clear cache, run Administration > Rebuild, then exercise editor entry, save/reload/conflict protection, page settings, flow drag/drop, restricted rich-text paste and formatting, stable inline-variable insertion, bounded variable presentation controls, divider/spacer/page-break rendering and reordering, inherited styling/typography and Romanian diacritics, sample/empty rendering, validation issue focus, keyboard traversal, complex-node deletion confirmation, ACL-filtered standard/custom entity source selection, source-change confirmation, readable field/link browsing, metadata search, circular/depth-limited expansion, label-independent variable references, missing-value policies, and literal XSS/CSS payload display as inert or rejected data. Never use `/opt/crm.cursurituv.ro` for these checks.

Phase 14 runtime validation requires an approved non-production EspoCRM 10.0.0 instance. After install, Clear Cache and run Administration > Rebuild. Duplicate a marked template and verify the copy is a revision-zero draft with the design/source/assignment projection but no published versions or generation history. Archive a published template and verify it becomes inactive while its version panel and immutable records remain intact. Create a draft from both the current and an older published version, verify revision increment and restored layout/source summaries, then confirm every historical version is byte-for-byte unchanged. Exercise stale revisions and users lacking design, publish, record-edit, or version-read access. Confirm normal and mass hard-delete remain unavailable. Generated-document navigation is intentionally deferred until the `DocumentBuilderDocument` scope is introduced in Phase 36 and its template workflow in Phase 38. Never use the production path.

Phase 13 runtime validation requires an approved non-production EspoCRM 10.0.0 instance. After install, Clear Cache and run Administration > Rebuild. Publish a marked source-neutral draft with `POST api/v1/DocumentBuilder/template/{id}/publish` and its current `expectedRevision`; verify exactly one immutable current version exists, its checksum/publisher/time/change note and ACL projection are populated, the template points to it, and native audit history records the status/current-version switch. Retry the same request and expect HTTP 409, verify stale revisions return HTTP 409 and unauthorized publishers return HTTP 403. Exercise an injected persistence failure in a test adapter and verify no version/current-marker/template-status changes survive. Entity, spreadsheet, media, variable, and other schema-only capabilities must remain blocked until their implementation phase. Never use the production path.

Phase 12 runtime validation requires an approved non-production EspoCRM 10.0.0 instance. After install, Clear Cache and run Administration > Rebuild. With a marked draft and an authorized designer, call `PUT api/v1/DocumentBuilder/template/{id}/draft` using the current revision; verify the normalized layout and incremented revision. Repeat with the stale revision and expect HTTP 409. Change `dataSource`, verify the first request returns the impact report without changing the record, then retry with `confirmSourceChange: true`. Verify an unauthorized user receives HTTP 403 and a Published or Archived template rejects the save. Never use the production path.

Phase 11 runtime validation requires an approved non-production EspoCRM 10.0.0 instance. After install and Administration > Rebuild, verify the version table and unique template/version index exist. Create a marked version only through a temporary publication-service harness, then verify it remains readable after changing the draft, normal REST create/update/delete requests are forbidden, and own/team readers follow the publication-time ACL projection. Remove only records carrying the exact test marker.

Phase 10 runtime validation requires an approved non-production EspoCRM 10.0.0 instance. Install the package, run Administration > Rebuild, then verify that an administrator can create a source-neutral draft, that its layout/default summary/revision match the Phase 10 metadata test, and that an ungranted role cannot read or write the record. Also verify own/team scope behavior with two marked test users. Do not create records on the production path.

Phase 04 PDF rendering is a manual runtime check. It may be run only with an approved non-production EspoCRM 10.0.0 instance and a new output directory, following `docs/phase-04-renderer-feasibility.md`.

## Fixture policy

`fixtures/catalogue.json` is the complete inventory. All fixtures are original, compact project test data. Security payloads are escaped inert strings and must never be evaluated, fetched, decoded as active content, or sent to a real target. Large-resource cases use declared sizes and counts rather than large committed files. Acceptance scenarios name capabilities and expected outcomes; they are intentionally not the production layout schema.
