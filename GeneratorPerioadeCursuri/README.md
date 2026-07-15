# Generator perioade cursuri

This is the EspoCRM extension package for `Generator perioade cursuri`.

Package ID: `generator-perioade-cursuri`

Current package version: `2.1.0`

Package source:

- `manifest.json` contains extension metadata.
- `files/` contains files copied into the EspoCRM root when the extension is installed.
- `scripts/` contains install and uninstall hooks.

The module code lives under:

```text
files/custom/Espo/Modules/GeneratorPerioadeCursuri
```

To build an installable ZIP from the repository root:

```bash
bash build.sh --extension GeneratorPerioadeCursuri --zip files scripts
```

The ZIP version is read from `manifest.json`.

After installing or changing module metadata, rebuild EspoCRM cache.

```bash
cd /var/www/html
php command.php rebuild
rm -rf data/cache/*
```
