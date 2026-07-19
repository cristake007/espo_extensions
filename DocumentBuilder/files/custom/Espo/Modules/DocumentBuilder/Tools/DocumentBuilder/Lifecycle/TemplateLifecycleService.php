<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Lifecycle;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\LayoutProcessorProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\ORM\Entity;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class TemplateLifecycleService
{
    private const TEMPLATE_NAME_MAX_LENGTH = 150;

    public function __construct(
        private TemplateLifecycleStore $store,
        private TemplateLifecycleAccess $access,
        private LayoutProcessorProvider $processorProvider,
        private CanonicalSerializer $serializer,
    ) {}

    public function duplicate(string $templateId, DuplicateTemplateRequest $request): TemplateLifecycleResult
    {
        $this->requireId($templateId, 'template');

        return $this->store->duplicateLocked(
            $templateId,
            function (Entity $source, array $teamIds) use ($request): TemplateDuplicateData {
                $this->access->requireDuplicate($source);
                $this->requireRevision($source, $request->expectedRevision);
                $processed = $this->processorProvider->get()->process(
                    $this->encodeJson($source->get('currentDraftLayout'), 'draft layout'),
                );
                $layout = $processed->layout();
                $sourceDescriptor = $this->sourceDescriptor($layout);
                $name = $this->duplicateName($source->get('name'), $request->name);
                $assignedUserId = $source->get('assignedUserId');

                if (!is_string($assignedUserId) || $assignedUserId === '') {
                    throw new InvalidArgumentException('The source template assignment is invalid.');
                }

                return new TemplateDuplicateData([
                    'name' => $name,
                    'category' => $source->get('category'),
                    'description' => $source->get('description'),
                    'status' => 'Draft',
                    'sourceType' => $sourceDescriptor['type'],
                    'entityType' => $sourceDescriptor['type'] === 'entity'
                        ? $sourceDescriptor['entityType']
                        : null,
                    'spreadsheetSchema' => $this->copyJsonObject($source->get('spreadsheetSchema')),
                    'currentDraftLayout' => $layout,
                    'revision' => 0,
                    'draftChangeNote' => null,
                    'pageSize' => $layout['document']['page']['size'],
                    'orientation' => $layout['document']['page']['orientation'],
                    'isActive' => true,
                    'currentPublishedVersionId' => null,
                    'assignedUserId' => $assignedUserId,
                ], $teamIds);
            },
        );
    }

    public function archive(string $templateId, TemplateLifecycleRequest $request): TemplateLifecycleResult
    {
        $this->requireId($templateId, 'template');

        return $this->store->updateLocked(
            $templateId,
            function (Entity $template) use ($templateId, $request): TemplateLifecycleResult {
                $this->access->requireArchive($template);
                $actualRevision = $this->requireRevision($template, $request->expectedRevision);

                if ($template->get('status') === 'Archived') {
                    throw new TemplateLifecycleConflict('The template is already archived.');
                }

                $template->setMultiple([
                    'status' => 'Archived',
                    'isActive' => false,
                ]);

                return new TemplateLifecycleResult('archive', $templateId, 'Archived', $actualRevision);
            },
        );
    }

    public function createDraftFromVersion(
        string $templateId,
        DraftFromVersionRequest $request,
    ): TemplateLifecycleResult {
        $this->requireId($templateId, 'template');
        $this->requireId($request->versionId, 'version');

        return $this->store->restoreLocked(
            $templateId,
            $request->versionId,
            function (Entity $template): void {
                $this->access->requireDraftFromVersionTemplate($template);
            },
            function (Entity $template, Entity $version) use (
                $templateId,
                $request,
            ): TemplateLifecycleResult {
                $this->access->requireVersionRead($version);
                $actualRevision = $this->requireRevision($template, $request->expectedRevision);

                if ($template->get('status') !== 'Published') {
                    throw new TemplateLifecycleConflict(
                        'Only a published template can create a draft from version history.',
                    );
                }

                $processed = $this->processorProvider->get()->process(
                    $this->encodeJson($version->get('layoutSnapshot'), 'version layout snapshot'),
                );
                $layout = $processed->layout();
                $source = $this->sourceDescriptor($layout);
                $sourceSnapshot = $this->jsonObjectArray(
                    $version->get('sourceSnapshot'),
                    'version source snapshot',
                );

                if (
                    $this->serializer->serialize($sourceSnapshot) !== $this->serializer->serialize($source)
                ) {
                    throw new TemplateLifecycleConflict('The version source snapshot is inconsistent.');
                }

                $versionNumber = $version->get('versionNumber');

                if (!is_int($versionNumber) || $versionNumber < 1) {
                    throw new TemplateLifecycleConflict('The version number is invalid.');
                }

                $changeNote = $request->changeNote === null
                    ? sprintf('Restored from version %d.', $versionNumber)
                    : trim($request->changeNote);
                $nextRevision = $actualRevision + 1;

                $template->setMultiple([
                    'status' => 'Draft',
                    'sourceType' => $source['type'],
                    'entityType' => $source['type'] === 'entity' ? $source['entityType'] : null,
                    'spreadsheetSchema' => $source['type'] === 'spreadsheet'
                        ? $this->copyJsonObject($template->get('spreadsheetSchema'))
                        : new stdClass(),
                    'currentDraftLayout' => $layout,
                    'revision' => $nextRevision,
                    'draftChangeNote' => $changeNote === '' ? null : $changeNote,
                    'pageSize' => $layout['document']['page']['size'],
                    'orientation' => $layout['document']['page']['orientation'],
                    'isActive' => true,
                ]);

                $versionId = $version->getId();

                if ($versionId === null || $versionId === '') {
                    throw new TemplateLifecycleConflict('The version ID is invalid.');
                }

                return new TemplateLifecycleResult(
                    'draftFromVersion',
                    $templateId,
                    'Draft',
                    $nextRevision,
                    $versionId,
                );
            },
        );
    }

    private function requireRevision(Entity $template, int $expectedRevision): int
    {
        $actualRevision = $template->get('revision');

        if (!is_int($actualRevision) || $actualRevision < 0) {
            throw new InvalidArgumentException('The stored template revision is invalid.');
        }

        if ($expectedRevision !== $actualRevision) {
            throw new RevisionConflict($expectedRevision, $actualRevision);
        }

        return $actualRevision;
    }

    private function duplicateName(mixed $sourceName, ?string $requestedName): string
    {
        if (!is_string($sourceName) || trim($sourceName) === '') {
            throw new InvalidArgumentException('The source template name is invalid.');
        }

        $name = $requestedName === null ? 'Copy of ' . trim($sourceName) : trim($requestedName);

        if ($requestedName === null) {
            $name = mb_substr($name, 0, self::TEMPLATE_NAME_MAX_LENGTH);
        }

        if ($name === '' || mb_strlen($name) > self::TEMPLATE_NAME_MAX_LENGTH) {
            throw new InvalidArgumentException('The duplicated template name is invalid.');
        }

        return $name;
    }

    /** @param array<string, mixed> $layout @return array<string, mixed> */
    private function sourceDescriptor(array $layout): array
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || !is_string($source['type'] ?? null)) {
            throw new InvalidArgumentException('The normalized layout source is invalid.');
        }

        return $source;
    }

    private function encodeJson(mixed $value, string $label): string
    {
        if (!is_array($value) && !$value instanceof stdClass) {
            throw new InvalidArgumentException("The $label is invalid.");
        }

        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException("The $label could not be encoded.");
        }
    }

    private function copyJsonObject(mixed $value): stdClass
    {
        if ($value === null || $value === []) {
            return new stdClass();
        }

        try {
            $copy = json_decode(
                json_encode($value, JSON_THROW_ON_ERROR),
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('The spreadsheet schema could not be copied.');
        }

        if (!$copy instanceof stdClass) {
            throw new InvalidArgumentException('The spreadsheet schema is not a JSON object.');
        }

        return $copy;
    }

    /** @return array<string, mixed> */
    private function jsonObjectArray(mixed $value, string $label): array
    {
        if (is_array($value) && !array_is_list($value)) {
            return $value;
        }

        if ($value instanceof stdClass) {
            try {
                $array = json_decode(
                    json_encode($value, JSON_THROW_ON_ERROR),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                throw new InvalidArgumentException("The $label could not be decoded.");
            }

            if (is_array($array) && !array_is_list($array)) {
                return $array;
            }
        }

        throw new InvalidArgumentException("The $label is invalid.");
    }

    private function requireId(string $id, string $label): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $id) !== 1) {
            throw new InvalidArgumentException("A valid $label ID is required.");
        }
    }
}
