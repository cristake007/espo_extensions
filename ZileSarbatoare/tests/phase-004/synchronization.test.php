<?php

declare(strict_types=1);

use Espo\Modules\ZileSarbatoare\Tools\NagerDate\ClientException;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\Holiday;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayProvider;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayStore;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayWriteData;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\LockPolicy;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\PayloadNormalizer;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\Reconciler;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\ReconciliationResult;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\Settings;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\StoredHoliday;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SynchronizationRunner;

$source = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate';

foreach ([
    'ClientException.php',
    'ConcurrentSyncException.php',
    'Holiday.php',
    'HolidayType.php',
    'HolidayProvider.php',
    'HolidayWriteData.php',
    'StoredHoliday.php',
    'ReconciliationResult.php',
    'HolidayStore.php',
    'PayloadNormalizer.php',
    'Reconciler.php',
    'Settings.php',
    'SynchronizationRunner.php',
    'LockPolicy.php',
] as $file) {
    require_once "$source/$file";
}

final class FakeHolidayProvider implements HolidayProvider
{
    /** @var list<int> */
    public array $calls = [];

    /** @param array<int, list<Holiday>> $holidaysByYear */
    public function __construct(
        private array $holidaysByYear,
        private ?int $failureYear = null,
    ) {}

    public function fetch(
        string $countryCode,
        int $year,
        array $acceptedTypes = ['Public'],
        bool $nationalOnly = true,
    ): array {
        $this->calls[] = $year;

        if ($year === $this->failureYear) {
            throw new ClientException(ClientException::TRANSPORT, 'Nager.Date could not be reached.');
        }

        return $this->holidaysByYear[$year] ?? [];
    }
}

final class FakeHolidayStore implements HolidayStore
{
    /** @var array<string, array<string, mixed>> */
    public array $records;
    public int $transactionCount = 0;
    /** @var array{string, list<int>}|null */
    public ?array $lastScope = null;
    public ?string $failOperation = null;
    public int $failOnCall = 1;
    private int $nextId = 1000;
    /** @var array<string, int> */
    private array $operationCalls = [];

    /** @param array<string, array<string, mixed>> $records */
    public function __construct(array $records = [])
    {
        $this->records = $records;
    }

    public function transactional(Closure $operation): ReconciliationResult
    {
        $this->transactionCount++;
        $snapshot = $this->records;

        try {
            return $operation();
        } catch (Throwable $e) {
            $this->records = $snapshot;

            throw $e;
        }
    }

    public function findManagedScope(string $countryCode, array $years): array
    {
        $this->lastScope = [$countryCode, $years];
        $result = [];

        foreach ($this->records as $id => $record) {
            if (
                ($record['deleted'] ?? false) ||
                ($record['source'] ?? null) !== 'nager-date' ||
                ($record['managed'] ?? false) !== true ||
                ($record['countryCode'] ?? null) !== $countryCode ||
                !in_array($record['sourceYear'] ?? null, $years, true)
            ) {
                continue;
            }

            $result[] = new StoredHoliday(
                (string) $id,
                $record['date'],
                $record['name'],
                $record['countryCode'],
                $record['sourceYear'],
                $record['nationalHoliday'],
                $record['subdivisionCodes'],
                $record['holidayTypes'],
            );
        }

        return $result;
    }

    public function create(HolidayWriteData $data): void
    {
        $this->failIfConfigured('create');
        $this->records[(string) $this->nextId++] = $this->recordFromWriteData($data);
    }

    public function update(string $id, HolidayWriteData $data): void
    {
        $this->failIfConfigured('update');
        $this->records[$id] = $this->recordFromWriteData($data);
    }

    public function remove(string $id): void
    {
        $this->failIfConfigured('remove');
        $this->records[$id]['deleted'] = true;
    }

    /** @return array<string, mixed> */
    private function recordFromWriteData(HolidayWriteData $data): array
    {
        return [
            'date' => $data->date,
            'name' => $data->name,
            'countryCode' => $data->countryCode,
            'source' => 'nager-date',
            'managed' => true,
            'sourceYear' => $data->sourceYear,
            'nationalHoliday' => $data->nationalHoliday,
            'subdivisionCodes' => $data->subdivisionCodes,
            'holidayTypes' => $data->holidayTypes,
            'syncedAt' => $data->syncedAt,
            'deleted' => false,
        ];
    }

    private function failIfConfigured(string $operation): void
    {
        $this->operationCalls[$operation] = ($this->operationCalls[$operation] ?? 0) + 1;

        if ($this->failOperation === $operation && $this->operationCalls[$operation] === $this->failOnCall) {
            throw new RuntimeException("Injected $operation failure.");
        }
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) .
            ', received ' . var_export($actual, true) . '.');
    }
}

/** @param callable(): mixed $operation */
function assertThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException("$message No exception was thrown.");
}

/** @return list<Holiday> */
function fixtureHolidays(string $path, string $countryCode, int $year): array
{
    $json = file_get_contents($path);

    if ($json === false) {
        throw new RuntimeException('Fixture could not be read.');
    }

    return (new PayloadNormalizer())->normalize(
        json_decode($json, false, 32, JSON_THROW_ON_ERROR),
        $countryCode,
        $year,
    );
}

function settings(array $years): Settings
{
    return new Settings(true, 'RO', $years, ['Public'], true, true, 'Weekly', '03:00', 1, 1);
}

$fixture = __DIR__ . '/../fixtures/nager-date/ro-2026.json';
$holidays = fixtureHolidays($fixture, 'RO', 2026);
$manual = [
    'date' => '2026-02-24',
    'name' => 'Company day',
    'countryCode' => 'RO',
    'source' => 'manual',
    'managed' => false,
    'sourceYear' => null,
    'nationalHoliday' => true,
    'subdivisionCodes' => [],
    'holidayTypes' => ['Public'],
    'syncedAt' => null,
    'deleted' => false,
];
$otherCountry = [
    'date' => '2026-01-01',
    'name' => 'Other country',
    'countryCode' => 'DE',
    'source' => 'nager-date',
    'managed' => true,
    'sourceYear' => 2026,
    'nationalHoliday' => true,
    'subdivisionCodes' => [],
    'holidayTypes' => ['Public'],
    'syncedAt' => '2025-01-01 00:00:00',
    'deleted' => false,
];
$otherYear = array_replace($otherCountry, [
    'date' => '2025-01-01',
    'name' => 'Other year',
    'countryCode' => 'RO',
    'sourceYear' => 2025,
]);

$store = new FakeHolidayStore(['manual' => $manual, 'de' => $otherCountry, 'old' => $otherYear]);
$provider = new FakeHolidayProvider([2026 => $holidays]);
$runner = new SynchronizationRunner($provider, new Reconciler($store));
$now = new DateTimeImmutable('2026-01-10 10:00:00', new DateTimeZone('Europe/Bucharest'));
$first = $runner->run(settings([2026]), $now);

assertSameValue(17, $first->accepted, 'First import accepted count is invalid.');
assertSameValue(17, $first->created, 'First import did not create all fixture records.');
assertSameValue(0, $first->updated, 'First import unexpectedly updated records.');
assertSameValue(0, $first->removed, 'First import unexpectedly removed records.');
assertSameValue(['RO', [2026]], $store->lastScope, 'The managed write scope is invalid.');
assertSameValue($manual, $store->records['manual'], 'Manual records were changed by synchronization.');
assertSameValue($otherCountry, $store->records['de'], 'Other-country managed records were changed.');
assertSameValue($otherYear, $store->records['old'], 'Other-year managed records were changed.');

$imported = array_filter($store->records, static fn (array $record): bool =>
    $record['source'] === 'nager-date' && $record['countryCode'] === 'RO' && $record['sourceYear'] === 2026
);
assertSameValue(17, count($imported), 'Imported managed records are missing.');
assertSameValue(
    2,
    count(array_filter($imported, static fn (array $record): bool => $record['date'] === '2026-06-01')),
    'Two named holidays on the same date were collapsed.',
);

$afterFirst = $store->records;
$second = $runner->run(settings([2026]), $now->modify('+1 day'));
assertSameValue([17, 0, 0, 0], [$second->accepted, $second->created, $second->updated, $second->removed],
    'Identical synchronization was not idempotent.');
assertSameValue($afterFirst, $store->records, 'Identical synchronization made logical record changes.');

$changed = $holidays;
$changed[0] = new Holiday(
    $changed[0]->date,
    $changed[0]->name,
    $changed[0]->countryCode,
    $changed[0]->nationalHoliday,
    ['RO-B'],
    $changed[0]->holidayTypes,
);
$changedResult = (new SynchronizationRunner(
    new FakeHolidayProvider([2026 => $changed]),
    new Reconciler($store),
))->run(settings([2026]), $now->modify('+2 days'));
assertSameValue(1, $changedResult->updated, 'A changed managed row was not updated.');

$withoutLast = array_slice($changed, 0, -1);
$staleResult = (new SynchronizationRunner(
    new FakeHolidayProvider([2026 => $withoutLast]),
    new Reconciler($store),
))->run(settings([2026]), $now->modify('+3 days'));
assertSameValue(1, $staleResult->removed, 'A stale managed row was not removed.');
assertSameValue($manual, $store->records['manual'], 'Stale removal changed a manual record.');

$rollbackStore = new FakeHolidayStore();
$rollbackStore->failOperation = 'create';
$rollbackStore->failOnCall = 2;
$beforeFailure = $rollbackStore->records;
assertThrows(
    fn () => (new Reconciler($rollbackStore))->reconcile('RO', [2026], $holidays, '2026-01-10 08:00:00'),
    'Injected database failure was not propagated.',
);
assertSameValue($beforeFailure, $rollbackStore->records, 'Database failure did not roll back all changes.');

$fetchFailureStore = new FakeHolidayStore();
$fetchFailureProvider = new FakeHolidayProvider([2026 => $holidays], 2027);
assertThrows(
    fn () => (new SynchronizationRunner(
        $fetchFailureProvider,
        new Reconciler($fetchFailureStore),
    ))->run(settings([2026, 2027]), $now),
    'Second-year fetch failure was not propagated.',
);
assertSameValue([2026, 2027], $fetchFailureProvider->calls, 'All requested years were not attempted in order.');
assertSameValue(0, $fetchFailureStore->transactionCount, 'A transaction began before every year was fetched.');
assertSameValue([], $fetchFailureStore->records, 'Fetch failure made partial database changes.');

$lostLockStore = new FakeHolidayStore();
assertThrows(
    fn () => (new SynchronizationRunner(
        new FakeHolidayProvider([2026 => $holidays]),
        new Reconciler($lostLockStore),
    ))->run(settings([2026]), $now, static fn (): bool => false),
    'Lost lock ownership did not stop reconciliation.',
);
assertSameValue(0, $lostLockStore->transactionCount, 'Lost lock ownership opened a transaction.');

$duplicateStore = new FakeHolidayStore();
assertThrows(
    fn () => (new Reconciler($duplicateStore))->reconcile(
        'RO',
        [2026],
        [$holidays[0], $holidays[0]],
        '2026-01-10 08:00:00',
    ),
    'Duplicate upstream identity was accepted.',
);
assertSameValue(0, $duplicateStore->transactionCount, 'Invalid reconciliation input opened a transaction.');

$emptyStore = new FakeHolidayStore($afterFirst);
$emptyResult = (new Reconciler($emptyStore))->reconcile('RO', [2026], [], '2026-01-10 08:00:00');
assertSameValue(17, $emptyResult->removed, 'A valid empty result did not remove the managed scope.');
assertSameValue($manual, $emptyStore->records['manual'], 'Valid empty result removed a manual record.');

$policy = new LockPolicy();
$lockNow = new DateTimeImmutable('2026-01-10 10:00:00', new DateTimeZone('UTC'));
assertSameValue(
    true,
    $policy->isOwnedByActiveRun(true, $lockNow->modify('-14 minutes'), $lockNow),
    'A fresh synchronization lock was not protected.',
);
assertSameValue(
    false,
    $policy->isOwnedByActiveRun(true, $lockNow->modify('-16 minutes'), $lockNow),
    'A stale synchronization lock was not recoverable.',
);
assertSameValue(
    true,
    $policy->tokenOwnsLock(hash('sha256', 'owner-token'), 'owner-token'),
    'Lock owner token was rejected.',
);
assertSameValue(
    false,
    $policy->tokenOwnsLock(hash('sha256', 'new-owner'), 'old-owner'),
    'Old run could release a new owner lock.',
);

echo "PHASE-004 atomic synchronization tests passed.\n";
