import assert from 'node:assert/strict';
import fs from 'node:fs';

const root = new URL('../../', import.meta.url);
const read = path => fs.readFileSync(new URL(path, root), 'utf8');
const notifier = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayBalance/HolidaySubmissionNotifier.php');
const provider = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayAdministration/HolidayApproverProvider.php');
const requestService = read('files/custom/Espo/Modules/EmployeeManagement/Tools/HolidayBalance/HolidayRequestService.php');
const job = read('files/custom/Espo/Modules/EmployeeManagement/Jobs/SendHolidayApprovalNotification.php');

const checks = [
    ['native-email-sender', notifier.includes('EmailSender') && notifier.includes('->send($email)')],
    ['native-grouped-job', notifier.includes('JobSchedulerFactory') && notifier.includes('->setGroup($group)')],
    ['stable-logical-identity', notifier.includes("self::EVENT, $requestId, $recipientId")],
    ['bounded-delayed-retries', notifier.includes('MAX_ATTEMPTS = 3') && notifier.includes("2 => 'PT1M'") && notifier.includes("3 => 'PT5M'")],
    ['delivery-state-machine', ['STATUS_DRAFT', 'STATUS_SENDING', 'STATUS_SENT', 'STATUS_FAILED'].every(value => notifier.includes(value))],
    ['permission-revalidation', notifier.includes('canReadApprovalRequest($recipient, $request)')],
    ['pending-only-delivery', notifier.includes("$request->get('status') !== 'Pending'")],
    ['base-url-link', notifier.includes("get('siteUrl')") && notifier.includes('/#EmployeeHolidayRequest')],
    ['plain-and-html-body', notifier.includes('setBody($html)') && notifier.includes('setBodyPlain($plain)')],
    ['transactional-outbox-trigger', requestService.indexOf('$this->entityManager->saveEntity($request);') < requestService.indexOf('$this->submissionNotifier->queue($request);')],
    ['team-role-native-acl', provider.includes('createUserAcl($user)') && provider.includes("getLevel(self::REQUEST, 'read') === 'all'")],
    ['job-delegates-only', job.includes('$this->notifier->deliver(')],
    ['no-install-backfill', !read('scripts/AfterInstall.php').includes('HolidaySubmissionNotifier')],
];

for (const [name, passed] of checks) {
    assert.equal(passed, true, name);
}

console.log(`PASS: Holiday approval email regression (${checks.length}/${checks.length}).`);
