<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;

use DateInterval;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Mail\EmailSender;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\Email;
use Espo\Entities\User;
use Espo\Modules\EmployeeManagement\Jobs\SendHolidayApprovalNotification;
use Espo\Modules\EmployeeManagement\Tools\HolidayAdministration\HolidayApproverProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;
use Throwable;

/** Native Email outbox and grouped Job delivery for a newly submitted Pending request. */
final class HolidaySubmissionNotifier
{
    private const REQUEST = 'EmployeeHolidayRequest';
    private const EVENT = 'pending-created-v1';
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAYS = [2 => 'PT1M', 3 => 'PT5M'];

    public function __construct(
        private EntityManager $entityManager,
        private JobSchedulerFactory $schedulers,
        private HolidayApproverProvider $approvers,
        private EmailSender $emailSender,
        private Config $config,
        private Log $log,
    ) {}

    /**
     * Persist one native Email and one native Job per eligible approver.
     * The caller's transaction makes request creation and outbox creation atomic.
     */
    public function queue(Entity $request): void
    {
        if (!$request->getId() || $request->get('status') !== 'Pending') {
            return;
        }

        $employee = $this->entityManager->getEntityById(
            User::ENTITY_TYPE,
            (string) $request->get('assignedUserId'),
        );

        if (!$employee instanceof User) {
            throw new RuntimeException('Holiday notification employee is unavailable.');
        }

        foreach ($this->approvers->users($request) as $recipient) {
            $address = trim((string) $recipient->getEmailAddress());

            if (!$this->isUsableAddress($address)) {
                $this->log->warning('Holiday approval email skipped: recipient has no usable address.', [
                    'holidayRequestId' => $request->getId(),
                    'recipientUserId' => $recipient->getId(),
                ]);
                continue;
            }

            $identity = $this->identity((string) $request->getId(), (string) $recipient->getId());
            $messageId = $this->messageId($identity);
            $email = $this->entityManager
                ->getRDBRepositoryByClass(Email::class)
                ->where(['messageId' => $messageId])
                ->findOne();

            if (!$email) {
                $email = $this->createQueuedEmail($request, $employee, $address, $messageId);
                $this->entityManager->saveEntity($email);
            }

            if ($email->getStatus() === Email::STATUS_SENT) {
                continue;
            }

            $this->schedule(
                (string) $request->getId(),
                (string) $recipient->getId(),
                (string) $email->getId(),
                $identity,
                1,
            );
        }
    }

    public function deliver(
        string $requestId,
        string $recipientId,
        string $emailId,
        string $identity,
        int $attempt,
    ): void {
        if (
            $attempt < 1 ||
            $attempt > self::MAX_ATTEMPTS ||
            !hash_equals($this->identity($requestId, $recipientId), $identity)
        ) {
            throw new RuntimeException('Invalid Holiday notification Job data.');
        }

        $request = $this->entityManager->getEntityById(self::REQUEST, $requestId);
        $recipient = $this->entityManager->getEntityById(User::ENTITY_TYPE, $recipientId);

        if (
            !$request ||
            $request->get('status') !== 'Pending' ||
            !$recipient instanceof User ||
            !$this->approvers->canReadApprovalRequest($recipient, $request)
        ) {
            $this->markUndeliverable($emailId, $requestId, $recipientId, 'authorization-or-status');
            return;
        }

        $address = trim((string) $recipient->getEmailAddress());

        if (!$this->isUsableAddress($address)) {
            $this->markUndeliverable($emailId, $requestId, $recipientId, 'email-address');
            return;
        }

        $email = $this->claim($emailId, $address);

        if (!$email) {
            return;
        }

        try {
            $this->emailSender->send($email);
            $this->entityManager->saveEntity($email, [
                SaveOption::SILENT => true,
                SaveOption::SKIP_HOOKS => true,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($emailId);
            $this->log->error('Holiday approval email delivery failed.', [
                'holidayRequestId' => $requestId,
                'recipientUserId' => $recipientId,
                'attempt' => $attempt,
                'exceptionClass' => $e::class,
            ]);

            if ($attempt < self::MAX_ATTEMPTS) {
                $this->schedule($requestId, $recipientId, $emailId, $identity, $attempt + 1);
            }

            throw $e;
        }
    }

    private function createQueuedEmail(
        Entity $request,
        User $employee,
        string $address,
        string $messageId,
    ): Email {
        $employeeName = trim((string) $employee->getName()) ?: 'Angajat';
        $start = (string) $request->get('dateStartDate');
        $end = (string) $request->get('dateEndDate');
        $days = (string) ((int) $request->get('days'));
        $url = rtrim((string) $this->config->get('siteUrl'), '/') . '/#EmployeeHolidayRequest';
        $subject = 'Cerere nouă de concediu – ' . $employeeName;
        $plain = implode("\n", [
            'A fost înregistrată o nouă cerere de concediu care necesită aprobare.',
            '',
            'Angajat: ' . $employeeName,
            'Data de început: ' . $start,
            'Data de sfârșit: ' . $end,
            'Zile lucrătoare solicitate: ' . $days,
            'Status: În așteptare',
            '',
            'Deschideți interfața de aprobare: ' . $url,
        ]);
        $escape = static fn(string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $html = '<p>A fost înregistrată o nouă cerere de concediu care necesită aprobare.</p>' .
            '<table role="presentation" style="border-collapse:collapse">' .
            '<tr><td style="padding:4px 12px 4px 0"><strong>Angajat</strong></td><td>' . $escape($employeeName) . '</td></tr>' .
            '<tr><td style="padding:4px 12px 4px 0"><strong>Data de început</strong></td><td>' . $escape($start) . '</td></tr>' .
            '<tr><td style="padding:4px 12px 4px 0"><strong>Data de sfârșit</strong></td><td>' . $escape($end) . '</td></tr>' .
            '<tr><td style="padding:4px 12px 4px 0"><strong>Zile lucrătoare solicitate</strong></td><td>' . $escape($days) . '</td></tr>' .
            '<tr><td style="padding:4px 12px 4px 0"><strong>Status</strong></td><td>În așteptare</td></tr>' .
            '</table>' .
            '<p><a href="' . $escape($url) . '">Deschideți interfața de aprobare a concediilor</a></p>';

        /** @var Email $email */
        $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);
        $email
            ->setSubject($subject)
            ->setBody($html)
            ->setBodyPlain($plain)
            ->setIsHtml()
            ->setToAddressList([$address])
            ->setStatus(Email::STATUS_DRAFT)
            ->setParent($request)
            ->setMessageId($messageId);
        $email->set('isSystem', true);

        return $email;
    }

    private function claim(string $emailId, string $address): ?Email
    {
        return $this->entityManager->getTransactionManager()->run(function () use ($emailId, $address): ?Email {
            /** @var ?Email $email */
            $email = $this->entityManager
                ->getRDBRepositoryByClass(Email::class)
                ->where(['id' => $emailId])
                ->forUpdate()
                ->findOne();

            if (!$email || $email->getStatus() === Email::STATUS_SENT) {
                return null;
            }

            // A concurrent worker already owns the only uncertain SMTP attempt.
            if ($email->getStatus() === Email::STATUS_SENDING) {
                return null;
            }

            $email->setToAddressList([$address]);
            $email->setStatus(Email::STATUS_SENDING);
            $this->entityManager->saveEntity($email, [
                SaveOption::SILENT => true,
                SaveOption::SKIP_HOOKS => true,
            ]);

            return $email;
        });
    }

    private function markFailed(string $emailId): void
    {
        $email = $this->entityManager->getEntityById(Email::ENTITY_TYPE, $emailId);

        if (!$email instanceof Email || $email->getStatus() === Email::STATUS_SENT) {
            return;
        }

        $email->setStatus(Email::STATUS_FAILED);
        $this->entityManager->saveEntity($email, [
            SaveOption::SILENT => true,
            SaveOption::SKIP_HOOKS => true,
        ]);
    }

    private function markUndeliverable(
        string $emailId,
        string $requestId,
        string $recipientId,
        string $reason,
    ): void {
        $this->markFailed($emailId);
        $this->log->warning('Holiday approval email cancelled before delivery.', [
            'holidayRequestId' => $requestId,
            'recipientUserId' => $recipientId,
            'reason' => $reason,
        ]);
    }

    private function schedule(
        string $requestId,
        string $recipientId,
        string $emailId,
        string $identity,
        int $attempt,
    ): void {
        if ($this->hasScheduledAttempt($identity, $attempt)) {
            return;
        }

        $token = bin2hex(random_bytes(16));
        $group = $this->group($identity);
        $data = Data::create([
            'requestId' => $requestId,
            'recipientId' => $recipientId,
            'emailId' => $emailId,
            'identity' => $identity,
            'attempt' => $attempt,
            'token' => $token,
        ])->withTargetId($requestId)->withTargetType(self::REQUEST);
        $scheduler = $this->schedulers->create()
            ->setClassName(SendHolidayApprovalNotification::class)
            ->setGroup($group)
            ->setData($data);

        if ($attempt > 1) {
            $scheduler->setDelay(new DateInterval(self::RETRY_DELAYS[$attempt]));
        }

        $scheduler->schedule();
        $job = $this->entityManager->getRDBRepository('Job')
            ->where([
                'targetId' => $requestId,
                'targetType' => self::REQUEST,
                'className' => SendHolidayApprovalNotification::class,
                'group' => $group,
            ])
            ->order('number', 'DESC')
            ->findOne();

        if (!$job || (($this->toArray($job->get('data'))['token'] ?? null) !== $token)) {
            throw new RuntimeException('The native Holiday notification Job was not persisted.');
        }

        // Retries are scheduled explicitly with bounded delays; disable native immediate retries.
        $job->set('attempts', 0);
        $this->entityManager->saveEntity($job);
    }

    private function hasScheduledAttempt(string $identity, int $attempt): bool
    {
        foreach ($this->entityManager->getRDBRepository('Job')
            ->where([
                'className' => SendHolidayApprovalNotification::class,
                'group' => $this->group($identity),
            ])
            ->find() as $job) {
            $data = $this->toArray($job->get('data'));

            if (($data['identity'] ?? null) === $identity && (int) ($data['attempt'] ?? 0) === $attempt) {
                return true;
            }
        }

        return false;
    }

    private function identity(string $requestId, string $recipientId): string
    {
        return hash('sha256', implode(':', [self::EVENT, $requestId, $recipientId]));
    }

    private function messageId(string $identity): string
    {
        return '<employee-holiday-' . $identity . '@espo>';
    }

    private function group(string $identity): string
    {
        return 'employee-holiday-email:' . $identity;
    }

    private function isUsableAddress(string $address): bool
    {
        return $address !== ''
            && !str_contains($address, "\r")
            && !str_contains($address, "\n")
            && !str_contains($address, ';')
            && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array<string, mixed> */
    private function toArray(mixed $value): array
    {
        return is_object($value)
            ? get_object_vars($value)
            : (is_array($value) ? $value : []);
    }
}
