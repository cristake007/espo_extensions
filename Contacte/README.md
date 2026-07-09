# contacte

EspoCRM extension package for `contacte`.

Package ID: `contacte`

Module code lives under:

```text
files/custom/Espo/Modules/Contacte
```

Module ACL files can be placed under:

```text
files/custom/Espo/Modules/Contacte/Resources/metadata/aclDefs
```

Native EspoCRM entity overrides can be placed under:

```text
files/custom/Espo/Custom/Resources/metadata/entityDefs
files/custom/Espo/Custom/Resources/metadata/clientDefs
files/custom/Espo/Custom/Resources/metadata/recordDefs
files/custom/Espo/Custom/Resources/metadata/aclDefs
files/custom/Espo/Custom/Resources/layouts
files/custom/Espo/Custom/Resources/i18n
```

Example native Contact field override:

```text
files/custom/Espo/Custom/Resources/metadata/entityDefs/Contact.json
files/custom/Espo/Custom/Resources/i18n/en_US/Contact.json
files/custom/Espo/Custom/Resources/i18n/ro_RO/Contact.json
```

Build an installable ZIP from the repository root:

```bash
./build.sh --extension ./Contacte --zip 1.0.0 files scripts
```
