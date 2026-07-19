<?php

declare(strict_types=1);

namespace Espo\Modules\DocumentBuilder\Tools\DocumentBuilder\Publication;

final class NoopVariablePublicationValidator implements VariablePublicationValidator
{
    public function validate(PublicationValidationContext $context): void
    {}
}
