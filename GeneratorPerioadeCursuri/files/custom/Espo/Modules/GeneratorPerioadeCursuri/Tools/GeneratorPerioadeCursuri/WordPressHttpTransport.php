<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use Closure;
use RuntimeException;
use Throwable;

class WordPressTransportException extends RuntimeException
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

class WordPressHttpTransport
{
    public const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;
    public const CONNECT_TIMEOUT_SECONDS = 5;
    public const RESPONSE_TIMEOUT_SECONDS = 30;

    private Closure $runner;

    public function __construct(?Closure $runner = null)
    {
        $this->runner = $runner ?? Closure::fromCallable([$this, 'runCurl']);
    }

    /**
     * @param array{url: string, scheme: string, host: string, port: int, explicitPort: bool, pinnedAddress: string, curlResolve: string} $approvedUrl
     * @param array<int, string> $headers
     * @param array{username: string, password: string}|null $auth
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function send(
        array $approvedUrl,
        string $method,
        array $headers,
        ?array $auth = null,
        ?string $body = null
    ): array {
        $method = strtoupper($method);

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new WordPressTransportException('request_failed', 'The WordPress request could not be completed.');
        }

        $responseHeaders = [];
        $responseBody = '';
        $responseTooLarge = false;

        $onHeader = static function (string $line) use (&$responseHeaders, &$responseTooLarge): int {
            $length = strlen($line);
            $trimmed = trim($line);

            if (str_starts_with(strtolower($trimmed), 'http/')) {
                $responseHeaders = [];
                $responseTooLarge = false;
                return $length;
            }

            if ($trimmed === '') {
                return $length;
            }

            $separator = strpos($trimmed, ':');

            if ($separator === false) {
                return $length;
            }

            $name = strtolower(trim(substr($trimmed, 0, $separator)));
            $value = trim(substr($trimmed, $separator + 1));
            $responseHeaders[$name] = $value;

            if ($name === 'content-length' && ctype_digit($value) && (int) $value > self::MAX_RESPONSE_BYTES) {
                $responseTooLarge = true;
            }

            return $length;
        };

        $onBody = static function (string $chunk) use (&$responseBody, &$responseTooLarge): int {
            if ($responseTooLarge || strlen($responseBody) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                $responseTooLarge = true;
                return 0;
            }

            $responseBody .= $chunk;

            return strlen($chunk);
        };

        $request = [
            'url' => $approvedUrl['url'],
            'resolve' => $approvedUrl['curlResolve'],
            'method' => $method,
            'headers' => $headers,
            'auth' => $auth,
            'body' => $body,
            'connectTimeout' => self::CONNECT_TIMEOUT_SECONDS,
            'responseTimeout' => self::RESPONSE_TIMEOUT_SECONDS,
        ];

        try {
            $result = ($this->runner)($request, $onHeader, $onBody);
        } catch (WordPressTransportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new WordPressTransportException('request_failed', 'The WordPress request could not be completed.');
        }

        if ($responseTooLarge) {
            throw new WordPressTransportException('response_too_large', 'WordPress returned a response that is too large.');
        }

        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            $reason = is_array($result) ? (string) ($result['reason'] ?? '') : '';

            if ($reason === 'timeout') {
                throw new WordPressTransportException('timeout', 'The WordPress request timed out.');
            }

            if ($reason === 'connection') {
                throw new WordPressTransportException('connection_failed', 'Unable to reach WordPress.');
            }

            throw new WordPressTransportException('request_failed', 'The WordPress request could not be completed.');
        }

        $status = $result['status'] ?? null;

        if (!is_int($status) || $status < 100 || $status > 599) {
            throw new WordPressTransportException('invalid_response', 'WordPress returned an invalid response.');
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $responseBody,
        ];
    }

    /**
     * @param array{
     *     url: string,
     *     resolve: string,
     *     method: string,
     *     headers: array<int, string>,
     *     auth: array{username: string, password: string}|null,
     *     body: string|null,
     *     connectTimeout: int,
     *     responseTimeout: int
     * } $request
     * @return array{success: bool, status?: int, reason?: string}
     */
    private function runCurl(array $request, Closure $onHeader, Closure $onBody): array
    {
        $handle = curl_init();

        if ($handle === false) {
            return ['success' => false, 'reason' => 'request'];
        }

        $options = [
            CURLOPT_URL => $request['url'],
            CURLOPT_CUSTOMREQUEST => $request['method'],
            CURLOPT_HTTPHEADER => $request['headers'],
            CURLOPT_CONNECTTIMEOUT => $request['connectTimeout'],
            CURLOPT_TIMEOUT => $request['responseTimeout'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => static fn (\CurlHandle $handle, string $line): int => $onHeader($line),
            CURLOPT_WRITEFUNCTION => static fn (\CurlHandle $handle, string $chunk): int => $onBody($chunk),
            CURLOPT_RESOLVE => [$request['resolve']],
            CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_NOSIGNAL => true,
        ];

        if ($request['body'] !== null) {
            $options[CURLOPT_POSTFIELDS] = $request['body'];
        }

        if ($request['auth'] !== null) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERNAME] = $request['auth']['username'];
            $options[CURLOPT_PASSWORD] = $request['auth']['password'];
        }

        curl_setopt_array($handle, $options);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errorCode = curl_errno($handle);
        curl_close($handle);

        if ($success === false) {
            if ($errorCode === CURLE_OPERATION_TIMEDOUT) {
                return ['success' => false, 'reason' => 'timeout'];
            }

            if (in_array($errorCode, [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY], true)) {
                return ['success' => false, 'reason' => 'connection'];
            }

            return ['success' => false, 'reason' => 'request'];
        }

        return ['success' => true, 'status' => $status];
    }
}
