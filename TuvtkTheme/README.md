# TUVTK Theme

Installable EspoCRM extension containing the custom `Tuvtk` theme used by
`intern.cursurituv.ro`.

The extension was created with the repository scaffold generator. Its theme
payload installs these files:

```text
custom/Espo/Modules/TuvtkTheme/Resources/metadata/themes/Tuvtk.json
client/custom/css/tuvtk.css
client/custom/css/tuvtk-iframe.css
client/custom/img/tuvtk-login-office.png
```

## Build

From the repository root:

```bash
./build.sh --extension ./TuvtkTheme --zip 1.0.7 files scripts
```

The resulting `dist/tuvtk-theme-1.0.7.zip` can be uploaded from
**Administration > Extensions** in EspoCRM. After installation, run
**Administration > Rebuild** and clear the browser cache. The theme is then
available as `Tuvtk` in EspoCRM's appearance settings.

On desktop login screens, the login form occupies the left 25 percent of the
viewport and the bundled office image fills the right 75 percent. Both regions
use the full `100vh` viewport height. Smaller screens use a single-column login
layout without the image. The form uses an open layout without the default
EspoCRM card chrome or login-page footer.

Side-navbar items use compact, vertically balanced rows. Their red hover and
active marker expands smoothly from the vertical center toward both edges.
The collapse control is an outlined circular handle centered on the sidebar
rail and follows the rail when the navbar is collapsed.

Use the `tuvtk-theme-1.0.7.zip` release asset when downloading the extension
from GitHub. GitHub's automatically generated **Source code** ZIP is a copy of
the whole repository and is not an EspoCRM installation package.
