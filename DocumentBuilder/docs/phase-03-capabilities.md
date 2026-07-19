# Phase 03 bundled capability contract

## Evidence boundary

This contract is pinned to the EspoCRM 10.0.0 tag object `debc6b75bd9177259fb14b99cef93d8cd5d88c5b` and peeled commit `2cde9d980f84cfc3caa1adf3275a0817e1e49bfa`. The extension manifest accepts `>=10.0.0` by product-owner decision, but the versions and loading paths below are certified only for the 10.0.0 baseline.

Document Builder does not ship Composer or npm manifests and does not copy these libraries into its package. It reuses only libraries already supplied and loaded by EspoCRM. License identifiers below are inventory facts from the locked package metadata or pinned package source, not legal advice.

## Server libraries

EspoCRM's root `vendor/autoload.php` exposes each locked package through Composer. Extension classes use the listed namespace; they must not include files from `vendor/` directly.

| Capability | Locked package | License | Loading contract | Decision |
|---|---|---|---|---|
| PDF engine | `dompdf/dompdf` `v3.1.5` | LGPL-2.1 | Composer namespace `Dompdf\\` | Reuse behind the Document Builder PDF adapter. Phase 04 decides whether Espo's template-coupled initializer can be reused and proves rendering behavior. |
| CSV/XLSX | `phpoffice/phpspreadsheet` `5.7.0` | MIT | Composer namespace `PhpOffice\\PhpSpreadsheet\\` | Reuse. Reader selection, formula handling, archive limits, and malicious-file behavior remain Phase 69 work. |
| QR generation | `chillerlan/php-qrcode` `5.0.5` | MIT OR Apache-2.0 | Composer namespace `chillerlan\\QRCode\\` | Selected for local server QR generation. Output format and PDF readability remain Phase 50 checks. |
| Linear barcode generation | `picqer/php-barcode-generator` `v3.2.4` | LGPL-3.0-or-later | Composer namespace `Picqer\\Barcode\\` | Available candidate only. No barcode type is approved until Phase 50 proves matching server/browser support; QR remains mandatory first. |

The exact requirements, versions, licenses, source references, and PSR-4 mappings are recorded in the pinned [`composer.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.json#L37-L49) and [`composer.lock`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/composer.lock).

## Client libraries

Espo builds the aliases in `app > jsLibs` into its loader configuration. Registered names can be used as AMD/import dependencies; lazy loading uses `Espo.loader.requirePromise('lib!name')`. Product code must not load `client/lib` paths or create script tags itself.

| Capability | Locked package | License | Supported load form | Decision |
|---|---|---|---|---|
| Browser sanitization | `dompurify` `3.4.11` | MPL-2.0 OR Apache-2.0 | `import DOMPurify from 'dompurify'` | Selected as client-side defense in depth. It never replaces the Phase 20 server allowlist and canonical rich-text normalization. |
| Rich-text input | `summernote` `0.9.1` | MIT | Prefer Espo `views/fields/wysiwyg`; it loads `lib!summernote` and accepts a custom toolbar | Selected as the editing widget with a restricted toolbar and no raw-code control. Server sanitization remains authoritative. |
| Palette drag and ordered flow moves | `@shopify/draggable` `1.1.4` | MIT | `import {Draggable, Sortable} from '@shopify/draggable'` | Selected for structural drag/reorder. It has cancelable drag events but no resize primitive; Document Builder state and commands remain authoritative. |
| Freeform pointer move/resize | `jquery-ui-espo` `0.2.3`, built from `jquery-ui` `1.13.2` | ISC for the Espo wrapper; MIT for jQuery UI | Import/AMD dependency `jquery-ui`, or `lib!jquery-ui`; widgets attach to jQuery | Selected as the bounded pointer adapter because Espo's pinned build contains draggable, droppable, resizable, and sortable. It does not own stored geometry, snapping, zoom conversion, rotation, selection, history, or keyboard behavior. |
| Grid engine candidate | Espo fork of `gridstack` `5.1.1` at `f833a4d4bde2eff9efc66c5265714d3b6f514097` | MIT | `import GridStack from 'gridstack'` | Not selected. Its integer column/cell collision and reflow engine would become a second layout authority and does not model freeform millimetre geometry. |
| Browser QR preview | `qrcodejs` `1.0.0` | MIT | `Espo.loader.requirePromise('lib!qrcodejs')` | Selected for preview only. Phase 50 must compare encoded values and readable output with the server renderer. |
| Browser barcode preview | `jsbarcode` `3.11.4` | MIT | `Espo.loader.requirePromise('lib!jsbarcode')` | Available candidate only. Enabled formats must be the intersection proven with Picqer in Phase 50. |

The registry and output paths are pinned in [`app/jsLibs.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/application/Espo/Resources/metadata/app/jsLibs.json) and [`frontend/libs.json`](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/frontend/libs.json). Espo's own consumers prove the supported forms for [DOMPurify](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/ui.ts#L28-L34), [Draggable](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/helpers/list/misc/list-tree-draggable.js#L26-L31), [GridStack](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/views/dashboard.js#L28-L34), [Summernote](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/views/fields/wysiwyg.ts#L185-L196), and [QR/barcodes](https://github.com/espocrm/espocrm/blob/2cde9d980f84cfc3caa1adf3275a0817e1e49bfa/client/src/views/fields/barcode.js#L65-L82).

The freeform decision also uses the exact Espo jQuery UI build definition at commit [`9529ed989d7c7da0f5cc49d7d7ab91a8e04850e1`](https://github.com/yurikuzn/jquery-ui-espo/blob/9529ed989d7c7da0f5cc49d7d7ab91a8e04850e1/Gruntfile.js), which includes only `draggable`, `droppable`, `resizable`, and `sortable`. The GridStack rejection is based on Espo's locked fork commit and its integer column/cell model, not on the mere presence of the library.

## License evidence

- Server licenses and source commits come directly from EspoCRM's `composer.lock`.
- DOMPurify and the two Espo Git dependencies expose their licenses in `package-lock.json` or their pinned source.
- Exact npm package metadata records MIT for Shopify Draggable, jQuery UI, JsBarcode, QRCode.js, and Summernote. Espo's lock fixes their versions and package integrity or source commit.
- Document Builder does not redistribute separate copies or alter license files. Release packaging must continue to exclude dependency source and generated client bundles.

## Deferred proof

This phase verifies availability, version, loader reachability, license inventory, and an interaction ownership decision. It does not claim:

- PDF layout, font, image, SVG, pagination, or QR readability behavior;
- spreadsheet safety or supported workbook features;
- server-side rich-text sanitizer correctness;
- browser performance with 200 elements, touch behavior, accessibility, or browser compatibility;
- barcode format parity;
- runtime availability on any EspoCRM release other than the pinned 10.0.0 source baseline.

Those claims remain assigned to their named feasibility or feature phases and, where necessary, the approved non-production runtime.
