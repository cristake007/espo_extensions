<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\EmployeeHolidayRequest;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use InvalidArgumentException;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Field\DateTime;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\ORM\EntityManager;
use Espo\Tools\WorkingTime\{CalendarFactory, CalendarUtilityFactory, Extractor};

final class WorkingDayCalculator
{
    public function __construct(private EntityManager $entityManager,
        private CalendarFactory $calendarFactory, private CalendarUtilityFactory $utilityFactory,
        private Extractor $extractor) {}

    /** Derived immutable booking result, never another editable schedule or personal availability. */
    public function snapshot(User $user, string $dateStart, string $dateEnd, array $nonWorkingDates, bool $allowEmpty = false): array
    {
        [$start, $end] = $this->period($dateStart, $dateEnd);
        $id = $user->get('workingTimeCalendarId');
        $record = is_string($id) ? $this->entityManager->getEntityById(WorkingTimeCalendar::ENTITY_TYPE, $id) : null;
        if (!$record instanceof WorkingTimeCalendar) { throw new BadRequest('An explicit native Working Time Calendar must be assigned by the platform administrator.'); }
        $calendar = $this->calendarFactory->create($record);
        $utility = $this->utilityFactory->create($calendar);
        $timezone = $calendar->getTimezone();
        $nonWorking = array_fill_keys($nonWorkingDates, true);
        $days = []; $months = []; $ranges = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day')) as $point) {
            $date = $point->format('Y-m-d');
            $from = DateTime::fromDateTime(new DateTimeImmutable($date . ' 00:00:00', $timezone));
            if (isset($nonWorking[$date]) || !$utility->isWorkingDay($from)) { continue; }
            $days[$date] = $utility->getSummedWorkingHours($from, $from->modify('+1 day'));
            $ranges[$date] = array_map(static fn (array $range): array => [
                $range[0]->withTimezone($timezone)->toDateTime()->format('H:i'),
                $range[1]->withTimezone($timezone)->toDateTime()->format('H:i'),
            ], $this->extractor->extract($calendar, $from, $from->modify('+1 day')));
            $month = substr($date, 0, 7);
            $months[$month] = ($months[$month] ?? 0) + 1;
        }
        if (!$days && !$allowEmpty) { throw new InvalidArgumentException('The selected period contains no working days.'); }
        return ['version' => 1, 'calendarId' => $id, 'timezone' => $timezone->getName(),
            'dateStart' => $dateStart, 'dateEnd' => $dateEnd, 'days' => (object) $days,
            'monthlyDays' => (object) $months, 'totalDays' => count($days),
            'excludedPublicDates' => $nonWorkingDates, 'ranges' => (object) $ranges];
    }

    /** Original Holiday period validation, shared with persisted monthly results. */
    public function period(string $dateStart, string $dateEnd): array
    {
        $start = $this->parse($dateStart);
        $end = $this->parse($dateEnd);
        if ($end < $start) { throw new InvalidArgumentException('The last day cannot be before the first day.'); }
        if ($start->diff($end)->days > 366) { throw new InvalidArgumentException('A holiday booking cannot span more than 367 days.'); }
        return [$start, $end];
    }

    private function parse(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) ||
            $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException('Dates must use the YYYY-MM-DD format.');
        }

        return $date;
    }
}
