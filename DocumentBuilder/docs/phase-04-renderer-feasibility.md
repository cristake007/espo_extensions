# Phase 04 renderer feasibility matrix

## Evidence boundary

This phase is pinned to EspoCRM 10.0.0 commit `2cde9d980f84cfc3caa1adf3275a0817e1e49bfa` and its locked Dompdf `v3.1.5` commit `f11ead23a8a76d0ff9bbc6c7c8fd7e05ca328496`. Source inspection is complete. No fixture has been rendered on an approved non-production EspoCRM instance, so all output, visual, extraction, and performance results remain pending.

The fixtures are risk probes, not production templates or a renderer implementation. They contain no source-record data, network references, executable content, raw user HTML, or remote resources.

## Renderer initialization decision

The production adapter will inject Espo's `Espo\Tools\Pdf\Dompdf\DompdfInitializer` and supply a narrow implementation of `Espo\Tools\Pdf\Template` containing validated page geometry and font choices. This is viable because the initializer depends on the interface, creates a fresh Dompdf instance, applies Espo font configuration and chroot, disables JavaScript, and sets paper size/orientation. Document Builder must still keep `isRemoteEnabled`, `isPhpEnabled`, and `isJavascriptEnabled` false.

Attachment and generated media bytes will be passed as validated data URIs, matching Espo's `ImageSourceProvider` and QR/barcode composition strategy. Product code will not add arbitrary file paths to Dompdf's chroot and will not enable remote fetching.

## Capability matrix

| Capability | Source evidence | Fixture | Acceptance criterion | Current status and decision |
|---|---|---|---|---|
| Romanian text and fonts | Espo defaults to DejaVu Sans; Dompdf's locked UFM contains `ĂăÂâÎîȘșȚț` code points | `romanian-flow-pagination.html` | All listed glyphs extract and render without replacement characters; text remains selectable | Source-supported; runtime pending |
| Natural flow pagination | Dompdf page reflower implements automatic and forced breaks | `romanian-flow-pagination.html` | At least two pages; no clipped paragraph; stable page count over two fresh renders | Source-supported; runtime pending |
| Conservative grid | Dompdf supports HTML tables and explicitly does not support CSS Grid or Flexbox | `table-grid-long-row.html` | Column widths within 1 mm of the reference; borders align; no flex/grid CSS | Table translation selected; runtime pending |
| Oversized table row | Dompdf documents that table cells are not pageable | `table-grid-long-row.html` | Oversized-row behavior is recorded; the row is never claimed to split safely | Known limitation: publication must reject a row taller than the printable page or use an explicitly designed fallback |
| Freeform millimetre geometry | Dompdf converts `mm` units and has relative/absolute positioners | `freeform-mm.html` | Reference edges within 1 mm; no child escapes the section; rotation reference within 1 degree | Source-supported; runtime pending |
| Fixed header/footer | Dompdf clones fixed children for later pages; Espo's native composer uses fixed header/footer CSS | `header-footer-counters.html` | Header/footer appear once on every page and stay within 1 mm of their reference positions | Source-supported; runtime pending |
| Current/total page number | Espo uses CSS `counter(page)` for current page; CPDF `page_text` replaces `{PAGE_NUM}` and `{PAGE_COUNT}` after layout | `header-footer-counters.html` | Exactly three pages labelled `Page 1 of 3` through `Page 3 of 3` | Canvas overlay selected for total count; runtime pending |
| PNG/JPEG | Dompdf supports both directly; Espo converts attachment bytes to data URIs | `media-data-uri.html` | Both render at the requested box within 1 mm, without broken-image fallback | Source-supported; runtime pending |
| WebP | Dompdf recognizes WebP and CPDF converts it through GD | `media-data-uri.html` | WebP renders with the expected colors/dimensions; runtime must confirm `imagewebp` support | Source-supported with GD; runtime pending |
| Sanitized SVG candidate | Dompdf supports SVG through an image/data URI but documents raw inline SVG as unsupported; SVG resource references are checked | `media-data-uri.html` | Sanitized, reference-free SVG data URI renders; raw inline SVG and referenced resources remain prohibited | Data-URI candidate selected; sanitizer proof remains Phase 52; runtime pending |
| Local QR | Espo 10.0.0 already composes chillerlan QR output as an SVG image source | `media-data-uri.html` | Encoded fixture value scans exactly and no URL is fetched | Local SVG data-URI path selected; readability runtime pending |
| Explicit page breaks | Dompdf implements `page-break-before`, `page-break-after`, and avoidance rules | `page-breaks.html` | Exactly three ordered pages; keep-together probe outcome recorded | Source-supported; runtime pending |

## Hard constraints carried forward

- Use a fresh Dompdf instance for every document; Dompdf documents state leakage when an instance renders more than one HTML document.
- Never emit CSS Grid or Flexbox to the PDF renderer. Grid sections translate to deterministic tables or percentage-width blocks.
- Absolute positioning is valid only inside a bounded freeform section. Flow content remains in normal layout.
- Treat a table row as indivisible. If its measured content can exceed printable height, publication must fail or use a separately specified fallback; silent clipping is not acceptable.
- Use post-layout CPDF canvas text for total page count. CSS `counter(page)` alone is not accepted as proof of `{PAGE_COUNT}`.
- Raw inline SVG is not supported. Only server-sanitized, reference-free SVG bytes represented through the approved image path may progress to the SVG security phase.
- Remote and PHP execution remain disabled. Fixture and production HTML must contain no remote URLs, script elements, `@import`, or user-supplied CSS.

## Pending runtime procedure

Do not run the runtime harness on `/opt/crm.cursurituv.ro`. On a separately approved EspoCRM 10.0.0 instance:

1. Confirm the instance is disposable or backed up and record its path and URL.
2. Run `php DocumentBuilder/tests/phase-04/render-fixtures.php /path/to/non-production-espo /new/output/directory` from this repository.
3. Preserve the emitted `runtime-results.json`, PDFs, environment details, and SHA-256 hashes outside the extension package.
4. Render every fixture twice with fresh instances and compare page counts and hashes. Hash differences require visual/content comparison; they are not automatically failures.
5. Inspect Romanian glyphs and extracted text, geometry against the stated tolerances, page labels, image fallbacks, WebP/SVG output, QR readability, table overflow, and ordered page breaks.
6. Record measured values and pass/fail results in this matrix before any downstream phase treats runtime behavior as supported.

The harness bootstraps Espo to obtain the real initializer and may populate Espo's Dompdf font cache. It creates only the new requested output directory and does not query or mutate business records.
