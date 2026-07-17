<?php

declare(strict_types=1);

namespace Espo\Core\Api {

    interface Request
    {
        public function getParsedBody(): \stdClass;
    }

    interface Response {}

    interface Action
    {
        public function process(Request $request): Response;
    }

    final class TestResponse implements Response
    {
        public function __construct(public mixed $data) {}
    }

    final class ResponseComposer
    {
        public static function json(mixed $data): Response
        {
            return new TestResponse($data);
        }
    }
}

namespace Espo\Core\Exceptions {
    class BadRequest extends \RuntimeException {}
    class Forbidden extends \RuntimeException {}
}

namespace Espo\Entities {
    class User
    {
        public function __construct(private bool $admin) {}

        public function isAdmin(): bool
        {
            return $this->admin;
        }
    }
}

namespace {
    use Espo\Core\Api\Request;
    use Espo\Core\Api\TestResponse;
    use Espo\Core\Exceptions\BadRequest;
    use Espo\Core\Exceptions\Forbidden;
    use Espo\Entities\User;
    use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\Api\PostAvailableDates;
    use Espo\Modules\ZileSarbatoare\Tools\ZileLibere\ZileLibereCalendar;
    $sourceRoot = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/ZileLibere';
    $actionFile = $sourceRoot . '/Api/PostAvailableDates.php';

    require_once $sourceRoot . '/ZileLibereCalendar.php';

    if (!is_file($actionFile)) {
        fwrite(STDERR, "Expected pre-implementation failure: PostAvailableDates.php is missing.\n");
        exit(1);
    }

    require_once $actionFile;

    final class FakeRequest implements Request
    {
        public function __construct(private \stdClass $body) {}

        public function getParsedBody(): \stdClass
        {
            return $this->body;
        }
    }

    final class FakeCalendar implements ZileLibereCalendar
    {
        public int $callCount = 0;

        /** @var array{int, list<int>, string}|null */
        public ?array $lastCall = null;

        /** @param list<string> $dates */
        public function __construct(private array $dates = []) {}

        public function getZileLiberePentruLuni(
            int $year,
            array $months,
            string $countryCode = 'RO',
        ): array {
            throw new RuntimeException('The record lookup method must not be called.');
        }

        public function getDateLiberePentruLuni(
            int $year,
            array $months,
            string $countryCode = 'RO',
        ): array {
            $this->callCount++;
            $this->lastCall = [$year, $months, $countryCode];

            return $this->dates;
        }

        public function esteZiLibera(
            string $date,
            string $countryCode = 'RO',
        ): bool {
            throw new RuntimeException('The single-date lookup method must not be called.');
        }
    }

    function request(array $body): FakeRequest
    {
        return new FakeRequest((object) $body);
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . ' Expected ' . var_export($expected, true) .
                ', received ' . var_export($actual, true) . '.',
            );
        }
    }

    /** @param class-string<Throwable> $expectedClass */
    function assertThrows(
        string $expectedClass,
        callable $operation,
        string $message,
    ): void {
        try {
            $operation();
        } catch (Throwable $exception) {
            if ($exception instanceof $expectedClass) {
                return;
            }

            throw new RuntimeException(
                "$message Expected $expectedClass, received " . $exception::class . '.',
            );
        }

        throw new RuntimeException("$message No exception was thrown.");
    }

    $calendar = new FakeCalendar(['2026-01-01', '2026-01-02']);
    $action = new PostAvailableDates($calendar, new User(true));
    $response = $action->process(request([
        'year' => 2026,
        'months' => [2, 1, 2],
    ]));

    assertSameValue(1, $calendar->callCount, 'A valid request was not delegated exactly once.');
    assertSameValue(
        [2026, [2, 1, 2], 'RO'],
        $calendar->lastCall,
        'The action changed valid input or accepted a browser-provided country.',
    );
    assertSameValue(true, $response instanceof TestResponse, 'The action returned an invalid response.');
    assertSameValue(
        ['dates' => ['2026-01-01', '2026-01-02']],
        $response->data,
        'The successful response contract is invalid.',
    );

    $emptyCalendar = new FakeCalendar();
    $emptyResponse = (new PostAvailableDates($emptyCalendar, new User(true)))
        ->process(request(['year' => 2026, 'months' => [1]]));
    assertSameValue(['dates' => []], $emptyResponse->data, 'An empty lookup response is invalid.');
    assertSameValue(1, $emptyCalendar->callCount, 'An empty result caused an extra lookup.');

    $forbiddenCalendar = new FakeCalendar();
    assertThrows(
        Forbidden::class,
        fn () => (new PostAvailableDates($forbiddenCalendar, new User(false)))
            ->process(request(['year' => 2026, 'months' => [1]])),
        'A non-administrator request was accepted.',
    );
    assertSameValue(0, $forbiddenCalendar->callCount, 'Authorization ran after the holiday lookup.');

    $invalidBodies = [
        [],
        ['year' => 2026],
        ['months' => [1]],
        ['list' => [['year' => 2026, 'months' => [1]]]],
        ['year' => '2026', 'months' => [1]],
        ['year' => 2026.0, 'months' => [1]],
        ['year' => true, 'months' => [1]],
        ['year' => null, 'months' => [1]],
        ['year' => 0, 'months' => [1]],
        ['year' => 9999, 'months' => [1]],
        ['year' => 2026, 'months' => '1'],
        ['year' => 2026, 'months' => []],
        ['year' => 2026, 'months' => ['1']],
        ['year' => 2026, 'months' => [1.0]],
        ['year' => 2026, 'months' => [true]],
        ['year' => 2026, 'months' => [null]],
        ['year' => 2026, 'months' => [0]],
        ['year' => 2026, 'months' => [13]],
    ];

    foreach ($invalidBodies as $index => $body) {
        $invalidCalendar = new FakeCalendar();

        assertThrows(
            BadRequest::class,
            fn () => (new PostAvailableDates($invalidCalendar, new User(true)))
                ->process(request($body)),
            "Invalid request body $index was accepted.",
        );
        assertSameValue(
            0,
            $invalidCalendar->callCount,
            "Invalid request body $index reached the calendar service.",
        );
    }

    fwrite(STDOUT, "PHASE-007 holiday lookup API contracts passed.\n");
}
