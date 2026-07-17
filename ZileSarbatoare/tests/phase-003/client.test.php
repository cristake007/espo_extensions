<?php

declare(strict_types=1);

use Espo\Modules\ZileSarbatoare\Tools\NagerDate\ClientException;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HolidayFilter;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HttpResponse;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\HttpTransport;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\NagerDateClient;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\PayloadNormalizer;

$source = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate';

foreach ([
    'ClientException.php',
    'HttpResponse.php',
    'HttpTransport.php',
    'Holiday.php',
    'HolidayType.php',
    'HolidayProvider.php',
    'PayloadNormalizer.php',
    'HolidayFilter.php',
    'NagerDateClient.php',
] as $file) {
    require_once "$source/$file";
}

final class FakeTransport implements HttpTransport
{
    /** @var list<string> */
    public array $urls = [];
    /** @var list<int> */
    public array $limits = [];

    public function __construct(
        private ?HttpResponse $response = null,
        private ?ClientException $exception = null,
    ) {}

    public function get(string $url, int $maximumBytes): HttpResponse
    {
        $this->urls[] = $url;
        $this->limits[] = $maximumBytes;

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->response ?? new HttpResponse(200, '[]');
    }
}

function clientFor(FakeTransport $transport): NagerDateClient
{
    return new NagerDateClient($transport, new PayloadNormalizer(), new HolidayFilter());
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) .
            ', received ' . var_export($actual, true) . '.');
    }
}

/** @param callable(): mixed $callback */
function assertClientFailure(string $category, callable $callback, string $message): ClientException
{
    try {
        $callback();
    } catch (ClientException $e) {
        assertSameValue($category, $e->getCategory(), $message);

        return $e;
    }

    throw new RuntimeException("$message No ClientException was thrown.");
}

$fixtures = __DIR__ . '/../fixtures/nager-date';
$romaniaJson = file_get_contents("$fixtures/ro-2026.json");
$edgeJson = file_get_contents("$fixtures/ro-2026-edge-cases.json");

if ($romaniaJson === false || $edgeJson === false) {
    throw new RuntimeException('Nager.Date fixtures could not be read.');
}

$transport = new FakeTransport(new HttpResponse(200, $romaniaJson));
$holidays = clientFor($transport)->fetch('RO', 2026);
assertSameValue(17, count($holidays), 'The Romanian fixture was not fully normalized.');
assertSameValue(
    'https://date.nager.at/api/v3/PublicHolidays/2026/RO',
    $transport->urls[0],
    'The client did not use the fixed localized v3 HTTPS endpoint.',
);
assertSameValue('Anul Nou', $holidays[0]->name, 'The client must select the localized holiday name.');
assertSameValue([], $holidays[0]->subdivisionCodes, 'Nullable subdivisions must normalize to an empty list.');
assertSameValue(1048576, $transport->limits[0], 'The response limit is not fixed at one MiB.');

$edgeClient = clientFor(new FakeTransport(new HttpResponse(200, $edgeJson)));
$nationalPublic = $edgeClient->fetch('RO', 2026, ['Public'], true);
assertSameValue(2, count($nationalPublic), 'National-only public filtering is invalid.');
assertSameValue('National public holiday', $nationalPublic[0]->name, 'Holiday names must be trimmed.');
assertSameValue(['RO-B'], $nationalPublic[1]->subdivisionCodes, 'Subdivision codes must be deduplicated.');
assertSameValue(['Bank', 'Public'], $nationalPublic[1]->holidayTypes, 'Holiday types must be canonicalized.');

$allPublic = $edgeClient->fetch('RO', 2026, ['Public'], false);
assertSameValue(3, count($allPublic), 'Regional public filtering is invalid.');
$bank = $edgeClient->fetch('RO', 2026, ['Bank'], false);
assertSameValue(2, count($bank), 'Multiple upstream holiday types were not matched correctly.');
$none = $edgeClient->fetch('RO', 2026, ['School'], false);
assertSameValue([], $none, 'A valid empty filtered result must remain valid.');

assertClientFailure(
    ClientException::TRANSPORT,
    fn () => clientFor(new FakeTransport(
        exception: new ClientException(ClientException::TRANSPORT, 'Connection timed out.'),
    ))->fetch('RO', 2026),
    'Timeout and DNS/TLS failures must remain transport failures.',
);

assertClientFailure(
    ClientException::STATUS,
    fn () => clientFor(new FakeTransport(new HttpResponse(503, '{"upstream":"private"}')))->fetch('RO', 2026),
    'Non-success status was not rejected.',
);

assertClientFailure(
    ClientException::REDIRECT,
    fn () => clientFor(new FakeTransport(new HttpResponse(
        302,
        '',
        'https://attacker.invalid/holidays',
    )))->fetch('RO', 2026),
    'Redirect response was not rejected.',
);

$invalidJson = '{"secret-upstream-value":';
$jsonError = assertClientFailure(
    ClientException::JSON,
    fn () => clientFor(new FakeTransport(new HttpResponse(200, $invalidJson)))->fetch('RO', 2026),
    'Invalid JSON was not rejected.',
);

if (str_contains($jsonError->getMessage(), 'secret-upstream-value')) {
    throw new RuntimeException('A client error exposed the upstream response body.');
}

assertClientFailure(
    ClientException::RESPONSE_SIZE,
    fn () => clientFor(new FakeTransport(new HttpResponse(200, str_repeat('x', 1048577))))->fetch('RO', 2026),
    'Oversized response was not rejected.',
);

$validRow = json_decode($edgeJson, true, 32, JSON_THROW_ON_ERROR)[0];
$invalidRows = [
    'wrong country' => ['countryCode' => 'DE'],
    'wrong year' => ['date' => '2025-01-01'],
    'invalid date' => ['date' => '2026-02-30'],
    'empty localized name' => ['localName' => '  '],
    'localized name incompatible with entity schema' => ['localName' => '<script>'],
    'invalid boolean' => ['global' => 1],
    'invalid county array' => ['counties' => 'RO-B'],
    'invalid county code' => ['counties' => ['../RO-B']],
    'invalid holiday-type array' => ['types' => null],
    'unknown holiday type' => ['types' => ['Untrusted']],
];

foreach ($invalidRows as $label => $changes) {
    $payload = [$validRow, array_replace($validRow, $changes)];

    assertClientFailure(
        ClientException::SCHEMA,
        fn () => clientFor(new FakeTransport(new HttpResponse(
            200,
            json_encode($payload, JSON_THROW_ON_ERROR),
        )))->fetch('RO', 2026),
        "Invalid payload case '$label' was not rejected atomically.",
    );
}

assertClientFailure(
    ClientException::SCHEMA,
    fn () => clientFor(new FakeTransport(new HttpResponse(200, '{}')))->fetch('RO', 2026),
    'A non-list response root was accepted.',
);
assertClientFailure(
    ClientException::SCHEMA,
    fn () => clientFor(new FakeTransport(new HttpResponse(200, '[null]')))->fetch('RO', 2026),
    'A non-object response row was accepted.',
);

$missingField = $validRow;
unset($missingField['date']);
assertClientFailure(
    ClientException::SCHEMA,
    fn () => clientFor(new FakeTransport(new HttpResponse(
        200,
        json_encode([$missingField], JSON_THROW_ON_ERROR),
    )))->fetch('RO', 2026),
    'A row with a missing required field was accepted.',
);

$noRequestTransport = new FakeTransport();
$noRequestClient = clientFor($noRequestTransport);
assertClientFailure(
    ClientException::INPUT,
    fn () => $noRequestClient->fetch('ro', 2026),
    'Lowercase country input was accepted.',
);
assertClientFailure(
    ClientException::INPUT,
    fn () => $noRequestClient->fetch('RO', 2101),
    'Out-of-range year input was accepted.',
);
assertClientFailure(
    ClientException::INPUT,
    fn () => $noRequestClient->fetch('RO', 2026, ['Unknown']),
    'Unknown requested holiday type was accepted.',
);
assertSameValue([], $noRequestTransport->urls, 'Invalid inputs must be rejected before transport invocation.');

echo "PHASE-003 Nager.Date client tests passed.\n";
