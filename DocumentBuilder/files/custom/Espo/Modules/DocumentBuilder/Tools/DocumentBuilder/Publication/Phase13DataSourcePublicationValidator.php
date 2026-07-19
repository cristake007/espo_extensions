<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

final class Phase13DataSourcePublicationValidator implements DataSourcePublicationValidator
{
    public function validate(PublicationValidationContext $context): void
    {
        $source = $context->processedLayout->layout()['dataSource'] ?? null;

        if (!is_array($source) || !is_string($source['type'] ?? null)) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::DataSource,
                'dataSource.invalid',
            );
        }

        if ($context->template->get('sourceType') !== $source['type']) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::DataSource,
                'dataSource.summaryMismatch',
            );
        }

        $expectedEntityType = $source['type'] === 'entity' ? ($source['entityType'] ?? null) : null;

        if ($context->template->get('entityType') !== $expectedEntityType) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::DataSource,
                'dataSource.summaryMismatch',
            );
        }
    }
}
