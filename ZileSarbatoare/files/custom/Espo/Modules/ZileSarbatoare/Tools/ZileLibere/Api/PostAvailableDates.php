<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Tools\ZileLibere\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereCalendar;

final class PostAvailableDates implements Action
{
    private const MINIMUM_YEAR = 1;
    private const MAXIMUM_YEAR = 9998;

    public function __construct(
        private ZileLibereCalendar $calendar,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Forbidden('Only administrators can retrieve available holiday dates.');
        }

        $data = $request->getParsedBody();
        $year = $data->year ?? null;
        $months = $data->months ?? null;

        if (!is_int($year) || $year < self::MINIMUM_YEAR || $year > self::MAXIMUM_YEAR) {
            throw new BadRequest('Year must be an integer between 1 and 9998.');
        }

        if (!is_array($months) || $months === [] || !array_is_list($months)) {
            throw new BadRequest('Months must be a non-empty list of integers between 1 and 12.');
        }

        foreach ($months as $month) {
            if (!is_int($month) || $month < 1 || $month > 12) {
                throw new BadRequest('Every month must be an integer between 1 and 12.');
            }
        }

        $records = $this->calendar->getZileLiberePentruLuni($year, $months, 'RO');
        $holidaysByDate = [];

        foreach ($records as $record) {
            $holidaysByDate[$record->date] ??= [
                'names' => [],
                'type' => 'internal',
            ];

            if (!in_array($record->name, $holidaysByDate[$record->date]['names'], true)) {
                $holidaysByDate[$record->date]['names'][] = $record->name;
            }

            if ($record->source !== 'manual') {
                $holidaysByDate[$record->date]['type'] = 'legal';
            }
        }

        $holidays = [];

        foreach ($holidaysByDate as $date => $holiday) {
            $holidays[] = [
                'date' => $date,
                'name' => implode(' / ', $holiday['names']),
                'type' => $holiday['type'],
                'source' => 'zile-sarbatoare',
            ];
        }

        return ResponseComposer::json([
            'dates' => array_keys($holidaysByDate),
            'holidays' => $holidays,
        ]);
    }
}
