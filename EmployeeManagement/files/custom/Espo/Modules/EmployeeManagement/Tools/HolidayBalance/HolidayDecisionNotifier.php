<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\HolidayBalance;

use Espo\Core\Utils\Language;
use Espo\Entities\Notification;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/** Creates one native notification for each final holiday approval decision. */
final class HolidayDecisionNotifier
{
    private const REQUEST = 'EmployeeHolidayRequest';

    public function __construct(
        private EntityManager $entityManager,
        private Language $language,
    ) {}

    public function notify(Entity $request, string $status): void
    {
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            return;
        }

        $recipientId = (string) $request->get('assignedUserId');
        $requestId = (string) $request->getId();
        $revision = (int) $request->get('accountingRevision');

        if ($recipientId === '' || $requestId === '') {
            return;
        }

        if ($this->alreadySent($recipientId, $requestId, $status, $revision)) {
            return;
        }

        $messageKey = $status === 'Approved'
            ? 'holidayApprovedEmployeeNotification'
            : 'holidayRejectedEmployeeNotification';
        $message = $this->language->translateLabel(
            $messageKey,
            'messages',
            self::REQUEST,
        );
        $message = str_replace(
            ['{start}', '{end}'],
            [(string) $request->get('dateStartDate'), (string) $request->get('dateEndDate')],
            $message,
        );

        /** @var Notification $notification */
        $notification = $this->entityManager->getNewEntity(Notification::ENTITY_TYPE);
        $notification->setUserId($recipientId);
        $notification->set([
            'type' => Notification::TYPE_MESSAGE,
            'read' => false,
            'message' => $message,
            'relatedType' => self::REQUEST,
            'relatedId' => $requestId,
            'data' => [
                'employeeHolidayType' => 'decision',
                'holidayRequestId' => $requestId,
                'status' => $status,
                'accountingRevision' => $revision,
            ],
        ]);
        $this->entityManager->saveEntity($notification);
    }

    private function alreadySent(
        string $recipientId,
        string $requestId,
        string $status,
        int $revision,
    ): bool {
        $notifications = $this->entityManager
            ->getRDBRepositoryByClass(Notification::class)
            ->where([
                'userId' => $recipientId,
                'relatedType' => self::REQUEST,
                'relatedId' => $requestId,
                'type' => Notification::TYPE_MESSAGE,
            ])
            ->find();

        foreach ($notifications as $notification) {
            $data = $notification->get('data');
            $data = is_object($data)
                ? get_object_vars($data)
                : (is_array($data) ? $data : []);

            if (
                ($data['employeeHolidayType'] ?? null) === 'decision' &&
                ($data['status'] ?? null) === $status &&
                (int) ($data['accountingRevision'] ?? 0) === $revision
            ) {
                return true;
            }
        }

        return false;
    }
}
