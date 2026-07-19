<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\Capability;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityNotPublishable;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Layout\CapabilityRegistry;

final readonly class PublicationValidationService
{
    public function __construct(
        private DataSourcePublicationValidator $dataSourceValidator,
        private MediaPublicationValidator $mediaValidator,
        private VariablePublicationValidator $variableValidator,
    ) {}

    public function validate(PublicationValidationContext $context): void
    {
        try {
            CapabilityRegistry::phase08()->requirePublishable($this->requiredCapabilities($context));
        } catch (CapabilityNotPublishable) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::Capability,
                'capability.notPublishable',
            );
        }

        $this->dataSourceValidator->validate($context);
        $this->mediaValidator->validate($context);
        $this->variableValidator->validate($context);
    }

    /** @return list<Capability> */
    private function requiredCapabilities(PublicationValidationContext $context): array
    {
        $layout = $context->processedLayout->layout();
        $required = [];

        foreach ($layout['capabilities'] ?? [] as $marker) {
            if (is_string($marker) && ($capability = Capability::tryFrom($marker)) !== null) {
                $required[$capability->value] = $capability;
            }
        }

        $sourceType = $layout['dataSource']['type'] ?? null;

        if ($sourceType === 'entity') {
            $required[Capability::EntitySource->value] = Capability::EntitySource;
        } elseif ($sourceType === 'spreadsheet') {
            $required[Capability::SpreadsheetSource->value] = Capability::SpreadsheetSource;
        }

        return array_values($required);
    }
}
