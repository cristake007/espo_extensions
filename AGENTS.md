# Project Agent Instructions

## Upload Field UI Convention

- Use the Generator Perioade Cursuri upload field as the canonical upload presentation for extensions in this repository:
  - `GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/src/views/fields/source-file.js`
  - `GeneratorPerioadeCursuri/files/client/custom/modules/generator-perioade-cursuri/css/generator-perioade-cursuri.css`
- Present editable file inputs as a modern, full-width drop zone with a clear file-picker action, drag-and-drop feedback, and visible allowed-file-type badges.
- Extend EspoCRM's native `views/fields/file` view and preserve its upload lifecycle, attachment handling, validation, and metadata-driven `accept` attribute. Do not replace native upload behavior with a custom backend endpoint unless the user explicitly requests backend work.
- Follow the TUVTK theme vocabulary for upload components. Source colors only from these custom-theme tokens:
  - `--tuvtk-primary`
  - `--tuvtk-secondary`
  - `--tuvtk-hover`
  - `--tuvtk-surface`
  - `--tuvtk-border`
  - `--tuvtk-border-soft`
  - `--tuvtk-text`
  - `--tuvtk-muted`
  - `--tuvtk-label`
- Do not add literal hex, RGB, RGBA, or independent brand colors to upload-field CSS.
- Match the TUVTK compact visual language: 3px radii, surface-colored controls, primary action text, secondary hover text, theme borders, and no decorative component shadows.
- Prefer EspoCRM classes such as `btn btn-default`, `text-primary`, `text-muted`, and `label label-default`. Add narrowly scoped declarations using the TUVTK variables when the theme's generic `!important` selectors would otherwise override component semantics.
- Keep upload fields keyboard accessible, responsive, and visually clear during drag-over, focus, selected-file, validation, and removal states.
