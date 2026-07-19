# Phase 02 lifecycle contract

## Installed configuration

`AfterInstall.php` registers one `tabList` group with stable ID `document-builder`, translated label `Document Builder`, and an empty `itemList`. The empty group is structural: EspoCRM 10.0.2 filters it from the rendered navbar until later phases add ACL-visible entity scopes.

Installation preserves the order and value of every unrelated `tabList` entry. A stale or duplicate group owned by the same stable ID is replaced by one canonical group. Repeated installation does not write the config again when the canonical group is already present.

## Upgrade behavior

EspoCRM extension upgrades invoke the installed package's `BeforeUninstall.php` before installing the new package. The removal and subsequent registration are therefore both required to be idempotent. Neither script relies on application entities or module classes that may be absent at its point in the lifecycle.

## Uninstall preservation policy

`BeforeUninstall.php` removes only `tabList` objects whose `id` is exactly `document-builder`. A user-created group with the same label but another ID is unrelated and remains unchanged.

Lifecycle scripts must never:

- query, update, or delete Document Builder business records;
- query, update, unlink, or delete EspoCRM Attachment records or stored files;
- drop tables, columns, indexes, or other schema objects;
- remove unrelated config entries, navigation items, metadata, or files;
- run cleanup based only on an entity-name prefix or translated label.

Template versions, snapshots, generation history, generated PDFs, and media attachments remain preserved across uninstall. The core extension manager removes packaged module files and rebuilds metadata; it does not authorize Document Builder scripts to remove business data.

## Pending non-production smoke checklist

Do not run this checklist on `/opt/crm.cursurituv.ro`. It remains pending until the product owner supplies a separate non-production instance.

1. Confirm `bin/command app-info --core-version` reports `10.0.2` and record the test instance path.
2. Back up or confirm disposability of the test instance, including its database and configured file storage.
3. Install `dist/document-builder-1.0.0.zip` through Administration > Extensions or the documented extension CLI command.
4. Confirm installation and rebuild complete without errors and the module appears as `Document Builder`.
5. Inspect `tabList`: exactly one group has ID `document-builder`; all prior entries retain their order and values.
6. Reinstall the same package and confirm the owned group is not duplicated.
7. Uninstall the extension and confirm the owned group is removed while all unrelated entries remain unchanged.
8. Once later phases add persistent records and attachments, repeat uninstall/reinstall and verify their record counts, IDs, checksums, and stored bytes are unchanged.
9. Reinstall once more and confirm the preserved state is readable after rebuild.

No runtime result is claimed until this checklist is executed on the approved instance.
