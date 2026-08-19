# TUVTK Theme

Installable EspoCRM extension containing the custom `Tuvtk` theme used by
`intern.cursurituv.ro`.

The package installs these files:

```text
custom/Espo/Custom/Resources/metadata/themes/Tuvtk.json
client/css/espo/tuvtk.css
client/css/espo/tuvtk-iframe.css
```

## Build

From the repository root:

```bash
./build.sh --extension ./TuvtkTheme --zip 1.0.0 files
```

The resulting ZIP can be uploaded from **Administration > Extensions** in
EspoCRM. After installation, run **Administration > Rebuild** and clear the
browser cache. The theme is then available as `Tuvtk` in EspoCRM's appearance
settings.

Use the `tuvtk-theme-1.0.0.zip` release asset when downloading the extension
from GitHub. Do not upload GitHub's automatically generated **Source code** ZIP;
it contains a parent repository directory, so EspoCRM cannot find
`manifest.json` at the archive root.
