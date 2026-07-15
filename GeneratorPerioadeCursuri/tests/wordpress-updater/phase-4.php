<?php

declare(strict_types=1);

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Attachment;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressClientException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressCourseClient;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressHttpTransport;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressProgramMerger;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressScheduleParser;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUpdaterHttpException;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUpdaterService;
use Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri\WordPressUrlGuard;
use WordPressUpdaterTest\Record;

$root = __DIR__;
$extensionRoot = dirname(__DIR__, 2);
$sourceRoot = $extensionRoot . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Tools/GeneratorPerioadeCursuri';

require $root . '/espo-service-test-double.php';

if (!class_exists(PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    require $root . '/phpspreadsheet-test-double.php';
}

require $sourceRoot . '/WordPressScheduleParser.php';
require $sourceRoot . '/WordPressProgramMerger.php';
require $sourceRoot . '/WordPressUrlGuard.php';
require $sourceRoot . '/WordPressHttpTransport.php';
require $sourceRoot . '/WordPressCourseClient.php';
require $sourceRoot . '/WordPressUpdaterService.php';

class FakeCourseClient extends WordPressCourseClient
{
    public array $resolveCalls = [];
    public array $getCalls = [];
    public array $updateCalls = [];

    public function __construct(private array $configuration)
    {
    }

    public function getBaseUrl(): string
    {
        return $this->configuration['baseUrl'] ?? 'https://wp.example.test';
    }

    public function getUsername(): string
    {
        return $this->configuration['username'] ?? 'fixture-editor';
    }

    public function testConnection(): array
    {
        $this->throwConfigured('connect');

        return $this->configuration['user'] ?? ['id' => 7, 'name' => 'Fixture Editor'];
    }

    public function resolveCoursePostId(string $slug): int
    {
        $this->resolveCalls[] = $slug;
        $this->throwConfigured('resolve');

        return $this->configuration['postId'] ?? 42;
    }

    public function getCourse(int $postId): array
    {
        $this->getCalls[] = $postId;
        $this->throwConfigured('get');

        return $this->configuration['course'] ?? ['acf' => ['program' => []]];
    }

    public function updateCourseProgram(int $postId, array|false $program): array
    {
        $this->updateCalls[] = [$postId, $program];
        $this->throwConfigured('update');

        return ['id' => $postId];
    }

    private function throwConfigured(string $operation): void
    {
        $reason = $this->configuration['throw'][$operation] ?? null;

        if (is_string($reason)) {
            throw new WordPressClientException($reason, 'Safe fake client error.');
        }
    }
}

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

$validCsv = "Title,Permalink,Ianuarie\n" .
    "Fixture Course,https://wp.example.test/cursuri/fixture-course/,13.01.2026\n";

/** @return array<string, mixed> */
$makeEnvironment = static function (string $csv = '') use ($validCsv): array {
    $entityManager = new EntityManager();
    $fileStorage = new FileStorageManager();
    $acl = new Acl();
    $language = new Language();
    $log = new Log();
    $record = new Record('record-1', [
        'wpScheduleFileId' => 'attachment-1',
        'wpBaseUrl' => 'https://wp.example.test',
        'wpUsername' => 'fixture-editor',
    ]);
    $attachment = new Attachment(
        'attachment-1',
        'schedule.csv',
        'GeneratorPerioadeCursuriWordPressUpdater',
        'record-1',
        'wpScheduleFile'
    );
    $entityManager->entities['GeneratorPerioadeCursuriWordPressUpdater:record-1'] = $record;
    $entityManager->entities['Attachment:attachment-1'] = $attachment;
    $fileStorage->contents['attachment-1'] = $csv !== '' ? $csv : $validCsv;
    $clientConfigurations = [];
    $factoryCalls = [];
    $clients = [];
    $factory = static function (string $baseUrl, string $username, string $password) use (
        &$clientConfigurations,
        &$factoryCalls,
        &$clients
    ): WordPressCourseClient {
        $factoryCalls[] = [$baseUrl, $username, $password];
        $client = new FakeCourseClient(array_shift($clientConfigurations) ?? []);
        $clients[] = $client;

        return $client;
    };
    $service = new WordPressUpdaterService(
        $entityManager,
        $fileStorage,
        new WordPressScheduleParser(),
        new WordPressProgramMerger(),
        new WordPressUrlGuard(static fn (): array => ['93.184.216.34']),
        new WordPressHttpTransport(static fn (): array => ['success' => false, 'reason' => 'request']),
        $acl,
        $language,
        $log,
        $factory,
        static fn (): DateTimeImmutable => new DateTimeImmutable(
            '2026-01-10',
            new DateTimeZone('Europe/Bucharest')
        )
    );

    return [
        'service' => $service,
        'entityManager' => $entityManager,
        'fileStorage' => $fileStorage,
        'acl' => $acl,
        'log' => $log,
        'record' => $record,
        'attachment' => $attachment,
        'clientConfigurations' => &$clientConfigurations,
        'factoryCalls' => &$factoryCalls,
        'clients' => &$clients,
    ];
};

$environment = $makeEnvironment(
    "Title,Permalink,Ianuarie\n" .
    "Fixture Course,https://wp.example.test/cursuri/fixture-course/,13.01.2026\n" .
    "Invalid Date,https://wp.example.test/cursuri/invalid-date/,31.02.2026\n" .
    "Missing Permalink,,14.01.2026\n"
);
$preview = $environment['service']->preview('record-1', (object) []);
$assertSame('attachment-1', $preview['previewSourceFileId'], 'Preview must return the authoritative attachment ID.');
$assertSame(3, count($preview['rows']), 'Preview must retain row-level errors without discarding other rows.');
$assertSame(['13.01.2026'], $preview['rows'][0]['finalDates'], 'Preview must compute local Excel-only final dates.');
$assertSame('wpUpdaterInvalidDate', $preview['rows'][1]['error'], 'Invalid date text must become a localized row error.');
$assertSame(false, $preview['rows'][1]['canUpdate'], 'Invalid date rows must not be updateable.');
$assertSame('wpUpdaterInvalidPermalink', $preview['rows'][2]['error'], 'Missing permalink must become a localized row error.');
$assertSame(false, $preview['rows'][2]['canFetch'], 'Missing permalink rows must disable WordPress actions.');
$assertSame([], $environment['factoryCalls'], 'Preview must not construct a WordPress client.');
$assertSame(['attachment-1'], $environment['fileStorage']->reads, 'Preview must read only the record-linked attachment.');

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = [
    'baseUrl' => 'https://wp.example.test/site',
    'username' => 'fixture-editor',
    'user' => ['id' => 7, 'name' => 'Fixture Editor'],
];
$connected = $environment['service']->connect('record-1', (object) [
    'wpBaseUrl' => ' https://wp.example.test/site/ ',
    'wpUsername' => ' fixture-editor ',
    'wpAppPassword' => 'dummy application password',
]);
$assertSame(['id' => 7, 'name' => 'Fixture Editor'], $connected['user'], 'Connect must return only the safe user identity.');
$assertSame('https://wp.example.test/site', $environment['record']->get('wpBaseUrl'), 'Connect must save the normalized base URL only after success.');
$assertSame('fixture-editor', $environment['record']->get('wpUsername'), 'Connect must save the trimmed username only after success.');
$assertTrue(!array_key_exists('wpAppPassword', $environment['record']->attributes), 'Connect must never persist the application password.');
$assertSame(1, count($environment['entityManager']->saved), 'Successful connection must save the record exactly once.');

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = ['throw' => ['connect' => 'authentication_failed']];
$exception = $captureException(
    WordPressUpdaterHttpException::class,
    fn () => $environment['service']->connect('record-1', (object) [
        'wpBaseUrl' => 'https://wp.example.test',
        'wpUsername' => 'fixture-editor',
        'wpAppPassword' => 'dummy password',
    ]),
    'Failed WordPress authentication must map to a client-safe upstream error.'
);
$assertSame(502, $exception?->getStatus(), 'WordPress authentication failure must use HTTP 502.');
$assertSame([], $environment['entityManager']->saved, 'Failed connection must not modify the updater record.');

$rowInput = static fn (array $extra = []): object => (object) array_merge([
    'previewSourceFileId' => 'attachment-1',
    'sourceRow' => 2,
    'wpAppPassword' => 'dummy password',
], $extra);

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = [
    'postId' => 42,
    'course' => ['acf' => ['program' => [['data' => '09.01.2026'], ['data' => '12.01.2026']]]],
];
$fetched = $environment['service']->fetchDates('record-1', $rowInput());
$assertSame(42, $fetched['postId'], 'Fetch must return the exact-slug resolved post ID.');
$assertSame(['12.01.2026'], $fetched['existingValidDates'], 'Fetch must filter expired current dates.');
$assertSame(['12.01.2026', '13.01.2026'], $fetched['finalDates'], 'Fetch must merge current and authoritative file dates.');
$assertSame([], $environment['clients'][0]->updateCalls, 'Fetch must never perform a WordPress update.');

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = [
    'course' => ['acf' => ['program' => [['data' => '12.01.2026']]]],
];
$updated = $environment['service']->updateRow('record-1', $rowInput());
$assertSame(true, $updated['updated'], 'Changed programs must report a successful update.');
$assertSame(
    [[42, [['data' => '12.01.2026'], ['data' => '13.01.2026']]]],
    $environment['clients'][0]->updateCalls,
    'Update must post the recomputed exact ACF program once.'
);
$assertSame(
    ['recordId' => 'record-1', 'sourceRow' => 2, 'postId' => 42],
    $environment['log']->infoEntries[0][1],
    'External write logs must contain safe identifiers only.'
);
$assertSame([], $environment['entityManager']->saved, 'Row updates must not persist preview or WordPress response state.');

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = [
    'course' => ['acf' => ['program' => [['data' => '13.01.2026']]]],
];
$unchanged = $environment['service']->updateRow('record-1', $rowInput());
$assertSame(false, $unchanged['updated'], 'Ordered-equal programs must report no changes.');
$assertSame('no changes', $unchanged['status'], 'Ordered-equal programs must use the no-changes status.');
$assertSame([], $environment['clients'][0]->updateCalls, 'No-change updates must perform zero POST calls.');
$assertSame([], $environment['log']->infoEntries, 'No-change updates must not log an external write.');

$environment = $makeEnvironment();
$exception = $captureException(
    BadRequest::class,
    fn () => $environment['service']->updateRow('record-1', $rowInput(['slug' => 'browser-target'])),
    'Browser-provided target fields must be rejected.'
);
$assertSame('wpUpdaterUnknownField', strtok((string) $exception?->getMessage(), ':'), 'Unknown target input must use the strict request error.');
$assertSame([], $environment['factoryCalls'], 'Unknown target input must be rejected before client construction.');

$environment = $makeEnvironment();
$exception = $captureException(
    WordPressUpdaterHttpException::class,
    fn () => $environment['service']->fetchDates('record-1', $rowInput(['previewSourceFileId' => 'old-attachment'])),
    'Changed attachment IDs must invalidate the preview.'
);
$assertSame(409, $exception?->getStatus(), 'Stale preview must use HTTP 409.');
$assertSame([], $environment['fileStorage']->reads, 'Stale preview must fail before attachment reads.');
$assertSame([], $environment['factoryCalls'], 'Stale preview must fail before client construction.');

$environment = $makeEnvironment();
$environment['entityManager']->entities['Attachment:attachment-1'] = new Attachment(
    'attachment-1',
    'schedule.csv',
    'GeneratorPerioadeCursuriWordPressUpdater',
    'different-record',
    'wpScheduleFile'
);
$captureException(
    Forbidden::class,
    fn () => $environment['service']->preview('record-1', (object) []),
    'Cross-record attachment access must be forbidden.'
);
$assertSame([], $environment['fileStorage']->reads, 'Cross-record attachment checks must happen before file reads.');
$assertSame([], $environment['factoryCalls'], 'Cross-record attachment checks must happen before client construction.');

$environment = $makeEnvironment();
$environment['acl']->scopeRead = false;
$captureException(
    Forbidden::class,
    fn () => $environment['service']->preview('record-1', (object) ['unexpected' => true]),
    'Scope ACL must be checked before request or file processing.'
);
$assertSame([], $environment['fileStorage']->reads, 'Denied scope access must perform no file read.');

$environment = $makeEnvironment();
$environment['acl']->recordEdit = false;
$captureException(
    Forbidden::class,
    fn () => $environment['service']->connect('record-1', (object) []),
    'Record read/edit ACL must guard connection operations.'
);
$assertSame([], $environment['factoryCalls'], 'Denied record access must perform no client construction.');

$environment = $makeEnvironment();
$captureException(
    NotFound::class,
    fn () => $environment['service']->fetchDates('record-1', $rowInput(['sourceRow' => 999])),
    'A source row absent from the authoritative file must return not found.'
);
$assertSame([], $environment['factoryCalls'], 'Missing source rows must fail before client construction.');

$environment = $makeEnvironment("Title,Permalink,Ianuarie\nInvalid,https://wp.example.test/cursuri/invalid/,31.02.2026\n");
$captureException(
    BadRequest::class,
    fn () => $environment['service']->fetchDates('record-1', $rowInput()),
    'Invalid authoritative file dates must fail before WordPress access.'
);
$assertSame([], $environment['factoryCalls'], 'Invalid file dates must fail before client construction.');

$wideHeader = implode(',', array_merge(['Title', 'Permalink'], array_fill(0, 49, 'Other')));
$environment = $makeEnvironment($wideHeader . "\nCourse,https://wp.example.test/cursuri/course/\n");
$exception = $captureException(
    WordPressUpdaterHttpException::class,
    fn () => $environment['service']->preview('record-1', (object) []),
    'Source boundary failures must use a bounded HTTP error.'
);
$assertSame(413, $exception?->getStatus(), 'Source width boundary must map to HTTP 413.');

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = ['throw' => ['resolve' => 'course_not_found']];
$captureException(
    NotFound::class,
    fn () => $environment['service']->fetchDates('record-1', $rowInput()),
    'Exact-slug misses must return a row-level not found response.'
);

$environment = $makeEnvironment();
$environment['clientConfigurations'][] = ['course' => ['acf' => 'invalid']];
$captureException(
    BadRequest::class,
    fn () => $environment['service']->fetchDates('record-1', $rowInput()),
    'Invalid WordPress course data must map to a safe 400 response.'
);

$routes = json_decode(
    (string) file_get_contents($extensionRoot . '/files/custom/Espo/Modules/GeneratorPerioadeCursuri/Resources/routes.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$updaterRoutes = array_values(array_filter(
    $routes,
    static fn (array $route): bool => str_contains($route['route'], 'GeneratorPerioadeCursuriWordPressUpdater')
));
$assertSame(4, count($updaterRoutes), 'Exactly four updater routes must be registered.');
$assertSame(
    ['connect', 'fetchDates', 'preview', 'updateRow'],
    (static function (array $routes): array {
        $actions = array_map(static fn (array $route): string => basename($route['route']), $routes);
        sort($actions);
        return $actions;
    })($updaterRoutes),
    'Updater routes must expose only the four planned actions.'
);

foreach ([
    'PostPreviewWordPressUpdate.php' => 'preview',
    'PostConnectWordPress.php' => 'connect',
    'PostFetchWordPressDates.php' => 'fetchDates',
    'PostUpdateWordPressCourse.php' => 'updateRow',
] as $file => $method) {
    $source = (string) file_get_contents($sourceRoot . '/Api/' . $file);
    $assertTrue(str_contains($source, 'implements Action'), "{$file} must be an Espo API Action.");
    $assertTrue(str_contains($source, "service->{$method}"), "{$file} must delegate to {$method}.");
    $assertTrue(!preg_match('/wpAppPassword.*(log|set)|Authorization/i', $source), "{$file} must not handle or persist secrets.");
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }

    fwrite(STDERR, sprintf("%d of %d Phase 4 checks failed.\n", count($failures), $checks));
    exit(1);
}

fwrite(STDOUT, "Phase 4 WordPress updater orchestration and API actions: {$checks} checks passed; no network used.\n");
