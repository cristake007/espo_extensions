<?php

declare(strict_types=1);

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayDocumentReissueService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$applicationRoot = dirname(__DIR__, 5);
chdir($applicationRoot);
require 'bootstrap.php';

$options = getopt('', ['request-id:', 'month:', 'dry-run', 'execute', 'reason:', 'confirm-attachment-id:']);
$requestId = trim((string) ($options['request-id'] ?? ''));
$month = trim((string) ($options['month'] ?? ''));
$execute = array_key_exists('execute', $options);

if ($requestId === '' || $month === '' || ($execute && array_key_exists('dry-run', $options))) {
    fwrite(STDERR, "Usage: php reissue-holiday-document.php --request-id=ID --month=YYYY-MM [--dry-run | --execute --reason=TEXT --confirm-attachment-id=ID]\n");
    exit(2);
}

$application = new Application();
$application->setupSystemUser();
$container = $application->getContainer();
$service = $container->getByClass(InjectableFactory::class)->create(HolidayDocumentReissueService::class);

try {
    if (!$execute) {
        $result = $service->dryRun($requestId, $month);
    } else {
        $reason = trim((string) ($options['reason'] ?? ''));
        $confirmation = trim((string) ($options['confirm-attachment-id'] ?? ''));
        if ($reason === '' || $confirmation === '') {
            throw new RuntimeException('--execute requires --reason and --confirm-attachment-id from a fresh dry-run.');
        }
        $operator = json_encode([
            'osUser' => get_current_user(),
            'effectiveUid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
            'host' => gethostname() ?: 'unknown',
            'cwd' => getcwd(),
            'phpBinary' => PHP_BINARY,
        ], JSON_THROW_ON_ERROR);
        $result = $service->execute($requestId, $month, $reason, $confirmation, $operator);
    }

    printf("Month: %s\n\n", $result['month']);
    printf("Annual entitlement %s: %s\n", substr($result['month'], 0, 4), $result['annualEntitlement']);
    printf("Carryover from %s: %s\n", (int) substr($result['month'], 0, 4) - 1, $result['carryover']);
    printf("Available before leave: %s\n", $result['availableBeforeLeave']);
    printf("Requested days: %s\n", $result['requestedDays']);
    printf("Projected remaining: %s\n\n", $result['projectedRemaining']);
    printf("Accounting changes: %s\n", $result['accountingChanges']);
    printf("Original document: %s (%s)\n", $result['originalDocument'], $result['originalAttachmentId']);
    if ($execute) {
        printf("New attachment: %s\nVerification code: %s\nSHA-256: %s\n", $result['newAttachmentId'], $result['verificationCode'], $result['sha256']);
    } else {
        printf("\nExecute only after review with:\n  --execute --reason=\"...\" --confirm-attachment-id=%s\n", $result['originalAttachmentId']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Reissue aborted safely: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
