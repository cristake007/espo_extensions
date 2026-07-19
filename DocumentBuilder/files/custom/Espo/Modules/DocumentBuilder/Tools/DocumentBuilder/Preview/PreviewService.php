<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftRecordAccess;
use InvalidArgumentException;
use stdClass;

final readonly class PreviewService
{
    public function __construct(
        private PreviewTemplateStore $templates,
        private DraftRecordAccess $access,
        private PreviewRateLimit $rateLimit,
        private SamplePreviewResolver $samples,
        private EntityResolver $entities,
        private SystemPreviewResolver $system,
    ) {}

    public function preview(string $templateId, PreviewRequest $request): PreviewResult
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $templateId) !== 1) {
            throw new InvalidArgumentException('A valid preview template ID is required.');
        }

        $this->rateLimit->consume($templateId, $request->mode);
        $template = $this->templates->find($templateId);

        if ($template === null) {
            throw new PreviewTemplateNotFound();
        }

        $this->access->requireEdit($template);

        if ($template->get('status') !== 'Draft') {
            throw new InvalidArgumentException('Only draft templates can be previewed.');
        }

        $revision = $template->get('revision');

        if (!is_int($revision) || $revision < 0) {
            throw new InvalidArgumentException('The stored preview revision is invalid.');
        }

        if ($revision !== $request->expectedRevision) {
            throw new PreviewRevisionConflict($request->expectedRevision, $revision);
        }

        $layout = $this->layout($template->get('currentDraftLayout'));

        if ($request->mode === PreviewMode::Sample) {
            $values = ($layout['dataSource']['type'] ?? null) === 'none' ? [] : $this->samples->resolve($layout);
        } else {
            $resolved = $this->entities->resolve($layout, (string) $request->recordId);
            $values = array_map(
                static fn ($item): PreviewValue => new PreviewValue(
                    $item->identity,
                    $item->value,
                    PreviewValueOrigin::Real,
                    $item->provenance,
                ),
                $resolved->values,
            );
        }

        $values = [...$values, ...$this->system->resolve($layout)];

        return new PreviewResult($templateId, $revision, $request->mode, $values);
    }

    /** @return array<string, mixed> */
    private function layout(mixed $value): array
    {
        if ($value instanceof stdClass) {
            $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('The stored preview layout is invalid.');
        }

        return $value;
    }
}
