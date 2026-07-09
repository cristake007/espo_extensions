# Planificari perioade cursuri

This is the EspoCRM extension package scaffold for `Planificari perioade cursuri`.

Package ID: `planificari-perioade-cursuri`

Current package version: `1.0.44`

Package source:

- `manifest.json` contains extension metadata.
- `files/` contains files copied into the EspoCRM root when the extension is installed.
- `scripts/` contains install and uninstall hooks.

The module code lives under:

```text
files/custom/Espo/Modules/Planificari
```

To build an installable ZIP from this directory:

```bash
cd /source_folder
zip -r ../planificari-perioade-cursuri-1.0.44.zip manifest.json files scripts
```

After installing or changing module metadata, rebuild EspoCRM cache.

```bash
cd /var/www/html
php command.php rebuild
rm -rf data/cache/*
```
