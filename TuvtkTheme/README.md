# TUVTK Theme

Installable EspoCRM extension containing the custom `Tuvtk` theme used by
`intern.cursurituv.ro`.

The extension was created with the repository scaffold generator. Its theme
payload installs these files:

```text
custom/Espo/Modules/TuvtkTheme/Resources/metadata/themes/Tuvtk.json
client/custom/css/tuvtk.css
client/custom/css/tuvtk-iframe.css
```

## Build

From the repository root:

```bash
./build.sh --extension ./TuvtkTheme --zip 1.0.1 files scripts
```

The resulting `dist/tuvtk-theme-1.0.1.zip` can be uploaded from
**Administration > Extensions** in EspoCRM. After installation, run
**Administration > Rebuild** and clear the browser cache. The theme is then
available as `Tuvtk` in EspoCRM's appearance settings.

Use the `tuvtk-theme-1.0.1.zip` release asset when downloading the extension
from GitHub. GitHub's automatically generated **Source code** ZIP is a copy of
the whole repository and is not an EspoCRM installation package.
