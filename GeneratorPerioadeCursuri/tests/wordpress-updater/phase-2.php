<?php

declare(strict_types=1);

use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressClientException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressCourseClient;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressHttpTransport;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressTransportException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUrlException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUrlGuard;

$root = __DIR__;
$sourceRoot = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require $sourceRoot . '/WordPressUrlGuard.php';
require $sourceRoot . '/WordPressHttpTransport.php';
require $sourceRoot . '/WordPressCourseClient.php';

$fixture = json_decode(
    (string) file_get_contents($root . '/fixtures/http/scenarios.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$scenarios = array_column($fixture['scenarios'], null, 'name');
$executedScenarios = [];
$checks = 0;
$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$checks, &$failures): void {
    $checks++;

    if ($expected !== $actual) {
        $failures[] = $message . '\n  expected: ' . var_export($expected, true) . '\n  actual:   ' . var_export($actual, true);
    }
};

$assertTrue = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$captureException = static function (string $class, callable $callback, string $message) use (&$checks, &$failures): ?Throwable {
    $checks++;

    try {
        $callback();
        $failures[] = $message . "\n  expected exception: {$class}";
        return null;
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            $failures[] = $message . '\n  actual exception: ' . $exception::class . ': ' . $exception->getMessage();
        }

        return $exception;
    }
};

$dnsCalls = [];
$resolver = static function (string $host, int $port) use (&$dnsCalls): array {
    $dnsCalls[] = [$host, $port];

    return match ($host) {
        'wp.example.test', 'other.example.test' => ['93.184.216.34'],
        'mixed.example.test' => ['93.184.216.34', '192.168.10.20'],
        '10.0.0.8', '127.0.0.1', '192.0.2.1', 'fd00::8', '2606:4700:4700::1111' => [$host],
        default => [],
    };
};
$guard = new WordPressUrlGuard($resolver);

$public = $guard->approve(' HTTPS://WP.Example.Test:443/subdir/ ');
$assertSame('https://wp.example.test/subdir', $public['url'], 'Public URLs must be normalized.');
$assertSame('93.184.216.34', $public['pinnedAddress'], 'The approved public address must be pinned.');
$assertSame('wp.example.test:443:93.184.216.34', $public['curlResolve'], 'The cURL pin must include host and port.');
$executedScenarios[] = 'public-url';

foreach (['private-ipv4' => 'http://10.0.0.8', 'private-ipv6' => 'https://[fd00::8]'] as $name => $url) {
    $exception = $captureException(WordPressUrlException::class, fn () => $guard->approve($url), "{$name} must be rejected.");
    $assertSame('prohibited_destination', $exception?->getReason(), "{$name} must use the prohibited-destination category.");
    $executedScenarios[] = $name;
}

$callsBeforeMetadata = count($dnsCalls);
$exception = $captureException(
    WordPressUrlException::class,
    fn () => $guard->approve('http://metadata.google.internal'),
    'Metadata hosts must be rejected.'
);
$assertSame('prohibited_destination', $exception?->getReason(), 'Metadata hosts must use the prohibited-destination category.');
$assertSame($callsBeforeMetadata, count($dnsCalls), 'Metadata hosts must be rejected before DNS resolution.');
$executedScenarios[] = 'metadata-host';

$exception = $captureException(
    WordPressUrlException::class,
    fn () => $guard->approve('https://mixed.example.test'),
    'Mixed public/private DNS answers must be rejected.'
);
$assertSame('prohibited_destination', $exception?->getReason(), 'Mixed DNS must use the prohibited-destination category.');
$executedScenarios[] = 'mixed-public-private-dns';

$publicIpv6 = $guard->approve('https://[2606:4700:4700::1111]');
$assertSame('[2606:4700:4700::1111]:443:[2606:4700:4700::1111]', $publicIpv6['curlResolve'], 'Public IPv6 literals must produce a valid cURL pin.');

foreach ([
    'ftp://wp.example.test',
    'https://user:secret@wp.example.test',
    'https://wp.example.test?query=value',
    'https://wp.example.test#fragment',
    'https://localhost',
    'https://name.localhost',
    'https://bad_host.example.test',
    'https://wp.example.test/path with space',
] as $unsafeBaseUrl) {
    $captureException(WordPressUrlException::class, fn () => $guard->normalize($unsafeBaseUrl), 'Unsafe base URL syntax must be rejected: ' . $unsafeBaseUrl);
}

$current = $guard->approve('https://wp.example.test/start');
$resolverCallsBeforeRedirect = count($dnsCalls);
$exception = $captureException(
    WordPressUrlException::class,
    fn () => $guard->approveRedirect($current, 'https://other.example.test/admin', true),
    'Authenticated cross-host redirects must be rejected.'
);
$assertSame('unsafe_redirect', $exception?->getReason(), 'Cross-host auth redirects must use the unsafe category.');
$assertSame($resolverCallsBeforeRedirect, count($dnsCalls), 'Cross-host auth redirects must be rejected before target DNS.');

$exception = $captureException(
    WordPressUrlException::class,
    fn () => $guard->approveRedirect($current, 'http://wp.example.test/admin', true),
    'Authenticated HTTPS downgrades must be rejected.'
);
$assertSame('unsafe_redirect', $exception?->getReason(), 'HTTPS downgrade must use the unsafe category.');
$executedScenarios[] = 'authenticated-https-downgrade';

$upgraded = $guard->approveRedirect($guard->approve('http://wp.example.test/start'), 'https://wp.example.test/next', true);
$assertSame('https://wp.example.test/next', $upgraded['url'], 'Default-port HTTP-to-HTTPS upgrades must be allowed.');
$relative = $guard->approveRedirect($current, '../next', true);
$assertSame('https://wp.example.test/next', $relative['url'], 'Safe relative redirects must resolve and normalize dot segments.');

$rebindCall = 0;
$rebindGuard = new WordPressUrlGuard(static function () use (&$rebindCall): array {
    $rebindCall++;
    return $rebindCall === 1 ? ['93.184.216.34'] : ['127.0.0.1'];
});
$rebindCurrent = $rebindGuard->approve('https://wp.example.test/start');
$exception = $captureException(
    WordPressUrlException::class,
    fn () => $rebindGuard->approveRedirect($rebindCurrent, '/next', true),
    'Same-host redirect DNS must be resolved and checked again.'
);
$assertSame('prohibited_destination', $exception?->getReason(), 'A same-host DNS change to private must be rejected before transport.');

/**
 * @param array<int, array<string, mixed>> $responses
 * @param array<int, array<string, mixed>> $requests
 */
$makeTransport = static function (array $responses, array &$requests): WordPressHttpTransport {
    $runner = static function (array $request, Closure $onHeader, Closure $onBody) use (&$responses, &$requests): array {
        $requests[] = $request;
        $response = array_shift($responses);

        if (!is_array($response)) {
            return ['success' => false, 'reason' => 'request'];
        }

        if (isset($response['transportError'])) {
            return [
                'success' => false,
                'reason' => $response['transportError'] === 'timeout' ? 'timeout' : 'connection',
            ];
        }

        $status = (int) ($response['status'] ?? 200);
        $onHeader("HTTP/1.1 {$status} Fixture\r\n");

        foreach ($response['headers'] ?? [] as $name => $value) {
            $onHeader($name . ': ' . $value . "\r\n");
        }

        $onHeader("\r\n");

        if (isset($response['streamedBodyBytes'])) {
            $remaining = (int) $response['streamedBodyBytes'];
            $chunk = str_repeat('x', 64 * 1024);

            while ($remaining > 0) {
                $current = substr($chunk, 0, min(strlen($chunk), $remaining));
                $remaining -= strlen($current);

                if ($onBody($current) === 0) {
                    break;
                }
            }
        } else {
            $onBody((string) ($response['body'] ?? ''));
        }

        return ['success' => true, 'status' => $status];
    };

    return new WordPressHttpTransport($runner);
};

$transportRequests = [];
$transport = $makeTransport([['status' => 200, 'headers' => ['X-Fixture' => 'yes'], 'body' => '{}']], $transportRequests);
$transportResponse = $transport->send($public, 'GET', ['Accept: */*'], ['username' => 'fixture', 'password' => 'dummy-value']);
$assertSame(200, $transportResponse['status'], 'Transport must return the bounded status.');
$assertSame('yes', $transportResponse['headers']['x-fixture'], 'Transport headers must be normalized.');
$assertSame(5, $transportRequests[0]['connectTimeout'], 'Transport must enforce a five-second connect timeout.');
$assertSame(30, $transportRequests[0]['responseTimeout'], 'Transport must enforce a thirty-second response timeout.');
$assertSame($public['curlResolve'], $transportRequests[0]['resolve'], 'Transport must use the approved DNS pin.');
$assertTrue(!array_filter($transportRequests[0]['headers'], static fn (string $header): bool => stripos($header, 'authorization:') === 0), 'Authorization must not be synthesized as a visible header.');

$oversizedRequests = [];
$oversizedTransport = $makeTransport([$scenarios['oversized-body']['responses'][0]], $oversizedRequests);
$exception = $captureException(
    WordPressTransportException::class,
    fn () => $oversizedTransport->send($public, 'GET', []),
    'Streamed bodies over 5 MiB must be rejected.'
);
$assertSame('response_too_large', $exception?->getReason(), 'Oversized bodies must use the response-too-large category.');
$executedScenarios[] = 'oversized-body';

$lengthRequests = [];
$lengthTransport = $makeTransport([['status' => 403, 'headers' => ['Content-Length' => '5242881'], 'body' => '']], $lengthRequests);
$captureException(WordPressTransportException::class, fn () => $lengthTransport->send($public, 'GET', []), 'Oversized error responses must be rejected from Content-Length.');

/**
 * @param array<int, array<string, mixed>> $responses
 * @return array{0: WordPressCourseClient, 1: object{requests: array<int, array<string, mixed>>, sleeps: array<int, float>, time: float}}
 */
$makeClient = static function (array $responses) use ($resolver, $makeTransport): array {
    $state = (object) ['requests' => [], 'sleeps' => [], 'time' => 100.0];
    $requests = &$state->requests;
    $transport = $makeTransport($responses, $requests);
    $clock = static function () use ($state): float {
        return $state->time;
    };
    $sleeper = static function (float $seconds) use ($state): void {
        $state->sleeps[] = $seconds;
        $state->time += $seconds;
    };
    $client = new WordPressCourseClient(
        ' https://WP.Example.Test/site/ ',
        ' fixture-editor ',
        'abcd efgh ijkl',
        new WordPressUrlGuard($resolver),
        $transport,
        $sleeper,
        $clock,
        static fn (float $minimum, float $maximum): float => $minimum
    );

    return [$client, $state];
};

[$client, $state] = $makeClient([['status' => 200, 'body' => '{"id":7,"name":"Fixture Editor","email":"hidden@example.test"}']]);
$assertSame(['id' => 7, 'name' => 'Fixture Editor'], $client->testConnection(), 'Connection success must expose only safe identity fields.');
$assertSame('https://wp.example.test/site', $client->getBaseUrl(), 'The client must retain only the normalized base URL.');
$assertSame('fixture-editor', $state->requests[0]['auth']['username'], 'The username must be trimmed.');
$assertSame('abcdefghijkl', $state->requests[0]['auth']['password'], 'Spaces must be removed from the application password.');
$assertTrue(str_ends_with($state->requests[0]['url'], '/site/wp-json/wp/v2/users/me'), 'Connection must use the users/me endpoint under the WordPress subdirectory.');
$assertTrue(in_array('User-Agent: insomnia/11.0.2', $state->requests[0]['headers'], true), 'The parity User-Agent must be preserved.');

[$client, $state] = $makeClient([['status' => 200, 'body' => '[{"slug":"other","id":9},{"slug":"fixture course","id":42}]']]);
$assertSame(42, $client->resolveCoursePostId('fixture course'), 'Course resolution must require an exact slug and positive ID.');
$assertTrue(str_ends_with($state->requests[0]['url'], '?slug=fixture%20course'), 'The slug query must use RFC 3986 encoding.');

[$client] = $makeClient([['status' => 200, 'body' => '[{"slug":"fixture","id":"42"}]']]);
$exception = $captureException(WordPressClientException::class, fn () => $client->resolveCoursePostId('fixture'), 'A string post ID must not be accepted as a positive integer.');
$assertSame('course_not_found', $exception?->getReason(), 'Invalid post IDs must not become authoritative targets.');

[$client, $state] = $makeClient([
    ['status' => 401, 'body' => '{}'],
    ['status' => 200, 'body' => '[{"slug":"fixture","id":42}]'],
]);
$assertSame(42, $client->resolveCoursePostId('fixture'), 'Course GET must allow one unauthenticated fallback after 401.');
$assertTrue($state->requests[0]['auth'] !== null && $state->requests[1]['auth'] === null, 'Credentials must be omitted from the unauthenticated fallback.');

[$client] = $makeClient([['status' => 200, 'body' => '{"id":42,"acf":{"program":false}}']]);
$assertSame(['id' => 42, 'acf' => ['program' => false]], $client->getCourse(42), 'Course reads must return an object-shaped response.');

[$client, $state] = $makeClient([
    ['status' => 503, 'body' => 'temporary remote detail'],
    ['status' => 200, 'body' => '{"id":42}'],
]);
$client->updateCourseProgram(42, [['data' => '10.01.2026']]);
$assertSame(2, count($state->requests), 'An explicit retryable POST response may be retried once before success.');
$assertSame('POST', $state->requests[0]['method'], 'Course updates must use POST.');
$assertSame('{"acf":{"program":[{"data":"10.01.2026"}]}}', $state->requests[0]['body'], 'Course updates must post only the exact ACF program payload.');
$assertSame($state->requests[0]['body'], $state->requests[1]['body'], 'Explicit-response POST retries must retain the same idempotent payload.');
$assertSame([1.35], $state->sleeps, 'First exponential backoff must be deterministic with injected jitter.');

[$client, $state] = $makeClient([]);
$exception = $captureException(
    WordPressClientException::class,
    fn () => $client->updateCourseProgram(42, [['data' => '10.01.2026', 'unexpected' => 'value']]),
    'The protocol client must reject payload rows containing fields other than data.'
);
$assertSame('invalid_request', $exception?->getReason(), 'Malformed update payloads must use the invalid-request category.');
$assertSame(0, count($state->requests), 'Malformed update payloads must be rejected before DNS or transport.');

[$client, $state] = $makeClient([$scenarios['post-timeout-delivery-unknown']['responses'][0]]);
$exception = $captureException(WordPressClientException::class, fn () => $client->updateCourseProgram(42, false), 'A POST timeout must not be retried.');
$assertSame('timeout', $exception?->getReason(), 'POST timeout must retain a safe timeout category.');
$assertSame(1, count($state->requests), 'A POST timeout with unknown delivery must perform one exchange.');
$executedScenarios[] = 'post-timeout-delivery-unknown';

[$client, $state] = $makeClient([$scenarios['get-timeout']['responses'][0]]);
$captureException(WordPressClientException::class, fn () => $client->resolveCoursePostId('fixture'), 'GET timeouts must map to a safe error.');
$assertSame(1, count($state->requests), 'Transport failures are not explicit-response retries.');
$executedScenarios[] = 'get-timeout';

$dnsBeforeRetries = count($dnsCalls);
[$client, $state] = $makeClient($scenarios['rate-limited-retries']['responses']);
$assertSame(['id' => 7, 'name' => 'Fixture Editor'], $client->testConnection(), 'Rate-limited connection must succeed after bounded retries.');
$assertSame(3, count($state->requests), 'Rate limiting must use only the mocked retry sequence.');
$assertSame([2.0, 2.6], $state->sleeps, 'Retry-After and exponential backoff must be deterministic.');
$assertSame(3, count($dnsCalls) - $dnsBeforeRetries, 'DNS must be resolved and repinned for every retry attempt.');
$executedScenarios[] = 'rate-limited-retries';

[$client, $state] = $makeClient($scenarios['cloudflare-challenge']['responses']);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Cloudflare challenges must have a specific safe error.');
$assertSame('browser_challenge', $exception?->getReason(), 'Cloudflare challenges must retain their category.');
$assertSame(1, count($state->requests), 'Cloudflare challenges must not be retried.');
$executedScenarios[] = 'cloudflare-challenge';

[$client, $state] = $makeClient([['status' => 200, 'headers' => ['cf-mitigated' => 'challenge'], 'body' => '{"id":7,"name":"Unexpected"}']]);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Challenge markers must be detected even on a nominally successful status.');
$assertSame('browser_challenge', $exception?->getReason(), 'Successful-status challenge markers must retain the browser-challenge category.');

[$client] = $makeClient($scenarios['invalid-json']['responses']);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Invalid JSON must have a safe error.');
$assertSame('invalid_response', $exception?->getReason(), 'Invalid JSON must retain the invalid-response category.');
$executedScenarios[] = 'invalid-json';

[$client, $state] = $makeClient([
    ['status' => 302, 'headers' => ['Location' => '/site/next']],
    ['status' => 200, 'body' => '{"id":7,"slug":"fixture-editor"}'],
]);
$assertSame(['id' => 7, 'name' => 'fixture-editor'], $client->testConnection(), 'Safe same-host redirects must preserve authenticated workflow.');
$assertSame(2, count($state->requests), 'A safe redirect must perform exactly one additional exchange.');
$assertSame([0.85], $state->sleeps, 'Redirect exchanges must be spaced by at least 0.85 seconds.');

[$client, $state] = $makeClient($scenarios['redirect-private-ip']['responses']);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Redirects to private IPs must be rejected.');
$assertSame('unsafe_redirect', $exception?->getReason(), 'Authenticated private cross-host redirects must be rejected before target DNS.');
$assertSame(1, count($state->requests), 'Private redirects must be rejected before a second exchange.');
$executedScenarios[] = 'redirect-private-ip';

[$client, $state] = $makeClient($scenarios['authenticated-cross-host-redirect']['responses']);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Authenticated cross-host redirects must be rejected by the client.');
$assertSame('unsafe_redirect', $exception?->getReason(), 'Cross-host redirects must retain the unsafe category.');
$assertSame(1, count($state->requests), 'Credentials must never be sent to the cross-host target.');
$executedScenarios[] = 'authenticated-cross-host-redirect';

[$client, $state] = $makeClient($scenarios['too-many-redirects']['responses']);
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'More than three redirects must be rejected.');
$assertSame('too_many_redirects', $exception?->getReason(), 'Redirect overflow must retain its safe category.');
$assertSame(4, count($state->requests), 'The initial exchange plus three redirects is the maximum.');
$executedScenarios[] = 'too-many-redirects';

[$client, $state] = $makeClient(array_fill(0, 5, ['status' => 403, 'body' => 'SENSITIVE REMOTE BODY']));
$exception = $captureException(WordPressClientException::class, fn () => $client->testConnection(), 'Denied responses must exhaust only the bounded retry budget.');
$assertSame(5, count($state->requests), 'At most four retries may follow the first attempt.');
$assertTrue(!str_contains((string) $exception?->getMessage(), 'SENSITIVE'), 'Remote response bodies must not appear in exceptions.');
$assertTrue(!str_contains((string) $exception?->getMessage(), 'abcdefghijkl'), 'Credentials must not appear in exceptions.');

$missingScenarios = array_values(array_diff(array_keys($scenarios), array_unique($executedScenarios)));
$assertSame([], $missingScenarios, 'Every locked HTTP/DNS fixture scenario must execute in Phase 2.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, sprintf("%d of %d Phase 2 checks failed.\n", count($failures), $checks));
    exit(1);
}

fwrite(STDOUT, "Phase 2 WordPress updater network safety: {$checks} checks passed; all mocked scenarios executed; no network used.\n");
