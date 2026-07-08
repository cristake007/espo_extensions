# Planificari perioade cursuri

This is the EspoCRM extension package scaffold for `Planificari perioade cursuri`.

Package ID: `planificari-perioade-cursuri`

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
cd /opt/crm.cursurituv.ro/extensions/Planificari
zip -r ../planificari-perioade-cursuri-1.0.41.zip manifest.json files scripts
```

After installing or changing module metadata, rebuild EspoCRM cache.
