<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use Closure;
use JsonException;
use RuntimeException;

class WordPressClientException extends RuntimeException
{
    public function __construct(private string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

class WordPressCourseClient
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];
    private const RETRY_STATUSES = [403, 429, 500, 502, 503, 504];
    private const MAX_REDIRECTS = 3;
    private const MAX_RETRIES = 4;
    private const MINIMUM_REQUEST_INTERVAL = 0.85;
    private const BASE_BACKOFF_SECONDS = 1.25;
    private const MAX_BACKOFF_SECONDS = 12.0;
    private const MAX_RETRY_AFTER_SECONDS = 30.0;

    private string $baseUrl;
    private string $username;
    private string $password;
    private Closure $sleeper;
    private Closure $clock;
    private Closure $jitter;
    private ?float $lastRequestCompletedAt = null;

    public function __construct(
        string $baseUrl,
        string $username,
        string $applicationPassword,
        private WordPressUrlGuard $urlGuard,
        private WordPressHttpTransport $transport,
        ?Closure $sleeper = null,
        ?Closure $clock = null,
        ?Closure $jitter = null
    ) {
        $this->baseUrl = $this->urlGuard->normalize($baseUrl);
        $this->username = trim($username);
        $this->password = str_replace(' ', '', trim($applicationPassword));
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
        $this->jitter = $jitter ?? static fn (float $minimum, float $maximum): float =>
            random_int((int) round($minimum * 1000), (int) round($maximum * 1000)) / 1000;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /** @return array{id: int, name: string} */
    public function testConnection(): array
    {
        $data = $this->requestJson('GET', '/wp-json/wp/v2/users/me', true, false);
        $id = $this->positiveInteger($data['id'] ?? null);
        $name = $this->stringValue($data['name'] ?? null);

        if ($name === '') {
            $name = $this->stringValue($data['slug'] ?? null);
        }

        if ($id === null || $name === '') {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        return ['id' => $id, 'name' => $name];
    }

    public function resolveCoursePostId(string $slug): int
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new WordPressClientException('course_not_found', 'Course not found by slug.');
        }

        $query = http_build_query(['slug' => $slug], '', '&', PHP_QUERY_RFC3986);
        $data = $this->requestJson('GET', '/wp-json/wp/v2/cursuri?' . $query, true, true);

        if (!array_is_list($data)) {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        foreach ($data as $item) {
            if (!is_array($item) || ($item['slug'] ?? null) !== $slug) {
                continue;
            }

            $id = $this->positiveInteger($item['id'] ?? null);

            if ($id !== null) {
                return $id;
            }
        }

        throw new WordPressClientException('course_not_found', 'Course not found by slug.');
    }

    /** @return array<string, mixed> */
    public function getCourse(int $postId): array
    {
        if ($postId <= 0) {
            throw new WordPressClientException('course_not_found', 'The WordPress course was not found.');
        }

        $data = $this->requestJson('GET', '/wp-json/wp/v2/cursuri/' . $postId, true, true);

        if (array_is_list($data)) {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        return $data;
    }

    /**
     * @param array<int, array{data: string}>|false $program
     * @return array<string, mixed>
     */
    public function updateCourseProgram(int $postId, array|false $program): array
    {
        if ($postId <= 0) {
            throw new WordPressClientException('course_not_found', 'The WordPress course was not found.');
        }

        if (is_array($program)) {
            if (!array_is_list($program)) {
                throw new WordPressClientException('invalid_request', 'The WordPress update payload is invalid.');
            }

            $validatedProgram = [];

            foreach ($program as $row) {
                if (!is_array($row) || array_keys($row) !== ['data'] || !is_string($row['data'])) {
                    throw new WordPressClientException('invalid_request', 'The WordPress update payload is invalid.');
                }

                $validatedProgram[] = ['data' => $row['data']];
            }

            $program = $validatedProgram;
        }

        try {
            $body = json_encode(
                ['acf' => ['program' => $program]],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new WordPressClientException('invalid_request', 'The WordPress update payload is invalid.');
        }

        $data = $this->requestJson(
            'POST',
            '/wp-json/wp/v2/cursuri/' . $postId,
            true,
            false,
            $body
        );

        if (array_is_list($data)) {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        return $data;
    }

    /**
     * @return array<mixed>
     */
    private function requestJson(
        string $method,
        string $path,
        bool $authenticated,
        bool $allowUnauthenticatedFallback,
        ?string $body = null
    ): array {
        $url = $this->endpoint($path);
        $lastResponse = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            $response = $this->sendWithRedirects($method, $url, $authenticated, $body);

            if ($this->isCloudflareChallenge($response)) {
                $this->throwForResponse($response);
            }

            if ($response['status'] >= 200 && $response['status'] < 300) {
                return $this->decodeJsonObjectOrList($response['body']);
            }

            if ($response['status'] === 401 && $allowUnauthenticatedFallback && $authenticated) {
                $fallback = $this->sendWithRedirects($method, $url, false, $body);

                if ($this->isCloudflareChallenge($fallback)) {
                    $this->throwForResponse($fallback);
                }

                if ($fallback['status'] >= 200 && $fallback['status'] < 300) {
                    return $this->decodeJsonObjectOrList($fallback['body']);
                }

                $this->throwForResponse($fallback);
            }

            $lastResponse = $response;

            if (!in_array($response['status'], self::RETRY_STATUSES, true) || $attempt >= self::MAX_RETRIES) {
                break;
            }

            $this->sleep($this->retryDelay($attempt, $response));
        }

        if ($lastResponse !== null) {
            $this->throwForResponse($lastResponse);
        }

        throw new WordPressClientException('request_failed', 'The WordPress operation could not be completed.');
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function sendWithRedirects(string $method, string $url, bool $authenticated, ?string $body): array
    {
        $currentMethod = strtoupper($method);
        $currentBody = $body;
        try {
            $approvedUrl = $this->urlGuard->approve($url);
        } catch (WordPressUrlException $exception) {
            throw new WordPressClientException($exception->getReason(), $exception->getMessage());
        }

        for ($redirectCount = 0; $redirectCount <= self::MAX_REDIRECTS; $redirectCount++) {
            $this->spaceRequest();

            try {
                $response = $this->transport->send(
                    $approvedUrl,
                    $currentMethod,
                    $this->headers(),
                    $authenticated ? ['username' => $this->username, 'password' => $this->password] : null,
                    $currentBody
                );
            } catch (WordPressTransportException $exception) {
                $this->lastRequestCompletedAt = ($this->clock)();
                throw new WordPressClientException($exception->getReason(), $exception->getMessage());
            }

            $this->lastRequestCompletedAt = ($this->clock)();

            if (!in_array($response['status'], self::REDIRECT_STATUSES, true)) {
                return $response;
            }

            $location = trim((string) ($response['headers']['location'] ?? ''));

            if ($location === '') {
                throw new WordPressClientException('invalid_redirect', 'WordPress returned an invalid redirect.');
            }

            if ($redirectCount >= self::MAX_REDIRECTS) {
                throw new WordPressClientException('too_many_redirects', 'WordPress returned too many redirects.');
            }

            try {
                $approvedUrl = $this->urlGuard->approveRedirect($approvedUrl, $location, $authenticated);
            } catch (WordPressUrlException $exception) {
                throw new WordPressClientException($exception->getReason(), $exception->getMessage());
            }

            if ($response['status'] === 303 ||
                (in_array($response['status'], [301, 302], true) && $currentMethod === 'POST')) {
                $currentMethod = 'GET';
                $currentBody = null;
            }
        }

        throw new WordPressClientException('too_many_redirects', 'WordPress returned too many redirects.');
    }

    /** @return array<int, string> */
    private function headers(): array
    {
        return [
            'User-Agent: insomnia/11.0.2',
            'Accept: */*',
            'Content-Type: application/json',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . '/',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ];
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function spaceRequest(): void
    {
        if ($this->lastRequestCompletedAt === null) {
            return;
        }

        $elapsed = ($this->clock)() - $this->lastRequestCompletedAt;

        if ($elapsed < self::MINIMUM_REQUEST_INTERVAL) {
            $this->sleep(self::MINIMUM_REQUEST_INTERVAL - max(0.0, $elapsed));
        }
    }

    private function retryDelay(int $attempt, array $response): float
    {
        $retryAfter = trim((string) ($response['headers']['retry-after'] ?? ''));

        if ($retryAfter !== '' && is_numeric($retryAfter) && (float) $retryAfter >= 0) {
            return min((float) $retryAfter, self::MAX_RETRY_AFTER_SECONDS);
        }

        $base = min(self::BASE_BACKOFF_SECONDS * (2 ** $attempt), self::MAX_BACKOFF_SECONDS);

        return $base + ($this->jitter)(0.1, 0.5);
    }

    private function sleep(float $seconds): void
    {
        ($this->sleeper)(max(0.0, $seconds));
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     */
    private function isCloudflareChallenge(array $response): bool
    {
        return strtolower((string) ($response['headers']['cf-mitigated'] ?? '')) === 'challenge' ||
            stripos($response['body'], 'Just a moment') !== false;
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $response
     */
    private function throwForResponse(array $response): never
    {
        if ($this->isCloudflareChallenge($response)) {
            throw new WordPressClientException('browser_challenge', 'WordPress rejected the request with a browser challenge.');
        }

        if ($response['status'] >= 500) {
            throw new WordPressClientException('unavailable', 'WordPress is temporarily unavailable.');
        }

        throw match ($response['status']) {
            401 => new WordPressClientException('authentication_failed', 'WordPress authentication failed.'),
            403 => new WordPressClientException('operation_denied', 'WordPress denied this operation.'),
            404 => new WordPressClientException('endpoint_not_found', 'The required WordPress endpoint was not found.'),
            429 => new WordPressClientException('rate_limited', 'WordPress is temporarily rate limiting requests.'),
            default => new WordPressClientException('request_rejected', 'WordPress rejected the request.'),
        };
    }

    /** @return array<mixed> */
    private function decodeJsonObjectOrList(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        if (!is_array($data)) {
            throw new WordPressClientException('invalid_response', 'WordPress returned an invalid response.');
        }

        return $data;
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
