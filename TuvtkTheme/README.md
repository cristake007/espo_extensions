# TUVTK Theme

Installable EspoCRM extension containing the custom `Tuvtk` theme used by
`intern.cursurituv.ro`.

The extension was created with the repository scaffold generator. Its theme
payload installs these files:

```text
custom/Espo/Modules/TuvtkTheme/Resources/metadata/themes/Tuvtk.json
custom/Espo/Modules/TuvtkTheme/Resources/metadata/clientDefs/App.json
client/custom/css/tuvtk.css
client/custom/css/tuvtk-iframe.css
client/custom/img/tuvtk-login-office.png
client/custom/modules/tuvtk-theme/src/views/site/navbar.js
```

## Build

From the repository root:

```bash
./build.sh --extension ./TuvtkTheme --zip 1.0.11 files scripts
```

The resulting `dist/tuvtk-theme-1.0.11.zip` can be uploaded from
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
Nested flyout entries use the same compact height and balanced vertical spacing
as the primary sidebar items.
The full-height sidebar rail uses muted grey at rest and transitions to the
primary theme blue when its compact collapse handle is hovered or focused. It
renders above hamburger and separator backgrounds, so it remains continuous.
The outlined handle is centered vertically and follows the rail when the navbar
is collapsed.
Collapsed sidebar items use themed, accessible hover and keyboard-focus
tooltips instead of the browser's native title tooltip.

Use the `tuvtk-theme-1.0.11.zip` release asset when downloading the extension
from GitHub. GitHub's automatically generated **Source code** ZIP is a copy of
the whole repository and is not an EspoCRM installation package.
