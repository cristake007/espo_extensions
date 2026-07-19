<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\DataSource\Variable\VariableReferenceValidator;
use Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Security\PermissionDenied;
use InvalidArgumentException;

final readonly class CompiledVariablePublicationValidator implements VariablePublicationValidator
{
    public function __construct(private VariableReferenceValidator $validator)
    {}

    public function validate(PublicationValidationContext $context): void
    {
        if ($this->containsPageCount($context->processedLayout->layout())) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::Capability,
                'renderer.pageCountUnsupported',
            );
        }

        try {
            $this->validator->validate(
                $context->processedLayout->layout(),
                $context->template->get('spreadsheetSchema'),
            );
        } catch (InvalidArgumentException | PermissionDenied) {
            throw new PublicationValidationException(
                PublicationBlockerCategory::Variable,
                'variable.unresolved',
            );
        }
    }

    private function containsPageCount(mixed $value): bool
    {
        if (!is_array($value)) return false;
        if (($value['type'] ?? null) === 'variable') {
            $identity = $value['identity'] ?? null;

            return is_array($identity) && ($identity['source'] ?? null) === 'system' &&
                ($identity['type'] ?? null) === 'system' && ($identity['path'] ?? null) === ['pageCount'];
        }

        foreach ($value as $item) {
            if ($this->containsPageCount($item)) return true;
        }

        return false;
    }
}
