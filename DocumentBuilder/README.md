# Document Builder

Document Builder is an EspoCRM 10.0.2 extension package.

## Canonical identity

| Purpose | Value |
|---|---|
| Display name | `Document Builder` |
| Extension root | `DocumentBuilder` |
| Manifest name | `Document Builder` |
| Backend module | `DocumentBuilder` |
| Backend namespace | `Espo\Modules\DocumentBuilder` |
| Frontend module | `document-builder` |
| Package ID | `document-builder` |

Backend module code and metadata belong under:

```text
files/custom/Espo/Modules/DocumentBuilder/
```

Frontend code belongs under:

```text
files/client/custom/modules/document-builder/
```

Native EspoCRM entity overrides, when a supported module-local extension point is insufficient, belong under:

```text
files/custom/Espo/Custom/Resources/
```

## Compatibility

- EspoCRM: `10.0.2` only.
- PHP: `>=8.3.0 <8.6.0`, matching EspoCRM 10.0.2.
- Other EspoCRM releases are unsupported until explicitly validated.

## Build

From the repository root, build the installable ZIP with:

```bash
./build.sh --extension ./DocumentBuilder --zip 1.0.0 files scripts
```

The version argument must match `manifest.json`. The generated package is written to `dist/document-builder-1.0.0.zip`.
