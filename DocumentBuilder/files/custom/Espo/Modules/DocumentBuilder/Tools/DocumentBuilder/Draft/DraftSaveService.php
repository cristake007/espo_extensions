<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityCatalogue\EntitySourceEligibility;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CanonicalSerializer;
use Espo\ORM\Entity;
use InvalidArgumentException;
use stdClass;

final readonly class DraftSaveService
{
    public function __construct(
        private DraftTemplateStore $store,
        private DraftRecordAccess $access,
        private LayoutProcessorProvider $processorProvider,
        private SourceReferenceImpactAnalyzer $impactAnalyzer,
        private EntitySourceEligibility $entitySourceEligibility,
        private CanonicalSerializer $serializer,
    ) {}

    public function save(string $templateId, DraftSaveRequest $request): DraftSaveResult
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $templateId) !== 1) {
            throw new InvalidArgumentException('A valid template ID is required.');
        }

        return $this->store->updateLocked($templateId, function (Entity $template) use ($templateId, $request): DraftSaveResult {
            $this->access->requireEdit($template);

            if ($template->get('status') !== 'Draft') {
                throw new TemplateNotDraft('Only draft templates can be saved.');
            }

            $actualRevision = $template->get('revision');

            if (!is_int($actualRevision) || $actualRevision < 0) {
                throw new InvalidArgumentException('The stored draft revision is invalid.');
            }

            if ($request->expectedRevision !== $actualRevision) {
                throw new RevisionConflict($request->expectedRevision, $actualRevision);
            }

            $processed = $this->processorProvider->get()->process($request->layoutJson);
            $nextLayout = $processed->layout();
            $currentLayout = $this->normalizeStoredLayout($template->get('currentDraftLayout'));
            $previousSource = $this->requireSource($currentLayout);
            $nextSource = $this->requireSource($nextLayout);

            if ($nextSource['type'] === 'spreadsheet') {
                $this->access->requireSpreadsheetSource();
            }

            if ($nextSource['type'] === 'entity') {
                $this->entitySourceEligibility->requireEligible($nextSource['entityType']);
            }

            $sourceChanged = $this->serializer->serialize($previousSource) !==
                $this->serializer->serialize($nextSource);
            $impactReport = null;

            if ($sourceChanged) {
                $impactReport = new SourceChangeImpactReport(
                    $previousSource,
                    $nextSource,
                    $this->impactAnalyzer->analyze($currentLayout, $nextLayout),
                );

                if (!$request->confirmSourceChange) {
                    throw new SourceChangeConfirmationRequired($impactReport);
                }
            }

            $nextRevision = $actualRevision + 1;
            $sourceType = $nextSource['type'];
            $entityType = $sourceType === 'entity' ? $nextSource['entityType'] : null;
            $spreadsheetSchema = !$sourceChanged && $sourceType === 'spreadsheet'
                ? $template->get('spreadsheetSchema')
                : new stdClass();

            $template->setMultiple([
                'currentDraftLayout' => $nextLayout,
                'revision' => $nextRevision,
                'draftChangeNote' => $request->changeNote,
                'sourceType' => $sourceType,
                'entityType' => $entityType,
                'spreadsheetSchema' => $spreadsheetSchema,
                'pageSize' => $nextLayout['document']['page']['size'],
                'orientation' => $nextLayout['document']['page']['orientation'],
            ]);

            return new DraftSaveResult(
                $templateId,
                $nextRevision,
                $nextLayout,
                $nextSource,
                $request->changeNote,
                $impactReport,
            );
        });
    }

    /** @return array<string, mixed> */
    private function normalizeStoredLayout(mixed $layout): array
    {
        if (is_array($layout) && !array_is_list($layout)) {
            return $layout;
        }

        if ($layout instanceof stdClass) {
            $json = json_encode($layout, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($value) && !array_is_list($value)) {
                return $value;
            }
        }

        throw new InvalidArgumentException('The stored draft layout is invalid.');
    }

    /** @param array<string, mixed> $layout @return array<string, mixed> */
    private function requireSource(array $layout): array
    {
        $source = $layout['dataSource'] ?? null;

        if (!is_array($source) || array_is_list($source) || !is_string($source['type'] ?? null)) {
            throw new InvalidArgumentException('The normalized layout source is invalid.');
        }

        return $source;
    }
}
