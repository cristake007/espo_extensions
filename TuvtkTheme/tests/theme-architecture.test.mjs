import assert from 'node:assert/strict';
import {readFile, readdir, stat} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';

const extensionRoot = path.resolve(import.meta.dirname, '..');
const cssRoot = path.join(extensionRoot, 'files', 'client', 'custom', 'css');
const metadataRoot = path.join(
    extensionRoot,
    'files',
    'custom',
    'Espo',
    'Modules',
    'TuvtkTheme',
    'Resources',
    'metadata'
);

const read = relativePath => readFile(path.join(extensionRoot, relativePath), 'utf8');

test('manifest and theme metadata describe the 1.0.19 Espo-derived theme', async () => {
    const manifest = JSON.parse(await read('manifest.json'));
    const theme = JSON.parse(await readFile(path.join(metadataRoot, 'themes', 'Tuvtk.json'), 'utf8'));

    assert.equal(manifest.version, '1.0.19');
    assert.deepEqual(manifest.acceptableVersions, ['>=10.0.0']);
    assert.deepEqual(manifest.php, ['>=8.4']);
    assert.equal(theme.parent, 'Espo');
    assert.equal(theme.stylesheet, 'client/custom/css/tuvtk.css');
    assert.equal(theme.stylesheetIframe, 'client/custom/css/tuvtk-iframe.css');
    assert.equal(theme.isDark, false);
    assert.equal('params' in theme, false);
    assert.equal('mappedParams' in theme, false);
});

test('theme entry points import Espo and contain no copied generated bundle', async () => {
    const [mainCss, iframeCss, mainStats, iframeStats] = await Promise.all([
        readFile(path.join(cssRoot, 'tuvtk.css'), 'utf8'),
        readFile(path.join(cssRoot, 'tuvtk-iframe.css'), 'utf8'),
        stat(path.join(cssRoot, 'tuvtk.css')),
        stat(path.join(cssRoot, 'tuvtk-iframe.css')),
    ]);

    assert.match(mainCss, /^\/\*[\s\S]*?@import url\("\.\.\/\.\.\/css\/espo\/espo\.css"\);/);
    assert.match(iframeCss, /@import url\("\.\.\/\.\.\/css\/espo\/espo-iframe\.css"\);/);
    assert.match(mainCss, /--theme-name:\s*Tuvtk\s*!important/);
    assert.doesNotMatch(mainCss, /--theme-name:\s*Espo/);
    assert.ok(mainStats.size < 30_000, `main stylesheet is unexpectedly large: ${mainStats.size}`);
    assert.ok(iframeStats.size < 10_000, `iframe stylesheet is unexpectedly large: ${iframeStats.size}`);
    assert.doesNotMatch(mainCss, /@import url\("tuvtk\//);
    assert.doesNotMatch(iframeCss, /@import url\("tuvtk\//);
});

test('CSS payload contains only the main and iframe stylesheets', async () => {
    const entries = await readdir(cssRoot, {withFileTypes: true});

    assert.deepEqual(
        entries.map(entry => entry.name).sort(),
        ['tuvtk-iframe.css', 'tuvtk.css']
    );

    for (const entry of entries) {
        assert.equal(entry.isFile(), true, `${entry.name} must be a file`);
    }
});

test('main stylesheet keeps the required section order', async () => {
    const source = await readFile(path.join(cssRoot, 'tuvtk.css'), 'utf8');
    const headings = [
        '/* 1. CSS nativ EspoCRM */',
        '/* 2. Tokeni TUVTK */',
        '/* 3. Navigare */',
        '/* 4. Formulare și modale */',
        '/* 5. Administration */',
        '/* 6. Login */',
        '/* 7. Calendar */',
    ];
    const positions = headings.map(heading => source.indexOf(heading));

    assert.ok(positions.every(position => position >= 0));
    assert.deepEqual([...positions].sort((a, b) => a - b), positions);
    assert.match(source, /^\/\* 1\. CSS nativ EspoCRM \*\/\n@import url\("\.\.\/\.\.\/css\/espo\/espo\.css"\);/);
});

test('iframe contains only the TUVTK mappings consumed by native iframe CSS', async () => {
    const iframeCss = await readFile(path.join(cssRoot, 'tuvtk-iframe.css'), 'utf8');

    for (const token of [
        '--default-border-color',
        '--navbar-height',
        '--panel-bg',
        '--scroll-bg',
        '--scroll-thumb-bg',
        '--scroll-width',
        '--scroll-border-width',
        '--text-color',
        '--text-link-color',
        '--text-link-hover-color',
    ]) {
        assert.match(iframeCss, new RegExp(`${token}:`));
    }

    assert.doesNotMatch(iframeCss, /--btn-|--dropdown-|--calendar-border/);
});

test('every local CSS asset resolves inside the extension payload', async () => {
    const cssFiles = ['tuvtk.css', 'tuvtk-iframe.css'];

    for (const relativeFile of cssFiles) {
        const absoluteFile = path.join(cssRoot, relativeFile);
        const source = await readFile(absoluteFile, 'utf8');

        for (const match of source.matchAll(/url\("([^"?]+)"\)/g)) {
            const reference = match[1];

            if (reference.includes('/css/espo/')) {
                continue;
            }

            await stat(path.resolve(path.dirname(absoluteFile), reference));
        }

        assert.doesNotMatch(source, /\/\*!? Bootstrap v3/);
    }
});

test('navbar extension is theme-guarded and fully restores changed attributes', async () => {
    const source = await read(
        'files/client/custom/modules/tuvtk-theme/src/views/site/navbar.js'
    );

    assert.match(source, /\['views\/site\/navbar', 'ui', 'jquery'\]/);
    assert.match(source, /getName\(\) !== 'Tuvtk'/);
    assert.match(source, /!this\.isSide\(\)/);
    assert.match(source, /originalAriaLabel/);
    assert.match(source, /removeAttribute\('aria-label'\)/);
    assert.match(source, /item\.popover\.destroy\(\)/);
    assert.match(
        source,
        /switchMinimizer\(\)\s*\{[\s\S]*?if \(this\.isSideMenuOpened\)\s*\{[\s\S]*?this\.closeSideMenu\(\);[\s\S]*?return;[\s\S]*?return super\.switchMinimizer\(\);/
    );
    assert.doesNotMatch(source, /addEventListener\(['"]click['"]/);
    assert.doesNotMatch(source, /prototype\.|monkey|setInterval/);
});

test('theme labels use Espo theme translation keys', async () => {
    for (const locale of ['en_US', 'ro_RO']) {
        const language = JSON.parse(await read(
            `files/custom/Espo/Modules/TuvtkTheme/Resources/i18n/${locale}/Global.json`
        ));

        assert.equal(typeof language.themes.Tuvtk, 'string');
        assert.ok(language.themes.Tuvtk.length > 0);
    }
});

test('sidenav geometry follows the native navbar edge in every collapsed state', async () => {
    const navigation = await read('files/client/custom/css/tuvtk.css');

    assert.match(navigation, /#navbar > \.navbar a\.minimizer\s*\{[\s\S]*?position:\s*absolute;/);
    assert.match(navigation, /#navbar > \.navbar a\.minimizer\s*\{[\s\S]*?right:\s*0;/);
    assert.match(navigation, /#navbar > \.navbar a\.minimizer\s*\{[\s\S]*?transform:\s*translate\(50%, -50%\);/);
    assert.doesNotMatch(navigation, /\.minimized\.side-menu-opened/);
    assert.doesNotMatch(navigation, /left:\s*calc\(var\(--navbar-(?:minimized-)?width\)/);
    assert.match(navigation, /\.navbar::after[\s\S]*?z-index:\s*10;/);
    assert.match(navigation, /\.navbar::after[\s\S]*?right:\s*0;/);
    assert.match(navigation, /\.navbar::after[\s\S]*?transform:\s*translateX\(50%\);/);
    assert.match(navigation, /\.navbar::after[\s\S]*?clear:\s*none;/);
    assert.match(navigation, /\.navbar::after[\s\S]*?height:\s*auto;/);
});

test('Administration colors are scoped to the native admin template', async () => {
    const mainCss = await read('files/client/custom/css/tuvtk.css');

    assert.match(mainCss, /--tuvtk-primary:\s*#164194;/i);
    assert.match(mainCss, /--tuvtk-secondary:\s*#d41131;/i);
    assert.match(
        mainCss,
        /#main:has\(> \.admin-content > \.row \.admin-tables-container\) > \.page-header > h3\s*\{\s*color:\s*var\(--tuvtk-primary\);\s*\}/
    );
    assert.match(
        mainCss,
        /#main > \.admin-content \.admin-tables-container > \.admin-content-section > h4\s*\{\s*color:\s*var\(--tuvtk-primary\);\s*\}/
    );
    assert.doesNotMatch(mainCss, /\.admin-content[^,{]*(?:\ba\b|\.icon\b|\btd\b)\s*[,\{]/);
    assert.doesNotMatch(mainCss, /^\s*(?:h2|h3|strong)\s*\{/m);
});
