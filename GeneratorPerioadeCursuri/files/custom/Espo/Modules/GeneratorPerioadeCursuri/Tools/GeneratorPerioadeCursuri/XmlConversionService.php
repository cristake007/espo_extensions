<?php

namespace Espo\Modules\GeneratorPerioadeCursuri\Tools\GeneratorPerioadeCursuri;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\Attachment;
use Throwable;
use ValueError;

class XmlConversionService
{
    private const ENTITY_TYPE = 'GeneratorPerioadeCursuriXmlConverter';
    private const SOURCE_FIELD = 'xmlScheduleFile';
    private const OUTPUT_FIELD = 'xmlConvertedFile';
    private const MIME_TYPE = 'application/xml';
    private const MIN_START_POST_ID = 1;
    private const MAX_START_POST_ID = 2147483647;

    private const PARSER_ERROR_KEYS = [
        'The source file is empty.' => 'xmlSourceEmpty',
        'The source file may be at most 20 MiB.' => 'xmlSourceTooLarge',
        'The source file must be CSV or XLSX.' => 'xmlSourceTypeInvalid',
        'The CSV file must use UTF-8.' => 'xmlCsvUtf8Required',
        'The CSV file could not be read.' => 'xmlCsvUnreadable',
        'The XLSX file does not have a valid structure.' => 'xmlXlsxStructureInvalid',
        'The XLSX file could not be read.' => 'xmlXlsxUnreadable',
        'The input file may contain at most 50 columns.' => 'xmlTooManyColumns',
        'No supported date columns found. Use Romanian or English month columns, or Luna columns (Luna 1-Luna 12).' =>
            'xmlNoSupportedMonths',
        'The input file may contain at most 5000 courses.' => 'xmlTooManyCourses',
        'No valid course data found in the input file.' => 'xmlNoValidCourseData',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private XmlScheduleParser $parser,
        private MecXmlBuilder $builder,
        private Acl $acl,
        private Language $language,
        private Log $log
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(string $id): array
    {
        $newAttachment = null;
        $recordUpdated = false;

        try {
            $this->checkScopeAccess();
            $record = $this->entityManager->getEntityById(self::ENTITY_TYPE, $id);

            if (!$record) {
                throw new BadRequest($this->translate('xmlConverterNotFound'));
            }

            if (!$this->acl->checkEntityRead($record) || !$this->acl->checkEntityEdit($record)) {
                throw new Forbidden();
            }

            $sourceFileId = $record->get(self::SOURCE_FIELD . 'Id');

            if (!is_string($sourceFileId) || $sourceFileId === '') {
                throw new BadRequest($this->translate('xmlSourceRequired'));
            }

            /** @var ?Attachment $sourceAttachment */
            $sourceAttachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $sourceFileId);

            if (!$sourceAttachment) {
                throw new BadRequest($this->translate('xmlSourceNotFound'));
            }

            if (!$this->isAttachmentLinkedToRecord($sourceAttachment, $record->getId(), self::SOURCE_FIELD) ||
                !$this->acl->checkEntityRead($sourceAttachment)) {
                throw new Forbidden();
            }

            $startPostId = $this->parseStartPostId($record->get('startPostId'));
            $sourceFileName = $sourceAttachment->getName() ?? 'schedule-file';
            $contents = $this->fileStorageManager->getContents($sourceAttachment);

            try {
                $events = $this->parser->parse($contents, $sourceFileName);
            } catch (BadRequest $e) {
                throw new BadRequest($this->translateParserError($e->getMessage()), $e->getCode(), $e);
            }

            $xml = $this->builder->build($events, $startPostId);
            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Bucharest'));
            $convertedAt = $now
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
            $fileName = 'formatted_courses_' . $now->format('Y') . '.xml';
            $previousAttachmentId = $record->get(self::OUTPUT_FIELD . 'Id');

            /** @var Attachment $newAttachment */
            $newAttachment = $this->entityManager
                ->getRDBRepositoryByClass(Attachment::class)
                ->getNew();

            $newAttachment
                ->setName($fileName)
                ->setType(self::MIME_TYPE)
                ->setRole(Attachment::ROLE_EXPORT_FILE)
                ->setTargetField(self::OUTPUT_FIELD)
                ->setRelated($record)
                ->setContents($xml);

            $this->entityManager->saveEntity($newAttachment);

            $record->set(self::OUTPUT_FIELD . 'Id', $newAttachment->getId());
            $record->set('xmlConvertedAt', $convertedAt);
            $this->entityManager->saveEntity($record);
            $recordUpdated = true;

            $this->removePreviousAttachment($previousAttachmentId, $record->getId(), $newAttachment->getId());

            return [
                'success' => true,
                'eventCount' => count($events),
                'attachmentId' => $newAttachment->getId(),
                'timestamp' => $convertedAt,
                'filename' => $fileName,
                'downloadUrl' => '?entryPoint=download&id=' . $newAttachment->getId(),
                'record' => [
                    'id' => $record->getId(),
                    'xmlConvertedFileId' => $newAttachment->getId(),
                    'xmlConvertedAt' => $convertedAt,
                ],
            ];
        } catch (Forbidden | BadRequest $e) {
            if ($newAttachment && !$recordUpdated) {
                $this->removeFailedAttachment($newAttachment);
            }

            throw $e;
        } catch (ValueError $e) {
            if ($newAttachment && !$recordUpdated) {
                $this->removeFailedAttachment($newAttachment);
            }

            throw new BadRequest($this->translate('xmlConversionUnable'), 400, $e);
        } catch (Throwable $e) {
            if ($newAttachment && !$recordUpdated) {
                $this->removeFailedAttachment($newAttachment);
            }

            $this->log->error('XML conversion failed.', [
                'recordId' => $id,
                'exception' => $e,
            ]);

            throw new BadRequest($this->translate('xmlConversionUnable'), 400, $e);
        }
    }

    private function checkScopeAccess(): void
    {
        foreach ([Table::ACTION_CREATE, Table::ACTION_READ, Table::ACTION_EDIT] as $action) {
            if (!$this->acl->checkScope(self::ENTITY_TYPE, $action)) {
                throw new Forbidden();
            }
        }
    }

    private function parseStartPostId(mixed $value): int
    {
        if (is_int($value)) {
            $startPostId = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $startPostId = (int) trim($value);
        } else {
            throw new BadRequest($this->translate('xmlStartPostIdInvalid'));
        }

        if ($startPostId < self::MIN_START_POST_ID || $startPostId > self::MAX_START_POST_ID) {
            throw new BadRequest($this->translate('xmlStartPostIdInvalid'));
        }

        return $startPostId;
    }

    private function isAttachmentLinkedToRecord(
        Attachment $attachment,
        ?string $recordId,
        string $field
    ): bool {
        return $recordId !== null &&
            $attachment->getRelatedType() === self::ENTITY_TYPE &&
            $attachment->get('relatedId') === $recordId &&
            $attachment->getTargetField() === $field;
    }

    private function removePreviousAttachment(mixed $attachmentId, ?string $recordId, ?string $newAttachmentId): void
    {
        if (!is_string($attachmentId) || $attachmentId === '' || $attachmentId === $newAttachmentId) {
            return;
        }

        /** @var ?Attachment $attachment */
        $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $attachmentId);

        if (!$attachment) {
            return;
        }

        if (!$this->isAttachmentLinkedToRecord($attachment, $recordId, self::OUTPUT_FIELD)) {
            $this->log->warning('Previous XML attachment was not removed because it is not linked to the converter record.', [
                'recordId' => $recordId,
                'attachmentId' => $attachmentId,
            ]);

            return;
        }

        try {
            $this->entityManager->removeEntity($attachment);
        } catch (Throwable $e) {
            $this->log->warning('Previous XML attachment could not be removed.', [
                'recordId' => $recordId,
                'attachmentId' => $attachmentId,
                'exception' => $e,
            ]);
        }
    }

    private function removeFailedAttachment(Attachment $attachment): void
    {
        try {
            $this->entityManager->removeEntity($attachment);
        } catch (Throwable $e) {
            $this->log->warning('Incomplete XML attachment could not be removed.', [
                'attachmentId' => $attachment->getId(),
                'exception' => $e,
            ]);
        }
    }

    private function translateParserError(string $message): string
    {
        if (preg_match('/^Duplicate normalized header: (.+)\.$/', $message, $matches)) {
            return str_replace('{header}', $matches[1], $this->translate('xmlDuplicateHeader'));
        }

        if (preg_match('/^Source row (\d+) has conflicting values for title and nume curs\.$/', $message, $matches)) {
            return str_replace('{row}', $matches[1], $this->translate('xmlTitleConflict'));
        }

        if (str_starts_with($message, 'Missing required columns: ')) {
            $columns = substr($message, strlen('Missing required columns: '));

            return str_replace('{columns}', $columns, $this->translate('xmlMissingRequiredColumns'));
        }

        $key = self::PARSER_ERROR_KEYS[$message] ?? null;

        return $key ? $this->translate($key) : $this->translate('xmlConversionUnable');
    }

    private function translate(string $key): string
    {
        return $this->language->translateLabel($key, 'messages', self::ENTITY_TYPE);
    }
}
