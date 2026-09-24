<?php

declare(strict_types=1);

namespace Espo\Modules\ContentFactory\Tools\ContentFactory\N8n;

use Closure;
use Espo\Core\Utils\Log;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Config\Settings;
use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
use Throwable;

final class N8nHttpTransport
{
    public const MAX_RESPONSE_BYTES = 8 * 1024 * 1024;

    private Closure $runner;

    public function __construct(
        private Log $log,
        ?Closure $runner = null,
    ) {
        $this->runner = $runner ?? Closure::fromCallable([$this, 'runCurl']);
    }

    /** @return array{status: int, contentType: string, body: string} */
    public function post(Settings $settings, string $body, string $requestId): array
    {
        $startedAt = microtime(true);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Request-ID: ' . $requestId,
        ];

        if ($settings->authToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $settings->authToken;
        }

        $this->log->info('ContentFactory n8n request started.', [
            'requestId' => $requestId,
            'startedAtUtc' => gmdate('c'),
            'configuredConnectTimeout' => $settings->connectTimeoutSeconds,
            'configuredResponseTimeout' => $settings->responseTimeoutSeconds,
            'finalCurlConnectTimeout' => $settings->connectTimeoutSeconds,
            'finalCurlResponseTimeout' => $settings->responseTimeoutSeconds,
            'timeoutUnit' => 'seconds',
        ]);

        try {
            $result = ($this->runner)([
                'url' => $settings->webhookUrl,
                'headers' => $headers,
                'body' => $body,
                'connectTimeout' => $settings->connectTimeoutSeconds,
                'responseTimeout' => $settings->responseTimeoutSeconds,
                'maxResponseBytes' => self::MAX_RESPONSE_BYTES,
            ]);
        } catch (Throwable $exception) {
            $this->log->warning('ContentFactory n8n request threw an exception.', [
                'requestId' => $requestId,
                'elapsedMilliseconds' => $this->elapsedMilliseconds($startedAt),
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            if ($exception instanceof GenerationException) {
                throw $exception;
            }

            throw new GenerationException(
                'N8N_UNAVAILABLE',
                502,
                'Serviciul extern de generare nu este disponibil.',
            );
        }

        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            $reason = is_array($result) ? ($result['reason'] ?? null) : null;

            $this->log->warning('ContentFactory n8n HTTP request failed.', [
                'requestId' => $requestId,
                'elapsedMilliseconds' => $this->elapsedMilliseconds($startedAt),
                'reason' => is_string($reason) ? $reason : 'unknown',
                'curlErrorCode' => is_array($result) ? ($result['errorCode'] ?? null) : null,
                'exceptionClass' => null,
                'exceptionMessage' => is_array($result) ? ($result['errorMessage'] ?? null) : null,
            ]);

            if ($reason === 'timeout') {
                throw new GenerationException(
                    'N8N_TIMEOUT',
                    504,
                    'Serviciul de generare nu a răspuns în timpul permis.',
                );
            }

            if ($reason === 'response_too_large') {
                throw new GenerationException(
                    'N8N_RESPONSE_TOO_LARGE',
                    502,
                    'Răspunsul serviciului de generare este prea mare.',
                );
            }

            throw new GenerationException(
                'N8N_UNAVAILABLE',
                502,
                'Serviciul extern de generare nu este disponibil.',
            );
        }

        $status = $result['status'] ?? null;
        $responseBody = $result['body'] ?? null;
        $contentType = strtolower((string) ($result['contentType'] ?? ''));

        $this->log->info('ContentFactory n8n HTTP response received.', [
            'requestId' => $requestId,
            'elapsedMilliseconds' => $this->elapsedMilliseconds($startedAt),
            'httpStatus' => is_int($status) ? $status : null,
            'contentType' => $contentType,
            'responseBytes' => is_string($responseBody) ? strlen($responseBody) : null,
        ]);

        if (!is_int($status) || !is_string($responseBody)) {
            throw new GenerationException(
                'N8N_RESPONSE_INVALID',
                502,
                'Răspunsul serviciului de generare nu este valid.',
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new GenerationException(
                'N8N_HTTP_ERROR',
                502,
                'Serviciul extern nu a putut genera conținutul.',
            );
        }

        if (!str_starts_with($contentType, 'application/json')) {
            throw new GenerationException(
                'N8N_RESPONSE_INVALID',
                502,
                'Răspunsul serviciului de generare nu este valid.',
            );
        }

        return ['status' => $status, 'contentType' => $contentType, 'body' => $responseBody];
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @param array{url: string, headers: list<string>, body: string, connectTimeout: int, responseTimeout: int, maxResponseBytes: int} $request
     * @return array{success: bool, status?: int, contentType?: string, body?: string, reason?: string, errorCode?: int, errorMessage?: string}
     */
    private function runCurl(array $request): array
    {
        $handle = curl_init();

        if ($handle === false) {
            return ['success' => false, 'reason' => 'connection'];
        }

        $responseBody = '';
        $tooLarge = false;

        curl_setopt_array($handle, [
            CURLOPT_URL => $request['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request['body'],
            CURLOPT_HTTPHEADER => $request['headers'],
            CURLOPT_CONNECTTIMEOUT => $request['connectTimeout'],
            CURLOPT_TIMEOUT => $request['responseTimeout'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_WRITEFUNCTION => static function (
                \CurlHandle $unused,
                string $chunk,
            ) use (&$responseBody, &$tooLarge, $request): int {
                if (strlen($responseBody) + strlen($chunk) > $request['maxResponseBytes']) {
                    $tooLarge = true;
                    return 0;
                }

                $responseBody .= $chunk;

                return strlen($chunk);
            },
        ]);

        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $errorCode = curl_errno($handle);
        $errorMessage = curl_error($handle);
        curl_close($handle);

        if ($tooLarge) {
            return ['success' => false, 'reason' => 'response_too_large'];
        }

        if ($success === false) {
            return [
                'success' => false,
                'reason' => $errorCode === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'connection',
                'errorCode' => $errorCode,
                'errorMessage' => $errorMessage,
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'contentType' => $contentType,
            'body' => $responseBody,
        ];
    }
}
