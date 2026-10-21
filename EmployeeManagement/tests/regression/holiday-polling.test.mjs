import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL(
    '../../files/client/custom/modules/employee-management/src/views/holiday-request/index.js',
    import.meta.url,
), 'utf8');

const listeners = new Map();
const clearedTimers = [];
const scheduledTimers = [];
const documentStub = {
    hidden:false,
    addEventListener(name, listener) { listeners.set(name, listener); },
    removeEventListener(name, listener) {
        if (listeners.get(name) === listener) { listeners.delete(name); }
    },
};
let ajaxHandler = async () => ({list:[]});
let HolidayView;

class BaseView {}

vm.runInNewContext(source, {
    define(dependencies, factory) { HolidayView = factory(BaseView); },
    document:documentStub,
    setTimeout(callback, delay) {
        const timer = {id:scheduledTimers.length + 1, callback, delay};
        scheduledTimers.push(timer);
        return timer.id;
    },
    clearTimeout(id) { if (id !== undefined && id !== null) { clearedTimers.push(id); } },
    console:{warn() {}, info() {}},
    Espo:{
        Ajax:{getRequest(url) { return ajaxHandler(url); }},
        Ui:{success() {}, warning() {}},
    },
    encodeURIComponent,
});

function view(overrides = {}) {
    return Object.assign(new HolidayView(), {
        rows:[],
        listMode:'queue',
        listModeVersion:0,
        visibilityVersion:0,
        pollFailureCount:0,
        pollStopped:false,
        pollImmediatePending:false,
        polling:false,
        refreshing:false,
        requestSignature:JSON.stringify([]),
    }, overrides);
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });
    return {promise, resolve, reject};
}

test('uses 30 seconds normally, 5 seconds while processing, and bounded backoff', () => {
    const instance = view();
    assert.equal(instance.getPollingDelay(), 30000);
    instance.rows = [{processing:true}];
    assert.equal(instance.getPollingDelay(), 5000);
    instance.pollFailureCount = 1;
    assert.equal(instance.getPollingDelay(), 10000);
    instance.pollFailureCount = 6;
    assert.equal(instance.getPollingDelay(), 120000);
    instance.rows = [];
    assert.equal(instance.getPollingDelay(), 120000);
});

test('an empty manager queue is polled and a new request is applied without a second list request', async () => {
    const response = {list:[{id:'new-request', status:'Pending', documents:[]}]};
    let listRequests = 0;
    let refreshOptions;
    const instance = view({
        requestList:async mode => {
            listRequests++;
            assert.equal(mode, 'queue');
            return response;
        },
        refresh:async options => {
            refreshOptions = options;
            return true;
        },
    });

    assert.equal(await instance.pollForUpdates(), true);
    assert.equal(listRequests, 1);
    assert.equal(refreshOptions.listResponse, response);
    assert.equal(refreshOptions.mode, 'queue');
});

test('unchanged pages make one list request per normal cycle and no duplicate refresh', async () => {
    let listRequests = 0;
    let refreshes = 0;
    const instance = view({
        requestList:async () => {
            listRequests++;
            return {list:[]};
        },
        refresh:async () => { refreshes++; return true; },
        schedulePoll() {},
    });

    for (let cycle = 0; cycle < 6; cycle++) {
        await instance.runScheduledPoll();
    }
    assert.equal(listRequests, 6);
    assert.equal(refreshes, 0);
    assert.equal(instance.getPollingDelay(), 30000);
});

test('a response from a previous list mode cannot refresh the current mode', async () => {
    const request = deferred();
    let refreshes = 0;
    const instance = view({
        requestList:() => request.promise,
        refresh:async () => { refreshes++; return true; },
    });
    const polling = instance.pollForUpdates();
    instance.listMode = 'approved';
    instance.listModeVersion++;
    request.resolve({list:[{id:'old-queue-row', status:'Pending', documents:[]}]});

    assert.equal(await polling, true);
    assert.equal(refreshes, 0);
});

test('processing completion, published documents, and balance recovery change the refresh decision', async () => {
    const instance = view({listMode:'own'});
    const processing = [{
        id:'request-1', status:'ApprovalProcessing', accountingRevision:2,
        approvalState:'Processing', approvalError:null, documents:[],
    }];
    const approved = [{
        id:'request-1', status:'Approved', accountingRevision:3,
        approvalState:'Completed', approvalError:null,
        documents:[{id:'document-1', name:'approval.docx'}],
    }];
    instance.requestSignature = instance.makeRequestSignature(processing);
    let refreshes = 0;
    instance.requestList = async () => ({list:approved});
    instance.refresh = async () => { refreshes++; return true; };
    assert.equal(await instance.pollForUpdates(), true);
    assert.equal(refreshes, 1);

    instance.requestSignature = instance.makeRequestSignature(approved);
    instance.balanceLoadFailed = true;
    assert.equal(await instance.pollForUpdates(), true);
    assert.equal(refreshes, 2);
});

test('list requests are serialized even when callers ask for different modes', async () => {
    const first = deferred();
    const second = deferred();
    const urls = [];
    let active = 0;
    let maximumActive = 0;
    ajaxHandler = url => {
        urls.push(url);
        active++;
        maximumActive = Math.max(maximumActive, active);
        const pending = urls.length === 1 ? first : second;
        return pending.promise.finally(() => { active--; });
    };
    const instance = view();
    const queue = instance.requestList('queue');
    const approved = instance.requestList('approved');

    assert.deepEqual(urls, ['EmployeeManagement/holiday/queue']);
    first.resolve({list:[]});
    await queue;
    await Promise.resolve();
    assert.deepEqual(urls, [
        'EmployeeManagement/holiday/queue',
        'EmployeeManagement/holiday/approved',
    ]);
    second.resolve({list:[]});
    await approved;
    assert.equal(maximumActive, 1);
});

test('rapid mode switches coalesce refreshes and render only the current response', async () => {
    const firstLoad = deferred();
    const loadModes = [];
    let renders = 0;
    let activeLoads = 0;
    let maximumActiveLoads = 0;
    const instance = view({
        async load(mode) {
            loadModes.push(mode);
            activeLoads++;
            maximumActiveLoads = Math.max(maximumActiveLoads, activeLoads);
            if (loadModes.length === 1) { await firstLoad.promise; }
            activeLoads--;
            return mode === this.listMode;
        },
        async reRender() { renders++; },
        schedulePoll() {},
    });

    const queueRefresh = instance.refresh({mode:'queue', modeVersion:0});
    instance.listMode = 'approved';
    instance.listModeVersion = 1;
    const approvedRefresh = instance.refresh({mode:'approved', modeVersion:1});
    firstLoad.resolve();
    await Promise.all([queueRefresh, approvedRefresh]);

    assert.deepEqual(loadModes, ['queue', 'approved']);
    assert.equal(maximumActiveLoads, 1);
    assert.equal(renders, 1);
});

test('mode switches request an immediate refresh', () => {
    let refreshes = 0;
    const instance = view({refresh() { refreshes++; }});
    instance.switchListMode('approved');
    assert.equal(instance.listMode, 'approved');
    assert.equal(instance.listModeVersion, 1);
    assert.equal(refreshes, 1);
});

test('a successful user-triggered refresh restores the normal interval after a polling error', async () => {
    const instance = view({
        pollFailureCount:2,
        load:async () => true,
        reRender:async () => {},
        schedulePoll() {},
    });
    await instance.refresh();
    assert.equal(instance.pollFailureCount, 0);
    assert.equal(instance.getPollingDelay(), 30000);
});

test('hidden tabs stop scheduling and becoming visible triggers an immediate check', () => {
    scheduledTimers.length = 0;
    clearedTimers.length = 0;
    const instance = view({pollTimer:44});
    documentStub.hidden = true;
    instance.handleVisibilityChange();
    assert.equal(instance.pollTimer, null);
    assert.ok(clearedTimers.includes(44));

    documentStub.hidden = false;
    instance.handleVisibilityChange();
    assert.equal(scheduledTimers.at(-1).delay, 0);
    assert.equal(instance.visibilityVersion, 2);
});

test('poll failures back off, recovery resets the interval, and cleanup removes resources', async () => {
    const instance = view({
        pollForUpdates:async () => { throw new Error('temporary network failure'); },
    });
    instance.schedulePoll = () => {};
    await instance.runScheduledPoll();
    assert.equal(instance.pollFailureCount, 1);
    assert.equal(instance.getPollingDelay(), 60000);

    instance.pollForUpdates = async () => true;
    await instance.runScheduledPoll();
    assert.equal(instance.pollFailureCount, 0);
    assert.equal(instance.getPollingDelay(), 30000);

    const handler = () => {};
    instance.visibilityHandler = handler;
    listeners.set('visibilitychange', handler);
    instance.pollTimer = 99;
    instance.stopPolling();
    assert.equal(instance.pollStopped, true);
    assert.equal(instance.pollTimer, null);
    assert.equal(listeners.has('visibilitychange'), false);
    assert.ok(clearedTimers.includes(99));
});

test('an in-flight response is discarded after the view is destroyed', async () => {
    const request = deferred();
    let refreshes = 0;
    const instance = view({
        requestList:() => request.promise,
        refresh:async () => { refreshes++; return true; },
        visibilityHandler:() => {},
    });
    const polling = instance.pollForUpdates();
    instance.stopPolling();
    request.resolve({list:[{id:'late-row', status:'Pending', documents:[]}]});
    assert.equal(await polling, true);
    assert.equal(refreshes, 0);
});
