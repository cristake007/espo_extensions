# TUVTK Theme

Installable EspoCRM extension containing the custom `Tuvtk` theme for EspoCRM
10. The theme inherits the native Espo metadata and imports the CSS shipped by
the installed EspoCRM version before applying its own tokens and component
overrides. It does not contain a copy of EspoCRM's generated CSS.

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
./test-php.sh validate TuvtkTheme
./test-node.sh test TuvtkTheme
./test-php.sh build TuvtkTheme
```

The resulting `dist/tuvtk-theme-1.0.19.zip` can be uploaded from
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
is collapsed. When the hamburger temporarily opens a collapsed sidebar, one
click on the handle closes that overlay without changing the saved collapsed
state.
Collapsed sidebar items use themed, accessible hover and keyboard-focus
tooltips instead of the browser's native title tooltip. Only one tooltip can be
visible at a time, and it closes immediately when its item loses hover or focus.

Saturday and Sunday use a subtle light-red wash with fine diagonal hatching in
the month and week calendar views. Weekend headings and date numbers are muted
to reinforce that these are non-working days while keeping events prominent.

## CSS architecture

`tuvtk.css` imports the installed Espo stylesheet and contains all TUVTK
overrides in seven ordered sections: native Espo CSS, tokens, navigation,
forms and modals, Administration, login and calendar. New application styles
belong in the corresponding existing section.

The iframe entry point remains separate because Espo renders iframe content in
a separate document with `espo-iframe.css`. CSS custom properties cannot cross
that document boundary, so it repeats only the TUVTK mappings consumed by the
native iframe stylesheet. The navbar JavaScript extends Espo's native navbar view
only to create accessible, styled tooltips for collapsed side-navigation
items. Its behavior is guarded by the active `Tuvtk` theme and restores the
attributes it changes on cleanup.

## Packaging changes in 1.0.19

The TUVTK palette, compact 36 px sidebar rows, centered animated red marker,
collapse rail, collapsed-item tooltips, split login artwork, Administration
headings and weekend hatching are unchanged. The former component stylesheets
were consolidated into the ordered sections of `tuvtk.css`; no component rules
were redesigned.

Use the `tuvtk-theme-1.0.19.zip` release asset when downloading the extension
from GitHub. GitHub's automatically generated **Source code** ZIP is a copy of
the whole repository and is not an EspoCRM installation package.
