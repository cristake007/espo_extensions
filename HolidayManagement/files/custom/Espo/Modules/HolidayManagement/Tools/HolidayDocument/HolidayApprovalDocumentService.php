<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayDocument;

use DateTimeImmutable;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Attachment;
use Espo\Entities\User;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\NonWorkingDayProvider;
use Espo\Modules\HolidayManagement\Tools\HolidayRequest\WorkingDayCalculator;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

final class HolidayApprovalDocumentService
{
    private const REQUEST = 'HolidayRequest';
    private const PROFILE = 'HolidayProfile';
    private const STATUS_PENDING = 'Pending';
    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    private const MONTH_NAMES = [
        1 => 'ianuarie',
        2 => 'februarie',
        3 => 'martie',
        4 => 'aprilie',
        5 => 'mai',
        6 => 'iunie',
        7 => 'iulie',
        8 => 'august',
        9 => 'septembrie',
        10 => 'octombrie',
        11 => 'noiembrie',
        12 => 'decembrie',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private Config $config,
        private DateTimeUtil $dateTime,
        private WorkingDayCalculator $workingDayCalculator,
        private NonWorkingDayProvider $nonWorkingDayProvider,
        private HolidayDocumentGenerator $generator,
    ) {}

    /** @return list<string> */
    public function generate(Entity $request): array
    {
        $userId = (string) $request->get('assignedUserId');
        $profileId = (string) $request->get('profileId');
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);
        $profile = $this->entityManager->getEntityById(self::PROFILE, $profileId);

        if (!$user || !$profile) {
            throw new RuntimeException('The holiday request document data is incomplete.');
        }

        $segments = $this->buildMonthlySegments(
            (string) $request->get('dateStartDate'),
            (string) $request->get('dateEndDate'),
        );
        $segmentDays = array_sum(array_column($segments, 'days'));
        $requestDays = max(0, (int) $request->get('days'));

        if ($segmentDays !== $requestDays) {
            throw new RuntimeException('The monthly holiday document totals do not match the request.');
        }

        $annualEntitlement = (float) $profile->get('annualEntitlement');
        $balanceBefore = (float) $profile->get('balance') + $this->getPendingDays($userId);
        $totalDays = max($annualEntitlement, $balanceBefore);
        $documentDate = $this->formatDate($this->dateTime->getToday()->toString());
        $actualApproverName = (string) $request->get('decidedByName');
        $block1Title = trim((string) $this->config->get('holidayManagementApprovalBlock1Title'));
        $block2Title = trim((string) $this->config->get('holidayManagementApprovalBlock2Title'));
        $block1Name = $this->approvalName('holidayManagementApprovalBlock1Name', $block1Title, $actualApproverName);
        $block2Name = $this->approvalName('holidayManagementApprovalBlock2Name', $block2Title, $actualApproverName);
        $attachmentIds = [];

        foreach ($segments as $segment) {
            $daysAlreadyUsed = max(0.0, $totalDays - $balanceBefore);
            $balanceAfter = $balanceBefore - $segment['days'];
            $contents = $this->generator->render([
                'dateStart' => $this->formatDate($segment['dateStart']),
                'dateEnd' => $this->formatDate($segment['dateEnd']),
                'year' => (string) $segment['year'],
                'month' => (string) $segment['month'],
                'requestedDays' => $this->formatDays($segment['days']),
                'lastName' => trim((string) $user->get('lastName')),
                'firstName' => trim((string) $user->get('firstName')),
                'approvalBlock1Title' => $block1Title,
                'approvalBlock1Name' => $block1Name,
                'approvalBlock2Title' => $block2Title,
                'approvalBlock2Name' => $block2Name,
                'totalDays' => $this->formatDays($totalDays),
                'daysAlreadyUsed' => $this->formatDays($daysAlreadyUsed),
                'balanceBefore' => $this->formatDays($balanceBefore),
                'balanceAfter' => $this->formatDays($balanceAfter),
                'documentDate' => $documentDate,
            ]);
            $filename = sprintf(
                'Cerere concediu - %s %d.docx',
                self::MONTH_NAMES[$segment['month']],
                $segment['year'],
            );

            /** @var Attachment $attachment */
            $attachment = $this->entityManager->getNewEntity(Attachment::ENTITY_TYPE);
            $attachment
                ->setName($filename)
                ->setType(self::MIME_TYPE)
                ->setRole(Attachment::ROLE_ATTACHMENT)
                ->setSize(strlen($contents))
                ->setParent($request)
                ->setTargetField('approvalDocuments');

            $this->entityManager->saveEntity($attachment);
            $this->fileStorageManager->putContents($attachment, $contents);
            $attachmentIds[] = $attachment->getId();
            $balanceBefore = $balanceAfter;
        }

        return $attachmentIds;
    }

    /** @return list<array{dateStart: string, dateEnd: string, year: int, month: int, days: int}> */
    private function buildMonthlySegments(string $dateStart, string $dateEnd): array
    {
        $requestStart = new DateTimeImmutable($dateStart);
        $requestEnd = new DateTimeImmutable($dateEnd);
        $month = $requestStart->modify('first day of this month');
        $segments = [];

        while ($month <= $requestEnd) {
            $monthEnd = $month->modify('last day of this month');
            $segmentStart = $requestStart > $month ? $requestStart : $month;
            $segmentEnd = $requestEnd < $monthEnd ? $requestEnd : $monthEnd;
            $start = $segmentStart->format('Y-m-d');
            $end = $segmentEnd->format('Y-m-d');
            $nonWorkingDates = $this->nonWorkingDayProvider->getDates($start, $end);

            try {
                $days = $this->workingDayCalculator->count($start, $end, $nonWorkingDates);
            } catch (\InvalidArgumentException) {
                $days = 0;
            }

            $segments[] = [
                'dateStart' => $start,
                'dateEnd' => $end,
                'year' => (int) $month->format('Y'),
                'month' => (int) $month->format('n'),
                'days' => $days,
            ];
            $month = $month->modify('first day of next month');
        }

        return $segments;
    }

    private function getPendingDays(string $userId): float
    {
        $days = 0.0;
        $requests = $this->entityManager
            ->getRDBRepository(self::REQUEST)
            ->where([
                'assignedUserId' => $userId,
                'OR' => [
                    ['status' => self::STATUS_PENDING],
                    ['status' => null],
                ],
            ])
            ->find();

        foreach ($requests as $pendingRequest) {
            $days += max(0.0, (float) $pendingRequest->get('days'));
        }

        return $days;
    }

    private function approvalName(string $setting, string $title, string $actualApproverName): string
    {
        $configuredName = trim((string) $this->config->get($setting));

        if ($configuredName !== '') {
            return $configuredName;
        }

        return $title !== '' ? $actualApproverName : '';
    }

    private function formatDate(string $date): string
    {
        return (new DateTimeImmutable($date))->format('d.m.Y');
    }

    private function formatDays(float|int $days): string
    {
        return rtrim(rtrim(number_format((float) $days, 2, '.', ''), '0'), '.');
    }
}
