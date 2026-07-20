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

}
