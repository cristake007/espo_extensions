import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const clientRoot = resolve(
    import.meta.dirname,
    '../../files/client/custom/modules/zile-sarbatoare/src/views'
);

function loadView(relativePath, dependency, browserWindow) {
    let ViewClass;

    const source = readFileSync(resolve(clientRoot, relativePath), 'utf8');
    const context = {
        CustomEvent: class {
            constructor(type) {
                this.type = type;
            }
        },
        define(names, factory) {
            assert.equal(names.length, 1);
            ViewClass = factory(dependency);
        },
        window: browserWindow,
    };

    vm.runInNewContext(source, context, {filename: relativePath});

    return ViewClass;
}

function createWindow() {
    const listeners = new Map();

    return {
        addEventListener(name, callback) {
            listeners.set(name, callback);
        },
        dispatchEvent(event) {
            listeners.get(event.type)?.(event);
        },
        hasListener(name) {
            return listeners.has(name);
        },
        removeEventListener(name, callback) {
            if (listeners.get(name) === callback) {
                listeners.delete(name);
            }
        },
    };
}

class BaseView {
    handlers = new Map();
    refreshCalls = [];

    setup() {
        this.baseSetupCalled = true;
    }

    once(name, callback) {
        this.handlers.set(name, callback);
    }

    trigger(name) {
        const callback = this.handlers.get(name);

        this.handlers.delete(name);
        callback?.();
    }

    actionRefresh(options) {
        this.refreshCalls.push(options);
    }
}

test('a successful holiday modal save refreshes and then detaches the active calendar', () => {
    const browserWindow = createWindow();
    const CalendarView = loadView('calendar/calendar.js', BaseView, browserWindow);
    const EditModalView = loadView('modals/zile-libere-edit.js', BaseView, browserWindow);
    const calendar = new CalendarView();
    const modal = new EditModalView();

    calendar.setup();
    modal.setup();

    assert.equal(calendar.baseSetupCalled, true);
    assert.equal(modal.baseSetupCalled, true);
    assert.equal(browserWindow.hasListener('zile-sarbatoare:calendar-refresh'), true);
    assert.equal(calendar.refreshCalls.length, 0);

    modal.trigger('after:save');

    assert.equal(calendar.refreshCalls.length, 1);
    assert.equal(calendar.refreshCalls[0].suppressLoadingAlert, true);

    calendar.trigger('remove');
    assert.equal(browserWindow.hasListener('zile-sarbatoare:calendar-refresh'), false);
});

test('timeline refresh uses the same successful-save event', () => {
    const browserWindow = createWindow();
    const TimelineView = loadView('calendar/timeline.js', BaseView, browserWindow);
    const timeline = new TimelineView();

    timeline.setup();
    browserWindow.dispatchEvent({type: 'zile-sarbatoare:calendar-refresh'});

    assert.equal(timeline.refreshCalls.length, 1);
    assert.equal(timeline.refreshCalls[0], undefined);
});
