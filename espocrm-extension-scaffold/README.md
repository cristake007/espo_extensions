# EspoCRM Extension Scaffold

This folder is a minimal reference scaffold for EspoCRM extension packages.

New extensions should now be generated from the repository root with `build.sh`, rather than by copying this folder manually.

## Create a new extension

From the repository root:

```bash
./build.sh
```

or:

```bash
./build.sh --new
```

The script asks for an extension display name and creates a new extension folder in the repository root.

Example input:

```text
Enhanced Contacts
```

Generated structure:

```text
EnhancedContacts/
├── manifest.json
├── README.md
├── files/
│   ├── custom/Espo/Modules/EnhancedContacts/
│   │   ├── Controllers/.gitkeep
│   │   ├── Entities/.gitkeep
│   │   ├── Hooks/.gitkeep
│   │   ├── Tools/.gitkeep
│   │   ├── Classes/FieldValidators/.gitkeep
│   │   └── Resources/
│   │       ├── module.json
│   │       ├── metadata/
│   │       │   ├── entityDefs/.gitkeep
│   │       │   ├── scopes/.gitkeep
│   │       │   ├── clientDefs/.gitkeep
│   │       │   ├── recordDefs/.gitkeep
│   │       │   └── aclDefs/.gitkeep
│   │       ├── layouts/.gitkeep
│   │       └── i18n/
│   │           ├── en_US/Global.json
│   │           └── ro_RO/Global.json
│   └── client/custom/modules/enhanced-contacts/
│       └── src/.gitkeep
└── scripts/
    └── .gitkeep
```

## Existing entity augmentation

Installable extensions augment existing EspoCRM entities through their own module metadata. The `custom/Espo/Custom` tree is reserved for instance-specific customization and is not generated.

Example Contact test field:

```text
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/entityDefs/Contact.json
```

```json
{
    "fields": {
        "tuvtkTestInput": {
            "type": "varchar",
            "maxLength": 100,
            "tooltip": true
        }
    }
}
```

Labels:

```text
files/custom/Espo/Modules/EnhancedContacts/Resources/i18n/en_US/Contact.json
files/custom/Espo/Modules/EnhancedContacts/Resources/i18n/ro_RO/Contact.json
```

```json
{
    "fields": {
        "tuvtkTestInput": "TUVTK Test Input"
    }
}
```

The same module metadata locations are used for new custom entities and existing-entity augmentation:

```text
files/custom/Espo/Modules/<ModuleName>/Resources/metadata/entityDefs
files/custom/Espo/Modules/<ModuleName>/Resources/metadata/scopes
files/custom/Espo/Modules/<ModuleName>/Resources/metadata/clientDefs
files/custom/Espo/Modules/<ModuleName>/Resources/metadata/recordDefs
files/custom/Espo/Modules/<ModuleName>/Resources/metadata/aclDefs
```

## Build an installable ZIP

Validate first:

```bash
./build.sh --extension ./EnhancedContacts --validate
```

From the repository root:

```bash
./build.sh --extension ./EnhancedContacts --zip 1.0.0 files scripts
```

You can also pass an absolute extension path:

```bash
./build.sh --extension /opt/DemoExtension --zip 1.0.1 files scripts
```

This creates the ZIP in:

```text
dist/<extension-folder-name>-<version>.zip
```

The ZIP root contains:

```text
manifest.json
README.md, if present
files/
scripts/, if requested
```

Do not zip the parent folder itself. EspoCRM must see `manifest.json` immediately at the ZIP root.

## Naming rules

Use three names consistently:

| Purpose | Example |
| --- | --- |
| Extension folder | `EnhancedContacts` |
| Manifest display name | `Enhanced Contacts` |
| PHP module folder | `EnhancedContacts` |
| Frontend module folder | `enhanced-contacts` |

Recommended naming rules:

- Use `PascalCase` for PHP/module folders under `files/custom/Espo/Modules/`.
- Use `kebab-case` for frontend folders under `files/client/custom/modules/`.
- Keep the manifest `name` readable, with spaces allowed.
- Keep the ZIP filename lowercase and versioned.

## Add extension files

Put files under `files/` using the same relative path they should have inside EspoCRM.

Examples:

```text
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/entityDefs/ContactEmail.json
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/scopes/ContactEmail.json
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/clientDefs/ContactEmail.json
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/aclDefs/ContactEmail.json
files/custom/Espo/Modules/EnhancedContacts/Controllers/ContactEmail.php
files/custom/Espo/Modules/EnhancedContacts/Entities/ContactEmail.php
files/custom/Espo/Modules/EnhancedContacts/Hooks/Contact/SomeHook.php
files/custom/Espo/Modules/EnhancedContacts/Tools/Contacts/SomeService.php
files/client/custom/modules/enhanced-contacts/src/views/some-view.js
files/custom/Espo/Modules/EnhancedContacts/Resources/metadata/entityDefs/Contact.json
files/custom/Espo/Modules/EnhancedContacts/Resources/layouts/Contact/detail.json
```

Avoid placing generated ZIP files inside `files/`.

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
- Run `--validate` before packaging; ZIP builds also run validation automatically.
