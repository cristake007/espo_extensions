<?php

declare(strict_types=1);

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Attachment;
use InvalidArgumentException;
use LengthException;
use RuntimeException;
use Throwable;

class WordPressUpdaterHttpException extends RuntimeException
{
    public function __construct(private int $status, string $message)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}

class WordPressUpdaterService
{
    private const ENTITY_TYPE = 'GeneratorPerioadeCursuriWordPressUpdater';
    private const SOURCE_FIELD = 'wpScheduleFile';
    private const MAX_ID_LENGTH = 64;
    private const MAX_PASSWORD_LENGTH = 500;

    private const PARSER_ERROR_KEYS = [
        'The source file is empty.' => 'wpUpdaterSourceEmpty',
        'The source file may be at most 20 MiB.' => 'wpUpdaterSourceTooLarge',
        'The source file must be CSV or XLSX.' => 'wpUpdaterSourceTypeInvalid',
        'The CSV file must use UTF-8.' => 'wpUpdaterCsvUtf8Required',
        'The CSV file could not be read.' => 'wpUpdaterCsvUnreadable',
        'The XLSX file does not have a valid structure.' => 'wpUpdaterXlsxStructureInvalid',
        'The XLSX file could not be read.' => 'wpUpdaterXlsxUnreadable',
        'The input file may contain at most 50 columns.' => 'wpUpdaterTooManyColumns',
        'The input file may contain at most 5000 non-empty course rows.' => 'wpUpdaterTooManyCourses',
    ];

    private const CLIENT_ERROR_KEYS = [
        'host_resolution' => 'wpUpdaterHostResolutionFailed',
        'unsafe_redirect' => 'wpUpdaterUnsafeRedirect',
        'invalid_redirect' => 'wpUpdaterUnsafeRedirect',
        'too_many_redirects' => 'wpUpdaterTooManyRedirects',
        'authentication_failed' => 'wpUpdaterAuthenticationFailed',
        'operation_denied' => 'wpUpdaterOperationDenied',
        'endpoint_not_found' => 'wpUpdaterEndpointNotFound',
        'rate_limited' => 'wpUpdaterRateLimited',
        'unavailable' => 'wpUpdaterUnavailable',
        'browser_challenge' => 'wpUpdaterBrowserChallenge',
        'response_too_large' => 'wpUpdaterResponseTooLarge',
        'invalid_response' => 'wpUpdaterInvalidResponse',
        'timeout' => 'wpUpdaterTimeout',
        'connection_failed' => 'wpUpdaterConnectionFailed',
    ];

    private Closure $clientFactory;
    private Closure $todayProvider;

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private WordPressScheduleParser $parser,
        private WordPressProgramMerger $merger,
        private WordPressUrlGuard $urlGuard,
        private WordPressHttpTransport $transport,
        private Acl $acl,
        private Language $language,
        private Log $log,
        ?Closure $clientFactory = null,
        ?Closure $todayProvider = null
    ) {
        $this->clientFactory = $clientFactory ?? fn (
            string $baseUrl,
            string $username,
            string $password
        ): WordPressCourseClient => new WordPressCourseClient(
            $baseUrl,
            $username,
            $password,
            $this->urlGuard,
            $this->transport
        );
        $this->todayProvider = $todayProvider ?? static fn (): DateTimeImmutable =>
            new DateTimeImmutable('today', new DateTimeZone('Europe/Bucharest'));
    }

    /** @return array<string, mixed> */
    public function preview(string $recordId, mixed $input = null): array
    {
        return $this->execute('preview', $recordId, null, function () use ($recordId, $input): array {
            $record = $this->loadRecord($recordId);
            $this->validateInput($input, []);
            [$sourceFileId, $contents, $fileName] = $this->loadSource($record);
            $rows = $this->parser->parse($contents, $fileName);
            $today = $this->today();
            $previewRows = [];

            foreach ($rows as $row) {
                $error = null;
                $merge = [
                    'finalDates' => [],
                    'payload' => ['acf' => ['program' => false]],
                ];

                if ($row['slug'] === '') {
                    $error = $this->translate('wpUpdaterInvalidPermalink');
                }

                try {
                    $merge = $this->merger->merge([], $row['excelDates'], $today);
                } catch (InvalidArgumentException $exception) {
                    $error = $this->translate('wpUpdaterInvalidDate');
                }

                $canFetch = $row['slug'] !== '';
                $canUpdate = $error === null && $canFetch && $merge['finalDates'] !== [];

                $previewRows[] = [
                    'sourceRow' => $row['sourceRow'],
                    'title' => $row['title'],
                    'permalink' => $row['permalink'],
                    'slug' => $row['slug'],
                    'postId' => null,
                    'excelDates' => $row['excelDates'],
                    'existingValidDates' => [],
                    'finalDates' => $merge['finalDates'],
                    'payload' => $merge['payload'],
                    'currentDatesLoaded' => false,
                    'canFetch' => $canFetch,
                    'canUpdate' => $canUpdate,
                    'status' => $error === null ? 'preview ready' : 'error',
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'previewSourceFileId' => $sourceFileId,
                'rows' => $previewRows,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function connect(string $recordId, mixed $input): array
    {
        return $this->execute('connect', $recordId, null, function () use ($recordId, $input): array {
            $record = $this->loadRecord($recordId);
            $data = $this->validateInput($input, ['wpBaseUrl', 'wpUsername', 'wpAppPassword']);
            $baseUrl = $this->requireString($data, 'wpBaseUrl', 2048);
            $username = $this->requireString($data, 'wpUsername', 150);
            $password = $this->requireString($data, 'wpAppPassword', self::MAX_PASSWORD_LENGTH);
            $client = $this->createClient($baseUrl, $username, $password);
            $user = $client->testConnection();

            $record->set('wpBaseUrl', $client->getBaseUrl());
            $record->set('wpUsername', $client->getUsername());
            $this->entityManager->saveEntity($record);

            return [
                'success' => true,
                'message' => $this->translate('wpUpdaterConnected'),
                'user' => $user,
                'wpBaseUrl' => $client->getBaseUrl(),
                'wpUsername' => $client->getUsername(),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function fetchDates(string $recordId, mixed $input): array
    {
        $sourceRow = $this->sourceRowForLog($input);

        return $this->execute('fetchDates', $recordId, $sourceRow, function () use ($recordId, $input): array {
            $record = $this->loadRecord($recordId);
            [$row, $password] = $this->loadAuthoritativeRow($record, $input);
            $client = $this->clientFromRecord($record, $password);
            $postId = $client->resolveCoursePostId($row['slug']);
            $existingProgram = $this->extractExistingProgram($client->getCourse($postId));
            $merge = $this->merger->merge($existingProgram, $row['excelDates'], $this->today());

            return [
                'success' => true,
                'sourceRow' => $row['sourceRow'],
                'postId' => $postId,
                'excelDates' => $row['excelDates'],
                'existingValidDates' => $merge['existingValidDates'],
                'finalDates' => $merge['finalDates'],
                'payload' => $merge['payload'],
                'currentDatesLoaded' => true,
                'canFetch' => true,
                'canUpdate' => $merge['finalDates'] !== [],
                'status' => 'dates loaded',
                'error' => null,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function updateRow(string $recordId, mixed $input): array
    {
        $sourceRow = $this->sourceRowForLog($input);

        return $this->execute('updateRow', $recordId, $sourceRow, function () use ($recordId, $input): array {
            $record = $this->loadRecord($recordId);
            [$row, $password] = $this->loadAuthoritativeRow($record, $input);
            $client = $this->clientFromRecord($record, $password);
            $postId = $client->resolveCoursePostId($row['slug']);
            $existingProgram = $this->extractExistingProgram($client->getCourse($postId));
            $merge = $this->merger->merge($existingProgram, $row['excelDates'], $this->today());

            if (!$merge['changed']) {
                return [
                    'success' => true,
                    'status' => 'no changes',
                    'updated' => false,
                    'sourceRow' => $row['sourceRow'],
                    'postId' => $postId,
                    'finalDates' => $merge['finalDates'],
                    'payload' => $merge['payload'],
                ];
            }

            $client->updateCourseProgram($postId, $merge['payload']['acf']['program']);

            $this->log->info('WordPress course program updated.', [
                'recordId' => $recordId,
                'sourceRow' => $row['sourceRow'],
                'postId' => $postId,
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'updated' => true,
                'sourceRow' => $row['sourceRow'],
                'postId' => $postId,
                'finalDates' => $merge['finalDates'],
                'payload' => $merge['payload'],
            ];
        });
    }

    /**
     * @return array{0: array{sourceRow: int, title: string, permalink: string, slug: string, excelDates: array<int, string>}, 1: string}
     */
    private function loadAuthoritativeRow(object $record, mixed $input): array
    {
        $data = $this->validateInput($input, ['previewSourceFileId', 'sourceRow', 'wpAppPassword']);
        $previewSourceFileId = $this->requireString($data, 'previewSourceFileId', self::MAX_ID_LENGTH);
        $sourceRow = $this->requireSourceRow($data);
        $password = $this->requireString($data, 'wpAppPassword', self::MAX_PASSWORD_LENGTH);
        $currentSourceFileId = $record->get(self::SOURCE_FIELD . 'Id');

        if (!is_string($currentSourceFileId) || $currentSourceFileId === '') {
            throw new NotFound($this->translate('wpUpdaterSourceNotFound'));
        }

        if ($previewSourceFileId !== $currentSourceFileId) {
            throw new WordPressUpdaterHttpException(409, $this->translate('wpUpdaterPreviewStale'));
        }

        [, $contents, $fileName] = $this->loadSource($record);
        $rows = $this->parser->parse($contents, $fileName);
        $row = null;

        foreach ($rows as $candidate) {
            if ($candidate['sourceRow'] === $sourceRow) {
                $row = $candidate;
                break;
            }
        }

        if ($row === null) {
            throw new NotFound($this->translate('wpUpdaterSourceRowNotFound'));
        }

        if ($row['slug'] === '') {
            throw new BadRequest($this->translate('wpUpdaterInvalidPermalink'));
        }

        $this->merger->validateFileDates($row['excelDates']);

        return [$row, $password];
    }

    private function loadRecord(string $recordId): object
    {
        $this->checkScopeAccess();
        $record = $this->entityManager->getEntityById(self::ENTITY_TYPE, $recordId);

        if (!$record) {
            throw new NotFound($this->translate('wpUpdaterRecordNotFound'));
        }

        if (!$this->acl->checkEntityRead($record) || !$this->acl->checkEntityEdit($record)) {
            throw new Forbidden();
        }

        return $record;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function loadSource(object $record): array
    {
        $sourceFileId = $record->get(self::SOURCE_FIELD . 'Id');

        if (!is_string($sourceFileId) || $sourceFileId === '') {
            throw new NotFound($this->translate('wpUpdaterSourceNotFound'));
        }

        /** @var ?Attachment $attachment */
        $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $sourceFileId);

        if (!$attachment) {
            throw new NotFound($this->translate('wpUpdaterSourceNotFound'));
        }

        if (!$this->isAttachmentLinkedToRecord($attachment, $record->getId()) ||
            !$this->acl->checkEntityRead($attachment)) {
            throw new Forbidden();
        }

        return [
            $sourceFileId,
            $this->fileStorageManager->getContents($attachment),
            $attachment->getName() ?? 'schedule-file',
        ];
    }

    private function isAttachmentLinkedToRecord(Attachment $attachment, ?string $recordId): bool
    {
        return $recordId !== null &&
            $attachment->getRelatedType() === self::ENTITY_TYPE &&
            $attachment->get('relatedId') === $recordId &&
            $attachment->getTargetField() === self::SOURCE_FIELD;
    }

    private function checkScopeAccess(): void
    {
        foreach ([Table::ACTION_READ, Table::ACTION_EDIT] as $action) {
            if (!$this->acl->checkScope(self::ENTITY_TYPE, $action)) {
                throw new Forbidden();
            }
        }
    }

    /** @return array<string, mixed> */
    private function validateInput(mixed $input, array $allowedFields): array
    {
        if ($input === null) {
            $input = (object) [];
        }

        if (!is_object($input)) {
            throw new BadRequest($this->translate('wpUpdaterRequestObjectRequired'));
        }

        $data = get_object_vars($input);
        $unknown = array_values(array_diff(array_keys($data), $allowedFields));

        if ($unknown !== []) {
            sort($unknown);
            throw new BadRequest(str_replace(
                '{field}',
                $unknown[0],
                $this->translate('wpUpdaterUnknownField')
            ));
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function requireString(array $data, string $field, int $maxLength): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new BadRequest(str_replace(
                '{field}',
                $field,
                $this->translate('wpUpdaterInvalidField')
            ));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function requireSourceRow(array $data): int
    {
        $sourceRow = $data['sourceRow'] ?? null;

        if (!is_int($sourceRow) || $sourceRow < 2) {
            throw new BadRequest($this->translate('wpUpdaterInvalidSourceRow'));
        }

        return $sourceRow;
    }

    private function clientFromRecord(object $record, string $password): WordPressCourseClient
    {
        $baseUrl = $record->get('wpBaseUrl');
        $username = $record->get('wpUsername');

        if (!is_string($baseUrl) || trim($baseUrl) === '' ||
            !is_string($username) || trim($username) === '') {
            throw new BadRequest($this->translate('wpUpdaterConnectRequired'));
        }

        return $this->createClient($baseUrl, $username, $password);
    }

    private function createClient(string $baseUrl, string $username, string $password): WordPressCourseClient
    {
        $client = ($this->clientFactory)($baseUrl, $username, $password);

        if (!$client instanceof WordPressCourseClient) {
            throw new RuntimeException('Invalid WordPress client factory result.');
        }

        return $client;
    }

    /** @return array<int, mixed>|false|null */
    private function extractExistingProgram(array $course): array|false|null
    {
        $acf = $course['acf'] ?? [];

        if (!is_array($acf)) {
            throw new BadRequest($this->translate('wpUpdaterInvalidCourseData'));
        }

        $program = $acf['program'] ?? [];

        if ($program !== false && $program !== null && !is_array($program)) {
            throw new BadRequest($this->translate('wpUpdaterInvalidCourseData'));
        }

        if (is_array($program) && !array_is_list($program)) {
            throw new BadRequest($this->translate('wpUpdaterInvalidCourseData'));
        }

        return $program;
    }

    private function today(): DateTimeImmutable
    {
        $today = ($this->todayProvider)();

        if (!$today instanceof DateTimeImmutable) {
            throw new RuntimeException('Invalid today provider result.');
        }

        return $today
            ->setTimezone(new DateTimeZone('Europe/Bucharest'))
            ->setTime(0, 0);
    }

    private function sourceRowForLog(mixed $input): ?int
    {
        if (!is_object($input) || !property_exists($input, 'sourceRow') || !is_int($input->sourceRow)) {
            return null;
        }

        return $input->sourceRow;
    }

    /** @return array<string, mixed> */
    private function execute(
        string $operation,
        string $recordId,
        ?int $sourceRow,
        Closure $callback
    ): array {
        try {
            return $callback();
        } catch (Forbidden | NotFound | BadRequest | WordPressUpdaterHttpException $exception) {
            throw $exception;
        } catch (LengthException $exception) {
            throw new WordPressUpdaterHttpException(413, $this->translateParserError($exception->getMessage()));
        } catch (InvalidArgumentException $exception) {
            throw new BadRequest($this->translateParserError($exception->getMessage()), 0, $exception);
        } catch (WordPressUrlException $exception) {
            $this->throwMappedUrlError($exception);
        } catch (WordPressClientException $exception) {
            $this->throwMappedClientError($exception);
        } catch (Throwable $exception) {
            $context = [
                'recordId' => $recordId,
                'operation' => $operation,
                'exception' => $exception,
            ];

            if ($sourceRow !== null) {
                $context['sourceRow'] = $sourceRow;
            }

            $this->log->error('WordPress updater operation failed.', $context);

            throw new WordPressUpdaterHttpException(502, $this->translate('wpUpdaterOperationFailed'));
        }
    }

    private function throwMappedUrlError(WordPressUrlException $exception): never
    {
        if (in_array($exception->getReason(), ['invalid_url', 'prohibited_destination'], true)) {
            throw new BadRequest($this->translate('wpUpdaterInvalidUrl'));
        }

        $key = self::CLIENT_ERROR_KEYS[$exception->getReason()] ?? 'wpUpdaterOperationFailed';
        throw new WordPressUpdaterHttpException(502, $this->translate($key));
    }

    private function throwMappedClientError(WordPressClientException $exception): never
    {
        if ($exception->getReason() === 'course_not_found') {
            throw new NotFound($this->translate('wpUpdaterCourseNotFound'));
        }

        if (in_array($exception->getReason(), ['invalid_url', 'prohibited_destination', 'invalid_request'], true)) {
            throw new BadRequest($this->translate('wpUpdaterInvalidUrl'));
        }

        $key = self::CLIENT_ERROR_KEYS[$exception->getReason()] ?? 'wpUpdaterOperationFailed';
        throw new WordPressUpdaterHttpException(502, $this->translate($key));
    }

    private function translateParserError(string $message): string
    {
        if (str_starts_with($message, 'Missing required columns: ')) {
            $columns = rtrim(substr($message, strlen('Missing required columns: ')), '.');

            return str_replace('{columns}', $columns, $this->translate('wpUpdaterMissingRequiredColumns'));
        }

        if (str_starts_with($message, 'Source row ') && str_contains($message, 'at most 50 columns')) {
            return $this->translate('wpUpdaterTooManyColumns');
        }

        if ($message === 'The schedule contains an invalid WordPress program date.') {
            return $this->translate('wpUpdaterInvalidDate');
        }

        $key = self::PARSER_ERROR_KEYS[$message] ?? null;

        return $key ? $this->translate($key) : $this->translate('wpUpdaterSourceInvalid');
    }

    private function translate(string $key): string
    {
        return $this->language->translateLabel($key, 'messages', self::ENTITY_TYPE);
    }
}
