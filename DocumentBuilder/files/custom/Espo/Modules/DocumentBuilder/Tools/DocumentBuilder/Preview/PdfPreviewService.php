<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Preview;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Config\SettingsProvider;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\EntityResolver\EntityResolver;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableFormatContext;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Draft\DraftRecordAccess;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\DocumentTreeBuilder;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\HtmlRenderer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\PdfRenderer;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Rendering\Tree\DocumentValue;
use InvalidArgumentException;
use stdClass;

final readonly class PdfPreviewService
{
    public function __construct(
        private PreviewTemplateStore $templates,
        private DraftRecordAccess $access,
        private PreviewRateLimit $rateLimit,
        private PdfPreviewConcurrency $concurrency,
        private SamplePreviewResolver $samples,
        private EntityResolver $entities,
        private DocumentTreeBuilder $trees,
        private HtmlRenderer $html,
        private PdfRenderer $pdf,
        private SettingsProvider $settingsProvider,
    ) {}

    public function preview(string $templateId, PreviewRequest $request): PdfPreviewResult
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/D', $templateId) !== 1) {
            throw new InvalidArgumentException('A valid PDF preview template ID is required.');
        }
        $this->rateLimit->consume($templateId, $request->mode);
        $leaseId = $this->concurrency->enter();
        try {
            $template = $this->templates->find($templateId);
            if ($template === null) throw new PreviewTemplateNotFound();
            $this->access->requireEdit($template);
            if ($template->get('status') !== 'Draft') throw new InvalidArgumentException('Only drafts can be previewed.');
            $revision = $template->get('revision');
            if (!is_int($revision) || $revision < 0) throw new InvalidArgumentException('The preview revision is invalid.');
            if ($revision !== $request->expectedRevision) throw new PreviewRevisionConflict($request->expectedRevision, $revision);
            $layout = $this->layout($template->get('currentDraftLayout'));
            $sourceType = $layout['dataSource']['type'] ?? null;
            if ($request->mode === PreviewMode::Sample && $sourceType === 'none') {
                $values = [];
            } elseif ($request->mode === PreviewMode::Sample) {
                $values = $this->sampleValues($layout);
            } elseif ($sourceType === 'entity') {
                $values = $this->recordValues($layout, (string) $request->recordId);
            } else {
                throw new InvalidArgumentException('Record PDF preview requires an entity source.');
            }
            $defaults = $layout['document']['defaults'] ?? [];
            $context = new VariableFormatContext(
                is_string($defaults['locale'] ?? null) ? $defaults['locale'] :
                    $this->settingsProvider->get()->defaultLocale(),
                is_string($defaults['timezone'] ?? null) ? $defaults['timezone'] : 'UTC',
            );
            $tree = $this->trees->build($layout, $values, $context);
            $rendered = $this->pdf->render($tree, $this->html->render($tree));

            return new PdfPreviewResult($rendered->bytes, $rendered->pageCount, $tree->warnings);
        } finally {
            $this->concurrency->leave($leaseId);
        }
    }

    /** @param array<string, mixed> $layout @return list<DocumentValue> */
    private function sampleValues(array $layout): array
    {
        return array_map(static fn (PreviewValue $value): DocumentValue =>
            new DocumentValue($value->identity, $value->value, $value->provenance?->toArray()), $this->samples->resolve($layout));
    }

    /** @param array<string, mixed> $layout @return list<DocumentValue> */
    private function recordValues(array $layout, string $recordId): array
    {
        return array_map(static fn ($value): DocumentValue =>
            new DocumentValue($value->identity, $value->value, $value->provenance->toArray()),
            $this->entities->resolve($layout, $recordId)->values,
        );
    }

    /** @return array<string, mixed> */
    private function layout(mixed $value): array
    {
        if ($value instanceof stdClass) $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException('The stored PDF preview layout is invalid.');
        return $value;
    }
}
