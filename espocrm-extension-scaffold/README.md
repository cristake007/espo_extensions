# EspoCRM Extension Scaffold

This folder is a minimal scaffold for a new EspoCRM extension package.

It is intentionally separate from the extension already under development in this repository. Copy or rename this folder when starting a new extension.

## Structure

```text
espocrm-extension-scaffold/
├── manifest.json
├── README.md
├── build.sh
└── files/
    ├── custom/Espo/Modules/ExampleExtension/
    │   └── Resources/
    │       ├── metadata/app/module.json
    │       └── i18n/
    │           ├── en_US/Global.json
    │           └── ro_RO/Global.json
    └── client/custom/modules/example-extension/
        └── src/.gitkeep
```

EspoCRM extension ZIP packages must contain `manifest.json` at the root of the ZIP. The `files/` directory is the payload that EspoCRM copies into the application.

## Rename the extension

Use three names consistently:

| Purpose | Current value | Example replacement |
| --- | --- | --- |
| Package folder | `espocrm-extension-scaffold` | `planificari-reports` |
| Manifest display name | `Example Extension Scaffold` | `Planificari Reports` |
| PHP module folder | `ExampleExtension` | `PlanificariReports` |
| Frontend module folder | `example-extension` | `planificari-reports` |

Recommended naming rules:

- Use `PascalCase` for PHP/module folders under `files/custom/Espo/Modules/`.
- Use `kebab-case` for frontend folders under `files/client/custom/modules/`.
- Keep the manifest `name` readable, with spaces allowed.
- Keep the ZIP filename lowercase and versioned, for example `planificari-reports-1.0.0.zip`.

### Files to change when renaming

1. Rename this folder:

   ```bash
   mv espocrm-extension-scaffold planificari-reports
   ```

2. Edit `manifest.json`:

   ```json
   {
     "name": "Planificari Reports",
     "version": "1.0.0",
     "description": "EspoCRM extension for Planificari reports.",
     "author": "TUVTK",
     "acceptableVersions": [
       ">=8.0.0"
     ],
     "releaseDate": "2026-07-09"
   }
   ```

3. Rename the backend module folder:

   ```bash
   mv files/custom/Espo/Modules/ExampleExtension files/custom/Espo/Modules/PlanificariReports
   ```

4. Rename the frontend module folder if the extension has frontend code:

   ```bash
   mv files/client/custom/modules/example-extension files/client/custom/modules/planificari-reports
   ```

5. Update labels in:

   ```text
   files/custom/Espo/Modules/<ModuleName>/Resources/i18n/en_US/Global.json
   files/custom/Espo/Modules/<ModuleName>/Resources/i18n/ro_RO/Global.json
   ```

6. Update any PHP namespaces, metadata paths, JS imports, view names, and loader references that still contain the old name.

## Add extension files

Put files under `files/` using the same relative path they should have inside EspoCRM.

Examples:

```text
files/custom/Espo/Modules/PlanificariReports/Resources/metadata/entityDefs/SomeEntity.json
files/custom/Espo/Modules/PlanificariReports/Services/SomeService.php
files/client/custom/modules/planificari-reports/src/views/some-view.js
```

Avoid placing generated ZIP files inside `files/`.

## Build the installable ZIP

From inside the extension folder:

```bash
cd espocrm-extension-scaffold
bash build.sh example-extension-scaffold 1.0.0
```

This creates:

```text
../dist/example-extension-scaffold-1.0.0.zip
```

The ZIP root will contain:

```text
manifest.json
README.md
files/
```

Do not zip the parent folder itself. EspoCRM must see `manifest.json` immediately at the ZIP root.

Manual ZIP command, if you do not want to use `build.sh`:

```bash
mkdir -p ../dist
zip -r ../dist/example-extension-scaffold-1.0.0.zip manifest.json README.md files \
  -x "*.git*" \
  -x "*/.DS_Store" \
  -x "__MACOSX/*" \
  -x "dist/*"
```

## Install in EspoCRM

1. Build the ZIP.
2. In EspoCRM, go to **Administration > Extensions**.
3. Upload the generated ZIP.
4. Install the extension.
5. Run **Administration > Rebuild**.
6. Clear browser cache or hard refresh if frontend assets changed.

## Development notes

- Keep scaffold files small and explicit.
- Add only the EspoCRM files needed by the extension.
- Keep generated ZIP files out of Git.
- Prefer one extension folder per extension in this repository.
- Before packaging, check JSON syntax and PHP syntax where applicable.
