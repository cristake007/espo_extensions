<?php

declare(strict_types=1);

use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereCalendarService;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereData;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereRepository;
use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereValidator;

$source = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere';

foreach ([
    'ZileLibereData.php',
    'ZileLibereRepository.php',
    'ZileLibereCalendar.php',
    'ZileLibereValidator.php',
    'ZileLibereCalendarService.php',
] as $file) {
    require_once "$source/$file";
}

final class FakeZileLibereRepository implements ZileLibereRepository
{
    public int $queryCount = 0;
    /** @var array{string, string, string}|null */
    public ?array $lastQuery = null;

    /** @param list<ZileLibereData> $records */
    public function __construct(private array $records)
    {}

    public function findInDateRange(
        string $countryCode,
        string $dateFrom,
        string $dateUntil,
    ): array {
        $this->queryCount++;
        $this->lastQuery = [$countryCode, $dateFrom, $dateUntil];

        return array_values(array_filter(
            $this->records,
            static fn (ZileLibereData $record): bool =>
                $record->countryCode === $countryCode &&
                $record->date >= $dateFrom &&
                $record->date < $dateUntil,
        ));
    }
}

function data(
    string $id,
    string $date,
    string $name,
    string $countryCode = 'RO',
    string $source = 'manual',
): ZileLibereData {
    return new ZileLibereData($id, $date, $name, $countryCode, $source);
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) .
            ', received ' . var_export($actual, true) . '.');
    }
}

/** @param callable(): mixed $operation */
function assertThrows(callable $operation, string $expectedMessage, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $e) {
        assertSameValue($expectedMessage, $e->getMessage(), $message);

        return;
    }

    throw new RuntimeException("$message No InvalidArgumentException was thrown.");
}

$repository = new FakeZileLibereRepository([
    data('jan', '2026-01-01', 'January'),
    data('feb', '2026-02-24', 'Company day'),
    data('mar-z', '2026-03-01', 'Zulu holiday', 'RO', 'nager-date'),
    data('mar-a', '2026-03-01', 'Alpha holiday', 'RO', 'nager-date'),
    data('apr', '2026-04-10', 'Unselected April'),
    data('jun', '2026-06-10', 'Unselected June'),
    data('jul', '2026-07-15', 'July day'),
    data('de', '2026-07-16', 'German day', 'DE', 'nager-date'),
    data('next', '2027-02-24', 'Next year'),
]);
$calendar = new ZileLibereCalendarService($repository, new ZileLibereValidator());

$records = $calendar->getZileLiberePentruLuni(2026, [7, 2, 3, 2], ' ro ');
assertSameValue(1, $repository->queryCount, 'The record method did not use exactly one query.');
assertSameValue(
    ['RO', '2026-02-01', '2026-08-01'],
    $repository->lastQuery,
    'The indexed minimum-to-maximum month range is invalid.',
);
assertSameValue(
    ['feb', 'mar-a', 'mar-z', 'jul'],
    array_map(static fn (ZileLibereData $record): string => $record->id, $records),
    'Non-consecutive month filtering or deterministic sorting is invalid.',
);
assertSameValue(
    ['manual', 'nager-date', 'nager-date', 'manual'],
    array_map(static fn (ZileLibereData $record): string => $record->source, $records),
    'Manual and synchronized records were not both returned.',
);

$oneMonth = $calendar->getZileLiberePentruLuni(2026, [2]);
assertSameValue(['feb'], array_map(
    static fn (ZileLibereData $record): string => $record->id,
    $oneMonth,
), 'One-month selection returned an invalid record set.');
assertSameValue(
    ['RO', '2026-02-01', '2026-03-01'],
    $repository->lastQuery,
    'One-month query range is invalid.',
);

$consecutive = $calendar->getZileLiberePentruLuni(2026, [2, 3]);
assertSameValue(['feb', 'mar-a', 'mar-z'], array_map(
    static fn (ZileLibereData $record): string => $record->id,
    $consecutive,
), 'Consecutive-month selection returned an invalid record set.');
assertSameValue(
    ['RO', '2026-02-01', '2026-04-01'],
    $repository->lastQuery,
    'Consecutive-month query range is invalid.',
);

$dates = $calendar->getDateLiberePentruLuni(2026, [2, 3, 7]);
assertSameValue(4, $repository->queryCount, 'A public month method used more than one query.');
assertSameValue(
    ['2026-02-24', '2026-03-01', '2026-07-15'],
    $dates,
    'Date-only results are not sorted and unique.',
);

assertSameValue(true, $calendar->esteZiLibera('2026-03-01', 'ro'), 'Known holiday was not found.');
assertSameValue(false, $calendar->esteZiLibera('2026-03-02', 'RO'), 'Unknown holiday was reported as free.');
assertSameValue(
    ['RO', '2026-03-02', '2026-03-03'],
    $repository->lastQuery,
    'Single-date lookup did not use an indexed one-day range.',
);

assertThrows(
    fn () => $calendar->getZileLiberePentruLuni(2026, []),
    'Select at least one month.',
    'Empty month selection was accepted.',
);

foreach ([0, 13, '2', 2.0] as $invalidMonth) {
    assertThrows(
        fn () => $calendar->getZileLiberePentruLuni(2026, [$invalidMonth]),
        'Every month must be an integer between 1 and 12.',
        'Invalid month was accepted.',
    );
}

foreach ([0, 9999] as $invalidYear) {
    assertThrows(
        fn () => $calendar->getZileLiberePentruLuni($invalidYear, [1]),
        'Year must be between 1 and 9998.',
        'Invalid year was accepted.',
    );
}

foreach (['R', 'ROM', 'R0', ''] as $invalidCountry) {
    assertThrows(
        fn () => $calendar->getZileLiberePentruLuni(2026, [1], $invalidCountry),
        'Country code must contain two ISO uppercase letters.',
        'Invalid country was accepted.',
    );
}

foreach (['2026-02-30', '2026-2-03', 'not-a-date'] as $invalidDate) {
    assertThrows(
        fn () => $calendar->esteZiLibera($invalidDate),
        'Date must be a valid calendar date in YYYY-MM-DD format.',
        'Invalid ISO date was accepted.',
    );
}

$decemberRepository = new FakeZileLibereRepository([data('dec', '9998-12-31', 'Last supported day')]);
$decemberCalendar = new ZileLibereCalendarService($decemberRepository, new ZileLibereValidator());
assertSameValue(
    ['9998-12-31'],
    $decemberCalendar->getDateLiberePentruLuni(9998, [12]),
    'December range at the supported year boundary is invalid.',
);
assertSameValue(
    ['RO', '9998-12-01', '9999-01-01'],
    $decemberRepository->lastQuery,
    'December did not use the next January as its exclusive range boundary.',
);

echo "PHASE-005 public month service tests passed.\n";
