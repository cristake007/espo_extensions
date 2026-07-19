<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\ProcessedLayout;
use InvalidArgumentException;

final readonly class TemplateVersionSnapshotFactory
{
    private const MAX_CHANGE_NOTE_LENGTH = 4000;

    public function __construct(private CanonicalSerializer $serializer)
    {}

    /**
     * @param array<string, mixed> $sourceSnapshot
     * @param list<string> $teamIdList
     */
    public function create(
        string $templateId,
        string $templateName,
        int $versionNumber,
        ProcessedLayout $processedLayout,
        array $sourceSnapshot,
        string $publisherId,
        DateTimeImmutable $publishedAt,
        string $assignedUserId,
        array $teamIdList = [],
        ?string $changeNote = null,
    ): TemplateVersionSnapshot {
        $templateId = $this->requireId($templateId, 'template');
        $publisherId = $this->requireId($publisherId, 'publisher');
        $assignedUserId = $this->requireId($assignedUserId, 'assigned user');
        $templateName = trim($templateName);

        if ($templateName === '' || mb_strlen($templateName) > 150) {
            throw new InvalidArgumentException('A template version requires a valid template name.');
        }

        if ($versionNumber < 1) {
            throw new InvalidArgumentException('A template version number must be positive.');
        }

        $layout = $processedLayout->layout();
        $schemaVersion = $layout['schemaVersion'] ?? null;

        if (!is_int($schemaVersion) || $schemaVersion < 1) {
            throw new InvalidArgumentException('A processed layout must expose a valid schema version.');
        }

        $normalizedSource = $layout['dataSource'] ?? null;

        if (
            !is_array($normalizedSource) ||
            $this->serializer->serialize($sourceSnapshot) !== $this->serializer->serialize($normalizedSource)
        ) {
            throw new InvalidArgumentException('The source snapshot must exactly match the normalized layout source.');
        }

        $changeNote = $changeNote === null ? null : trim($changeNote);

        if ($changeNote === '') {
            $changeNote = null;
        }

        if ($changeNote !== null && mb_strlen($changeNote) > self::MAX_CHANGE_NOTE_LENGTH) {
            throw new InvalidArgumentException('The template-version change note is too long.');
        }

        $teamIdList = array_values(array_unique(array_map(
            fn (string $teamId): string => $this->requireId($teamId, 'team'),
            $teamIdList,
        )));
        sort($teamIdList, SORT_STRING);

        $checksumInput = [
            'schemaVersion' => $schemaVersion,
            'layoutSnapshot' => $layout,
            'sourceSnapshot' => $normalizedSource,
        ];

        return new TemplateVersionSnapshot([
            'name' => sprintf('%s v%d', $templateName, $versionNumber),
            'templateId' => $templateId,
            'versionNumber' => $versionNumber,
            'schemaVersion' => $schemaVersion,
            'layoutSnapshot' => $layout,
            'sourceSnapshot' => $normalizedSource,
            'publishedById' => $publisherId,
            'publishedAt' => $publishedAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s'),
            'changeNote' => $changeNote,
            'checksum' => hash('sha256', $this->serializer->serialize($checksumInput)),
            'isCurrent' => true,
            'assignedUserId' => $assignedUserId,
            'teamsIds' => $teamIdList,
        ]);
    }

    private function requireId(string $id, string $label): string
    {
        $id = trim($id);

        if ($id === '' || strlen($id) > 64) {
            throw new InvalidArgumentException("A valid $label ID is required.");
        }

        return $id;
    }
}
