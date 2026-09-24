<?php

declare(strict_types=1);

namespace Espo\Core\Utils {
    final class Log
    {
        /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
        public array $entries = [];

        /** @param array<string, mixed> $context */
        public function info(string $message, array $context = []): void
        {
            $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
        }

        /** @param array<string, mixed> $context */
        public function warning(string $message, array $context = []): void
        {
            $this->entries[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
        }
    }
}

namespace {
    use Espo\Core\Utils\Log;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Config\Settings;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\Generation\GenerationException;
    use Espo\Modules\ContentFactory\Tools\ContentFactory\N8n\N8nHttpTransport;

    $module = dirname(__DIR__, 2) . '/files/custom/Espo/Modules/ContentFactory';

    require $module . '/Tools/ContentFactory/Config/Settings.php';
    require $module . '/Tools/ContentFactory/Generation/GenerationException.php';
    require $module . '/Tools/ContentFactory/N8n/N8nHttpTransport.php';

    $settings = new Settings(
        true,
        'https://n8n.example.test/webhook/content-factory',
        'secret-token-must-not-be-logged',
        5,
        180,
    );
    $log = new Log();
    $capturedRequest = null;
    $transport = new N8nHttpTransport(
        $log,
        static function (array $request) use (&$capturedRequest): array {
            $capturedRequest = $request;

            return [
                'success' => true,
                'status' => 200,
                'contentType' => 'application/json; charset=utf-8',
                'body' => '{"success":true}',
            ];
        },
    );

    $transport->post($settings, '{"brief":"test"}', 'timeout-unit-test');

    if (
        !is_array($capturedRequest) ||
        $capturedRequest['connectTimeout'] !== 5 ||
        $capturedRequest['responseTimeout'] !== 180
    ) {
        throw new RuntimeException('Timeout seconds were changed before reaching the HTTP runner.');
    }

    $started = $log->entries[0]['context'] ?? [];

    if (
        ($started['configuredResponseTimeout'] ?? null) !== 180 ||
        ($started['finalCurlResponseTimeout'] ?? null) !== 180 ||
        ($started['timeoutUnit'] ?? null) !== 'seconds'
    ) {
        throw new RuntimeException('Timeout diagnostic log does not report 180 seconds.');
    }

    $serializedLog = json_encode($log->entries, JSON_THROW_ON_ERROR);

    if (str_contains($serializedLog, 'secret-token-must-not-be-logged')) {
        throw new RuntimeException('Authentication token leaked into diagnostic logs.');
    }

    $failureLog = new Log();
    $failureTransport = new N8nHttpTransport(
        $failureLog,
        static fn (array $request): array => [
            'success' => false,
            'reason' => 'timeout',
            'errorCode' => 28,
            'errorMessage' => 'Operation timed out after 180000 milliseconds',
        ],
    );

    try {
        $failureTransport->post($settings, '{}', 'timeout-failure-test');
        throw new RuntimeException('Transport timeout was not mapped to N8N_TIMEOUT.');
    } catch (GenerationException $exception) {
        if ($exception->getErrorCode() !== 'N8N_TIMEOUT') {
            throw $exception;
        }
    }

    $failure = $failureLog->entries[1]['context'] ?? [];

    if (
        !is_int($failure['elapsedMilliseconds'] ?? null) ||
        ($failure['curlErrorCode'] ?? null) !== 28 ||
        ($failure['exceptionMessage'] ?? null) === null
    ) {
        throw new RuntimeException('Transport failure diagnostics are incomplete.');
    }

    echo "n8n HTTP timeout-unit tests passed.\n";
}
