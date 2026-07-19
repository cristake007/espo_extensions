<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\LayoutProcessorProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\RevisionConflict;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshot;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\TemplateVersion\TemplateVersionSnapshotFactory;
use Espo\ORM\Entity;
use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class PublicationService
{
    public function __construct(
        private PublicationStore $store,
        private PublicationRecordAccess $access,
        private LayoutProcessorProvider $processorProvider,
        private PublicationValidationService $validationService,
        private TemplateVersionSnapshotFactory $snapshotFactory,
        private PublicationActor $actor,
    ) {}

    public function publish(string $templateId, PublicationRequest $request): PublicationResult
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $templateId) !== 1) {
            throw new InvalidArgumentException('A valid template ID is required.');
        }

        return $this->store->publishLocked(
            $templateId,
            function (Entity $template, int $versionNumber, array $teamIds) use (
                $templateId,
                $request,
            ): TemplateVersionSnapshot {
                $this->access->requirePublish($template);

                if ($template->get('status') !== 'Draft') {
                    throw new PublicationConflict('Only draft templates can be published.');
                }

                $actualRevision = $template->get('revision');

                if (!is_int($actualRevision) || $actualRevision < 0) {
                    throw new InvalidArgumentException('The stored draft revision is invalid.');
                }

                if ($request->expectedRevision !== $actualRevision) {
                    throw new RevisionConflict($request->expectedRevision, $actualRevision);
                }

                $processedLayout = $this->processorProvider->get()->process(
                    $this->encodeStoredLayout($template->get('currentDraftLayout')),
                );
                $this->validationService->validate(
                    new PublicationValidationContext($template, $processedLayout),
                );

                $name = $template->get('name');
                $assignedUserId = $template->get('assignedUserId');

                if (!is_string($name) || !is_string($assignedUserId)) {
                    throw new InvalidArgumentException('The template publication metadata is invalid.');
                }

                $source = $processedLayout->layout()['dataSource'] ?? null;

                if (!is_array($source)) {
                    throw new InvalidArgumentException('The normalized layout source is invalid.');
                }

                $changeNote = $request->changeNote;

                if ($changeNote === null) {
                    $storedChangeNote = $template->get('draftChangeNote');
                    $changeNote = is_string($storedChangeNote) ? $storedChangeNote : null;
                }

                return $this->snapshotFactory->create(
                    $templateId,
                    $name,
                    $versionNumber,
                    $processedLayout,
                    $source,
                    $this->actor->id(),
                    $this->actor->publishedAt(),
                    $assignedUserId,
                    $teamIds,
                    $changeNote,
                );
            },
        );
    }

    private function encodeStoredLayout(mixed $layout): string
    {
        if (!is_array($layout) && !$layout instanceof stdClass) {
            throw new InvalidArgumentException('The stored draft layout is invalid.');
        }

        try {
            return json_encode(
                $layout,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('The stored draft layout could not be encoded.');
        }
    }
}
