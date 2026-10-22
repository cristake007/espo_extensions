import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const navbarPath = path.resolve(
    import.meta.dirname,
    '..',
    'files',
    'client',
    'custom',
    'modules',
    'tuvtk-theme',
    'src',
    'views',
    'site',
    'navbar.js'
);

async function loadNavbarView() {
    const source = await readFile(navbarPath, 'utf8');
    let TuvtkNavbarView;

    class NativeNavbarView {
        constructor() {
            this.isSideMenuOpened = false;
            this.nativeSwitchCount = 0;
            this.closeSideMenuCount = 0;
            this.tuvtkCollapsedTooltipList = [];
        }

        switchMinimizer() {
            this.nativeSwitchCount++;

            return 'native-switch';
        }

        closeSideMenu() {
            this.closeSideMenuCount++;
            this.isSideMenuOpened = false;
        }
    }

    vm.runInNewContext(source, {
        define(name, dependencies, factory) {
            assert.equal(name, 'tuvtk-theme:views/site/navbar');
            assert.deepEqual([...dependencies], ['views/site/navbar', 'ui', 'jquery']);
            TuvtkNavbarView = factory(NativeNavbarView, {}, () => {});
        },
    });

    return TuvtkNavbarView;
}

test('chevron closes a temporary side menu without changing permanent minimized state', async () => {
    const NavbarView = await loadNavbarView();
    const view = new NavbarView();

    view.isSideMenuOpened = true;

    const result = view.switchMinimizer();

    assert.equal(result, undefined);
    assert.equal(view.isSideMenuOpened, false);
    assert.equal(view.closeSideMenuCount, 1);
    assert.equal(view.nativeSwitchCount, 0);
});

test('chevron delegates every non-overlay state to the native minimizer', async () => {
    const NavbarView = await loadNavbarView();
    const view = new NavbarView();

    const result = view.switchMinimizer();

    assert.equal(result, 'native-switch');
    assert.equal(view.closeSideMenuCount, 0);
    assert.equal(view.nativeSwitchCount, 1);
});
